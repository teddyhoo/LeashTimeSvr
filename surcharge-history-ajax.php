<?
/* surcharge-history-ajax.php
*
* id - id of appointment to be edited
*
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "surcharge-fns.php";
require_once "service-fns.php";
require_once "petpick-grid.php";
require_once "time-framer-mouse.php";
require_once "pet-fns.php";
require_once "discount-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('ea');
extract($_REQUEST);

if(!$id) {
	echo "Surcharge ID not specified.";
	exit;
}
else $appt = getSurcharge($id, $withNames=true, $withPayableData=false, $withBillableData=false);
$dates = array();
$dates[] = array($appt['created'], "Created by ".getUserNameWithTip($appt['createdby']));
//$dates[] = array($appt['modified'], "Last Modified by ".getUserName($appt['modifiedby']));
if($billable) {
	$dates[] = array($billable['billabledate'], "Billable Created");
	//$dates[] = array($billable['paid'], "Billable paid off");
	if($payments) foreach($payments as $payment) $dates[] = array($payment['issuedate'], "Payment made");
	if($invoiceitem) $dates[] = array($invoiceitem['date'], "Invoiced") ;
}

$lognotes = fetchRows("SELECT time, note, user FROM tblchangelog WHERE itemptr = $id AND itemtable = 'tblsurcharge'");

foreach($lognotes as $note) 
	$dates[] = array($note[0], $note[1].' by '.getUserNameWithTip($note[2]));
	
function dateSort($a, $b) {return strcmp($a[0], $b[0]);}
uasort($dates, 'dateSort');
$dates = array_reverse($dates);
?>
<hr>
<b>History:</b> <?= $appt['modifiedby'] ? "Last Modified by ".getUserNameWithTip($appt['modifiedby']).' at '.$appt['modified'] : ''?><hr>
<table>
<?
foreach($dates as $event) echo "<tr><td>{$event[0]}<td>&nbsp;{$event[1]}<br>";
?>
</table>

<?
function getUserName($userid) {
	if(!$userid) return "System";
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname, CONCAT('[', loginid, ', ',userid, ']')) FROM tbluser WHERE userid = $userid LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
	return $name;
}

function getUserinfo($userid) {
	if(!$userid) return "System";
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$user = fetchFirstAssoc("SELECT fname, lname, loginid, userid FROM tbluser WHERE userid = $userid LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
	return $user;
}

function getUserNameWithTip($userid) {
	if(!$userid) return "System";
	$user = getUserinfo($userid);
	if($user['fname'] || $user['lname']) {
		$name = "{$user['fname']} {$user['lname']}";
		$title = "{$user['loginid']}: {$user['userid']}";
	}
	else {
		$name = "login: {$user['loginid']}";
		$title = "{$user['loginid']}: {$user['userid']}";
	}
	return "<span style='text-decoration:underline;cursor:pointer;' title='".safeValue($title)."'>$name</span>";
}

