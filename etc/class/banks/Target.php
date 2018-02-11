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
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions($filename, $acctid) {
		global $db;
		if(false !== $fh = fopen($filename, 'r')) {
			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;
			$preview->dupeCount = 0;

			if($chkdupe = $db->prepare('select IsDuplicateTransaction(?, \'false\', ?, ?)'))
				if($chkdupe->bind_param('ids', $acctid, $amount, $posted)) {
					while($line = fgetcsv($fh)) {
						$l2len = strlen($line[2]);
						$tran = new stdClass();
						// translate the data
						$tran->extid = null;  // not provided
						$tran->transdate = date('Y-m-d', strtotime($line[0]));
						$tran->posted = $posted = date('Y-m-d', strtotime($line[1]));
						$tran->name = self::TitleCase(trim(substr($line[2], 0, 25)));
						$tran->amount = $amount = +$line[3];
						$tran->city = $l2len > 25 ? self::TitleCase(trim(substr($line[2], 25, 13))) : null;
						$tran->state = $l2len > 38 ? substr($line[2], 38, 2) : null;
						$tran->zip = null;  // not provided
						$tran->notes = '';  // not provided

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
