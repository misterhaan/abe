import ApiBase from "./apiBase.js";

const urlbase = "api/fund/";

export default class FundApi extends ApiBase {
	static List() {
		return super.GET(urlbase + "list", result => result.funds);
	}
	static Add(name, balance, target) {
		return super.POST(urlbase + "add", {
			name: name,
			balance: balance,
			target: target
		}, result => {
			return {
				id: result.id,
				balanceDisplay: result.balanceDisplay,
				targetDisplay: result.targetDisplay
			};
		});
	}
	static Save(id, name, balance, target) {
		return super.POST(urlbase + "save", {
			id: id,
			name: name,
			balance: balance,
			target: target
		}, result => {
			return {
				balanceDisplay: result.balanceDisplay,
				targetDisplay: result.targetDisplay
			};
		});
	}
	static MoveDown(id) {
		return super.POST(urlbase + "moveDown", { id: id }, () => true);
	}
	static MoveUp(id) {
		return super.POST(urlbase + "moveUp", { id: id }, () => true);
	}
	static MoveTo(moveId, beforeId) {
		return super.POST(urlbase + "moveTo", {
			moveId: moveId,
			beforeId: beforeId
		}, () => true);
	}
	static Close(id) {
		super.POST(urlbase + "close", { id: id }, () => true);
	}
};
