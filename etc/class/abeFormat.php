<?php
class abeFormat {
	/**
	 * Format an amount for display with two decimal places and thousands
	 * separators, unless the value is less than 10k.
	 * @param unknown $amt
	 */
	public static function Amount($amt) {
		if(+$amt >= 10000 || +$amt <= -10000)
			return number_format(+$amt, 2);
		return number_format(+$amt, 2, '.', '');
	}
}
?>
