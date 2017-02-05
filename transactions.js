$(function() {
  ko.applyBindings(TransactionsModel, $("#transactions")[0]);
  $(window).keydown(function(e) {
    switch(e.keyCode) {
      case 27:  // esc
        TransactionsModel.SelectNone(null, e);
        return false;
      case 33:  // page up
        TransactionsModel.SelectPrevious();
        return !TransactionsModel.selection();
      case 34:  // page down
        TransactionsModel.SelectNext();
        return !TransactionsModel.selection();
    }
  });
});

$(window).on("beforeunload", function() {
  if(TransactionsModel.changed)
    TransactionsModel.Save();
});

var TransactionsModel = new function() {
  var self = this;
  self.loading = ko.observable(false);
  self.saving = ko.observable(false);
  self.changed = false;

  self.dates = ko.observableArray([]);
  self.more = ko.observable(false);

  self.categoriesLoaded = false;
  self.categories = ko.observableArray([]);
  self.catCursor = ko.observable(false);

  self.selection = ko.observable(false);

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
  (self.GetCategories = function() {
    self.loading(true);
    $.get("?ajax=categories", null, function(result) {
      if(!result.fail) {
        self.categories(result.categories);
        self.categoriesLoaded = true;
        self.GetTransactions();
      } else
        alert(result.message);
    }, "json");
  })();

  self.Select = function(transaction) {
    if(self.selection())
      self.selection().suggestingCategories(false);
    self.selection(transaction);
  };

  self.SelectPrevious = function() {
    if(self.selection()) {
      self.selection().suggestingCategories(false);
      var focus = $(":focus").parent();
      focus = focus.length ? focus = focus[0].className : false;
      for(var d = 0; d < self.dates().length; d++) {
        var t = self.dates()[d].transactions().indexOf(self.selection());
        if(t == 0)
          if(d == 0)
            return;
          else {
            self.selection(self.dates()[d - 1].transactions().last());
            if(focus)
              $(".full:visible ." + focus + " input").focus();
            return;
          }
        else if(t > 0) {
          self.selection(self.dates()[d].transactions()[t - 1]);
          if(focus)
            $(".full:visible ." + focus + " input").focus();
          return;
        }
      }
    }
  };

  self.SelectNext = function() {
    if(self.selection()) {
      self.selection().suggestingCategories(false);
      var focus = $(":focus").parent();
      focus = focus.length ? focus = focus[0].className : false;
      for(var d = 0; d < self.dates().length; d++) {
        var t = self.dates()[d].transactions().indexOf(self.selection());
        if(t == self.dates()[d].transactions().length - 1)
          if(d == self.dates().length - 1)
            return;
          else {
            self.selection(self.dates()[d + 1].transactions()[0]);
            if(focus)
              $(".full:visible ." + focus + " input").focus();
            return;
          }
        else if(t >= 0 && t < self.dates()[d].transactions().length - 1) {
          self.selection(self.dates()[d].transactions()[t + 1]);
          if(focus)
            $(".full:visible ." + focus + " input").focus();
          return;
        }
      }
    }
  };

  self.SelectNone = function(transaction, e) {
    if(self.selection())
      self.selection().suggestingCategories(false);
    self.selection(false);
    if(e)
      e.stopImmediatePropagation();
  };

  self.CloseAndSave = function() {
    self.Save(true);
  };
  self.Save = function(close) {
    if(self.changed) {
      self.saving(true);
      var transactions = [];
      var data = [];
      for(var d = 0; d < self.dates().length; d++)
        for(var t = 0; t < self.dates()[d].transactions().length; t++)
          if(self.dates()[d].transactions()[t].changed) {
            var tr = self.dates()[d].transactions()[t];
            transactions.push(tr);
            data.push({id: tr.id, name: tr.name(), category: tr.category(), notes: tr.notes()});
          }
      if(data.length)
        $.post("?ajax=save", {transactions: data}, function(result) {
          self.saving(false);
          if(!result.fail) {
            self.SelectNone();
            self.categories(result.categories);
            for(t = 0; t < transactions.length; t++)
              transactions[t].changed = false;
            self.changed = false;
          } else
            alert(result.message);
        }, "json");
      else {
        self.saving(false);
        if(close)
          self.SelectNone();
      }
    } else if(close) {
      self.SelectNone();
    }
  };

  self.ShowSuggestions = function(transaction) {
    transaction.suggestingCategories(true);
  };

  self.HideSuggestions = function(transaction) {
    window.setTimeout(function() {
      transaction.suggestingCategories(false);
    }, 10);  // need to delay this because ios (safari?) won't fire tap / click events on the suggestion items
  };

  self.ChooseCategory = function(category) {
    self.selection().category(category.name);
    self.selection().suggestingCategories(false);
  };

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
    return true;  // keep the event
  };

  self.CaptureClick = function(transaction, e) {
    e.stopImmediatePropagation();
  };
};

function ObserveDate(date) {
  for(var t = 0; t < date.transactions.length; t++)
    date.transactions[t] = ObserveTransaction(date.transactions[t]);
  date.transactions = ko.observableArray(date.transactions);
  return date;
}
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

function GetParams(dates) {
  if(dates.length) {
    var oldest = dates[dates.length - 1];
    var oldid = oldest.transactions()[oldest.transactions().length - 1];
    return {oldest: oldest.date, oldid: oldid.id, acct: FindAccountID()};
  }
  return {oldest: "9999-12-31", oldid: 0, acct: FindAccountID()};
}

function FindAccountID() {
  var qs = window.location.search.substring(1).split("&");
  for(var i = 0; i < qs.length; i++) {
    var p = qs[i].split("=");
    if(p[0] = "acct")
      return p[1];
  }
  return 0;
}

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

Array.prototype.last = function() {
  return this[this.length - 1];
};

String.prototype.containsAnyCase = function(needle) {
  return !needle || this.toLowerCase().indexOf(needle.toLowerCase()) >= 0;
};

Array.prototype.nameContainsAnyCase = function(needle) {
  for(var c = 0; c < this.length; c++)
    if(!needle || this[c].name.containsAnyCase(needle))
      return true;
  return false;
};