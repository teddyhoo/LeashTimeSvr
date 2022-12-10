<? // reports-textable-clients.php
// for the clients supplied, dump a line for each textable phone number they possess
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "field-utils.php";

$locked = locked('o-');

$status = $_REQUEST['status'];
$status = $status == 'active' ? "AND active = 1" : ($status == 'inactive' ? "AND active = 0" : '1=1');
$clientids = $_REQUEST['ids'] ? explode(',', $_REQUEST['ids']) : (
						$_REQUEST['filteredlist'] && $_SESSION['clientListIDString'] ? explode(',', $_SESSION['clientListIDString']) 
						: fetchCol0("SELECT clientid FROM tblclient WHERE $status"));
						
$clientids = $_REQUEST['ids'] ? $_REQUEST['ids'] : $_SESSION['clientListIDString'];
//unset($_SESSION['clientListIDString']);

if(!$clientids) {
	echo "No clients with textable phones found.";
	return;
}
						

$result = doQuery(
	"SELECT fname, lname, CONCAT_WS(',', fname, lname) as fullName, homephone, cellphone, workphone, cellphone2
		FROM tblclient
		WHERE clientid IN ($clientids)
		ORDER BY lname, fname
	", 1);
	
$numbers = array();
while($row = fetchResultAssoc($result)) {
	foreach($row as $k => $v) {
		if(strpos($k, 'phone') === FALSE) continue;
		$p = strpos("$v", 'T');
		// canonicalUSPhoneNumber, usablePhoneNumber, strippedPhoneNumber
		if($p !== FALSE
			 && strlen($canonicalUSPhoneNumber = canonicalUSPhoneNumber($v)) == 12
			 && !$numbers[$canonicalUSPhoneNumber]) {
			 	$numbers[$canonicalUSPhoneNumber] = 1;
				echo "\"{$row['fname']}\",\"{$row['lname']}\",\"".canonicalUSPhoneNumber($v)."\"<br>";
		}
	}
}