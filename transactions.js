$(function() {
  ko.applyBindings(new TransactionsModel(), $("#transactions")[0]);
});

function TransactionsModel() {
  var self = this;
  self.loading = ko.observableArray(false);
  self.dates = ko.observableArray([]);
  self.more = ko.observableArray(false);

  self.GetTransactions = function() {
    self.loading(true);
    $.get("?ajax=get", GetParams(self.dates()), function(result) {
      if(!result.fail) {
        for(var d = 0; d < result.dates.length; d++)
          if(self.dates().length && self.dates()[self.dates().length - 1].date == result.dates[d].date)
            for(var t = 0; t < result.dates[d].transactions.length; t++)
              self.dates()[self.dates().length - 1].transactions.push(result.dates[d].transactions[t]);
          else {
            result.dates[d].transactions = ko.observableArray(result.dates[d].transactions);
            self.dates.push(result.dates[d]);
          }
        self.more(result.more);
      } else
        alert(result.message);
      self.loading(false);
    }, "json");
  };
  self.GetTransactions();
}

function GetParams(dates) {
  if(dates.length) {
    var oldest = dates[dates.length - 1];
    var oldid = oldest.transactions()[oldest.transactions().length - 1];
    return {oldest: oldest.date, oldid: oldid.id};
  }
  return {oldest: "9999-12-31", oldid: 0};
}
