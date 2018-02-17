/**
 * Delay before hiding suggestions when the field loses focus.  If clicking a
 * suggestion doesn't work, this should be increased.
 */
var hideSuggestDelay = 250;

$(function() {
	ko.applyBindings(TransactionsModel, $("#transactions")[0]);
	SetBookmarkSpec(window.location.hash);
	$("a[href='#showFilters']").click(function(e) {
		TransactionsModel.showFilters(!TransactionsModel.showFilters());
		return false;
	});
});

/**
 * Full transaction view keyboard shortcuts:
 * ESC closes full view (and saves any changes)
 * Page Up moves to the previous transaction
 * Page Down moves to the next transaction
 */
$(window).keydown(function(e) {
	switch(e.keyCode) {
		case 27:  // esc
			if(TransactionsModel.showFilters())
				TransactionsModel.CancelFilters();
			else
				TransactionsModel.CloseAndSave();
			return false;
		case 33:  // page up
			TransactionsModel.SelectPrevious();
			return !TransactionsModel.selection();
		case 34:  // page down
			TransactionsModel.SelectNext();
			return !TransactionsModel.selection();
	}
});

/**
 * Make sure any unsaved changes get saved.
 */
$(window).on("beforeunload", function() {
	if(TransactionsModel.changed)
		TransactionsModel.Save();
});

/**
 * View model for the transactions list.
 */
