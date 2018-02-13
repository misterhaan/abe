<?php
require_once __DIR__ . '/etc/class/abe.php';

$html = new abeHtml();
$html->EnableBookmark('Bookmark this spending view on the main menu');
$html->Open('Spending');
$html->FormAddBookmark();
?>
			<h1>Spending</h1>

			<header id=pagesettings>
				<div>
					<label><input type=radio name=summarysize value=monthly data-bind="checked: summarysize"><span title="Summarize data by month">Monthly</span></label>
					<label><input type=radio name=summarysize value=yearly data-bind="checked: summarysize"><span title="Summarize data by year">Yearly</span></label>
				</div>
				<div>
					<label><input type=radio name=summarytype value=net data-bind="checked: summarytype"><span title="Bar graph of overall income and spending"></span></label>
					<label><input type=radio name=summarytype value=cat data-bind="checked: summarytype"><span title="Line graph of category spending"></span></label>
					<label><input type=radio name=summarytype value=det data-bind="checked: summarytype"><span title="Table of category spending amounts"></span></label>
				</div>
			</header>

			<svg id=monthtrend viewBox="0 0 800 400" data-bind="visible: summarytype() == 'net'"></svg>
			<svg id=monthcattrend viewBox="0 0 800 400" data-bind="visible: summarytype() == 'cat'"></svg>

			<div id=spendmonthcat data-bind="visible: summarytype() == 'det'"><div><table>
				<thead><tr>
					<td></td>
					<!-- ko foreach: dates -->
					<th data-bind="text: name"></th>
					<!-- /ko -->
				</tr></thead>
				<tbody>
					<tr class=total>
						<th>Total</th>
						<!-- ko foreach: dates -->
						<td><a data-bind="text: net, attr: {href: 'transactions.php#!datestart=' + start + '/dateend=' + end}"></a></td>
						<!-- /ko -->
					</tr>
					<!-- ko foreach: cats -->
						<!-- ko if: subcats -->
						<tr class=group data-bind="click: $root.ToggleCategory, css: {expand: $root.expandedCats().indexOf(id) < 0, collapse: $root.expandedCats().indexOf(id) > -1}">
							<th data-bind="text: name"></th>
							<!-- ko foreach: $root.dates -->
							<td data-bind="text: $root.findParentAmount($index(), +$parent.id)"></td>
							<!-- /ko -->
						</tr>
						<!-- ko if: $root.expandedCats().indexOf(id) > -1 --><!-- ko foreach: subcats -->
						<tr class=subcat>
							<th data-bind="text: name"></th>
							<!-- ko foreach: $root.dates -->
							<td><a data-bind="text: cats[$parent.id], attr: {href: 'transactions.php#!cats=' + +$parent.id + '/datestart=' + start + '/dateend=' + end}"></a></td>
							<!-- /ko -->
						</tr>
						<!-- /ko --><!-- /ko -->
						<!-- /ko -->
						<!-- ko ifnot: subcats -->
						<tr>
							<th data-bind="text: name"></th>
							<!-- ko foreach: $root.dates -->
							<td><a data-bind="text: cats[+$parent.id], attr: {href: 'transactions.php#!cats=' + +$parent.id + '/datestart=' + start + '/dateend=' + end}"></a></td>
							<!-- /ko -->
						</tr>
						<!-- /ko -->
					<!-- /ko -->
				</tbody>
			</table></div></div>
<?php
$html->Close();
