import $ from "../../external/jquery-3.4.1.min.js";

const urlbase = "api/bookmark/";

export default {
	List() {
		const url = urlbase + "list";
		return $.ajax({
			method: "GET",
			url: url,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return result.bookmarks;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	MoveDown(id) {
		const url = urlbase + "moveDown";
		return $.ajax({
			method: "POST",
			url: url,
			data: { id: id },
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return true;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	MoveUp(id) {
		const url = urlbase + "moveUp";
		return $.ajax({
			method: "POST",
			url: url,
			data: { id: id },
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return true;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Delete(id) {
		const url = urlbase + "delete";
		return $.ajax({
			method: "POST",
			url: url,
			data: { id: id },
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return true;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
