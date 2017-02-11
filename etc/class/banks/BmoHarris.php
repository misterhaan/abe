<?php
/**
 * Bank functions specific to BMO Harris Bank.
 * @author misterhaan
 */
class BmoHarris extends cyaBank {
	/**
	 * Import transactions from an OFX file into an account from BMO Harris Bank.
	 * @param string $filename Full path to the OFX file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 */
	public function ImportOfxTransactions($filename, $account) {
		global $ajax, $db;

		if(false !== $fh = fopen($filename, 'r')) {
			// transaction makes all these inserts much faster
			$db->real_query('start transaction');
			if($ins = $db->prepare('insert into transactions (account, extid, posted, name, amount, notes) values (?, ?, ?, ?, ?, ?)'))
				if($ins->bind_param('isssds', $account, $extid, $posted, $name, $amount, $notes)) {
					$ajax->Data->count = 0;
					$ajax->Data->extradata = [];
					$net = 0;
					$intrans = false;
					while(false !== $line = fgets($fh)) {
						$line = trim($line);
						if($intrans) {
							if($line == '</STMTTRN>') {
								$intrans = false;
								if($checknum)
									$name .= ' ' . $checknum;
								if($ins->execute()) {
									$ajax->Data->count++;
									$net += $amount;
								}
								else
									$ajax->Fail('Error executing transaction import:  ' . $ins->error);
							} else {
								list($tag, $data) = explode('>', $line, 2);
								switch($tag) {
									case '<TRNTYPE':
									case '<MEMO':
										// not using these
										break;
									case '<DTPOSTED':
										$posted = substr($data, 0, 4) . '-' . substr($data, 4, 2) . '-' . substr($data, 6, 2);
										break;
									case '<TRNAMT':
										$amount = +$data;
										break;
									case '<FITID':
										$extid = $data;
										break;
									case '<CHECKNUM':
										$checknum = $data;
										break;
									case '<NAME':
										$name = self::TitleCase(str_replace('&amp;', '&', $data));
										break;
									default:
										// log unexpected tags
										$ajax->Data->extradata[] = [
												'extid' => $extid,
												'tag' => $tag
										];
										break;
								}
							}
						} else if($line == '<STMTTRN>') {
							$intrans = true;
							$checknum = false;
							$extid = '';
							$posted = '';
							$name = '';
							$amount = 0;
							$notes = '';
						}
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

	/**
	 * Import transactions from a QBO file into an account from BMO Harris Bank.
	 * @param string $filename Full path to the QBO file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 */
	public function ImportQboTransactions($filename, $account) {
		// The qbo file is basically the same as ofx, just with some extra stuff we will ignore anyway.
		self::ImportOfxTransactions($filename, $account);
	}

	/**
	 * Import transactions from a QFX file into an account from BMO Harris Bank.
	 * @param string $filename Full path to the QFX file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 */
	public function ImportQfxTransactions($filename, $account) {
		// The qfx file is basically the same as ofx, just with some extra stuff we will ignore anyway.
		self::ImportOfxTransactions($filename, $account);
	}

	/**
	 * Import transactions from a CSV file into an account from BMO Harris Bank.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 */
	public function ImportCsvTransactions($filename, $account) {
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
