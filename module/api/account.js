import ApiBase from "./apiBase.js";

const urlbase = "api/account/";

export default class AccountApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list");
	}
	static Types() {
		return super.GET(urlbase + "types");
	}
	static Add(name, type, bank, balance, closed) {
		return super.POST(urlbase + "add", {
			name: name,
			type: type,
			bank: bank,
			balance: balance,
			closed: closed
		});
	}
	static Save(id, name, type, bank, balance, closed) {
		return super.PUT(urlbase + "save/" + id, {
			name: name,
			type: type,
			bank: bank,
			balance: balance,
			closed: closed
		});
	}
};
