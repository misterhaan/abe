<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for budget API requests.
 * @author misterhaan
 */
class BudgetApi extends Api {
	private const MonthRegex = '/^[0-9]{4}\-[0-9]{2}$/';

	/**
	 * Return the documentation for the budget API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'list', 'Get the list of all months that have a budget defined. Months are returned with <code>Sort</code> (YYYY-MM) and <code>Display</code> properties.');

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'active', 'Load an active budget.');
		$endpoint->PathParameters[] = new ParameterDocumentation('month', 'string', 'Month to look up (YYYY-MM format).', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'suggestions', 'Retrieve category totals to help suggest budget values.');
		$endpoint->PathParameters[] = new ParameterDocumentation('month', 'string', 'Month to suggest budget values for (YYYY-MM format).', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('PUT', 'active', 'Create a new budget. Parameters are passed as a JSON object in the request body, not as POST values.', 'query string');
		$endpoint->PathParameters[] = new ParameterDocumentation('month', 'string', 'Month new budget is for (YYYY-MM format).', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('catids', 'array', 'Array of category IDs to include in this budget.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('catamounts', 'array', 'Amounts in this budget as a parallel array to <code>catids</code>. Positive amounts are for spending and negative amounts are for income.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('fundids', 'array', 'Array of fund IDs to include in this budget.');
		$endpoint->BodyParameters[] = new ParameterDocumentation('fundamounts', 'array', 'Amounts that will change as a part of this budget as a parallel array to <code>fundids</code>. Positive amounts mean money going into the fund and negative mean coming out.');

		$endpoints[] = $endpoint = new EndpointDocumentation('POST', 'actual', 'Update the actual value for the specified budget category(ies).', 'multipart');
		$endpoint->PathParameters[] = new ParameterDocumentation('month', 'string', 'Month the budget category belongs to (YYYY-MM format).', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the category(ies) to update. Can pass an array to set multiple in one API call. New categories are added to the budget.', true);
		$endpoint->BodyParameters[] = new ParameterDocumentation('amount', 'float', 'New actual amount for the budget category(ies). Typically negative for income categories and positive for spending categories. Must be an array parallel to <code>id</code> if <code>id</code> is an array, or a single value if <code>id</code> is a single value.', true);

		$endpoints[] = $endpoint = new EndpointDocumentation('PUT', 'actualFund', 'Update the actual value for the specified budget savings fund.', 'float', 'New actual amount for the budget savings fund. Positive amounts raise the fund balance and negative amonuts lower it.');
		$endpoint->PathParameters[] = new ParameterDocumentation('month', 'string', 'Month the budget budget fund item belongs to (YYYY-MM format).', true);
		$endpoint->PathParameters[] = new ParameterDocumentation('id', 'integer', 'ID of the savings fund to update.', true);

		return $endpoints;
	}

	/**
	 * Action to look up months that have a budget defined.
	 */
	protected static function GET_list(): void {
		$db = self::RequireLatestDatabase();
		try {
			$select = $db->prepare('select date_format(month, \'%Y-%m\') as sort, date_format(month, \'%b %Y\') as display from (select month from budget_categories group by month union select month from budget_funds group by month) as m group by month');
			$select->execute();
			$select->bind_result($sort, $display);
			$months = [];
			while ($select->fetch())
				$months[] = new BudgetMonth($sort, $display);
			$select->close();
			self::Success($months);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up list of months that have budgets', $mse);
		}
	}

	/**
	 * Load an active budget.
	 */
	protected static function GET_active(array $params): void {
		$month = trim(array_shift($params));
		if (!$month || !preg_match(self::MonthRegex, $month))
			self::NeedMoreInfo('Parameter “month” is required and must be formatted YYYY-MM.');
		$month .= '-01';
		$db = self::RequireLatestDatabase();
		try {
			$budget = new ActiveBudget();

			// TODO:  also get summary data for this month and combine with this
			$select = $db->prepare('call GetBudget(?)');
			$select->bind_param('s', $month);
			$select->execute();
			$select->bind_result($groupname, $catname, $catid, $planned, $actual, $amount);
			while ($select->fetch())
				$budget->Categories[] = new ActiveBudgetCategory($catid, $catname, $groupname, $planned, $actual, $amount);
			$select->close();

			$select = $db->prepare('select f.name, f.id, b.planned, b.actual from budget_funds as b left join funds as f on f.id=b.fund where month=? order by name');
			$select->bind_param('s', $month);
			$select->execute();
			$select->bind_result($name, $id, $planned, $actual);
			while ($select->fetch())
				$budget->Funds[] = new ActiveBudgetFund($id, $name, $planned, $actual);
			$select->close();

			self::Success($budget);
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up budget details', $mse);
		}
	}

	/**
	 * Action to look up suggestions for a budget.
	 */
	protected static function GET_suggestions(array $params): void {
		$month = trim(array_shift($params));
		if (!$month || !preg_match(self::MonthRegex, $month))
			self::NeedMoreInfo('Parameter “month” is required and must be formatted YYYY-MM.');
		$db = self::RequireLatestDatabase();
		$suggestions = new BudgetSuggestions();
		self::SuggestAverageMonth($db, $suggestions);
		self::SuggestSpecificMonths($db, $suggestions, $month);
		self::Success($suggestions);
	}

