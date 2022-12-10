<? // comm-prefs-client.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";

$failure = false;
// Determine access privs
$id = $_REQUEST['id'];
if(userRole() == 'c' && $id != $_SESSION["clientid"]) locked('NoWayDude!');
else if(userRole() == 'p') {
	if($id && !in_array($id, getActiveClientIdsForProvider($_SESSION["providerid"])))
		$failure = true;
}
else $locked = locked('o-');

extract(extractVars('id,savePrefs', $_REQUEST));

$booleanParamKeys = 
'optOutMassEmail|No Mass Emails
autoEmailCreditReceipts|Automatic Credit Card Transaction Emails
1|----
autoEmailScheduleChanges|Schedule Change Emails
confirmNewSchedules|Always request confirmation for new schedules
confirmSchedules|Always request confirmation for schedule changes
2|----
autoEmailApptCancellations|Visit Cancellation Emails
confirmApptCancellations|Always request confirmation for visit cancellations
3|----
autoEmailApptReactivations|Visit Reactivation Emails
confirmApptReactivations|Always request confirmation for visit reactivations
4|----
autoEmailApptChanges|Visit Change Emails
confirmApptModifications|Always request confirmation for visit modifications
5|----
sendScheduleAsList|Use List format for emailed schedules';
//5|----
//autoEmailClientSchedule|Weekly Schedule Emails

$realTimeVisitNotification = TRUE; //$_SESSION['preferences']['clientArrivalCompletionRealTimeNotification'];
if(staffOnlyTEST() || $realTimeVisitNotification) $booleanParamKeys .= 
"\n-1|----\n$booleanParamKeys
showClientCompletionDetails|Show Client Visit Completion details on calendar|Show the client the visit completion time in the client's calendar.
notifyClientCompletionDetails|Notify Client by email on Visit Completion|Notify Client by email the when visit is complete.
showClientArrivalDetails|Show Client Visit Arrival details on calendar|Show the client the sitter's arrival time in the client's calendar.
notifyClientArrivalDetails|Notify Client by email when sitter Arrives|Notify Client by email when the sitter arrives for a visit.";							
							
							
							
$explanations = array();							
$lines = explode("\n", $booleanParamKeys);
foreach($lines as $line) {
	$line = explode('|', trim($line));
	if($line[2]) $explanations[$line[0]] = $line[2];
}
$booleanParamKeys = explodePairPerLine($booleanParamKeys);

if($_SESSION["providerid"]) {
	$ids = getActiveClientIdsForProvider($_SESSION["providerid"]);
	$filter = !$ids ? "WHERE 1=0" : "WHERE  clientid IN (".join(',', $ids).")";
}
$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as name, lname, fname FROM tblclient $filter ORDER BY lname, fname");

if(!$failure && $_POST && $savePrefs) {
	foreach($booleanParamKeys as $property => $label)
		if($label != '----') {
			$val = $_POST[$property] == 0 ? '0' : $_POST[$property];
			setClientPreference($id, $property, $val == -1 ? null : $val);
		}
		
		
foreach(getRecipientsPreferences($_POST) as $property => $v)
	setClientPreference($id, $property, $v);
		
$message = "Preferences saved for {$clientNames[$id]}";
}

$pageTitle = "Client Communication Preferences";

$breadcrumbs = "<a href='comm-prefs.php'>Communication Preferences</a> ";
if($id && !$failure) 
	$breadcrumbs .= "- <a href='client-edit.php?tab=communication&id=$id'>{$clientNames[$id]}'s Communication Page</a>";

include "frame.html";
// ***************************************************************************
if($failure) echo "<font color=red>Insufficient rights to change this client's preferences.</font>";
if($message) echo "<font color=green>$message</font>";

echo "<p><form name='clientprefsform' method='POST'>";
hiddenElement('savePrefs','1');
$dups = array();
foreach($clientNames as $clientid => $name) {
	if($dups[$name]) $clientNames[$clientid] .= " (".(1+$dups[$name]).")";
	$dups[$name] = 1 + $dups[$name];
}
$options = array_merge(array('-- Select a Client --' => ''), array_flip($clientNames));
selectElement('Client:', 'id', $id, $options, 'document.location.href="comm-prefs-client.php?id="+this.options[this.selectedIndex].value');
if($id) {
	echo " ";
	echoButton('', 'Save Preferences', 'document.clientprefsform.submit()');
}

echo "<p><table>";
if($id) foreach($booleanParamKeys as $property => $label) {
	if($label == '----') echo "<tr><td colspan=2><hr></td></tr>";
	else {
		if($explanations[$property]) $label = "<span title=\"{$explanations[$property]}\">$label</span>";
		$value = getClientPreference($id, $property, true);  // consults $_SESSION["preferences"] by default
		heritableYesNoOptionsRow($label, $property, $value);
	}
}
echo "</table>";

if($id) { //enabled for all 2020-10-04 $_SESSION['preferences']['enableVisitReportAltEmail']
	echo "<hr><h3>Email Delivery Targets</h3>";
	recipientsTable($id, $messageTypes=null);
	echo "<hr>";
}



echo "</form>";
if(/* mattOnlyTEST() && $_SESSION['preferences']['enableNativeSitterAppAccess'] && */ $id) { 
	echo "<br>";
	fauxLink('Native Sitter App Prefs', 
		"$.fn.colorbox({href:\"native-sitter-preference-list.php?clientid=$id\", width:\"800\", height:\"920\", iframe: true, scrolling: true, opacity: \"0.3\"})"
		, $noEcho=false, $title=null, $id=null, $class=null, $style='font-size:1.2em;');
}


// ***************************************************************************
echo "<br><img src='art/spacer.gif' width=1 height=300>";
include "frame-end.html";
?>

