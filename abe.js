$(function() {
	$("#toggleMenuPane").click(function() {
		$("#menuPane").slideToggle();
		return false;
	});
	$("#bookmarks .delete").click(DeleteBookmark);
	$("#bookmarks .down").click(MoveBookmarkDown);
	$("#bookmarks .up").click(MoveBookmarkUp);
	$("a[href='#addBookmark']").click(function() {
		$("#newBookmark").slideToggle();
		$("#bookmarkName:visible").focus();
		return false;
	});
	$("a[href='#cancelBookmark']").click(function() {
		$("#newBookmark").hide();
		return false;
	});
	$("#saveBookmark").click(SaveBookmark);
});

function DeleteBookmark() {
	var link = this;
	var bookmark = $(link).parent();
	$.post(link.href, {id: bookmark.data("id")}, function(result) {
		if(result.fail)
			alert(result.message);
		else {
			if(bookmark.prev().prop("tagName") == "HEADER")
				if(bookmark.is(":last-child")) {
					bookmark.parent().parent().remove();
					return;
				} else
					bookmark.next().children(".up").remove();
			if(bookmark.is(":last-child"))
				bookmark.prev().children(".down").remove();
			bookmark.remove();
		}
	}, "json");
	return false;
}

function MoveBookmarkDown() {
	var link = this;
	var bookmark = $(link).parent();
	$.post(link.href, {id: bookmark.data("id")}, function(result) {
		if(result.fail)
			alert(result.message);
		else {
			if(bookmark.next().is(":last-child"))
				$(link).insertAfter(bookmark.next().children(":first-child"));
			if(bookmark.prev().prop("tagName") == "HEADER")
				bookmark.next().children(".up").insertBefore(bookmark.children(".delete"));
			bookmark.insertAfter(bookmark.next());
		}
	}, "json");
	return false;
}

function MoveBookmarkUp() {
	var link = this;
	var bookmark = $(link).parent();
	$.post(link.href, {id: bookmark.data("id")}, function(result) {
		if(result.fail)
			alert(result.message);
		else {
			if(bookmark.is(":last-child"))
				bookmark.prev().children(".down").insertBefore(bookmark.children(".delete"));
			if(bookmark.prev().prev().prop("tagName") == "HEADER")
				$(link).insertAfter(bookmark.prev().children(":first-child"));
			bookmark.insertBefore(bookmark.prev());
		}
	}, "json");
	return false;
}

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
	} else
		alert("Title is required.");
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

ko.nonVmHandlers = ko.nonVmHandlers || {};
/**
 * Limit keys that can be entered into an amount field to digits, minus sign,
 * and decimal point.  Attach to KeyPress event.
 * @param vm View model object.  Not used.
 * @param e Event object used to check which key was pressed.
 * @returns {Boolean} True if key is allowed.
 */
ko.nonVmHandlers.AmountKey = function(vm, e) {
	switch(e.which) {
		case 0:  // control keys
		case 8:  // backspace (firefox only)
		case 13:  // enter
		case 45:  // -
		case 46:  // .
		case 48:  // 0
		case 49:  // 1
		case 50:  // 2
		case 51:  // 3
		case 52:  // 4
		case 53:  // 5
		case 54:  // 6
		case 55:  // 7
		case 56:  // 8
		case 57:  // 9
			return true;
	}
	return false;
}

ko.bindingHandlers.slideVisible = {
	init: function(element, valueAccessor) {
	},
	update: function(element, valueAccessor) {
		ko.unwrap(valueAccessor()) ? $(element).slideDown() : $(element).slideUp();
	}
};

ko.abe = ko.abe || {};
ko.bindingHandlers.draggable = {
	init: function(element, valueAccessor) {
		var data = valueAccessor();
		$(element).attr("draggable", true);
		$(element).on("dragstart", function(event) {
			$(element).addClass("dragging");
			ko.abe.dragdata = ko.unwrap(data.data);
			event.originalEvent.dataTransfer.effectAllowed = ko.unwrap(data.effect) || "move";
			event.originalEvent.dataTransfer.setData("text/plain", ko.unwrap(data.name) || "");
		});
		$(element).on("dragend", function() {
			$(element).removeClass("dragging");
			delete ko.abe.dragdata;
		});
	},
	update: function(element, valueAccessor) {
	}
};
ko.bindingHandlers.droppable = {
	init: function(element, valueAccessor) {
		var data = valueAccessor();
		element.draglevel = 0;
		$(element).on("dragover", function(event) {
			event.preventDefault();
			event.originalEvent.dataTransfer.dropEffect = ko.unwrap(data.effect) || "move";
		});
		$(element).on("dragenter", function() {
			element.draglevel++;
			$(element).addClass("droptarget");
		});
		$(element).on("dragleave", function() {
			if(!--element.draglevel)
				$(element).removeClass("droptarget");
		});
		$(element).on("drop", function(event) {
			element.draglevel = 0;
			$(element).removeClass("droptarget");
			event.stopPropagation();
			data.drop(ko.abe.dragdata);
			delete ko.abe.dragdata;
		});
	},
	update: function(element, valueAccessor) {
	}
};