var TransactionsModel = new function() {
	/**
	 * Reference to the transactions list model for event handlers that might change the value of this.
	 */
	var self = this;

	/**
	 * Whether more transactions are being loaded.
	 */
	self.loading = ko.observable(false);
	/**
	 * Whether any transactions have been changed (and thus need to be saved).
	 */
	self.changed = false;
	/**
	 * Whether changes are being saved.
	 */
	self.saving = ko.observable(false);

	/**
	 * List of dates that have transactions.  Each date contains at least one transaction.
	 */
	self.dates = ko.observableArray([]);
	/**
	 * Transaction that is currently selected for full view.  False for none.
	 */
	self.selection = ko.observable(false);
	/**
	 * Whether there are more transactions that could be loaded.
	 */
	self.more = ko.observable(false);

	/**
	 * Accounts to suggest.
	 */
	self.accounts = ko.observableArray([]);
	/**
	 * Keyboard-highlighted account.  False for none.
	 */
	self.acctCursor = ko.observable(false);
	/**
	 * Categories to suggest.
	 */
	self.categories = ko.observableArray([]);
	/**
	 * Last transaction category field to receive focus.  When we need the active
	 * category, it's this.
	 */
	self.activeCategory = ko.observable("");
	/**
	 * Category choices for the active category field of the selected transaction.
	 * Does not include categories already chosen or that do not match what's been
	 * typed into the field.
	 */
	self.categoriesForTransaction = ko.pureComputed(function() {
		self = self || TransactionsModel;
		var cats = [];
		if(self.selection() && self.activeCategory()) {
			var search = self.activeCategory().name();
			for(var c = 0; c < self.categories().length; c++) {
				var cat = self.categories()[c];
				if((!search || cat.groupname.containsAnyCase(search) || cat.name.containsAnyCase(search)) && !self.selection().categories().find(function(ipc) { return ipc.name() != search && ipc.name() == cat.name; }))
					cats.push(HighlightCategory(cat, search));
			}
		}
		return cats;
	});
	/**
	 * Keyboard-highlighted category.  Used for filters and transactions full view.  False for none.
	 */
	self.catCursor = ko.observable(false);

	/**
	 * Whether the filters menu should be displayed.
	 */
	self.showFilters = ko.observable(false);
	self.showFilters.subscribe(function() {
		if(self.showFilters()) {
			self.filterAcct("");
			self.filterCat("");
			// use slice to make copies.  they will be restored on cancel.
			self.oldFilters = {accounts:  self.filterAccounts().slice(), categories: self.filterCategories().slice(), dateStart: self.dateStart(), dateEnd: self.dateEnd(), minAmount: self.minAmount(), searchName: self.searchName()};
		}
	});

	/**
	 * Accounts to include transactions from.
	 */
	self.filterAccounts = ko.observableArray([]);
	/**
	 * The account field on the filter menu.
	 */
	self.filterAcct = ko.observable("");
	self.filterAcct.subscribe(function() {
		if(self.filterAcct())
			self.suggestingAccounts(true);
	});
	/**
	 * Whether the accounts field dropdown is visible.
	 */
	self.suggestingAccounts = ko.observable(false);
	self.suggestingAccounts.subscribe(function() {
		if(!self.suggestingAccounts())
			self.acctCursor(false);
	});
	/**
	 * Options for the dropdown of the account field in the filter menu.
	 */
	self.accountsForFilter = ko.pureComputed(function() {
		self = self || TransactionsModel;
		var accts = [];
		for(var a = 0; a < self.accounts().length; a++)
			if((!self.filterAcct() || self.accounts()[a].name.containsAnyCase(self.filterAcct())) && self.filterAccounts().indexOf(self.accounts()[a]) < 0)
				accts.push(self.accounts()[a]);
		return accts;
	});

	/**
	 * Categories to include transactions from.
	 */
	self.filterCategories = ko.observableArray([]);
	/**
	 * Category field on the filter menu.
	 */
	self.filterCat = ko.observable("");
	self.filterCat.subscribe(function() {
		if(self.filterCat())
			self.suggestingFilterCategories(true);
	});
	/**
	 * Whether the filter menu category field dropdown is visible.
	 */
	self.suggestingFilterCategories = ko.observable(false);
	self.suggestingFilterCategories.subscribe(function() {
		if(!self.suggestingFilterCategories())
			self.catCursor(false);
	});
	/**
	 * Options for the dropdown of the category field in the filter menu.
	 */
	self.categoriesForFilter = ko.computed(function() {
		self = self || TransactionsModel;
		var cats = [];
		var uncat = {id: 0, name: "(uncategorized)", groupname: ""};
		var search = self.filterCat();
		if((!search || uncat.name.containsAnyCase(search)) && !self.filterCategories().find(function(chosen) {return chosen.id == 0;}))
			cats.push(HighlightCategory(uncat, search));
		for(var c = 0; c < self.categories().length; c++) {
			var cat = self.categories()[c];
			if((!search || cat.name.containsAnyCase(search) || cat.groupname.containsAnyCase(search)) && self.filterCategories().indexOf(cat) < 0)
				cats.push(HighlightCategory(cat, search));
		}
		return cats;
	});

	/**
	 * Earliest date to include transactions from.  Should be YYYY-MM-DD or an empty string.
	 */
	self.dateStart = ko.observable("");
	self.dateStart.subscribe(function() {
		if(!(self.dateStart() == "" || /^[0-9]{4}-[0-9]{2}-[0-9]{2}/.test(self.dateStart()))) {
			// attempt to format the date to YYYY-MM-DD
			var d = new Date(self.dateStart());
			self.dateStart(d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2) );
		}
	});
	/**
	 * Latest date to include transactions from.  Should be YYYY-MM-DD or an empty string.
	 */
	self.dateEnd = ko.observable("");
	self.dateEnd.subscribe(function() {
		if(!(self.dateEnd() == "" || /^[0-9]{4}-[0-9]{2}-[0-9]{2}/.test(self.dateEnd()))) {
			// attempt to format the date to YYYY-MM-DD
			var d = new Date(self.dateEnd());
			self.dateEnd(d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2) );
		}
	});

	/**
	 * Minimum transaction amount to include.
	 */
	self.minAmount = ko.observable("");
	self.minAmount.subscribe(function() {
		if(self.minAmount() != "")
			self.minAmount((+self.minAmount()).toFixed(2));
	});

	/**
	 * Include transactions that contain this text in their name.
	 */
	self.searchName = ko.observable("");

	/**
	 * Initialize filters from the location hash.
	 */
	self.InitializeFilters = function() {
		var info = ParseHash();
		if(info.accts)
			self.InitializeAccountFilter(info.accts);
		if(info.cats)
			self.InitializeCategoryFilter(info.cats);
		if(info.datestart)
			self.dateStart(info.datestart);
		if(info.dateend)
			self.dateEnd(info.dateend);
		if(info.minamount)
			self.minAmount(info.minamount);
		if(info.search)
			self.searchName(info.search);
	};

	/**
	 * Initialize filtered accounts from the location hash.
	 * @param accounts string Comma-separated account IDs to include.
	 */
	self.InitializeAccountFilter = function(accounts) {
		accounts = accounts.split(",");
		for(var fa = 0; fa < accounts.length; fa++)
			if(accounts[fa])  // skip blank or zero values
				for(var a = 0; a < self.accounts().length; a++)
					if(self.accounts()[a].id == +accounts[fa]) {
						self.filterAccounts.push(self.accounts()[a]);
						break;
					}
	};

	/**
	 * Initialize filtered categories from the location hash.
	 * @param cats string Comma-separated category IDs to include.
	 */
	self.InitializeCategoryFilter = function(cats) {
		cats = cats.split(",");
		for(var fc = 0; fc < cats.length; fc++)
			if(cats[fc] === "0")  // category zero isn't in the category list
				self.filterCategories.push({id: 0, name: "(uncategorized)", subs: []});
			else if(cats[fc])  // skip blank values
				findcat: for(var pc = 0; pc < self.categories().length; pc++)
					if(self.categories()[pc].id == +cats[fc]) {
						self.filterCategories.push(self.categories()[pc]);
						break;
					} else
						for(var sc = 0; sc < self.categories()[pc].subs.length; sc++)
							if(self.categories()[pc].subs[sc].id == +cats[fc]) {
								self.filterCategories.push(self.categories()[pc].subs[sc]);
								break findcat;
							}
	};

	/**
	 * Get more transaction from the server.
	 */
	self.GetTransactions = function(checkHash) {
		self.loading(true);
		if(checkHash)
			self.InitializeFilters();
		// TODO:  move to API
		$.get("?ajax=get", GetParams(self.dates()), function(result) {
			if(!result.fail) {
				for(var d = 0; d < result.dates.length; d++)
					if(self.dates().length && self.dates().last().date == result.dates[d].date)
						for(var t = 0; t < result.dates[d].transactions.length; t++)
							self.dates().last().transactions.push(ObserveTransaction(result.dates[d].transactions[t]));
					else
						self.dates.push(ObserveDate(result.dates[d]));
				self.more(result.more);
			} else
				alert(result.message);
			self.loading(false);
		}, "json");
	};

	/**
	 * Load categories from the server.  Also loads the first set of transactions
	 * if accounts have been loaded.
	 */
	(self.GetCategories = function(firstTime) {
		self.loading(true);
		$.get("api/category/list", null, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.categories(result.categories);
				if(firstTime && self.accountsLoaded)
					self.GetTransactions(true);
				self.categoriesLoaded = true;
			}
		}, "json");
	})(true);

	/**
	 * Load accounts from the server.  Also loads the first set of transactions if
	 * categories have been loaded.
	 */
	(self.GetAccounts = function() {
		$.get("accounts.php?ajax=accountlist", null, function(result) {
			if(!result.fail) {
				self.accounts(result.accounts);
				if(self.categoriesLoaded)
					self.GetTransactions(true);
				self.accountsLoaded = true;
			}
			else
				alert(result.message);
		}, "json");
	})();

	/**
	 * Select the specified transaction for full view.
	 * @param transaction Transaction to select.
	 */
	self.Select = function(transaction) {
		if(self.selection())
			self.selection().suggestingCategories(false);
		self.selection(transaction);
	};

	/**
	 * Select the previous transaction for full view.  Does not wrap around from
	 * first to last.
	 */
	self.SelectPrevious = function() {
		if(self.selection()) {
			// track focused field so the same field can be focused on the previous transaction
			var focus = $(":focus").parent();
			focus = focus.length ? focus = focus[0].className : false;
			// find and select the previous transaction
			for(var d = 0; d < self.dates().length; d++) {
				var t = self.dates()[d].transactions().indexOf(self.selection());
				if(t == 0)
					if(d == 0)
						return;  // first transaction already selected
					else {
						self.Select(self.dates()[d - 1].transactions().last());
						if(focus)
							$(".full:visible ." + focus + " input").focus();
						return;
					}
				else if(t > 0) {
					self.Select(self.dates()[d].transactions()[t - 1]);
					if(focus)
						$(".full:visible ." + focus + " input").focus();
					return;
				}
			}
		}
	};

	/**
	 * Select the next transaction for full view.  Does not wrap around from last
	 * to first.
	 */
	self.SelectNext = function() {
		if(self.selection()) {
			// track focused field so the same field can be focused on the previous transaction
			var focus = $(":focus").parent();
			focus = focus.length ? focus = focus[0].className : false;
			// find and select the next transaction
			for(var d = 0; d < self.dates().length; d++) {
				var t = self.dates()[d].transactions().indexOf(self.selection());
				if(t == self.dates()[d].transactions().length - 1)
					if(d == self.dates().length - 1)
						return;  // last transaction already selected
					else {
						self.Select(self.dates()[d + 1].transactions()[0]);
						if(focus)
							$(".full:visible ." + focus + " input").focus();
						return;
					}
				else if(t >= 0 && t < self.dates()[d].transactions().length - 1) {
					self.Select(self.dates()[d].transactions()[t + 1]);
					if(focus)
						$(".full:visible ." + focus + " input").focus();
					return;
				}
			}
		}
	};

	/**
	 * Close full view.
	 */
	self.SelectNone = function() {
		if(self.selection())
			self.selection().suggestingCategories(false);
		self.selection(false);
	};

	/**
	 * Save any outstanding transaction changes and close the full-view
	 * transaction.
	 */
	self.CloseAndSave = function() {
		$(":focus").blur();
		self.Save(true);
	};
	/**
	 * Save any outstanding transaction changes.
	 * @param close Whether to also close full view.
	 */
	self.Save = function(close) {
		if(self.changed) {
			self.saving(true);
			var transactions = [];  // list of transactions that were changed, for clearing the changed flag later
			var data = [];  // transaction data to send to the server
			for(var d = 0; d < self.dates().length; d++)
				for(var t = 0; t < self.dates()[d].transactions().length; t++)
					if(self.dates()[d].transactions()[t].changed) {
						var tr = self.dates()[d].transactions()[t];
						transactions.push(tr);
						// data needs the id to know which transaction, plus anything that (could have) changed.
						data.push({
							id: tr.id, name: tr.name().trim(), notes: tr.notes().trim(),
							catnames: tr.categories().map(function(c) {return c.name() ? c.name().trim() : "";}).join("\n"),
							catamounts: tr.categories().map(function(c) {return +c.amount();}).join("\n")
						});
					}
			if(data.length)  // should be true if self.changed was true
				$.post("?ajax=save", {transactions: data}, function(result) {
					self.saving(false);
					if(!result.fail) {
						self.GetCategories(false);
						if(close)
							self.SelectNone();
						//self.categories(result.categories);  // may have added categories, so reset category list.
						for(t = 0; t < transactions.length; t++)
							transactions[t].changed = false;
						self.changed = false;
					} else
						alert(result.message);
				}, "json");
			else {
				self.changed = false;
				self.saving(false);
				if(close)
					self.SelectNone();
			}
		} else if(close) {
			self.SelectNone();
		}
	};

	/**
	 * Track which transaction category field received focus most recently.
	 */
	self.CategoryFocus = function(category) {
		self.activeCategory(category);
	};

	/**
	 * Show category suggestions for the specified transaction category.
	 * @param category Category form transaction in full view mode.
	 */
	self.ShowSuggestions = function(category) {
		category.suggesting(true);
	};
	
	/**
	 * Show category suggestions for the filter menu's category field.
	 */
	self.ShowFilterCatSuggestions = function() {
		self.suggestingFilterCategories(true);
	};

	/**
	 * Show account suggestions for the filter menu's account field.
	 */
	self.ShowAcctSuggestions = function() {
		self.suggestingAccounts(true);
	};

	/**
	 * Hide suggestions for the specified transaction category.
	 * @param category Category from transaction in full view mode.
	 */
	self.HideSuggestions = function(category) {
		window.setTimeout(function() {
			category.suggesting(false);
			self.catCursor(false);
		}, hideSuggestDelay);  // need to delay this so tap / click events on the suggestion items fire
	};

	/**
	 * Hide category suggestions for the filter menu's category field.
	 */
	self.HideFilterCatSuggestions = function() {
		window.setTimeout(function() {
			self.suggestingFilterCategories(false);
		}, hideSuggestDelay);  // need to delay this so tap / click events on the suggestion items fire
	};

	/**
	 * Hide account suggestions for the filter menu's account field.
	 */
	self.HideFilterAcctSuggestions = function() {
		window.setTimeout(function() {
			self.suggestingAccounts(false);
		}, hideSuggestDelay);  // need to delay this so tap / click events on the suggestion items fire
	};

	/**
	 * Set the category of the full-view tranaction to the specified category.
	 * @param category Category being chosen.
	 */
	self.ChooseCategory = function(category) {
		self.activeCategory().name(category.plainName);
		self.activeCategory().suggesting(false);
		self.catCursor(false);
	};

	/**
	 * Include the specified category in the filter.
	 * @param category Category being included.
	 */
	self.ChooseFilterCategory = function(category) {
		self.filterCategories.push(category);
		self.suggestingFilterCategories(false);
	};

	/**
	 * Include the specified account in the filter.
	 * @param account Account being chosen.
	 */
	self.ChooseAccount = function(account) {
		self.filterAccounts.push(account);
		self.suggestingAccounts(false);
	};

	/**
	 * Remove the specified category from the filter.
	 * @param category Category to remove.
	 */
	self.RemoveFilterCategory = function(category) {
		self.filterCategories.splice(self.filterCategories.indexOf(category), 1);
	};

	/**
	 * Remove the specified account from the filter.
	 * @param account Account to remove.
	 */
	self.RemoveAccount = function(account) {
		self.filterAccounts.splice(self.filterAccounts.indexOf(account), 1);
	};

	/**
	 * Keyboard shortcuts for transaction category field:
	 * ESC hides suggestions
	 * Up arrow highlights the previous suggestion.  It will wrap from the top to the bottom.
	 * Down arrow highlights the next suggestion.  It will wrap from the bottom to the top.
	 * Enter selects the highlighted category.
	 * Tab selects the highlighted category and then moves to the next field.
	 */
	self.CategoryKey = function(category, e) {
		switch(e.keyCode) {
			case 27:  // escape
				if(category.suggesting()) {
					category.suggesting(false);
					e.stopImmediatePropagation();
					return false;
				}
				break;  // if it's not hiding the suggestions it should hide the full transaction view
			case 38:  // up arrow
				category.suggesting(true);
				if(TransactionsModel.catCursor()) {
					var i = TransactionsModel.categoriesForTransaction().indexOf(TransactionsModel.catCursor());
					if(i < 0)
						TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction().last());
					else if(i)
						TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction()[i - 1]);
					else
						TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction().last());
				} else
					TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction().last());
				return false;
			case 40:  // down arrow
				category.suggesting(true);
				if(TransactionsModel.catCursor()) {
					var i = TransactionsModel.categoriesForTransaction().indexOf(TransactionsModel.catCursor());
					if(i < 0)
						TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction()[0]);
					else if(i + 1 < TransactionsModel.categoriesForTransaction().length)
						TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction()[i + 1]);
					else
						TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction()[0]);
				} else
					TransactionsModel.catCursor(TransactionsModel.categoriesForTransaction()[0]);
				return false;
			case 13:  // enter key
				if(TransactionsModel.catCursor()) {
					category.name(TransactionsModel.catCursor().plainName);
					TransactionsModel.catCursor(false);
					category.suggesting(false);
					return false;
				}
				break;
			case 9:  // tab key
				if(TransactionsModel.catCursor()) {
					category.name(TransactionsModel.catCursor().name);
					TransactionsModel.catCursor(false);
					category.suggesting(false);
				}
				break;
		}
		return true;  // knockout will suppress the event unless we return true
	};

	/**
	 * Keyboard shortcuts for filter menu category field:
	 * ESC hides suggestions
	 * Up arrow highlights the previous suggestion.  It will wrap from the top to the bottom.
	 * Down arrow highlights the next suggestion.  It will wrap from the bottom to the top.
	 * Enter selects the highlighted category.
	 * Tab selects the highlighted category and then moves to the next field.
	 */
	self.CategoryFilterKey = function(model, e) {
		switch(e.keyCode) {
			case 27:  // escape
				if(self.suggestingFilterCategories()) {
					self.suggestingFilterCategories(false);
					e.stopImmediatePropagation();
					return false;
				}
				break;  // if it's not hiding the suggestions it should hide the filter menu
			case 38:  // up arrow
				self.suggestingFilterCategories(true);
				if(TransactionsModel.catCursor()) {
					var prevcat = false;
					for(var c = 0; c < self.categoriesForFilter().length; c++)
						if(self.categoriesForFilter()[c] == TransactionsModel.catCursor())
							if(prevcat) {
								TransactionsModel.catCursor(prevcat);
								return false;
							} else {
								prevcat = self.categoriesForFilter().last();
								if(prevcat.subs && prevcat.subs.length)
									prevcat = prevcat.subs.last();
								TransactionsModel.catCursor(prevcat);
								return false;
							}
						else {
							prevcat = self.categoriesForFilter()[c];
							for(var sc = 0; sc < self.categoriesForFilter()[c].subs.length; sc++)
								if(self.categoriesForFilter()[c].subs[sc] == TransactionsModel.catCursor()) {
									TransactionsModel.catCursor(prevcat);
									return false;
								} else
									prevcat = self.categoriesForFilter()[c].subs[sc];
						}
				}
				prevcat = self.categoriesForFilter().last();
				if(prevcat.subs && prevcat.subs.length)
					prevcat = prevcat.subs.last();
				TransactionsModel.catCursor(prevcat);
				return false;
			case 40:  // down arrow
				self.suggestingFilterCategories(true);
				if(TransactionsModel.catCursor()) {
					var nextcat = false;
					for(var c = self.categoriesForFilter().length - 1; c >= 0; c--) {
						for(var sc = self.categoriesForFilter()[c].subs.length - 1; sc >= 0; sc--)
							if(self.categoriesForFilter()[c].subs[sc] == TransactionsModel.catCursor())
								if(nextcat) {
									TransactionsModel.catCursor(nextcat);
									return false;
								} else {
									TransactionsModel.catCursor(self.categoriesForFilter()[0]);
									return false;
								}
							else
								nextcat = self.categoriesForFilter()[c].subs[sc];
						if(self.categoriesForFilter()[c] == TransactionsModel.catCursor())
							if(nextcat) {
								TransactionsModel.catCursor(nextcat);
								return false;
							} else {
								TransactionsModel.catCursor(self.categoriesForFilter()[0]);
								return false;
							}
						else
							nextcat = self.categoriesForFilter()[c];
					}
				}
				TransactionsModel.catCursor(self.categoriesForFilter()[0]);
				return false;
			case 13:  // enter key
				if(TransactionsModel.catCursor()) {
					self.ChooseFilterCategory(TransactionsModel.catCursor());
					return false;
				}
				break;
			case 9:  // tab key
				if(TransactionsModel.catCursor()) {
					self.ChooseFilterCategory(TransactionsModel.catCursor());
					self.suggestingFilterCategories(false);
				}
				break;
		}
		return true;  // knockout will suppress the event unless we return true
	};

	/**
	 * Keyboard shortcuts for filter menu account field:
	 * ESC hides suggestions
	 * Up arrow highlights the previous suggestion.  It will wrap from the top to the bottom.
	 * Down arrow highlights the next suggestion.  It will wrap from the bottom to the top.
	 * Enter selects the highlighted category.
	 * Tab selects the highlighted category and then moves to the next field.
	 */
	self.AccountFilterKey = function(model, e) {
		switch(e.keyCode) {
			case 27:  // escape
				if(self.suggestingAccounts()) {
					self.suggestingAccounts(false);
					e.stopImmediatePropagation();
					return false;
				}
				break;  // if it's not hiding the suggestions it should hide the filter menu
			case 38:  // up arrow
				self.suggestingAccounts(true);
				if(TransactionsModel.acctCursor()) {
					var prev = false;
					for(var a = 0; a < self.accountsForFilter().length; a++)
						if(self.accountsForFilter()[a] == TransactionsModel.acctCursor())
							if(prev) {
								TransactionsModel.acctCursor(prev);
								return false;
							} else {
								TransactionsModel.acctCursor(self.accountsForFilter().last());
								return false;
							}
						else
							prev = self.accountsForFilter()[a];
				}
				TransactionsModel.acctCursor(self.accountsForFilter().last());
				return false;
			case 40:  // down arrow
				self.suggestingAccounts(true);
				if(TransactionsModel.acctCursor()) {
					var next = false;
					for(var a = self.accountsForFilter().length - 1; a >= 0; a--)
						if(self.accountsForFilter()[a] == TransactionsModel.acctCursor())
							if(next) {
								TransactionsModel.acctCursor(next);
								return false;
							} else {
								TransactionsModel.acctCursor(self.accountsForFilter()[0]);
								return false;
							}
						else
							next = self.accountsForFilter()[a];
				}
				TransactionsModel.acctCursor(self.accountsForFilter()[0]);
				return false;
			case 13:  // enter key
				if(TransactionsModel.acctCursor()) {
					self.ChooseAccount(TransactionsModel.acctCursor());
					return false;
				}
				break;
			case 9:  // tab key
				if(TransactionsModel.acctCursor()) {
					self.ChooseAccount(TransactionsModel.acctCursor());
					self.suggestingAccounts(false);
				}
				break;
		}
		return true;  // knockout will suppress the event unless we return true
	};

	/**
	 * Clear loaded transactions and reload with the new filters.
	 */
	self.UpdateFilters = function() {
		self.showFilters(false);
		self.dates([]);
		self.UpdateHash();
		self.GetTransactions();
	};

	/**
	 * Update the hash with the current filter settings so they'll be remembered
	 * through a refresh or bookmark.
	 */
	self.UpdateHash = function() {
		var info = [];
		if(self.filterAccounts().length)
			info.push("accts=" + self.filterAccounts().map(function(a) { return a.id; }).join(","));
		if(self.filterCategories().length)
			info.push("cats=" + self.filterCategories().map(function(c) { return c.id; }).join(","));
		if(self.dateStart())
			info.push("datestart=" + self.dateStart());
		if(self.dateEnd())
			info.push("dateend=" + self.dateEnd());
		if(self.minAmount())
			info.push("minamount=" + self.minAmount());
		if(self.searchName())
			info.push("search=" + self.searchName());
		info = info.length ? "#!" + info.join("/") : "";
		if(info != window.location.hash) {
			if(info)
				window.location.hash = info;
			else
				history.pushState("", document.title, window.location.pathname);
			SetBookmarkSpec(info);
		}
	};

	/**
	 * Cancel changes to filters.
	 */
	self.CancelFilters = function() {
		self.showFilters(false);
		self.filterAccounts(self.oldFilters.accounts);
		self.filterCategories(self.oldFilters.categories);
		self.dateStart(self.oldFilters.dateStart);
		self.dateEnd(self.oldFilters.dateEnd);
		self.minAmount(self.oldFilters.minAmount);
		self.searchName(self.oldFilters.searchName);
	};

	/**
	 * Prevent a click event from being handled by a parent element.
	 * @param transaction Transaction in full view mode (not used but passed by knockout).
	 * @param e Event object which will be stopped from propagating.
	 */
	self.CaptureClick = function(transaction, e) {
		e.stopImmediatePropagation();
	};
};

