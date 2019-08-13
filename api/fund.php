<?php
require_once dirname(__DIR__) . '/etc/class/abe.php';

/**
 * Handler for fund API requests.
 * @author misterhaan
 */
class FundApi extends abeApi {
	/**
	 * Write out the documentation for the fund API controller.  The page is
	 * already opened with an h1 header, and will be closed after the call
	 * completes.
	 */
	protected static function ShowDocumentation() {
?>
			<h2 id=POSTadd>POST add</h2>
			<p>
				Add a new fund.
			</p>
			<dl class=parameters>
				<dt>name</dt>
				<dd>
					Name of the new fund.
				</dd>
				<dt>balance</dt>
				<dd>
					Current balance of the fund.
				</dd>
				<dt>target</dt>
				<dd>
					Target balance for the fund.
				</dd>
			</dl>

			<h2 id=POSTclose>POST close</h2>
			<p>
				Mark a fund closed, which means setting its balance and target to zero.
			</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the fund to close.
				</dd>
			</dl>

			<h2 id=GETlist>GET list</h2>
			<p>Get the list of active funds.</p>

			<h2 id=GETlist>GET listClosed</h2>
			<p>Get the list of inactive funds.</p>

			<h2 id=POSTreopen>POST reopen</h2>
			<p>
				Reopen a fund with a fresh balance and target.
			</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the fund to reopen.
				</dd>
				<dt>name</dt>
				<dd>
					New name of the fund.
				</dd>
				<dt>balance</dt>
				<dd>
					Current balance of the fund.
				</dd>
				<dt>target</dt>
				<dd>
					Target balance for the fund.
				</dd>
			</dl>

			<h2 id=POSTsave>POST save</h2>
			<p>
				Save changes to a fund.
			</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>
					ID of the fund to save.
				</dd>
				<dt>name</dt>
				<dd>
					New name of the fund.
				</dd>
				<dt>balance</dt>
				<dd>
					Current balance of the fund.
				</dd>
				<dt>target</dt>
				<dd>
					Target balance for the fund.
				</dd>
			</dl>
<?php
	}

	/**
	 * Add a new fund.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function addAction($ajax) {
		global $db;
		if(isset($_POST['name']) && isset($_POST['target']) && $name = trim($_POST['name'])) {
			$target = round(+$_POST['target'], 2);
			$balance = round(+$_POST['balance'], 2);
			if($target != 0 || $balance !=0)
				if($ins = $db->prepare('insert into funds (name, balance, target, sort) values (?, ?, ?, (select coalesce(max(sort), 0) + 1 from (select sort from funds where balance!=0 or target!=0) as f))'))
					if($ins->bind_param('sdd', $name, $balance, $target))
						if($ins->execute()) {
							$ajax->Data->id = $ins->insert_id;
							$ajax->Data->balanceDisplay = abeFormat::Amount($balance);
							$ajax->Data->targetDisplay = abeFormat::Amount($target);
						} else
							$ajax->Fail('Error executing query to create fund:  ' . $ins->errno . ' ' . $ins->error);
					else
						$ajax->Fail('Error binding parameters to create fund:  ' . $ins->errno . ' ' . $ins->error);
				else
					$ajax->Fail('Error preparing to create fund:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Funds must have nonzero current balance or target.');
		} else
			$ajax->Fail('Parameters \'name\', \'balance\', and \'target\' must be provided.');
	}

	/**
	 * Get the list of all open funds.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction($ajax) {
		global $db;
		$ajax->Data->funds = [];
		if($funds = $db->query('select id, name, sort, balance, target from funds where balance!=0 or target!=0 order by sort'))
			while($fund = $funds->fetch_object()) {
				$fund->balanceDisplay = abeFormat::Amount($fund->balance);
				$fund->targetDisplay = abeFormat::Amount($fund->target);
				$ajax->Data->funds[] = $fund;
			}
		else
			$ajax->Fail('Error looking up funds:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Get the list of all closed funds.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listClosedAction($ajax) {
		global $db;
		$ajax->Data->funds = [];
		if($funds = $db->query('select id, name from funds where balance=0 and target=0 order by name'))
			while($fund = $funds->fetch_object())
				$ajax->Data->funds[] = $fund;
		else
			$ajax->Fail('Error looking up closed funds:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Reopen a previously closed fund.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function reopenAction($ajax) {
		global $db;
		// TODO:  set sort?  maybe that's actually it.
		if(isset($_POST['id']) && ($id = +$_POST['id'])
				&& isset($_POST['name']) && ($name = trim($_POST['name']))
				&& isset($_POST['balance']) && isset($_POST['target'])) {
			$balance = round(+$_POST['balance'], 2);
			$target = round(+$_POST['target'], 2);
			if($balance !=0 || $target != 0)
				if($u = $db->prepare('update funds set name=?, balance=?, target=? where id=? limit 1'))
					if($u->bind_param('sddi', $name, $balance, $target, $id))
						if($u->execute()) {
							$ajax->Data->balanceDisplay = abeFormat::Amount($balance);
							$ajax->Data->targetDisplay = abeFormat::Amount($target);
						} else
							$ajax->Fail('Error executing query to save fund:  ' . $u->errno . ' ' . $u->error);
					else
						$ajax->Fail('Error binding parameters to save fund:  ' . $u->errno . ' ' . $u->error);
				else
					$ajax->Fail('Error preparing to save fund:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Funds must have nonzero current balance or target to reopen.');
		} else
			$ajax->Fail('Parameters \'id\', \'name\', \'balance\', and \'target\' must be provided and non-empty.');
	}

	/**
	 * Save changes to a fund.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function saveAction($ajax) {
		global $db;
		if(isset($_POST['id']) && ($id = +$_POST['id'])
				&& isset($_POST['name']) && ($name = trim($_POST['name']))
				&& isset($_POST['balance']) && isset($_POST['target'])) {
			$balance = round(+$_POST['balance'], 2);
			$target = round(+$_POST['target'], 2);
			if($balance != 0 || $target != 0)
				if($u = $db->prepare('update funds set name=?, balance=?, target=? where id=? limit 1'))
					if($u->bind_param('sddi', $name, $balance, $target, $id))
						if($u->execute()) {
							$ajax->Data->balanceDisplay = abeFormat::Amount($balance);
							$ajax->Data->targetDisplay = abeFormat::Amount($target);
						} else
							$ajax->Fail('Error executing query to save fund:  ' . $u->errno . ' ' . $u->error);
					else
						$ajax->Fail('Error binding parameters to save fund:  ' . $u->errno . ' ' . $u->error);
				else
					$ajax->Fail('Error preparing to save fund:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Funds must have nonzero current balance or target.');
		} else
			$ajax->Fail('Parameters \'id\', \'name\', \'balance\', and \'target\' must be provided and non-empty.');
	}
}
FundApi::Respond();
