<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for fund API requests.
 * @author misterhaan
 */
class FundApi extends Api {
	/**
	 * Return the documentation for the fund API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'list', 'Get the list of funds.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'add', 'Add a new fund.', 'multipart');
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'Name of the new fund.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('balance', 'float', 'Current balance of the fund.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('target', 'float', 'Target balance for the fund.');

		$endpoints[] = $endpoint = new EndpointDocumentation('PUT', 'save', 'Save changes to a fund.', 'query string');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'int', 'ID of the fund to save.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('name', 'string', 'New name of the fund.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('balance', 'float', 'Current balance of the fund.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('target', 'float', 'Target balance for the fund.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'moveDown', 'Move a fund down in the sort order, switching with the fund after it.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'int', 'ID of the fund to move down.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'moveUp', 'Move a fund up in the sort order, switching with the fund before it.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'int', 'ID of the fund to move up.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'moveTo', 'Move a fund from its current sort position to just before another fund.');
		$endpoint->PathParameters[] = new ParameterDocumentation('moveId', 'int', 'ID of the fund to move.', true);
		$endpoint->PathParameters[] = new ParameterDocumentation('beforeId', 'int', 'ID of the fund the <code>moveId</code> fund should be moved before.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('PATCH', 'close', 'Mark a fund closed, which means setting its balance and target to zero.');
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'int', 'ID of the fund to close.', true);

		return $endpoints;
	}

	/**
	 * Get the list of all funds.
	 */
	protected static function GET_list() {
		$db = self::RequireLatestDatabase();
		try {
			$select = $db->prepare('select id, name, balance, target from funds order by sort');
			$select->execute();
			$select->bind_result($id, $name, $balance, $target);
			$funds = [];
			while ($select->fetch())
				$funds[] = new Fund($id, $name, $balance, $target);
			$select->close();
			self::Success($funds);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up funds', $mse);
		}
	}

	/**
	 * Add a new fund.
	 */
	protected static function POST_add() {
		if (!isset($_POST['name'], $_POST['balance'], $_POST['target']) || !($name = trim($_POST['name'])))
			self::NeedMoreInfo('Parameters “name”, “balance”, and “target” must be provided.');
		$target = round(+$_POST['target'], 2);
		$balance = round(+$_POST['balance'], 2);
		if ($target == 0 && $balance == 0)
			self::NeedMoreInfo('Funds must have nonzero current balance or target.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update funds set sort=sort+1 where balance=0 and target=0');
			$update->execute();
			$update->close();

			$insert = $db->prepare('insert into funds (name, balance, target, sort) values (?, ?, ?, (select coalesce(max(sort), 0) + 1 from (select sort from funds where balance!=0 or target!=0) as f))');
			$insert->bind_param('sdd', $name, $balance, $target);
			$insert->execute();
			$fund = new Fund($insert->insert_id, $name, $balance, $target);
			$insert->close();

			$db->commit();
			self::Success($fund);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error creating fund', $mse);
		}
	}

	/**
	 * Save changes to a fund.
	 */
	protected static function PUT_save(array $params) {
		$id = +array_shift($params);
		$put = self::ParseRequestText();
		if (!$id || !isset($put['name'], $put['balance'], $put['target']) || !($name = trim($put['name'])))
			self::NeedMoreInfo('Parameters “id”, “name”, “balance”, and “target” must be provided and non-empty.');
		$balance = round(+$put['balance'], 2);
		$target = round(+$put['target'], 2);
		if ($balance == 0 && $target == 0)
			self::NeedMoreInfo('Funds must have nonzero current balance or target.');
		$db = self::RequireLatestDatabase();

		try {
			$update = $db->prepare('update funds set name=?, balance=?, target=? where id=? limit 1');
			$update->bind_param('sddi', $name, $balance, $target, $id);
			$update->execute();
			$update->close();
			self::Success(new Fund($id, $name, $balance, $target));
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error saving fund', $mse);
		}
	}

	/**
	 * Action to move a fund down in the sort order.
	 */
	protected static function POST_moveDown(array $params) {
		$id = +array_shift($params);
		if (!$id)
			self::NeedMoreInfo('Required parameter missing or invalid.  Provide a numeric id to move.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update funds set sort=sort-1 where sort=(select sort+1 from (select sort from funds where id=? limit 1) as f) limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$update = $db->prepare('update funds set sort=sort+1 where id=? limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error moving fund down', $mse);
		}
	}

	/**
	 * Action to move a fund up in the sort order.
	 */
	protected static function POST_moveUp(array $params) {
		$id = +array_shift($params);
		if (!$id)
			self::NeedMoreInfo('Required parameter missing or invalid.  Provide a numeric id to move.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update funds set sort=sort+1 where sort=(select sort-1 from (select sort from funds where id=? limit 1) as f) limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$update = $db->prepare('update funds set sort=sort-1 where id=? limit 1');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error moving fund up', $mse);
		}
	}

	/**
	 * Action to move a fund before another fund in the sort order.
	 */
	protected static function POST_moveTo(array $params) {
		$moveid = +array_shift($params);
		$beforeid = +array_shift($params);
		if (!$moveid || !$beforeid)
			self::NeedMoreInfo('Required parameters “moveId” and “beforeId” must be present and nonzero numeric.');

		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update funds set sort=sort-1 where sort>(select sort from (select sort from funds where id=? limit 1) as f)');
			$update->bind_param('i', $moveid);
			$update->execute();
			$update->close();

			$update = $db->prepare('update funds set sort=sort+1 where sort>=(select sort from (select sort from funds where id=? limit 1) as f)');
			$update->bind_param('i', $beforeid);
			$update->execute();
			$update->close();

			$update = $db->prepare('update funds set sort=(select sort-1 from (select sort as newsort from funds where id=? limit 1) as f) where id=? limit 1');
			$update->bind_param('ii', $beforeid, $moveid);
			$update->execute();
			$update->close();

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error moving fund', $mse);
		}
	}

	/**
	 * Close a fund and sort it down to the beginning of the closed funds.
	 */
	protected static function PATCH_close(array $params) {
		$id = +array_shift($params);
		if (!$id)
			self::NeedMoreInfo('Parameter “id” must be provided and non-empty.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update funds set sort=sort-1 where sort>(select sort from (select sort from funds where id=?) as f) and (balance>0 or target>0)');
			$update->bind_param('i', $id);
			$update->execute();
			$update->close();

			$update = $db->prepare('update funds set balance=0, target=0, sort=(select coalesce(max(sort), 0) + 1 from (select sort from funds where (balance>0 or target>0) and id!=?) as f) where id=?');
			$update->bind_param('ii', $id, $id);
			$update->execute();
			$update->close();

			$db->commit();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error closing fund', $mse);
		}
	}
}

class Fund {
	public int $ID;
	public string $Name;
	public float $Balance;
	public float $Target;
	public string $BalanceDisplay;
	public string $TargetDisplay;

	public function __construct(int $id, string $name, float $balance, float $target) {
		$this->ID = $id;
		$this->Name = $name;
		$this->Balance = $balance;
		$this->Target = $target;
		require_once 'format.php';
		$this->BalanceDisplay = Format::Amount($balance);
		$this->TargetDisplay = Format::Amount($target);
	}
}

FundApi::Respond();
