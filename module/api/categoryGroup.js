import ApiBase from "./apiBase.js";

const urlbase = "api/categoryGroup/";

export default class CategoryGroupApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list", result => result.groups);
	}
	static Add(name) {
		return super.POST(urlbase + "add", { name: name }, result => result.id);
	}
	static Rename(id, name) {
		return super.POST(urlbase + "rename", {
			id: id,
			name: name
		}, () => true);
	}
	static Delete(id) {
		return super.POST(urlbase + "delete", { id: id }, () => true);
	}
};
