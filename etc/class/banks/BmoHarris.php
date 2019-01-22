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
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseCsvTransactions($filename, $acctid) {
		global $db;
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
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseOfxTransactions($filename, $acctid) {
		global $db;
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
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseQboTransactions($filename) {
		// The qbo file is basically the same as ofx, just with some extra stuff we will ignore anyway.
		self::ParseOfxTransactions($filename);
	}

	/**
	 * Parse transactions from a QFX file for an account from BMO Harris Bank.
	 * @param string $filename Full path to the QFX file on the server.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseQfxTransactions($filename) {
		// The qfx file is basically the same as ofx, just with some extra stuff we will ignore anyway.
		self::ParseOfxTransactions($filename, $account);
	}

	/**
	 * Import transactions from a CSV file into an account from BMO Harris Bank.
	 * Currently leaves out data Abe needs to know.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 */
	public function ImportCsvTransactionsBroken($filename, $account) {
		// can's use CSV because it leaves out important data
		global $ajax, $db;

		if(false !== $fh = fopen($filename, 'r')) {
			// first three lines are headers
			for($i = 0; $i < 3; $i++)
				fgets($fh);

				// transaction makes all these inserts much faster
			$db->real_query('start transaction');

			// prepare and bind a statement
			if(false !== $ins = $db->prepare('insert into transactions (account, extid, posted, name, amount) values (?, ?, ?, ?, ?)'))
				if($ins->bind_param('isssd', $account, $extid, $posted, $name, $amount)) {
					$ajax->Data->count = 0;
					$net = 0;
					while($line = fgetcsv($fh)) {
						// translate the data
						$extid = $line[6];
						$posted = date('Y-m-d', strtotime($line[1]));
						$name = $line[7] == 'Check' ? 'Check ' . $line[5] : self::TitleCase($line[2]);
						$amount = $line[8] == 'Debit' ? -$line[3] : +$line[3];
						$net += $amount;

						if($ins->execute())
							$ajax->Data->count++;
						else
							$ajax->Fail('Error executing transaction import:  ' . $ins->error);
					}
					// close the statement
					$ins->close();
					self::UpdateAccount($account, false, $net);
				} else
					$ajax->Fail('Error binding import parameters:  ' . $ins->error);
			else
				$ajax->Fail('Database error preparing to import transactions:  ' . $db->error);
			$db->real_query('commit');
			fclose($fh);
		} else
			$ajax->Fail('Unable to open file.');
	}
}
?>
