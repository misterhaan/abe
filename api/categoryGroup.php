<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for categoryGroup API requests.
 * @author misterhaan
 */
class CategoryGroupApi extends abeApi {
	/**
	 * Write out the documentation for the categoryGroup API controller.  The page
	 * is already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
			<h2 id=POSTadd>POST add</h2>
			<p>
				Add a new category group.
			</p>
			<dl class=parameters>
				<dt>name</dt>
				<dd>
					Name of the new category group.  Category grouo names must be unique
					so requests with a duplicate category name will fail.
				</dd>
			</dl>

			<h2 id=POSTdelete>POST delete</h2>
			<p>Delete an existing category group, provided nothing is using it.</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the category group to delete.
				</dd>
			</dl>

			<h2 id=GETlist>GET list</h2>
			<p>Get the list of category groups with their categories.</p>

			<h2 id=POSTrename>POST rename</h2>
			<p>Rename an existing category group.</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the category group to rename.
				</dd>
				<dt>name</dt>
				<dd>
					New name for the category group.  Category group names must be unique
					so requests with a duplicate category group name will fail.
				</dd>
			</dl>
<?php
	}

	/**
	 * Add a new category group.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function addAction($ajax) {
		if(isset($_POST['name']) && $name = trim($_POST['name'])) {
			$db = self::RequireLatestDatabase($ajax);
			if($i = $db->prepare('insert into category_groups (name) values (?)'))
				if($i->bind_param('s', $name))
					if($i->execute()) {
						$ajax->Data->name = $name;
						$ajax->Data->id = $i->insert_id;
					} else
						$ajax->Fail('Error executing category group add:  ' . $i->errno . ' ' . $i->error);
				else
					$ajax->Fail('Error binding parameters to add category group:  ' . $i->errno . ' ' . $i->error);
			else
				$ajax->Fail('Error preparing to add category:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameter \'name\' is required and cannot be blank.');
	}

	/**
	 * Delete a category group.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function deleteAction($ajax) {
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db = self::RequireLatestDatabase($ajax);
			if($chk = $db->prepare('select case when exists(select 1 from categories where grp=? limit 1) then 1 else 0 end'))
				if($chk->bind_param('i', $id))
					if($chk->execute())
						if($chk->bind_result($inuse))
							if($chk->fetch())
								if(+$inuse == 0) {
									$chk->close();
									if($del = $db->prepare('delete from category_groups where id=? limit 1'))
										if($del->bind_param('i', $id))
											if($del->execute())
												; // success!
											else
												$ajax->Fail('Error executing category group delete:  ' . $del->errno . ' ' . $del->error);
										else
											$ajax->Fail('Error binding parameters to delete category group:  ' . $del->errno .' ' . $del->error);
									else
										$ajax->Fail('Error preparing to delete category group:  ' . $db->errno . ' ' . $error);
								} else
									$ajax->Fail('Cannot delete category group because it is in use.');
							else
								$ajax->Fail('Error fetching result of category group usage check:  ' . $chk->errno . ' ' . $chk->error);
						else
							$ajax->Fail('Error binding result of category group usage check:  ' . $chk->errno . ' ' . $chk->error);
					else
						$ajax->Fail('Error executing category group usage check:  ' . $chk->errno . ' ' . $chk->error);
				else
					$ajax->Fail('Error binding parameters to check category group usage:  ' . $chk->errno . ' ' . $chk->error);
			else
				$ajax->Fail('Error preparing to check category group usage:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameter \'id\' is required and must be numeric.');
	}

	/**
	 * Get the list of all categories organized into their groups.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction($ajax) {
		$db = self::RequireLatestDatabase($ajax);
		if($groups = $db->query('select g.id, coalesce(g.name, \'(ungrouped)\') as name, group_concat(c.id order by c.name separator \'\\n\') as catids, group_concat(c.name order by c.name separator \'\\n\') as catnames from categories as c left join category_groups as g on g.id=c.grp group by g.id order by g.name')) {
			$ajax->Data->groups = [];
			while($group = $groups->fetch_object()) {
				$catids = explode("\n", $group->catids);
				$catnames = explode("\n", $group->catnames);
				$cats = [];
				for($c = 0; $c < count($catids); $c++)
					$cats[] = ['id' => +$catids[$c], 'name' => $catnames[$c]];
				$ajax->Data->groups[] = ['id' => +$group->id, 'name' => $group->name, 'categories' => $cats];
			}
		} else
			$ajax->Fail('Error looking up categories:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Rename an existing category group.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function renameAction($ajax) {
		if(isset($_POST['id']) && isset($_POST['name']) && ($id = +$_POST['id']) && $name = trim($_POST['name'])) {
			$db = self::RequireLatestDatabase($ajax);
			if($u = $db->prepare('update category_groups set name=? where id=? limit 1'))
				if($u->bind_param('si', $name, $id))
					if($u->execute())
						$ajax->Data->name = $name;
					else
						$ajax->Fail('Error executing category group rename:  ' . $u->errno . ' ' . $u->error);
				else
					$ajax->Fail('Error binding parameters to rename category group:  ' . $u->errno . ' ' . $u->error);
			else
				$ajax->Fail('Error preparing to rename category group:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameters \'id\' and \'name\' are required and cannot be zero or blank.');
	}
}
CategoryGroupApi::Respond();
