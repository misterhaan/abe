<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for categoryGroup API requests.
 * @author misterhaan
 */
class CategoryGroupApi extends Api {
	/**
	 * Return the documentation for the categoryGroup API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'list', 'Get the list of category groups with their categories.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'add', 'Add a new category group.', 'multipart');
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'Name of the new category group. Category group names must be unique so requests with a duplicate category name will fail.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('PATCH', 'name', 'Rename an existing category group.', 'string', 'New name for the category group. Category group names must be unique so requests with a duplicate category group name will fail.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the category group to rename.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('DELETE', 'delete', 'Delete an existing category group, provided nothing is using it.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the category group to delete.', true);

		return $endpoints;
	}

	/**
	 * Get the list of all categories organized into their groups.
	 */
	protected static function GET_list() {
		$db = self::RequireLatestDatabase();
		try {
			$select = $db->prepare('select ifnull(g.id,0) as id, coalesce(g.name, \'(ungrouped)\') as name, group_concat(c.id order by c.name separator \'\\n\') as catids, group_concat(c.name order by c.name separator \'\\n\') as catnames from categories as c left join category_groups as g on g.id=c.grp group by g.id union select g.id, g.name, null as catids, null as catnames from category_groups as g left join categories as c on c.grp=g.id where c.id is null order by name');
			$select->execute();
			$select->bind_result($id, $name, $catids, $catnames);
			$groups = [];
			while ($select->fetch())
				$groups[] = new Group($id, $name, $catids ? explode("\n", $catids) : [], $catnames ? explode("\n", $catnames) : []);
			$select->close();
			self::Success($groups);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up categories', $mse);
		}
	}

	/**
	 * Add a new category group.
	 */
	protected static function POST_add() {
		if (!isset($_POST['name']) || !($name = trim($_POST['name'])))
			self::NeedMoreInfo('Parameter “name” is required and cannot be blank.');
		$db = self::RequireLatestDatabase();
		try {
			$insert = $db->prepare('insert into category_groups (name) values (?)');
			$insert->bind_param('s', $name);
			$insert->execute();
			$id = $insert->insert_id;
			$insert->close();
			self::Success($id);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error adding category group', $mse);
		}
	}

	/**
	 * Rename an existing category group.
	 */
	protected static function PATCH_name(array $params) {
		if (!($id = +array_shift($params)))
			self::NeedMoreInfo('Parameter “id” is required and cannot be zero or blank.');
		if (!($name = trim(self::ReadRequestText())))
			self::NeedMoreInfo('Request body is required.');
		$db = self::RequireLatestDatabase();
		try {
			$update = $db->prepare('update category_groups set name=? where id=? limit 1');
			$update->bind_param('si', $name, $id);
			$update->execute();
			$update->close();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error renaming category group', $mse);
		}
	}

	/**
	 * Delete a category group.
	 */
	protected static function DELETE_delete(array $params) {
		if (!($id = +array_shift($params)))
			self::NeedMoreInfo('Parameter “id” is required and cannot be zero or blank.');
		$db = self::RequireLatestDatabase();
		try {
			$select = $db->prepare('select case when exists(select 1 from categories where grp=? limit 1) then 1 else 0 end');
			$select->bind_param('i', $id);
			$select->execute();
			$select->bind_result($inuse);
			$select->fetch();
			$select->close();
			if ($inuse)
				self::NeedMoreInfo('Cannot delete category group because it is in use.');
			$delete = $db->prepare('delete from category_groups where id=? limit 1');
			$delete->bind_param('i', $id);
			$delete->execute();
			$delete->close();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error deleting category group', $mse);
		}
	}
}

class Group {
	public int $ID;
	public string $Name;
	public array $Categories;

	public function __construct(int $id, string $name, array $catids, array $catnames) {
		$this->ID = $id;
		$this->Name = $name;
		$this->Categories = array_map(function ($id, $name) {
			return new Category(+$id, $name);
		}, $catids, $catnames);
	}
}

class Category {
	public int $ID;
	public string $Name;

	public function __construct(int $id, string $name) {
		$this->ID = $id;
		$this->Name = $name;
	}
}

CategoryGroupApi::Respond();
