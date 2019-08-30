<?php
/**
 * Bank functions specific to Amazon (Synchrony).
 * @author misterhaan
 *
 */
class Amazon extends abeBank {
	/**
	 * Parse transactions from a CSV file for a credit card account from Amazon.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions($filename, $acctid, $db) {
		if(false !== $fh = fopen($filename, 'r')) {
			// first line is header
			fgets($fh);

			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;
			$preview->dupeCount = 0;

			if($chkdupe = $db->prepare('select IsDuplicateTransaction(?, ?, ?, ?)'))
				if($chkdupe->bind_param('isds', $acctid, $extid, $amount, $posted)) {
					while($line = fgetcsv($fh)) {
						$l4len = strlen($line[4]);
						$tran = new stdClass();
						// translate the data
						$tran->extid = $extid = trim($line[2]) ? $line[2] : null;
						$tran->transdate = date('Y-m-d', strtotime($line[0]));
						$tran->posted = $posted = date('Y-m-d', strtotime($line[1]));
						$tran->name = self::TitleCase(trim(substr($line[4], 0, 25)));
						$tran->amount = $amount = +$line[3];
						$tran->city = $l4len > 25 ? self::TitleCase(trim(substr($line[4], 25, 13))) : null;
						$tran->state = $l4len > 38 ? substr($line[4], 38, 2) : null;
						$tran->zip = null;  // not provided
						$tran->notes = $l4len > 41 ? substr($line[4], 41) : '';

						// sometimes they use the city as a continuation of the name
						if($tran->city == 'You' && substr($tran->name, -5) == 'Thank') {
							$tran->name .= ' You';
							$tran->city = '';
						}
						if($tran->city == 'Credit' && substr($tran->name, -9) == 'Statement') {
							$tran->name .= ' Credit';
							$tran->city = '';
						}

						// commas in notes aren't encoded, so we have to put them back together
						for($l = 5; $l < count($line); $l++)
							$tran->notes .= ',' . $line[$l];

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
