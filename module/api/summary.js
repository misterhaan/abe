import "../../external/jquery-3.4.1.min.js";

const urlbase = "api/summary/";

export default {
	MonthlyCategories() {
		const url = urlbase + "monthlyCategories";
		return $.ajax({
			method: "GET",
			url: url,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return {
					cats: result.cats,
					dates: result.dates
				};
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	YearlyCategories() {
		const url = urlbase + "yearlyCategories";
		return $.ajax({
			method: "GET",
			url: url,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return {
					cats: result.cats,
					dates: result.dates
				};
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
