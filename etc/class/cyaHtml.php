<?php
/**
 * HTML layout for Collect Your Assets
 *
 * Each script should create and use an object from this class when it's not
 * responding to an ajax request.
 * @author misterhaan
 */
class cyaHtml {
	const SITE_NAME_FULL = 'Collect Your Assets';
	const SITE_NAME_SHORT = 'C-YA';

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
	 * URL for the back link in the header.
	 * @var string
	 */
	private $back = '/';
	/**
	 * Action links to put in the header.
	 * @var array
	 */
	private $actions = [];

	/**
	 * Creates a new cyaHtml object.
	 */
	public function cyaHtml() {
		$this->back = INSTALL_PATH . '/';
	}

	/**
	 * Set the URL for the header's back link.  Must be called before Open().
	 * @param string $url Relative URL for the header's back link.
	 */
	public function SetBack($url) {
		$this->back = $url;
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
		<link rel=stylesheet href="<?php echo INSTALL_PATH; ?>/theme/cya.css">
		<script src="<?php echo INSTALL_PATH; ?>/jquery-3.1.1.min.js" type="text/javascript"></script>
		<script src="<?php echo INSTALL_PATH; ?>/knockout-3.4.1.js" type="text/javascript"></script>
		<script src="<?php echo INSTALL_PATH; ?>/cya.js" type="text/javascript"></script>
<?php
		if(file_exists(str_replace('.php', '.js', $_SERVER['SCRIPT_FILENAME']))) {
?>
		<script src="<?php echo str_replace('.php', '.js', $_SERVER['SCRIPT_NAME']); ?>" type="text/javascript"></script>
<?php
		}
?>
	</head>
	<body>
		<header>
			<span class=back>
<?php
		if($_SERVER['PHP_SELF'] != INSTALL_PATH . '/index.php') {
?>
				<a href="<?php echo $this->back; ?>"><span>back</span></a>
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
		<main role=main>
<?php
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
			<div id=copyright>© 2017 <?php echo self::SITE_NAME_FULL; ?></div>
		</footer>
	</body>
</html>
<?php
	}
}
?>
