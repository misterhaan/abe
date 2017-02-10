<?php
/**
 * Bank functions specific to Target.
 * @author misterhaan
 */
class Target extends cyaBank {
	/**
	 * Import transactions from a CSV file into a credit card account from Target.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 * @return boolean True if successful.
	 */
	public function ImportCsvTransactions($filename, $account) {
		global $ajax, $db;

		if(false !== $fh = fopen($filename, 'r')) {
			// transaction makes all these inserts much faster
			$db->real_query('start transaction');

			// prepare and bind a statement
			if(false !== $ins = $db->prepare('insert into transactions (account, transdate, posted, name, amount, city, state) values (?, ?, ?, ?, ?, ?, ?)'))
				if($ins->bind_param('isssdss', $account, $transdate, $posted, $name, $amount, $city, $state)) {
					$ajax->Data->count = 0;
					$net = 0;
					while($line = fgetcsv($fh)) {
						// translate the data
						$transdate = date('Y-m-d', strtotime($line[0]));
						$posted = date('Y-m-d', strtotime($line[1]));
						$name = self::TitleCase(trim(substr($line[2], 0, 25)));
						$amount = +$line[3];
						$city = self::TitleCase(trim(substr($line[2], 25, 13)));
						$state = substr($line[4], 38, 2);
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
		} else
			$ajax->Fail('Unable to open file.');
		return false;
	}
}
?>
