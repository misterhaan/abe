<?php
require_once __DIR__ . '/etc/class/cya.php';

if(isset($_GET['ajax'])) {
  $ajax = new cyaAjax();
  switch($_GET['ajax']) {
    case 'savedbsetup': CreateDatabaseKeys(); break;
    case 'createdb':    CreateDatabaseMysql(); break;
    case 'installdb':   InstallDatabase(); break;
    case 'upgradedb':   UpgradeDatabase(); break;
  }
  $ajax->Send();
  die;
}
$html = new cyaHtml();
$html->Open('Setup');
?>
      <h1><?php echo cyaHtml::SITE_NAME_FULL; ?> Setup</h1>
<?php
if(!file_exists(dirname($_SERVER['DOCUMENT_ROOT']) . '/.cyaKeys.php'))
  DatabaseForm();
elseif(!$db || $db->connect_errno)
  DatabaseCreateForm();
elseif(!isset($config) || !$config)
  DatabaseInstallForm();
elseif($config->structureVersion < cyaVersion::Structure || $config->dataVersion < cyaVersion::Data)
  DatabaseUpgradeForm();
else
  AllGoodMessage();
$html->Close();

function DatabaseForm() {
?>
      <h2>Database</h2>
      <p>
        <?php echo cyaHtml::SITE_NAME_SHORT; ?> stores data in a MySQL database.
        Set up the connection below:
      </p>
      <form id=dbsetup>
        <label title="Enter the hostname for the database.  Usually the database is the same host as the web server, and the hostname should be 'localhost'">
          <span class=label>Host:</span>
          <span class=field><input name=host value=localhost required></span>
        </label>
        <label title="Enter the name of the database <?php echo cyaHtml::SITE_NAME_SHORT; ?> should use">
          <span class=label>Database:</span>
          <span class=field><input name=name required></span>
        </label>
        <label title="Enter the username that owns the <?php echo cyaHtml::SITE_NAME_SHORT; ?> database">
          <span class=label>Username:</span>
          <span class=field><input name=user required></span>
        </label>
        <label title="Enter the password for the user that owns the <?php echo cyaHtml::SITE_NAME_SHORT; ?> database">
          <span class=label>Password:</span>
          <span class=field><input type=password name=pass required></span>
        </label>
        <label title="Confirm the previously-entered password">
          <span class=label>Confirm:</span>
          <span class=field><input type=password name=confirm required></span>
        </label>
        <button>Save</button>
      </form>
<?php
}

