<?php

/**
 * Bank functions specific to Amazon (Synchrony).
 * @author misterhaan
 *
 */
class Amazon extends Bank {
	/**
	 * Parse transactions from a CSV file for a credit card account from Amazon.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions(string $filename, int $acctid, mysqli $db) {
		if (false !== $fh = fopen($filename, 'r')) {
			// first line is header
			fgets($fh);

			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;
			$preview->dupeCount = 0;

			if ($chkdupe = $db->prepare('select IsDuplicateTransaction(?, ?, ?, ?)'))
				if ($chkdupe->bind_param('isds', $acctid, $extid, $amount, $posted)) {
					while ($line = fgetcsv($fh)) {
						$tran = new stdClass();
						// translate the data
						$tran->extid = $extid = trim($line[2]) ? $line[2] : null;
						$tran->transdate = date('Y-m-d', strtotime($line[0]));
						$tran->posted = $posted = date('Y-m-d', strtotime($line[1]));
						$tran->name = self::TitleCase($line[4]);
						$tran->amount = $amount = +$line[3];
						$tran->city = null;  // not provided
						$tran->state = null;  // not provided
						$tran->zip = null;  // not provided
						$tran->notes = null;  // not provided

						// all automatic payments have the same ID, which makes it useless
						if ($tran->name == 'Automatic Payment - Thank You') {
							$tran->extid = $extid = null;
						}

						if ($chkdupe->execute())
							if ($chkdupe->bind_result($dupe))
								if ($chkdupe->fetch())
									if ($tran->duplicate = $dupe)
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
