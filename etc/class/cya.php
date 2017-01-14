<?php
set_include_path(__DIR__ . ':' . __DIR__ . '/banks');

define('INSTALL_PATH', dirname(dirname(substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])))));

ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

if(@include_once dirname($_SERVER['DOCUMENT_ROOT']) . '/.cyaKeys.php') {
  $db = @new mysqli(cyaKeysDB::HOST, cyaKeysDB::USER, cyaKeysDB::PASS, cyaKeysDB::NAME);
  if(!$db->connect_errno) {
    $db->real_query('set names \'utf8mb4\'');
    $db->set_charset('utf8mb4');
    if($config = $db->query('select * from config limit 1')) {
      if($config = $config->fetch_object()) {
        if(!IsSetup() && ($config->structureVersion < cyaVersion::Structure || $config->dataVersion < cyaVersion::Data))
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

function IsSetup() {
  return strpos($_SERVER['PHP_SELF'], '/setup.php') !== false;
}

function GoSetup() {
  header('Location: ' . $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname(substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])))) . '/setup.php');
  die;
}

function __autoload($class) {
  switch($class) {
    case 'cyaAjax':
      require_once 'cyaAjax.php';
      break;
    case 'cyaFormat':
      require_once 'cyaFormat.php';
      break;
    case 'cyaHtml':
      require_once 'cyaHtml.php';
      break;
    case 'cyaVersion':
      require_once 'cyaVersion.php';
      break;
  }
}
?>
