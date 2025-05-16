<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for category API requests.
 * @author misterhaan
 */
class CategoryApi extends Api {
	/**
	 * Return the documentation for the budget API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'add', 'Add a new category. Note that categories can also be added by entering an unrecognized name into a transaction’s category field, which does not use this API and always puts the category in the (ungrouped) group.', 'multipart');
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'Name of the new category. Category names must be unique so requests with a duplicate category name will fail.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('grp', 'integer', 'ID of the category group to put this category into. Default is the (ungrouped) group.');

		$endpoints[] = $endpoint = new EndpointDocumentation('PATCH', 'name', 'Rename an existing category.', 'string', 'New name for the category. Category names must be unique so requests with a duplicate category name will fail.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the category to rename.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('PATCH', 'group', 'Move an existing category to a different category group.', 'integer', 'ID of the category group to move this category into. Defaults to the (ungrouped) group.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the category to move.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('DELETE', 'delete', 'Delete an existing category, provided nothing is using it.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the category to delete.', true);

		return $endpoints;
	}

	/**
	 * Add a new category.
	 */
	protected static function POST_add() {
		if (!isset($_POST['name']) || !($name = trim($_POST['name'])))
			self::NeedMoreInfo('Parameter “name” is required and cannot be blank.');
		$db = self::RequireLatestDatabase();
		try {
			$insert = $db->prepare('insert into categories (name, grp) values (?, ?)');
			$insert->bind_param('si', $name, $grp);
			$grp = isset($_POST['grp']) && +$_POST['grp'] ? +$_POST['grp'] : null;
			$insert->execute();
			$id = $insert->insert_id;
			$insert->close();
			self::Success($id);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error adding category', $mse);
		}
	}

	/**
	 * Rename an existing category.
	 */
	protected static function PATCH_name(array $params) {
		if (!($id = +array_shift($params)))
			self::NeedMoreInfo('Parameter “id” is required and cannot be zero or blank.');
		if (!($name = trim(self::ReadRequestText())))
			self::NeedMoreInfo('Request body is required.');

		$db = self::RequireLatestDatabase();
		try {
			$update = $db->prepare('update categories set name=? where id=? limit 1');
			$update->bind_param('si', $name, $id);
			$update->execute();
			$update->close();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error renaming category', $mse);
		}
	}

	/**
	 * Move a category to a different group.
	 */
	protected static function PATCH_group(array $params) {
		if (!($id = +array_shift($params)))
			self::NeedMoreInfo('Parameter “id” is required and cannot be zero or blank.');
		$grp = self::ReadRequestText();
		$grp = $grp && $grp != 'null' ? +$grp : 0;  // when "0" gets sent it acts like it was ""
		$db = self::RequireLatestDatabase();

		try {
			$update = $db->prepare('update categories set grp=nullif(?,0) where id=? limit 1');
			$update->bind_param('ii', $grp, $id);
			$update->execute();
			$update->close();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error moving category', $mse);
		}
	}

	/**
	 * Delete a category.
	 */
	protected static function DELETE_delete(array $params) {
		if (!($id = +array_shift($params)))
			self::NeedMoreInfo('Parameter “id” is required and cannot be zero or blank.');
		$db = self::RequireLatestDatabase();
		try {
			$check = $db->prepare('select case when exists(select 1 from transactions where category=? limit 1) or exists(select 1 from splitcats where category=?) then 1 else 0 end');
			$check->bind_param('ii', $id, $id);
			$check->execute();
			$check->bind_result($inuse);
			$check->fetch();
			$check->close();
			if (+$inuse != 0)
				self::NeedMoreInfo('Cannot delete category because it is in use.');

			$delete = $db->prepare('delete from categories where id=? limit 1');
			$delete->bind_param('i', $id);
			$delete->execute();
			$delete->close();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error deleting category', $mse);
		}
	}
}
CategoryApi::Respond();
