<?php
require_once __DIR__ . '/etc/class/abe.php';

// ajax requests come in as ?ajax=function, so run the appropriate function
if(isset($_GET['ajax'])) {
	$ajax = new abeAjax();
	switch($_GET['ajax']) {
		case 'savename':    UpdateCategoryName(); break;
		case 'movecat':     MoveCategory(); break;
		case 'addcategory': AddCategory(); break;
	}
	$ajax->Send();
	die;  // skip HTML output
}

$html = new abeHtml();
$html->Open('Settings');
// TODO:  support touch-based drag-and-drop and alternate way to change parent
// TODO:  support deleting categories
// TODO:  don't allow a category to become a parent if it's in use
?>
			<h1>Settings</h1>

			<div class=tabbed>
				<nav class=tabs>
					<a class=accounts href="accounts.php">Accounts</a>
					<span class=categories data-bind="event: {dragover: DragOver, dragenter: DragEnter, dragleave: DragLeave, drop: DropRoot}">Categories</span>
				</nav>
				<section id=categories>
					<ol data-bind="foreach: categories">
						<li>
							<span data-bind="attr: {draggable: subs().length || isEditing() ? null : true}, event: {dragstart: $root.DragStart, dragend: $root.DragEnd, dragover: $root.DragOver, dragenter: $root.DragEnter, dragleave: $root.DragLeave, drop: $root.Drop}">
								<!-- ko ifnot: isEditing -->
									<span data-bind="text: name"></span>
									<a class=edit href="#edit" data-bind="visible: !$root.editing(), click: $root.EditCategory"><span>edit</span></a>
								<!-- /ko -->
								<!-- ko if: isEditing -->
									<input data-bind="value: name">
									<a class=save href="#save" data-bind="click: $root.SaveCategoryName"><span>save</span></a>
								<!-- /ko -->
							</span>
							<!-- ko if: subs().length -->
								<ol data-bind="foreach: subs">
									<li><span draggable=true data-bind="event: {dragstart: $root.DragStart, dragend: $root.DragEnd}">
										<!-- ko ifnot: isEditing -->
											<span data-bind="text: name"></span>
											<a class=edit href="#edit" data-bind="visible: !$root.editing(), click: $root.EditCategory"><span>edit</span></a>
										<!-- /ko -->
										<!-- ko if: isEditing -->
											<input data-bind="value: name">
											<a class=save href="#save" data-bind="click: $root.SaveCategoryName"><span>save</span></a>
										<!-- /ko -->
									</span></li>
								</ol>
							<!-- /ko -->
						</li>
					</ol>
					<input data-bind="textInput: addCatName" placeholder="New category name">
					<button class=add title="Add a new category with the specified name" data-bind="enable: addCatName().trim(), click: AddCategory"><span>Add</span></button>
				</section>
			</div>
<?php
$html->Close();

function UpdateCategoryName() {
	global $ajax, $db;
	if($u = $db->prepare('update categories set name=? where id=? limit 1'))
		if($u->bind_param('si', $name, $id)) {
			$name = trim($_POST['name']);
			$id = +$_POST['id'];
			if($u->execute())
				$ajax->Data->name = $name;
			else
				$ajax->Fail('Error executing category name update:  ' . $u->error);
		} else
			$ajax->Fail('Error binding parameters for category name update:  ' . $u->error);
	else
		$ajax->Fail('Error preparing to update category name:  ' . $db->error);
}

function MoveCategory() {
	global $ajax, $db;
	if($u = $db->prepare('update categories set parent=? where id=? limit 1'))
		if($u->bind_param('ii', $parent, $id)) {
			$parent = +$_POST['parent'];
			if($parent < 0)
				$parent = null;
			$id = +$_POST['id'];
			if($u->execute())
				;  // everything worked and we don't need to send anything back
			else
				$ajax->Fail('Error executing category move:  ' . $u->error);
		} else
			$ajax->Fail('Error binding parameters for category move:  ' . $u->error);
	else
		$ajax->Fail('Error preparing to move category:  ' . $db->error);
}

function AddCategory() {
	global $ajax, $db;
	if($i = $db->prepare('insert into categories (name) values (?)'))
		if($i->bind_param('s', $name)) {
			$name = trim($_POST['name']);
			if($i->execute()) {
				$ajax->Data->name = $name;
				$ajax->Data->id = $i->insert_id;
			} else
				$ajax->Fail('Error executing category add:  ' . $i->error);
		} else
			$ajax->Fail('Error binding parameters to add category:  ' . $i->error);
	else
		$ajax->Fail('Error preparing to add category:  ' . $db->error);
}
