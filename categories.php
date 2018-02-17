<?php
require_once __DIR__ . '/etc/class/abe.php';

$html = new abeHtml();
$html->Open('Settings');
// TODO:  support touch-based drag-and-drop and alternate way to change parent
// TODO:  support deleting categories
?>
			<h1>Settings</h1>

			<div class=tabbed>
				<nav class=tabs>
					<a class=accounts href="accounts.php">Accounts</a>
					<span class=categories>Categories</span>
				</nav>
				<section id=categorygroups>
					<ol data-bind="foreach: groups">
						<li data-bind="css: {selected: isSelected}, click: $root.SelectGroup, droppable: {drop: DropCategory}, eventoff: {dragover: $root.DragOver, dragenter: $root.DragEnter, dragleave: $root.DragLeave, drop: $root.Drop}">
							<!-- ko ifnot: isEditingName -->
								<span data-bind="text: name"></span>
								<a class=edit href="#edit" data-bind="visible: !$root.isEditing(), click: EditName" title="Rename this category group"><span>edit</span></a>
							<!-- /ko -->
							<!-- ko if: isEditingName -->
								<input data-bind="value: name" maxlength=24>
								<a class=save href="api/categoryGroup/rename" data-bind="click: SaveName"><span>save</span></a>
							<!-- /ko -->
							<!-- ko if: canDelete -->
								<a class=delete href="api/categoryGroup/delete" data-bind="click: Delete"><span>delete</span></a>
							<!-- /ko -->
						</li>
					</ol>
					<div class=add>
						<input data-bind="textInput: addGroupName" placeholder="New name">
						<button class=add title="Add a new category group with the specified name" data-bind="enable: addGroupName().trim(), click: AddGroup"><span>Add</span></button>
					</div>
				</section>
				<section id=categories data-bind="with: selectedGroup">
					<ol data-bind="foreach: categories">
						<li data-bind="draggable: {data: $data, name: name}, eventoff: {dragstart: $root.DragStart, dragend: $root.DragEnd}">
							<!-- ko ifnot: isEditingName -->
								<span data-bind="text: name"></span>
								<a class=edit href="#edit" data-bind="visible: !$root.isEditing(), click: EditName" title="Rename this category"><span>edit</span></a>
							<!-- /ko -->
							<!-- ko if: isEditingName -->
								<input data-bind="value: name" maxlength=24>
								<a class=save href="api/category/rename" data-bind="click: SaveName"><span>save</span></a>
							<!-- /ko -->
							<a class=delete href="api/category/delete" data-bind="click: Delete"><span>delete</span></a>
						</li>
					</ol>
					<div class=add>
						<input data-bind="textInput: $root.addCatName" placeholder="New name">
						<button class=add title="Add a new category with the specified name" data-bind="enable: $root.addCatName().trim(), click: $root.AddCategory"><span>Add</span></button>
					</div>
				</section>
			</div>
<?php
$html->Close();
