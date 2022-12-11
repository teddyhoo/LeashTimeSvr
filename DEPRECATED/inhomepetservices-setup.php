<? // inhomepetservices-setup.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";

if(strpos($db, 'inhomepetservices_') !== 0) {
	echo "<h1>You are in database $db!</h1>";
	exit;
}

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
include "common/init_db_common.php";
$mgrid = fetchRow0Col0("SELECT userid FROM tbluser WHERE bizptr = {$_SESSION['bizptr']} AND isowner = 1 LIMIT 1", 1);
list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
include "common/init_db_petbiz.php";




$lines = 
"petTypes||Dog|Cat|Bird|Dog & Cat|Rabbit or Guinea Pig
reportStaleVisits||1
staleVisitNotificationOptions||Yes, after 40 minutes
staleVisitsLimitDays||1
visitsStaleAfterMinutes||40
allowProvidertoProviderEmail||0
offerTimeOffProviderUI||0
enableTimeoffCalendarGlobalVisibility||0
sittersCanRequestVisitCancellation||1
sittersCanRequestClientProfileChanges||0
sittersCanSendICInvoices||0
trackSitterToClientEmail||0
replyToOfficeInSitterToClientEmail||0
provuisched_client||pets/name
provuisched_hideaddress||1
provuisched_hidepay||1
provuisched_hidephone||1
provuisched_start||timeofday
suppresscontactinfo||0
mobileSitterAppEnabled||1
mobileVersionPreferred||1
webUIOnMobileDisabled||0
mobileDetailedListVisit||1
mobileOfferFindAVet||0
mobileOfferArrivedButton||0
mobileSitterVisitNoteColor||black
mobile_private_zone_timeout_interval||300
mobileEmailsToClientsReplyToBusinessEmail||0
notifyClientArrivalDetails||
notifyClientCompletionDetails||
notifyClientArrivalDetailsSMS||
notifyClientCompletionDetailsSMS||
sendVisitArrivalByApp||
sendVisitCompletionByApp||
enhancedVisitReportArrivalTime||
enhancedVisitReportCompletionTime||
enhancedVisitReportVisitNote||
enhancedVisitReportMoodButtons||
enhancedVisitReportPetPhoto||
enhancedVisitReportRouteMap||
visitreportGeneratesClientRequest||
visitreport_NO_ClientRequest|
sitterReportsToClientViaServerDirectly||
sitterReportsToClientDirectly||
sitterReportsToClientViaServerAfterApproval||
visitreportIsNOTLogged||
EnhancedVisitReportSubject||
EnhancedVisitReportTemplate||
enableUnassignedVisitsBoard||0
enableProviderTeamSchedule||0
providerCanPrintIntakeForms||0
suppressEmergencyContactinfo||0
hideReassignedFromNoteFromProviders||0
emailFromAddress||robyn@inhomepetservices.com
emailHost||mail.inhomepetservices.com
emailPassword||robynsl222
emailUser||robyn@inhomepetservices.com
cancellationDeadlineHours||2
holidayVisitLookaheadPeriod||7
mobileKeyDescriptionForKeyId||1
requestResolutionEmail||0
optOutMassEmail||0
autoEmailCreditReceipts||1
autoEmailScheduleChanges||0
confirmNewSchedules||0
confirmSchedules||0
autoEmailApptCancellations||0
confirmApptCancellations||0
autoEmailApptReactivations||0
confirmApptReactivations||0
autoEmailApptChanges||0
confirmApptModifications||0
sendScheduleAsList||0
showClientCompletionDetails||0
showClientArrivalDetails||0
autoEmailScheduleChangesProvider||0
confirmNewSchedulesProvider||0
confirmSchedulesProvider||0
autoEmailApptCancellationsProvider||0
confirmApptCancellationsProvider||0
autoEmailApptReactivationsProvider||0
confirmApptReactivationsProvider||0
autoEmailApptChangesProvider||0
confirmApptModificationsProvider||0		
sendScheduleAsCalendar||0
scheduleDay||Never
noEmptyProviderScheduleNotification||
scheduleDaily||
emergencycarepermission||
masterSchedule||1
masterScheduleDays||3
markStartFinish||0
enableSitterNotesChatterMods||1";	


$prefs = explodePairPerLine($lines, $sepr='||');

$mgrprefs = explodePairsLine("provsched_client|pets/name||provsched_hidephone|1||provsched_hideaddress|1||provsched_start|timeofday");

