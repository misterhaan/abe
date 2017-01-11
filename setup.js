$(function() {
  $("#dbsetup").submit(SaveDbSettings);
  $("#dbcreate").submit(CreateDb);
  $("a[href$='?ajax=installdb']").click(InstallDb);
});

function SaveDbSettings(e) {
  var form = this;
  $(form).find("button").prop("disabled", true).addClass("waiting");
  $.post("setup.php?ajax=savedbsetup", $(form).serialize(), function(result) {
    if(!result.fail)
      window.location.reload(true);
    else {
      $(form).find("button").prop("disabled", false).removeClass("waiting");
      alert(result.message);
    }
  }, "json");
  return false;
}

function CreateDb() {
  var form = this;
  $(form).find("button").prop("disabled", true).addClass("waiting");
  $.post("setup.php?ajax=createdb", $(form).serialize(), function(result) {
    if(!result.fail)
      window.location.reload(true);
    else {
      $(form).find("button").prop("disabled", false).removeClass("waiting");
      alert(result.message);
    }
  }, "json");
  return false;
}

function InstallDb() {
  var link = this;
  $(link).addClass("waiting");
  $.post(link.href, {}, function(result) {
    if(!result.fail)
      window.location.reload(true);
    else {
      $(link).removeClass("waiting");
      alert(result.message);
    }
  }, "json");
  return false;
}
