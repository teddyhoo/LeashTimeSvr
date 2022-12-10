<?
/* visit-report-ext.php
*
* display a visit report for what may be a non-logged-in client
*
* Parameters: 
* bizid - used to identify business and appointmentid of visit
* id - id of appointment
* token - to check back to make sure this request matches the authorized request
* d - int code indicating which fields -- see: appointment-client-notification-fns.php>fieldDisplayFromCode()
				--to display in enhanced VR
				-- RATS!  Ignore this, because of (getAppointmentProperty($appointmentid, 'reportPublicDetails')
*/
//print_r($_GET);
// This is the client side javascript version of the visit report

if(!$_GET['nugget']) {
	echo "Sorry, no nugget.";
	exit;
}

$vrhtmlFile = "../html/clientside/sandbox/petowner/visit-reports/CareReport.html";
//$vrhtmlFile = "../html/clientside/sandbox/petowner/visit-reports/test.html";
$vrhtml = file_get_contents($vrhtmlFile);
$vrhtml = str_replace('#NUGGET#', $_GET['nugget'], $vrhtml);

echo $vrhtml;

exit;

// ############# ALL CODE BELOW IS OBSELETE #################

require_once "common/init_session.php";
require_once "common/init_db_common.php";

if($_GET['token']) {
	$tokenDetails = fetchFirstAssoc("SELECT * FROM tblresponsetoken WHERE token = '{$_GET['token']}'");
	if(!$tokenDetails) $error = "Information unavailable (code T)"; // "T" for unknown token
	else if($tokenDetails['bizptr'] != $_GET['bizid']) $error = "Information unavailable (code B)"; // "T" for biz id mismatch
	else {
		$url = $tokenDetails['url']; // visit-report-ext.php?token=sdird&bizid=3&id=987263&d=62
		list($page, $args) = explode('?', $url);
		$args = explode('&', $args);
		foreach($args as $a) {
			$parts = explode('=', $a);
			$params[$parts[0]] = $parts[1];
		}
		foreach($params as $k => $v)
			if($params[$k] != $_GET[$k])
			 $error = "Information unavailable (code H)"; // "H" for hacked
	}
}
else if(!$_GET['id']) $error = "Information unavailable (code I)";
else {
	$noTokenProvided = true;
	$_GET['bizid'] = $_SESSION['bizptr'];
	$params['id'] = $_GET['id'];
}

if(!$error) {
	if($_SESSION['bizptr']) {
		if($_GET['bizid'] != $_SESSION['bizptr']) {
			$killSession = true;
			session_unset();
			session_destroy();
			require "common/init_session.php";
		}
	}
	else if(!$_SESSION['bizptr'])
		$killSession = true;
		
	if(userRole()) { // logged in...
		require_once "common/init_db_petbiz.php";
	}
	else {
		$_SESSION['bizptr'] = $_GET['bizid'];
		$_SESSION["rights"] = 'c-';
		$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$_GET['bizid']} AND activebiz = 1");
		if(!$biz) $error = "Information unavailable (code B)"; // "B" for business not found
		else reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	}

}
if(!$error) {
	require_once "appointment-client-notification-fns.php";
	require_once "appointment-fns.php";
	$id = $params['id'];
	if(!$id) $error = "Information unavailable (code I)"; // "I" for missing visit ID
	else {
		$appt = getAppointment($id, $withNames=true, $withPayableData=false, $withBillableData=false);
		if(!$appt) $error = "Information unavailable (code V)"; // "V" for missing visit
		else if(!isVisitReportClientViewable($id)) $error = "Information unavailable (code N)"; // "N" for Not viewable
		if($noTokenProvided) { // don't allow fishing trips. if client logged in, appt must belong to that client
			if(userRole() == 'c' && $_SESSION["clientid"] != $appt['clientptr'])
				$error = "Information unavailable (code F)";
		}
	}
}
if($error) {
	echo "Sorry, $error";
	exit;
}



exit;  // ALL  BELOW IS OBSELETE

$template = $template ? $template : enhancedVisitReportEmailTemplate();	
$template['body'] .= "#STARTMAPROUTE##MAPROUTE##ENDMAPROUTE#";
// NOT USED? $_SESSION['noMapLockNeeded'] = true;
$_SESSION["clientid"] = $appt['clientptr']; // for the map
//print_r($template);
$includeFields = getAppointmentProperty($appt['appointmentid'], 'reportPublicDetails');

ensureInstallationSettings(); // no help with map
//echo "killSession[[[$killSession]]]";exit;
//echo "[[[{$appt['appointmentid']}]]] [$includeFields]";

//if(mattOnlyTEST()) echo "USER ROLE: [".userRole()."]<p>";
if($_GET['original']) {
	$visitPhotoURL = visitPhotoURL($appt['appointmentid'], $internalUse=!$killSession, $_GET['bizid']);
	$message =  preprocessVRMessage($template['body'], $appt, $visitPhotoURL, $visitMapURL=null, $client=null, $includeFields);
	echo $message;
}
else {
	echo enhancedOnlineVisitReportHTML($appt, !$killSession, null, $includeFields);
}

//if(mattOnlyTEST()) echo "USER ROLE: [".userRole()."] <p>killSession[$killSession]<br>";
//echo "killSession[[[$killSession]]]";exit;
if($killSession) {
	session_unset();
	session_destroy();
}
//if(mattOnlyTEST()) echo "USER ROLE: [".userRole()."] <p>killSession[$killSession]<br>";
