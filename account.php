<?php
require_once __DIR__ . '/etc/class/abe.php';

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new abeAjax();
	switch($_GET['ajax']) {
		case 'lists': GetLists(); break;
		case 'get':   GetAccount(); break;
		case 'save':  SaveAccount(); break;
	}
	$ajax->Send();
	die();  // skip HTML output
}

$html = new abeHtml();
$html->Open('Account');
?>
			<h1 data-bind="text: id ? name : 'Add account'"></h1>
			<form id=editaccount data-bind="submit: Save">
				<label>
					<span class=label>Name:</span>
					<span class=field><input data-bind="textInput: name" maxlength=32 required></span>
				</label>
				<label>
					<span class=label>Bank:</span>
					<span class=field><select data-bind="value: bank, options: banklist, optionsText: 'name', optionsValue: 'id'"></select></span>
				</label>
				<label>
					<span class=label>Type:</span>
					<span class=field><select data-bind="value: type, options: typelist, optionsText: 'name', optionsValue: 'id'"></select></span>
				</label>
				<label>
					<span class=label>Balance:</span>
					<span class=field><input id=balance type=number step=.01 data-bind="value: balanceFormatted" required></span>
				</label>
				<label>
					<span class=label>Closed:</span>
					<span class=field><input type=checkbox data-bind="checked: closed"><span>Closed accounts are hidden on the accounts page and cannot import transactions.</span></span>
				</label>
				<button>Save</button>
			</form>
<?php
$html->Close();

/**
 * Look up option lists for banks and account types.
 */
function GetLists() {
	global $ajax, $db;
	$ajax->Data->banks = [];
	$ajax->Data->types = [];
	$ajax->Data->banks[] = (object)['id' => false, 'name' => ''];
	$ajax->Data->types[] = (object)['id' => false, 'name' => '', 'class' => ''];
	if($banks = $db->query('select id, name from banks order by name'))
		while($bank = $banks->fetch_object())
			$ajax->Data->banks[] = $bank;
	else
		$ajax->Fail('Error looking up supported banks.');
	if($types = $db->query('select id, name, class from account_types order by name'))
		while($type = $types->fetch_object())
			$ajax->Data->types[] = $type;
	else
		$ajax->Fail('Error looking up account types.');
}

/**
 * Look up information for the account specified by $_GET['id'].
 */
function GetAccount() {
	global $ajax, $db;
	if($acct = $db->query('select id, bank, account_type, name, balance, closed from accounts where id=' . +$_GET['id'] . ' limit 1'))
		if($acct = $acct->fetch_object())
			$ajax->Data = $acct;
		else
			$ajax->Fail('Account not found.');
	else
		$ajax->Fail('Error looking up account:  ' . $db->error);
}

/**
 * Save the account.
 */
function SaveAccount() {
	global $ajax, $db;
	$id = isset($_POST['id']) && $_POST['id'] ? +$_POST['id'] : false;
	$set = 'accounts set bank=?, name=?, account_type=?, balance=?, updated=?, closed=?';
	if($id)
		if($put = $db->prepare('update ' . $set . ' where id=? limit 1'))
			if($put->bind_param('isidiii', $bank, $name, $account_type, $balance, $updated, $closed, $id))
				; // good
			else
				$ajax->Fail('Error binding parameters for account update:  ' . $put->error);
		else
			$ajax->Fail('Error preparing to update account:  ' . $db->error);
	else
		if($put = $db->prepare('insert into ' . $set))
			if($put->bind_param('isidii', $bank, $name, $account_type, $balance, $updated, $closed))
				; // good
			else
				$ajax->Fail('Error binding parameters for account insert:  ' . $put->error);
		else
			$ajax->Fail('Error preparing to insert account:  ' . $db->error);
	if(!$ajax->Data->fail) {
		$bank = +$_POST['bank'];
		$name = trim($_POST['name']);
		$account_type = +$_POST['type'];
		$balance = +$_POST['balance'];
		$updated = +time();
		$closed = $_POST['closed'] ? 1 : 0;
		if(!$put->execute())
			$ajax->Fail('Error saving account:  ' . $put->error);
		$put->close();
	}
}
?>
