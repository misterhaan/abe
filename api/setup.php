<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for setup API requests.
 * @author misterhaan
 */
class SetupApi extends abeApi {
	/**
	 * Write out the documentation for the setup API controller.  The page is
	 * already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
			<h2 id=POSTconfigureDatabase>POST configureDatabase</h2>
			<p>Save database connection configuration.</p>
			<dl class=parameters>
				<dt>host</dt>
				<dd>MySQL hostname.  Required.  Usually, "localhost" is correct here.</dd>
				<dt>name</dt>
				<dd>MySQL database name.  Required.</dd>
				<dt>user</dt>
				<dd>Username of the database owner.  Required.</dd>
				<dt>pass</dt>
				<dd>Password for the database owner account.  Required.</dd>
			</dl>

			<h2 id=POSTcreateDatabase>POST createDatabase</h2>
			<p>Create the database and grant access to the configured user.</p>
			<dl class=parameters>
				<dt>rootpw</dt>
				<dd>
					Password for the MySQL root user.  Required.  Value is only used to
					complete this operation and is not stored.
				</dd>
			</dl>

			<h2 id=POSTinstallDatabase>POST installDatabase</h2>
			<p>Install the database at the latest version.</p>

			<h2 id=GETlevel>GET level</h2>
			<p>Get the current setup level.</p>

			<h2 id=POSTupgradeDatabase>POST upgradeDatabase</h2>
			<p>Upgrade the database to the latest version.</p>
<?php
	}

	/**
	 * Configure the database connection.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function configureDatabaseAction($ajax) {
		if(isset($_POST['host'], $_POST['name'], $_POST['user'], $_POST['pass']) && ($host = trim($_POST['host']))
			&& ($name = trim($_POST['name'])) && ($user = trim($_POST['user'])) && ($pass = $_POST['pass']))
			if($fh = fopen(dirname(DOCROOT) . '/.abeKeys.php', 'w'))
				fwrite($fh, '<?php
class abeKeysDB {
	const HOST = \'' . addslashes($_POST['host']) . '\';
	const NAME = \'' . addslashes($_POST['name']) . '\';
	const USER = \'' . addslashes($_POST['user']) . '\';
	const PASS = \'' . addslashes($_POST['pass']) . '\';
}');
			else
				$ajax->Fail('Unable to open database connection file for writing:  ' . error_get_last()["message"]);
		else
			$ajax->Fail('Parameters host, name, user, and pass are all required and cannot be blank.');
	}

	/**
	 * Create the database and grant access to the configured user.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function createDatabaseAction($ajax) {
		if(isset($_POST['rootpw']) && ($rootpw = trim($_POST['rootpw']))) {
			require_once dirname(DOCROOT) . '/.abeKeys.php';
			$rdb = @new mysqli(abeKeysDB::HOST, 'root', $rootpw);
			if(!$rdb->errno) {
				$rdb->real_query('set names \'utf8mb4\'');
				$rdb->set_charset('utf8mb4');
				if($rdb->real_query('create database if not exists `' . $rdb->escape_string(abeKeysDB::NAME) . '` character set utf8mb4 collate utf8mb4_unicode_ci'))
					if($rdb->real_query('grant all on `' . $rdb->escape_string(abeKeysDB::NAME) . '`.* to \'' . $rdb->escape_string(abeKeysDB::USER) . '\'@\'localhost\' identified by \'' . $rdb->escape_string(abeKeysDB::PASS) . '\''))
						;  // done here!
					else
						$ajax->Fail('Error granting database priveleges.');
				else
					$ajax->Fail('Error creating database.');
			} else
				$ajax->Fail('Unable to connect to database as root with the supplied password:  ' . $rdb->errno . ' ' . $rdb->error);
		} else
			$ajax->Fail('Password is required.');
	}

	/**
	 * Install the database at the latest version.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function installDatabaseAction($ajax) {
		$db = self::RequireDatabase($ajax);
		// tables, views, routines; then alphabetical order.  if anything has
		// dependencies that come later, it comes after its last dependency.
		$files = [
				'tables/account_types', 'tables/banks', 'tables/accounts', 'tables/bookmarks',
				'tables/category_groups', 'tables/categories',
				'tables/funds', 'tables/transactions', 'tables/splitcats',
				'tables/config',
				'routines/GetCategoryID',
				'routines/GetMonthlyCategorySpending', 'routines/GetTransactions',
				'routines/GetYearlyCategorySpending', 'routines/IsDuplicateTransaction'
		];
		$db->autocommit(false);  // no partial database installations
		foreach($files as $file) {
			if(!self::RunQueryFile($file, $db, $ajax)) {
				list($type, $name) = explode('s/', $file);
				$ajax->Fail('Error creating ' . $name . ' ' . $type . ':  ' . $db->errno . ' ' . $db->error);
				return;
			}
		}
		if($db->real_query('insert into config (structureVersion) values (' . +abeVersion::Structure . ')')) {
			self::ImportBanks($db, $ajax);
			if(!$ajax->Data->fail) {
				self::ImportAccountTypes($db, $ajax);
				if(!$ajax->Data->fail)
					if($db->real_query('update config set dataVersion=' . +abeVersion::Data . ' limit 1'))
						$db->commit();
					else
						$ajax->Fail('Error configuring data version.');
			}
		} else
			$ajax->Fail('Error initializing configuration.');
	}

	/**
	 * Get the current setup level.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function levelAction($ajax) {
		if(!file_exists(dirname(DOCROOT) . '/.abeKeys.php')) {
			$ajax->Data->level = -4;
			return;
		}
		require_once(dirname(DOCROOT) . '/.abeKeys.php');
		$db = @new mysqli(abeKeysDB::HOST, abeKeysDB::USER, abeKeysDB::PASS, abeKeysDB::NAME);
		if(!$db || $db->connect_errno) {
			$ajax->Data->level = -3;
			return;
		}
		$config = $db->query('select * from config limit 1');
		if(!$config || !($config = $config->fetch_object())) {
			$ajax->Data->level = -2;
			return;
		}
		if($config->structureVersion < abeVersion::Structure || $config->dataVersion < abeVersion::Data) {
			$ajax->Data->level = -1;
			return;
		}
		$ajax->Data->level = 0;
	}

	/**
	 * Run all applicable upgrade scripts to bring the database up to the current version.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function upgradeDatabaseAction($ajax) {
		$db = self::RequireDatabaseWithConfig($ajax);
		$db->autocommit(false);  // each step should commit only if the entire step succeeds
		if($db->config->structureVersion < abeVersion::Structure)
			if(!self::UpgradeDatabaseStructure($db, $ajax))
				return;
		if($db->config->dataVersion < abeVersion::Data)
			self::UpgradeDatabaseData($db, $ajax);
	}

	/**
	 * Import bank definitions into the database.  Part of InstallDatabase().
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 */
	private static function ImportBanks($db, $ajax) {
		if(false !== $f = fopen(dirname(__DIR__) . '/etc/db/data/banks.csv', 'r'))
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
		else
			$ajax->Fail('Unable to read banks data file.');
	}

