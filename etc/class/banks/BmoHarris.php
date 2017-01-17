<?php
/**
 * Bank functions specific to BMO Harris Bank.
 * @author misterhaan
 *
 */
class BmoHarris extends cyaBank {
  /**
   * Import transactions from a CSV file into an account from BMO Harris Bank.
   * @param string $filename Full path to the CSV file on the server.
   * @param integer $account ID of the account the transactions belong to.
   * @return boolean True if successful.
   */
  public function ImportCsvTransactions($filename, $account) {
    global $ajax, $db;

    if(false !== $fh = fopen($filename, 'r')) {
      // first three lines are headers
      for($i = 0; $i < 3; $i++)
        fgets($fh);

      // transaction makes all these inserts much faster
      $db->real_query('start transaction');

      // prepare and bind a statement
      if(false !== $ins = $db->prepare('insert into transactions (account, extid, posted, name, amount) values (?, ?, ?, ?, ?)'))
        if($ins->bind_param('isssd', $account, $extid, $posted, $name, $amount)) {
          $ajax->Data->count = 0;
          $net = 0;
          while($line = fgetcsv($fh)) {
            // translate the data
            $extid = $line[6];
            $posted = date('Y-m-d', strtotime($line[1]));
            $name = $line[7] == 'Check' ? 'Check ' . $line[5] : self::TitleCase($line[2]);
            $amount = $line[8] == 'Debit' ? -$line[3] : +$line[3];
            $net += $amount;

            if($ins->execute())
              $ajax->Data->count++;
            else
              $ajax->Fail('Error executing transaction import:  ' . $ins->error);
          }
          // close the statement
          $ins->close();
          self::UpdateAccount($account, false, $net);
        } else
          $ajax->Fail('Error binding import parameters:  ' . $ins->error);
      else
        $ajax->Fail('Database error preparing to import transactions:  ' . $db->error);
      $db->real_query('commit');
    } else
      $ajax->Fail('Unable to open file.');
    return false;
  }
}
?>
