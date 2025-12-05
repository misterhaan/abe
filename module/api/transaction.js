import ApiBase from "./apiBase.js";

const urlbase = "api/transaction/";

export default class TransactionApi extends ApiBase {
	static List(skip, accts = null, cats = null, datestart = null, dateend = null, minamount = null, search = null) {
		const data = {};
		if(accts) data.accts = accts;
		if(cats) data.cats = cats;
		if(datestart) data.datestart = datestart;
		if(dateend) data.dateend = dateend;
		if(minamount) data.minamount = minamount;
		if(search) data.search = search;
		return super.GET(urlbase + "list/" + skip, data);
	}
	static Save(id, name, notes, categories) {
		const catnames = [];
		const catamounts = [];
		for(const c in categories) {
			catnames.push(categories[c].name);
			catamounts.push(categories[c].amount);
		}
		return super.PATCH(urlbase + "save/" + id, {
			name: name,
			notes: notes,
			catnames: catnames,
			catamounts: catamounts
		});
	}
	static ParseFile(accountId, transactionFile) {
		const data = new FormData();
		data.append("transfile", transactionFile.files[0], transactionFile.value);
		return super.POST(urlbase + "parseFile/" + accountId, data);
	}

	static Import(accountId, transactions) {
		const data = JSON.stringify(transactions);
		return super.POST(urlbase + "import/" + accountId, data);
	}
};
