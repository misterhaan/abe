<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for setup API requests.
 * @author misterhaan
 */
class SetupApi extends Api {
	/**
	 * Return the documentation for the setup API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'level', 'Get the current setup level.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'configureDatabase', 'Save database connection configuration.', 'multipart');
		$endpoint->BodyParameters[] = new ParameterDocumentation('host', 'string', 'MySQL hostname. Usually, "localhost" is correct here.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'MySQL database name.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('user', 'string', 'Username of the database owner.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('pass', 'string', 'Password for the database owner account.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'createDatabase', 'Create the database and grant access to the configured user.', 'multipart');
		$endpoint->BodyParameters[] = new ParameterDocumentation('rootpw', 'string', 'Password for the MySQL root user. Value is only used to complete this operation and is not stored.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'installDatabase', 'Install the database at the latest version.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'upgradeDatabase', 'Upgrade the database to the latest version.');

		return $endpoints;
	}

	/**
	 * Get the current setup level.
	 */
	protected static function GET_level(): void {
		Env::Initialize();
		if (!file_exists(dirname(Env::$DocRoot) . '/.abeKeys.php'))
			self::Success(-4);
		require_once(dirname(Env::$DocRoot) . '/.abeKeys.php');
		$db = @new mysqli(abeKeysDB::HOST, abeKeysDB::USER, abeKeysDB::PASS, abeKeysDB::NAME);
		if (!$db || $db->connect_errno)
			self::Success(-3);
		$config = $db->query('select * from config limit 1');
		if (!$config || !($config = $config->fetch_object()))
			self::Success(-2);
		require_once 'version.php';
		if ($config->structureVersion < Version::Structure || $config->dataVersion < Version::Data)
			self::Success(-1);
		self::Success(0);
	}

