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
		return super.POSTwithFile(urlbase + "parseFile/" + accountId, data);
	}

	/**
	 * Maximum number of transactions allowed to be saved at a time.
	 * This value needs to give enough room for the php.ini setting max_input_vars
	 * which defaults to 1000, using the formula of number of transactions
	 * multiplied by fields per transaction (currently 10) plus 2 more for the
	 * account id and net change.
	 */
	static MaxTransactions = 80;

	static Import(accountId, transactions) {
		if(transactions.length > this.MaxTransactions)
			throw new Error(`Cannot import more than ${this.MaxTransactions} transactions at a time.  Received ${transactions.length}.`);

		return super.POST(urlbase + "import/" + accountId, {
			transactions: transactions
		});
	}
};
