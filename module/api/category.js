import $ from "../../external/jquery-3.4.1.min.js";

const urlbase = "api/category/";

export default {
	Add(name, groupId) {
		const url = urlbase + "add";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				name: name,
				grp: groupId
			},
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return result.id;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Rename(id, name) {
		const url = urlbase + "rename";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				id: id,
				name: name
			},
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
	Move(id, groupId) {
		const url = urlbase + "move";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				id: id,
				grp: groupId
			},
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
			data: {
				id: id
			},
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
