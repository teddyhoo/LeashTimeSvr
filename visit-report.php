<? // visit-report.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "appointment-fns.php";
require_once "client-flag-fns.php";
require_once "appointment-client-notification-fns.php";

// Determine access privs
if(userRole() != 'c') $locked = locked('o-');

$max_rows = 100;
extract($_REQUEST);

if($_POST['operation'] == 'sendVisitReportFeedback') {
		require_once "appointment-client-notification-fns.php";
		print_r(sendVisitReportFeedback($_POST));
		exit;
}
else if($_POST) {
	if($messageptr = sendVisitReport($_POST)) ;
	if(is_array($messageptr)) $error = $messageptr[0];
	else $success = 'Visit report sent.';
	//logChange($id, 'tblclientrequest', 'm', $_SESSION['auth_user_id'].'|Visit report sent.') ;			
	

}

if(userRole() == 'c') { //$_SESSION['clientid
	$appt = getAppointment($id, $withNames=true, $withPayableData=false, $withBillableData=false);
	if($_SESSION["clientid"] != $appt['clientptr']) exit;
	if(isVisitReportClientViewable($id)) echo dumpVisitReportClientDisplay($id);
	exit;
}
 
$appt = getAppointment($id, $withNames=true, $withPayableData=false, $withBillableData=false);
$providerName = fetchRow0Col0(
	"SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider 
		WHERE providerid = {$appt['providerptr']}
		LIMIT 1");
$clientptr = $appt['clientptr'];
$clientDetails = getOneClientsDetails($clientptr, array('email'));
		
$buttons = fetchKeyValuePairs($sql = 
	"SELECT substring(property, 8) as property, value FROM tblappointmentprop 
		WHERE appointmentptr = $id
		AND property LIKE 'button_%'", 1); // strlen('button_')+1
		
		
		
if($buttons) $buttons = json_encode($buttons);
else $buttons = "{}";

$extraFields = array(
		'x-appointmentptr'=>$id,
		'x-clientptr'=>$clientptr,
		'x-providerptr'=>$appt['providerptr'],
		'x-providername'=>$providerName,
		'x-buttons'=>$buttons,
		'x-note'=>$appt['note'],
		'x-messageptr'=>'',
		'x-sentby'=>'',
		);
		
		
$extraHeadContent = '<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>' ;
		

require_once "frame-bannerless.php";
if($_SESSION["flags_enabled"]) { 
	$flags = clientFlagPanel($clientptr, $officeOnly=false, $noEdit=true, $contentsOnly=false, $onClick=null, $includeBillingFlags=false);
}

echo "\n<h2 style='font-size:1.5em'>Visit Report: {$clientDetails['clientname']} $flags</h2>";

$status = $success ? $success : $error;
$statusColor = $success ? 'green' : 'red';
if($status)  echo "<div class='tiplooks' style='color:$statusColor;font-size:1.2em;fontweight:bold'>$status</div><p>";

$service = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appt['servicecode']} LIMIT 1");


echo "Visit: ".shortDate(strtotime($appt['date']))." {$appt['timeofday']} $service Pets: {$appt['pets']}";
echo "<br>Sitter: $providerName";
$lastReport = 
	fetchRow0Col0(
				"SELECT value FROM tblappointmentprop
				 WHERE appointmentptr = $id AND property = 'lastReport'
				 LIMIT 1");
if($lastReport) {
	$lastReport = fetchFirstAssoc("SELECT * FROM tblmessage WHERE msgid = $lastReport LIMIT 1");
	echo "<p>Report last sent: ".longestDayAndDateAndTime(strtotime($lastReport['datetime']))
				." by {$lastReport['mgrname']}.";;
}
else echo "<p>No report has been sent.";
if(mattOnlyTEST()) {
	echo "<br>";
	fauxLink('Edit', "openConsoleWindow(\"photosetter\", \"appointment-edit.php?id=$id\", {$_SESSION['dims']['appointment-edit']});");
	echo " - ";
	echo "<a href=\"appt-analysis.php?id=$id\" target=\"analysis\">Analyze</a>";
}

?>
<p>
<form name='visitreportsender' method='POST'>
<?
hiddenElement('id', $id);
hiddenElement('operation', '');
echo "\n<div style='background:white;width:570px;padding-left:5px;padding-top:5px;'>";
dumpVisitReportEditorFormElementRows($extraFields, $id);
echo "\n</div>"; // manager's editor
?>
</form>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function setPetPhoto() {
	openConsoleWindow('photosetter', 'pet-photo-reassign.php?v=<?= $id ?>',750,450);
}
	
<? dumpPhotoRotationJS($id); ?>

function sendVisitReport() {
	var fields = new Array('ARRIVED','COMPLETED','MOODBUTTON','MAPROUTEURL','INCLUDENOTE','INCLUDEPHOTO');
	var checked = 0;
	for(var i = 0; i < fields.length; i++)
		if(document.getElementById(fields[i]) && document.getElementById(fields[i]).checked) checked += 1;
	if(checked == 0) alert('Please select at least one element of the report.');
	else  document.visitreportsender.submit();
}

function sendVisitReportFeedback() {  // to sitter
	document.getElementById('operation').value = 'sendVisitReportFeedback';
	/*var fields = new Array('ARRIVED','COMPLETED','MOODBUTTON','MAPROUTEURL','INCLUDENOTE','INCLUDEPHOTO');
	var checked = 0;
	for(var i = 0; i < fields.length; i++)
		if(document.getElementById(fields[i]) && document.getElementById(fields[i]).checked) checked += 1;
	if(checked == 0) alert('Please select at least one element of the report.');
	else*/  document.visitreportsender.submit();
}


</script>
