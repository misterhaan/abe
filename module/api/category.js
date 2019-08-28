import ApiBase from "./apiBase.js";

const urlbase = "api/category/";

export default class CategoryApi extends ApiBase {
	static Add(name, groupId) {
		return super.POST(urlbase + "add", {
			name: name,
			grp: groupId
		}, result => result.id);
	}
	static Rename(id, name) {
		return super.POST(urlbase + "rename", {
			id: id,
			name: name
		}, () => true);
	}
	static Move(id, groupId) {
		return super.POST(urlbase + "move", {
			id: id,
			grp: groupId
		}, () => true);
	}
	static Delete(id) {
		return super.POST(urlbase + "delete", { id: id }, () => true);
	}
};
