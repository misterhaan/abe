$(function() {
  ko.applyBindings(TransactionsModel, $("#transactions")[0]);
  $(window).keydown(function(e) {
    switch(e.keyCode) {
      case 27:
        TransactionsModel.SelectNone(null, e);
        return false;
      case 38:
        TransactionsModel.SelectPrevious();
        return !TransactionsModel.selection();
      case 40:
        TransactionsModel.SelectNext();
        return !TransactionsModel.selection();
    }
  });
});

var TransactionsModel = new function() {
  var self = this;
  self.loading = ko.observable(false);
  self.editing = ko.observable(false);
  self.saving = ko.observable(false);

  self.dates = ko.observableArray([]);
  self.more = ko.observable(false);

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
  self.GetTransactions();

  self.Select = function(transaction) {
    self.selection(transaction);
  };

  self.SelectPrevious = function() {
    if(self.selection())
      for(var d = 0; d < self.dates().length; d++) {
        var t = self.dates()[d].transactions().indexOf(self.selection());
        if(t == 0)
          if(d == 0)
            return;
          else {
            self.selection(self.dates()[d - 1].transactions().last());
            return;
          }
        else if(t > 0) {
          self.selection(self.dates()[d].transactions()[t - 1]);
          return;
        }
      }
  };

  self.SelectNext = function() {
    if(self.selection())
      for(var d = 0; d < self.dates().length; d++) {
        var t = self.dates()[d].transactions().indexOf(self.selection());
        if(t == self.dates()[d].transactions().length - 1)
          if(d == self.dates().length - 1)
            return;
          else {
            self.selection(self.dates()[d + 1].transactions()[0]);
            return;
          }
        else if(t >= 0 && t < self.dates()[d].transactions().length - 1) {
          self.selection(self.dates()[d].transactions()[t + 1]);
          return;
        }
      }
  };

  self.SelectNone = function(transaction, e) {
    self.selection(false);
    e.stopImmediatePropagation();
  };

  self.Edit = function() {
    self.editing(true);
  };

  self.Save = function() {
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
    $.post("?ajax=save", {transactions: data}, function(response) {
      self.saving(false);
      self.editing(false);
      if(!response.fail)
        for(t = 0; t < transactions.length; t++)
          transactions[t].changed = false;
      else
        alert(response.message);
    }, "json");
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
  });
  (transaction.category = ko.observable(transaction.category)).subscribe(function() {
    transaction.changed = true;
  });
  (transaction.notes = ko.observable(transaction.notes)).subscribe(function() {
    transaction.changed = true;
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
