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
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions($filename) {
		if(false !== $fh = fopen($filename, 'r')) {
			// first line is headers
			fgets($fh);

			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;

			while($line = fgetcsv($fh)) {
				$tran = new stdClass();
				// translate the data
				$tran->extid = $line[7];
				$tran->transdate = date('Y-m-d', strtotime($line[0]));
				$tran->posted = date('Y-m-d', strtotime($line[1]));
				$tran->name = self::TitleCase($line[3]);
				$tran->amount = -str_replace(['$', '(', ')'], ['', '-', ''], $line[2]);
				$tran->city = self::TitleCase($line[4]);
				$tran->state = $line[5];
				$tran->zip = $line[6];
				$tran->notes = '';  // not provided

				// sometimes they use the city as a continuation of the name
				if($tran->city == 'You' && substr($tran->name, -5) == 'Thank' || $tran->city == 'Fee' && $tran->name == 'International Transaction') {
					$tran->name .= ' ' . $tran->city;
					$tran->city = '';
				}

				$preview->net += $tran->amount;
				$preview->transactions[] = $tran;
			}
			return $preview;
		}
		return false;
	}
}
