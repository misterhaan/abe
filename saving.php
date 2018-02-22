<?php
require_once __DIR__ . '/etc/class/abe.php';

$html = new abeHtml();
$html->Open('Saving');
// TODO:  show savings allocation donut
?>
			<h1>Saving</h1>

			<!-- ko foreach: funds -->
			<div class=fund>
				<h2>
					<!-- ko ifnot: isEditing -->
						<!-- ko ifnot: $root.isEditing -->
							<a href="#edit" class=edit title="Edit this fund" data-bind="click: Edit"><span>Edit</span></a>
						<!-- /ko -->
						<span class=name data-bind="text: name"></span>
						<a href="api/fund/close" class=remove title="Close this fund"><span>Close</span></a>
					<!-- /ko -->
					<!-- ko if: isEditing -->
						<input data-bind="value: name" class=name maxlength=32 required>
						<a href="api/fund/save" class=save title="Save this fund" data-bind="click: Save"><span>Save</span></a>
					<!-- /ko -->
				</h2>
				<div class=percentfield>
					<div class=percentvalue data-bind="style: {width: percent() + '%'}"></div>
				</div>
				<div class=values>
					<!-- ko ifnot: isEditing -->
						<span data-bind="text: balanceDisplay"></span>
						of
						<span data-bind="text: targetDisplay"></span>
					<!-- /ko -->
					<!-- ko if: isEditing -->
						<input data-bind="value: balance, event: {keypress: ko.nonVmHandlers.AmountKey}" type=number step=.01>
						of
						<input data-bind="value: target, event: {keypress: ko.nonVmHandlers.AmountKey}" type=number step=.01>
					<!-- /ko -->
				</div>
			</div>
			<!-- /ko -->

			<!-- ko if: showClosed -->
			<!-- ko foreach: closedFunds -->
			<div class=fund>
				<h2>
					<!-- ko ifnot: isEditing -->
						<!-- ko ifnot: $root.isEditing -->
							<a href="#edit" class=edit title="Reopen this fund" data-bind="click: Edit"><span>Edit</span></a>
						<!-- /ko -->
						<span class=name data-bind="text: name"></span>
					<!-- /ko -->
					<!-- ko if: isEditing -->
						<input data-bind="value: name" class=name maxlength=32 required>
						<a href="api/fund/reopen" class=save title="Reopen this fund" data-bind="click: Save"><span>Save</span></a>
					<!-- /ko -->
				</h2>
				<!-- ko if: isEditing -->
					<div class=values>
						<input data-bind="value: newBalance, event: {keypress: ko.nonVmHandlers.AmountKey}" type=number step=.01>
						of
						<input data-bind="value: newTarget, event: {keypress: ko.nonVmHandlers.AmountKey}" type=number step=.01>
					</div>
				<!-- /ko -->
			</div>
			<!-- /ko -->
			<!-- /ko -->

			<!-- ko if: closedFunds().length -->
				<div id=toggleClosed><a href=#toggleClosed data-bind="text: showClosed() ? 'Hide inactive accounts ↑' : 'Show inactive accounts ↓', click: ToggleClosedFunds"></a></div>
			<!-- /ko -->

			<div id=addFund class=fund data-bind="ifnot: isEditing">
				<h2><input data-bind="textInput: newName" placeholder="New name" maxlength=32 required></h2>
				<div class=values>
					<input data-bind="value: newBalance, event: {keypress: ko.nonVmHandlers.AmountKey}" type=number step=.01 placeholder=Current>
					of
					<input data-bind="value: newTarget, event: {keypress: ko.nonVmHandlers.AmountKey}" type=number step=.01 required placeholder=Target>
				</div>
				<button class=add title="Add a new saving fund with the specified information" data-bind="enable: newIsValid(), click: AddFund"> Add</button>
			</div>
<?php
$html->Close();
