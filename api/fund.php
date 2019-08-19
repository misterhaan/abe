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
			<p>Get the list of funds.</p>

			<h2 id=POSTmoveDown>POST moveDown</h2>
			<p>
				Move a fund down in the sort order, switching with the fund
				after it.  All parameters are required.
			</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>ID of the fund to move down.</dd>
			</dl>

			<h2 id=POSTmoveTo>POST moveTo</h2>
			<p>
				Move a fund from its current sort position to just before another fund.
			</p>
			<dl class=parameters>
				<dt>moveId</dt>
				<dd>ID of the fund to move.</dd>
				<dt>beforeId</dt>
				<dd>ID of the fund the moveId fund should be moved before.</dd>
			</dl>

			<h2 id=POSTmoveUp>POST moveUp</h2>
			<p>
				Move a fund up in the sort order, switching with the fund
				before it.  All parameters are required.
			</p>
			<dl class=parameters>
				<dt>id</dt>
				<dd>ID of the fund to move up.</dd>
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
			if($target != 0 || $balance !=0) {
				$db->autocommit(false);
				if($db->real_query('update funds set sort=sort+1 where balance=0 and target=0'))
					if($ins = $db->prepare('insert into funds (name, balance, target, sort) values (?, ?, ?, (select coalesce(max(sort), 0) + 1 from (select sort from funds where balance!=0 or target!=0) as f))'))
						if($ins->bind_param('sdd', $name, $balance, $target))
							if($ins->execute()) {
								$db->commit();
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
						$ajax->Fail('Error adjusting sort order of deactivated funds:  ' . $db->errno . ' ' . $db->error);
			} else
				$ajax->Fail('Funds must have nonzero current balance or target.');
		} else
			$ajax->Fail('Parameters \'name\', \'balance\', and \'target\' must be provided.');
	}

	/**
	 * Close a fund and sort it down to the beginning of the closed funds.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function closeAction($ajax) {
		global $db;
		if(isset($_POST['id']) && ($id = +$_POST['id'])) {
			$db->autocommit(false);
			if($shiftsort = $db->prepare('update funds set sort=sort-1 where sort>(select sort from (select sort from funds where id=?) as f) and (balance>0 or target>0)'))
				if($shiftsort->bind_param('i', $id))
					if($shiftsort->execute())
						if($update = $db->prepare('update funds set balance=0, target=0, sort=(select coalesce(max(sort), 0) + 1 from (select sort from funds where (balance>0 or target>0) and id!=?) as f) where id=?'))
							if($update->bind_param('ii', $id, $id))
								if($update->execute())
									$db->commit();
								else
									$ajax->Fail('Database error executing query to close fund:  ' . $update->errno . ' ' . $update->error);
							else
								$ajax->Fail('Database error binding parameter to close fund:  ' . $update->errno . ' ' . $update->error);
						else
							$ajax->Fail('Database error preparing to close fund:  ' . $db->errno . ' ' . $db->error);
					else
						$ajax->Fail('Database error executing query to adjust sort order:  ' . $shiftsort->errno . ' ' . $shiftsort->error);
				else
					$ajax->Fail('Database error binding parameter to adjust sort order:  ' . $shiftsort->errno . ' ' . $shiftsort->error);
			else
				$ajax->Fail('Database error preparing to adjust sort order:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Parameter \'id\' must be provided and non-empty.');
	}

	/**
	 * Get the list of all funds.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function listAction($ajax) {
		global $db;
		$ajax->Data->funds = [];
		if($funds = $db->query('select id, name, balance, target from funds order by sort'))
			while($fund = $funds->fetch_object()) {
				$fund->id += 0;
				$fund->balance += 0;
				$fund->target += 0;
				$fund->balanceDisplay = abeFormat::Amount($fund->balance);
				$fund->targetDisplay = abeFormat::Amount($fund->target);
				$ajax->Data->funds[] = $fund;
			}
		else
			$ajax->Fail('Error looking up funds:  ' . $db->errno . ' ' . $db->error);
	}

	/**
	 * Action to move a fund down in the sort order.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function moveDownAction($ajax) {
		global $db;
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db->autocommit(false);
			if($swap = $db->prepare('update funds set sort=sort-1 where sort=(select sort+1 from (select sort from funds where id=? limit 1) as f) limit 1'))
				if($swap->bind_param('i', $id))
					if($swap->execute())
						if($swap = $db->prepare('update funds set sort=sort+1 where id=? limit 1'))
							if($swap->bind_param('i', $id))
								if($swap->execute())
									$db->commit();
								else
									$ajax->Fail('Database error moving fund down:  ' . $db->errno . ' ' . $db->error);
							else
								$ajax->Fail('Database error binding parameter to move fund down:  ' . $db->errno . ' ' . $db->error);
						else
							$ajax->Fail('Database error preparing to move fund down:  ' . $db->errno . ' ' . $db->error);
					else
						$ajax->Fail('Database error moving next fund up:  ' . $db->errno . ' ' . $db->error);
				else
					$ajax->Fail('Database error binding parameter to move next fund up:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Database error preparing to move next fund up:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Required parameter missing or invalid.  Provide a numeric id to move.');
	}

	/**
	 * Action to move a fund before another fund in the sort order.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function moveToAction($ajax) {
		global $db;
		if(isset($_POST['moveId']) && isset($_POST['beforeId']) && ($moveid = +$_POST['moveId']) && $beforeid = +$_POST['beforeId']) {
			$db->autocommit(false);
			if($moveOthers = $db->prepare('update funds set sort=sort-1 where sort>(select sort from (select sort from funds where id=? limit 1) as f)'))
				if($moveOthers->bind_param('i', $moveid))
					if($moveOthers->execute())
						if($moveOthers = $db->prepare('update funds set sort=sort+1 where sort>=(select sort from (select sort from funds where id=? limit 1) as f)'))
							if($moveOthers->bind_param('i', $beforeid))
								if($moveOthers->execute())
									if($moveFund = $db->prepare('update funds set sort=(select sort-1 from (select sort as newsort from funds where id=? limit 1) as f) where id=? limit 1'))
										if($moveFund->bind_param('ii', $beforeid, $moveid))
											if($moveFund->execute())
												$db->commit();
											else
												$ajax->Fail('Database error moving fund:  ' . $moveFund->errno . ' ' . $moveFund->error);
										else
											$ajax->Fail('Database error binding parameters to move fund:  ' . $moveFund->errno . ' ' . $moveFund->error);
									else
										$ajax->Fail('Database error preparing to move fund:  ' . $db->errno . ' ' . $db->error);
								else
									$ajax->Fail('Database error moving other funds down:  ' . $moveOthers->errno . ' ' . $moveOthers->error);
							else
								$ajax->Fail('Database error binding parameter to move other funds down:  ' . $moveOthers->errno . ' ' . $moveOthers->error);
						else
							$ajax->Fail('Database error preparing to other funds down:  ' . $db->errno . ' ' . $db->error);
					else
						$ajax->Fail('Database error moving other funds up:  ' . $moveOthers->errno . ' ' . $moveOthers->error);
				else
					$ajax->Fail('Database error binding parameter to move other funds up:  ' . $moveOthers->errno . ' ' . $moveOthers->error);
			else
				$ajax->Fail('Database error preparing to move other funds up:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Required parameters \'moveId\' and \'beforeId\' must be present and nonzero numeric.');
	}

	/**
	 * Action to move a fund up in the sort order.
	 * @param abeAjax $ajax Ajax object for returning data or reporting an error.
	 */
	protected static function moveUpAction($ajax) {
		global $db;
		if(isset($_POST['id']) && $id = +$_POST['id']) {
			$db->autocommit(false);
			if($swap = $db->prepare('update funds set sort=sort+1 where sort=(select sort-1 from (select sort from funds where id=? limit 1) as f) limit 1'))
				if($swap->bind_param('i', $id))
					if($swap->execute())
						if($swap = $db->prepare('update funds set sort=sort-1 where id=? limit 1'))
							if($swap->bind_param('i', $id))
								if($swap->execute())
									$db->commit();
								else
									$ajax->Fail('Database error moving fund up:  ' . $db->errno . ' ' . $db->error);
							else
								$ajax->Fail('Database error binding parameter to move fund up:  ' . $db->errno . ' ' . $db->error);
						else
							$ajax->Fail('Database error preparing to move fund up:  ' . $db->errno . ' ' . $db->error);
					else
						$ajax->Fail('Database error moving previous fund down:  ' . $db->errno . ' ' . $db->error);
				else
					$ajax->Fail('Database error binding parameter to move previous fund down:  ' . $db->errno . ' ' . $db->error);
			else
				$ajax->Fail('Database error preparing to move previous fund down:  ' . $db->errno . ' ' . $db->error);
		} else
			$ajax->Fail('Required parameter missing or invalid.  Provide a numeric id to move.');
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
