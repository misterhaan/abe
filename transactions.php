<?php
require_once __DIR__ . '/etc/class/cya.php';

define('MAX_TRANS', 50);  // how many transactions to load at a time (should probably move to a setting)

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new cyaAjax();
	switch($_GET['ajax']) {
		case 'get': GetTransactions(); break;
		case 'save': SaveTransactions(); break;
		case 'categories': GetCategories(); break;
	}
	$ajax->Send();
	die;  // skip HTML output
}

$html = new cyaHtml();
// if we're viewing transactions for one account, back should go to the account list.  otherwise to the main menu
if(isset($_GET['acct']))
	$html->SetBack('accounts.php');
$html->AddAction('#showFilters', 'filter', 'Filter', 'Filter transactions');
$html->AddAction('import.php', 'import', 'Import', 'Import transactions');
$html->Open('Transactions');
?>
			<h1>Transactions</h1>
			<div id=transactions>
				<div id=filters data-bind="visible: showFilters">
					<label>
						Accounts:
						<!-- ko ifnot: filterAccounts().length -->
							<span class="all account">(all)</span>
						<!-- /ko -->
						<!-- ko foreach: filterAccounts -->
							<span class="account" data-bind="css: typeclass"><span data-bind="text: name"></span><a class=remove data-bind="click: $root.RemoveAccount"></a></span>
						<!-- /ko -->
						<input data-bind="textInput: filterAcct, event: { dblclick: ShowAcctSuggestions, keydown: AccountFilterKey, blur: HideFilterAcctSuggestions }" placeholder="Find an account">
					</label>
					<ol class=suggestions data-bind="visible: suggestingAccounts, foreach: accountsForFilter">
						<li><div data-bind="text: name, click: $root.ChooseAccount, attr: {'class': 'account ' + typeclass}, css: {kbcursor: $data == $root.acctCursor()}"></div></li>
					</ol>
					<label class=categories>
						Categories:
						<!-- ko ifnot: filterCategories().length -->
							<span class="all category">(all)</span>
						<!-- /ko -->
						<!-- ko foreach: filterCategories -->
							<span class="category"><span data-bind="text: name"></span><a class=remove data-bind="click: $root.RemoveFilterCategory"></a></span>
						<!-- /ko -->
						<input data-bind="textInput: filterCat, event: { dblclick: ShowFilterCatSuggestions, keydown: $root.CategoryFilterKey, blur: HideFilterCatSuggestions }" maxlength=24 placeholder="Find a category">
					</label>
					<ol class=suggestions data-bind="visible: suggestingFilterCategories, foreach: categoriesForFilter">
						<li>
							<div data-bind="text: name, click: $root.ChooseFilterCategory, css: {kbcursor: $data == $root.catCursor()}"></div>
							<!-- ko if: subs.length -->
							<ol data-bind="foreach: subs">
								<li><div data-bind="text: name, click: $root.ChooseFilterCategory, css: {kbcursor: $data == $root.catCursor()}"></div></li>
							</ol>
							<!-- /ko -->
						</li>
					</ol>
					<label class=date>
						<span>Since:</span>
						<input type=date data-bind="value: dateStart">
					</label>
					<label class=date>
						<span>Before:</span>
						<input type=date data-bind="value: dateEnd">
					</label>
					<div class=calltoaction>
						<button data-bind="click: UpdateFilters">OK</button><a href="#closeFilters" data-bind="click: CancelFilters">Cancel</a>
					</div>
				</div>
				<ol>
					<!-- ko foreach: dates -->
						<li class=date>
							<header><time data-bind="text: displayDate"></time></header>
							<ul data-bind="foreach: transactions">
								<li class=transaction data-bind="css: acctclass, click: $root.Select">
									<div class=quick>
										<div class=name data-bind="text: name"></div>
										<div class=category data-bind="text: category() ? category() : '(uncategorized)'"></div>
									</div>
									<div class=amount data-bind="text: amount"></div>
									<div class=full data-bind="visible: $root.selection() == $data, scrollTo: $root.selection() == $data, click: $root.CaptureClick">
										<div class=transaction>
											<div><label class=name><input data-bind="value: name" maxlength=64></label></div>
											<div class=amount data-bind="text: amount"></div>
											<a class=close data-bind="visible: !$root.saving(), click: $root.CloseAndSave" title="Save changes and close"><span>close</span></a>
											<span class=working data-bind="visible: $root.saving"></span>
										</div>
										<div class=details>
											<label class=category><input data-bind="textInput: category, css: {newcat: newCategory}, event: { dblclick: $root.ShowSuggestions, keydown: $root.CategoryKey, blur: $root.HideSuggestions }" placeholder="(uncategorized)" maxlength=24></label>
											<ol class=suggestions data-bind="visible: suggestingCategories, foreach: $root.categories">
												<!-- ko if: name.containsAnyCase($parent.category()) || subs.nameContainsAnyCase($parent.category()) -->
													<li>
														<div data-bind="text: name, click: $root.ChooseCategory, css: {kbcursor: $data == $root.catCursor()}"></div>
														<!-- ko if: subs.length && subs.nameContainsAnyCase($parent.category()) -->
															<ol data-bind="foreach: subs">
																<!-- ko if: name.containsAnyCase($parents[1].category()) -->
																	<li><div data-bind="text: name, click: $root.ChooseCategory, css: {kbcursor: $data == $root.catCursor()}"></div></li>
																<!-- /ko -->
															</ol>
														<!-- /ko -->
													</li>
												<!-- /ko -->
											</ol>
											<div class=account data-bind="css: acctclass, text: acctname"></div>
											<div class=transdate data-bind="visible: transdate">Transaction <time data-bind="text: transdate"></time></div>
											<div class=posted>Posted <time data-bind="text: posted"></time></div>
											<label class=note><input data-bind="value: notes"></label>
											<div class=location data-bind="visible: city, text: city + (state ? ', ' + state + (zip ? ' ' + zip : '') : '')"></div>
										</div>
									</div>
								</li>
							</ul>
						</li>
					<!-- /ko -->
					<li class=loading data-bind="visible: loading">Loading...</li>
					<li class=calltoaction data-bind="visible: more"><a href="#GetTransactions" data-bind="click: GetTransactions">Load more</a></li>
				</ol>
			</div>
