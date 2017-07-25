<?php
require_once __DIR__ . '/etc/class/abe.php';

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new abeAjax();
	switch($_GET['ajax']) {
		case 'accountlist': GetAccountList(); break;
	}
	$ajax->Send();
	die;  // skip HTML output
}

$html = new abeHtml();
$html->AddAction('account.php', 'add', '+', 'Add another account');
$html->Open('Accounts');
?>
			<h1>Accounts</h1>
			<p data-bind="visible: !loading() && !accounts().length">
				No active accounts.  Maybe you should <a href="account.php">add one</a>?
			</p>
			<section id=accountlist data-bind="foreach: accounts">
				<div class=account data-bind="css: typeclass">
					<h2 data-bind="text: name"></h2>
					<div class=detail>
						<time class=lastupdate data-bind="text: 'As of ' + newest"></time>
						<span class=balance data-bind="text: balance"></span>
					</div>
					<div class=actions>
						<a class=transactions data-bind="attr: {href: 'transactions.php#!accts=' + id}" title="See transactions from this account"><span>transactions</span></a>
						<a class=bank data-bind="attr: {href: bankurl}" title="Visit this account’s bank website"><span>bank</span></a>
						<a class=import data-bind="attr: {href: 'import.php?acct=' + id}" title="Import transactions to this account"><span>import</span></a>
						<a class=edit data-bind="attr: {href: 'account.php?id=' + id}"><span>edit</span></a>
					</div>
				</div>
			</section>
<?php
$html->Close();

/**
 * Look up the list of accounts.
 */
function GetAccountList() {
	global $ajax, $db;
	$accts = <<<ACCTS
		select a.id, a.name, at.class as typeclass, a.updated, b.name as bankname, b.url as bankurl, a.balance, date_format(max(t.posted), '%b %D') as newest
		from accounts as a
			left join banks as b on b.id=a.bank
			left join account_types as at on at.id=a.account_type
			left join transactions as t on t.account=a.id
		where not a.closed group by a.id order by a.updated desc
ACCTS;
	if($accts = $db->query($accts)) {
		$ajax->Data->accounts = [];
		while($acct = $accts->fetch_object()) {
			$acct->balance = number_format($acct->balance, 2);
			$acct->updated = date('F j', $acct->updated);
			$ajax->Data->accounts[] = $acct;
		}
	} else
		$ajax->Fail('Error looking up account list:  ' . $db->error);
}
?>
