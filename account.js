$(function() {
	ko.applyBindings(new AccountModel(), $("main")[0]);
});

/**
 * View model for an account that's being edited.
 */
function AccountModel() {
	/**
	 * Reference to the account model for event handlers that might change the value of this.
	 */
	var self = this;
	/**
	 * ID of this account in the database.
	 */
	self.id = FindID();
	/**
	 * Database ID of the bank this account is through.
	 */
	self.bank = ko.observable(false);
	/**
	 * Database ID of this account's type.
	 */
	self.type = ko.observable(false);
	/**
	 * Name of this account.
	 */
	self.name = ko.observable('');
	/**
	 * The account's current balance as a number.
	 */
	self.balance = ko.observable(0.00);
	/**
	 * The account's current balance, formatted with two decimal places.
	 */
	self.balanceFormatted = ko.pureComputed({
		read: function() { return self.balance().toFixed(2); },
		write: function(value) { self.balance(parseFloat(value)); }
	});
	/**
	 * Whether the account is closed.
	 */
	self.closed = ko.observable(false);

	/**
	 * Options for the bank select field.
	 */
	self.banklist = ko.observableArray([]);
	/**
	 * Options for the account type select field.
	 */
	self.typelist = ko.observableArray([]);

	// load banklist and typelist from the server.
	$.get("?ajax=lists", {}, function(result) {
		if(result.fail)
			alert(result.message);
		else {
			self.banklist(result.banks);
			self.typelist(result.types);
			// now that banklist and typelist are loaded, get the values for this account if editing an existing account
			if(self.id) {
				$.get("?ajax=get&id=" + self.id, {}, function(result) {
					if(result.fail)
						alert(result.message);
					else {
						self.bank(result.bank);
						self.type(result.account_type);
						self.name(result.name);
						self.balance(+result.balance);
						self.closed(+result.closed);
					}
				}, "json");
			}
		}
	}, "json");

	/**
	 * Save the account and return to the accounts page.
	 */
	self.Save = function() {
		$("#editaccount button").prop("disabled", true).addClass("working");
		$.post("?ajax=save", {id: self.id, bank: self.bank(), name: self.name(), type: self.type(), balance: self.balance(), closed: self.closed() ? 1 : 0}, function(result) {
			if(result.fail) {
				$("#editaccount button").prop("disabled", false).removeClass("working");
				alert(result.message);
			} else
				window.location.href = "accounts.php";
		}, "json");
	};
}

/**
 * Find the account ID in the query string, if present.
 * @returns string|bool ID of the account to edit, or false if creating a new account.
 */
function FindID() {
	var qs = window.location.search.substring(1).split("&");
	for(var i = 0; i < qs.length; i++) {
		var p = qs[i].split("=");
		if(p[0] = "id")
			return p[1];
	}
	return false;
}
