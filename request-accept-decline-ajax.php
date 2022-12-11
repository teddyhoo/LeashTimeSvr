<?
//request-accept-decline-ajax.php

/*
SELECT appointmentid, canceled, cancellationreason, pendingchange, requestid, requesttype, resolved, resolution
FROM tblappointment app
LEFT JOIN tblclientrequest ON requestid = abs(pendingchange)
WHERE pendingchange IS NOT NULL


*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Verify login information here
locked('o-');
extract($_REQUEST);

$where = "pendingchange = $request OR pendingchange = (0 - $request)";

$mods = withModificationFields(array("pendingchange"=>null));

//print_r($mods);echo "<p>[$where]<p>";
updateTable('tblappointment', $mods, $where, 1);
// completion and charge are unchanged, so no discount action is necessary	

if($_REQUEST['applyNote']) {
	$oldReq = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $request LIMIT 1", 1);
	$addition = mysqli_real_escape_string(stripslashes($oldReq['note']));
	$mods =  
		array('note' =>
					sqlVal("IF(note IS NULL, '$addition', CONCAT_WS('\\n', note, '$addition'))"));
		
	if(strpos($oldReq['scope'], 'day_') === 0)
		$where = "clientptr = {$oldReq['clientptr']} AND date = '".substr($oldReq['scope'], strlen('day_'))."'";
	else
		$where = "appointmentid = ".substr($oldReq['scope'], strlen('sole_'));
		
	updateTable('tblappointment', $mods, "$where", 1);
}



if($honor) {
	$dateTime = shortDateAndTime()." Honored by {$_SESSION['auth_username']} ({$_SESSION['auth_user_id']})";
	$resolution = 'honored';
}
else {
	$dateTime = shortDateAndTime()." Declined by {$_SESSION['auth_username']} ({$_SESSION['auth_user_id']})\n";
	$resolution = 'declined';
}
$dateTime = mysqli_real_escape_string($dateTime);
updateTable('tblclientrequest', array('resolved'=>1, 'resolution'=>$resolution, 
				'officenotes'=>sqlVal("CONCAT_WS('\\n','$dateTime', officenotes)")), "requestid = $request", 1);

