<?php
require_once __DIR__ . '/etc/class/abe.php';

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new abeAjax();
	switch($_GET['ajax']) {
		case 'monthcats': MonthCats(); break;
	}
	$ajax->Send();
	die;  // skip HTML output
}

$html = new abeHtml();
$html->Open('Spending');
?>
			<h1>Spending</h1>

			<svg id=monthtrend viewBox="0 0 800 400"></svg>

			<div id=spendmonthcat><div><table>
				<thead><tr>
					<td></td>
					<!-- ko foreach: months -->
					<th data-bind="text: name"></th>
					<!-- /ko -->
				</tr></thead>
				<tbody>
					<tr class=total>
						<th>Total</th>
						<!-- ko foreach: months -->
						<td><a data-bind="text: net, attr: {href: 'transactions.php#!datestart=' + start + '/dateend=' + end}"></a></td>
						<!-- /ko -->
					</tr>
					<!-- ko foreach: cats -->
						<!-- ko if: subcats -->
						<tr class=group data-bind="click: $root.ToggleCategory, css: {expand: $root.expandedCats().indexOf(id) < 0, collapse: $root.expandedCats().indexOf(id) > -1}">
							<th data-bind="text: name"></th>
							<!-- ko foreach: $root.months -->
							<td data-bind="text: $root.findParentAmount($index(), +$parent.id)"></td>
							<!-- /ko -->
						</tr>
						<!-- ko if: $root.expandedCats().indexOf(id) > -1 --><!-- ko foreach: subcats -->
						<tr class=subcat>
							<th data-bind="text: name"></th>
							<!-- ko foreach: $root.months -->
							<td><a data-bind="text: cats[$parent.id], attr: {href: 'transactions.php#!cats=' + +$parent.id + '/datestart=' + start + '/dateend=' + end}"></a></td>
							<!-- /ko -->
						</tr>
						<!-- /ko --><!-- /ko -->
						<!-- /ko -->
						<!-- ko ifnot: subcats -->
						<tr>
							<th data-bind="text: name"></th>
							<!-- ko foreach: $root.months -->
							<td><a data-bind="text: cats[+$parent.id], attr: {href: 'transactions.php#!cats=' + +$parent.id + '/datestart=' + start + '/dateend=' + end}"></a></td>
							<!-- /ko -->
						</tr>
						<!-- /ko -->
					<!-- /ko -->
				</tbody>
			</table></div></div>
<?php
$html->Close();

function MonthCats() {
	global $ajax, $db;
	// TODO:  accept $_GET['oldest'] for getting older data
	$oldest = date('Y') - 1 . '-' . date('m') . '-00';
	$amts = <<<AMTS
		select a.displaymonth, concat(a.sortmonth, '-01') as monthstart, last_day(concat(a.sortmonth, '-01')) as monthend, sum(a.amount) as amount, c.id as catid, coalesce(c.name, '(uncategorized)') as catname, p.id as parentid, p.name as parentname
		from (select date_format(posted, '%b %Y') as displaymonth, date_format(posted, '%Y-%m') as sortmonth, sum(amount) as amount, category
			from transactions where splitcat=0 and posted>'$oldest' group by year(posted), month(posted), category
		union select date_format(t.posted, '%b %Y') as displaymonth, date_format(t.posted, '%Y-%m') as sortmonth, sum(s.amount) as amount, s.category
			from splitcats as s left join transactions as t on t.id=s.transaction where t.posted>'$oldest' group by year(t.posted), month(t.posted), category) as a
		left join categories as c on c.id=a.category
		left join categories as p on p.id=c.parent
		where amount!=0 group by a.sortmonth, c.id
AMTS;
	if($amts = $db->query($amts)) {
		$ajax->Data->months = [];
		$ajax->Data->cats = [];
		$lastmonth = false;
		$m = -1;
		$ctrack = [];  // track which categories have been added
		$parentmap = [];  // track which parent categories have been added and where they are in the list
		while($amt = $amts->fetch_object()) {
			// add a new month as it changes.  query is sorted by month so when it changes we know it's new
			if($lastmonth != $amt->displaymonth) {
				$m = count($ajax->Data->months);
				$ajax->Data->months[] = ['name' => $amt->displaymonth, 'start' => $amt->monthstart, 'end' => $amt->monthend, 'net' => 0, 'made' => 0, 'spent' => 0, 'cats' => []];
				$lastmonth = $amt->displaymonth;
			}
			if(!in_array($amt->catid, $ctrack)) {
				if($amt->parentid) {
					if(!array_key_exists($amt->parentid, $parentmap)) {
						$parentmap[+$amt->parentid] = count($ajax->Data->cats);
						$ajax->Data->cats[] = ['id' => +$amt->parentid, 'name' => $amt->parentname, 'subcats' => []];
					}
					$ajax->Data->cats[$parentmap[+$amt->parentid]]['subcats'][] = ['id' => +$amt->catid, 'name' => $amt->catname];
				} else
					$ajax->Data->cats[] = ['id' => +$amt->catid, 'name' => $amt->catname, 'subcats' => false];
				$ctrack[] = $amt->catid;
			}
			$ajax->Data->months[$m]['net'] += $amt->amount;
			$ajax->Data->months[$m][$amt->amount < 0 ? 'spent' : 'made'] += $amt->amount;
			$ajax->Data->months[$m]['cats'][+$amt->catid] = $amt->amount;
		}
		usort($ajax->Data->cats, 'AlphabetizeNamed');
		foreach($ajax->Data->cats as $parent)
			if(is_array($parent['subcats']))
				usort($parent['subcats'], 'AlphabetizeNamed');
	} else
		$ajax->Fail('error looking up monthly spending by category:  ' . $db->error);
}

function AlphabetizeNamed($a, $b) {
	return strcmp($a['name'], $b['name']);
}
