<?php
require_once __DIR__ . '/etc/class/abe.php';

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new abeAjax();
	switch($_GET['ajax']) {
		case 'preview': Preview(); break;
		case 'save': Save(); break;
	}
	$ajax->Send();
	die;  // skip HTML output
}

$html = new abeHtml();
$html->Open('Import Transactions');
// TODO:  when doing preview, check if transactions already exist
?>
			<h1>Import Transactions</h1>
			<form id=importtrans>
				<label>
					<span class=label>Account:</span>
					<span class=field><select name=acctid data-bind="value: account, options: accountlist, optionsText: 'name', optionsValue: 'id'"></select></span>
				</label>
				<label data-bind="visible: selectedAccount()">
					<span class=label>Bank:</span>
					<span class=field><a data-bind="text: selectedAccount().bankname + ' login', attr: {href: selectedAccount().bankurl}">bankname</a></span>
				</label>
				<label data-bind="visible: selectedAccount()">
					<span class=label>Latest:</span>
					<span class=field data-bind="text: selectedAccount().newest"></span>
				</label>
				<label>
					<span class=label>Transactions:</span>
					<span class=field><input name=transfile type=file data-bind="event: {change: Preview}"></span>
				</label>

				<!-- ko foreach: previews -->
				<section class="transactions preview">
					<header>
						<h2 data-bind="text: name + ' → ' + acctname"></h2>
						<span class=count data-bind="text: transactions.length + ' transactions'"></span>
						<span class=amount data-bind="text: net.toFixed(2) + ' net'"></span>
						<span class=status data-bind="visible: saved">Imported</span>
						<button data-bind="visible: !saved(), click: $root.Save, css: {working: working()}, enable: !working()">Save</button>
						<a class=dismiss href="#done" data-bind="click: $root.Done" title="Remove this preview">Dismiss</a>
					</header>
					<ul data-bind="foreach: transactions">
						<li class=transaction>
							<div class=quick>
								<div class=name data-bind="text: name"></div>
								<div class=amount data-bind="text: amount.toFixed(2)"></div>
							</div>
							<div class=detail>
								<div class="transdate" data-bind="visible: transdate">Transaction <time data-bind="text: transdate"></time></div>
								<div class="posted">Posted <time data-bind="text: posted"></time></div>
								<div class="note" data-bind="visible: notes, text: notes"></div>
								<div class="location" data-bind="visible: city, text: city + (state ? ', ' + state + (zip ? ' ' + zip : '') : '')"></div>
							</div>
						</li>
					</ul>
				</section>
				<!-- /ko -->
			</form>
<?php
$html->Close();

/**
 * Translate uploaded file into a list of transactions for preview.
 */
function Preview() {
	global $ajax;
	if(isset($_POST['acctid']) && $_POST['acctid'] += 0)
		if(file_exists($_FILES['transfile']['tmp_name']) && is_uploaded_file($_FILES['transfile']['tmp_name'])) {
			if($bankclass = LookupBank($_POST['acctid']))
				if($preview = $bankclass::ParseTransactions($_FILES['transfile']['name'], $_FILES['transfile']['tmp_name']))
					$ajax->Data->preview = $preview;
		} else
			$ajax->Fail('Transaction file not provided.');
	else
		$ajax->Fail('Account not specified.');
	unlink($_FILES['transfile']['tmp_name']);
}

/**
 * Save previewed transactions.
 */
function Save() {
	// TODO:  automatic categorization / renaming engine
	global $ajax, $db;
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
 * Look up the class for the account's bank.
 * @param integer $acctid Account ID
 * @return string Class name for account's bank class
 */
function LookupBank($acctid) {
	global $ajax, $db;
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
