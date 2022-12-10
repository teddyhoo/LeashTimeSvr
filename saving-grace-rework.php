<? // saving-grace-rework.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($db != 'savinggrace') {echo "WRONG DB"; exit; }

/*
$keyless = fetchCol0("SELECT clientid FROM tblclient LEFT JOIN tblkey ON clientptr = clientid WHERE keyid IS NULL");
$all = fetchKeyValuePairs("SELECT clientptr, externalptr FROM tempClientMap");

foreach($keyless as $id)
	insertTable('tblkey', array('clientptr'=>$id, 'copies'=>'0'), 1);
	
foreach($all as $id => $bw) {
	$key = fetchFirstAssoc("SELECT * FROM tblkey WHERE clientptr = $id LIMIT 1");
	updateTable('tblkey', array('description'=>$bw), "clientptr = $id", 1);
}
*/
$custom1s = fetchKeyValuePairs("SELECT clientptr, value FROM relclientcustomfield WHERE value AND fieldname = 'custom1'");
//print_r($custom1s);
foreach($custom1s as $id => $cellphone2) {
	echo "$id.cellphone2 = $cellphone2<br>";
	updateTable('tblclient', array('cellphone2'=>$cellphone2), "clientid = $id", 1);
}