<?php
$html->Close();

/**
 * Look up the next batch of transactions.
 */
function GetTransactions() {
	global $ajax, $db;
	if($select = $db->prepare('call GetTransactions(?, ?, ?, ?, ?, ?, ?)'))
		if($select->bind_param('isissss', $maxcount, $oldest, $oldid, $accountids, $categoryids, $datestart, $dateend)) {
			$maxcount = MAX_TRANS;
			$oldest = $_GET['oldest'];
			$oldid = $_GET['oldid'];
			$accountids = $_GET['accts'] ? $_GET['accts'] : null;
			$categoryids = $_GET['cats'] || $_GET['cats'] === '0' ? $_GET['cats'] : null;
			$datestart = $_GET['datestart'] ? $_GET['datestart'] : null;
			$dateend = $_GET['dateend'] ? $_GET['dateend'] : null;
			if($select->execute())
				if($select->store_result())
					if($select->bind_result($id, $posted, $transdate, $acctclass, $acctname, $name, $category, $amount, $notes, $city, $state, $zip)) {
						$ajax->Data->dates = [];
						while($select->fetch()) {
							$displayDate = date('F j, Y (D)', strtotime($posted . ' 12:00 PM'));
							if(!count($ajax->Data->dates) || $ajax->Data->dates[count($ajax->Data->dates) - 1]->date != $posted)
								$ajax->Data->dates[] = (object)['date' => $posted, 'displayDate' => $displayDate, 'transactions' => []];
							// show commas if 10k or more
							if(+$amount >= 10000.00 || +$amount <= -10000.00)
								$amount = number_format(+$amount, 2);
							$ajax->Data->dates[count($ajax->Data->dates) - 1]->transactions[] = (object)['id' => $id, 'posted' => $posted, 'transdate' => $transdate, 'acctclass' => $acctclass, 'acctname' => $acctname, 'name' => $name, 'category' => $category, 'amount' => $amount, 'notes' => $notes, 'city' => $city, 'state' => $state, 'zip' => $zip];
							$oldest = $posted;
							$oldid = $id;
						}
						$ajax->Data->more = false;
						$maxcount = 1;
						$select->free_result();
						$db->next_result();  // get past the extra stored procedure result
						if($select->execute())
							if($select->store_result())
								$ajax->Data->more = $select->num_rows > 0;
							else
								$ajax->Fail('Error storing result checking for more:  ' . $select->error);
						else
							$ajax->Fail('Error executing check for more:  ' . $select->error);
					} else
						$ajax->Fail('Error binding statement results:  ' . $select->error);
				else
					$ajax->Fail('Error storing result looking up transactions:  ' . $select->error);
			else
				$ajax->Fail('Error executing statement to look up transactions:  ' . $select->error);
			$select->close();
		} else
			$ajax->Fail('Error binding parameters to look up transactions:  ' . $select->error);
	else
		$ajax->Fail('Error preparing to look up transactions:  ' . $db->error);
	return;
	
	$ts = 'select t.id, t.posted, t.transdate, at.class as acctclass, a.name as acctname, t.name, c.name as category, t.amount, t.notes, t.city, t.state, t.zip from transactions as t left join categories as c on c.id=t.category left join accounts as a on a.id=t.account left join account_types as at on at.id=a.account_type where ' . (isset($_GET['acct']) && +$_GET['acct'] ? 't.account=\'' . +$_GET['acct'] . '\' and ' : '') . '(t.posted<\'' . $db->escape_string($_GET['oldest']) . '\' or t.posted=\'' . $db->escape_string($_GET['oldest']) . '\' and t.id<\'' . $db->escape_string($_GET['oldid']) . '\') order by t.posted desc, t.id desc limit ' . MAX_TRANS;
	if($ts = $db->query($ts)) {
		$ajax->Data->dates = [];
		$posted = '';
		$id = 0;
		while($t = $ts->fetch_object()) {
			$displayDate = date('F j, Y (D)', strtotime($t->posted . ' 12:00 PM'));
			if(!count($ajax->Data->dates) || $ajax->Data->dates[count($ajax->Data->dates) - 1]->date != $t->posted)
				$ajax->Data->dates[] = (object)['date' => $t->posted, 'displayDate' => $displayDate, 'transactions' => []];
			// show commas if 10k or more
			if(+$t->amount >= 10000.00 || +$t->amount <= -10000.00)
				$t->amount = number_format(+$t->amount, 2);
			$ajax->Data->dates[count($ajax->Data->dates) - 1]->transactions[] = unserialize(serialize($t)); // ['id' => $id, 'posted' => $posted, 'name' => $name, 'category' => $category, 'amount' => $amount];
			$posted = $t->posted;
			$id = $t->id;
		}
		$ajax->Data->more = false;
		if($more = $db->query('select 1 from transactions where posted<\'' . $db->escape_string($posted) . '\' or posted=\'' . $db->escape_string($posted) . '\' and id<\'' . $db->escape_string($id) . '\' order by posted desc, id desc limit 1'))
			if($more->num_rows)
				$ajax->Data->more = true;
	} else
		$ajax->Fail('Error looking up transactions:  ' . $db->error);
}

