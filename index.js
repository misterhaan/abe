$(function() {
  ko.applyBindings(new TransactionsModel(), $("#last3trans")[0]);
});

function TransactionsModel() {
  var self = this;
  self.loading = ko.observableArray(false);
  self.dates = ko.observableArray([]);

  self.GetTransactions = function() {
    self.loading(true);
    $.get("transactions.php?ajax=get", {limit: 3, oldest: "9999-12-31", oldid: 0}, function(result) {
      if(!result.fail)
        self.dates(result.dates);
      else
        alert(result.message);
      self.loading(false);
    }, "json");
  };
  self.GetTransactions();
}