	/**
	 * Create a budget.
	 */
	protected static function PUT_active(array $params) {
		$month = trim(array_shift($params));
		if (!$month || !preg_match(self::MonthRegex, $month))
			self::NeedMoreInfo('Parameter “month” is required and must be formatted YYYY-MM.');
		$month .= '-01';

		$data = self::ParseRequestText();

		$catids = isset($data['catids']) ? $data['catids'] : [];
		$catamounts = isset($data['catamounts']) ? $data['catamounts'] : [];
		if (count($catids) != count($catamounts))
			self::NeedMoreInfo('Parameters catids and catamounts must have the same number of array elements.');

		$fundids = isset($data['fundids']) ? $data['fundids'] : [];
		$fundamounts = isset($data['fundamounts']) ? $data['fundamounts'] : [];
		if (count($fundids) != count($fundamounts))
			self::NeedMoreInfo('Parameters fundids and fundamounts must have the same number of array elements.');

		if (count($catids) + count($fundids) <= 0)
			self::NeedMoreInfo('At least one category or fund must have an amount in order to create a budget.');

		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$insert = $db->prepare('insert into budget_categories (month, category, planned) values (?, ?, ?)');
			$insert->bind_param('sid', $month, $catid, $amount);
			for ($c = 0; $c < count($catids); $c++) {
				$catid = $catids[$c];
				$amount = $catamounts[$c];
				$insert->execute();
			}
			$insert->close();

			$insert = $db->prepare('insert into budget_funds (month, fund, planned) values (?, ?, ?)');
			$insert->bind_param('sid', $month, $fundid, $amount);
			for ($f = 0; $f < count($fundids); $f++) {
				$fundid = $fundids[$f];
				$amount = $fundamounts[$f];
				!$insert->execute();
			}
			$insert->close();

			$db->commit();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error creating budget', $mse);
		}
	}

	/**
	 * Update the actual value of a budget category.
	 */
	protected static function POST_actual(array $params): void {
		$month = trim(array_shift($params));
		if (!$month || !preg_match(self::MonthRegex, $month))
			self::NeedMoreInfo('Parameter “month” is required and must be formatted YYYY-MM.');
		$month .= '-01';

		if (!isset($_POST['id'], $_POST['amount']) || !(($multi = is_array($_POST['id'])) || +$_POST['id']) || !is_array($_POST['amount']) == $multi || !(!$multi || count($_POST['id']) == count($_POST['amount'])))
			self::NeedMoreInfo('Parameters id and amount are required and must be numeric or an array of numbers.  If arrays, they must be parallel.');
		$ids = $multi ? $_POST['id'] : [$_POST['id']];
		$amounts = $multi ? $_POST['amount'] : [$_POST['amount']];
		$db = self::RequireLatestDatabase();
		try {
			$update = $db->prepare('insert into budget_categories (month, category, actual) values (?, ?, ?) on duplicate key update actual=?');
			$update->bind_param('sidd', $month, $id, $amount, $amount);
			for ($c = 0; $c < count($ids); $c++) {
				$id = +$ids[$c];
				$amount = +$amounts[$c];
				if ($id)
					$update->execute();
			}
			$update->close();
			self::Success();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error updating budget item balance', $mse);
		}
	}

	/**
	 * Update the actual value a budget adds to a savings fund.
	 */
	protected static function PUT_actualFund(array $params) {
		$month = trim(array_shift($params));
		if (!$month || !preg_match(self::MonthRegex, $month))
			self::NeedMoreInfo('Parameter “month” is required and must be formatted YYYY-MM.');
		$month .= '-01';
		if (!($id = +array_shift($params)))
			self::NeedMoreInfo('Parameter “id” is required and must be numeric.');
		if (!($amount = +self::ReadRequestText()))
			self::NeedMoreInfo('Request body is required and must be numeric.');
		$db = self::RequireLatestDatabase();
		try {
			$db->begin_transaction();

			$update = $db->prepare('update funds set balance=balance+?-(select actual from budget_funds where month=? and fund=?) where id=?');
			$update->bind_param('dsii', $amount, $month, $id, $id);
			$update->execute();
			$update->close();

			$update = $db->prepare('update budget_funds set actual=? where month=? and fund=?');
			$update->bind_param('dsi', $amount, $month, $id);
			$update->execute();
			$update->close();

			$db->commit();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error updating budget fund amount', $mse);
		}
	}

	/**
	 * Load category averages for the past 12 months.
	 * @param mysqli $db Database connection object.
	 * @param BudgetSuggestions $suggestions Budget suggestions object average month will be added to.
	 */
	private static function SuggestAverageMonth(mysqli $db, BudgetSuggestions $suggestions) {
		$suggestions->Columns[] = "Average";
		try {
			$select = $db->prepare('call GetMonthlyCategoryAverage()');
			$select->execute();
			$select->bind_result($amount, $catid, $catname, $groupid, $groupname);
			while ($select->fetch())
				if ($catid)
					$suggestions->Categories[] = new SuggestedBudgetCategory($catid, $catname, $groupname);
			$select->close();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up average of latest 12 months spending', $mse);
		}
	}

	/**
	 * Load category totals for the most recent full month and the specified month a year ago.
	 * @param mysqli $db Database connection object.
	 * @param BudgetSuggestions $suggestions Budget suggestions object specific months will be added to.
	 * @param string $month Month for new budget, YYYY-MM format.
	 */
	private static function SuggestSpecificMonths(mysqli $db, BudgetSuggestions $suggestions, string $month) {
		try {
			$select = $db->prepare('select GetLastFullMonth()');
			$select->execute();
			$select->bind_result($lastfullmonth);
			$select->fetch();
			$select->close();

			$select = $db->prepare('select amount, catid, catname, coalesce(groupname, \'(ungrouped)\') as groupname from spending_monthly where year(month)=? and month(month)=? and not catid is null order by groupname, catname');
			$select->bind_param('ii', $y, $m);
			list($yyyy, $mm) = explode('-', $month);

			// previous month
			$y = +$yyyy;
			$m = $mm - 1;
			if ($m <= 0) {
				$y--;
				$m += 12;
			}
			// if previous month isn't a full month, use last full month
			if (date('Y-m-01', strtotime($y . '-' . $m)) > $lastfullmonth) {
				list($ly, $lm, $ld) = explode('-', $lastfullmonth);
				$y = +$ly;
				$m = +$lm;
			}
			self::SuggestOneMonth($suggestions, $select, $y, $m);

			// this month last year
			$y = $yyyy - 1;
			$m = +$mm;
			self::SuggestOneMonth($suggestions, $select, $y, $m);

			$select->close();
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up specific month budget suggestions', $mse);
		}
	}

	/**
	 * Load category totals for the month specifed.
	 * @param BudgetSuggestions $suggestions Budget suggestions object the month will be added to.
	 * @param mysqli_stmt $stmt Prepared statement ready to execute to get data.
	 * @param int $y Year to load.
	 * @param int $m Month to load.
	 */
	private static function SuggestOneMonth(BudgetSuggestions $suggestions, mysqli_stmt $stmt, int $y, int $m) {
		$i = count($suggestions->Columns);
		$suggestions->Columns[] = date('M Y', strtotime($y . '-' . $m));
		$stmt->execute();
		$stmt->bind_result($amount, $catid, $catname, $groupname);
		$v = 0;
		while ($stmt->fetch()) {
			while ($v < count($suggestions->Categories) && (mb_strtolower($groupname) > mb_strtolower($suggestions->Categories[$v]->Group) || $groupname == $suggestions->Categories[$v]->Group && mb_strtolower($catname) > mb_strtolower($suggestions->Categories[$v]->Name)))
				$v++;
			if ($v < count($suggestions->Categories) && $groupname == $suggestions->Categories[$v]->Group && $catname == $suggestions->Categories[$v]->Name) {
				for ($a = count($suggestions->Categories[$v]->AmountColumns); $a < $i; $a++)
					$suggestions->Categories[$v]->AmountColumns[] = null;
				$suggestions->Categories[$v]->AmountColumns[] = +$amount;
			} else {
				$amounts = [];
				for ($a = 0; $a < $i; $a++)
					$amounts[] = null;
				$amounts[] = +$amount;
				$category = new SuggestedBudgetCategory($catid, $catname, $groupname);
				$category->AmountColumns = $amounts;
				array_splice($suggestions->Categories, $v, 0, [$category]);
			}
		}
	}
}