function CreateDatabaseKeys() {
  global $ajax;
  $ajax->Data->fieldIssues = [];
  if(!isset($_POST['host']) || !($_POST['host'] = trim($_POST['host'])))
    $ajax->Data->fieldIssues[] = ['field' => 'host', 'issue' => 'Host is required.'];
  if(!isset($_POST['name']) || !($_POST['name'] = trim($_POST['name'])))
    $ajax->Data->fieldIssues[] = ['field' => 'name', 'issue' => 'Database name is required.'];
  elseif(strpos($_POST['name'], '`') !== false)
    $ajax->Data->fieldIssues[] = ['field' => 'name', 'issue' => 'Database name cannot contain the ` character.'];
  if(!isset($_POST['user']) || !($_POST['user'] = trim($_POST['user'])))
    $ajax->Data->fieldIssues[] = ['field' => 'user', 'issue' => 'Username is required.'];
  if(!isset($_POST['pass']) || !($_POST['pass'] = trim($_POST['pass'])))
    $ajax->Data->fieldIssues[] = ['field' => 'pass', 'issue' => 'Password is required.'];
  if(trim($_POST['pass']) != $_POST['confirm'])
    $ajax->Data->fieldIssues[] = ['field' => 'confirm', 'issue' => 'Passwords do not match.'];
  if(count($ajax->Data->fieldIssues))
    $ajax->Fail(count($ajax->Data->fieldIssues) . ' fields have problems');
  else
    if($fh = fopen(dirname($_SERVER['DOCUMENT_ROOT']) . '/.cyaKeys.php', 'w')) {
      fwrite($fh, '<?php
class cyaKeysDB {
  const HOST = \'' . addslashes($_POST['host']) . '\';
  const NAME = \'' . addslashes($_POST['name']) . '\';
  const USER = \'' . addslashes($_POST['user']) . '\';
  const PASS = \'' . addslashes($_POST['pass']) . '\';
}
?>');
    } else
      $ajax->Fail('Unable to save database connection file');
}

function DatabaseCreateForm() {
?>
      <h2>Create Database</h2>
      <p>
        <?php echo cyaHtml::SITE_NAME_SHORT; ?> can’t connect to the database.
        Usually this is because it hasn’t been created yet.  Enter the password
        for the MySQL root user below to create the database and grant access to
        the configured user:
      </p>
      <form id=dbcreate>
        <label title="Enter the password for the MySQL root user (will not be stored)">
          <span class=label>Password:</span>
          <span class=field><input type=password name=rootpw required></span>
        </label>
        <button>Create</button>
      </form>
<?php
}

function CreateDatabaseMysql() {
  global $ajax;
  if(!isset($_POST['rootpw']) || !($_POST['rootpw'] = trim($_POST['rootpw'])))
    $ajax->Fail('Password is required');
  else {
    $rdb = @new mysqli(cyaKeysDB::HOST, 'root', $_POST['rootpw']);
    if($rdb->errno)
      $ajax->Fail('Unable to connect to database as root.  Is the password correct?');
    else {
      $rdb->real_query('set names \'utf8mb4\'');
      $rdb->set_charset('utf8mb4');
      if($rdb->real_query('create database if not exists `' . cyaKeysDB::NAME . '` character set utf8mb4 collate utf8mb4_unicode_ci'))
        if($rdb->real_query('grant all on `' . cyaKeysDB::NAME . '`.* to \'' . $rdb->escape_string(cyaKeysDB::USER) . '\'@\'localhost\' identified by \'' . $rdb->escape_string(cyaKeysDB::PASS) . '\''))
          ;  // done here!
        else
          $ajax->Fail('Error granting database priveleges.');
      else
        $ajax->Fail('Error creating database.');
    }
  }
}

function DatabaseInstallForm() {
?>
      <h2>Install Database</h2>
      <p>
        It looks like the <?php echo cyaHtml::SITE_NAME_SHORT; ?> database
        hasn’t been installed.
      </p>
      <nav class=calltoaction><a href="?ajax=installdb">Install Database</a></nav>
<?php
}

function InstallDatabase() {
  global $ajax, $db;
  $ajax->Data->tableErrors = [];
  $tabledir = __DIR__ . '/etc/db/tables/';
  // alphabetical except config comes last and tables with foreign keys must come after the tables they reference
  $tables = ['account_types', 'banks', 'accounts', 'categories', 'transactions', 'splitcats', 'config'];
  foreach($tables as $table) {
    $sql = trim(file_get_contents($tabledir . $table . '.sql'));
    if(substr($sql, -1) == ';')
      $sql = substr($sql, 0, -1);
    if(!$db->real_query($sql))
      $ajax->Data->tableErrors[] = ['table' => $table, 'errno' => $db->errno, 'error' => $db->error];
  }
  if(count($ajax->Data->tableErrors))
    $ajax->Fail('Error creating ' . count($ajax->Data->tableErrors) . ' of ' . count($tables) . ' tables.');
  else {
    $ajax->Data->routineErrors = [];
    $routinedir = __DIR__ . '/etc/db/routines/';
    $routines = ['GetCategoryID'];
    foreach($routines as $routine) {
      $sql = trim(file_get_contents($routinedir . $routine . '.sql'));
      if(!$db->real_query($sql))
        $ajax->Data->routineErrors[] = ['routine' => $routine, 'errno' => $db->errno, 'error' => $db->error];
    }
    if(count($ajax->Data->routineErrors))
      $ajax->Fail('Error creating ' . count($ajax->Data->routineErrors) . ' of ' . count($routines) . ' routines.');
    elseif($db->real_query('insert into config (structureVersion) values (' . +cyaVersion::Structure . ')')) {
      ImportBanks();
      ImportAccountTypes();
      if($db->real_query('update config set dataVersion=' . +cyaVersion::Data . ' limit 1'))
        ;  // done here!
      else
        $ajax->Fail('Error configuring data version.');
    } else
      $ajax->Fail('Error initializing configuration.');
  }
}

function ImportBanks() {
  global $ajax, $db;
  if(false !== $f = fopen(__DIR__ . '/etc/db/data/banks.csv', 'r')) {
    $db->real_query('start transaction');
    if($ins = $db->prepare('insert into banks (class, name, url) select * from (select ? as class, ? as name, ? as url) as b where not exists (select class from banks where class=?) limit 1'))
      if($ins->bind_param('ssss', $class, $name, $url, $class)) {
        while(list($class, $name, $url) = fgetcsv($f))
          if(!$ins->execute())
            $ajax->Fail('Error importing bank:  ' . $ins->error);
        $ins->close();
      } else
        $ajax->Fail('Error binding bank import parameters:  ' . $ins->error);
    else
      $ajax->Fail('Database error preparing to import banks:  ' . $db->error);
    $db->real_query('commit');
  } else
    $ajax->Fail('Unable to read banks data file.');
}

function ImportAccountTypes() {
  global $ajax, $db;
  if(false !== $f = fopen(__DIR__ . '/etc/db/data/account_types.csv', 'r')) {
    $db->real_query('start transaction');
    if($ins = $db->prepare('insert into account_types (name, class) select * from (select ? as name, ? as class) as a where not exists (select class from account_types where name=?) limit 1'))
      if($ins->bind_param('sss', $name, $class, $name)) {
        while(list($name, $class) = fgetcsv($f))
          if(!$ins->execute())
            $ajax->Fail('Error importing account type:  ' . $ins->error);
        $ins->close();
      } else
        $ajax->Fail('Error binding account type import parameters:  ' . $ins->error);
    else
      $ajax->Fail('Database error preparing to import account types:  ' . $db->error);
    $db->real_query('commit');
  } else
    $ajax->Fail('Unable to read account types data file.');
}

function DatabaseUpgradeForm() {
?>
      <h2>Upgrade Database</h2>
      <p>
        There have been some additions to <?php echo cyaHtml::SITE_NAME_SHORT; ?>
        since this database was last set up.  An upgrade is needed to activate
        them and keep everything else running smoothly.
      </p>
      <nav class=calltoaction><a href="?ajax=upgradedb">Upgrade Database</a></nav>
<?php
}

function UpgradeDatabase() {
  global $ajax, $db, $config;
  // TODO:  add upgrade code once there have been structure or data changes after a release.
  $ajax->Fail('Not implemented.');
}

function AllGoodMessage() {
?>
      <p>
        The <?php echo cyaHtml::SITE_NAME_SHORT; ?> database is fully up-to-date!
      </p>
      <nav class=calltoaction><a href="<?php echo INSTALL_PATH; ?>/">Let’s Go!</a></nav>
<?php
}
?>
