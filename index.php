<?php
require_once __DIR__ . '/etc/class/cya.php';

$html = new cyaHtml();
$html->Open(cyaHtml::SITE_NAME_FULL);
?>
			<h1><?php echo cyaHtml::SITE_NAME_FULL; ?></h1>
			<nav id=mainmenu>
				<a href=transactions.php>Transactions</a>
				<a href=accounts.php>Accounts</a>
				<a href=settings.php>Settings</a>
			</nav>
<?php
$html->Close();
?>