class BudgetMonth {
	public string $Sort;
	public string $Display;

	public function __construct(string $sort, string $display) {
		$this->Sort = $sort;
		$this->Display = $display;
	}
}

class ActiveBudget {
	public array $Categories = [];
	public array $Funds = [];
}

class ActiveBudgetCategory {
	public ?int $ID;
	public ?string $Name;
	public ?string $Group;
	public float $Planned;
	public float $Actual;
	public float $Amount;

	public function __construct(?int $id, ?string $name, ?string $group, float $planned, float $actual, float $amount) {
		$this->ID = $id;
		$this->Name = $name;
		$this->Group = $group;
		$this->Planned = $planned;
		$this->Actual = $actual;
		$this->Amount = $amount;
	}
}

class ActiveBudgetFund {
	public int $ID;
	public string $Name;
	public float $Planned;
	public float $Actual;

	public function __construct(int $id, string $name, float $planned, float $actual) {
		$this->ID = $id;
		$this->Name = $name;
		$this->Planned = $planned;
		$this->Actual = $actual;
	}
}

class BudgetSuggestions {
	public array $Columns = [];
	public array $Categories = [];
}

class SuggestedBudgetCategory {
	public int $ID;
	public string $Name;
	public ?string $Group;
	public array $AmountColumns = [];

	public function __construct(int $id, string $name, ?string $group) {
		$this->ID = $id;
		$this->Name = $name;
		$this->Group = $group;
	}
}

BudgetApi::Respond();
