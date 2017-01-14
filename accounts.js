$(function() {
  ko.applyBindings(new AccountListModel(), $("#accountlist")[0]);
});

function AccountListModel() {
  var self = this;

  self.accounts = ko.observableArray([]);
  $.get("?ajax=accountlist", {}, function(result) {
    if(!result.fail)
      for(var a = 0; a < result.accounts.length; a++)
        self.accounts.push(result.accounts[a]);
    else
      alert(result.message);
  }, "json");
}
