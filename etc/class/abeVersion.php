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
	const Structure = abeStructureVersion::MySqlStrict;
	/**
	 * Database data (rows) version.  Changing this triggers the setup script in
	 * update mode.
	 * @var integer
	 */
	const Data = abeDataVersion::Initial;
}

/**
 * List of structure versions for Abe Personal Finance.  New versions should be
 * added at the top and use the next integer value.  Be sure to update
 * InstallDatabase() and UpgradeDatabaseStructure() in setup.php.
 * @author misterhaan
 */
class abeStructureVersion {
	const MySqlStrict = 8;
	const Budgets = 7;
	const Funds = 6;
	const CategoryGroups = 5;
	const SummaryProcedures = 4;
	const Duplicates = 3;
	const Bookmarks = 2;
	const Initial = 1;
}

/**
 * List of data versions for Abe Personal Finance.  New versions should be
 * added at the top and use the next integer value.  Be sure to update
 * InstallDatabase() and UpgradeDatabaseData() in setup.php.
 * @author misterhaan
 */
class abeDataVersion {
	const Initial = 1;
}
