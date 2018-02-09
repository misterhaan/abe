<?php
/**
 * HTML layout for Abe Personal Finance
 *
 * Each script should create and use an object from this class when it's not
 * responding to an ajax request.
 * @author misterhaan
 */
class abeHtml {
	const SITE_NAME_FULL = 'Abe Personal Finance';
	const SITE_NAME_SHORT = 'Abe';

	/**
	 * Whether the HTML has been opened.
	 * @var bool
	 */
	private $isopen = false;
	/**
	 * Whether the HTML has been closed.
	 * @var bool
	 */
	private $isclosed = false;

	/**
	 * Page name for bookmarking, or false if not bookmarkable.
	 * @var string|bool $bookmarkPage
	 */
	private $bookmarkPage = false;
	/**
	 * Action links to put in the header.
	 * @var array
	 */
	private $actions = [];

	/**
	 * Creates a new abeHtml object.
	 */
	public function abeHtml() {
	}

	/**
	 * Enables bookmarks for this page.  Adds an action, so control its position
	 * by ordering this call with any AddAction() calls.
	 * @param string $tooltip Tooltip value for add bookmark action link
	 * @param string $page Page being bookmarked such as transactions or spending.  Skip to find from SCRIPT_NAME.
	 */
	public function EnableBookmark($tooltip, $page = false) {
		if(!$page)
			$page = $_SERVER['SCRIPT_NAME'];
		$page = explode('/', $page);
		$page = $page[count($page) - 1];
		if(substr($page, -4) == '.php')
			$page = substr($page, 0, -4);
		$this->bookmarkPage = $page;
		$this->AddAction('#addBookmark', 'bookmark', 'Bookmark', $tooltip);
	}

	/**
	 * Add an action link to the header.  Must be called before Open().
	 * @param string $url Action link URL.
	 * @param string $class CSS class name for the action (used to replace text with a fontawesome icon)
	 * @param string $text Link text (usually hidden by the class).
	 * @param string $tooltip Link tooltip text.
	 */
	public function AddAction($url, $class, $text, $tooltip = '') {
		$this->actions[] = ['url' => $url, 'class' => $class, 'text' => $text, 'tooltip' => $tooltip];
	}

