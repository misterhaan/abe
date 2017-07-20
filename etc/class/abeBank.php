<?php
/**
 * Base class for bank-specific functions such as CSV transaction import.
 * @author misterhaan
 *
 */
abstract class abeBank {
	/**
	 * Import transactions from a file into an account.
	 * @param string $origname Original filename, used to infer file format from extension.
	 * @param string $filename Full path to the file on the server.
	 * @param integer $account ID of the account the transactions belong to.
	 * @return boolean True if successful.
	 */
	public function ImportTransactions($origname, $filename, $account) {
		global $ajax;
		$ext = explode('.', $origname);
		$ext = $ext[count($ext) - 1];
		$import = 'Import' . ucfirst(strtolower($ext)) . 'Transactions';
		if(method_exists(static::class, $import))
			static::$import($filename, $account);
		else
			$ajax->Fail('Abe does not support ' . $ext . ' file transaction import for this bank.');
	}

	/**
	 * Changes the casing of a string to Title Case.
	 * @param string $string String in all-caps.
	 * @return string The string, converted to Title Case.
	 */
	protected function TitleCase($string) {
		if(preg_match('/[a-z]/', $string))
			return $string;
		return ucwords(strtolower($string));
	}

	/**
	 * Update the account after importing transactions.
	 * @param number $account ID of the account to update.
	 * @param number $date Unix timestamp for the last updated time for the account, or false for now.
	 * @param number $amount Amount the balance changed by.
	 */
	protected function UpdateAccount($account, $date = false, $amount = 0) {
		global $ajax, $db;
		if(!$date)
			$date = time();
		if(!$db->real_query('update accounts set updated=\'' . +$date . '\', balance=balance+\'' . +$amount . '\' where id=\'' . +$account .'\' limit 1'))
			$ajax->Fail('Error updating account:  ' . $db->error);
	}
}
?>
