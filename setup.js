$(function() {
	$("#dbsetup").submit(SaveDbSettings);
	$("#dbcreate").submit(CreateDb);
	$("a[href$='?ajax=installdb']").click(InstallDb);
	$("a[href$='?ajax=upgradedb']").click(UpgradeDb);
});

/**
 * Save the database settings form.
 */
function SaveDbSettings() {
	var form = this;
	$(form).find("button").prop("disabled", true).addClass("working");
	$.post("setup.php?ajax=savedbsetup", $(form).serialize(), function(result) {
		if(!result.fail)
			window.location.reload(true);
		else {
			$(form).find("button").prop("disabled", false).removeClass("working");
			alert(result.message);
		}
	}, "json");
	return false;
}

/**
 * Create the database and grant access for the username and password.
 */
function CreateDb() {
	var form = this;
	$(form).find("button").prop("disabled", true).addClass("working");
	$.post("setup.php?ajax=createdb", $(form).serialize(), function(result) {
		if(!result.fail)
			window.location.reload(true);
		else {
			$(form).find("button").prop("disabled", false).removeClass("working");
			alert(result.message);
		}
	}, "json");
	return false;
}

/**
 * Install the database.
 */
function InstallDb() {
	var link = this;
	$(link).addClass("working");
	$.post(link.href, {}, function(result) {
		if(!result.fail)
			window.location.reload(true);
		else {
			$(link).removeClass("working");
			alert(result.message);
		}
	}, "json");
	return false;
}

/**
 * Upgrade the database.
 */
function UpgradeDb() {
	var link = this;
	$(link).addClass("working");
	$.post(link.href, {}, function(result) {
		if(!result.fail)
			window.location.reload(true);
		else {
			$(link).removeClass("working");
			alert(result.message);
		}
	}, "json");
	return false;
}
