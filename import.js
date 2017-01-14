$(function() {
  ko.applyBindings(new ImportModel(), $("#importtrans")[0]);
});

function ImportModel() {
  var self = this;
  self.account = ko.observable(false);
  self.accountlist = ko.observableArray([]);

  $.get("accounts.php?ajax=accountlist", {}, function(result) {
    if(!result.fail) {
      for(var a = 0; a < result.accounts.length; a++)
        self.accountlist.push(result.accounts[a]);
      self.account(FindAccountID());
    } else
      alert(result.message);
  }, "json");

  self.Import = function() {
    $("#importtrans button").prop("disabled", true).addClass("waiting");
    $.post({url: "?ajax=import", data: new FormData($("#importtrans")[0]), cache: false, contentType: false, processData: false, success: function(result) {
      $("#importtrans button").prop("disabled", false).removeClass("waiting");
      if(!result.fail)
        alert("Imported " + result.count + " transactions.");
      else
        alert(result.message);
    }, dataType: "json"});
  };
}

function FindAccountID() {
  var qs = window.location.search.substring(1).split("&");
  for(var i = 0; i < qs.length; i++) {
    var p = qs[i].split("=");
    if(p[0] = "acct")
      return p[1];
  }
  return false;
}
