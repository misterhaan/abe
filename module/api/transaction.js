import "../../external/jquery-3.4.1.min.js";

const urlbase = "api/transaction/";

export default {
	List(oldest = null, oldid = null, accts = null, cats = null, datestart = null, dateend = null, minamount = null, search = null) {
		const url = urlbase + "list";
		let data = {};
		if(oldest) data.oldest = oldest;
		if(oldid) data.oldid = oldid;
		if(accts) data.accts = accts;
		if(cats) data.cats = cats;
		if(datestart) data.datestart = datestart;
		if(dateend) data.dateend = dateend;
		if(minamount) data.minamount = minamount;
		if(search) data.search = search;
		return $.ajax({
			method: "GET",
			url: url,
			data: data,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return {
					dates: result.dates,
					more: result.more
				};
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Save(id, name, notes, categories) {
		const url = urlbase + "save";
		let catnames = [];
		let catamounts = [];
		for(let c in categories) {
			catnames.push(categories[c].name);
			catamounts.push(categories[c].amount);
		}
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				id: id,
				name: name,
				notes: notes,
				catnames: catnames,
				catamounts: catamounts
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
	ParseFile(accountId, transactionFile) {
		const url = urlbase + "parseFile";
		let data = new FormData();
		data.append("acctid", accountId);
		data.append("transfile", transactionFile.files[0], transactionFile.value);
		return $.ajax({
			method: "POST",
			url: url,
			data: data,
			cache: false,
			contentType: false,
			processData: false,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return result.preview;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Import(accountId, transactions, net) {
		const url = urlbase + "import";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				acctid: accountId,
				transactions: transactions,
				net: net
			},
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return { sortable: result.newestSortable, display: result.newestDisplay };
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
