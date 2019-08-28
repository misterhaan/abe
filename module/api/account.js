import ApiBase from "./apiBase.js";

const urlbase = "api/account/";

export default class AccountApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list", result => result.accounts);
	}
	static Types() {
		return super.GET(urlbase + "types", result => {
			return {
				banks: result.banks,
				types: result.types
			};
		});
	}
	static Add(name, type, bank, balance, closed) {
		return super.POST(urlbase + "add", {
			name: name,
			type: type,
			bank: bank,
			balance: balance,
			closed: closed ? 1 : 0
		}, result => result.id);
	}
	static Save(id, name, type, bank, balance, closed) {
		return super.POST(urlbase + "save", {
			id: id,
			name: name,
			type: type,
			bank: bank,
			balance: balance,
			closed: closed ? 1 : 0
		}, () => id);
	}
};
