<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for transaction API requests.
 * @author misterhaan
 */
class TransactionApi extends abeApi {
	const MAX = 50;

	/**
	 * Write out the documentation for the transaction file import API controller.
	 * The page is already opened with an h1 header, and will be closed after the
	 * call completes.
	 */
	protected static function ShowDocumentation() {
?>
		<h2 id=POSTimport>POST import</h2>
		<p>
			Import transactions into an account. Used to save results of
			<a href="#POSTparseFile"><code>parseFile</code></a>.
		</p>
		<dl class=parameters>
			<dt>acctid</dt>
			<dd>
				ID of the bank account the transactions should import to.
			</dd>
			<dt>transactions</dt>
			<dd>
				Array of transactions to import. Each item should have the same set
				of properties as the results from
				<a href="#POSTparseFile"><code>parseFile</code></a>.
			</dd>
			<dt>net</dt>
			<dd>
				Amount to adjust the account balance. Should be the total of all
				transactions. Optional; default zero.
			</dd>
		</dl>

		<h2 id=GETlist>GET list</h2>
		<p>
			List transactions in order. Returns a limited set because there are
			usually too many transactions to load all at once.
		</p>
		<dl class=parameters>
			<dt>oldest</dt>
			<dd>
				Date of the oldest transaction already loaded. Transactions returned
				will be from this date or earlier. YYYY-MM-DD format. Optional;
				default is to start with the most recent transaction.
			</dd>
			<dt>oldid</dt>
			<dd>
				ID of the oldest transaction already loaded. Transactions from the
				same day as <code>oldest</code> are only included if their ID is
				older than this value. Ignored if <code>oldest</code> is provided.
			</dd>
			<dt>accts</dt>
			<dd>
				IDs of accounts to include as a comma-delimited string. Optional;
				default is all accounts.
			</dd>
			<dt>cats</dt>
			<dd>
				IDs of categories to include as a comma-delimited string. Optional;
				default is all categories.
			</dd>
			<dt>datestart</dt>
			<dd>
				Earliest transaction date to include. YYYY-MM-DD format. Optional;
				default is earliest in database.
			</dd>
			<dt>dateend</dt>
			<dd>
				Latest transaction date to include. YYYY-MM-DD format. Optional;
				default is latest in database.
			</dd>
			<dt>minamount</dt>
			<dd>
				Minimum transaction amount to include. Negative-amount transactions
				are treated as positive amounts for this filter. Optional; default
				is all amounts.
			</dd>
			<dt>search</dt>
			<dd>
				Optional. If specified, only transactions that include this text in
				the name are included.
			</dd>
		</dl>

		<h2 id=POSTparseFile>POST parseFile</h2>
		<p>
			Parse the transactions in a file. The file must be provided as a form
			upload named <code>transfile</code>.
		</p>
		<dl class=parameters>
			<dt>acctid</dt>
			<dd>
				ID of the bank account the file belongs to. Used to look up which
				bank it’s from in order to correctly parse the file.
			</dd>
		</dl>

		<h2 id=POSTsave>POST save</h2>
		<p>
			Save changes to a transaction. May create new categories.
		</p>
		<dl class=parameters>
			<dt>id</dt>
			<dd>ID of the transaction to save.</dd>
			<dt>name</dt>
			<dd>Name of the transaction.</dd>
			<dt>notes</dt>
			<dd>Notes for the transaction. Optional; default none.</dd>
			<dt>catnames</dt>
			<dd>
				Names of categories for this transaction. Array parallel with
				<code>catamounts</code>. Optional; default none.
			</dd>
			<dt>catamounts</dt>
			<dd>
				Amounts for each category. Array parallel with <code>catnames</code>
				and should add up to the transaction amount. Optional; default none.
			</dd>
		</dl>
<?php
	}

