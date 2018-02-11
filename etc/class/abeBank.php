<?php
/**
 * Base class for bank-specific functions such as CSV transaction import.
 * @author misterhaan
 *
 */
abstract class abeBank {
	/**
	 * Read transactions from a file into an array of objects that match the
	 * transactions table in the database.
	 * @param string $origname Original filename, used to infer file format from extension.
	 * @param string $filename Full path to the file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public function ParseTransactions($origname, $filename, $acctid) {
		global $ajax;
		$ext = explode('.', $origname);
		$ext = $ext[count($ext) - 1];
		$parse = 'Parse' . ucfirst(strtolower($ext)) . 'Transactions';
		if(method_exists(static::class, $parse)) {
			$return = static::$parse($filename, $acctid);
			$return->name = $origname;
			return $return;
		} else
			$ajax->Fail('Abe does not support ' . $ext . ' file transaction import for this bank.');
		return false;
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

	protected function GetDuplicateChecker() {
		global $db;
		return $db->prepare('select IsDuplicateTransaction(?, ?, ?, ?)');
	}
}
