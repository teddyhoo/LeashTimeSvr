<? // reports-foreign-payments.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if(!dbTEST('leashtimecustomers')) {
	echo "WRONG DB! ($db)";
	exit;
}

// find foreign customers
$bizids = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient WHERE garagegatecode IS NOT NULL", 1);

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require_once "common/init_db_common.php";
$bizzes = fetchAssociationsKeyedBy(
	"SELECT * 
		FROM tblpetbiz 
		WHERE bizid IN (".join(',', array_keys($bizids)).")
		AND  country IS NOT NULL AND country != 'US'", 'bizid', 1);
//foreach($bizzes as $bizid => $biz)
//	echo "($bizid) {$biz['bizname']}<br>";
foreach($bizzes as $bizid => $biz) {
	$foreignClients[$bizids[$bizid]] = $bizid;
	//echo "($bizid) [{$biz['state']}, {$biz['country']}] {$biz['bizname']}<br>";
}
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
$year = $_GET['year'];
$payments = fetchKeyValuePairs(
	"SELECT clientptr, SUM(amount) 
		FROM tblcredit
		WHERE payment = 1
			AND clientptr IN (".join(',', array_keys($foreignClients)).")
			AND issuedate >= '$year-01-01' AND issuedate <= '$year-12-31'
		GROUP BY clientptr", 1);
$credits = fetchKeyValuePairs(
	"SELECT clientptr, SUM(amount) 
		FROM tblcredit
		WHERE payment = 0
			AND clientptr IN (".join(',', array_keys($foreignClients)).")
			AND issuedate >= '$year-01-01' AND issuedate <= '$year-12-31'
		GROUP BY clientptr", 1);

echo "Source: reports-foreign-payments.php?year=$year<p>";
echo "\"Reminder: report payments, not net.\"<p>";
echo "Country,Business,Payments,Credits,Net<br>";
foreach($foreignClients as $clientptr => $bizid) {
	if(!$payments[$clientptr]) continue;
	$biz = $bizzes[$bizid];
	$diff = (0+$payments[$clientptr]) - (0+$credits[$clientptr]);
	//echo "($bizid) [{$biz['state']}, {$biz['country']}] {$biz['bizname']} {$payments[$clientptr]} -  {$credits[$clientptr]} = $diff<br>";
	echo "{$biz['country']},{$biz['bizname']},{$payments[$clientptr]},{$credits[$clientptr]},$diff<br>";
}