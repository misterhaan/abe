import "../../external/jquery-3.4.1.min.js";
import GroupApi from "../api/categoryGroup.js";
import CategoryApi from "../api/category.js";
import ReportErrors from "../reportErrors.js";
import DragDrop from "../dragDrop.js";

const Unsaved = -1;

// TODO:  support touch-based drag-and-drop and alternate way to change group
export default {
	data() {
		return {
			groups: [],
			selected: false,
			editing: false,
			working: false,
			loading: true
		};
	},
	created() {
		GroupApi.List().done(groups => {
			this.groups = groups;
			if(this.groups.length)
				this.selected = this.groups[0];
			else
				this.AddGroup();
		}).fail(this.Error).always(() => {
			this.loading = false;
		});
		this.$emit("add-action", {
			action: this.AddGroup,
			url: "#settings/categories!addGroup",
			class: "addAlt",
			text: "+",
			tooltip: "Add a new category group"
		});
		this.$emit("add-action", {
			action: this.AddCategory,
			url: "#settings/categories!addCategory",
			class: "add",
			text: "+",
			tooltip: "Add a new category"
		});
	},
	mixins: [ReportErrors],
	methods: {
		Select(group, event = false) {
			if(!this.editing && (!event || event.originalTarget.nodeName != "A"))
				if(this.selected == group)
					this.Edit(group);
				else
					this.selected = group;
		},
		Edit(item, event = false) {
			if(item.id && (!event || event.originalTarget.nodeName != "A")) {
				this.editing = item;
				setTimeout(() => $("input.name").focus());
			}
		},
		AddGroup() {
			if(!this.editing && !this.working) {
				let newGroup = false;
				for(let g in this.groups)
					if(this.groups[g].id == Unsaved) {
						newGroup = this.groups[g];
						break;
					}
				if(!newGroup) {
					newGroup = {
						id: Unsaved,
						name: "",
						categories: []
					};
					this.groups.push(newGroup);
				}
				this.selected = newGroup;
				this.Edit(newGroup);
			}
		},
		SaveGroup() {
			if(this.editing && this.selected == this.editing)
				if(this.editing.name = this.editing.name.trim()) {
					this.working = this.editing;
					this.editing = false;
					if(this.working.id == Unsaved)
						GroupApi.Add(this.working.name).done(id => {
							this.working.id = id;
							this.groups.sort(CompareGroup);
						}).fail(this.Error).always(() => {
							this.working = false;
						});
					else
						GroupApi.Rename(this.working.id, this.working.name).done(() => {
							this.groups.sort(CompareGroup);
						}).fail(this.Error).always(() => {
							this.working = false;
						});
				} else {
					alert("Category groups must have a name.");
					$("input.name").focus();
				}
		},
		DeleteGroup() {
			if(this.editing && this.selected == this.editing && !this.editing.categories.length) {
				this.working = this.editing;
				this.editing = false;
				const index = this.groups.indexOf(this.working);
				if(index)
					this.selected = this.groups[index - 1];
				if(this.working.id == Unsaved) {
					this.groups.splice(index, 1);
					this.working = false;
				} else
					GroupApi.Delete(this.working.id).done(() => {
						this.groups.splice(index, 1);
					}).fail(this.Error).always(() => {
						this.working = false;
					});
			}
		},
		MoveCategory(category, group) {
			if(category != this.selected && !this.editing && !this.working) {
				const currentGroup = this.selected;
				this.working = category;
				CategoryApi.Move(category.id, group.id).done(() => {
					currentGroup.categories.splice(currentGroup.categories.indexOf(category), 1);
					group.categories.push(category);
					group.categories.sort(CompareCategory);
				}).fail(this.Error).always(() => {
					this.working = false;
				});
			}
		},
		AddCategory() {
			if(!this.editing && !this.working && this.selected) {
				let newCategory = false;
				for(let c in this.selected.categories)
					if(this.selected.categories[c].id == Unsaved) {
						newCategory = this.selected.categories[c];
						break;
					}
				if(!newCategory) {
					newCategory = {
						id: Unsaved,
						name: ""
					};
					this.selected.categories.push(newCategory);
				}
				this.Edit(newCategory);
			}
		},
		SaveCategory() {
			if(this.editing)
				if(this.editing.name = this.editing.name.trim()) {
					const group = this.selected;
					this.working = this.editing;
					this.editing = false;
					if(this.working.id == Unsaved)
						CategoryApi.Add(this.working.name, group.id).done(id => {
							this.working.id = id;
							group.categories.sort(CompareCategory);
						}).fail(this.Error).always(() => {
							this.working = false;
						});
					else
						CategoryApi.Rename(this.working.id, this.working.name).done(() => {
							group.categories.sort(CompareCategory);
						}).fail(this.Error).always(() => {
							this.working = false;
						});
				} else {
					alert("Categories must have a name.");
					$("input.name").focus();
				}
		},
		DeleteCategory() {
			if(this.editing) {
				const group = this.selected;
				this.working = this.editing;
				this.editing = false;
				CategoryApi.Delete(this.working.id).done(() => {
					group.categories.splice(group.categories.indexOf(this.working), 1);
				}).fail(this.Error).always(() => {
					this.working = false;
				});
			}
		}
	},
	directives: {
		draggable: DragDrop.Draggable,
		droptarget: DragDrop.DropTarget
	},
	template: /*html*/ `
		<section id=categories>
			<p class=loading v-if=loading>Loading categories...</p>
			<ol id=categorygroups v-if=!loading>
				<li v-for="group in groups" :class="{selected: group == selected, working: group == working}" v-droptarget="{data: group, onDrop: MoveCategory}" @click.prevent="Select(group, $event)">
					<template v-if="group != editing">
						<span class=name>{{group.name}}</span>
						<span class=actionspacer></span>
					</template>
					<template v-if="group == editing">
						<input class=name v-model=group.name maxlength=24 placeholder="Group name" required @keyup.enter=SaveGroup>
						<a class=save title="Save changes to this group’s name" @click.prevent=SaveGroup><span>Save</span></a>
						<a class=delete title="Delete this group" @click.prevent=DeleteGroup v-if=!group.categories.length><span>Delete</span></a>
					</template>
				</li>
			</ol>
			<ol id=groupcategories v-if="!loading && selected">
				<li v-for="category in selected.categories" :class="{working: category == working}" v-draggable="{data: category, name: category.name}" @click.prevent="Edit(category, $event)">
					<template v-if="category != editing">
						{{category.name}}
					</template>
					<template v-if="category == editing">
						<input class=name v-model=category.name maxlength=24 placeholder="Category name" required @keyup.enter=SaveCategory>
						<a class=save title="Save changes to this category’s name" @click.prevent=SaveCategory><span>Save</span></a>
						<a class=delete title="Delete this category (will fail if category is in use)" @click.prevent=DeleteCategory><span>Delete</span></a>
					</template>
				</li>
			</ol>
		</section>
	`
};

function CompareGroup(a, b) {
	return !a.id
		? -1 : !b.id
			? 1 : a.name.toLowerCase().localeCompare(b.name.toLowerCase());
}

function CompareCategory(a, b) {
	return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
}
