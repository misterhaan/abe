$(function() {
	$("#bookmarks .delete").click(DeleteBookmark);
	$("#bookmarks .down").click(MoveBookmarkDown);
	$("#bookmarks .up").click(MoveBookmarkUp);
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
