<?php
require_once dirname($_SERVER['SCRIPT_FILENAME']) . '/etc/class/cya.php';

$html = new cyaHtml();
$html->Open(cyaHtml::SITE_NAME_FULL);
?>
      <h1><?php echo cyaHtml::SITE_NAME_FULL; ?></h1>
<?php
$html->Close();
?>
