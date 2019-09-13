import ApiBase from "./apiBase.js";

const urlbase = "api/budget/";

export default class BudgetApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list", result => result.months);
	}
	static LoadActive(month) {
		return super.GETwithParams(urlbase + "active", { month: month }, result => {
			return {
				categories: result.categories,
				funds: result.funds
			};
		});
	}
	static SetActual(month, id, amount) {
		return super.POST(urlbase + "actual", {
			month: month,
			id: id,
			amount: amount
		}, () => true);
	}
	static SetActualFund(month, id, amount) {
		return super.POST(urlbase + "actualFund", {
			month: month,
			id: id,
			amount: amount
		}, () => true);
	}
	static Suggestions(month) {
		return super.GETwithParams(urlbase + "suggestions", { month: month }, result => {
			return {
				columns: result.columns,
				values: result.values
			};
		});
	}
	static Create(month, categoryIds, categoryAmounts, fundIds, fundAmounts) {
		return super.POST(urlbase + "create", {
			month: month,
			catids: categoryIds,
			catamounts: categoryAmounts,
			fundids: fundIds,
			fundamounts: fundAmounts
		}, () => true);
	}
};
