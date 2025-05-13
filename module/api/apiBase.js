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
				throw new Error(result.message);  // needs to throw on this thread so it doesn't hit the done (success) handler
			} else
				return successTransform(result);
		}).fail(request => {
			if(request.status)
				throwAsync(request.status + " " + request.statusText + " from " + url);
			else
				throwAsync(request);
		});
	}
};

function throwAsync(message) {
	setTimeout(() => {
		if(message instanceof Error)
			throw message;
		else
			throw new Error(message);
	});
}
