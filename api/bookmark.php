<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for bookmark API requests.
 * @author misterhaan
 */
class BookmarkApi extends Api {
	/**
	 * Return the documentation for the bookmark API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'list', 'Get all bookmarks in order.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'add', 'Add a new bookmark.', 'multipart');
		$endpoint->BodyParameters[] = new ParameterDocumentation('view', 'string', 'Page the bookmark goes to, which is the part of the URL directly after the <code>#</code> character.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('spec', 'string', 'Specific view settings for the bookmark.  Must begin with a <code>#</code> character.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'Text the bookmark link will display with.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'moveDown', 'Move a bookmark down in the sort order, switching with the bookmark after it.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the bookmark to move down.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'moveUp', 'Move a bookmark up in the sort order, switching with the bookmark before it.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the bookmark to move up.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('DELETE', 'id', 'Remove a bookmark.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the bookmark to remove.', true);

		return $endpoints;
	}

	/**
	 * Action to get the complete list of bookmarks in the correct sort order.
	 */
	protected static function GET_list() {
		$db = self::RequireLatestDatabase();
		try {
			$select = $db->prepare('select id, page, concat(\'#\', page, \'!\', trim(leading \'#!\' from spec)) as url, name from bookmarks order by sort');
			$select->execute();
			$select->bind_result($id, $page, $url, $name);
			$bookmarks = [];
			while ($select->fetch())
				$bookmarks[] = new Bookmark($id, $page, $url, $name);
			$select->close();
			self::Success($bookmarks);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up bookmarks', $mse);
		}
	}

	/**
	 * Action to add a bookmark.
	 */
	protected static function POST_add(): void {
		if (!isset($_POST['view'], $_POST['spec'], $_POST['name']) || !($view = trim($_POST['view'])) || !($spec = trim($_POST['spec'])) || !($name = trim($_POST['name'])))
			self::NeedMoreInfo('Required parameter(s) missing.  Provide view, spec, and name.');
		$pagename = explode('/', $view)[0];
		if (!file_exists(dirname(__DIR__) . '/module/component/' . $pagename . '.js'))
			self::NotFound('Invalid view parameter:  view does not exist.');
		$db = self::RequireLatestDatabase();
		try {
			$insert = $db->prepare('insert into bookmarks (page, spec, name, sort) values (?, ?, ?, (select coalesce(max(b.sort), 0) + 1 from bookmarks as b))');
			$insert->bind_param('sss', $view, $spec, $name);
			$insert->execute();
			$insert->close();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error saving bookmark', $mse);
		}
	}

	/**
	 * Action to move a bookmark down in the sort order.
	 */
	protected static function POST_moveDown(array $params): void {
		$id = +array_shift($params);
		if (!$id)
			self::NeedMoreInfo('Required parameter missing or invalid.  Provide a numeric id to move.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update bookmarks set sort=sort-1 where sort=(select sort+1 from (select sort from bookmarks where id=? limit 1) as b) limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$update = $db->prepare('update bookmarks set sort=sort+1 where id=? limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error moving bookmark down', $mse);
		}
	}

	/**
	 * Action to move a bookmark up in the sort order.
	 */
	protected static function POST_moveUp(array $params): void {
		$id = +array_shift($params);
		if (!$id)
			self::NeedMoreInfo('Required parameter missing or invalid.  Provide a numeric id to move.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update bookmarks set sort=sort+1 where sort=(select sort-1 from (select sort from bookmarks where id=? limit 1) as b) limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$update = $db->prepare('update bookmarks set sort=sort-1 where id=? limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error moving bookmark up', $mse);
		}
	}

	/**
	 * Action to delete a bookmark.
	 */
	protected static function DELETE_id(array $params) {
		$id = +array_shift($params);
		if (!$id)
			self::NeedMoreInfo('Required parameter missing or invalid.  Provide a numeric id to delete.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update bookmarks set sort=sort-1 where sort>(select sort from (select sort from bookmarks where id=?) as b)');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$delete = $db->prepare('delete from bookmarks where id=? limit 1');
			$delete->bind_param('i', $id);
			$delete->execute();
			$delete->close();

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error deleting bookmark', $mse);
		}
	}
}

class Bookmark {
	public int $ID;
	public string $Page;
	public string $URL;
	public string $Name;

	public function __construct($id, $page, $url, $name) {
		$this->ID = $id;
		$this->Page = $page;
		$this->URL = $url;
		$this->Name = $name;
	}
}

BookmarkApi::Respond();
