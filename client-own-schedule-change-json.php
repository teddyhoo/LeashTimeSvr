<?
// client-own-schedule-change-json.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "client-schedule-fns.php";
require_once "pet-fns.php";

require_once "request-fns.php";

// accepts a json array representing a client's visit change request and creates a composite visit change request
// format
/*
changetype: cancel|uncancel|change
groupnote: text ===> becomes the note of the request
visits: [
id: (visit id) note: note,
...
]
*/
$locked = locked('c-', $noForward=true, $exitIfLocked=true);
if($locked) {
	header("Content-type: application/json");
	echo json_encode(array('error'=>'no active session'));
	exit;
}

extract(extractVars('changes,test', $_REQUEST));
if(!$changes) {
	$changes = file_get_contents('php://input');
}


$clientid= $_SESSION["clientid"];
$client= fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $clientid LIMIT 1", 1);

if($test) {
	$changes['changetype'] = in_array($test, explode(',','')) ? $test : 'cancel';
	$changes['groupnote'] = 'Please cancel all these visits';
	$start = date('Y-m-d');
	$end = date('Y-m-d', strtotime("+4 days"));
	$visitids = fetchCol0(
		"SELECT appointmentid 
			FROM tblappointment 
			WHERE clientptr = '$clientid' AND date >= '$start' AND date < '$end'");
	foreach($visitids as $id) {
		$visit = array('id'=>$id);
		if($id % 2 == 1) $visit['note'] = "odd visit note $id";
		$changes['visits'][] = $visit;
	}
	$changes = json_encode($changes);
}

$changeStr = $changes;
$changes = json_decode($changes, 'assoc');

$request['requesttype'] = 'schedulechange';
$request['note'] = $changes['groupnote'];
$labels = array('cancel'=>'Cancellation', 'uncancel'=>'Unancellation', 'change'=>'Change');
$request['subject'] = "{$labels[$changes['changetype']]} Request from {$client['fname']} {$client['lname']}";;

$hidden['changetype'] = $changes['changetype'];
$hidden['visitsjson'] = json_encode($changes['visits']);
	

foreach($changes['visits'] as $visit) {
	$sql = "SELECT date, timeofday, servicecode, appointmentid, providerptr, note, charge, adjustment, rate, bonus,
									canceled, completed, pets
			FROM tblappointment 
			WHERE appointmentid = {$visit['id']}
			LIMIT 1";
	$orig = fetchFirstAssoc($sql, 1);
	if(!$orig['canceled'] && !$orig['completed']) { // check for arrived
		if($arrived = fetchRow0Col0(
				"SELECT date 
				 FROM tblgeotrack 
				 WHERE appointmentptr = {$visit['id']} AND event = 'arrived'
				 LIMIT 1", 1))
			 $orig['arrived'] = arrived;
	}
	$orig['status'] = 
		$orig['arrived'] ? 'arrived' : (
		$orig['canceled'] ? 'canceled' : (
		$orig['completed'] ? 'completed' : null));
	;
	if(!$orig) $orig = array("error"=>"unknown visit[{$visit['id']}]");
	else if(strlen("{$orig['note']}") > 40)
		$orig['note'] = substr($orig['note'], 0, 40).'...';
	$originalVisits[$visit['id']] = $orig;
}
$hidden['origvisits'] = json_encode($originalVisits);

	
foreach($hidden as $key => $value)
	$extraFields .= "<hidden key=\"$key\"><![CDATA[$value]]></hidden>";
	
$request['extrafields'] = "<extrafields>$extraFields</extrafields>";

$requestid = saveNewGenericRequest($request);

if($requestid) {
	$newRequest = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = '$requestid' LIMIT 1", 1);
	if($newRequest) setPendingChangeNotice($newRequest);
	if($changes['successUserNotice']) $_SESSION['user_notice'] = $changes['successUserNotice']; // will become $_SESSION['user_notice']
}

if($test) {
	$hidden = getHiddenExtraFields($newRequest);
	print_r(json_decode($hidden['visitsjson'], 'assoc'));
	exit;
}

header("Content-type: application/json");
echo json_encode(array('status'=>'ok', 'requestid'=>$requestid, 'changetype'=>$changes['changetype']));