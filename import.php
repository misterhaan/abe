<?php
require_once __DIR__ . '/etc/class/abe.php';

$html = new abeHtml();
$html->Open('Import Transactions');
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
					<span class=field data-bind="text: selectedAccount().newestDisplay"></span>
				</label>
				<label>
					<span class=label>Transactions:</span>
					<span class=field><input name=transfile type=file data-bind="event: {change: Preview}"></span>
				</label>

				<!-- ko foreach: previews -->
				<section class="transactions preview">
					<h2 data-bind="text: name + ' → ' + acctname"></h2>
					<header>
						<span class=count data-bind="text: transactions.length + ' transactions'"></span>
						<span class=duplicates data-bind="text: (dupeCount * 100 / transactions.length) + '% duplicates'"></span>
						<span class=amount data-bind="text: net.toFixed(2) + ' net'"></span>
						<span class=status data-bind="visible: saved">Imported</span>
						<button data-bind="visible: !saved(), click: $root.Save, css: {working: working()}, enable: !working()">Save</button>
						<a class=dismiss href="#done" data-bind="click: $root.Done" title="Remove this preview">Dismiss</a>
					</header>
					<ul data-bind="foreach: transactions">
						<li class=transaction>
							<div class=quick>
								<div class=name data-bind="text: name"></div>
								<div class=amount data-bind="text: amount.toFixed(2), css: {duplicate: duplicate}, attr: {title: duplicate ? 'Abe already has this transaction' : null}"></div>
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
