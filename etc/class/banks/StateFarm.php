<?php
/**
 * Bank functions specific to State Farm.
 * @author misterhaan
 */
class StateFarm extends abeBank {
	/**
	 * Parse transactions from a CSV for into a credit card account from State
	 * Farm.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions($filename, $acctid, $db) {
		if(false !== $fh = fopen($filename, 'r')) {
			// first line is headers
			fgets($fh);

			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;
			$preview->dupeCount = 0;

			if($chkdupe = $db->prepare('select IsDuplicateTransaction(?, ?, ?, ?)'))
				if($chkdupe->bind_param('isds', $acctid, $extid, $amount, $posted)) {
					while($line = fgetcsv($fh)) {
						$tran = new stdClass();
						// translate the data
						$tran->extid = $extid = $line[7];
						$tran->transdate = date('Y-m-d', strtotime($line[0]));
						$tran->posted = $posted = date('Y-m-d', strtotime($line[1]));
						$tran->name = self::TitleCase($line[3]);
						$tran->amount = $amount = -str_replace(['$', '(', ')'], ['', '-', ''], $line[2]);
						$tran->city = self::TitleCase($line[4]);
						$tran->state = $line[5];
						$tran->zip = $line[6];
						$tran->notes = '';  // not provided

						// sometimes they use the city as a continuation of the name
						if($tran->city == 'You' && substr($tran->name, -5) == 'Thank' || $tran->city == 'Fee' && $tran->name == 'International Transaction') {
							$tran->name .= ' ' . $tran->city;
							$tran->city = '';
						}

						if($chkdupe->execute())
							if($chkdupe->bind_result($dupe))
								if($chkdupe->fetch())
									if($tran->duplicate = $dupe)
										$preview->dupeCount++;

						$preview->net += $tran->amount;
						$preview->transactions[] = $tran;
					}
					return $preview;
				}
		}
		return false;
	}
}
