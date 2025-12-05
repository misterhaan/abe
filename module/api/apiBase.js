export default class ApiBase {
	static GET(url, data) {
		if(data)
			url += "?" + new URLSearchParams(data);
		return ajax("GET", url);
	}
	static POST(url, data) {
		return ajax("POST", url, data);
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

async function ajax(method, url, data) {
	const init = { method: method };
	if(data)
		if(typeof data == "string" || data instanceof FormData || data instanceof URLSearchParams)
			init.body = data;
		else
			init.body = new URLSearchParams(data);
	const response = await fetch(url, init);
	if(!response.ok)
		handleError(response);
	return await response.json();
}

function handleError(response) {
	if(response.status == 533) {
		const redirect = response.headers.get("X-Setup-Location");
		if(redirect)
			location = redirect;
	}
	throwAsync(response);
}

async function throwAsync(response) {
	const responseText = await response.text();
	setTimeout(() => {
		throw new Error(response.status + " " + (responseText || response.statusText) + " from " + response.url);
	});
}
