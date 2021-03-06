<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for bookmark API requests.
 * @author misterhaan
 */
class BookmarkApi extends abeApi {
	/**
	 * Write out the documentation for the bookmark API controller.  The page is
	 * already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
			<h2 id=POSTadd>POST add</h2>
			<p>Add a new bookmark.  All parameters are required.</p>
			<dl class=parameters>
				<dt>page</dt>
				<dd>
					Page the bookmark goes to, which is the php file without the
					extension.  For example, <code>transactions</code> for
					transactions.php.
				</dd>
				<dt>spec</dt>
				<dd>
					Specifics of the bookmark, which is generally the URL hash but could
					also contain a query string.  Must begin with a <code>#</code> or
					<code>?</code> character.
				</dd>
				<dt>name</dt>
				<dd>Text the bookmark link will display with.</dd>
			</dl>

			<h2 id=POSTdelete>POST delete</h2>
			<p>Remove a bookmark.  All parameters are required.</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>ID of the bookmark to remove.</dd>
			</dl>

			<h2 id=GETlist>GET list</h2>
			<p>Get all bookmarks in order.</p>

			<h2 id=POSTmoveDown>POST moveDown</h2>
			<p>
				Move a bookmark down in the sort order, switching with the bookmark
				after it.  All parameters are required.
			</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>ID of the bookmark to move down.</dd>
			</dl>

			<h2 id=POSTmoveUp>POST moveUp</h2>
			<p>
				Move a bookmark up in the sort order, switching with the bookmark
				before it.  All parameters are required.
			</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>ID of the bookmark to move up.</dd>
			</dl>
<?php
	}

	/**
	 * Action to add a bookmark.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function addAction(abeAjax $ajax) {
		if(isset($_POST['page'], $_POST['spec'], $_POST['name']) && ($page = trim($_POST['page'])) && ($spec = trim($_POST['spec'])) && ($name = trim($_POST['name']))) {
			$pagename = explode('/', $page)[0];
			if(file_exists(dirname(__DIR__) . '/' . $pagename . '.php') || file_exists(dirname(__DIR__) . '/module/component/' . $pagename . '.js')) {
				$db = self::RequireLatestDatabase($ajax);
				if($ins = $db->prepare('insert into bookmarks (page, spec, name, sort) values (?, ?, ?, (select coalesce(max(b.sort), 0) + 1 from bookmarks as b))')) {
					if($ins->bind_param('sss', $page, $spec, $name)) {
						if($ins->execute())
							; // success!  done.
						elseif($db->errno == 1062)
							$ajax->Fail('This page is already bookmarked.');
						else
							$ajax->Fail('Database error saving bookmark:  ' . $db->errno . ' ' . $db->error);
					} else
						$ajax->Fail('Database error binding parameters to save bookmark:  ' . $db->errno . ' ' . $db->error);
				} else
					$ajax->Fail('Database error preparing to save bookmark:  ' . $db->errno . ' ' . $db->error);
			} else
				$ajax->Fail('Invalid page parameter:  page does not exist.');
		} else
			$ajax->Fail('Required parameter(s) missing.  Provide page, spec, and name.');
	}

	/**
	 * Action to delete a bookmark.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function deleteAction(abeAjax $ajax) {
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db = self::RequireLatestDatabase($ajax);
			$db->autocommit(false);
			if($update = $db->prepare('update bookmarks set sort=sort-1 where sort>(select sort from (select sort from bookmarks where id=?) as b)'))
				if($update->bind_param('i', $id))
					if($update->execute())
						if($del = $db->prepare('delete from bookmarks where id=? limit 1'))
							if($del->bind_param('i', $id))
								if($del->execute())
									$db->commit();
								else
									$ajax->Fail('Database error deleting bookmark:  ' . $db->errno . ' ' . $db->error);
							else
								$ajax->Fail('Database error binding parameter to delete bookmark:  ' . $db->errno . ' ' . $db->error);
						else
							$ajax->Fail('Database error preparing to delete bookmark:  ' . $db->errno . ' ' . $db->error);
					else
						$ajax->Fail('Database error ajusting sorting:  ' . $db->errno . ' ' . $db->error);
				else
					$ajax->Fail('Database error binding parameter to adjust sorting:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Database error preparing to adjust sorting:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Required parameter missing or invalid.  Provide a numeric id to delete.');
	}

	/**
	 * Action to get the complete list of bookmarks in the correct sort order.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction(abeAjax $ajax) {
		$db = self::RequireLatestDatabase($ajax);
		$bookmarks = 'select id, page, concat(\'#\', page, \'!\', trim(leading \'#!\' from spec)) as url, name from bookmarks order by sort';
		if($bookmarks = $db->query($bookmarks)) {
			$list = [];
			while($bookmark = $bookmarks->fetch_object())
				$list[] = $bookmark;
			$ajax->Data->bookmarks = $list;
		} else
			$ajax->Fail('Error looking up bookmarks:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Action to move a bookmark down in the sort order.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function moveDownAction(abeAjax $ajax) {
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db = self::RequireLatestDatabase($ajax);
			$db->autocommit(false);
			if($swap = $db->prepare('update bookmarks set sort=sort-1 where sort=(select sort+1 from (select sort from bookmarks where id=? limit 1) as b) limit 1'))
				if($swap->bind_param('i', $id))
					if($swap->execute())
						if($swap = $db->prepare('update bookmarks set sort=sort+1 where id=? limit 1'))
							if($swap->bind_param('i', $id))
								if($swap->execute())
									$db->commit();
								else
									$ajax->Fail('Database error moving bookmark down:  ' . $db->errno . ' ' . $db->error);
							else
								$ajax->Fail('Database error binding parameter to move bookmark down:  ' . $db->errno . ' ' . $db->error);
						else
							$ajax->Fail('Database error preparing to move bookmark down:  ' . $db->errno . ' ' . $db->error);
					else
						$ajax->Fail('Database error moving next bookmark up:  ' . $db->errno . ' ' . $db->error);
				else
					$ajax->Fail('Database error binding parameter to move next bookmark up:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Database error preparing to move next bookmark up:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Required parameter missing or invalid.  Provide a numeric id to move.');
	}

	/**
	 * Action to move a bookmark up in the sort order.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function moveUpAction(abeAjax $ajax) {
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db = self::RequireLatestDatabase($ajax);
			$db->autocommit(false);
			if($swap = $db->prepare('update bookmarks set sort=sort+1 where sort=(select sort-1 from (select sort from bookmarks where id=? limit 1) as b) limit 1'))
				if($swap->bind_param('i', $id))
					if($swap->execute())
						if($swap = $db->prepare('update bookmarks set sort=sort-1 where id=? limit 1'))
							if($swap->bind_param('i', $id))
								if($swap->execute())
									$db->commit();
								else
									$ajax->Fail('Database error moving bookmark up:  ' . $db->errno . ' ' . $db->error);
							else
								$ajax->Fail('Database error binding parameter to move bookmark up:  ' . $db->errno . ' ' . $db->error);
						else
							$ajax->Fail('Database error preparing to move bookmark up:  ' . $db->errno . ' ' . $db->error);
					else
						$ajax->Fail('Database error moving previous bookmark down:  ' . $db->errno . ' ' . $db->error);
				else
					$ajax->Fail('Database error binding parameter to move previous bookmark down:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Database error preparing to move previous bookmark down:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Required parameter missing or invalid.  Provide a numeric id to move.');
	}
}
BookmarkApi::Respond();
