$(function() {
  ko.applyBindings(new AccountModel(), $("main")[0]);
});

function AccountModel() {
  var self = this;
  self.id = FindID();
  self.bank = ko.observable(false);
  self.name = ko.observable('');
  self.balance = ko.observable(0.00);
  self.balanceFormatted = ko.pureComputed({
    read: function() { return self.balance().toFixed(2); },
    write: function(value) { self.balance(parseFloat(value)); }
  });
  self.closed = ko.observable(false);

  self.banklist = ko.observableArray([]);

  $.get("?ajax=banklist", {}, function(result) {
    if(result.fail)
      alert(result.message);
    else {
      self.banklist(result.banks);
      if(self.id) {
        $.get("?ajax=get&id=" + self.id, {}, function(result) {
          if(result.fail)
            alert(result.message);
          else {
            self.bank(result.bank);
            self.name(result.name);
            self.balance(+result.balance);
            self.closed(+result.closed);
          }
        }, "json");
      }
    }
  }, "json");

  self.Save = function() {
    $("#editaccount button").prop("disabled", true).addClass("working");
    $.post("?ajax=save", {id: self.id, bank: self.bank(), name: self.name(), balance: self.balance(), closed: self.closed() ? 1 : 0}, function(result) {
      if(result.fail) {
        $("#editaccount button").prop("disabled", false).removeClass("working");
        alert(result.message);
      } else
        window.location.href = "accounts.php";
    }, "json");
  };
}

function FindID() {
  var qs = window.location.search.substring(1).split("&");
  for(var i = 0; i < qs.length; i++) {
    var p = qs[i].split("=");
    if(p[0] = "id")
      return p[1];
  }
  return false;
}
