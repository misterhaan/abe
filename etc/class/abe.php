<?php
// include and require should find files in this directory and its banks subdirectory
set_include_path(__DIR__ . ':' . __DIR__ . '/banks');

// CONTEXT_DOCUMENT_ROOT is set when an alias or similar is used, which makes
// DOCUMENT_ROOT incorrect for this purpose.  assume the presence of an alias
// means we're one level deep.
define('DOCROOT', isset($_SERVER['CONTEXT_PREFIX']) && isset($_SERVER['CONTEXT_DOCUMENT_ROOT']) && $_SERVER['CONTEXT_PREFIX'] ? dirname($_SERVER['CONTEXT_DOCUMENT_ROOT']) : $_SERVER['DOCUMENT_ROOT']);

// find the application path on the webserver for absolute URLs
$install_path = dirname(dirname(substr(__DIR__, strlen(DOCROOT))));
if ($install_path == '/')
	$install_path = '';
define('INSTALL_PATH', $install_path);
unset($install_path);

// PHP should treat strings as UTF8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

/**
 * Called automatically when a class is accessed but isn't available yet.  All
 * the other files in this directory should be set up here.
 * @param string $class Name of the class to load.
 */
spl_autoload_register(function ($class) {
	switch ($class) {
		case 'abeAjax':
			require_once 'abeAjax.php';
			break;
		case 'abeApi':
			require_once 'abeApi.php';
			break;
		case 'Bank':
			require_once 'bank.php';
			break;
		case 'abeFormat':
			require_once 'abeFormat.php';
			break;
		case 'abeHtml':
			require_once 'abeHtml.php';
			break;
		case 'Version':
			require_once 'version.php';
			break;
	}
});