	/**
	 * Import account types into the database.  Part of InstallDatabase().
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 */
	private static function ImportAccountTypes($db, $ajax) {
		if(false !== $f = fopen(dirname(__DIR__) . '/etc/db/data/account_types.csv', 'r'))
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
		else
			$ajax->Fail('Unable to read account types data file.');
	}

	/**
	 * Upgrade database structure and update the structure version.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return boolean True if successful
	 */
	private static function UpgradeDatabaseStructure($db, $ajax) {
		if($db->config->structureVersion < abeStructureVersion::Bookmarks)
			if(self::RunQueryFile('tables/bookmarks', $db, $ajax) && self::SetStructureVersion(abeStructureVersion::Bookmarks, $db, $ajax))
				$db->commit();
			else {
				$ajax->Fail('Error upgrading database structure to version ' . abeStructureVersion::Bookmarks . ':  ' . $db->errno . ' ' . $db->error);
				return false;
			}
		if($db->config->structureVersion < abeStructureVersion::Duplicates)
			if(self::RunQueryFile('routines/IsDuplicateTransaction', $db, $ajax) && self::SetStructureVersion(abeStructureVersion::Duplicates, $db, $ajax))
				$db->commit();
			else {
				$ajax->Fail('Error upgrading database structure to version ' . abeStructureVersion::Duplicates . ':  ' . $db->errno . ' ' . $db->error);
				return false;
			}
		if($db->config->structureVersion < abeStructureVersion::SummaryProcedures)
			if(
				self::RunQueryFile('routines/GetMonthlyCategorySpending', $db, $ajax)
				&& self::RunQueryFile('routines/GetYearlyCategorySpending', $db, $ajax)
				&& self::SetStructureVersion(abeStructureVersion::SummaryProcedures, $db, $ajax)
			)
				$db->commit();
			else {
				$ajax->Fail('Error upgrading database structure to version ' . abeStructureVersion::SummaryProcedures . ':  ' . $db->errno . ' ' . $db->error);
				return false;
			}
		if($db->config->structureVersion < abeStructureVersion::CategoryGroups)
			if(
				self::RunQueryFile('tables/category_groups', $db, $ajax)
				&& self::RunQueryFile('transitions/5-categories', $db, $ajax)
				&& self::RunQueryFile('routines/GetMonthlyCategorySpending', $db, $ajax)
				&& self::RunQueryFile('routines/GetYearlyCategorySpending', $db, $ajax)
				&& self::SetStructureVersion(abeStructureVersion::CategoryGroups, $db, $ajax)
			)
				$db->commit();
			else {
				$ajax->Fail('Error upgrading database structure to version ' . abeStructureVersion::CategoryGroups . ':  ' . $db->errno . ' ' . $db->error);
				return false;
			}
		if($db->config->structureVersion < abeStructureVersion::Funds)
			if(self::RunQueryFile('tables/funds', $db, $ajax) && self::SetStructureVersion(abeStructureVersion::Funds, $db, $ajax))
				$db->commit();
			else {
				$ajax->Fail('Error upgrading database structure to version ' . abeStructureVersion::Funds . ':  ' . $db->errno . ' ' . $db->error);
				return false;
			}
		// add more upgrades here (older ones need to go first)
		return true;
	}

