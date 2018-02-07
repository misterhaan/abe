<?php
require_once __DIR__ . '/etc/class/abe.php';

$html = new abeHtml();
$html->Open(abeHtml::SITE_NAME_FULL);
?>
			<h1><?php echo abeHtml::SITE_NAME_FULL; ?></h1>
<?php
$bookmarks = 'select id, page, concat(page, \'.php\', spec) as url, name from bookmarks order by sort';
if($bookmarks = $db->query($bookmarks))
	if($bmcount = $bookmarks->num_rows) {
?>
			<nav id=bookmarks>
				<div>
					<header>Bookmarks</header>
<?php
		for($b = 0; $bookmark = $bookmarks->fetch_object(); $b++) {
?>
					<div class=bookmark data-id=<?=$bookmark->id; ?>>
						<a class=<?=htmlspecialchars($bookmark->page); ?> href="<?=htmlspecialchars($bookmark->url); ?>"><?=htmlspecialchars($bookmark->name); ?></a>
<?php
			if($bmcount > 1 && $b + 1 < $bmcount) {
?>
						<a class=down href="api/bookmark/moveDown" title="Move this bookmark down"></a>
<?php
			}
			if($bmcount > 1 && $b) {
?>
						<a class=up href="api/bookmark/moveUp" title="Move this bookmark up"></a>
<?php
			}
?>
						<a class=delete href="api/bookmark/delete" title="Delete this bookmark"></a>
					</div>
<?php
		}
?>
				</div>
			</nav>
<?php
	}
?>
			<nav id=mainmenu>
				<a href=transactions.php>Transactions</a>
				<a href=spending.php>Spending</a>
				<a href=import.php>Import</a>
				<a href=categories.php>Settings</a>
			</nav>
<?php
$html->Close();
