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
	 * Upload the transactions file and import it into the database.
	 */
	self.Import = function() {
		$("#importtrans button").prop("disabled", true).addClass("waiting");
		$.post({url: "?ajax=import", data: new FormData($("#importtrans")[0]), cache: false, contentType: false, processData: false, success: function(result) {
			$("#importtrans button").prop("disabled", false).removeClass("waiting");
			if(!result.fail)
				alert("Imported " + result.count + " transactions.");
			else
				alert(result.message);
		}, dataType: "json"});
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
