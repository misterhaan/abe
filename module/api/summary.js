import ApiBase from "./apiBase.js";

const urlbase = "api/summary/";

export default class SummaryApi extends ApiBase {
	static MonthlyCategories() {
		return super.GET(urlbase + "monthlyCategories");
	}
	static YearlyCategories() {
		return super.GET(urlbase + "yearlyCategories");
	}
};
