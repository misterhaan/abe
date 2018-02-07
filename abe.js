$(function() {
	$("a[href='#addBookmark']").click(function() {
		$("#newBookmark").toggle();
		return false;
	});
	$("a[href='#cancelBookmark']").click(function() {
		$("#newBookmark").hide();
		return false;
	});
	$("#saveBookmark").click(SaveBookmark);
});

/**
 * Save the current view of the page as a bookmark.
 */
function SaveBookmark() {
	var title = $("#bookmarkName").val().trim();
	if(title) {
		var url = $("#bookmarkUrl");
		$("#saveBookmark").prop("disabled", true).addClass("working");
		$.post("api/bookmark/add", {name: title, page: url.data("page"), spec: url.data("spec")}, function(result) {
			if(result.fail)
				alert(result.message);
			else
				$("#newBookmark").hide();
			$("#saveBookmark").prop("disabled", false).removeClass("working");
		}, "json");
	} else {
		alert("Title is required.");
	}
	return false;
}

/**
 * Set the current spec on a bookmarkable page.
 * @param string spec Specific part of the page to bookmark, which is a query string and / or hash.
 */
function SetBookmarkSpec(spec) {
	var url = $("#bookmarkUrl");
	url.data("spec", spec);
	url.val(url.data("page") + ".php" + url.data("spec"));
}

/**
 * Parse the location hash into a parameters object if it's prefixed with #!.
 * Parameters are separated by a forward slash, and names and values are
 * separated by an equals sign.  Values may contain an equals sign but not a
 * forward slash.
 * @returns object Hash object.
 */
function ParseHash() {
	var info = {};
	var hash = window.location.hash;
	if(hash.substr(0, 2) == "#!") {
		hash = hash.substr(2).split("/");
		for(var v = 0; v < hash.length; v++) {
			var pair = hash[v].split("=");
			if(pair.length > 1)
				info[pair.shift()] = pair.join("=");
		}
	}
	return info;
}
