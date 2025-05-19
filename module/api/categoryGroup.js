import ApiBase from "./apiBase.js";

const urlbase = "api/categoryGroup/";

export default class CategoryGroupApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list");
	}
	static Add(name) {
		return super.POST(urlbase + "add", { name: name });
	}
	static Rename(id, name) {
		return super.PATCH(urlbase + "name/" + id, name);
	}
	static Delete(id) {
		return super.DELETE(urlbase + "delete/" + id);
	}
};
