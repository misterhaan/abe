<?php
require_once __DIR__ . '/etc/class/cya.php';

if(isset($_GET['ajax'])) {
  $ajax = new cyaAjax();
  switch($_GET['ajax']) {
    case 'banklist': GetBankList(); break;
    case 'get':      GetAccount(); break;
    case 'save':     SaveAccount(); break;
  }
  $ajax->Send();
  die;
}

$html = new cyaHtml();
$html->Open('Account');
?>
      <h1 data-bind="text: id ? name : 'Add account'"></h1>
      <form id=editaccount data-bind="submit: Save">
        <label>
          <span class=label>Name:</span>
          <span class=field><input data-bind="textInput: name" maxlength=32 required></span>
        </label>
        <label>
          <span class=label>Bank:</span>
          <span class=field><select data-bind="value: bank, options: banklist, optionsText: 'name', optionsValue: 'id'">
            <option data-bind="text: name, attr: {value: id}"></option>
          </select></span>
        </label>
        <label>
          <span class=label>Balance:</span>
          <span class=field><input id=balance type=number step=.01 data-bind="value: balanceFormatted" required></span>
        </label>
        <label>
          <span class=label>Closed:</span>
          <span class=field><input type=checkbox data-bind="checked: closed"></span>
        </label>
        <button>Save</button>
      </form>
<?php
$html->Close();

function GetBankList() {
  global $ajax, $db;
  $ajax->Data->banks = [];
  $ajax->Data->banks[] = (object)['id' => false, 'name' => ''];
  if($banks = $db->query('select id, name from banks order by name'))
    while($bank = $banks->fetch_object())
      $ajax->Data->banks[] = $bank;
  else
    $ajax->Fail('Error looking up supported banks.');
}

function GetAccount() {
  global $ajax, $db;
  if($acct = $db->query('select id, bank, name, balance, closed from accounts where id=' . +$_GET['id'] . ' limit 1'))
    if($acct = $acct->fetch_object())
      $ajax->Data = $acct;
    else
      $ajax->Fail('Account not found.');
  else
    $ajax->Fail('Error looking up account:  ' . $db->error);
}

function SaveAccount() {
  global $ajax, $db;
  $id = isset($_POST['id']) && $_POST['id'] ? +$_POST['id'] : false;
  $set = 'accounts set bank=?, name=?, balance=?, closed=?';
  if($id)
    if($put = $db->prepare('update ' . $set . ' where id=? limit 1'))
      if($put->bind_param('isdii', $bank, $name, $balance, $closed, $id))
        ;  // good
      else
        $ajax->Fail('Error binding parameters for account update:  ' . $put->error);
    else
      $ajax->Fail('Error preparing to update account:  ' . $db->error);
  else
    if($put = $db->prepare('insert into ' . $set))
      if($put->bind_param('isdi', $bank, $name, $balance, $closed))
        ;  // good
      else
        $ajax->Fail('Error binding parameters for account insert:  ' . $put->error);
    else
      $ajax->Fail('Error preparing to insert account:  ' . $db->error);
  if(!$ajax->Data->fail) {
    $bank = +$_POST['bank'];
    $name = trim($_POST['name']);
    $balance = +$_POST['balance'];
    $closed = $_POST['closed'] ? 1 : 0;
    if(!$put->execute())
      $ajax->Fail('Error saving account:  ' . $put->error);
    $put->close();
  }
}
?>