	/**
	 * Starts the HTML and writes out the header.  This should be called before
	 * any other HTML output from the script.
	 * @param string $title Title of the page to display on the browser tab.  The site name will be added to the end if it's not contained in the title.
	 */
	public function Open($title) {
		if($this->isopen)
			return;
		$this->isopen = true;
		if(strpos($title, self::SITE_NAME_FULL) === false && strpos($title, self::SITE_NAME_SHORT) === false)
			$title .= ' - ' . self::SITE_NAME_SHORT;
		header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang=en>
	<head>
		<meta charset=utf-8>
		<meta name=viewport content="width=device-width, initial-scale=1">
		<title><?php echo $title; ?></title>
		<link rel=stylesheet href="<?php echo INSTALL_PATH; ?>/theme/abe.css">
		<script src="<?php echo INSTALL_PATH; ?>/jquery-3.2.1.min.js" type="text/javascript"></script>
		<script src="<?php echo INSTALL_PATH; ?>/knockout-3.4.2.js" type="text/javascript"></script>
<?php
		if(basename($_SERVER['SCRIPT_NAME']) == 'spending.php') {
?>
		<script src="<?php echo INSTALL_PATH; ?>/d3.min.js" type="text/javascript"></script>
<?php
		}
?>
		<script src="<?php echo INSTALL_PATH; ?>/abe.js" type="text/javascript"></script>
<?php
		if(file_exists(str_replace('.php', '.js', $_SERVER['SCRIPT_FILENAME']))) {
?>
		<script src="<?php echo str_replace('.php', '.js', $_SERVER['SCRIPT_NAME']); ?>" type="text/javascript"></script>
<?php
		}
?>
		<link rel="apple-touch-icon" sizes="57x57" href="<?php echo INSTALL_PATH; ?>/apple-icon-57x57.png">
		<link rel="apple-touch-icon" sizes="60x60" href="<?php echo INSTALL_PATH; ?>/apple-icon-60x60.png">
		<link rel="apple-touch-icon" sizes="72x72" href="<?php echo INSTALL_PATH; ?>/apple-icon-72x72.png">
		<link rel="apple-touch-icon" sizes="76x76" href="<?php echo INSTALL_PATH; ?>/apple-icon-76x76.png">
		<link rel="apple-touch-icon" sizes="114x114" href="<?php echo INSTALL_PATH; ?>/apple-icon-114x114.png">
		<link rel="apple-touch-icon" sizes="120x120" href="<?php echo INSTALL_PATH; ?>/apple-icon-120x120.png">
		<link rel="apple-touch-icon" sizes="144x144" href="<?php echo INSTALL_PATH; ?>/apple-icon-144x144.png">
		<link rel="apple-touch-icon" sizes="152x152" href="<?php echo INSTALL_PATH; ?>/apple-icon-152x152.png">
		<link rel="apple-touch-icon" sizes="180x180" href="<?php echo INSTALL_PATH; ?>/apple-icon-180x180.png">
		<link rel="icon" type="image/png" sizes="192x192"  href="<?php echo INSTALL_PATH; ?>/android-icon-192x192.png">
		<link rel="icon" type="image/png" sizes="32x32" href="<?php echo INSTALL_PATH; ?>/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="<?php echo INSTALL_PATH; ?>/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="<?php echo INSTALL_PATH; ?>/favicon-16x16.png">
		<meta name="msapplication-TileColor" content="#593">
		<meta name="msapplication-TileImage" content="<?php echo INSTALL_PATH; ?>/ms-icon-144x144.png">
		<meta name="theme-color" content="#593">
	</head>
	<body>
		<header>
			<span class=back>
<?php
		if($needsMenu = $_SERVER['SCRIPT_NAME'] != INSTALL_PATH . '/index.php') {
?>
				<a id=toggleMenuPane href="<?php echo INSTALL_PATH; ?>/" title="Go to main menu"><span>home</span></a>
<?php
		}
?>
			</span>
			<span class=actions>
<?php
		foreach($this->actions as $action) {
?>
				<a class=<?php echo $action['class']; ?> href="<?php echo $action['url']; ?>" title="<?php echo $action['tooltip']; ?>"><span><?php echo $action['text']; ?></span></a>
<?php
		}
?>
			</span>
		</header>
<?php
		if($needsMenu) {
?>
		<div id=menuPane>
<?php
			$this->MainMenu();
?>
		</div>
<?php
		}
?>
		<main role=main>
<?php
	}

	/**
	 * Write out the main menu.
	 */
	public function MainMenu() {
		global $db;
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
	}

	/**
	 * Write out the form for adding a bookmark.
	 */
	public function FormAddBookmark() {
		if($this->bookmarkPage) {
?>
			<div id=newBookmark>
				<label>
					<span>Title:</span>
					<input id=bookmarkName required maxlength=60>
				</label>
				<label>
					<span>Page:</span>
					<input id=bookmarkUrl readonly data-page=<?=$this->bookmarkPage; ?> data-spec="" value="<?=$this->bookmarkPage; ?>.php">
				</label>
				<div class=calltoaction>
					<button id=saveBookmark>Save</button><a href="#cancelBookmark" title="Go back to the transactions list">Cancel</a>
				</div>
			</div>
<?php
		}
	}

	/**
	 * Ends the HTML and writes out the footer.  This should be called after all
	 * other HTML output from the script.
	 */
	public function Close() {
		if(!$this->isopen || $this->isclosed)
			return;
		$this->isclosed = true;
?>
		</main>
		<footer>
			<div id=copyright>© 2017 - 2018 <?php echo self::SITE_NAME_FULL; ?></div>
		</footer>
	</body>
</html>
<?php
	}
}
?>
