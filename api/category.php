<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for category API requests.
 * @author misterhaan
 */
class CategoryApi extends abeApi {
	/**
	 * Write out the documentation for the category API controller.  The page is
	 * already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
			<h2 id=POSTadd>POST add</h2>
			<p>
				Add a new category.  Note that categories can also be added by entering
				an unrecognized name into a transaction’s category field, which does not
				use this API and always puts the category in the (ungrouped) group.
			</p>
			<dl class=parameters>
				<dt>name</dt>
				<dd>
					Name of the new category.  Category names must be unique so requests
					with a duplicate category name will fail.
				</dd>
				<dt>grp</dt>
				<dd>
					ID of the category group to put this category into.  Optional.
					Default is the (ungrouped) group.
				</dd>
			</dl>

			<h2 id=POSTdelete>POST delete</h2>
			<p>Delete an existing category, provided nothing is using it.</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the category to delete.
				</dd>
			</dl>

			<h2 id=GETlist>GET list</h2>
			<p>Get the list of all categories.</p>

			<h2 id=POSTmove>POST move</h2>
			<p>Move an existing category to a different category group.</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the category to move.
				</dd>
				<dt>grp</dt>
				<dd>
					ID of the category group to move this category into.  May specify
					<code>null</code> for the (ungrouped) group.
				</dd>
			</dl>

			<h2 id=POSTrename>POST rename</h2>
			<p>Rename an existing category.</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the category to rename.
				</dd>
				<dt>name</dt>
				<dd>
					New name for the category.  Category names must be unique so requests
					with a duplicate category name will fail.
				</dd>
			</dl>
<?php
	}

	/**
	 * Add a new category.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function addAction($ajax) {
		if(isset($_POST['name']) && $name = trim($_POST['name'])) {
			$db = self::RequireLatestDatabase($ajax);
			if($i = $db->prepare('insert into categories (name, grp) values (?, ?)'))
				if($i->bind_param('si', $name, $grp)) {
					$grp = isset($_POST['grp']) && +$_POST['grp'] ? +$_POST['grp'] : null;
					if($i->execute()) {
						$ajax->Data->name = $name;
						$ajax->Data->grp = $grp;
						$ajax->Data->id = $i->insert_id;
					} else
						$ajax->Fail('Error executing category add:  ' . $i->errno . ' ' . $i->error);
				} else
					$ajax->Fail('Error binding parameters to add category:  ' . $i->errno . ' ' . $i->error);
			else
				$ajax->Fail('Error preparing to add category:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameter \'name\' is required and cannot be blank.');
	}

	/**
	 * Delete a category.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function deleteAction($ajax) {
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db = self::RequireLatestDatabase($ajax);
			if($chk = $db->prepare('select case when exists(select 1 from transactions where category=? limit 1) or exists(select 1 from splitcats where category=?) then 1 else 0 end'))
				if($chk->bind_param('ii', $id, $id))
					if($chk->execute())
						if($chk->bind_result($inuse))
							if($chk->fetch())
								if(+$inuse == 0) {
									$chk->close();
									if($del = $db->prepare('delete from categories where id=? limit 1'))
										if($del->bind_param('i', $id))
											if($del->execute())
												; // success!
											else
												$ajax->Fail('Error executing category delete:  ' . $del->errno . ' ' . $del->error);
										else
											$ajax->Fail('Error binding parameters to delete category:  ' . $del->errno .' ' . $del->error);
									else
										$ajax->Fail('Error preparing to delete category:  ' . $db->errno . ' ' . $db->error);
								} else
									$ajax->Fail('Cannot delete category because it is in use.');
							else
								$ajax->Fail('Error fetching result of category usage check:  ' . $chk->errno . ' ' . $chk->error);
						else
							$ajax->Fail('Error binding result of category usage check:  ' . $chk->errno . ' ' . $chk->error);
					else
						$ajax->Fail('Error executing category usage check:  ' . $chk->errno . ' ' . $chk->error);
				else
					$ajax->Fail('Error binding parameters to check category usage:  ' . $chk->errno . ' ' . $chk->error);
			else
				$ajax->Fail('Error preparing to check category usage:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameter \'id\' is required and must be numeric.');
	}

	/**
	 * Get the list of all categories.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction($ajax) {
		$db = self::RequireLatestDatabase($ajax);
		if($cats = $db->query('select c.id, c.name, coalesce(g.name, \'(ungrouped)\') as groupname from categories as c left join category_groups as g on g.id=c.grp order by groupname, c.name')) {
			$ajax->Data->categories = [];
			while($cat = $cats->fetch_object())
				$ajax->Data->categories[] = $cat;
		} else
			$ajax->Fail('Error looking up categories:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Move a category to a different group.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function moveAction($ajax) {
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db = self::RequireLatestDatabase($ajax);
			$grp = isset($_POST['grp']) && +$_POST['grp'] ? +$_POST['grp'] : null;
			if($u = $db->prepare('update categories set grp=? where id=? limit 1'))
				if($u->bind_param('ii', $grp, $id))
					if($u->execute())
						; // success!
					else
						$ajax->Fail('Error executing category move:  ' . $u->errno . ' ' . $u->error);
				else
					$ajax->Fail('Error binding parameters to move category:  ' . $u->errno . ' ' . $u->error);
			else
				$ajax->Fail('Error preparing to move category:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameter \'id\' is required and must be numeric.');
	}

	/**
	 * Rename an existing category.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function renameAction($ajax) {
		if(isset($_POST['id']) && isset($_POST['name']) && ($id = +$_POST['id']) && $name = trim($_POST['name'])) {
			$db = self::RequireLatestDatabase($ajax);
			if($u = $db->prepare('update categories set name=? where id=? limit 1'))
				if($u->bind_param('si', $name, $id))
					if($u->execute())
						$ajax->Data->name = $name;
					else
						$ajax->Fail('Error executing category rename:  ' . $u->errno . ' ' . $u->error);
				else
					$ajax->Fail('Error binding parameters to rename category:  ' . $u->errno . ' ' . $u->error);
			else
				$ajax->Fail('Error preparing to rename category:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameters \'id\' and \'name\' are required and cannot be zero or blank.');
	}
}
CategoryApi::Respond();