	/**
	 * Save previewed transactions.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function importAction(abeAjax $ajax) {
		if (isset($_POST['acctid']) && $account = +$_POST['acctid'])
			if (isset($_POST['transactions']) && is_array($_POST['transactions']) && $count = count($_POST['transactions'])) {
				$db = self::RequireLatestDatabase($ajax);
				$db->autocommit(false);
				if ($ins = $db->prepare('insert into transactions (account, extid, transdate, posted, name, amount, city, state, zip, notes) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'))
					if ($ins->bind_param('issssdssss', $account, $extid, $transdate, $posted, $name, $amount, $city, $state, $zip, $notes)) {
						$ajax->Data->newestSortable = '';
						foreach ($_POST['transactions'] as $trans) {
							$trans = (object)$trans;
							$extid = $trans->extid ? $trans->extid : null;
							$transdate = $trans->transdate ? $trans->transdate : null;
							$posted = $trans->posted;
							if ($posted > $ajax->Data->newestSortable)
								$ajax->Data->newestSortable = $posted;
							$name = $trans->name;
							$amount = $trans->amount;
							$city = $trans->city ? $trans->city : null;
							$state = $trans->state ? $trans->state : null;
							$zip = $trans->zip ? $trans->zip : null;
							$notes = $trans->notes;
							if ($ins->execute())
								$count--;
						}
						$ajax->Data->newestDisplay = date('M jS', strtotime($ajax->Data->newestSortable));
						if ($count)
							$ajax->Fail('Error saving ' . $count . ' of ' . count($_POST['transactions']) . ' transactions:  ' . $ins->errno . ' ' . $ins->error);
						$ins->close();
						if ($db->real_query('update accounts set updated=\'' . +time() . '\', balance=balance+\'' . +$_POST['net'] . '\' where id=\'' . +$account . '\' limit 1'))
							$db->commit();
						else
							$ajax->Fail('Error updating account:  ' . $db->errno . ' ' . $db->error);
					} else
						$ajax->Fail('Error binding import parameters:  ' . $ins->errno . ' ' . $ins->error);
				else
					$ajax->Fail('Database error preparing to import transactions:  ' . $db->errno . ' ' . $db->error);
			} else
				$ajax->Fail('No transactions to save.');
		else
			$ajax->Fail('Account not specified.');
	}

	/**
	 * Get transactions in order according to filter parameters.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction(abeAjax $ajax) {
		$db = self::RequireLatestDatabase($ajax);
		if ($select = $db->prepare('call GetTransactions(?, ?, ?, ?, ?, ?, ?, ?, ?)'))
			if ($select->bind_param('isissssds', $maxcount, $oldest, $oldid, $accountids, $categoryids, $datestart, $dateend, $minamount, $search)) {
				$maxcount = self::MAX;
				$oldest = isset($_GET['oldest']) ? $_GET['oldest'] : null;
				$oldid = isset($_GET['oldid']) ? $_GET['oldid'] : null;
				$accountids = isset($_GET['accts']) && $_GET['accts'] ? $_GET['accts'] : null;
				$categoryids = isset($_GET['cats']) && ($_GET['cats'] || $_GET['cats'] === '0') ? $_GET['cats'] : null;
				$datestart = isset($_GET['datestart']) && $_GET['datestart'] ? $_GET['datestart'] : null;
				$dateend = isset($_GET['dateend']) && $_GET['dateend'] ? $_GET['dateend'] : null;
				$minamount = isset($_GET['minamount']) && $_GET['minamount'] ? $_GET['minamount'] : null;
				$search = isset($_GET['search']) && $_GET['search'] ? trim($_GET['search']) : null;
				if ($select->execute())
					if ($transactions = $select->get_result()) {
						$ajax->Data->dates = [];
						while ($transaction = $transactions->fetch_object()) {
							if (!count($ajax->Data->dates) || $ajax->Data->dates[count($ajax->Data->dates) - 1]->date != $transaction->posted)
								$ajax->Data->dates[] = (object)['date' => $transaction->posted, 'displayDate' => date('F j, Y (D)', strtotime($transaction->posted . ' 12:00 PM')), 'transactions' => []];

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

							$transaction->amountDisplay = Format::Amount($transaction->amount);
							$ajax->Data->dates[count($ajax->Data->dates) - 1]->transactions[] = $transaction;
							$oldest = $transaction->posted;
							$oldid = $transaction->id;
						}
						$ajax->Data->more = false;
						$maxcount = 1;
						$db->next_result();  // get past the extra stored procedure result, otherwise there's a segmentation fault!?!?
						if ($select->execute())
							if ($ajax->Data->more = $select->get_result())
								$ajax->Data->more = !!$ajax->Data->more->num_rows;
							else
								$ajax->Fail('Error getting result checking for more:  ' . $select->errno . ' ' . $select->error);
						else
							$ajax->Fail('Error executing check for more:  ' . $select->errno . ' ' . $select->error);
					} else
						$ajax->Fail('Error getting result of looking up transactions:  ' . $select->errno . ' ' . $select->error);
				else
					$ajax->Fail('Error executing statement to look up transactions:  ' . $select->errno . ' ' . $select->error);
				$select->close();
			} else
				$ajax->Fail('Error binding parameters to look up transactions:  ' . $select->errno . ' ' . $select->error);
		else
			$ajax->Fail('Error preparing to look up transactions:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Translate uploaded file into a list of transactions for preview.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function parseFileAction(abeAjax $ajax) {
		// TODO:  accept bankclass or bankid instead of acctid
		// TODO:  automatic categorization engine
		if (isset($_POST['acctid']) && $_POST['acctid'] += 0)
			if (file_exists($_FILES['transfile']['tmp_name']) && is_uploaded_file($_FILES['transfile']['tmp_name'])) {
				$db = self::RequireLatestDatabase($ajax);
				if ($bankclass = self::LookupBank($_POST['acctid'], $ajax, $db)) {
					if ($preview = $bankclass::ParseTransactions($_FILES['transfile']['name'], $_FILES['transfile']['tmp_name'], $_POST['acctid'], $ajax, $db))
						$ajax->Data->preview = $preview;
				}
			} else
				$ajax->Fail('Transaction file not provided.');
		else
			$ajax->Fail('Account not specified.');
		unlink($_FILES['transfile']['tmp_name']);
	}

	/**
	 * Save changes to a transaction.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function saveAction(abeAjax $ajax) {
		if (isset($_POST['id'], $_POST['name']) && ($id = +$_POST['id']) && ($name = trim($_POST['name']))) {
			$db = self::RequireLatestDatabase($ajax);
			$db->autocommit(false);
			if ($update = $db->prepare('update transactions set name=?, notes=?, category=GetCategoryID(?), splitcat=?, reviewed=1 where id=? limit 1'))
				if ($update->bind_param('sssii', $name, $notes, $catname, $splitcat, $id)) {
					$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
					$catname = null;
					$splitcat = false;
					if (isset($_POST['catnames'], $_POST['catamounts']) && is_array($_POST['catnames']) && is_array($_POST['catamounts']) && count($_POST['catnames']) == count($_POST['catamounts'])) {
						$splitcat = count($_POST['catnames']) > 1;
						if (!$splitcat)
							$catname = trim($_POST['catnames'][0]);
					}
					if ($update->execute()) {
						$update->close();
						if ($del = $db->prepare('delete from splitcats where transaction=?'))
							if ($del->bind_param('i', $id))
								if ($del->execute()) {
									$del->close();
									if ($splitcat)
										if ($ins = $db->prepare('insert into splitcats (transaction, category, amount) values (?, GetCategoryID(?), ?)'))
											if ($ins->bind_param('isd', $id, $name, $amount)) {
												$failedcats = [];
												for ($i = 0; $i < count($_POST['catnames']); $i++) {
													$name = trim($_POST['catnames'][$i]);
													if ($amount = +$_POST['catamounts'][$i])
														if (!$ins->execute())
															$failetcats[] = $name;
												}
												if (!count($failedcats)) {
													$ins->close();
													$db->commit();
												} else
													$ajax->Fail('Error saving categories ' . implode(', ', $failedcats) . ':  ' . $ins->errno . ' ' . $ins->error);
											} else
												$ajax->Fail('Error binding to insert split categories:  ' . $ins->errno . ' ' . $ins->error);
										else
											$ajax->Fail('Error preparing to insert split categories:  ' . $db->errno . ' ' . $db->error);
									else
										$db->commit();
								} else
									$ajax->Fail('Error executing delete old split categories:  ' . $del->errno . ' ' . $del->error);
							else
								$ajax->Fail('Error binding transaction id to delete old split categories:  ' . $del->errno . ' ' . $del->error);
						else
							$ajax->Fail('Error preparing to delete old split categories:  ' . $db->errno . ' ' . $db->error);
					} else
						$ajax->Fail('Error executing update for transaction:  ' . $update->errno . ' ' . $update->error);
				} else
					$ajax->Fail('Error binding transaction parameters:  ' . $update->errno . ' ' . $update->error);
			else
				$ajax->Fail('Error preparing to update transactions:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameters \'id\' and \'name\' are required.');
	}

	/**
	 * Look up the class for the account's bank.
	 * @param integer $acctid Account ID
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @param mysqli $db Database connection object
	 * @return string Class name for account's bank class
	 */
	private static function LookupBank(int $acctid, abeAjax $ajax, mysqli $db) {
		$acct = 'select b.class from accounts as a left join banks as b on b.id=a.bank where a.id=\'' . +$acctid . '\' limit 1';
		if ($acct = $db->query($acct))
			if ($acct = $acct->fetch_object()) {
				$acct = $acct->class;
				require_once $acct . '.php';
				return $acct;
			} else
				$ajax->Fail('Account not found.');
		else
			$ajax->Fail('Error looking up account:  ' . $db->errno . ' ' . $db->error);
		return false;
	}
}
TransactionApi::Respond();
