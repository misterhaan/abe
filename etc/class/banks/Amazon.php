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
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions($filename) {
		if(false !== $fh = fopen($filename, 'r')) {
			// first line is header
			fgets($fh);

			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;

			while($line = fgetcsv($fh)) {
				$tran = new stdClass();
				// translate the data
				$tran->extid = trim($line[2]) ? $line[2] : null;
				$tran->transdate = date('Y-m-d', strtotime($line[0]));
				$tran->posted = date('Y-m-d', strtotime($line[1]));
				$tran->name = self::TitleCase(trim(substr($line[4], 0, 25)));
				$tran->amount = +$line[3];
				$tran->city = self::TitleCase(trim(substr($line[4], 25, 13)));
				$tran->state = substr($line[4], 38, 2);
				$tran->zip = null;  // not provided
				$tran->notes = substr($line[4], 41);

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

				$preview->net += $tran->amount;
				$preview->transactions[] = $tran;
			}
			return $preview;
		}
		return false;
	}
}
