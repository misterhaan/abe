<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for summary API requests.
 * @author misterhaan
 */
class SummaryApi extends abeApi {
	/**
	 * Write out the documentation for the summary API controller.  The page is
	 * already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
			<h2 id=GETmonthlyCategories>GET monthlyCategories</h2>
			<p>Retrieve monthly category totals.</p>
			<dl class=parameters>
				<dt>oldest</dt>
				<dd>
					How far back to summarize.  Default is the beginning of a year ago
					this month.  Must be formatted YYYY-MM-DD but DD is forced to the
					beginning of the month.  (Not yet implemented.)
				</dd>
			</dl>

			<h2 id=GETyearlyCategories>GET yearlyCategories</h2>
			<p>Retrieve yearly category totals.</p>
			<dl class=parameters>
				<dt>oldest</dt>
				<dd>
					How far back to summarize.  Default is the beginning of the year ten
					years ago.  Must be formatted YYYY-MM-DD but MM-DD is forced to the
					beginning of the year.  (Not yet implemented.)
				</dd>
			</dl>
<?php
	}

	/**
	 * Action to lookup monthly spending totals.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function monthlyCategoriesAction($ajax) {
		$db = self::RequireLatestDatabase($ajax);
		// TODO:  accept $_GET['oldest'] for getting older data
		$oldest = date('Y') - 1 . '-' . date('m') . '-00';
		if($amts = $db->query('call GetMonthlyCategorySpending(\'' . $oldest . '\')'))
			static::ParseQueryResults($amts, $ajax);
		else
			$ajax->Fail('error looking up monthly spending by category:  ' . $db->error);
	}

	/**
	 * Action to lookup yearly spending totals.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function yearlyCategoriesAction($ajax) {
		$db = self::RequireLatestDatabase($ajax);
		// TODO:  accept $_GET['oldest'] for getting older data
		$oldest = date('Y') - 10 . '-01-00';
		if($amts = $db->query('call GetYearlyCategorySpending(\'' . $oldest . '\')'))
			static::ParseQueryResults($amts, $ajax);
		else
			$ajax->Fail('error looking up monthly spending by category:  ' . $db->error);
	}

	/**
	 * Parse query results for monthly or yearly summaries into common category
	 * and date structure.
	 * @param mysqli_result $amts Results of monthly or yearly category spending query.
	 * @param abeAjax $ajax Ajax object for returning data.
	 */
	private static function ParseQueryResults($amts, $ajax) {
		$ajax->Data->dates = [];
		$ajax->Data->cats = [];
		$lastdate = false;
		$d = -1;
		$ctrack = [];  // track which categories have been added
		$parentmap = [];  // track which parent categories have been added and where they are in the list
		while($amt = $amts->fetch_object()) {
			// add a new date (month or year) as it changes.  query is sorted by date so when it changes we know it's new
			if($lastdate != $amt->displaydate) {
				$d = count($ajax->Data->dates);
				$ajax->Data->dates[] = ['name' => $amt->displaydate, 'start' => $amt->datestart, 'end' => $amt->dateend, 'net' => 0, 'made' => 0, 'spent' => 0, 'cats' => []];
				$lastdate = $amt->displaydate;
			}
			if(!in_array($amt->catid, $ctrack)) {
				if($amt->groupid) {
					if(!array_key_exists($amt->groupid, $parentmap)) {
						$parentmap[+$amt->groupid] = count($ajax->Data->cats);
						$ajax->Data->cats[] = ['id' => +$amt->groupid, 'name' => $amt->groupname, 'subcats' => []];
					}
					$ajax->Data->cats[$parentmap[+$amt->groupid]]['subcats'][] = ['id' => +$amt->catid, 'name' => $amt->catname];
				} else
					$ajax->Data->cats[] = ['id' => +$amt->catid, 'name' => $amt->catname, 'subcats' => false];
					$ctrack[] = $amt->catid;
			}
			$ajax->Data->dates[$d]['net'] += $amt->amount;
			$ajax->Data->dates[$d][$amt->amount < 0 ? 'spent' : 'made'] += $amt->amount;
			$ajax->Data->dates[$d]['cats'][+$amt->catid] = $amt->amount;
		}
		usort($ajax->Data->cats, ['SummaryApi', 'AlphabetizeNamed']);
		foreach($ajax->Data->cats as &$parent)
			if(is_array($parent['subcats']))
				usort($parent['subcats'], ['SummaryApi', 'AlphabetizeNamed']);
	}

	/**
	 * Determine sort order based on the 'name' property.
	 * @param array $a First named array.
	 * @param array $b Second named array.
	 */
	private static function AlphabetizeNamed($a, $b) {
		return strcmp($a['name'], $b['name']);
	}
}
SummaryApi::Respond();
