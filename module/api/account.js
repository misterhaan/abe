import $ from "../../external/jquery-3.4.1.min.js";

const urlbase = "api/account/";

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
				return result.accounts;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Types() {
		const url = urlbase + "types";
		return $.ajax({
			method: "GET",
			url: url,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return {
					banks: result.banks,
					types: result.types
				};
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Add(name, type, bank, balance, closed) {
		const url = urlbase + "add";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				name: name,
				type: type,
				bank: bank,
				balance: balance,
				closed: closed ? 1 : 0
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
	Save(id, name, type, bank, balance, closed) {
		const url = urlbase + "save";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				id: id,
				name: name,
				type: type,
				bank: bank,
				balance: balance,
				closed: closed ? 1 : 0
			},
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return id;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
