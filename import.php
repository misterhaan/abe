<?php
require_once __DIR__ . '/etc/class/cya.php';

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new cyaAjax();
	switch($_GET['ajax']) {
		case 'import': Import(); break;
	}
	$ajax->Send();
	die;  // skip HTML output
}

$html = new cyaHtml();
// Import can be accessed from the transactions page or the accounts page.
$html->SetBack(isset($_GET['acct']) && +$_GET['acct'] ? "accounts.php" : "transactions.php");
$html->Open('Import Transactions');
?>
			<h1>Import Transactions</h1>
			<form id=importtrans data-bind="submit: Import">
				<label>
					<span class=label>Account:</span>
					<span class=field><select name=acctid data-bind="value: account, options: accountlist, optionsText: 'name', optionsValue: 'id'"></select></span>
				</label>
				<label>
					<span class=label>Transactions:</span>
					<span class=field><input name=transfile type=file></span>
				</label>
				<button>Import</button>
			</form>
<?php
$html->Close();

/**
 * Import transactions from the uploaded file into the specified account.
 */
function Import() {
	global $ajax, $db;
	if($acct = $db->query('select a.id, b.class from accounts as a left join banks as b on b.id=a.bank where a.id=\'' . +$_POST['acctid'] . '\' limit 1'))
		if($acct = $acct->fetch_object()) {
			$bankclass = $acct->class;
			require_once $bankclass . '.php';
			$bankclass::ImportTransactions($_FILES['transfile']['name'], $_FILES['transfile']['tmp_name'], $acct->id);
		} else
			$ajax->Fail('Account not found.');
	else
		$ajax->Fail('Error looking up account:  ' . $db->error);
	unlink($_FILES['transfile']['tmp_name']);
}
?>
