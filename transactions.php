<?php
require_once __DIR__ . '/etc/class/cya.php';

define('MAX_TRANS', 50);

if(isset($_GET['ajax'])) {
  $ajax = new cyaAjax();
  switch($_GET['ajax']) {
    case 'get': GetTransactions(); break;
  }
  $ajax->Send();
  die;
}

$html = new cyaHtml();
$html->AddAction('import.php', 'import', 'Import', 'Import transactions');
$html->Open('Transactions');
?>
      <h1>Transactions</h1>
      <ol id=transactions>
        <!-- ko foreach: dates -->
          <li class=date>
            <header><time data-bind="text: displayDate"></time></header>
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
        <li class=calltoaction data-bind="visible: more"><a href="#GetTransactions" data-bind="click: GetTransactions">Load more</a></li>
      </ol>
<?php
$html->Close();

function GetTransactions() {
  global $ajax, $db;
  if($ts = $db->query('select t.id, t.posted, at.class as acctclass, t.name, c.name as category, t.amount from transactions as t left join categories as c on c.id=t.category left join accounts as a on a.id=t.account left join account_types as at on at.id=a.account_type where t.posted<\'' . $db->escape_string($_GET['oldest']) . '\' or t.posted=\'' . $db->escape_string($_GET['oldest']) . '\' and t.id<\'' . $db->escape_string($_GET['oldid']) . '\' order by t.posted desc, t.id desc limit ' . MAX_TRANS)) {
    $ajax->Data->dates = [];
    $posted = '';
    $id = 0;
    while($t = $ts->fetch_object()) {
      $displayDate = date('F j, Y (D)', strtotime($t->posted . ' 12:00 PM'));
      if(!count($ajax->Data->dates) || $ajax->Data->dates[count($ajax->Data->dates) - 1]->date != $t->posted)
        $ajax->Data->dates[] = (object)['date' => $t->posted, 'displayDate' => $displayDate, 'transactions' => []];
      $ajax->Data->dates[count($ajax->Data->dates) - 1]->transactions[] = unserialize(serialize($t)); // ['id' => $id, 'posted' => $posted, 'name' => $name, 'category' => $category, 'amount' => $amount];
      $posted = $t->posted;
      $id = $t->id;
    }
    $ajax->Data->more = false;
    if($more = $db->query('select 1 from transactions where posted<\'' . $db->escape_string($posted) . '\' or posted=\'' . $db->escape_string($posted) . '\' and id<\'' . $db->escape_string($id) . '\' order by posted desc, id desc limit 1'))
      if($more->num_rows)
        $ajax->Data->more = true;
  } else
    $ajax->Fail('Error looking up transactions:  ' . $db->error);
}
?>
