<?php

/**
 * Bank functions specific to Target.
 * @author misterhaan
 */
class Target extends abeBank {
	/**
	 * Parse transactions from a CSV file for a credit card account from Target.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions(string $filename, int $acctid, mysqli $db) {
		if (false !== $fh = fopen($filename, 'r')) {
			// first line is headers
			fgets($fh);
			// second line is blank
			fgets($fh);

			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;
			$preview->dupeCount = 0;

			if ($chkdupe = $db->prepare('select IsDuplicateTransaction(?, \'false\', ?, ?)'))
				if ($chkdupe->bind_param('ids', $acctid, $amount, $posted)) {
					while ($line = fgetcsv($fh)) {
						$tran = new stdClass();
						// translate the data
						$tran->transdate = date('Y-m-d', strtotime($line[0]));
						$tran->posted = $posted = date('Y-m-d', strtotime($line[1]));
						$tran->extid = $line[2];
						$tran->amount = $amount = -$line[3];
						$tran->name = self::TitleCase(trim(substr($line[4], 0, -15)));
						$tran->city = self::TitleCase(trim(substr($line[4], -15, 13)));
						$tran->state = substr($line[4], -2);
						$tran->zip = null;  // not provided
						$tran->notes = '';  // not provided

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
