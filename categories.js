$(function() {
	draglevel = [];
	ko.applyBindings(vm = new VM());
});

function VM() {
	var self = this;  // self reference for functions where this is different
	this.groups = ko.observableArray([]);  // data being edited on this page.  groups contain categories.
	this.addGroupName = ko.observable("");  // name field for adding a group
	this.addCatName = ko.observable("");  // name field for adding a category
	this.selectedGroup = ko.observable(false);  // false for no selection, or a value from this.groups that is selected.
	this.isEditing = ko.observable(false);  // whether something is being edited.
	this.loading = ko.observable(false);  // whether category data is currently loading.
	this.draggingCategory = ko.observable(false);  // the category being dragged, or false if nothing is being dragged.

	/**
	 * Load all the categories by group.
	 */
	this.Load = function() {
		self.loading(true);
		$.get("api/categoryGroup/list", null, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.groups(result.groups.map(function(g) { return new GroupVM(g); }));
				if(self.groups().length)
					self.SelectGroup(self.groups()[0]);
			}
			self.loading(false);
		}, "json");
	};
	this.Load();

	/**
	 * Sort category groups by name.  This needs to be done after renaming or
	 * adding a category group.
	 */
	this.SortGroups = function() {
		self.groups.sort(function(a, b) { return a.name().toLowerCase() < b.name().toLowerCase() ? -1 : 1; });  // don't need a zero case because they can't be equal
	};

	/**
	 * Set the selected group to the specified group.
	 */
	this.SelectGroup = function(group) {
		self.selectedGroup(group);
	};

	/**
	 * Create a category group with the name specified in addGroupName.
	 */
	this.AddGroup = function() {
		$.post("api/categoryGroup/add", {name: self.addGroupName()}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.addGroupName("");
				self.groups.push(new GroupVM(result));
				self.SortGroups();
			}
		}, "json");
	};

	/**
	 * Create a category in the selected group with the name specified in addCatName.
	 */
	self.AddCategory = function() {
		var group = self.selectedGroup();
		$.post("api/category/add", {name: self.addCatName(), grp: group.id}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.addCatName("");
				group.categories.push(new CategoryVM(result));
				group.SortCategories();
			}
		}, "json");
	};

	// TODO: move to CategoryVM or draggable binding
	self.DragStart = function(category, e) {
		$(e.target).addClass("dragging");
		self.draggingCategory(category);
		e.originalEvent.dataTransfer.effectAllowed = "move";
		e.originalEvent.dataTransfer.setData("text/plain", category.name());
		return true;
	};

	// TODO: move to CategoryVM or draggable binding
	self.DragEnd = function(category, e) {
		$(e.target).removeClass("dragging");
	};

	// TODO: move to GroupVM or droppable binding
	self.DragOver = function(group, e) {
		e.preventDefault();
		e.originalEvent.dataTransfer.dropEffect = "move";
	};

	// TODO: move to GroupVM or droppable binding
	self.DragEnter = function(group, e) {
		var li = $(e.target).closest("li");
		li.data("draglevel", (li.data("draglevel") || 0) + 1);
		li.addClass("droptarget");
	};

	// TODO: move to GroupVM or droppable binding
	self.DragLeave = function(group, e) {
		var li = $(e.target).closest("li");
		var draglevel = li.data("draglevel") - 1;
		li.data("draglevel", draglevel);
		if(!draglevel)
			li.removeClass("droptarget");
	};

	// TODO: move to GroupVM and droppable binding
	self.Drop = function(group, e) {
		var li = $(e.target).closest("li");
		li.data("draglevel", 0);
		li.removeClass("droptarget");
		e.stopPropagation();
		var category = self.draggingCategory();
		$.post("api/category/move", {id: category.id, grp: group.id}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.selectedGroup().categories.remove(categery);
				group.categories.push(category);
				group.SortCategories();
			}
			self.draggingCategory(false);
		}, "json");
	};
}

function GroupVM(group) {
	var self = this;  // self reference for functions where this is different
	this.id = group.id;  // we need the id but it doesn't change
	this.name = ko.observable(group.name);
	this.categories = ko.observableArray(group.categories.map(function(c) { return new CategoryVM(c); }));
	this.isEditingName = ko.observable(false);

	// whether this group is selected
	this.isSelected = ko.pureComputed(function() {
		return vm.selectedGroup() && self.id == vm.selectedGroup().id;
	});

	// whether this group can be deleted (has an ID and doesn't have any categories)
	this.canDelete = ko.pureComputed(function() {
		return self.id && !self.categories().length;
	});

	/**
	 * Start editing the name of this group.
	 */
	this.EditName = function() {
		self.isEditingName(true);
		vm.isEditing(true);
		$("#categorygroups li input:visible").focus();
	};

	/**
	 * Save the group name after editing.
	 */
	this.SaveName = function(thisGroup, event) {
		$.post(event.target.href, {id: self.id, name: self.name()}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.isEditingName(false);
				vm.isEditing(self);
				vm.SortGroups();
			}
		}, "json");
	};

	/**
	 * Delete this group.
	 */
	this.Delete = function(thisGroup, event) {
		$.post(event.target.href, {id: self.id}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				if(self.isEditingName())
					vm.isEditing(false);
				vm.groups.remove(self);
			}
		}, "json");
	};

	/**
	 * Move the specified category into this group.
	 */
	this.DropCategory = function(category) {
		var oldGroup = vm.selectedGroup();
		$.post("api/category/move", {id: category.id, grp: self.id}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				oldGroup.categories.remove(category);
				self.categories.push(category);
				self.SortCategories();
			}
		}, "json");
	};

	/**
	 * Sort the categories in this group by name.  Use this when adding or
	 * renaming categories.
	 */
	this.SortCategories = function() {
		self.categories.sort(function(a, b) { return a.name() > b.name ? 1 : -1; });
	};
}

function CategoryVM(category) {
	var self = this;  // self reference for functions where this is different
	this.id = category.id;  // we need the id but it doesn't change
	this.name = ko.observable(category.name);
	this.isEditingName = ko.observable(false);

	/**
	 * Start editing the name of this category.
	 */
	this.EditName = function() {
		self.isEditingName(true);
		vm.isEditing(self);
		$("#categories li input:visible").focus();
	};

	/**
	 * Save the category name after editing.
	 */
	this.SaveName = function(thisCat, event) {
		var group = vm.selectedGroup();
		$.post(event.target.href, {id: self.id, name: self.name()}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				self.isEditingName(false);
				vm.isEditing(false);
				group.SortCategories();
			}
		}, "json");
	};

	/**
	 * Delete this category.
	 */
	this.Delete = function(thisCat, event) {
		var group = vm.selectedGroup();
		$.post(event.target.href, {id: self.id}, function(result) {
			if(result.fail)
				alert(result.message);
			else {
				if(self.isEditingName())
					vm.isEditing(false);
				group.categories.remove(self);
			}
		}, "json");
	};
}
