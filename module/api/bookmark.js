import ApiBase from "./apiBase.js";

const urlbase = "api/bookmark/";

export default class BookmarkApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list");
	}
	static Add(bookmarkUrl, name) {
		bookmarkUrl = bookmarkUrl.split("!");
		const view = bookmarkUrl.shift();
		const spec = bookmarkUrl.join("!");
		return super.POST(urlbase + "add", {
			view: view,
			spec: spec,
			name: name
		});
	}
	static MoveDown(id) {
		return super.POST(urlbase + "moveDown/" + id);
	}
	static MoveUp(id) {
		return super.POST(urlbase + "moveUp/" + id);
	}
	static Delete(id) {
		return super.DELETE(urlbase + "id/" + id);
	}
};
