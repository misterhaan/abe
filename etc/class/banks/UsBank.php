<?php
/**
 * Bank functions specific to State Farm.
 * @author misterhaan
 */
class UsBank extends abeBank {
	/**
	 * Parse transactions from a CSV for into a credit card account from State
	 * Farm.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions(string $filename, int $acctid, mysqli $db) {
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
						$tran->extid = $extid = self::ParseExtId($line[3]);
						$tran->posted = $posted = date('Y-m-d', strtotime($line[0]));
						$tran->name = self::TitleCase($line[2]);
						$tran->amount = $amount = +$line[4];

						// fields not provided
						$tran->transdate = null;
						$tran->city = null;
						$tran->state = null;
						$tran->zip = null;
						$tran->notes = '';

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


	private static function ParseExtId(string $csvValue) {
		$extid = explode(';', $csvValue)[0];
		if($extid == 'WEB FUTURE')
			$extid = null;
		return $extid;
	}
}
