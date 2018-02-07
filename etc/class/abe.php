<?php
// include and require should find files in this directory and its banks subdirectory
set_include_path(__DIR__ . ':' . __DIR__ . '/banks');

// find the application path on the webserver for absolute URLs
$install_path = dirname(dirname(substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']))));
if($install_path == '/')
	$install_path = '';
define('INSTALL_PATH', $install_path);
unset($install_path);

// PHP should treat strings as UTF8
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// connect to the database and read the configuration, or redirect to the setup script
if(@include_once dirname($_SERVER['DOCUMENT_ROOT']) . '/.abeKeys.php') {
	$db = @new mysqli(abeKeysDB::HOST, abeKeysDB::USER, abeKeysDB::PASS, abeKeysDB::NAME);
	if(!$db->connect_errno) {
		$db->real_query('set names \'utf8mb4\'');
		$db->set_charset('utf8mb4');
		if($config = $db->query('select * from config limit 1')) {
			if($config = $config->fetch_object()) {
				if(!IsSetup() && ($config->structureVersion < abeVersion::Structure || $config->dataVersion < abeVersion::Data))
					GoSetup();
			} elseif(!IsSetup())
				GoSetup();
		} elseif(!IsSetup())
			GoSetup();
	} elseif(!IsSetup())
		GoSetup();
} elseif(!IsSetup())
	GoSetup();
else
	$db = false;

/**
 * Whether the current page is the setup script.
 * @return boolean True if the current page is the setup script.
 */
function IsSetup() {
	return strpos($_SERVER['PHP_SELF'], '/setup.php') !== false;
}

/**
 * Redirect to the setup script.  Make sure IsSetup() is false or this will
 * cause a too many redirects error.
 */
function GoSetup() {
	header('Location: ' . INSTALL_PATH . '/setup.php');
	die;
}

/**
 * Called automatically when a class is accessed but isn't available yet.  All
 * the other files in this directory should be set up here.
 * @param string $class Name of the class to load.
 */
function __autoload($class) {
	switch($class) {
		case 'abeAjax':
			require_once 'abeAjax.php';
			break;
		case 'abeApi':
			require_once 'abeApi.php';
			break;
		case 'abeBank':
			require_once 'abeBank.php';
			break;
		case 'abeFormat':
			require_once 'abeFormat.php';
			break;
		case 'abeHtml':
			require_once 'abeHtml.php';
			break;
		case 'abeVersion':
			require_once 'abeVersion.php';
			break;
	}
}
?>
