import ApiBase from "./apiBase.js";

const urlbase = "api/category/";

export default class CategoryApi extends ApiBase {
	static Add(name, groupId) {
		return super.POST(urlbase + "add", {
			name: name,
			grp: groupId
		});
	}
	static Rename(id, name) {
		return super.PATCH(urlbase + "name/" + id, name);
	}
	static Move(id, groupId) {
		return super.PATCH(urlbase + "group/" + id, groupId.toString());
	}
	static Delete(id) {
		return super.DELETE(urlbase + "delete/" + id);
	}
};
