import CategoryGroupApi from "../api/categoryGroup.js";
import CategoryApi from "../api/category.js";
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
		CategoryGroupApi.List().done(groups => {
			this.groups = groups;
			if(this.groups.length)
				this.selected = this.groups[0];
			else
				this.AddGroup();
		}).always(() => {
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
	methods: {
		Select(group) {
			if(this.selected == group)
				this.Edit(group);
			else {
				if(this.editing)
					if(this.editing == this.selected)
						this.SaveGroup();
					else
						this.SaveCategory();
				this.selected = group;
			}
		},
		Edit(item) {
			if(item.ID) {
				if(this.editing && this.editing != item)
					if(this.editing == this.selected)
						this.SaveGroup();
					else
						this.SaveCategory();
				this.editing = item;
				setTimeout(() => $("input.name").focus());
			}
		},
		AddGroup() {
			if(!this.editing && !this.working) {
				let newGroup = false;
				for(let g in this.groups)
					if(this.groups[g].ID == Unsaved) {
						newGroup = this.groups[g];
						break;
					}
				if(!newGroup) {
					newGroup = {
						ID: Unsaved,
						Name: "",
						Categories: []
					};
					this.groups.push(newGroup);
				}
				this.selected = newGroup;
				this.Edit(newGroup);
			}
		},
		SaveGroup() {
			if(this.editing && this.selected == this.editing)
				if(this.editing.Name = this.editing.Name.trim()) {
					this.working = this.editing;
					this.editing = false;
					if(this.working.ID == Unsaved)
						CategoryGroupApi.Add(this.working.Name).done(id => {
							this.working.ID = id;
							this.groups.sort(CompareGroup);
						}).always(() => {
							this.working = false;
						});
					else
						CategoryGroupApi.Rename(this.working.ID, this.working.Name).done(() => {
							this.groups.sort(CompareGroup);
						}).always(() => {
							this.working = false;
						});
				} else {
					alert("Category groups must have a name.");
					$("input.name").focus();
				}
		},
		DeleteGroup() {
			if(this.editing && this.selected == this.editing && !this.editing.Categories.length) {
				this.working = this.editing;
				this.editing = false;
				const index = this.groups.indexOf(this.working);
				if(index)
					this.selected = this.groups[index - 1];
				if(this.working.ID == Unsaved) {
					this.groups.splice(index, 1);
					this.working = false;
				} else
					CategoryGroupApi.Delete(this.working.ID).done(() => {
						this.groups.splice(index, 1);
					}).always(() => {
						this.working = false;
					});
			}
		},
		MoveCategory(category, group) {
			if(category != this.selected && !this.editing && !this.working) {
				const currentGroup = this.selected;
				this.working = category;
				CategoryApi.Move(category.ID, group.ID).done(() => {
					currentGroup.Categories.splice(currentGroup.Categories.indexOf(category), 1);
					group.Categories.push(category);
					group.Categories.sort(CompareCategory);
				}).always(() => {
					this.working = false;
				});
			}
		},
		AddCategory() {
			if(!this.editing && !this.working && this.selected) {
				let newCategory = false;
				for(let c in this.selected.Categories)
					if(this.selected.Categories[c].id == Unsaved) {
						newCategory = this.selected.Categories[c];
						break;
					}
				if(!newCategory) {
					newCategory = {
						ID: Unsaved,
						Name: ""
					};
					this.selected.Categories.push(newCategory);
				}
				this.Edit(newCategory);
			}
		},
		SaveCategory() {
			if(this.editing)
				if(this.editing.Name = this.editing.Name.trim()) {
					const group = this.selected;
					this.working = this.editing;
					this.editing = false;
					if(this.working.ID == Unsaved)
						CategoryApi.Add(this.working.Name, group.ID).done(id => {
							this.working.ID = id;
							group.Categories.sort(CompareCategory);
						}).always(() => {
							this.working = false;
						});
					else
						CategoryApi.Rename(this.working.ID, this.working.Name).done(() => {
							group.Categories.sort(CompareCategory);
						}).always(() => {
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
				CategoryApi.Delete(this.working.ID).done(() => {
					group.Categories.splice(group.Categories.indexOf(this.working), 1);
				}).always(() => {
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
				<li v-for="group in groups" :class="{selected: group == selected, working: group == working}" v-droptarget="{data: group, onDrop: MoveCategory}" @click.prevent="Select(group)">
					<template v-if="group != editing">
						<span class=name>{{group.Name}}</span>
						<span class=actionspacer></span>
					</template>
					<template v-if="group == editing">
						<input class=name v-model.trim=group.Name maxlength=24 placeholder="Group name" required @keyup.enter=SaveGroup>
						<a class=save title="Save changes to this group’s name" @click.prevent.stop=SaveGroup><span>Save</span></a>
						<a class=delete title="Delete this group" @click.prevent.stop=DeleteGroup v-if=!group.Categories.length><span>Delete</span></a>
					</template>
				</li>
			</ol>
			<ol id=groupcategories v-if="!loading && selected">
				<li v-for="category in selected.Categories" :class="{working: category == working}" v-draggable="{disabled: editing || working, data: category, name: category.Name}" @click.prevent="Edit(category)">
					<template v-if="category != editing">
						{{category.Name}}
					</template>
					<template v-if="category == editing">
						<input class=name v-model.trim=category.Name maxlength=24 placeholder="Category name" required @keyup.enter=SaveCategory>
						<a class=save title="Save changes to this category’s name" @click.prevent.stop=SaveCategory><span>Save</span></a>
						<a class=delete title="Delete this category (will fail if category is in use)" @click.prevent.stop=DeleteCategory><span>Delete</span></a>
					</template>
				</li>
			</ol>
		</section>
	`
};

function CompareGroup(a, b) {
	return !a.ID
		? -1 : !b.ID
			? 1 : a.Name.toLowerCase().localeCompare(b.Name.toLowerCase());
}

function CompareCategory(a, b) {
	return a.Name.toLowerCase().localeCompare(b.Name.toLowerCase());
}
