<?
/* visit-report-data.php
*
* return visit report data in JSON form for what may be a non-logged-in client
*
* LOGGED IN Parameter: id  - id of appointment
* NOT LOGGED IN Parameter: nugget - encrypted and encoded params
*
* Parameters: 
* bizid - used to identify business and appointmentid of visit
* id - id of appointment
* d - int code indicating which fields -- see: appointment-client-notification-fns.php>fieldDisplayFromCode()
				--to display in enhanced VR
				-- RATS!  Ignore this, because of (getAppointmentProperty($appointmentid, 'reportPublicDetails')
*/
//print_r($_GET);

require_once "appointment-client-notification-fns.php";
if($_GET['generate']) {
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	require_once "encryption.php";
	locked('o-');
	echo "https:/LeashTime.com/visit-report-data.php?nugget=".visitReportDataPacketNugget($_REQUEST['id']);
	exit;
}
else if($_GET['nugget']) {
	require_once "common/init_session.php";
	require_once "common/init_db_common.php";
	require_once "encryption.php";
	$params = lt_decrypt($_GET['nugget']);
	$params = explode('&', $params);
	foreach($params as $part) {
		$keyValue = explode('=', $part);
		if($keyValue[0] == 'bizid') $bizid = $keyValue[1];
		else if($keyValue[0] == 'id') $nuggetid = $keyValue[1];
	}
	if(!($bizid && $nuggetid))
	  $error = "Information unavailable (code H)";// "H" for hacked
	else {
		$_SESSION['bizptr'] = $bizid;
		$_SESSION["rights"] = 'c-';
		$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid' AND activebiz = 1");
		if(!$biz) $error = "Information unavailable (code B)"; // "B" for business not found
		else reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
		$_SESSION["preferences"] = fetchKeyValuePairs("SELECT property,value FROM tblpreference");
	}
}
else {
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	if(userRole() == 'c') locked('c-', false, false);
	else {
		if(locked('o-', 'noforward', false))
			$error = "session not active";
	}
}

if(!$error) {
	if($_SESSION['bizptr']) {
		if($bizid && $bizid != $_SESSION['bizptr']) {
			$killSession = true;
			session_unset();
			session_destroy();
			require "common/init_session.php";
		}
	}
	else if(!$_SESSION['bizptr'])
		$killSession = true;
		
	if(userRole()) { // logged in...
		// ??? require_once "common/init_db_petbiz.php";
	}

}
if(!$error) {
	require_once "appointment-fns.php";
	$id = $nuggetid ? $nuggetid : $_REQUEST['id'];
	if(!$id) $error = "Information unavailable (code I)"; // "I" for missing visit ID
	else {
		$appt = getAppointment($id, $withNames=true, $withPayableData=false, $withBillableData=false);
		if(!$appt) $error = "Information unavailable (code V)"; // "V" for missing visit
		else if(!$_GET['nugget'] && userRole() == 'c' && !isVisitReportClientViewable($id)) $error = "Information unavailable (code N) ".userRole(); // "N" for Not viewable
		if(!$nuggetid) { // don't allow fishing trips. if client logged in, appt must belong to that client
			if(userRole() == 'c' && $_SESSION["clientid"] != $appt['clientptr'])
				$error = "Information unavailable (code F)";
		}
	}
}
if($error) {
	header("Content-type: application/json");
	echo json_encode(array('error'=>$error));
	if($killSession) {
		session_unset();
		session_destroy();
	}
	exit;
}

$_SESSION["clientid"] = $appt['clientptr']; // for the map

ensureInstallationSettings(); // no help with map
header("Content-type: application/json");
echo json_encode(visitReportDataPacket($id, !$nuggetid));

/*$includeFields = getAppointmentProperty($appt['appointmentid'], 'reportPublicDetails');
//echo "killSession[[[$killSession]]]";exit;
//echo "[[[{$appt['appointmentid']}]]] [$includeFields]";



$includeValue = inclusionPreferences($appt['clientptr'], $includeFields);
$visitPhotoURL = visitPhotoURL($appt['appointmentid'], $internalUse, $bizptr);
$mapRouteURL = visitMapURL($appt['appointmentid'], $internalUse, $bizptr);

global $biz;
$localSession['preferences'] = fetchKeyValuePairs("SELECT * FROM tblpreference", 1);
$localSession['auth_user_id'] = -999;
$localSession['auth_username'] = '';
$localSession['bizptr'] = $biz['bizid'];

if(!$client) $client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = '{$appt['clientptr']}' LIMIT 1");
$appointmentid = $appt['appointmentid'];
$sitterName = getDisplayableProviderName($appt['providerptr'], 'overrideasclient'); // '#ENHANCED_REPORT_SITTER#'
$onlineReportURL = generateEnhancedVisitReportResponseURL($appt, $_SESSION['bizptr']); //'#ENHANCED_REPORT_ONLINE#'
$bannerURL = enhancedVisitReportBannerURL(); //'#ENHANCED_REPORT_BANNER#'
$visitDate = longDate(strtotime($appt['date'])); // '#ENHANCED_REPORT_VISIT_DATE#'
$service = 	$serviceType = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = '{$appt['servicecode']}' LIMIT 1"); //'#ENHANCED_REPORT_SERVICE#'

echo enhancedOnlineVisitReportHTML($appt, !$killSession, null, $includeFields);
*/

//echo "killSession[[[$killSession]]]";exit;
if($killSession) {
	session_unset();
	session_destroy();
}