//echo "POST: ".print_r($_POST['go'],1);
if($_POST['go']) {
	foreach($mgrprefs as $k =>$v) setUserPreference($mgrid, $k, nullIfEmpty($v));
	foreach($prefs as $k =>$v) setPreference($k, nullIfEmpty($v));
	echo "<p>Added :".insertServiceTypes()." service types.";
	echo "<p>Made :".setTimePreferences()." time preference mods.";
	doQuery("UPDATE tblsurcharge SET automatic=0");
	
}

	$fields = "notifyClientArrivalDetails,notifyClientCompletionDetails,notifyClientArrivalDetailsSMS,notifyClientCompletionDetailsSMS,"
	."sendVisitArrivalByApp,sendVisitCompletionByApp,enhancedVisitReportArrivalTime,"
	."enhancedVisitReportCompletionTime,enhancedVisitReportVisitNote,enhancedVisitReportMoodButtons,"
	."enhancedVisitReportPetPhoto,enhancedVisitReportRouteMap,visitreportGeneratesClientRequest,"
	."sitterReportsToClientViaServerDirectly,sitterReportsToClientDirectly,sitterReportsToClientViaServerAfterApproval,"
	."visitreportIsNOTLogged,EnhancedVisitReportSubject,EnhancedVisitReportTemplate,masterSchedule,masterScheduleDays";
//foreach(explode(',',$fields) as $f) echo "<br>$f||".nullIfEmpty(fetchPreference($f));

function nullIfEmpty($v) {return $v === '0' ? '0' : ($v ? $v : null);}

function insertServiceTypes() {
	$sql = "INSERT INTO `tblservicetype` (`servicetypeid`, `label`, `descr`, `defaultrate`, `ispercentage`, `defaultcharge`, `extrapetcharge`, `extrapetrate`, `taxable`, `active`, `hours`, `hoursexclusive`, `menuorder`) VALUES
(48, 'Dog Walk 20m', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 0),
(49, 'Dog Walk 30m', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 1),
(50, 'Dog Walk 1hr', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 2),
(51, 'Pet Sitting 20m', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 3),
(52, 'Pet Sitting 30m', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 4),
(53, 'Pet Sitting 1hr', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 5),
(54, 'Overnight', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 8),
(55, 'BOARDING', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '24:00', 0, 6),
(56, 'DAYCARE', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 7),
(57, 'Consultation', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 0),
(58, 'Clipping (Bird)', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 0),
(59, 'Clipping (non-bird)', NULL, 0.00, 0, 0.00, NULL, NULL, 0, '1', '00:00', 0, 0),
(60, 'Dropoff', NULL, 0.00, 1, 0.00, NULL, NULL, 0, '1', '00:00', 0, 0);
";
	doQuery($sql, 1);
	return mysqli_affected_rows();
}

function setTimePreferences() {
	$sql = "REPLACE INTO `tblpreference` (`property`, `value`) VALUES
('defaultTimeFrame', '12:00 pm-2:00 pm'),
('mobile_private_zone_timeout_interval', '300'),
('suppressInvoiceTimeOfDay', '1'),
('suppressLongTimeFrameWarning', '1'),
('suppressTimeFrameDisplayInCLientUI', '1'),
('timeframeOverlapPolicy', 'permissive'),
('timeframe_1', 'AM Cats|7:00 am-10:45 am'),
('timeframe_2', 'AM|7:00 am-9:00 am'),
('timeframe_3', 'Early aft.|11:00 am-1:00 pm'),
('timeframe_4', 'Afternoon|12:00 pm-2:00 pm'),
('timeframe_5', 'Dinnertime|4:00 pm-6:00 pm'),
('timeframe_6', 'PM Cats|4:00 pm-8:00 pm'),
('timeframe_7', 'PM|8:00 pm-10:00 pm'),
('timeframe_8', 'Boarding|12:00 am-11:59 pm'),
('timeZone', 'America/New_York');
";
	doQuery($sql, 1);
	return mysqli_affected_rows();
}

if(!$_POST['go']) {
	echo "<h2>$db</h2>
	Will set the folloing prefs:<p>";
?>
	<form name=modsform method='post'>
	<input type='button' value='Go!' onclick='goManGo()'>
	<input type='hidden' id='go' name='go'>
	</form>
<?
	foreach($prefs as  $k =>$v) echo "$k: $v<br>";
	echo "<p>Will set manager prefs:<p>";	
	foreach($mgrprefs as  $k =>$v) echo "$k: $v<br>";
}

?>
<script language='javascript'>
function goManGo() {
	if(confirm("Are you sure?")) {
		document.getElementById('go').value=1;
		document.modsform.submit();
	}
}
</script>