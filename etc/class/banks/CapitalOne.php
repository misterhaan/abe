<?php
/**
 * Bank functions specific to Capital One.
 * @author misterhaan
 */
class CapitalOne extends abeBank {
	/**
	 * Parse transactions from a CSV file for an account from Capital One.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions(string $filename, int $acctid, mysqli $db) {
		if(false !== $fh = fopen($filename, 'r')) {
			// first line is header
			fgets($fh);

			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;
			$preview->dupeCount = 0;

			if($chkdupe = $db->prepare('select IsDuplicateTransaction(?, ?, ?, ?)'))
				if($chkdupe->bind_param('isds', $acctid, $extid, $amount, $posted)) {
					while($line = fgetcsv($fh))
						if(count($line) > 1) {  // only works for non-blank lines (technically lines that have at least two values)
							$tran = new stdClass();
							// translate the data
							$tran->transdate = $line[0];
							$tran->posted = $posted = $line[1];
							// third column is card number
							$tran->name = self::TitleCase($line[3]);
							// fifth column is category
							$tran->amount = 0;
							if(is_numeric($line[5]))
								$tran->amount -= +$line[5];
							if(is_numeric($line[6]))
								$tran->amount += +$line[6];

							// fields not provided
							$tran->extid = $extid = null;
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
}