/**
 * Save changed transactions and send back the latest categories list.
 */
function SaveTransactions() {
	global $ajax, $db;
	if($update = $db->prepare('update transactions set name=?, notes=?, category=GetCategoryID(?), reviewed=1 where id=? limit 1'))
		if($update->bind_param('sssi', $name, $notes, $catname, $id)) {
			foreach($_POST['transactions'] as $t) {
				$name = $t['name'];
				$notes = $t['notes'];
				$catname = $t['category'];
				$id = +$t['id'];
				if(!$update->execute())
					$ajax->Fail('Error executing update for transaction:  ' . $update->error);
			}
			$update->close();
			GetCategories();
		} else
			$ajax->Fail('Error binding transactions parameters:  ' . $update->error);
	else
		$ajax->Fail('Error preparing to update transactions:  ' . $db->error);
}

/**
 * Look up spending categories.
 */
function GetCategories() {
	global $ajax, $db;
	if($cats = $db->query('select id, name, parent from categories order by isnull(parent) desc, name')) {
		$ajax->Data->categories = [];
		while($cat = $cats->fetch_object())
			if($cat->parent)
				$p = $ajax->Data->categories[$cat->parent]['subs'][] = ['id' => $cat->id, 'name' => $cat->name];
			else
				$ajax->Data->categories[$cat->id] = ['id' => $cat->id, 'name' => $cat->name, 'subs' => []];
		$ajax->Data->categories = array_values($ajax->Data->categories);
	}
}
?>
