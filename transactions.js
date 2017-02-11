$(function() {
	ko.applyBindings(TransactionsModel, $("#transactions")[0]);
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
				self.GetTransactions();
			} else {
				self.loading(false);
				alert(result.message);
			}
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
						data.push({id: tr.id, name: tr.name().trim(), category: tr.category().trim(), notes: tr.notes().trim()});
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

	/**
	 * Hide category suggestions for the specified transaction.
	 * @param transaction Transaction in full view mode.
	 */
	self.HideSuggestions = function(transaction) {
		window.setTimeout(function() {
			transaction.suggestingCategories(false);
		}, 10);  // need to delay this because ios (safari?) won't fire tap / click events on the suggestion items
	};

	/**
	 * Set the category of the full-view tranaction to the specified category.
	 * @param category Category being chosen.
	 */
	self.ChooseCategory = function(category) {
		self.selection().category(category.name);
		self.selection().suggestingCategories(false);
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
	if(dates.length) {
		var oldest = dates.last();
		var oldid = oldest.transactions().last();
		return {oldest: oldest.date, oldid: oldid.id, acct: FindAccountID()};
	}
	return {oldest: "9999-12-31", oldid: 0, acct: FindAccountID()};
}

/**
 * Look up the account ID from the querystring.
 * @returns Account ID, or 0 if none.
 */
function FindAccountID() {
	var qs = window.location.search.substring(1).split("&");
	for(var i = 0; i < qs.length; i++) {
		var p = qs[i].split("=");
		if(p[0] = "acct")
			return p[1];
	}
	return 0;
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
