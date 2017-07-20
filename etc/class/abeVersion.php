<?php
/**
 * Version information for Abe Personal Finance.
 * @author misterhaan
 */
class abeVersion {
	/**
	 * The release number is not currently used.
	 * @var string
	 */
	const Release = '0.0.0';
	/**
	 * Database structure (tables and routines) version.  Changing this triggers
	 * the setup script in upgrade mode.
	 * @var integer
	 */
	const Structure = 1;
	/**
	 * Database data (rows) version.  Changing this triggers the setup script in
	 * update mode.
	 * @var integer
	 */
	const Data = 1;
}
?>
