<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for transactions API requests.
 * @author misterhaan
 */
class TransactionsApi extends abeApi {
	/**
	 * Write out the documentation for the transaction file import API controller.
	 * The page is already opened with an h1 header, and will be closed after the
	 * call completes.
	 */
	protected static function ShowDocumentation() {
?>
			<h2 id=POSTimport>POST import</h2>
			<p></p>
			<dl class=parameters>
				<dt>acctid</dt>
				<dd>
					ID of the bank account the transactions should import to.
				</dd>
				<dt>transactions</dt>
				<dd>
					Array of transactions to import.  Each item should have the same set
					of properties as the results from
					<a href="#POSTparseFile"><code>parseFile</code></a>.
				</dd>
			</dl>

			<h2 id=POSTparseFile>POST parseFile</h2>
			<p>
				Parse the transactions in a file.  The file must be provided as a form
				upload named <code>transfile</code>.
			</p>
			<dl class=parameters>
				<dt>acctid</dt>
				<dd>
					ID of the bank account the file belongs to.  Used to look up which
					bank it’s from in order to correctly parse the file.
				</dd>
			</dl>
<?php
	}

	/**
	 * Save previewed transactions.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function importAction($ajax) {
		global $db;
		if(isset($_POST['acctid']) && $account = +$_POST['acctid'])
			if(isset($_POST['transactions']) && is_array($_POST['transactions']) && $count = count($_POST['transactions'])) {
				$db->real_query('start transaction');  // transaction makes all these inserts much faster
				if($ins = $db->prepare('insert into transactions (account, extid, transdate, posted, name, amount, city, state, zip, notes) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'))
					if($ins->bind_param('issssdssss', $account, $extid, $transdate, $posted, $name, $amount, $city, $state, $zip, $notes)) {
						foreach($_POST['transactions'] as $trans) {
							$trans = (object)$trans;
							$extid = $trans->extid ? $trans->extid : null;
							$transdate = $trans->transdate ? $trans->transdate : null;
							$posted = $trans->posted;
							$name = $trans->name;
							$amount = $trans->amount;
							$city = $trans->city ? $trans->city : null;
							$state = $trans->state ? $trans->state: null;
							$zip = $trans->zip ? $trans->zip : null;
							$notes = $trans->notes;
							if($ins->execute())
								$count--;
						}
						if($count > 0)
							$ajax->Fail('Error saving ' . $count . ' of ' . count($_POST['transactions']) . ' transactions:  ' . $ins->error);
						$ins->close();
						if(!$db->real_query('update accounts set updated=\'' . +time() . '\', balance=balance+\'' . +$_POST['net'] . '\' where id=\'' . +$account .'\' limit 1'))
							$ajax->Fail('Error updating account:  ' . $db->error);
					} else
						$ajax->Fail('Error binding import parameters:  ' . $ins->error);
				else
					$ajax->Fail('Database error preparing to import transactions:  ' . $db->error);
				$db->real_query('commit');
			} else
				$ajax->Fail('No transactions to save.');
		else
			$ajax->Fail('Account not specified.');
	}

	/**
	 * Translate uploaded file into a list of transactions for preview.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function parseFileAction($ajax) {
		// TODO:  accept bankclass or bankid instead of acctid
		// TODO:  automatic categorization engine
		if(isset($_POST['acctid']) && $_POST['acctid'] += 0)
			if(file_exists($_FILES['transfile']['tmp_name']) && is_uploaded_file($_FILES['transfile']['tmp_name'])) {
				if($bankclass = self::LookupBank($_POST['acctid'], $ajax))
					if($preview = $bankclass::ParseTransactions($_FILES['transfile']['name'], $_FILES['transfile']['tmp_name'], $_POST['acctid']))
						$ajax->Data->preview = $preview;
			} else
				$ajax->Fail('Transaction file not provided.');
				else
					$ajax->Fail('Account not specified.');
					unlink($_FILES['transfile']['tmp_name']);
	}

	/**
	 * Look up the class for the account's bank.
	 * @param integer $acctid Account ID
	 * @return string Class name for account's bank class
	 */
	private static function LookupBank($acctid, $ajax) {
		global $db;
		$acct = 'select b.class from accounts as a left join banks as b on b.id=a.bank where a.id=\'' . +$acctid . '\' limit 1';
		if($acct = $db->query($acct))
			if($acct = $acct->fetch_object()) {
				$acct = $acct->class;
				require_once $acct . '.php';
				return $acct;
			} else
				$ajax->Fail('Account not found.');
		else
			$ajax->Fail('Error looking up account:  ' . $db->error);
		return false;
	}
}
TransactionsApi::Respond();
