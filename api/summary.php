<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'api.php';

/**
 * Handler for summary API requests.
 * @author misterhaan
 */
class SummaryApi extends Api {
	/**
	 * Return the documentation for the summary API controller..
	 * @return EndpointDocumentation[] Array of documentation for each endpoint of this API
	 */
	public static function GetEndpointDocumentation(): array {
		$endpoints = [];

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'monthlyCategories', 'Retrieve monthly category totals.');

		$endpoints[] = $endpoint = new EndpointDocumentation('GET', 'yearlyCategories', 'Retrieve yearly category totals.');

		return $endpoints;
	}

	/**
	 * Action to lookup monthly spending totals.
	 */
	protected static function GET_monthlyCategories() {
		$db = self::RequireLatestDatabase();
		// TODO:  accept oldest as a parameter for getting older data
		$oldest = date('Y') - 1 . '-' . date('m') . '-01';  // year ago beginning of this month
		try {
			$amts = $db->prepare('call GetMonthlyCategorySpending(?)');
			$amts->bind_param('s', $oldest);
			$amts->execute();
			$results = $amts->get_result();
			$amts->close();
			self::Success(static::ParseQueryResults($results));
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up monthly spending by category', $mse);
		}
	}

	/**
	 * Action to lookup yearly spending totals.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function GET_yearlyCategories() {
		$db = self::RequireLatestDatabase();
		// TODO:  accept oldest as a parameter for getting older data
		$oldest = date('Y') - 10 . '-01-01';  // ten years ago beginning of the year
		try {
			$amts = $db->prepare('call GetYearlyCategorySpending(?)');
			$amts->bind_param('s', $oldest);
			$amts->execute();
			$results = $amts->get_result();
			$amts->close();
			self::Success(static::ParseQueryResults($results));
		} catch (mysqli_sql_exception $mse) {
			self::DatabaseError('Error looking up yearly spending by category', $mse);
		}
	}

	/**
	 * Parse query results for monthly or yearly summaries into common category
	 * and date structure.
	 * @param mysqli_result $amts Results of monthly or yearly category spending query.
	 */
	private static function ParseQueryResults(mysqli_result $amts) {
		// TODO:  use a class for summary data
		$data = new stdClass();
		$data->dates = [];
		$data->cats = [];
		$lastdate = false;
		$d = -1;
		$ctrack = [];  // track which categories have been added
		$parentmap = [];  // track which parent categories have been added and where they are in the list
		while ($amt = $amts->fetch_object()) {
			// add a new date (month or year) as it changes.  query is sorted by date so when it changes we know it's new
			if ($lastdate != $amt->displaydate) {
				$d = count($data->dates);
				$data->dates[] = ['name' => $amt->displaydate, 'start' => $amt->datestart, 'end' => $amt->dateend, 'net' => 0, 'made' => 0, 'spent' => 0, 'cats' => []];
				$lastdate = $amt->displaydate;
			}
			if (!in_array($amt->catid, $ctrack)) {
				if ($amt->groupid) {
					if (!array_key_exists($amt->groupid, $parentmap)) {
						$parentmap[+$amt->groupid] = count($data->cats);
						$data->cats[] = ['id' => +$amt->groupid, 'name' => $amt->groupname, 'subcats' => []];
					}
					$data->cats[$parentmap[+$amt->groupid]]['subcats'][] = ['id' => +$amt->catid, 'name' => $amt->catname];
				} else
					$data->cats[] = ['id' => +$amt->catid, 'name' => $amt->catname, 'subcats' => false];
				$ctrack[] = $amt->catid;
			}
			$data->dates[$d]['net'] = round($data->dates[$d]['net'] + $amt->amount, 2);
			$type = $amt->amount < 0 ? 'spent' : 'made';
			$data->dates[$d][$type] = round($data->dates[$d][$type] + $amt->amount, 2);
			$data->dates[$d]['cats'][+$amt->catid] = $amt->amount;
		}
		usort($data->cats, ['SummaryApi', 'AlphabetizeNamed']);
		foreach ($data->cats as &$parent)
			if (is_array($parent['subcats']))
				usort($parent['subcats'], ['SummaryApi', 'AlphabetizeNamed']);
		return $data;
	}

	/**
	 * Determine sort order based on the 'name' property.
	 * @param array $a First named array.
	 * @param array $b Second named array.
	 */
	private static function AlphabetizeNamed(array $a, array $b) {
		return strcmp($a['name'], $b['name']);
	}
}
SummaryApi::Respond();
