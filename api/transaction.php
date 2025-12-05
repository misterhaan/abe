<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for transaction API requests.
 * @author misterhaan
 */
class TransactionApi extends Api {
	private const MAX = 50;

	/**
	 * Return the documentation for the transaction API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'list', 'List transactions in order. Returns a limited set because there are usually too many transactions to load all at once.', 'query string', 'Filters specfying which transactions are elegible to list.');
		$endpoint->PathParameters[] = new ParameterDocumentation('skip', 'int', 'Number of transactions to skip. Default 0.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('accts', 'string', 'IDs of accounts to include as a comma-delimited string. Default is all accounts.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('cats', 'string', 'IDs of categories to include as a comma-delimited string. Default is all categories.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('datestart', 'string', 'Earliest transaction date to include. YYYY-MM-DD format. Default is earliest in database.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('dateend', 'string', 'Latest transaction date to include. YYYY-MM-DD format. Default is latest in database.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('minamount', 'float', 'Minimum transaction amount to include. Negative-amount transactions are treated as positive amounts for this filter. Default is all amounts.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('search', 'string', 'If specified, only transactions that include this text in the name are included.');

		$endpoints[] = $endpoint = new EndpointDocumentation('PATCH', 'save', 'Save changes to a transaction. May create new categories.', 'multipart');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'int', 'ID of the transaction to save.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'Name of the transaction.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('notes', 'string', 'Notes for the transaction. Default none.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('catnames', 'string', 'Names of categories for this transaction. Array parallel with <code>catamounts</code>. Default none.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('catamounts', 'float', 'Amounts for each category. Array parallel with <code>catnames</code> and should add up to the transaction amount. Default none.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'parseFile', 'Parse the transactions in a file.', 'multipart');
		$endpoint->PathParameters[] = new ParameterDocumentation('acctid', 'int', 'ID of the bank account the file belongs to. Used to look up which bank it’s from in order to correctly parse the file.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('transfile', 'file', 'File containing transactions to parse.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'import', 'Import transactions into an account. Used to save results of <a href="#POST-parseFile"><code>parseFile</code></a>.', 'multipart');
		$endpoint->PathParameters[] = new ParameterDocumentation('acctid', 'int', 'ID of the bank account the transactions should import to.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('transactions', 'array', 'Array of transactions to import. Each item should have the same set of properties as the results from <a href="#POST-parseFile"><code>parseFile</code></a>.', true);

		return $endpoints;
	}

	/**
	 * Get transactions in order according to filter parameters.
	 */
	protected static function GET_list(array $params): void {
		$limit = self::MAX + 1;
		$skip = +array_shift($params);

		$accountids = isset($_GET['accts']) && $_GET['accts'] ? $_GET['accts'] : null;
		$categoryids = isset($_GET['cats']) && ($_GET['cats'] || $_GET['cats'] === '0') ? $_GET['cats'] : null;
		$datestart = isset($_GET['datestart']) && $_GET['datestart'] ? $_GET['datestart'] : null;
		$dateend = isset($_GET['dateend']) && $_GET['dateend'] ? $_GET['dateend'] : null;
		$minamount = isset($_GET['minamount']) && $_GET['minamount'] ? $_GET['minamount'] : null;
		$search = isset($_GET['search']) && $_GET['search'] ? trim($_GET['search']) : null;

		$db = self::RequireLatestDatabase();
		$data = new stdClass();
		try {
			$select = $db->prepare('call GetTransactions(?, ?, ?, ?, ?, ?, ?, ?)');
			$select->bind_param('iissssds', $limit, $skip, $accountids, $categoryids, $datestart, $dateend, $minamount, $search);
			$select->execute();
			$transactions = $select->get_result();
			$data->dates = [];
			$count = 0;
			while ($transaction = $transactions->fetch_object())
				if (++$count > self::MAX)
					$data->more = true;
				else {
					if (!count($data->dates) || $data->dates[count($data->dates) - 1]->date != $transaction->posted)
						$data->dates[] = (object)['date' => $transaction->posted, 'displayDate' => date('F j, Y (D)', strtotime($transaction->posted . ' 12:00 PM')), 'transactions' => []];

					$transaction->id += 0;
					$transaction->amount += 0;

					$transaction->categories = [];
					if (+$transaction->splitcat) {
						$sc_names = explode("\n", $transaction->sc_names);
						$sc_amounts = explode("\n", $transaction->sc_amounts);
						$remaining = $transaction->amount;
						for ($i = 0; $i < count($sc_names); $i++) {
							$transaction->categories[] = (object)['name' => $sc_names[$i], 'amount' => +$sc_amounts[$i]];
							$remaining -= +$sc_amounts[$i];
						}
						if (round($remaining, 2) != 0)  // save should prevent this case
							$transaction->categories[] = (object)['name' => null, 'amount' => $remaining];
					} else
						$transaction->categories[] = (object)['name' => $transaction->category, 'amount' => +$transaction->amount];
					unset($transaction->splitcat, $transaction->sc_names, $transaction->sc_amounts, $transaction->category);

					require_once 'format.php';
					$transaction->amountDisplay = Format::Amount($transaction->amount);
					$data->dates[count($data->dates) - 1]->transactions[] = $transaction;
				}
			$select->close();
			self::Success($data);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up transactions', $mse);
		}
	}

