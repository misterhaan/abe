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
              <li class=transaction data-bind="css: acctclass, click: $root.Select">
                <div>
                  <div class=name data-bind="text: name"></div>
                  <div class=category data-bind="text: category ? category : '(uncategorized)'"></div>
                </div>
                <div class=amount data-bind="text: amount"></div>
                <div class=full data-bind="visible: $root.selection() == $data">
                  <div class=transaction>
                    <div>
                      <div class=name data-bind="text: name"></div>
                    </div>
                    <div class=amount data-bind="text: amount"></div>
                    <a class=close data-bind="click: $root.SelectNone"><span>close</span></a>
                  </div>
                  <div>
                    <div class=category data-bind="text:category ? category : '(uncategorized)'"></div>
                    <div class=account data-bind="css: acctclass, text: acctname"></div>
                    <div class=transdate data-bind="visible: transdate">Transaction <time data-bind="text: transdate"></time></div>
                    <div class=posted>Posted <time data-bind="text: posted"></time></div>
                    <div class=note data-bind="visible: notes, text: notes"></div>
                    <div class=location data-bind="visible: city, text: city + (state ? ', ' + state + (zip ? ' ' + zip : '') : '')"></div>
                  </div>
                </div>
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
  $ts = 'select t.id, t.posted, t.transdate, at.class as acctclass, a.name as acctname, t.name, c.name as category, t.amount, t.notes, t.city, t.state, t.zip from transactions as t left join categories as c on c.id=t.category left join accounts as a on a.id=t.account left join account_types as at on at.id=a.account_type where ' . (isset($_GET['acct']) && +$_GET['acct'] ? 't.account=\'' . +$_GET['acct'] . '\' and ' : '') . '(t.posted<\'' . $db->escape_string($_GET['oldest']) . '\' or t.posted=\'' . $db->escape_string($_GET['oldest']) . '\' and t.id<\'' . $db->escape_string($_GET['oldid']) . '\') order by t.posted desc, t.id desc limit ' . MAX_TRANS;
  if($ts = $db->query($ts)) {
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
