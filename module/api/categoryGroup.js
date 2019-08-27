import "../../external/jquery-3.4.1.min.js";

const urlbase = "api/categoryGroup/";

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
				return result.groups;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Add(name) {
		const url = urlbase + "add";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				name: name
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
