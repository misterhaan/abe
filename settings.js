$(function() {
	ko.applyBindings(vm, $("#categories")[0]);
});

var vm = new (function() {
	var self = this;
	self.loading = ko.observable(false);
	self.categories = ko.observableArray([]);
	self.draggingCategory = ko.observable(false);
	self.editing = ko.observable(false);
	self.addCatName = ko.observable("");

	/**
	 * Load categories from the server.  Also loads the first set of transactions
	 * if accounts have been loaded.
	 */
	(self.GetCategories = function() {
		self.loading(true);
		$.get("transactions.php?ajax=categories", null, function(result) {
			if(!result.fail) {
				for(var c = 0; c < result.categories.length; c++)
					ObserveCategory(result.categories[c]);
				self.categories(result.categories);
			} else {
				self.loading(false);
				alert(result.message);
			}
		}, "json");
	})();

	self.AddCategory = function() {
		$.post("?ajax=addcategory", {name: self.addCatName()}, function(result) {
			if(!result.fail) {
				var c = {id: result.id, name: result.name, subs: []};
				ObserveCategory(c);
				self.categories.push(c);
				self.categories.sort(function(a, b) { return a.name() > b.name() ? 1 : a.name() < b.name() ? -1 : 0; });
				self.addCatName("");
			} else
				alert(result.message);
		}, "json");
	};

	self.RemoveCategory = function(category) {
		for(var c = 0; c < self.categories().length; c++)
			if(self.categories()[c] == category) {
				self.categories.splice(c, 1);
				return;
			} else
				for(var s = 0; s < self.categories()[c].subs().length; s++)
					if(self.categories()[c].subs()[s] == category) {
						self.categories()[c].subs.splice(s, 1);
						return;
					}
	};

	self.EditCategory = function(category, e) {
		self.editing(true);
		category.isEditing(true);
		e.preventDefault();
	};

	self.SaveCategoryName = function(category, e) {
		$.post("?ajax=savename", {id: category.id, name: category.name()}, function(result) {
			if(!result.fail) {
				self.editing(false);
				category.isEditing(false);
				category.name(result.name);
			}
			else
				alert(result.message);
		}, "json");
	};

	self.DragStart = function(category, e) {
		$(e.target).addClass("dragging");
		self.draggingCategory(category);
		e.originalEvent.dataTransfer.effectAllowed = "move";
		e.originalEvent.dataTransfer.setData("text/plain", category.name());
		return true;
	};

	self.DragEnd = function(category, e) {
		$(e.target).removeClass("dragging");
	};

	self.DragOver = function(category, e) {
		e.preventDefault();
		e.originalEvent.dataTransfer.dropEffect = "move";
	};

	self.DragEnter = function(category, e) {
		$(e.target).addClass("droptarget");
	};
	
	self.DragLeave = function(category, e) {
		$(e.target).removeClass("droptarget");
	};

	self.Drop = function(category, e) {
		e.stopPropagation();
		if(category != self.draggingCategory()) {
			self.RemoveCategory(self.draggingCategory());
			delete self.draggingCategory().subs;
			category.subs.push(self.draggingCategory());
			category.subs.sort(function(a, b) { return a.name() > b.name() ? 1 : a.name() < b.name() ? -1 : 0; });
			$.post("?ajax=movecat", {id: self.draggingCategory().id, parent: category.id}, function(result) {
				if(result.fail)
					alert(result.message);
			}, "json");
		}
		self.draggingCategory(false);
	};

	self.DropRoot = function(model, e) {
		e.stopPropagation();
		self.RemoveCategory(self.draggingCategory());
		if(!self.draggingCategory().subs)
			self.draggingCategory().subs = ko.observableArray([]);
		self.categories.push(self.draggingCategory());
		self.categories.sort(function(a, b) { return a.name() > b.name() ? 1 : a.name() < b.name() ? -1 : 0; });
		$.post("?ajax=movecat", {id: self.draggingCategory().id, parent: -1}, function(result) {
			if(result.fail)
				alert(result.message);
		}, "json");
		self.draggingCategory(false);
	};
})();

function ObserveCategory(category) {
	category.name = ko.observable(category.name);
	category.isEditing = ko.observable(false);
	if(category.subs) {
		category.subs = ko.observableArray(category.subs);
		for(var s = 0; s < category.subs().length; s++)
			ObserveCategory(category.subs()[s]);
	}
}
