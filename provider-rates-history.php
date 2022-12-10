<? // provider-rates-history.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require "zip-lookup.php";
require "provider-fns.php";
require "preference-fns.php";
require "service-fns.php";
require "pay-fns.php";
require_once "system-login-fns.php";


// Determine access privs
$locked = locked('+o-,+d-,#as');

$id = $_REQUEST['id'];

$pname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) AS name FROM  tblprovider WHERE providerid = $id");

echo "<h2>$pname Rate Changes</h2>";
$changes = fetchAssociations("SELECT * FROM tblchangelog WHERE itemptr = $id AND itemtable = 'relproviderrate' ORDER BY time");

if(!$changes) {
	echo "No rate change history found.";
}
list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require_once "common/init_db_common.php";
foreach($changes as $change) $userids[] = $change['user'];
$users = fetchKeyValuePairs("SELECT userid, CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid IN (".join(',', $userids).")");
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');

$serviceTypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
foreach($changes as $change) {
	$row = array();
	$row['time'] = shortDate(strtotime($change['time'])).' '.substr($change['time'], 11);
	if(strpos($change['note'], '|')) {
		$note = explode('|', $change['note']);
		$row['Service'] = $serviceTypes[$note[0]];
	}
	else {
		$note = $change['note'];
		$row['Service'] = 'various';
	}
	$op = $change['operation'];
	if($op == 'c') $row['Note'] = 'created: '.($note[2] ? "{$note[1]} %" : dollarAmount($note[1])); //$servType|$val|$ispercentage
	else if($op == 'd') $row['Note'] = 'deleted: $note';
	else { // 'm'
		$row['Note'] = "{$note[1]} => {$note[2]}";
	}
	$row['User'] = $users[$change['user']];
	$rows[] = $row;
}
quickTable($rows, "border=1");
		
