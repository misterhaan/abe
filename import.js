$(function() {
	ko.applyBindings(new ImportModel(), $("#importtrans")[0]);
});

/**
 * View model for the transaction import form.
 */
function ImportModel() {
	/**
	 * Reference to the transaction import model for event handlers that might change the value of this.
	 */
	var self = this;
	/**
	 * ID of the account to import into.
	 */
	self.account = ko.observable(false);
	/**
	 * List of accounts for the account select field.
	 */
	self.accountlist = ko.observableArray([]);
	/**
	 * Full object of the selected account.
	 */
	self.selectedAccount = ko.computed(function() {
		for(var a = 0; a < self.accountlist().length; a++)
			if(self.accountlist()[a].id == self.account())
				return self.accountlist()[a];
		return false;
	});
	/**
	 * Previews from transactions ready to import.
	 */
	self.previews = ko.observableArray([]);

	// Load the list of accounts.
	$.get("accounts.php?ajax=accountlist", {}, function(result) {
		if(!result.fail) {
			for(var a = 0; a < result.accounts.length; a++)
				self.accountlist.push(result.accounts[a]);
			self.accountlist.sort(function(a, b) { return a.name.localeCompare(b.name); });
			self.account(FindAccountID());
		} else
			alert(result.message);
	}, "json");

	/**
	 * Upload the transactions file and get back a preview of the transactions it
	 * contains.
	 */
	self.Preview = function() {
		$("#importtrans button").prop("disabled", true).addClass("waiting");
		var acctid = $("select").val();
		var acctname = $("select option[value='" + acctid + "']").text();
		//var acctname = self.accountlist()[
		$.post({url: "?ajax=preview", data: new FormData($("#importtrans")[0]), cache: false, contentType: false, processData: false, success: function(result) {
			$("#importtrans button").prop("disabled", false).removeClass("waiting");
			if(result.fail)
				alert(result.message);
			else {
				result.preview.acctid = acctid;
				result.preview.acctname = acctname;
				result.preview.saved = ko.observable(false);
				result.preview.working = ko.observable(false);
				self.previews.unshift(result.preview);
			}
		}, dataType: "json"});
	};

	/**
	 * Save previewed transactions to the database.
	 * @param object Preview data from self.previews().
	 */
	self.Save = function(preview) {
		preview.working(true);
		$.post("?ajax=save", {acctid: preview.acctid, transactions: preview.transactions, net: preview.net}, function(result) {
			if(result.fail)
				alert(result.message);
			else
				preview.saved(true);
			preview.working(false);
		}, "json");
	};
}

/**
 * Find the account ID in the query string, if present.
 * @returns string|bool ID of the default account to import into, or false if no account specified.
 */
function FindAccountID() {
	var qs = window.location.search.substring(1).split("&");
	for(var i = 0; i < qs.length; i++) {
		var p = qs[i].split("=");
		if(p[0] = "acct")
			return p[1];
	}
	return false;
}
