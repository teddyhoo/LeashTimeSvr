<?
/* client-request-appointment.php
*
* Parameters: 
* id - id of appointment to be edited
*/

require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "appointment-fns.php";
include "service-fns.php";
include "request-fns.php";
// Verify login information here
extract(extractVars('id,scope,phone,note,operation,mobile', $_REQUEST));

if(userRole() == 'p') {
	locked('p-');
	$activeClients = getActiveClientIdsForProvider($_SESSION["providerid"]);
}
else if(userRole() == 'c') {
	locked('c-');
	$activeClients = array($_SESSION["clientid"]);
}
else $activeClients = fetchCol0("SELECT clientid FROM tblclient WHERE active = 1");


$operationLabel = $operation == 'cancel' ? 'Cancel' : ($operation == 'change' ? 'Change' : ($operation == 'uncancel' ? 'Un-cancel' : ''));

if(!isset($id)) $error = "Appointment ID not specified.";
else {
	$source = getAppointment($id);
	if(userRole() == 'p' && $source['providerptr'] == $_SESSION["providerid"])
		$activeClients[] = $source['clientptr'];
	if(!in_array($source['clientptr'], $activeClients)) {
		echo "<h2>You have insufficient rights to view this page.</h2>";
		exit;
	}
	$package = getPackage($source['packageptr']);
	$packageType = $package['monthly'] ? 'Monthly Recurring' :
	               ($package['onedaypackage'] ? 'One Day' :
	               ($package['enddate'] ? 'Nonrecurring' : 'Weekly Recurring'));
  $source['packageType']	= $packageType;
}


if(!$error && $_POST) {
	if($_SESSION["providerid"]) {
		$request['providerptr'] = $_SESSION["providerid"];
		$request['clientptr'] = $source["clientptr"];
	}
	else if(userRole() == 'd') $request['clientptr'] = $source["clientptr"];
	else $request['clientptr'] = $_SESSION["clientid"];
	$request['scope'] = $scope;  // sole_apptId or day_apptDate
	$request['phone'] = $phone; //primaryPhoneNumber($data)
	$request['note'] = $note;
	$request['requesttype'] = $operation;
	if($operation == 'cancel') { // $id, $operation, $scope, 
	}
	else if($operation == 'uncancel') {
	}
	else if($operation == 'change') {
	}
	if(TRUE || dbTEST('dogslife,tonkatest')) {
		$extraFields[] = "<hidden key=\"visitdetails\"><![CDATA[".visitRequestMemo($request)."]]></hidden>";
		if($extraFields) $request['extrafields'] = "<extrafields>".join('', $extraFields)."</extrafields>";
	}
	$request['requestid'] = saveNewClientRequest($request);
	setPendingChangeNotice($request);
	if($mobile) 
		echo "<script language='javascript'>document.location.href='mobile-client-edit-day.php?date={$source['date']}'</script>";
	else echo "<script language='javascript'>if(window.opener.update) window.opener.update('appointments'); window.close();</script>";
	exit;
}

$windowTitle = "$operationLabel Appointment: ($packageType Package)";
if($mobile) 
	$customStyles = "
h2 {font-size:2.5em;} 
h3 {font-size:2.0em;} 
td {font-size:1.5em;} 
.standardInput {font-size:1.5em;}
/*input:radio {font-size:1.5em;} */
.scopelabel {font-size:2.0em;} 
.mobileLabel {font-size:2.0em;} 
.mobileInput {font-size:2.0em;}
textarea {font-size:1.1em;}
input.Button {font-size:2.0em;}
input.ButtonDown {font-size:2.0em;}
";

require "frame-bannerless.php";

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $operationLabel ?> Appointment</h2>
<?
$tooLateToCancel = $_SESSION['preferences']['cancellationDeadlineHours'];
if($tooLateToCancel && time() + (60 * 60 * $tooLateToCancel) > strtotime($source['date'].' '.$source['starttime']))
	echo "<h3><font color=red>{$_SESSION['preferences']['cancellationDeadlineWarning']}</font></h3>";
displayAppointment($source, false, false);
echo "<h3>$operationLabel...</h3>";

echo "<form name='apprequest' method='POST'>";
hiddenElement('id', $id);
hiddenElement('operation', $operation);
//labeledRadioButton($label, $name, $value=null, $selectedValue=null, $onClick=null, $labelClass=null, $inputClass=null, $labelFirst=null) {

labeledRadioButton('This appointment only.', 'scope', "sole_$id", "sole_$id", null, 'scopelabel', 'scoperadio');
echo "<br>";
labeledRadioButton("All of this day's appointments.", "scope", "day_{$source['date']}", null, null, 'scopelabel', 'scoperadio');
echo "<p>";
labeledInput("Best Phone Number to reach you:", 'phone', '', $labelClass='mobileLabel', $inputClass='mobileInput');
$noteLabel = $operation == 'change' ? 'How would you like to change this appointment?' : 'Note:';
$cols = $mobile ? 40 : 50;
echo "<p class='mobileLabel'>$noteLabel<br><textarea rows=4 cols=$cols id='note' name='note'></textarea>";
echo "<p>";
echoButton('',$operationLabel.' Appointment', "execute(\"$operation\")");
echo " ";
echoButton('', "Quit", 	
						($mobile ? "document.location.href=\"mobile-client-edit-day.php?date={$source['date']}\""
										 : 'window.close()'));

echo "</form>";

if(userRole() != 'c' && adequateRights('#ev') && !isset($noedit)) {
  echoButton('', "Edit Appointment", "document.location.href=\"appointment-edit.php?updateList=$updateList&id=$id\"");  //"window.close();openConsoleWindow(\"viewclient\", \"appointment-edit.php?id=$id\",530,420)"
  echo " ";
}
//echo " ";
//echoButton('', "Delete Appointment", 'deleteAppointment()', 'HotButton', 'HotButtonDown');
?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

function execute($operation) {
	<? if($operation == 'change') echo "setPrettynames('note','A description of the change you want to make');\n\tif(MM_validateForm('note','','R')) "; ?>
	document.apprequest.submit();
}

</script>
</body>
</html>
