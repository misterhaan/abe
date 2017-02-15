$(function() {
	ko.applyBindings(TransactionsModel, $("#transactions")[0]);
	$("a[href='#showFilters']").click(function(e) {
		TransactionsModel.showFilters(true);
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
			if(TransactionsModel.showFilters()) {
				if(TransactionsModel.suggestingFilterCategories())
					TransactionsModel.suggestingFilterCategories(false);
				else if(TransactionsModel.suggestingAccounts())
					TransactionsModel.suggestingAccounts(false);
				else
					TransactionsModel.CancelFilters();
			} else
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
	 * Whether there are more transactions that could be loaded.
	 */
	self.more = ko.observable(false);

	self.accounts = ko.observableArray([]);
	self.acctCursor = ko.observable(false);
	/**
	 * Categories to suggest.
	 */
	self.categories = ko.observableArray([]);
	/**
	 * Keyboard-highlighted category.  False for none.
	 */
	self.catCursor = ko.observable(false);

	/**
	 * Transaction that is currently selected for full view.  False for none.
	 */
	self.selection = ko.observable(false);

	/**
	 * Whether the filters menu should be displayed.
	 */
	self.showFilters = ko.observable(false);
	self.showFilters.subscribe(function() {
		if(self.showFilters()) {
			self.filterAcct("");
			self.filterCat("");
			// use slice to make copies
			self.oldFilters = {accounts:  self.filterAccounts().slice(), categories: self.filterCategories().slice()};
		}
	});

	self.filterAccounts = ko.observableArray([]);
	self.filterAcct = ko.observable("");
	self.filterAcct.subscribe(function() {
		if(self.filterAcct())
			self.suggestingAccounts(true);
	});
	self.suggestingAccounts = ko.observable(false);
	self.suggestingAccounts.subscribe(function() {
		if(!self.suggestingAccounts())
			self.acctCursor(false);
	});
	self.accountsForFilter = ko.computed(function() {
		self = self || TransactionsModel;
		var accts = [];
		for(var a = 0; a < self.accounts().length; a++)
			if((!self.filterAcct() || self.accounts()[a].name.containsAnyCase(self.filterAcct())) && self.filterAccounts().indexOf(self.accounts()[a]) < 0)
				accts.push(self.accounts()[a]);
		return accts;
	});

	self.filterCategories = ko.observableArray([]);
	self.filterCat = ko.observable("");
	self.filterCat.subscribe(function() {
		if(self.filterCat())
			self.suggestingFilterCategories(true);
	});
	self.suggestingFilterCategories = ko.observable(false);
	self.suggestingFilterCategories.subscribe(function() {
		if(!self.suggestingFilterCategories())
			self.catCursor(false);
	});
	self.categoriesForFilter = ko.computed(function() {
		self = self || TransactionsModel;
		var cats = [];
		var uncat = {id: 0, name: "(uncategorized)", subs: []};
		if((!self.filterCat() || uncat.name.containsAnyCase(self.filterCat())) && self.filterCategories().indexOf(uncat) < 0)
			cats.push(uncat);
		for(var pc = 0; pc < self.categories().length; pc++) {
			var cat = self.categories()[pc];
			var subs = [];
			for(var sc = 0; sc < cat.subs.length; sc++)
				if(cat.subs[sc].name.containsAnyCase(self.filterCat()) && self.filterCategories().indexOf(cat.subs[sc]) < 0)
					subs.push(cat.subs[sc]);
			if(subs.length || cat.name.containsAnyCase(self.filterCat()) && self.filterCategories().indexOf(cat) < 0) {
				cat.subs = subs;
				cats.push(cat);
			}
		}
		return cats;
	});

	/**
	 * Get more transaction from the server.
	 */
	self.GetTransactions = function() {
		self.loading(true);
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
	 * Load categories from the server.  Also loads the first set of transactions.
	 */
	(self.GetCategories = function() {
		self.loading(true);
		$.get("?ajax=categories", null, function(result) {
			if(!result.fail) {
				self.categories(result.categories);
				if(self.accountsLoaded)
					self.GetTransactions();
				self.categoriesLoaded = true;
			} else {
				self.loading(false);
				alert(result.message);
			}
		}, "json");
	})();

	(self.GetAccounts = function() {
		$.get("accounts.php?ajax=accountlist", null, function(result) {
			if(!result.fail) {
				self.accounts(result.accounts);
				var thisAcct = FindAccountID();
				for(var a = 0; a < result.accounts.length; a++)
					if(result.accounts[a].id == thisAcct) {
						self.filterAccounts.push(result.accounts[a]);
						break;
					}
				if(self.categoriesLoaded)
					self.GetTransactions();
				self.accountsLoaded = true;
			}
			else
				alert(result.message);
		}, "json");
	})();

	/**
	 * Select the specfied transaction for full view.
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
						data.push({id: tr.id, name: tr.name().trim(), category: tr.category() ? tr.category().trim() : tr.category(), notes: tr.notes().trim()});
					}
			if(data.length)  // should be true if self.changed was true
				$.post("?ajax=save", {transactions: data}, function(result) {
					self.saving(false);
					if(!result.fail) {
						if(close)
							self.SelectNone();
						self.categories(result.categories);  // may have added categories, so reset category list.
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
	 * Show category suggestions for the specified transaction.
	 * @param transaction Transaction in full view mode.
	 */
	self.ShowSuggestions = function(transaction) {
		transaction.suggestingCategories(true);
	};
	
	self.ShowFilterCatSuggestions = function() {
		self.suggestingFilterCategories(true);
	};

	self.ShowAcctSuggestions = function() {
		self.suggestingAccounts(true);
	};

	/**
	 * Hide category suggestions for the specified transaction.
	 * @param transaction Transaction in full view mode.
	 */
	self.HideSuggestions = function(transaction) {
		window.setTimeout(function() {
			transaction.suggestingCategories(false);
		}, 100);  // need to delay this because ios (safari?) won't fire tap / click events on the suggestion items
	};

	self.HideFilterCatSuggestions = function() {
		window.setTimeout(function() {
			self.suggestingFilterCategories(false);
		}, 100);  // need to delay this because ios (safari?) won't fire tap / click events on the suggestion items
	};

	self.HideFilterAcctSuggestions = function() {
		window.setTimeout(function() {
			self.suggestingAccounts(false);
		}, 100);  // need to delay this because ios (safari?) won't fire tap / click events on the suggestion items
	};

	/**
	 * Set the category of the full-view tranaction to the specified category.
	 * @param category Category being chosen.
	 */
	self.ChooseCategory = function(category) {
		self.selection().category(category.name);
		self.selection().suggestingCategories(false);
	};
	
	self.ChooseFilterCategory = function(category) {
		self.filterCategories.push(category);
		self.suggestingFilterCategories(false);
	};

	self.ChooseAccount = function(account) {
		self.filterAccounts.push(account);
		self.suggestingAccounts(false);
	};

	self.RemoveFilterCategory = function(category) {
		self.filterCategories.splice(self.filterCategories.indexOf(category), 1);
	};

	self.RemoveAccount = function(account) {
		self.filterAccounts.splice(self.filterAccounts.indexOf(account), 1);
	};

	/**
	 * Keyboard shortcuts for category field:
	 * ESC hides suggestions
	 * Up arrow highlights the previous suggestion.  It will wrap from the top to the bottom.
	 * Down arrow highlights the next suggestion.  It will wrap from the bottom to the top.
	 * Enter selects the highlighted category.
	 * Tab selects the highlighted category and then moves to the next field.
	 */
	self.CategoryKey = function(transaction, e) {
		switch(e.keyCode) {
			case 27:  // escape
				if(transaction.suggestingCategories()) {
					transaction.suggestingCategories(false);
					e.stopImmediatePropagation();
					return false;
				}
				break;  // if it's not hiding the suggestions it should hide the full transaction view
			case 38:  // up arrow
				transaction.suggestingCategories(true);
				if(TransactionsModel.catCursor() && (TransactionsModel.catCursor().name.containsAnyCase(transaction.category()) || TransactionsModel.catCursor().subs && TransactionsModel.catCursor().subs.nameContainsAnyCase(transaction.category()))) {
					var prevcat = false;
					for(var c = 0; c < TransactionsModel.categories().length; c++)
						if(TransactionsModel.categories()[c].name.containsAnyCase(transaction.category()) || TransactionsModel.categories()[c].subs.nameContainsAnyCase(transaction.category()))
							if(TransactionsModel.catCursor() == TransactionsModel.categories()[c]) {
								if(prevcat)
									TransactionsModel.catCursor(prevcat);
								else {
									for(var cc = TransactionsModel.categories().length - 1; cc >= 0 && !TransactionsModel.categories()[cc].name.containsAnyCase(transaction.category()) && !TransactionsModel.categories()[cc].subs.nameContainsAnyCase(transaction.category()); cc--);
									if(cc > -1) {
										if(TransactionsModel.categories()[cc].subs && TransactionsModel.categories()[cc].subs.length) {
											for(var sc = TransactionsModel.categories()[cc].subs.length - 1; sc >= 0 && !TransactionsModel.categories()[cc].subs[sc].name.containsAnyCase(transaction.category()); sc--);
											if(sc > -1) {
												TransactionsModel.catCursor(TransactionsModel.categories()[cc].subs[sc]);
												return false;
											}
										}
										TransactionsModel.catCursor(TransactionsModel.categories()[cc]);
									}
								}
								return false;
							} else {
								prevcat = TransactionsModel.categories()[c];
								if(TransactionsModel.categories()[c].subs)
									for(var sc = 0; sc < TransactionsModel.categories()[c].subs.length; sc++)
										if(TransactionsModel.categories()[c].subs[sc].name.containsAnyCase(transaction.category()))
											if(TransactionsModel.catCursor() == TransactionsModel.categories()[c].subs[sc]) {
												TransactionsModel.catCursor(prevcat);
												return false;
											} else
												prevcat = TransactionsModel.categories()[c].subs[sc];
							}
				} else {
					for(var c = TransactionsModel.categories().length - 1; c >= 0 && !TransactionsModel.categories()[c].name.containsAnyCase(transaction.category()) && !TransactionsModel.categories()[c].subs.nameContainsAnyCase(transaction.category()); c--);
					if(c > -1) {
						if(TransactionsModel.categories()[c].subs && TransactionsModel.categories()[c].subs.length) {
							for(var sc = TransactionsModel.categories()[c].subs.length - 1; sc >= 0 && !TransactionsModel.categories()[c].subs[sc].name.containsAnyCase(transaction.category()); sc--);
							if(sc > -1) {
								TransactionsModel.catCursor(TransactionsModel.categories()[c].subs[sc]);
								return false;
							}
						}
						TransactionsModel.catCursor(TransactionsModel.categories()[c]);
					}
				}
				return false;
			case 40:  // down arrow
				transaction.suggestingCategories(true);
				if(TransactionsModel.catCursor() && (TransactionsModel.catCursor().name.containsAnyCase(transaction.category()) || TransactionsModel.catCursor().subs && TransactionsModel.catCursor().subs.nameContainsAnyCase(transaction.category()))) {
					var nextcat = false;
					for(var c = TransactionsModel.categories().length - 1; c >=  0; c--)
						if(TransactionsModel.categories()[c].name.containsAnyCase(transaction.category()) || TransactionsModel.categories()[c].subs.nameContainsAnyCase(transaction.category())) {
							if(TransactionsModel.categories()[c].subs)
								for(var sc = TransactionsModel.categories()[c].subs.length - 1; sc >= 0 ; sc--)
									if(TransactionsModel.categories()[c].subs[sc].name.containsAnyCase(transaction.category()))
										if(TransactionsModel.catCursor() == TransactionsModel.categories()[c].subs[sc]) {
											if(nextcat)
												TransactionsModel.catCursor(nextcat);
											else {
												for(var cc = 0; cc < TransactionsModel.categories().length && !TransactionsModel.categories()[cc].name.containsAnyCase(transaction.category()) && !TransactionsModel.categories()[cc].subs.nameContainsAnyCase(transaction.category()); cc++);
												if(cc < TransactionsModel.categories().length)
													TransactionsModel.catCursor(TransactionsModel.categories()[cc]);
											}
											return false;
										} else
											nextcat = TransactionsModel.categories()[c].subs[sc];
							if(TransactionsModel.catCursor() == TransactionsModel.categories()[c]) {
								if(nextcat)
									TransactionsModel.catCursor(nextcat);
								else {
									for(var cc = 0; cc < TransactionsModel.categories().length && !TransactionsModel.categories()[cc].name.containsAnyCase(transaction.category()) && !TransactionsModel.categories()[cc].subs.nameContainsAnyCase(transaction.category()); cc++);
									if(cc < TransactionsModel.categories().length)
										TransactionsModel.catCursor(TransactionsModel.categories()[cc]);
								}
								return false;
							} else
								nextcat = TransactionsModel.categories()[c];
						}
				} else {
					for(var cc = 0; cc < TransactionsModel.categories().length && !TransactionsModel.categories()[cc].name.containsAnyCase(transaction.category()) && !TransactionsModel.categories()[cc].subs.nameContainsAnyCase(transaction.category()); cc++);
					if(cc < TransactionsModel.categories().length)
						TransactionsModel.catCursor(TransactionsModel.categories()[cc]);
				}
				return false;
			case 13:  // enter key
				if(TransactionsModel.catCursor()) {
					transaction.category(TransactionsModel.catCursor().name);
					transaction.suggestingCategories(false);
					return false;
				}
				break;
			case 9:  // tab key
				if(TransactionsModel.catCursor()) {
					transaction.category(TransactionsModel.catCursor().name);
					transaction.suggestingCategories(false);
				}
				break;
		}
		return true;  // knockout will suppress the event unless we return true
	};
	
	self.CategoryFilterKey = function(model, e) {
		switch(e.keyCode) {
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

	self.AccountFilterKey = function(model, e) {
		switch(e.keyCode) {
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

	self.UpdateFilters = function() {
		self.showFilters(false);
		self.dates([]);
		self.GetTransactions();
	};

	self.CancelFilters = function() {
		self.showFilters(false);
		self.filterAccounts(self.oldFilters.accounts);
		self.filterCategories(self.oldFilters.categories);
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
	(transaction.category = ko.observable(transaction.category)).subscribe(function() {
		transaction.changed = true;
		TransactionsModel.changed = true;
		if(!TransactionsModel.loading())
			transaction.suggestingCategories(true);
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
	transaction.newCategory = ko.computed(function() {
		if(!transaction.category())
			return false;
		for(var c = 0; c < TransactionsModel.categories().length; c++)
			if(TransactionsModel.categories()[c].name == transaction.category())
				return false;
			else
				for(var sc = 0; sc < TransactionsModel.categories()[c].subs.length; sc++)
					if(TransactionsModel.categories()[c].subs[sc].name == transaction.category())
						return false;
		return true;
	});
	return transaction;
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
	
	return params;
}

/**
 * Look up the account ID from the querystring.
 * @returns Account ID, or 0 if none.
 */
function FindAccountID() {
	var qs = window.location.search.substring(1).split("&");
	for(var i = 0; i < qs.length; i++) {
		var p = qs[i].split("=");
		if(p[0] == "acct")
			return p[1];
	}
	return null;
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
