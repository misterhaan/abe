<?php
require_once __DIR__ . '/etc/class/cya.php';

if(isset($_GET['ajax'])) {
  $ajax = new cyaAjax();
  switch($_GET['ajax']) {
    case 'accountlist': GetAccountList(); break;
  }
  $ajax->Send();
  die;
}

$html = new cyaHtml();
$html->AddAction('account.php', 'add', '+', 'Add another account');
$html->Open('Accounts');
?>
      <section id=accountlist data-bind="foreach: accounts">
        <div class=account>
          <h2 data-bind="text: name"></h2>
          <div class=detail>
            <span class=bankname data-bind="text: bankname"></span>
            <span class=balance data-bind="text: balance"></span>
          </div>
          <div class=actions>
            <a class=bank data-bind="attr: {href: bankurl}" title="Visit this account’s bank website"><span>bank</span></a>
            <a class=import data-bind="attr: {href: 'import.php?acct=' + id}" title="Import transactions to this account"><span>import</span></a>
            <a class=edit data-bind="attr: {href: 'account.php?id=' + id}"><span>edit</span></a>
          </div>
        </div>
      </section>
<?php
$html->Close();

function GetAccountList() {
  global $ajax, $db;
  if($accts = $db->query('select a.id, a.name, b.name as bankname, b.url as bankurl, a.balance from accounts as a left join banks as b on b.id=a.bank where not a.closed order by a.name')) {
    $ajax->Data->accounts = [];
    while($acct = $accts->fetch_object())
      $ajax->Data->accounts[] = $acct;
  } else
    $ajax->Fail('Error looking up account list:  ' . $db->error);
}
?>
