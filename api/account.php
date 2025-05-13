<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for account API requests.
 * @author misterhaan
 */
class AccountApi extends Api {
	/**
	 * Return the documentation for the account API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'list', 'Get the list of accounts.');

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'types', 'Gets available account types and supported banks.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'add', 'Add a new account.', 'multipart');
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'Name of account.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('bank', 'integer', 'ID of the bank of the account.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('type', 'integer', 'ID of the type of the account.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('balance', 'float', 'Current account balance.  Default zero.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('closed', 'boolean', 'Whether the account is closed.  Default false (not closed)');

		$endpoints[] = $endpoint = new EndpointDocumentation('PUT', 'save', 'Save changes to an account.', 'multipart');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the account being saved.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'Name of account.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('bank', 'integer', 'ID of the bank of the account.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('type', 'integer', 'ID of the type of the account.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('balance', 'float', 'Current account balance.  Default zero.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('closed', 'boolean', 'Whether the account is closed.  Default false (not closed)');

		return $endpoints;
	}

	/**
	 * Get the list of all accounts.
	 */
	protected static function GET_list() {
		$db = self::RequireLatestDatabase();
		$select = <<<ACCTS
			select a.id, a.name, a.closed, at.id as type, at.class as typeClass, b.id as bank, b.name as bankName, b.url as bankUrl, a.balance, max(t.posted) as newestSortable, date_format(max(t.posted), '%b %D') as newestDisplay
			from accounts as a
				left join banks as b on b.id=a.bank
				left join account_types as at on at.id=a.account_type
				left join transactions as t on t.account=a.id
			group by a.id
ACCTS;
		try {
			$select = $db->prepare($select);
			$select->execute();
			$select->bind_result($id, $name, $closed, $type, $typeClass, $bank, $bankName, $bankURL, $balance, $newestSortable, $newestDisplay);
			$accounts = [];
			while ($select->fetch())
				$accounts[] = new Account($id, $name, $closed, $type, $typeClass, $bank, $bankName, $bankURL, $balance, $newestSortable, $newestDisplay);
			$select->close();
			self::Success($accounts);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up account list', $mse);
		}
	}

	/**
	 * Get the lists of available account types and supported banks.
	 */
	protected static function GET_types() {
		$db = self::RequireLatestDatabase();
		$result = new AccountTypesAndBanks();
		$result->Banks[] = new Bank(0, '', '');

		try {
			$select = $db->prepare('select id, name, url from banks order by name');
			$select->execute();
			$select->bind_result($id, $name, $url);
			while ($select->fetch())
				$result->Banks[] = new Bank($id, $name, $url);
			$select->close();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up supported banks', $mse);
		}

		try {
			$select = $db->prepare('select id, name, class from account_types order by name');
			$select->execute();
			$select->bind_result($id, $name, $class);
			while ($select->fetch())
				$result->Types[] = new AccountType($id, $name, $class);
			$select->close();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up account types', $mse);
		}

		self::Success($result);
	}

	/**
	 * Add a new account.
	 */
	protected static function POST_add() {
		if (!isset($_POST['name'], $_POST['type'], $_POST['bank']) || !($name = trim($_POST['name'])) || !($type = +$_POST['type']) || !($bank = +$_POST['bank']))
			self::NeedMoreInfo('Parameters name, type, and bank are required.');
		$balance = isset($_POST['balance']) ? +$_POST['balance'] : 0;
		$closed = isset($_POST['closed']) && $_POST['closed'] && $_POST['closed'] != 'false';
		$db = self::RequireLatestDatabase();
		try {
			$insert = $db->prepare('insert into accounts (name, account_type, bank, balance, closed) values (?, ?, ?, ?, ?)');
			$insert->bind_param('siidi', $name, $type, $bank, $balance, $closed);
			$insert->execute();
			$id = $insert->insert_id;
			$insert->close();
			self::Success($id);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error saving account', $mse);
		}
	}

	/**
	 * Save changes to an account.
	 */
	protected static function PUT_save(array $params) {
		$id = +array_shift($params);
		if (!$id || !isset($_POST['name'], $_POST['type'], $_POST['bank']) || !($name = trim($_POST['name'])) || !($type = +$_POST['type']) || !($bank = +$_POST['bank']))
			self::NeedMoreInfo('Parameters id, name, type, and bank are required.');
		$balance = isset($_POST['balance']) ? +$_POST['balance'] : 0;
		$closed = isset($_POST['closed']) && $_POST['closed'] ? 1 : 0;
		$db = self::RequireLatestDatabase();
		try {
			$update = $db->prepare('update accounts set name=?, account_type=?, bank=?, balance=?, closed=? where id=? limit 1');
			$update->bind_param('siidii', $name, $type, $bank, $balance, $closed, $id);
			$update->execute();
			self::Success($id);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error saving account', $mse);
		}
	}
}

class Account {
	public int $ID;
	public string $Name;
	public bool $Closed;
	public AccountType $Type;
	public Bank $Bank;
	public float $Balance;
	public string $BalanceDisplay;
	public ?string $NewestSortable;
	public ?string $NewestDisplay;

	public function __construct(int $id, string $name, bool $closed, int $type, string $typeClass, int $bank, string $bankName, string $bankURL, float $balance, ?string $newestSortable, ?string $newestDisplay) {
		$this->ID = $id;
		$this->Name = $name;
		$this->Closed = $closed;
		$this->Type = new AccountType($type, null, $typeClass);
		$this->Bank = new Bank($bank, $bankName, $bankURL);
		$this->Balance = $balance;
		require_once 'format.php';
		$this->BalanceDisplay = Format::Amount($balance);
		$this->NewestSortable = $newestSortable;
		$this->NewestDisplay = $newestDisplay;
	}
}

class AccountType {
	public int $ID;
	public ?string $Name;
	public string $Class;

	public function __construct(int $id, ?string $name, string $class) {
		$this->ID = $id;
		$this->Name = $name;
		$this->Class = $class;
	}
}

class Bank {
	public int $ID;
	public string $Name;
	public string $URL;

	public function __construct(int $id, string $name, string $url) {
		$this->ID = $id;
		$this->Name = $name;
		$this->URL = $url;
	}
}

class AccountTypesAndBanks {
	public array $Types = [];
	public array $Banks = [];
}

AccountApi::Respond();
