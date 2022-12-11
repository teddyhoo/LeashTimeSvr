<? // fix-watchdog.php

echo "HAMSTRUNG";
exit;



require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($db != 'watchdogpetsitting') {
echo "WRONG DB: $db!";
exit;
}

$file = "/var/data/clientimports/watchdogpetsetting/customer.csv";
$strm = fopen($file, 'r');

fgetcsv($strm, 0, ",");
$clientids = fetchKeyValuePairs("SELECT * FROM tempClientMap");
$crlf = "\n";

while($row = fgetcsv($strm, 0, ",")) {
	$clientid = $clientids[$row[0]];
	if(!$clientid) echo "<font color=gray>No client found for ID: [{$row[0]}]</font><br>";
	else {
		$name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientid");
		$x = mysqli_real_escape_string($row[14]);
		if($x) {
			echo "ID: [{$row[0]}] ClientID: [$clientid] -- $name [{$row[14]}]<br>";
			//insertTable('tblclientpref', array('clientptr'=>$clientid, 'property'=>"flag_1", "value"=>"1|$x"), 1);

			//updateTable('tblclient',
			//						array('officenotes'=>array("CONCAT_WS(' ', officenotes, '$x')")) ,
			//						"clientid = $clientid", 1);
			if($clientid != 1624) updateTable('tblclient',
									array('garagegatecode'=>$x) ,
									"clientid = $clientid", 1);
								}
	}
}

function flagClientWith($clientid, $note) {
	for($i=1; $flag = getClientPreference($clientid, "flag_$i"); $i++) {
		$id = ($divider = strpos($flag, '|')) ? substr($flag, 0, $divider) : $flag;
		if($id == $flagid) return;
	}
	insertTable('tblclientpref', array('clientptr'=>$clientid, 'property'=>"flag_$i", "value"=>"$flagid|$note"), 1);
}