	/**
	 * Upgrade database data and update the data version.
	 * @return boolean True if successful
	 */
	private static function UpgradeDatabaseData($ajax) {
		// add future data upgrades here (older ones need to go first)
	}

	/**
	 * Load a query from a file and run it.
	 * @param string $filepath File subdirectory and name without extension.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return bool True if successful
	 */
	private static function RunQueryFile($filepath, $db, $ajax) {
		$sql = trim(file_get_contents(dirname(__DIR__) . '/etc/db/' . $filepath . '.sql'));
		if(substr($sql, -1) == ';')
			$sql = substr($sql, 0, -1);
		if($db->real_query($sql))
			return true;
		$ajax->Fail('Error running query file ' . $filepath . ':  ' . $db->errno . ' ' . $db->error);
		return false;
	}

	/**
	 * Sets the structure version to the provided value.  Use this after making
	 * database structure upgrades.
	 * @param integer $ver Structure version to set (use a constant from abeStructureVersion)
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return bool True if successful
	 */
	private static function SetStructureVersion($ver, $db, $ajax) {
		if($db->real_query('update config set structureVersion=' . +$ver . ' limit 1')) {
			$config->structureVersion = +$ver;
			return true;
		}
		$ajax->Fail('Error setting structure version to ' . $ver . ':  ' . $db->errno . ' ' . $db->error);
		return false;
	}
}
SetupApi::Respond();
