<?php
/**
 * Bank functions specific to BMO Harris Bank.
 * @author misterhaan
 */
class BmoHarris extends abeBank {
	/**
	 * Parse transactions from a CSV file for an account from BMO Harris Bank.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions(string $filename, int $acctid, mysqli $db) {
		if(false !== $fh = fopen($filename, 'r')) {
			// first three lines are header
			fgets($fh);
			fgets($fh);
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
						// first column is blank
						$tran->posted = $posted = date('Y-m-d', strtotime($line[1]));
						$tran->name = self::TitleCase($line[2]);
						$amount = +$line[3];
						// fifth column is currency which is probably always USD
						if($line[5])
							$tran->name .= ' ' . $line[5];
						$tran->extid = $extid = $line[6];
						// eigth column is credit/check/debit
						if(strtolower($line[8]) == 'debit')
							$amount = -$amount;
						$tran->amount = $amount;

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

	/**
	 * Parse transactions from an OFX file for an account from BMO Harris Bank.
	 * @param string $filename Full path to the OFX file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseOfxTransactions(string $filename, int $acctid, mysqli $db) {
		if(false !== $fh = fopen($filename, 'r')) {
			$preview = new stdClass();
			$preview->transactions = [];
			$preview->net = 0;
			$preview->dupeCount = 0;

			if($chkdupe = $db->prepare('select IsDuplicateTransaction(?, ?, ?, ?)'))
				if($chkdupe->bind_param('isds', $acctid, $extid, $amount, $posted)) {
					$intrans = false;
					while(false !== $line = fgets($fh)) {
						$line = trim($line);
						if($intrans) {
							if($line == '</STMTTRN>') {
								$intrans = false;
								if($checknum)
									$tran->name .= ' ' . $checknum;
								if($chkdupe->execute())
									if($chkdupe->bind_result($dupe))
										if($chkdupe->fetch())
											if($tran->duplicate = $dupe)
												$preview->dupeCount++;

								$preview->net += $tran->amount;
								$preview->transactions[] = $tran;
							} else {
								list($tag, $data) = explode('>', $line, 2);
								switch($tag) {
									case '<TRNTYPE':
									case '<MEMO':
										// not using these
										break;
									case '<DTPOSTED':
										$tran->posted = $posted = substr($data, 0, 4) . '-' . substr($data, 4, 2) . '-' . substr($data, 6, 2);
										break;
									case '<TRNAMT':
										$tran->amount = $amount = +$data;
										break;
									case '<FITID':
										$tran->extid = $extid = $data;
										break;
									case '<CHECKNUM':
										$checknum = $data;
										break;
									case '<NAME':
										$tran->name = self::TitleCase(str_replace('&amp;', '&', $data));
										break;
								}
							}
						} else if($line == '<STMTTRN>') {
							$intrans = true;
							$checknum = false;
							$tran = new stdClass();
							$tran->extid = $extid = null;
							$tran->transdate = null;
							$tran->posted = $posted = '';
							$tran->name = '';
							$tran->amount = $amount = 0;
							$tran->city = null;
							$tran->state = null;
							$tran->zip = null;
							$tran->notes = '';
						}
					}
					return $preview;
				}
		}
		return false;
	}

	/**
	 * Parse transactions from a QBO file for an account from BMO Harris Bank.
	 * @param string $filename Full path to the QBO file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseQboTransactions(string $filename, int $acctid, mysqli $db) {
		// The qbo file is basically the same as ofx, just with some extra stuff we will ignore anyway.
		self::ParseOfxTransactions($filename, $acctid, $db);
	}

	/**
	 * Parse transactions from a QFX file for an account from BMO Harris Bank.
	 * @param string $filename Full path to the QFX file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseQfxTransactions(string $filename, int $acctid, mysqli $db) {
		// The qfx file is basically the same as ofx, just with some extra stuff we will ignore anyway.
		self::ParseOfxTransactions($filename, $acctid, $db);
	}
}
