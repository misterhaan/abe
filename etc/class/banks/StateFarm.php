<?php
/**
 * Bank functions specific to State Farm.
 * @author misterhaan
 */
class StateFarm extends cyaBank {
	/**
	 * Import transactions from a CSV file into a credit card account from State
	 * Farm.
	 * @param string $filename Full path to the CSV file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 * @return boolean True if successful.
	 */
	public function ImportCsvTransactions($filename, $account) {
		global $ajax, $db;

		if(false !== $fh = fopen($filename, 'r')) {
			// first lines is headers
			fgets($fh);

			// transaction makes all these inserts much faster
			$db->real_query('start transaction');

			// prepare and bind a statement
			if(false !== $ins = $db->prepare('insert into transactions (account, extid, transdate, posted, name, amount, city, state, zip) values (?, ?, ?, ?, ?, ?, ?, ?, ?)'))
				if($ins->bind_param('issssdsss', $account, $extid, $transdate, $posted, $name, $amount, $city, $state, $zip)) {
					$ajax->Data->count = 0;
					$net = 0;
					while($line = fgetcsv($fh)) {
						// translate the data
						$extid = $line[7];
						$transdate = date('Y-m-d', strtotime($line[0]));
						$posted = date('Y-m-d', strtotime($line[1]));
						$name = self::TitleCase($line[3]);
						$amount = -str_replace([
								'$',
								'(',
								')'
						], [
								'',
								'-',
								''
						], $line[2]);
						$city = self::TitleCase($line[4]);
						$state = $line[5];
						$zip = $line[6];
						$net += $amount;

						// sometimes they use the city as a continuation of the name
						if($city == 'You' && substr($name, -5) == 'Thank' || $city == 'Fee' && $name == 'International Transaction') {
							$name .= ' ' . $city;
							$city = '';
						}

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