	/**
	 * Configure the database connection.
	 */
	protected static function POST_configureDatabase(): void {
		if (
			!isset($_POST['host'], $_POST['name'], $_POST['user'], $_POST['pass']) || !($host = trim($_POST['host']))
			|| !($name = trim($_POST['name'])) || !($user = trim($_POST['user'])) || !($pass = $_POST['pass'])
		)
			self::NeedMoreInfo('Parameters “host,” “name,” “user,” and “pass” are all required and cannot be blank.');
		Env::Initialize();
		if (!$fh = fopen(dirname(Env::$DocRoot) . '/.abeKeys.php', 'w'))
			self::NeedMoreInfo('Unable to open database connection file for writing:  ' . error_get_last()["message"]);
		fwrite($fh, '<?php
class abeKeysDB {
	const HOST = \'' . addslashes($_POST['host']) . '\';
	const NAME = \'' . addslashes($_POST['name']) . '\';
	const USER = \'' . addslashes($_POST['user']) . '\';
	const PASS = \'' . addslashes($_POST['pass']) . '\';
}');
		fclose($fh);
		self::Success();
	}

	/**
	 * Create the database and grant access to the configured user.
	 */
	protected static function POST_createDatabase(): void {
		if (!isset($_POST['rootpw']) || !($rootpw = trim($_POST['rootpw'])))
			self::NeedMoreInfo('Password is required.');
		Env::Initialize();
		require_once dirname(Env::$DocRoot) . '/.abeKeys.php';
		$rdb = @new mysqli(abeKeysDB::HOST, 'root', $rootpw);
		if ($rdb->errno)
			self::NeedMoreInfo('Unable to connect to database as root with the supplied password:  ' . $rdb->errno . ' ' . $rdb->error);
		try {
			$rdb->real_query('set names \'utf8mb4\'');
			$rdb->set_charset('utf8mb4');
			$rdb->real_query('create database if not exists `' . $rdb->escape_string(abeKeysDB::NAME) . '` character set utf8mb4 collate utf8mb4_unicode_ci');
			$rdb->real_query('grant all on `' . $rdb->escape_string(abeKeysDB::NAME) . '`.* to \'' . $rdb->escape_string(abeKeysDB::USER) . '\'@\'localhost\' identified by \'' . $rdb->escape_string(abeKeysDB::PASS) . '\'');
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error creating database', $mse);
		}
	}

	/**
	 * Install the database at the latest version.
	 */
	protected static function POST_installDatabase(): void {
		$db = self::RequireDatabase();
		// tables, views, routines; then alphabetical order.  if anything has
		// dependencies that come later, it comes after its last dependency.
		$files = [
			'tables/account_types',
			'tables/banks',
			'tables/accounts',
			'tables/bookmarks',
			'tables/category_groups',
			'tables/categories',
			'tables/funds',
			'tables/transactions',
			'tables/splitcats',
			'tables/config',
			'routines/GetCategoryID',
			'routines/GetMonthlyCategorySpending',
			'routines/GetTransactions',
			'routines/GetYearlyCategorySpending',
			'routines/IsDuplicateTransaction'
		];
		try {
			$db->begin_transaction();  // no partial database installations
			foreach ($files as $file)
				self::RunQueryFile($file, $db);

			require_once 'version.php';
			$structureVersion = Version::Structure;
			$insert = $db->prepare('insert into config (structureVersion) values (?)');
			$insert->bind_param('i', $structureVersion);
			$insert->execute();
			$insert->close();

			self::ImportBanks($db);
			self::ImportAccountTypes($db);
			self::SetDataVersion(Version::Data, $db);

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error installing database', $mse);
		}
	}

	/**
	 * Run all applicable upgrade scripts to bring the database up to the current version.
	 */
	protected static function POST_upgradeDatabase(): void {
		$db = self::RequireDatabaseWithConfig();
		require_once 'version.php';
		if ($db->StructureVersion < Version::Structure)
			self::UpgradeDatabaseStructure($db);
		if ($db->DataVersion < Version::Data)
			self::UpgradeDatabaseData($db);
		self::Success();
	}

	/**
	 * Import bank definitions into the database.  Part of InstallDatabase() but
	 * can also be called for data upgrades because it will only import new banks.
	 * @param mysqli $db Database connection object.
	 */
	private static function ImportBanks(mysqli $db): void {
		if (false === $f = fopen(dirname(__DIR__) . '/etc/db/data/banks.csv', 'r'))
			self::NeedMoreInfo('Unable to read banks data file.');
		try {
			$insert = $db->prepare('insert into banks (class, name, url) select * from (select ? as class, ? as name, ? as url) as b where not exists (select class from banks where class=?) limit 1');
			$insert->bind_param('ssss', $class, $name, $url, $class);
			while (list($class, $name, $url) = fgetcsv($f))
				$insert->execute();
			$insert->close();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error importing banks', $mse);
		} finally {
			fclose($f);
		}
	}

	/**
	 * Import account types into the database.  Part of InstallDatabase().
	 * @param mysqli $db Database connection object.
	 */
	private static function ImportAccountTypes(mysqli $db): void {
		if (false === $f = fopen(dirname(__DIR__) . '/etc/db/data/account_types.csv', 'r'))
			self::NeedMoreInfo('Unable to read account types data file.');
		try {
			$insert = $db->prepare('insert into account_types (name, class) select * from (select ? as name, ? as class) as a where not exists (select class from account_types where name=?) limit 1');
			$insert->bind_param('sss', $name, $class, $name);
			while (list($name, $class) = fgetcsv($f))
				$insert->execute();
			$insert->close();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error importing account types', $mse);
		} finally {
			fclose($f);
		}
	}

	/**
	 * Upgrade database structure and update the structure version.
	 * @param DatabaseWithConfig $db Database connection object.
	 */
	private static function UpgradeDatabaseStructure(DatabaseWithConfig $db): void {
		if ($db->StructureVersion < StructureVersion::Bookmarks)
			self::UpgradeDatabaseStructureStep(StructureVersion::Bookmarks, $db->DB, 'tables/bookmarks');
		if ($db->StructureVersion < StructureVersion::Duplicates)
			self::UpgradeDatabaseStructureStep(StructureVersion::Duplicates, $db->DB, 'routines/IsDuplicateTransaction');
		if ($db->StructureVersion < StructureVersion::SummaryProcedures)
			self::UpgradeDatabaseStructureStep(StructureVersion::SummaryProcedures, $db->DB, 'routines/GetMonthlyCategorySpending', 'routines/GetYearlyCategorySpending');
		if ($db->StructureVersion < StructureVersion::CategoryGroups)
			self::UpgradeDatabaseStructureStep(StructureVersion::CategoryGroups, $db->DB, 'tables/category_groups', 'transitions/5-categories', 'routines/GetMonthlyCategorySpending', 'routines/GetYearlyCategorySpending');
		if ($db->StructureVersion < StructureVersion::Funds)
			self::UpgradeDatabaseStructureStep(StructureVersion::Funds, $db->DB, 'tables/funds');
		if ($db->StructureVersion < StructureVersion::Budgets)
			self::UpgradeDatabaseStructureStep(StructureVersion::Budgets, $db->DB, 'tables/budget_categories', 'tables/budget_funds', 'views/spending_monthly', 'views/spending_yearly', 'routines/GetBudget', 'routines/GetLastFullMonth', 'routines/GetMonthlyCategoryAverage', 'transitions/7-budgets', 'routines/GetMonthlyCategorySpending', 'routines/GetYearlyCategorySpending');
		if ($db->StructureVersion < StructureVersion::MySqlStrict)
			self::UpgradeDatabaseStructureStep(StructureVersion::MySqlStrict, $db->DB, 'transitions/8-mysql-strict', 'views/spending_monthly', 'routines/GetLastFullMonth', 'routines/GetMonthlyCategorySpending', 'routines/GetTransactions', 'routines/GetYearlyCategorySpending');
		if ($db->StructureVersion < StructureVersion::TransactionPaging)
			self::UpgradeDatabaseStructureStep(StructureVersion::TransactionPaging, $db->DB, 'transitions/9-transaction-paging', 'routines/GetTransactions');
		// add more upgrades here (older ones need to go first)
	}

	private static function UpgradeDatabaseStructureStep(int $version, mysqli $db, string ...$queryfiles): void {
		try {
			$db->begin_transaction();
			foreach ($queryfiles as $file)
				self::RunQueryFile($file, $db);
			self::SetStructureVersion($version, $db);
			$db->commit();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error upgrading database structure to version ' . $version, $mse);
		}
	}

	/**
	 * Upgrade database data and update the data version.
	 * @param DatabaseWithConfig $db Database connection object.
	 */
	private static function UpgradeDatabaseData(DatabaseWithConfig $db): void {
		// the older data version UsBank does the same thing as CapitalOne and works to do both steps at once, so it's not listed here explicitly
		if ($db->DataVersion < DataVersion::CapitalOne) {
			$db->DB->begin_transaction();
			self::ImportBanks($db->DB);
			self::SetDataVersion(DataVersion::CapitalOne, $db->DB);
			$db->DB->commit();
		}
		// add future data upgrades here (older ones need to go first)
	}

	/**
	 * Load a query from a file and run it.
	 * @param string $filepath File subdirectory and name without extension.
	 * @param mysqli $db Database connection object.
	 */
	private static function RunQueryFile(string $filepath, mysqli $db): void {
		$sql = trim(file_get_contents(dirname(__DIR__) . '/etc/db/' . $filepath . '.sql'));
		try {
			if (substr($filepath, 0, 12) == 'transitions/') {  // transitions usually have more than one query
				$db->multi_query($sql);
				while ($db->next_result());  // these queries don't return results but we need to get past them to continue
			} else {
				if (substr($sql, -1) == ';')
					$sql = substr($sql, 0, -1);
				$db->real_query($sql);
			}
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error running query file ' . $filepath, $mse);
		}
	}

	/**
	 * Sets the structure version to the provided value.  Use this after making
	 * database structure upgrades.
	 * @param int $ver Structure version to set (use a constant from StructureVersion)
	 * @param mysqli $db Database connection object.
	 */
	private static function SetStructureVersion(int $ver, mysqli $db): void {
		$update = $db->prepare('update config set structureVersion=? limit 1');
		$update->bind_param('i', $ver);
		$update->execute();
		$update->close();
	}

	/**
	 * Sets the data version to the provided value.  Use this after making
	 * database data upgrades.
	 * @param int $ver Data version to set (use a constant from DataVersion)
	 * @param mysqli $db Database connection object.
	 */
	private static function SetDataVersion(int $ver, mysqli $db): void {
		try {
			$update = $db->prepare('update config set dataVersion=? limit 1');
			$update->bind_param('i', $ver);
			$update->execute();
			$update->close();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error setting data version', $mse);
		}
	}
}
SetupApi::Respond();
