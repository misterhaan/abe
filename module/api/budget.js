import ApiBase from "./apiBase.js";

const urlbase = "api/budget/";

export default class BudgetApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list");
	}
	static LoadActive(month) {
		return super.GET(urlbase + "active/" + month);
	}
	static Suggestions(month) {
		return super.GET(urlbase + "suggestions/" + month);
	}
	static Create(month, categoryIds, categoryAmounts, fundIds, fundAmounts) {
		return super.PUT(urlbase + "active/" + month, {
			catids: categoryIds,
			catamounts: categoryAmounts,
			fundids: fundIds,
			fundamounts: fundAmounts
		});
	}
	static SetActual(month, id, amount) {
		return super.POST(urlbase + "actual/" + month, {
			id: id,
			amount: amount
		});
	}
	static SetActualFund(month, id, amount) {
		return super.PUT(urlbase + "actualFund/" + month + "/" + id, amount);
	}
};
