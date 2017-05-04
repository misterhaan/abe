<?php
require_once __DIR__ . '/etc/class/cya.php';

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new cyaAjax();
	switch($_GET['ajax']) {
		case 'monthlytotal':
			// TODO:  exclude transfers
			'select date_format(posted, \'%b %Y\') as displaymonth, date_format(posted, \'%Y-%m\') as sortmonth, sum(case when amount<0 then -amount else 0 end) as spending, sum(case when amount>0 then amount else 0 end) as income, sum(amount) as net from transactions group by year(posted), month(posted)';
			break;
		case 'monthcats': MonthCats(); break;
	}
	$ajax->Send();
	die;  // skip HTML output
}

$html = new cyaHtml();
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
					<tr>
						<th data-bind="text: name"></th>
						<!-- ko foreach: $root.months -->
						<td><a data-bind="text: cats[+$parent.id], attr: {href: 'transactions.php#!cats=' + +$parent.id + '/datestart=' + start + '/dateend=' + end}"></a></td>
						<!-- /ko -->
					</tr>
					<!-- /ko -->
				</tbody>
			</table></div></div>
<?php
$html->Close();

function MonthCats() {
	global $ajax, $db;
	// TODO:  accept $_GET['oldest']
	$oldest = date('Y') - 1 . '-' . date('m') . '-00';
	$amts = 'select a.displaymonth, concat(a.sortmonth, \'-01\') as monthstart, last_day(concat(a.sortmonth, \'-01\')) as monthend, sum(a.amount) as amount, c.id as catid, coalesce(c.name, \'(uncategorized)\') as catname from (select date_format(posted, \'%b %Y\') as displaymonth, date_format(posted, \'%Y-%m\') as sortmonth, sum(amount) as amount, category from transactions where splitcat=0 and posted>\'' . $oldest . '\' group by year(posted), month(posted), category union select date_format(t.posted, \'%b %Y\') as displaymonth, date_format(t.posted, \'%Y-%m\') as sortmonth, sum(s.amount) as amount, s.category from splitcats as s left join transactions as t on t.id=s.transaction where t.posted>\'' . $oldest . '\' group by year(t.posted), month(t.posted), category) as a left join categories as c on c.id=a.category where amount!=0 group by a.sortmonth, c.id';
	if($amts = $db->query($amts)) {
		$ajax->Data->months = [];
		$ajax->Data->cats = [];
		$lastmonth = false;
		$m = -1;
		$ctrack = [];
		while($amt = $amts->fetch_object()) {
			if($lastmonth != $amt->displaymonth) {
				$m = count($ajax->Data->months);
				$ajax->Data->months[] = ['name' => $amt->displaymonth, 'start' => $amt->monthstart, 'end' => $amt->monthend, 'net' => 0, 'made' => 0, 'spent' => 0, 'cats' => []];
				$lastmonth = $amt->displaymonth;
			}
			if(!in_array($amt->catid, $ctrack)) {
				$ajax->Data->cats[] = ['id' => $amt->catid, 'name' => $amt->catname];
				$ctrack[] = $amt->catid;
			}
			$ajax->Data->months[$m]['net'] += $amt->amount;
			$ajax->Data->months[$m][$amt->amount < 0 ? 'spent' : 'made'] += $amt->amount;
			$ajax->Data->months[$m]['cats'][+$amt->catid] = $amt->amount;
		}
		usort($ajax->Data->cats, 'AlphabetizeNamed');
	} else
		$ajax->Fail('error looking up monthly spending by category');
}

function AlphabetizeNamed($a, $b) {
	return strcmp($a['name'], $b['name']);
}