/**
 * Make any transaction date properties that could change into knockout
 * observables so knockout can update the page when they change.
 * @param date Plain transaction date object.
 * @returns Observable transaction date object.
 */
function ObserveDate(date) {
	for(var t = 0; t < date.transactions.length; t++)
		date.transactions[t] = ObserveTransaction(date.transactions[t]);
	date.transactions = ko.observableArray(date.transactions);
	return date;
}

/**
 * Make any transaction properties that could change into knockout observables
 * so knockout can update the page when they change.
 * @param transaction Plain transaction object.
 * @returns Observable transaction object.
 */
function ObserveTransaction(transaction) {
	transaction.changed = false;
	(transaction.name = ko.observable(transaction.name)).subscribe(function() {
		transaction.changed = true;
		TransactionsModel.changed = true;
	});
	for(var c = 0; c < transaction.categories.length; c++)
		ObserveCategory(transaction.categories[c]);
	transaction.categories = ko.observableArray(transaction.categories);
	transaction.categoryList = ko.computed(function() {
		if(transaction.categories().length == 1 && !transaction.categories()[0].name())
			return "(uncategorized)";
		return transaction.categories().map(function(cat) { return cat.name(); }).join(", ");
	});
	(transaction.notes = ko.observable(transaction.notes)).subscribe(function() {
		transaction.changed = true;
		TransactionsModel.changed = true;
	});
	transaction.suggestingCategories = ko.observable(false);
	transaction.suggestingCategories.subscribe(function() {
		if(!transaction.suggestingCategories())
			TransactionsModel.catCursor(false);
	});
	return transaction;
}

