import "../../external/jquery-3.4.1.min.js";

const urlbase = "api/fund/";

export default {
	List() {
		const url = urlbase + "list";
		return $.ajax({
			method: "GET",
			url: url,
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return result.funds;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Add(name, balance, target) {
		const url = urlbase + "add";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				name: name,
				balance: balance,
				target: target
			},
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return {
					id: result.id,
					balanceDisplay: result.balanceDisplay,
					targetDisplay: result.targetDisplay
				};
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Save(id, name, balance, target) {
		const url = urlbase + "save";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				id: id,
				name: name,
				balance: balance,
				target: target
			},
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return {
					balanceDisplay: result.balanceDisplay,
					targetDisplay: result.targetDisplay
				};
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	MoveDown(id) {
		const url = urlbase + "moveDown";
		return $.ajax({
			method: "POST",
			url: url,
			data: { id: id },
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return true;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	MoveUp(id) {
		const url = urlbase + "moveUp";
		return $.ajax({
			method: "POST",
			url: url,
			data: { id: id },
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return true;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	MoveTo(moveId, beforeId) {
		const url = urlbase + "moveTo";
		return $.ajax({
			method: "POST",
			url: url,
			data: {
				moveId: moveId,
				beforeId: beforeId
			},
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return true;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	},
	Close(id) {
		const url = urlbase + "close";
		return $.ajax({
			method: "POST",
			url: url,
			data: { id: id },
			dataType: "json"
		}).then(result => {
			if(result.fail)
				throw new Error(result.message);
			else
				return true;
		}, request => {
			throw new Error(request.status + " " + request.statusText + " from " + url);
		});
	}
};
