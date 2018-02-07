<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';
$html = new abeHtml();
$html->Open('API');
?>
			<h1>Abe Personal Finance API</h1>

			<h2><a href=bookmark>bookmark</a></h2>
			<p>The bookmark API manages bookmarks that appear with the main menu.</p>
<?php
$html->Close();