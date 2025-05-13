<?php

/**
 * HTML Page for Abe; currently just used by API documentation as actual Abe content is in HTML files.
 */
abstract class Page {
	private const siteTitleFull = 'Abe Personal Finance';
	private const siteTitleShort = 'Abe';

	protected static string $title = self::siteTitleFull;

	/**
	 * Generic constructor sets a title and starts output.
	 * @param $title Title of the page to show on the browser tab.
	 */
	public function __construct(string $title) {
		self::SetTitle($title);
		self::Send();
	}

	private static function SetTitle(string $title): void {
		if (strpos($title, self::siteTitleShort) === false && strpos($title, self::siteTitleFull) === false)
			self::$title = $title . ' - ' . self::siteTitleShort;
		else
			self::$title = $title;
	}

	/**
	 * Output the main content of the page, starting with an h1 tag to show the title.
	 */
	protected abstract static function MainContent(): void;

	/**
	 * Send the page as a response.
	 */
	private static function Send(): void {
		header('Content-Type: text/html; charset=utf-8');

		Env::Initialize();

		// find the application path on the webserver for absolute URLs
		$installPath = dirname(dirname(substr(__DIR__, strlen(Env::$DocRoot))));
		if ($installPath == '/')
			$installPath = '';

?>
		<!DOCTYPE html>
		<html lang=en>

		<head>
			<meta charset=utf-8>
			<meta name=viewport content="width=device-width, initial-scale=1">
			<title><?= self::$title; ?></title>
			<link rel=stylesheet href="<?= $installPath; ?>/theme/abe.css">
			<link rel="apple-touch-icon" sizes="57x57" href="<?= $installPath; ?>/apple-icon-57x57.png">
			<link rel="apple-touch-icon" sizes="60x60" href="<?= $installPath; ?>/apple-icon-60x60.png">
			<link rel="apple-touch-icon" sizes="72x72" href="<?= $installPath; ?>/apple-icon-72x72.png">
			<link rel="apple-touch-icon" sizes="76x76" href="<?= $installPath; ?>/apple-icon-76x76.png">
			<link rel="apple-touch-icon" sizes="114x114" href="<?= $installPath; ?>/apple-icon-114x114.png">
			<link rel="apple-touch-icon" sizes="120x120" href="<?= $installPath; ?>/apple-icon-120x120.png">
			<link rel="apple-touch-icon" sizes="144x144" href="<?= $installPath; ?>/apple-icon-144x144.png">
			<link rel="apple-touch-icon" sizes="152x152" href="<?= $installPath; ?>/apple-icon-152x152.png">
			<link rel="apple-touch-icon" sizes="180x180" href="<?= $installPath; ?>/apple-icon-180x180.png">
			<link rel="icon" type="image/png" sizes="192x192" href="<?= $installPath; ?>/android-icon-192x192.png">
			<link rel="icon" type="image/png" sizes="32x32" href="<?= $installPath; ?>/favicon-32x32.png">
			<link rel="icon" type="image/png" sizes="96x96" href="<?= $installPath; ?>/favicon-96x96.png">
			<link rel="icon" type="image/png" sizes="16x16" href="<?= $installPath; ?>/favicon-16x16.png">
			<meta name="msapplication-TileColor" content="#593">
			<meta name="msapplication-TileImage" content="<?= $installPath; ?>/ms-icon-144x144.png">
			<meta name="theme-color" content="#593">
		</head>

		<body>
			<header>
				<span class=back>
					<a href="<?= $installPath; ?>/" title="Go to main menu"><span>home</span></a>
				</span>
				<span class=actions>
				</span>
			</header>
			<main role=main>
				<?php
				static::MainContent();
				?>
			</main>
			<footer>
				<div id=copyright>© 2017 - 2025 <?= self::siteTitleFull; ?></div>
			</footer>
		</body>

		</html>
<?php
	}
}
