export default class ApiBase {
	static GET(url, data) {
		return ajax("GET", url, data);
	}
	static POST(url, data) {
		return ajax("POST", url, data);
	}
	static POSTwithFile(url, data) {
		return $.ajax({
			method: "POST",
			url: url,
			data: data,
			cache: false,
			contentType: false,
			processData: false,
			dataType: "json"
		}).fail(request => {
			handleError(request, url);
		});
	}
	static PUT(url, data) {
		return ajax("PUT", url, data);
	}
	static PATCH(url, data) {
		return ajax("PATCH", url, data);
	}
	static DELETE(url) {
		return ajax("DELETE", url);
	}
};

function ajax(method, url, data) {
	return $.ajax({
		method: method,
		url: url,
		data: data || {},
		dataType: "json"
	}).fail(request => {
		handleError(request, url);
	});
}

function handleError(request, url) {
	if(request.status == 533) {
		const redirect = request.getResponseHeader("X-Setup-Location");
		if(redirect)
			location = redirect;
	}
	throwAsync(request, url);
}

function throwAsync(request, url) {
	setTimeout(() => {
		throw new Error(request.status + " " + (request.responseText || request.statusText) + " from " + url);
	});
}
