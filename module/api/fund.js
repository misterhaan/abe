import ApiBase from "./apiBase.js";

const urlbase = "api/fund/";

export default class FundApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list");
	}
	static Add(name, balance, target) {
		return super.POST(urlbase + "add", {
			name: name,
			balance: balance,
			target: target
		});
	}
	static Save(id, name, balance, target) {
		return super.PUT(urlbase + "save/" + id, {
			name: name,
			balance: balance,
			target: target
		});
	}
	static MoveDown(id) {
		return super.POST(urlbase + "moveDown/" + id);
	}
	static MoveUp(id) {
		return super.POST(urlbase + "moveUp/" + id);
	}
	static MoveTo(moveId, beforeId) {
		return super.POST(urlbase + "moveTo/" + moveId + "/" + beforeId);
	}
	static Close(id) {
		return super.PATCH(urlbase + "close/" + id);
	}
};
