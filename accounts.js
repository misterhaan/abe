$(function() {
	ko.applyBindings(new AccountListModel());
});

/**
 * View model for the account list.
 */
function AccountListModel() {
	/**
	 * Reference to the account list model for event handlers that might change the value of this.
	 */
	var self = this;

	/**
	 * Whether the account list is currently loading.
	 */
	self.loading = ko.observable(true);

	/**
	 * List of accounts.
	 */
	self.accounts = ko.observableArray([]);

	// load the account list
	$.get("?ajax=accountlist", {}, function(result) {
		if(!result.fail)
			for(var a = 0; a < result.accounts.length; a++)
				self.accounts.push(result.accounts[a]);
		else
			alert(result.message);
		self.loading(false);
	}, "json");
}
