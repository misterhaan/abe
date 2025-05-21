<?php

/**
 * Base class for bank-specific functions such as CSV transaction import.
 * @author misterhaan
 *
 */
abstract class Bank {
	/**
	 * Read transactions from a file into an array of objects that match the
	 * transactions table in the database.
	 * @param string $origname Original filename, used to infer file format from extension.
	 * @param string $filename Full path to the file on the server.
	 * @param int $acctid Account ID for duplicate checking.
	 * @param mysqli $db Database connection for running queries.
	 * @return array Parsed contents of the file, or false if unable to parse.
	 */
	public static function ParseTransactions(string $origname, string $filename, int $acctid, mysqli $db) {
		$ext = explode('.', $origname);
		$ext = $ext[count($ext) - 1];
		$parse = 'Parse' . ucfirst(strtolower($ext)) . 'Transactions';
		if (method_exists(static::class, $parse)) {
			$return = static::$parse($filename, $acctid, $db);
			$return->name = $origname;
			return $return;
		} else
			throw new Exception('Abe does not support ' . $ext . ' file transaction import for this bank.');
		return false;
	}

	/**
	 * Changes the casing of a string to Title Case.
	 * @param string $string String in all-caps.
	 * @return string The string, converted to Title Case.
	 */
	protected static function TitleCase(string $string) {
		if (!preg_match('/[a-z]/', $string))
			$string = ucwords(strtolower($string));
		return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-2');
	}
}