	/**
	 * Save changes to a transaction.
	 */
	protected static function PATCH_save(array $params): void {
		$id = +array_shift($params);
		$patch = self::ParseRequestText();
		if (!$id || !isset($patch['name']) || !($name = trim($patch['name'])))
			self::NeedMoreInfo('Parameters “id” and “name” are required.');
		$catname = null;
		$splitcat = false;
		$notes = isset($patch['notes']) ? trim($patch['notes']) : '';
		if (isset($patch['catnames'], $patch['catamounts']) && is_array($patch['catnames']) && is_array($patch['catamounts']) && count($patch['catnames']) == count($patch['catamounts'])) {
			$splitcat = count($patch['catnames']) > 1;
			if (!$splitcat)
				$catname = trim($patch['catnames'][0]);
		}
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update transactions set name=?, notes=?, category=GetCategoryID(?), splitcat=?, reviewed=1 where id=? limit 1');
			$update->bind_param('sssii', $name, $notes, $catname, $splitcat, $id);
			$update->execute();
			$update->close();

			$delete = $db->prepare('delete from splitcats where transaction=?');
			$delete->bind_param('i', $id);
			$delete->execute();
			$delete->close();

			if ($splitcat) {
				$insert = $db->prepare('insert into splitcats (transaction, category, amount) values (?, GetCategoryID(?), ?)');
				$insert->bind_param('isd', $id, $name, $amount);
				for ($i = 0; $i < count($patch['catnames']); $i++) {
					$name = trim($patch['catnames'][$i]);
					if ($amount = +$patch['catamounts'][$i])
						$insert->execute();
				}
				$insert->close();
			}

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error saving transaction', $mse);
		}
	}

	/**
	 * Translate uploaded file into a list of transactions for preview.
	 */
	protected static function POST_parseFile(array $params): void {
		// TODO:  accept bankclass or bankid instead of acctid
		// TODO:  automatic categorization engine
		$acctid = +array_shift($params);
		if (!$acctid)
			self::NeedMoreInfo('Account ID is required.');
		if (!file_exists($_FILES['transfile']['tmp_name']) || !is_uploaded_file($_FILES['transfile']['tmp_name']))
			self::NeedMoreInfo('Transaction file is required.');
		$db = self::RequireLatestDatabase();
		try {
			$bankclass = self::LookupBank($acctid, $db);
			self::Success($bankclass::ParseTransactions($_FILES['transfile']['name'], $_FILES['transfile']['tmp_name'], $acctid, $db));
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error parsing transaction file', $mse);
		} finally {
			unlink($_FILES['transfile']['tmp_name']);
		}
	}

	/**
	 * Save previewed transactions.
	 */
	protected static function POST_import(array $params): void {
		$account = +array_shift($params);
		if (!$account)
			self::NeedMoreInfo('Account ID is required.');
		if (!is_array($transactions = self::ReadRequestJson()) || !count($transactions))
			self::NeedMoreInfo('No transactions to save.');

		$newest = new stdClass();
		$net = 0;
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$insert = $db->prepare('insert into transactions (account, extid, transdate, posted, name, amount, city, state, zip, notes) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
			$insert->bind_param('issssdssss', $account, $extid, $transdate, $posted, $name, $amount, $city, $state, $zip, $notes);
			$newest->sortable = '';
			foreach ($transactions as $trans) {
				$trans = (object)$trans;
				$extid = $trans->extid ?: null;
				$transdate = $trans->transdate ?: null;
				$posted = $trans->posted;
				if ($posted > $newest->sortable)
					$newest->sortable = $posted;
				$name = $trans->name;
				$amount = $trans->amount;
				$city = $trans->city ?: null;
				$state = $trans->state ?: null;
				$zip = $trans->zip ?: null;
				$notes = $trans->notes;
				$insert->execute();
				$net += $amount;
			}
			$insert->close();

			$update = $db->prepare('update accounts set updated=unix_timestamp(now()), balance=balance+? where id=? limit 1');
			$update->bind_param('di', $net, $account);
			$update->execute();
			$update->close();

			$db->commit();
			$newest->display = date('M jS', strtotime($newest->sortable));
			self::Success($newest);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error importing transactions', $mse);
		}
	}

	/**
	 * Look up the class for the account's bank.
	 * @param integer $acctid Account ID
	 * @param mysqli $db Database connection object
	 * @return string Class name for account's bank class
	 */
	private static function LookupBank(int $acctid, mysqli $db): string {
		$select = $db->prepare('select b.class from accounts as a left join banks as b on b.id=a.bank where a.id=? limit 1');
		$select->bind_param('i', $acctid);
		$select->execute();
		$select->bind_result($class);
		if ($select->fetch()) {
			$select->close();
			require_once "$class.php";
			return $class;
		}
		$select->close();
		self::NotFound('Account not found.');
		return "";
	}
}
TransactionApi::Respond();
