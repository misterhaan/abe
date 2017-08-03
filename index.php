<?php
require_once __DIR__ . '/etc/class/abe.php';

$html = new abeHtml();
$html->Open(abeHtml::SITE_NAME_FULL);
?>
			<h1><?php echo abeHtml::SITE_NAME_FULL; ?></h1>
			<nav id=mainmenu>
				<a href=transactions.php>Transactions</a>
				<a href=spending.php>Spending</a>
				<a href=import.php>Import</a>
				<a href=categories.php>Settings</a>
			</nav>
<?php
$html->Close();
?>
