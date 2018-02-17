<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';
$html = new abeHtml();
$html->Open('API');
?>
			<h1>Abe Personal Finance API</h1>

			<h2 class=api><a href=bookmark>bookmark</a></h2>
			<p>The bookmark API manages bookmarks that appear with the main menu.</p>

			<h2 class=api><a href=category>category</a></h2>
			<p>
				The category API manages the categories that are used for transactions.
			</p>

			<h2 class=api><a href=categoryGroup>categoryGroup</a></h2>
			<p>
				The category API manages the groups that categories are organized into.
			</p>

			<h2 class=api><a href=summary>summary</a></h2>
			<p>The summary API summarizes transactions by month and year.</p>

			<h2 class=api><a href=transactions>transactions</a></h2>
			<p>
				The transactions API manages transactions, including importing
				transaction files.
			</p>
<?php
$html->Close();