/**
 * Make any transaction category properties that could change into knockout
 * observables so knockout can update the page when they change.
 * @param category Plain transaction category object.
 */
function ObserveCategory(category) {
	(category.name = ko.observable(category.name)).subscribe(function() {
		TransactionsModel.selection().changed = true;
		TransactionsModel.changed = true;
		if(!TransactionsModel.loading())
			category.suggesting(true);
	});
	(category.amount = ko.observable(category.amount)).subscribe(function() {
		if((category.amount().indexOf("+") > 0 || category.amount().indexOf("-") > 0 || category.amount().indexOf("*") > 0 || category.amount().indexOf("/") > 0) && /^-?\(?\d+(\.\d+)?([\+\-\*\/]\(?\-?\(?\d+(\.\d+)?\)?)*$/.test(category.amount()))
			category.amount((+eval(category.amount())).toFixed(2));
		else
			category.amount((+category.amount()).toFixed(2));
		var tran = TransactionsModel.selection();
		var blankcat = false;
		var diff = +tran.amount;
		for(var c = 0; c < tran.categories().length; c++)
			if(!tran.categories()[c].name())
				blankcat = tran.categories()[c];
			else
				diff -= +tran.categories()[c].amount();
		if(Math.abs(diff) >= .01)
			if(blankcat)
				blankcat.amount(diff.toFixed(2));
			else {
				blankcat = {name: "", amount: diff.toFixed(2)};
				ObserveCategory(blankcat);
				tran.categories.push(blankcat);
			}
		else if(blankcat)
			TransactionsModel.selection().categories.splice(TransactionsModel.selection().categories.indexOf(blankcat), 1);
	});
	category.suggesting = ko.observable(false);
	category.newCategory = ko.computed(function() {
		if(!category.name())
			return false;
		for(var c = 0; c < TransactionsModel.categories().length; c++)
			if(TransactionsModel.categories()[c].name == category.name())
				return false;
		return true;
	});
	// only accept digits, decimal point, parentheses, and basic math symbols
	category.AmountKey = function(category, e) {
		switch(e.which) {
			case 0:  // control keys
			case 8:  // backspace (firefox only)
			case 13:  // enter
			case 40:  // (
			case 41:  // )
			case 42:  // *
			case 43:  // +
			case 45:  // -
			case 46:  // .
			case 47:  // /
			case 48:  // 0
			case 49:  // 1
			case 50:  // 2
			case 51:  // 3
			case 52:  // 4
			case 53:  // 5
			case 54:  // 6
			case 55:  // 7
			case 56:  // 8
			case 57:  // 9
				return true;
		}
		console.log("which=" + e.which + ", keyCode=" + e.keyCode + ", charCode=" + e.charCode);
		return false;
	};
}

/**
 * Set up the parameters for looking up transaction from the server.
 * @param dates Transaction dates array.
 * @returns Parameters for GetTransactions.
 */
function GetParams(dates) {
	var params = {};
	if(dates.length) {
		params.oldest = dates.last();
		params.oldid = params.oldest.transactions().last().id;
		params.oldest = params.oldest.date;
	} else {
		params.oldest = "9999-12-31";
		params.oldid = 0;
	}
	params.accts = [];
	for(var a = 0; a < TransactionsModel.filterAccounts().length; a++)
		params.accts.push(TransactionsModel.filterAccounts()[a].id);
	params.accts = params.accts.join(",");
	params.cats = [];
	for(var c = 0; c < TransactionsModel.filterCategories().length; c++)
		params.cats.push(TransactionsModel.filterCategories()[c].id);
	params.cats = params.cats.join(",");
	params.datestart = TransactionsModel.dateStart();
	params.dateend = TransactionsModel.dateEnd();
	params.minamount = TransactionsModel.minAmount();
	params.search = TransactionsModel.searchName();
	return params;
}

/**
 * Knockout binding handler scrollTo, when updated to true, will scroll
 * vertically the least amount possible so that the element is visible.  If the
 * element is taller than the viewport, scroll so the element is at the top of
 * the viewport.
 */
ko.bindingHandlers.scrollTo = {
	update: function(element, valueAccessor) {
		if(ko.unwrap(valueAccessor())) {
			var r = element.getBoundingClientRect();
			if(r.top < 0)
				$("body").animate({scrollTop: $(element).offset().top}, 100);
			else if(r.bottom > $(window).height())
				if(r.height + 3 > $(window).height())
					$("body").animate({scrollTop: $(element).offset().top}, 100);
				else
					$("body").animate({scrollTop: $(element).offset().top - $(window).height() + r.height + 3}, 100);
		}
	}
};

function HighlightCategory(cat, search) {
	return {id: cat.id, plainName: cat.name, name: HighlightString(cat.name, search), groupname: HighlightString(cat.groupname, search)};
}

function HighlightString(str, search) {
	var html = $("<div/>").text(str).html();
	return search ? html.replace(new RegExp("(" + EscapeRegExp(search) + ")", "ig"), "<em>$1</em>") : html;
}

function EscapeRegExp(str) {
  return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
}

/**
 * Returns the last item in the array.
 */
Array.prototype.last = function() {
	return this[this.length - 1];
};

/**
 * Whether the string contains the needle, case-insensitive.
 */
String.prototype.containsAnyCase = function(needle) {
	return !needle || this.toLowerCase().indexOf(needle.toLowerCase()) >= 0;
};

/**
 * Whether the array contains at least one object with a name property that
 * contains the needle, case-insensitive.
 */
Array.prototype.nameContainsAnyCase = function(needle) {
	for(var c = 0; c < this.length; c++)
		if(!needle || this[c].name.containsAnyCase(needle))
			return true;
	return false;
};
