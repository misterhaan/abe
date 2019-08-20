import $ from "../../external/jquery-3.4.1.min.js";

const urlbase = "api/transaction/";

export default {
	ParseFile(accountId, transactionFile) {
		const url = urlbase + "parseFile";
		let data = new FormData();
		data.append("acctid", accountId);
		data.append("transfile", transactionFile.files[0], transactionFile.value);
		return $.ajax({
			method: "POST",
			url: url,
			data: data,
			cache: false,
			contentType: false,
			processData: false,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return result.preview;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Import(accountId, transactions, net) {
		const url = urlbase + "import";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				acctid: accountId,
				transactions: transactions,
				net: net
			},
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return { sortable: result.newestSortable, display: result.newestDisplay };
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
