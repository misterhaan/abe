export default class ApiBase {
	// TODO:  remove successTransform parameters once api conversion finishes
	static GET(url, successTransform) {
		return this.GETwithParams(url, {}, successTransform)
	}
	static GETwithParams(url, data, successTransform) {
		return ajax("GET", url, data, successTransform)
	}
	static POST(url, data, successTransform) {
		return ajax("POST", url, data, successTransform);
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
			return handleOldRedirect(result, successTransform);
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

function ajax(method, url, data, successTransform) {
	return $.ajax({
		method: method,
		url: url,
		data: data || {},
		dataType: "json"
	}).then(result => {
		return handleOldRedirect(result, successTransform);
	}).fail(request => {
		handleError(request, url);
	});
}

function handleOldRedirect(result, successTransform) {
	// TODO:  remove entire function after api conversion finishes
	if(result.fail) {
		if(result.redirect)
			location = result.redirect;
		throw new Error(result.message);  // needs to throw on this thread so it doesn't hit the done (success) handler
	} else
		return successTransform ? successTransform(result) : result;
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
		// TODO:  remove Error case after api conversion finishes
		if(request instanceof Error)
			throw request;
		else if(request.status)
			throw new Error(request.status + " " + (request.responseText || request.statusText) + " from " + url);
	});
}
