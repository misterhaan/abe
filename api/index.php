<?php
require_once dirname(__DIR__) . '/etc/class/environment.php';
require_once 'page.php';

class ApiIndex extends Page {
	public function __construct() {
		parent::__construct('API');
	}

	public static function MainContent(): void {
?>
		<h1>Abe Personal Finance API</h1>

		<h2 class=api><a href=account>account</a></h2>
		<p>The account API manages the accounts Abe works with.</p>

		<h2 class=api><a href=bookmark>bookmark</a></h2>
		<p>The bookmark API manages bookmarks that appear with the main menu.</p>

		<h2 class=api><a href=budget>budget</a></h2>
		<p>The budget API manages budgets.</p>

		<h2 class=api><a href=category>category</a></h2>
		<p>
			The category API manages the categories that are used for transactions.
		</p>

		<h2 class=api><a href=categoryGroup>categoryGroup</a></h2>
		<p>
			The category API manages the groups that categories are organized into.
		</p>

		<h2 class=api><a href=fund>fund</a></h2>
		<p>The fund API manages the funds used to allocate savings.</p>

		<h2 class=api><a href=setup>setup</a></h2>
		<p>
			The setup API handles configuring, creating, installing, and upgrading
			the Abe database.
		</p>

		<h2 class=api><a href=summary>summary</a></h2>
		<p>The summary API summarizes transactions by month and year.</p>

		<h2 class=api><a href=transaction>transaction</a></h2>
		<p>
			The transactions API manages transactions, including importing
			transaction files.
		</p>
<?php
	}
}
new ApiIndex();
