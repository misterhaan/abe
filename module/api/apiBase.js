export default class ApiBase {
	static GET(url, successTransform) {
		return this.GETwithParams(url, {}, successTransform)
	}
	static GETwithParams(url, data, successTransform) {
		return this.Ajax("GET", url, data, successTransform)
	}
	static POST(url, data, successTransform) {
		return this.Ajax("POST", url, data, successTransform);
	}
	static POSTwithFile(url, data, successTransform) {
		return $.ajax({
			method: "POST",
			url: url,
			data: data,
			cache: false,
			contentType: false,
			processData: false,
			dataType: "json"
		}).then(result => {
			if(result.fail) {
				if(result.redirect)
					location = result.redirect;
				throw new Error(result.message);
			} else
				return successTransform(result);
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
	static Ajax(method, url, data, successTransform) {
		return $.ajax({
			method: method,
			url: url,
			data: data,
			dataType: "json"
		}).then(result => {
			if(result.fail) {
				if(result.redirect)
					location = result.redirect;
				throw new Error(result.message);
			} else
				return successTransform(result);
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
