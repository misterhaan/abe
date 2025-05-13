<?php

/**
 * Base class for API controllers.  Controllers should provide the
 * ShowDocumentation function as well as any ___Action functions they want to
 * provide.  Requests are formed as [controller]/[method] and served by a
 * function named [method]Action in the abeApi class in [controller].php.
 * @author misterhaan
 */
abstract class abeApi {
	/**
	 * Respond to an API request or show API documentation.
	 */
	public static function Respond() {
		if (isset($_SERVER['PATH_INFO']) && substr($_SERVER['PATH_INFO'], 0, 1) == '/') {
			$ajax = new abeAjax();
			$method = substr($_SERVER['PATH_INFO'], 1);
			if (false === strpos($method, '/')) {
				$method .= 'Action';
				if (method_exists(static::class, $method))
					static::$method($ajax);
				else
					$ajax->Fail('Requested method does not exist.');
			} else
				$ajax->Fail('Invalid request.');
			$ajax->Send();
		} else {
			$html = new abeHtml();
			$name = substr($_SERVER['SCRIPT_NAME'], strlen(INSTALL_PATH) + 5, -4);  // five for '/api/' and -4 for '.php'
			$html->Open($name . ' API');
?>
			<h1><?= $name; ?> API</h1>
<?php
			static::ShowDocumentation($html);
			$html->Close();
		}
	}

	/**
	 * Gets the database connection object.  Redirects to setup if unable to
	 * connect for any reason.  APIs other than setup should use
	 * RequireLatestDatabase instead.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return mysqli Database connection object.
	 */
	protected static function RequireDatabase(abeAjax $ajax) {
		if (@include_once dirname(DOCROOT) . '/.abeKeys.php') {
			$db = @new mysqli(abeKeysDB::HOST, abeKeysDB::USER, abeKeysDB::PASS, abeKeysDB::NAME);
			if (!$db->connect_errno) {
				// not checking for failure here because it's probably okay to keep going
				$db->real_query('set names \'utf8mb4\'');
				$db->set_charset('utf8mb4');
				return $db;
			} else
				self::FailToSetup($ajax, 'Error connecting to database:  ' . $db->connect_errno . ' ' . $db->connect_error);
		} else
			self::FailToSetup($ajax, 'Database connection details not specified.');
	}

	/**
	 * Gets the database connection object along with the configuration record.
	 * Redirects to setup if anything is missing.  APIs other than setup should
	 * use RequireLatestDatabase instead.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return mysqli Database connection object.
	 */
	protected static function RequireDatabaseWithConfig(abeAjax $ajax) {
		$db = self::RequireDatabase($ajax);
		if ($config = $db->query('select * from config limit 1'))
			if ($config = $config->fetch_object()) {
				$db->config = $config;
				return $db;
			} else
				self::FailToSetup($ajax, 'Configuration not specified in database.');
		else
			self::FailToSetup($ajax, 'Error loading configuration from database:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Gets the database connection object, making sure it's on the latest
	 * version.  Redirects to setup if anything is missing.  If this function
	 * returns at all, it's safe to use the database connection object.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return mysqli Database connection object.
	 */
	protected static function RequireLatestDatabase(abeAjax $ajax) {
		$db = self::RequireDatabaseWithConfig($ajax);
		if ($db->config->structureVersion >= Version::Structure && $db->config->dataVersion >= Version::Data)
			return $db;
		else
			self::FailToSetup($ajax, 'Database upgrade required.');
	}

	/**
	 * Return an error message and redirect to setup to perform any required
	 * updates.  Stops execution of the current script.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @param string $message Error message to report.
	 */
	private static function FailToSetup(abeAjax $ajax, string $message) {
		$ajax->Fail($message);
		$ajax->Data->redirect = "setup.html";
		$ajax->Send();
		die;
	}

	/**
	 * Write out the documentation for the API controller.  The page is already
	 * opened with an h1 header, and will be closed after the call completes.
	 */
	protected abstract static function ShowDocumentation();
}
