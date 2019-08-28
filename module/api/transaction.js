import ApiBase from "./apiBase.js";

const urlbase = "api/transaction/";

export default class TransactionApi extends ApiBase {
	static List(oldest = null, oldid = null, accts = null, cats = null, datestart = null, dateend = null, minamount = null, search = null) {
		const data = {};
		if(oldest) data.oldest = oldest;
		if(oldid) data.oldid = oldid;
		if(accts) data.accts = accts;
		if(cats) data.cats = cats;
		if(datestart) data.datestart = datestart;
		if(dateend) data.dateend = dateend;
		if(minamount) data.minamount = minamount;
		if(search) data.search = search;
		return super.GETwithParams(urlbase + "list", data, result => {
			return {
				dates: result.dates,
				more: result.more
			};
		});
	}
	static Save(id, name, notes, categories) {
		const catnames = [];
		const catamounts = [];
		for(let c in categories) {
			catnames.push(categories[c].name);
			catamounts.push(categories[c].amount);
		}
		return super.POST(urlbase + "save", {
			id: id,
			name: name,
			notes: notes,
			catnames: catnames,
			catamounts: catamounts
		}, () => true);
	}
	static ParseFile(accountId, transactionFile) {
		const data = new FormData();
		data.append("acctid", accountId);
		data.append("transfile", transactionFile.files[0], transactionFile.value);
		return super.POSTwithFile(urlbase + "parseFile", data, result => result.preview);
	}
	static Import(accountId, transactions, net) {
		return super.POST(urlbase + "import", {
			acctid: accountId,
			transactions: transactions,
			net: net
		}, result => {
			return {
				sortable: result.newestSortable,
				display: result.newestDisplay
			};
		});
	}
};
