$(function() {
	ko.applyBindings(VM = new SavingVM());
});

function SavingVM() {
	var self = this;
	this.funds = ko.observableArray([]);
	this.closedFunds = ko.observableArray([]);

	this.isEditing = ko.observable(false);
	this.showClosed = ko.observable(false);

	this.newName = ko.observable("");
	this.newTarget = ko.observable("");
	this.newBalance = ko.observable("");

	this.newIsValid = ko.pureComputed(function() {
		return self.newName().trim() != "";
	});

	this.Load = function() {
		$.get("api/fund/list", null, function(result) {
			if(result.fail)
				alert(result.message);
			else
				self.funds(result.funds.map(function(f) {return new FundVM(f);}));
		}, "json");
		$.get("api/fund/listClosed", null, function(result) {
			if(result.fail)
				alert(result.message);
			else
				self.closedFunds(result.funds.map(function(f) {return new ClosedFundVM(f);}));
		}, "json");
	};
	this.Load();

	this.AddFund = function() {
		var newFund = {name: self.newName(), target: self.newTarget(), balance: self.newBalance()};
		$.post("api/fund/add", newFund, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				newFund.id = result.id;
				newFund.targetDisplay = result.targetDisplay;
				newFund.balanceDisplay = result.balanceDisplay;
				newFund.sort = self.funds().length;
				self.funds.push(new FundVM(newFund));
				self.newName("");
				self.newTarget("");
				self.newBalance("");
			}
		}, "json");
	};

	this.ToggleClosedFunds = function() {
		self.showClosed(!self.showClosed());
	};
}

function FundVM(fund) {
	var self = this;
	this.id = fund.id;
	this.sort = ko.observable(+(fund.sort || 0));
	this.name = ko.observable(fund.name);
	this.balance = ko.observable(+(fund.balance || 0));
	this.balanceDisplay = ko.observable(fund.balanceDisplay);
	this.target = ko.observable(+(fund.target || 0));
	this.targetDisplay = ko.observable(fund.targetDisplay);

	this.isEditing = ko.observable(false);

	this.percent = ko.pureComputed(function() {
		if(self.target())
			return Math.max(0, Math.min(100, 100 * self.balance() / self.target()));
		if(self.balance())
			return 100;
		return 0;
	});

	this.Edit = function() {
		self.isEditing(true);
		VM.isEditing(true);
	};

	this.Save = function(fund, event) {
		var updated = {id: self.id, name: self.name(), target: self.target(), balance: self.balance()};
		$.post(event.target.href, updated, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.balanceDisplay(result.balanceDisplay);
				self.targetDisplay(result.targetDisplay);
				VM.isEditing(false);
				self.isEditing(false);
			}
		}, "json");
	};
}

function ClosedFundVM(fund) {
	var self=this;
	this.id = fund.id;
	this.name = ko.observable(fund.name);

	this.isEditing = ko.observable(false);
	this.newBalance = ko.observable("");
	this.newTarget = ko.observable("");

	this.Edit = function() {
		self.isEditing(true);
		VM.isEditing(true);
	};

	this.ReOpen = function(fund, event) {
		var reopened = {id: self.id, name: self.name(), target: self.newTarget(), balance: self.newBalance()};
		if(!+reopened.target && !+reopened.balance)
			alert("A fund must have either a current balance or target balance (or both) in order to reopen.");
		else
			$.post(event.target.href, reopened, function(result) {
				if(result.fail)
					alert(result.message);
				else {
					reopened.targetDisplay = result.targetDisplay;
					reopened.balanceDisplay = result.balanceDisplay;
					reopened.sort = VM.funds().length;
					VM.funds.push(new FundVM(newFund));
					VM.closedFunds.remove(self);
					VM.isEditing(false);
				}
			}, "json");
	};
}
