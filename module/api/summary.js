import ApiBase from "./apiBase.js";

const urlbase = "api/summary/";

export default class SummaryApi extends ApiBase {
	static MonthlyCategories() {
		return super.GET(urlbase + "monthlyCategories", result => {
			return {
				cats: result.cats,
				dates: result.dates
			};
		});
	}
	static YearlyCategories() {
		return super.GET(urlbase + "yearlyCategories", result => {
			return {
				cats: result.cats,
				dates: result.dates
			};
		});
	}
};
