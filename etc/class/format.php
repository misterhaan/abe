<?php
class Format {
	/**
	 * Format an amount for display with two decimal places and thousands
	 * separators, unless the value is less than 10k.
	 * @param float $amt Amount to format.
	 */
	public static function Amount(float $amt): string {
		if (+$amt >= 10000 || +$amt <= -10000)
			return number_format(+$amt, 2);
		return number_format(+$amt, 2, '.', '');
	}
}
