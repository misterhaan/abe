<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for account API requests.
 * @author misterhaan
 */
class AccountApi extends abeApi {
	/**
	 * Write out the documentation for the account API controller.  The page is
	 * already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
		<h2 id=POSTadd>POST add</h2>
		<p>Add a new account.</p>
		<dl class=parameters>
			<dt>name</dt>
			<dd>Name of account. Required.</dd>
			<dt>bank</dt>
			<dd>ID of the bank of the account. Required.</dd>
			<dt>type</dt>
			<dd>ID of the type of account. Required.</dd>
			<dt>balance</dt>
			<dd>Current account balance. Optional; default zero.</dd>
			<dt>closed</dt>
			<dd>Whether the account is closed. Optional; default false (not closed)</dd>
		</dl>

		<h2 id=GETlist>GET list</h2>
		<p>Get the list of accounts.</p>
		<dl class=parameters>
			<dt>activeOnly</dt>
			<dd>If specified, closed accounts will left out of results.</dd>
		</dl>

		<h2 id=POSTsave>POST save</h2>
		<p>Save changes to an account.</p>
		<dl class=parameters>
			<dt>id</dt>
			<dd>ID of the account being saved. Required.</dd>
			<dt>name</dt>
			<dd>Name of account. Required.</dd>
			<dt>bank</dt>
			<dd>ID of the bank of the account. Required.</dd>
			<dt>type</dt>
			<dd>ID of the type of account. Required.</dd>
			<dt>balance</dt>
			<dd>Current account balance. Optional; default zero.</dd>
			<dt>closed</dt>
			<dd>Whether the account is closed. Optional; default false (not closed)</dd>
		</dl>

		<h2 id=GETtypes>GET types</h2>
		<p>Gets available account types and supported banks.</p>
<?php
	}

	/**
	 * Add a new account.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function addAction(abeAjax $ajax) {
		if (isset($_POST['name'], $_POST['type'], $_POST['bank']) && ($name = trim($_POST['name'])) && ($type = +$_POST['type']) && ($bank = +$_POST['bank'])) {
			$balance = isset($_POST['balance']) ? +$_POST['balance'] : 0;
			$closed = isset($_POST['closed']) && $_POST['closed'] ? 1 : 0;
			$db = self::RequireLatestDatabase($ajax);
			if ($ins = $db->prepare('insert into accounts (name, account_type, bank, balance, closed) values (?, ?, ?, ?, ?)'))
				if ($ins->bind_param('siidi', $name, $type, $bank, $balance, $closed))
					if ($ins->execute())
						$ajax->Data->id = $ins->insert_id;
					else
						$ajax->Fail('Error saving account:  ' . $ins->errno . ' ' . $ins->error);
				else
					$ajax->Fail('Error binding account parameters:  ' . $ins->errno . ' ' . $ins->error);
			else
				$ajax->Fail('Error preparing to add account:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameters name, type, and bank are required.');
	}

	/**
	 * Get the list of all funds.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction(abeAjax $ajax) {
		$db = self::RequireLatestDatabase($ajax);
		$accts = <<<ACCTS
			select a.id, a.name, a.closed, at.id as type, at.class as typeClass, b.id as bank, b.name as bankName, b.url as bankUrl, a.balance, max(t.posted) as newestSortable, date_format(max(t.posted), '%b %D') as newestDisplay
			from accounts as a
				left join banks as b on b.id=a.bank
				left join account_types as at on at.id=a.account_type
				left join transactions as t on t.account=a.id
			group by a.id
ACCTS;
		$accts .= isset($_GET['activeOnly'])
			? ' where a.closed=0 order by a.updated desc'
			: ' order by a.closed, a.updated desc';

		if ($accts = $db->query($accts)) {
			$ajax->Data->accounts = [];
			while ($acct = $accts->fetch_object()) {
				$acct->id += 0;
				$acct->closed = !!+$acct->closed;
				$acct->type += 0;
				$acct->bank += 0;
				$acct->balance += 0;
				$acct->balanceDisplay = Format::Amount($acct->balance);
				$ajax->Data->accounts[] = $acct;
			}
		} else
			$ajax->Fail('Error looking up account list:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Save changes to an account.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function saveAction(abeAjax $ajax) {
		if (isset($_POST['id'], $_POST['name'], $_POST['type'], $_POST['bank']) && ($id = +$_POST['id']) && ($name = trim($_POST['name'])) && ($type = +$_POST['type']) && ($bank = +$_POST['bank'])) {
			$balance = isset($_POST['balance']) ? +$_POST['balance'] : 0;
			$closed = isset($_POST['closed']) && $_POST['closed'] ? 1 : 0;
			$db = self::RequireLatestDatabase($ajax);
			if ($update = $db->prepare('update accounts set name=?, account_type=?, bank=?, balance=?, closed=? where id=? limit 1'))
				if ($update->bind_param('siidii', $name, $type, $bank, $balance, $closed, $id))
					if ($update->execute());  // update successful
					else
						$ajax->Fail('Error saving account:  ' . $update->errno . ' ' . $update->error);
				else
					$ajax->Fail('Error binding account parameters:  ' . $update->errno . ' ' . $update->error);
			else
				$ajax->Fail('Error preparing to update account:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameters id, name, type, and bank are required.');
	}

	/**
	 * Get the lists of available account types and supported banks.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function typesAction(abeAjax $ajax) {
		$db = self::RequireLatestDatabase($ajax);
		$ajax->Data->banks = [];
		$ajax->Data->types = [];
		$ajax->Data->banks[] = (object)['id' => false, 'name' => ''];
		if ($banks = $db->query('select id, name, url from banks order by name')) {
			while ($bank = $banks->fetch_object()) {
				$bank->id += 0;
				$ajax->Data->banks[] = $bank;
			}
			if ($types = $db->query('select id, name, class from account_types order by name'))
				while ($type = $types->fetch_object()) {
					$type->id += 0;
					$ajax->Data->types[] = $type;
				}
			else
				$ajax->Fail('Error looking up account types:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Error looking up supported banks:  ' . $db->errno . ' ' . $db->error);
	}
}
AccountApi::Respond();
