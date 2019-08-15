import $ from "../../external/jquery-3.4.1.min.js";

const urlbase = "api/account/";

export default {
	List() {
		const url = urlbase + "list";
		return $.ajax({
			method: "GET",
			url: url,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return result.accounts;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
