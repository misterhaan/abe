import ApiBase from "./apiBase.js";

const urlbase = "api/bookmark/";

export default class BookmarkApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list", result => result.bookmarks);
	}
	static Add(bookmarkUrl, name) {
		bookmarkUrl = bookmarkUrl.split("!");
		const page = bookmarkUrl.shift();
		const spec = bookmarkUrl.join("!");
		return super.POST(urlbase + "add", {
			page: page,
			spec: spec,
			name: name
		}, () => true);
	}
	static MoveDown(id) {
		return super.POST(urlbase + "moveDown", { id: id }, () => true);
	}
	static MoveUp(id) {
		return super.POST(urlbase + "moveUp", { id: id }, () => true);
	}
	static Delete(id) {
		return super.POST(urlbase + "delete", { id: id }, () => true);
	}
};
