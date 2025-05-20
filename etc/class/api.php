<?php
require_once 'environment.php';

/**
 * Base class for API controllers.  Requests are formed as
 * [controller]/[endpoint] with any required parameters separated by / after
 * the endpoint, and served by a function named [method]_[endpoint] in the Api
 * class in [controller].php.
 * @author misterhaan
 */
abstract class Api {
	/**
	 * Respond to an API request or show API documentation.
	 */
	public static function Respond() {
		if (isset($_SERVER['PATH_INFO']) && substr($_SERVER['PATH_INFO'], 0, 1) == '/') {
			$method = $_SERVER['REQUEST_METHOD'];
			if (in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
				$params = explode('/', substr($_SERVER['PATH_INFO'], 1));
				$method .= '_' . array_shift($params);  // turn the HTTP method and the endpoint into a php method name
				if (method_exists(static::class, $method))
					try {
						static::$method($params);
					} catch (mysqli_sql_exception $mse) {
						self::DatabaseError('Unexpected error', $mse);
					}
				else
					self::NotFound('Requested endpoint does not exist on this controller or requires a different request method.');
			} else
				self::NotFound("Method $method is not supported.");
		} else {
			require_once 'apiDocPage.php';
			new ApiDocPage(static::class);
		}
	}

	/**
	 * Provide documentation for this API when requested without an endpoint.
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public abstract static function GetEndpointDocumentation(): array;

	/**
	 * Read the request body as plain text.  Useful for PUT requests with a single data value.
	 */
	protected static function ReadRequestText(): string {
		$fp = fopen("php://input", "r");
		$text = '';
		while ($data = fread($fp, 1024))
			$text .= $data;
		return $text;
	}

	/**
	 * Parse the request body as a query string.  Useful for PUT requests with multiple data values.
	 */
	protected static function ParseRequestText(): array {
		$rawData = self::ReadRequestText();
		parse_str($rawData, $data);
		return $data;
	}

	/**
	 * Send a successful response.
	 * @param mixed $data Response data (optional)
	 */
	protected static function Success($data = true): void {
		header('Content-Type: application/json');
		die(json_encode($data));
	}

	/**
	 * Mark the request as not found.  This probably only makes sense for get
	 * requests that look up an item by a key.
	 * @param string $message short message describing what was not found
	 */
	protected static function NotFound(string $message = '') {
		http_response_code(404);
		header('Content-Type: text/plain');
		die($message);
	}

	/**
	 * Reject the request because it is missing required information.
	 * @param string $message short message describing what's missing and how to provide it.
	 */
	protected static function NeedMoreInfo(string $message) {
		http_response_code(422);
		header('Content-Type: text/plain');
		die($message);
	}

	/**
	 * Mark the request as encountering a database error.
	 * @param string $message failure reason
	 * @param mysqli_sql_exception $ex thrown error
	 */
	protected static function DatabaseError(string $message, mysqli_sql_exception $ex) {
		http_response_code(500);
		header('Content-Type: text/plain');
		die($message . ':  ' . $ex->getCode() . ' ' . $ex->getMessage());
	}

	/**
	 * Return an error message and redirect to setup to perform any required
	 * updates.  Stops execution of the current script.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @param string $message Error message to report.
	 */
	private static function RedirectToSetup(string $message) {
		header($_SERVER['SERVER_PROTOCOL'] . ' 533 Setup Required');  // this response code is not in the HTTP spec
		header('Content-Type: text/plain');
		header('X-Setup-Location: setup.html');
		die($message);
	}

	/**
	 * Gets the database connection object, making sure it's on the latest
	 * version.  Redirects to setup if anything is missing.  If this function
	 * returns at all, it's safe to use the database connection object.
	 * @return mysqli Database connection object.
	 */
	protected static function RequireLatestDatabase(): mysqli {
		require_once 'version.php';
		$config = self::RequireDatabaseWithConfig();
		if ($config->StructureVersion >= Version::Structure && $config->DataVersion >= Version::Data)
			return $config->DB;
		else
			self::RedirectToSetup('Database upgrade required.');
	}

	/**
	 * Gets the database connection object along with the configuration record.
	 * Redirects to setup if anything is missing.  APIs other than setup should
	 * use RequireLatestDatabase instead.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return mysqli Database connection object.
	 */
	protected static function RequireDatabaseWithConfig(): DatabaseWithConfig {
		$db = self::RequireDatabase();
		if ($config = $db->query('select * from config limit 1'))
			if ($config = $config->fetch_object())
				return new DatabaseWithConfig($db, $config->structureVersion, $config->dataVersion);
			else
				self::RedirectToSetup('Configuration not specified in database.');
		else
			self::RedirectToSetup('Error loading configuration from database:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Gets the database connection object.  Redirects to setup if unable to
	 * connect for any reason.  APIs other than setup should use
	 * RequireLatestDatabase instead.
	 * @param abeAjax $ajax Ajax object for reporting an error.
	 * @return mysqli Database connection object.
	 */
	protected static function RequireDatabase(): mysqli {
		Env::Initialize();
		if (@include_once dirname(Env::$DocRoot) . '/.abeKeys.php') {
			$db = @new mysqli(abeKeysDB::HOST, abeKeysDB::USER, abeKeysDB::PASS, abeKeysDB::NAME);
			if (!$db->connect_errno) {
				// not checking for failure here because it's probably okay to keep going
				$db->real_query('set names \'utf8mb4\'');
				$db->set_charset('utf8mb4');
				return $db;
			} else
				self::RedirectToSetup('Error connecting to database:  ' . $db->connect_errno . ' ' . $db->connect_error);
		} else
			self::RedirectToSetup('Database connection details not specified.');
	}
}

class DatabaseWithConfig {
	public mysqli $DB;
	public int $StructureVersion;
	public int $DataVersion;

	public function __construct(mysqli $db, int $structureVersion, int $dataVersion) {
		$this->DB = $db;
		$this->StructureVersion = $structureVersion;
		$this->DataVersion = $dataVersion;
	}
}
