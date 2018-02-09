<?php
require_once __DIR__ . '/etc/class/abe.php';

$html = new abeHtml();
$html->Open(abeHtml::SITE_NAME_FULL);
?>
			<h1><?php echo abeHtml::SITE_NAME_FULL; ?></h1>
<?php
$html->MainMenu();
$html->Close();
