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
			<h2 id=GETlist>GET list</h2>
			<p>Get the list of accounts.</p>
<?php
	}

	/**
	 * Get the list of all funds.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction($ajax) {
		global $db;
		$accts = <<<ACCTS
			select a.id, a.name, a.closed, at.class as typeClass, b.name as bankname, b.url as bankUrl, a.balance, max(t.posted) as newestSortable, date_format(max(t.posted), '%b %D') as newestDisplay
			from accounts as a
				left join banks as b on b.id=a.bank
				left join account_types as at on at.id=a.account_type
				left join transactions as t on t.account=a.id
			group by a.id order by a.closed, a.updated desc
ACCTS;
		if($accts = $db->query($accts)) {
			$ajax->Data->accounts = [];
			while($acct = $accts->fetch_object()) {
				$acct->id += 0;
				$acct->closed = !!+$acct->closed;
				$acct->balance += 0;
				$acct->balanceDisplay = abeFormat::Amount($acct->balance);
				$ajax->Data->accounts[] = $acct;
			}
		} else
			$ajax->Fail('Error looking up account list:  ' . $db->error);
	}
}
AccountApi::Respond();
