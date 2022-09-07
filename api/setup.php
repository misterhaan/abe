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
	protected static function configureDatabaseAction(abeAjax $ajax) {
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
	protected static function createDatabaseAction(abeAjax $ajax) {
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
	protected static function installDatabaseAction(abeAjax $ajax) {
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
			if(self::ImportBanks($db, $ajax)) {
				self::ImportAccountTypes($db, $ajax);
				if(!$ajax->Data->fail)
					if(self::SetDataVersion(abeVersion::Data, $db, $ajax))
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
	protected static function levelAction(abeAjax $ajax) {
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
	protected static function upgradeDatabaseAction(abeAjax $ajax) {
		$db = self::RequireDatabaseWithConfig($ajax);
		$db->autocommit(false);  // each step should commit only if the entire step succeeds
		if($db->config->structureVersion < abeVersion::Structure)
			if(!self::UpgradeDatabaseStructure($db, $ajax))
				return;
		if($db->config->dataVersion < abeVersion::Data)
			self::UpgradeDatabaseData($db, $ajax);
	}

	/**
	 * Import bank definitions into the database.  Part of InstallDatabase() but
	 * can also be called for data upgrades because it will only import new banks.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 */
	private static function ImportBanks(mysqli $db, abeAjax $ajax) {
		if(false !== $f = fopen(dirname(__DIR__) . '/etc/db/data/banks.csv', 'r'))
			if($ins = $db->prepare('insert into banks (class, name, url) select * from (select ? as class, ? as name, ? as url) as b where not exists (select class from banks where class=?) limit 1'))
				if($ins->bind_param('ssss', $class, $name, $url, $class)) {
					while(list($class, $name, $url) = fgetcsv($f))
						if(!$ins->execute())
							$ajax->Fail('Error importing bank:  ' . $ins->error);
					$ins->close();
					return !$ajax->Data->fail;
				} else
					$ajax->Fail('Error binding bank import parameters:  ' . $ins->error);
			else
				$ajax->Fail('Database error preparing to import banks:  ' . $db->error);
		else
			$ajax->Fail('Unable to read banks data file.');
		return false;
	}

	/**
	 * Import account types into the database.  Part of InstallDatabase().
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 */
	private static function ImportAccountTypes(mysqli $db, abeAjax $ajax) {
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
	private static function UpgradeDatabaseStructure(mysqli $db, abeAjax $ajax) {
		if($db->config->structureVersion < abeStructureVersion::Bookmarks
			&& !self::UpgradeDatabaseStructureStep(abeStructureVersion::Bookmarks, $db, $ajax, 'tables/bookmarks')
		)
			return false;

		if($db->config->structureVersion < abeStructureVersion::Duplicates
			&& !self::UpgradeDatabaseStructureStep(abeStructureVersion::Duplicates, $db, $ajax, 'routines/IsDuplicateTransaction')
		)
			return false;

		if($db->config->structureVersion < abeStructureVersion::SummaryProcedures
			&& !self::UpgradeDatabaseStructureStep(abeStructureVersion::SummaryProcedures, $db, $ajax,
				'routines/GetMonthlyCategorySpending',
				'routines/GetYearlyCategorySpending'
		))
			return false;

		if($db->config->structureVersion < abeStructureVersion::CategoryGroups
			&& !self::UpgradeDatabaseStructureStep(abeStructureVersion::CategoryGroups, $db, $ajax,
				'tables/category_groups',
				'transitions/5-categories',
				'routines/GetMonthlyCategorySpending',
				'routines/GetYearlyCategorySpending'
		))
			return false;

		if($db->config->structureVersion < abeStructureVersion::Funds
			&& !self::UpgradeDatabaseStructureStep(abeStructureVersion::Funds, $db, $ajax, 'tables/funds')
		)
			return false;

		if($db->config->structureVersion < abeStructureVersion::Budgets
			&& !self::UpgradeDatabaseStructureStep(abeStructureVersion::Budgets, $db, $ajax,
				'tables/budget_categories',
				'tables/budget_funds',
				'views/spending_monthly',
				'views/spending_yearly',
				'routines/GetBudget',
				'routines/GetLastFullMonth',
				'routines/GetMonthlyCategoryAverage',
				'transitions/7-budgets',
				'routines/GetMonthlyCategorySpending',
				'routines/GetYearlyCategorySpending'
		))
			return false;

		if($db->config->structureVersion < abeStructureVersion::MySqlStrict
			&& !self::UpgradeDatabaseStructureStep(abeStructureVersion::MySqlStrict, $db, $ajax,
				'transitions/8-mysql-strict',
				'views/spending_monthly',
				'routines/GetLastFullMonth',
				'routines/GetMonthlyCategorySpending',
				'routines/GetTransactions',
				'routines/GetYearlyCategorySpending'
		))
			return false;
		// add more upgrades here (older ones need to go first)
		return true;
	}

	private static function UpgradeDatabaseStructureStep(int $version, mysqli $db, abeAjax $ajax, string ...$queryfiles) {
		foreach($queryfiles as $file)
			if(!self::RunQueryFile($file, $db, $ajax))
				return false;
		if(self::SetStructureVersion($version, $db, $ajax)) {
			$db->commit();
			return true;
		}
		return false;
	}

	/**
	 * Upgrade database data and update the data version.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return boolean True if successful
	 */
	private static function UpgradeDatabaseData(mysqli $db, abeAjax $ajax) {
		// the older data version UsBank does the same thing as CapitalOne and works to do both steps at once, so it's not listed here explicitly
		if($db->config->dataVersion < abeDataVersion::CapitalOne) {
			if(self::ImportBanks($db, $ajax) && self::SetDataVersion(abeDataVersion::CapitalOne, $db, $ajax))
				$db->commit();
			else
				return false;
		}
		return true;
		// add future data upgrades here (older ones need to go first)
	}

	/**
	 * Load a query from a file and run it.
	 * @param string $filepath File subdirectory and name without extension.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return bool True if successful
	 */
	private static function RunQueryFile(string $filepath, mysqli $db, abeAjax $ajax) {
		$sql = trim(file_get_contents(dirname(__DIR__) . '/etc/db/' . $filepath . '.sql'));
		if(substr($filepath, 0, 12) == 'transitions/') {  // transitions usually have more than one query
			if($db->multi_query($sql)) {
				while($db->next_result());  // these queries don't return results but we need to get past them to continue
				return true;
			}
		} else {
			if(substr($sql, -1) == ';')
				$sql = substr($sql, 0, -1);
			if($db->real_query($sql))
				return true;
		}
		$ajax->Fail('Error running query file ' . $filepath . ':  ' . $db->errno . ' ' . $db->error);
		return false;
	}

	/**
	 * Load multiple queries from a file and run them.
	 * @param string $filepath File subdirectory and name without extension.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return bool True if successful
	 */
	private static function RunMultiQueryFile(string $filepath, mysqli $db, abeAjax $ajax) {
		$sql = trim(file_get_contents(dirname(__DIR__) . '/etc/db/' . $filepath . '.sql'));
		if($db->multi_query($sql))
			return true;
		$ajax->Fail('Error running query file ' . $filepath . ':  ' . $db->errno . ' ' . $db->error);
		return false;
	}

	/**
	 * Sets the structure version to the provided value.  Use this after making
	 * database structure upgrades.
	 * @param int $ver Structure version to set (use a constant from abeStructureVersion)
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return bool True if successful
	 */
	private static function SetStructureVersion(int $ver, mysqli $db, abeAjax $ajax) {
		if($update = $db->prepare('update config set structureVersion=? limit 1'))
			if($update->bind_param('i', $ver))
				if($update->execute()) {
					$update->close();
					$db->config->structureVersion = $ver;
					return true;
				} else
					$ajax->Fail('Error executing query to set structure version to ' . $ver . ':  ' . $update->errno . ' ' . $update->error);
			else
				$ajax->Fail('Error binding parameter to set structure version to ' . $ver . ':  ' . $update->errno . ' ' . $update->error);
		else
			$ajax->Fail('Error preparing to set structure version to ' . $ver . ':  ' . $db->errno . ' ' . $db->error);
		return false;
	}

	/**
	 * Sets the data version to the provided value.  Use this after making
	 * database data upgrades.
	 * @param int $ver Data version to set (use a constant from abeDataVersion)
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return bool True if successful
	 */
	private static function SetDataVersion(int $ver, mysqli $db, abeAjax $ajax) {
		if($update = $db->prepare('update config set dataVersion=? limit 1'))
			if($update->bind_param('i', $ver))
				if($update->execute()) {
					$update->close();
					$db->config->dataVersion = $ver;
					return true;
				} else
					$ajax->Fail('Error executing query to set data version to ' . $ver . ':  ' . $update->errno . ' ' . $update->error);
			else
				$ajax->Fail('Error binding parameter to set data version to ' . $ver . ':  ' . $update->errno . ' ' . $update->error);
		else
			$ajax->Fail('Error preparing to set data version to ' . $ver . ':  ' . $db->errno . ' ' . $db->error);
		return false;
	}
}
SetupApi::Respond();
