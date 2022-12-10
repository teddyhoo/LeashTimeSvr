<? // dispatchers.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
$locked = locked('o-#ed');  // add this to the rights table? (edit dispatchers)

if($dispatcher) {  // lightbox filler -- description of a dispatcher class
	exit;
}


$dispatchers = getDispatchers();

extract(extractVars('dclass', $_REQUEST));

$pageTitle = "Dispatchers";

include "frame.html";
// ***************************************************************************
$columns = explodePairsLine('name|Dispatcher||class|Type||');
$rows = array();
foreach($dispatchers as $dispatcher) {
	$row = 






// **************************************
function getDispatchers() {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$dispatchers = fetchAssociationsKeyedBy(
		"SELECT *, CONCAT_WS(' ', fname, lname) as name 
		FROM tbluser 
		WHERE orgptr = -1 AND bizptr = {$_SESSION['bizptr']} AND rights LIKE 'd-%'
		ORDER BY lname, fname", 'userid');
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	return $dispatchers;
}
