<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for budget API requests.
 */
class BudgetApi extends abeApi {
	private const MonthRegex = '/^[0-9]{4}\-[0-9]{2}$/';
	/**
	 * Write out the documentation for the budgetAPI controller.  The page is
	 * already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
		<h2 id=GETlatest>GET active</h2>
		<p>
			Load an active budget.
		</p>
		<dl class=parameters>
			<dt>month</dt>
			<dd>Month to look up (YYYY-MM format, latest month by default).
		</dl>

		<h2 id=POSTactual>POST actual</h2>
		<p>Update the actual value for the specified budget category(ies).</p>
		<dl class=parameters>
			<dt>amount</dt>
			<dd>
				New actual amount for the budget category(ies). Typically negative
				for income categories and positive for spending categories. Must be
				an array parallel to id if id is an array, or a single value if id is
				a single value.
			</dd>
			<dt>id</dt>
			<dd>
				ID of the category(ies) to update. Can pass an array to set
				multiple in one API call. New categories are added to the budget.
			</dd>
			<dt>month</dt>
			<dd>Month the budget category belongs to (YYYY-MM format).</dd>
		</dl>

		<h2 id=POSTactualFund>POST actualFund</h2>
		<p>Update the actual value for the specified budget savings fund.</p>
		<dl class=parameters>
			<dt>amount</dt>
			<dd>
				New actual amount for the budget savings fund. Positive amounts
				raise the fund balance and negative amonuts lower it.
			</dd>
			<dt>id</dt>
			<dd>
				ID of the savings fund to update.
			</dd>
			<dt>month</dt>
			<dd>Month the budget fund item belongs to (YYYY-MM format).</dd>
		</dl>

		<h2 id=POSTcreate>POST create</h2>
		<p>
			Create a new budget. Parameters are passed as a JSON object in the
			request body, not as POST values.
		</p>
		<dl class=parameters>
			<dt>month</dt>
			<dd>
				Month new budget is for (YYYY-MM format).
			</dd>
			<dt>categories[{catid, amount}]</dt>
			<dd>
				Array of category IDs and amounts in this budget. Positive amounts
				are for spending and negative amounts are for income.
			</dd>
			<dt>funds[{fund, amount}]</dt>
			<dd>
				Array of fund IDs and amounts that will change as a part of this
				budget. Positive amounts mean money going into the fund and negative
				mean coming out.
			</dd>
		</dl>

		<h2 id=GETlist>GET list</h2>
		<p>
			Get the list of all months that have a budged defined. Months are
			returned with sort (YYYY-MM) and display properties.
		</p>

		<h2 id=GETsuggestions>GET suggestions</h2>
		<p>Retrieve category totals to help suggest budget values.</p>
		<dl class=parameters>
			<dt>month</dt>
			<dd>Month to suggest budget values for (YYYY-MM format).</dd>
		</dl>
<?php
	}

	/**
	 * Load an active budget.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function activeAction(abeAjax $ajax) {
		if (isset($_GET['month']) && $_GET['month'])
			if (preg_match(self::MonthRegex, $month = trim($_GET['month']))) {
				$month .= '-01';
				$db = self::RequireLatestDatabase($ajax);
				// TODO:  also get summary data for this month and combine with this
				if ($cats = $db->prepare('call GetBudget(?)'))
					if ($cats->bind_param('s', $month))
						if ($cats->execute())
							if ($catresult = $cats->get_result()) {
								$ajax->Data->categories = [];
								while ($cat = $catresult->fetch_object()) {
									$cat->catid += 0;
									$cat->planned += 0;
									$cat->actual += 0;
									$cat->amount += 0;
									$ajax->Data->categories[] = $cat;
								}
								$db->next_result();
								if ($funds = $db->prepare('select f.name, f.id, b.planned, b.actual from budget_funds as b left join funds as f on f.id=b.fund where month=? order by name'))
									if ($funds->bind_param('s', $month))
										if ($funds->execute())
											if ($funds->bind_result($name, $id, $planned, $actual)) {
												$ajax->Data->funds = [];
												while ($funds->fetch())
													$ajax->Data->funds[] = ['name' => $name, 'id' => +$id, 'planned' => +$planned, 'actual' => +$actual];
											} else
												$ajax->Fail('Error binding result of budget funds:  ' . $funds->errno . ' ' . $funds->error);
										else
											$ajax->Fail('Error executing query to look up budget funds:  ' . $funds->errno . ' ' . $funds->error);
									else
										$ajax->Fail('Error binding parameter to look up budget funds:  ' . $funds->errno . ' ' . $funds->error);
								else
									$ajax->Fail('Error preparing to look up budget funds:  ' . $db->errno . ' ' . $db->error);
							} else
								$ajax->Fail('Error getting result of budget categories:  ' . $cats->errno . ' ' . $cats->error);
						else
							$ajax->Fail('Error executing query to look up budget categories:  ' . $cats->errno . ' ' . $cats->error);
					else
						$ajax->Fail('Error binding parameter to look up budget categories:  ' . $cats->errno . ' ' . $db->error);
				else
					$ajax->Fail('Error preparing to look up budget categories:  ' . $db->errno . ' ' . $db->error);
			} else
				$ajax->Fail('Month must be formatted YYYY-MM.');
		else
			$ajax->Fail('Parameter month is required.');
	}

	/**
	 * Update the actual value of a budget category.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function actualAction(abeAjax $ajax) {
		if (isset($_POST['month']) && preg_match(self::MonthRegex, $month = trim($_POST['month'])))
			if (isset($_POST['id']) && (($multi = is_array($_POST['id'])) || +$_POST['id']))
				if (isset($_POST['amount']) && is_array($_POST['amount']) == $multi && (!$multi || count($_POST['id']) == count($_POST['amount']))) {
					$month .= '-01';
					$ids = $multi ? $_POST['id'] : [$_POST['id']];
					$amounts = $multi ? $_POST['amount'] : [$_POST['amount']];
					$db = self::RequireLatestDatabase($ajax);
					if ($update = $db->prepare('insert into budget_categories (month, category, actual) values (?, ?, ?) on duplicate key update actual=?'))
						if ($update->bind_param('sidd', $month, $id, $amount, $amount))
							for ($c = 0; $c < count($ids); $c++) {
								$id = +$ids[$c];
								$amount = +$amounts[$c];
								if ($id && !$update->execute()) {
									$ajax->Fail('Error executing query to update budget item balance for category ' . $id . ':  ' . $update->errno . ' ' . $update->error);
									return;
								}
							}
						else
							$ajax->Fail('Error binding parameters to update budget item balance:  ' . $update->errno . ' ' . $update->error);
					else
						$ajax->Fail('Error preparing to update budget item balance:  ' . $db->errno . ' ' . $db->error);
				} else
					$ajax->Fail('Parameter amount is required and must be parallel with id.');
			else
				$ajax->Fail('Parameter id is required and must be numeric or an array of numbers.');
		else
			$ajax->Fail('Parameter month is required and must be formatted YYYY-MM.');
	}

	/**
	 * Update the actual value a budget adds to a savings fund.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function actualFundAction(abeAjax $ajax) {
		if (isset($_POST['month']) && preg_match(self::MonthRegex, $month = trim($_POST['month'])))
			if (isset($_POST['id']) && $id = +$_POST['id']) {
				$month .= '-01';
				$amount = +$_POST['amount'];
				$db = self::RequireLatestDatabase($ajax);
				$db->autocommit(false);
				if ($update = $db->prepare('update funds set balance=balance+?-(select actual from budget_funds where month=? and fund=?) where id=?'))
					if ($update->bind_param('dsii', $amount, $month, $id, $id))
						if ($update->execute())
							if ($update = $db->prepare('update budget_funds set actual=? where month=? and fund=?'))
								if ($update->bind_param('dsi', $amount, $month, $id))
									if ($update->execute())
										$db->commit();
									else
										$ajax->Fail('Error updating budget fund amount:  ' . $update->errno . ' ' . $update->error);
								else
									$ajax->Fail('Error binding parameters to update budget fund amount:  ' . $update->errno . ' ' . $update->error);
							else
								$ajax->Fail('Error preparing to update budget fund amount:  ' . $db->errno . ' ' . $db->error);
						else
							$ajax->Fail('Error updating saving fund balance:  ' . $update->errno . ' ' . $update->error);
					else
						$ajax->Fail('Error binding parameters to update saving fund balance:  ' . $update->errno . ' ' . $update->error);
				else
					$ajax->Fail('Error preparing to update saving fund balance:  ' . $db->errno . ' ' . $db->error);
			} else
				$ajax->Fail('Parameter id is required and must be numeric or an array of numbers.');
		else
			$ajax->Fail('Parameter month is required and must be formatted YYYY-MM.');
	}

	/**
	 * Create a budget.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function createAction(abeAjax $ajax) {
		if (isset($_POST['month']) && preg_match(self::MonthRegex, $month = trim($_POST['month']))) {
			$month .= '-01';
			$catids = isset($_POST['catids']) ? $_POST['catids'] : [];
			$catamounts = isset($_POST['catamounts']) ? $_POST['catamounts'] : [];
			if (count($catids) == count($catamounts)) {
				$fundids = isset($_POST['fundids']) ? $_POST['fundids'] : [];
				$fundamounts = isset($_POST['fundamounts']) ? $_POST['fundamounts'] : [];
				if (count($fundids) == count($fundamounts))
					if (count($catids) > 0 || count($fundids) > 0) {
						$db = self::RequireLatestDatabase($ajax);
						$db->autocommit(false);
						if ($inscat = $db->prepare('insert into budget_categories (month, category, planned) values (?, ?, ?)'))
							if ($inscat->bind_param('sid', $month, $catid, $amount))
								for ($c = 0; $c < count($catids); $c++) {
									$catid = $catids[$c];
									$amount = $catamounts[$c];
									if (!$inscat->execute()) {
										$ajax->Fail('Error setting budget amount for category ' . $catid . ':  ' . $inscat->errno . ' ' . $inscat->error);
										return;
									}
								}
							else {
								$ajax->Fail('Error binding parameters to save budget categories:  ' . $inscat->errno . ' ' . $inscat->error);
								return;
							}
						else {
							$ajax->Fail('Error preparing to save budget categories:  ' . $db->errno . ' ' . $db->error);
							return;
						}
						if ($insfund = $db->prepare('insert into budget_funds (month, fund, planned) values (?, ?, ?)'))
							if ($insfund->bind_param('sid', $month, $fundid, $amount))
								for ($f = 0; $f < count($fundids); $f++) {
									$fundid = $fundids[$f];
									$amount = $fundamounts[$f];
									if (!$insfund->execute()) {
										$ajax->Fail('Error setting budget amount for fund ' . $fundid . ':  ' . $insfund->errno . ' ' . $insfund->error);
										return;
									}
								}
							else
								$ajax->Fail('Error binding parameters to save budget funds:  ' . $insfund->errno . ' ' . $insfund->error);
						else
							$ajax->Fail('Error preparing to save budget funds:  ' . $db->errno . ' ' . $db->error);
						$db->commit();
					} else
						$ajax->Fail('At least one category or fund must have an amount in order to create a budget.');
				else
					$ajax->Fail('Parameters fundids and fundamounts must have the same number of array elements.');
			} else
				$ajax->Fail('Parameters catids and catamounts must have the same number of array elements.');
		} else
			$ajax->Fail('Parameter month is required and must be formatted YYYY-MM.');
	}

	/**
	 * Action to look up months that have a budget defined.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction(abeAjax $ajax) {
		$db = self::RequireLatestDatabase($ajax);
		if ($months = $db->query('select date_format(month, \'%Y-%m\') as sort, date_format(month, \'%b %Y\') as display from (select month from budget_categories group by month union select month from budget_funds group by month) as m group by month')) {
			$ajax->Data->months = [];
			while ($month = $months->fetch_object())
				$ajax->Data->months[] = $month;
		} else
			$ajax->Fail('Error looking up list of months that have budgets:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Action to look up suggestions for a budget.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function suggestionsAction(abeAjax $ajax) {
		if (isset($_GET['month']) && preg_match(self::MonthRegex, $month = trim($_GET['month']))) {
			$ajax->Data->columns = [];
			$ajax->Data->values = [];
			$db = self::RequireLatestDatabase($ajax);
			self::SuggestAverageMonth($db, $ajax);
			self::SuggestSpecificMonths($db, $ajax, $month);
		} else
			$ajax->Fail('Parameter \'month\' is required and must be formatted YYYY-MM.');
	}

	/**
	 * Load category averages for the past 12 months.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	private static function SuggestAverageMonth(mysqli $db, abeAjax $ajax) {
		$ajax->Data->columns[] = "Average";
		if ($cats = $db->query('call GetMonthlyCategoryAverage()')) {
			while ($cat = $cats->fetch_object())
				if ($cat->catid) {
					$cat->catid = +$cat->catid;
					$cat->amounts = [];
					$cat->amounts[] = +$cat->amount;
					if (!$cat->groupid)
						$cat->groupname = '(ungrouped)';
					unset($cat->amount, $cat->groupid);
					$ajax->Data->values[] = $cat;
				}
			$db->next_result();  // get past the extra stored procedure result
		} else
			$ajax->Fail('Error looking up average of latest 12 months spending:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Load category totals for the most recent full month and the specified month a year ago.
	 * @param mysqli $db Database connection object.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 * @param string $month Month for new budget, YYYY-MM format.
	 */
	private static function SuggestSpecificMonths(mysqli $db, abeAjax $ajax, string $month) {
		if ($lastfullmonth = $db->query('select GetLastFullMonth() as lastfullmonth'))
			if ($lastfullmonth = $lastfullmonth->fetch_object())
				$lastfullmonth = $lastfullmonth->lastfullmonth;
		if ($cats = $db->prepare('select amount, catid, catname, coalesce(groupname, \'(ungrouped)\') as groupname from spending_monthly where year(month)=? and month(month)=? and not catid is null order by groupname, catname'))
			if ($cats->bind_param('ii', $y, $m)) {
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
				self::SuggestOneMonth($ajax, $cats, $y, $m);

				// this month last year
				$y = $yyyy - 1;
				$m = +$mm;
				self::SuggestOneMonth($ajax, $cats, $y, $m);
			} else
				$ajax->Fail('Error binding parameters to look up a month worth of category totals:  ' . $cats->errno . ' ' . $cats->error);
		else
			$ajax->Fail('Error preparing to look up a month worth of category totals:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Load category totals for the month specifed.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 * @param mysqli_stmt $stmt Prepared statement ready to execute to get data.
	 * @param int $y Year to load.
	 * @param int $m Month to load.
	 */
	private static function SuggestOneMonth(abeAjax $ajax, mysqli_stmt $stmt, int $y, int $m) {
		$i = count($ajax->Data->columns);
		$ajax->Data->columns[] = $name = date('M Y', strtotime($y . '-' . $m));
		if ($stmt->execute())
			if ($stmt->bind_result($amount, $catid, $catname, $groupname)) {
				$v = 0;
				while ($stmt->fetch()) {
					while (mb_strtolower($groupname) > mb_strtolower($ajax->Data->values[$v]->groupname) || $groupname == $ajax->Data->values[$v]->groupname && mb_strtolower($catname) > mb_strtolower($ajax->Data->values[$v]->catname))
						$v++;
					if ($groupname == $ajax->Data->values[$v]->groupname && $catname == $ajax->Data->values[$v]->catname) {
						for ($a = count($ajax->Data->values[$v]->amounts); $a < $i; $a++)
							$ajax->Data->values[$v]->amounts[] = '';
						$ajax->Data->values[$v]->amounts[] = +$amount;
					} else {
						$amounts = [];
						for ($a = 0; $a < $i; $a++)
							$amounts[] = '';
						$amounts[] = +$amount;
						array_splice($ajax->Data->values, $v, 0, [(object)['catid' => $catid, 'catname' => $catname, 'groupname' => $groupname, 'amounts' => $amounts]]);
					}
				}
			} else
				$ajax->Fail('Error binding results of ' . $name . ' spending:  ' . $stmt->errno . ' ' . $stmt->error);
		else
			$ajax->Fail('Error executing query to look up ' . $name . ' spending:  ' . $stmt->errno . ' ' . $stmt->error);
	}
}
BudgetApi::Respond();
