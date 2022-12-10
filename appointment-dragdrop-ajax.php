<?
// appointment-dragdrop-ajax.php
require_once "common/db_fns.php";
require_once "common/init_session.php";
$iPadReassignmentTweaksEnabled = /*$_SESSION['frameLayout'] == 'fullScreenTabletView' &&*/ isIPad();
$touchPunchReassignmentTweaksEnabled = !$iPadReassignmentTweaksEnabled  && agentIsATablet();

if($iPadReassignmentTweaksEnabled) include "ipad-dragdrop-fns.php";  // WARNING: A CHANGE HERE MUST BE MADE IN job-reassignment.php AS WELL
else if($touchPunchReassignmentTweaksEnabled) include "touchpunch-dragdrop-fns.php";  // WARNING: A CHANGE HERE MUST BE MADE IN job-reassignment.php AS WELL
else include "dragdrop-fns.php";
require_once "provider-fns.php";

if($_GET['reassignmentCount']) {
	echo fetchRow0Col0("SELECT count(*) FROM relreassignment") ? '1' : '';
	exit;
}

$fn = isset($_GET['fn']) ? $_GET['fn'] : null;

if(isset($_GET['prov']) && ($_GET['prov'] > 0)) {
  $fullProvider = getProvider($_GET['prov']);
  $provider = array('name'=>providerShortName($fullProvider), 'providerptr'=>$fullProvider['providerid']);
}
else if(isset($_GET['prov'])) {
	$provider = $_GET['prov'] == -2 ? '-- All Sitters --' : /* -1 */ '-- Unassigned --';
	$provider = array('name'=>$provider, 'providerptr'=>$_GET['prov']);
}	
$dateRange = array(date('Y-m-d', strtotime($_GET['start'])), date('Y-m-d', strtotime($_GET['end'])));
if(!$dateRange[1]) $dateRange[1] = $dateRange[0];
if($dateRange[0]) {
	if($fn=='appointments') getAppointmentsFor($provider, $dateRange);
	if($fn=='reassigned') getReassignmentsFrom($provider, $dateRange);
	if($fn=='allappointments') getProvisionalAppointmentsFor($provider, $dateRange, $_GET['exclude']);
}
if($fn=='cancelAll') clearAllReassignments();
if($fn=='executeAll') executeAllReassignments();
if($fn=='cancelReassignment') cancelReassignment($_GET['appt']);
if($fn=='reassignappt') {
	if($_GET['prov']) {
		$success = reassignAppointmentTo($_GET['appt'], $provider['providerptr'], $_GET['origprov']);
		//if(!$success) echo $provider['name'].' is unavailable at this time.';
		if(!$success) {
			// if($fullProvider) 
			$appt = fetchFirstAssoc("SELECT appointmentid, clientptr FROM tblappointment WHERE appointmentid = {$_GET['appt']} LIMIT 1", 1);
			echo $provider['name'].' '.providerNotListedReason($fullProvider, $appt);
		}
	}
	else unassignAppoint($_GET['appt']);
}

?>