<?php
class cyaHtml {
  const SITE_NAME_FULL = 'Collect Your Assets';
  const SITE_NAME_SHORT = 'C-YA';

  private $isopen = false;
  private $isclosed = false;

  private $back = '/';
  private $actions = [];

  public function cyaHtml() {
    $this->back = INSTALL_PATH . '/';
  }

  public function SetBack($url) {
    $this->back = $url;
  }

  public function AddAction($url, $class, $text, $tooltip = '') {
    $this->actions[] = ['url' => $url, 'class' => $class, 'text' => $text, 'tooltip' => $tooltip];
  }

  public function Open($title) {
    if($this->isopen)
      return;
    $this->isopen = true;
    if(strpos($title, self::SITE_NAME_FULL) === false && strpos($title, self::SITE_NAME_SHORT) === false)
      $title .= ' - ' . self::SITE_NAME_SHORT;
    header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang=en>
  <head>
    <meta charset=utf-8>
    <meta name=viewport content="width=device-width, initial-scale=1">
    <title><?php echo $title; ?></title>
    <link rel=stylesheet href="<?php echo INSTALL_PATH; ?>/theme/cya.css">
    <script src="<?php echo INSTALL_PATH; ?>/jquery-3.1.1.min.js" type="text/javascript"></script>
    <script src="<?php echo INSTALL_PATH; ?>/knockout-3.4.1.js" type="text/javascript"></script>
    <script src="<?php echo INSTALL_PATH; ?>/cya.js" type="text/javascript"></script>
<?php
    if(file_exists(str_replace('.php', '.js', $_SERVER['SCRIPT_FILENAME']))) {
?>
    <script src="<?php echo str_replace('.php', '.js', $_SERVER['SCRIPT_NAME']); ?>" type="text/javascript"></script>
<?php
    }
?>
  </head>
  <body>
    <header>
      <span class=back>
<?php
    if($_SERVER['PHP_SELF'] != INSTALL_PATH . '/index.php') {
?>
        <a href="<?php echo $this->back; ?>"><span>back</span></a>
<?php
    }
?>
      </span>
      <span class=actions>
<?php
    foreach($this->actions as $action) {
?>
        <a class=<?php echo $action['class']; ?> href="<?php echo $action['url']; ?>" title="<?php echo $action['tooltip']; ?>"><span><?php echo $action['text']; ?></span></a>
<?php
    }
?>
      </span>
    </header>
    <main role=main>
<?php
  }

  public function Close() {
    if(!$this->isopen || $this->isclosed)
      return;
    $this->isclosed = true;
?>
    </main>
    <footer>
      <div id=copyright>© 2017 <?php echo self::SITE_NAME_FULL; ?></div>
    </footer>
  </body>
</html>
<?php
  }
}
?>
