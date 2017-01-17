<?php
require_once __DIR__ . '/etc/class/cya.php';

$html = new cyaHtml();
$html->Open(cyaHtml::SITE_NAME_FULL);
?>
      <h1><?php echo cyaHtml::SITE_NAME_FULL; ?></h1>
      <nav id=mainmenu>
        <a href=accounts.php>Accounts</a>
      </nav>

      <section id=last3trans>
        <h2>Latest Transactions</h2>
        <ol id=transactions>
          <!-- ko foreach: dates -->
            <li class=date>
              <ul data-bind="foreach: transactions">
                <li class=transaction data-bind="css: acctclass">
                  <div>
                    <div class=name data-bind="text: name"></div>
                    <div class=category data-bind="text: category ? category : '(uncategorized)'"></div>
                  </div>
                  <div class=amount data-bind="text: amount"></div>
                </li>
              </ul>
            </li>
          <!-- /ko -->
          <li class=loading data-bind="visible: loading">Loading...</li>
          <li id=alltrans><a href="transactions.php">All Transactions</a></li>
        </ol>
      </section>
<?php
$html->Close();
?>
