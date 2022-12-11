<? // find-hanging-credits.php


require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "credit-fns.php";


// exit;



$locked = locked('z-');

$databases = fetchCol0("SHOW DATABASES");

foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		echo "Not able to connect: invalid database username and/or password.\n";
	}
	$lnk1 = mysqli_select_db($db);
	if(mysqli_error()) echo mysqli_error();
	$tables = fetchCol0("SHOW TABLES");
	if(!in_array('tblbillable', $tables)) continue;
	findHangingCredits();
}



function findClientBalance($clientid) {
	$credits = getClientCredits($clientid, $nonZero=1);
	$unpaidSum = fetchRow0Col0(
		"SELECT sum(charge - paid) 
		 FROM tblbillable
		 WHERE clientptr = $clientid AND superseded = 0");
	if(((int)$unpaidSum*100) && $credits) {
		foreach($credits as $credit) 
			$totalCredit += $credit['amount'] - $credit['amountused'];
		return array('credit'=>$totalCredit, 'unpaid'=>$unpaidSum);
	}
}

function findHangingCredits() {
	global $db;
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	echo "<hr><b>$bizName ($db)</b><p>";
	foreach(fetchAssociations("SELECT * FROM tblclient") as $client) {
		$problem = findClientBalance($client['clientid']);
		if($problem) {
			if(!$problems) 	echo "<table width=50%><tr><th>Client<th>Avail Credit<th>Unpaid Billables";
			echo "<tr><td>{$client['fname']} {$client['lname']} [{$client['clientid']}]"
												."<td>{$problem['credit']}<td>{$problem['unpaid']}";
			$problems++;
		}
	}
	if($problems) echo "</table>";
	else "No Hanging credits found.";
}