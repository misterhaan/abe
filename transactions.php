<?php
require_once __DIR__ . '/etc/class/cya.php';

define('MAX_TRANS', 50);

if(isset($_GET['ajax'])) {
  $ajax = new cyaAjax();
  switch($_GET['ajax']) {
    case 'get': GetTransactions(); break;
    case 'save': SaveTransactions(); break;
  }
  $ajax->Send();
  die;
}

$html = new cyaHtml();
if(isset($_GET['acct']))
  $html->SetBack('accounts.php');
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
                  <div class=name data-bind="visible: !$root.editing() || $root.selection(), text: name"></div>
                  <label class=name data-bind="visible: $root.editing() && !$root.selection(), click: $root.CaptureClick"><input data-bind="value: name"></label>
                  <div class=category data-bind="visible: !$root.editing() || $root.selection(), text: category() ? category() : '(uncategorized)'"></div>
                  <label class=category data-bind="visible: $root.editing() && !$root.selection(), click: $root.CaptureClick"><input data-bind="value: category"></label>
                </div>
                <div class=amount data-bind="text: amount"></div>
                <div class=full data-bind="visible: $root.selection() == $data, scrollTo: $root.selection() == $data">
                  <div class=transaction>
                    <div>
                      <div class=name data-bind="visible: !$root.editing(), text: name"></div>
                      <label class=name data-bind="visible: $root.editing, click: $root.CaptureClick"><input data-bind="value: name"></label>
                    </div>
                    <div class=amount data-bind="text: amount"></div>
                    <a class=close data-bind="click: $root.SelectNone"><span>close</span></a>
                  </div>
                  <div class=details>
                    <div class=category data-bind="visible: !$root.editing(), text:category() ? category() : '(uncategorized)'"></div>
                    <label class=category data-bind="visible: $root.editing"><input data-bind="value: category"></label>
                    <div class=account data-bind="css: acctclass, text: acctname"></div>
                    <div class=transdate data-bind="visible: transdate">Transaction <time data-bind="text: transdate"></time></div>
                    <div class=posted>Posted <time data-bind="text: posted"></time></div>
                    <div class=note data-bind="visible: notes() && !$root.editing(), text: notes"></div>
                    <label class=note data-bind="visible: $root.editing"><input data-bind="value: notes"></label>
                    <div class=location data-bind="visible: city, text: city + (state ? ', ' + state + (zip ? ' ' + zip : '') : '')"></div>
                    <a class=edit href="#edit" data-bind="visible: !$root.editing(), click: $root.Edit"><span>edit</span></a>
                    <a class=save href="#save" data-bind="visible: $root.editing() && !$root.saving(), click: $root.Save"><span>save</span></a>
                    <span class=working data-bind="visible: $root.saving"></span>
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

function SaveTransactions() {
  global $ajax, $db;
  if($update = $db->prepare('update transactions set name=?, notes=?, category=GetCategoryID(?), reviewed=1 where id=? limit 1'))
    if($update->bind_param('sssi', $name, $notes, $catname, $id)) {
      foreach($_POST['transactions'] as $t) {
        $name = $t['name'];
        $notes = $t['notes'];
        $catname = $t['category'];
        $id = +$t['id'];
        if(!$update->execute())
          $ajax->Fail('Error executing update for transaction:  ' . $update->error);
      }
      $update->close();
    } else
      $ajax->Fail('Error binding transactions parameters:  ' . $update->error);
  else
    $ajax->Fail('Error preparing to update transactions:  ' . $db->error);
}
?>
