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
	 * Creates a new abeHtml object.
	 */
	public function abeHtml() {
	}

	/**
	 * Starts the HTML and writes out the header.  This should be called before
	 * any other HTML output from the script.
	 * @param string $title Title of the page to display on the browser tab.  The site name will be added to the end if it's not contained in the title.
	 */
	public function Open(string $title) {
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
		<title><?=$title; ?></title>
		<link rel=stylesheet href="<?=INSTALL_PATH; ?>/theme/abe.css">
		<link rel="apple-touch-icon" sizes="57x57" href="<?=INSTALL_PATH; ?>/apple-icon-57x57.png">
		<link rel="apple-touch-icon" sizes="60x60" href="<?=INSTALL_PATH; ?>/apple-icon-60x60.png">
		<link rel="apple-touch-icon" sizes="72x72" href="<?=INSTALL_PATH; ?>/apple-icon-72x72.png">
		<link rel="apple-touch-icon" sizes="76x76" href="<?=INSTALL_PATH; ?>/apple-icon-76x76.png">
		<link rel="apple-touch-icon" sizes="114x114" href="<?=INSTALL_PATH; ?>/apple-icon-114x114.png">
		<link rel="apple-touch-icon" sizes="120x120" href="<?=INSTALL_PATH; ?>/apple-icon-120x120.png">
		<link rel="apple-touch-icon" sizes="144x144" href="<?=INSTALL_PATH; ?>/apple-icon-144x144.png">
		<link rel="apple-touch-icon" sizes="152x152" href="<?=INSTALL_PATH; ?>/apple-icon-152x152.png">
		<link rel="apple-touch-icon" sizes="180x180" href="<?=INSTALL_PATH; ?>/apple-icon-180x180.png">
		<link rel="icon" type="image/png" sizes="192x192"  href="<?=INSTALL_PATH; ?>/android-icon-192x192.png">
		<link rel="icon" type="image/png" sizes="32x32" href="<?=INSTALL_PATH; ?>/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="96x96" href="<?=INSTALL_PATH; ?>/favicon-96x96.png">
		<link rel="icon" type="image/png" sizes="16x16" href="<?=INSTALL_PATH; ?>/favicon-16x16.png">
		<meta name="msapplication-TileColor" content="#593">
		<meta name="msapplication-TileImage" content="<?=INSTALL_PATH; ?>/ms-icon-144x144.png">
		<meta name="theme-color" content="#593">
	</head>
	<body>
		<header>
			<span class=back>
				<a href="<?=INSTALL_PATH; ?>/" title="Go to main menu"><span>home</span></a>
			</span>
			<span class=actions>
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
			<div id=copyright>© 2017 - 2019 <?=self::SITE_NAME_FULL; ?></div>
		</footer>
	</body>
</html>
<?php
	}
}
