<? // comm-prefs-provider.php
// Edit email prefs for one user at a time
// params: id - providerid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";

$failure = false;
// Determine access privs
if(userRole() == 'p') {
	if($id && $id != $_SESSION["providerid"])
		$failure = true;
}
else $locked = locked('o-');

extract(extractVars('id,savePrefs', $_REQUEST));

$booleanParamKeys = 
'autoEmailScheduleChangesProvider|Schedule Change Emails
confirmNewSchedulesProvider|Always request confirmation for new schedules
confirmSchedulesProvider|Always request confirmation for schedule changes
2|----
autoEmailApptCancellationsProvider|Visit Cancellation Emails
confirmApptCancellationsProvider|Always request confirmation for visit cancellations
3|----
autoEmailApptReactivationsProvider|Visit Reactivation Emails
confirmApptReactivationsProvider|Always request confirmation for visit reactivations
4|----
autoEmailApptChangesProvider|Visit Change Emails
confirmApptModificationsProvider|Always request confirmation for visit modifications
5|----
sendScheduleAsCalendar|Use Calendar format for emailed Client schedules';				
									
$booleanParamKeys = explodePairPerLine($booleanParamKeys);

if(TRUE || $_SESSION['preferences']['enableNativeSitterAppAccess']) {
	$booleanParamKeys[6] = '----';
	$booleanParamKeys['sitterReportsToClientViaServerAfterApproval'] = 'Sitter reports to be sent to client require manager approval.';
}

if($_SESSION["providerid"]) {
	$filter = "WHERE providerid = {$_SESSION["providerid"]}";
}
$providerNames = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) as name, lname, fname FROM tblprovider $filter ORDER BY lname, fname");

if(!$failure && $_POST && $savePrefs) {
	foreach($booleanParamKeys as $property => $label)
		if($label != '----') {
			$val = $_POST[$property] == 0 ? '0' : $_POST[$property];
			setProviderPreference($id, $property, $val == -1 ? null : $val);
		}
	if(staffOnlyTEST()) 
		setProviderPreference($id, 'visitSheetBaseFontSize', $_POST['visitSheetBaseFontSize']);
			
	$message = "Preferences saved for {$providerNames[$id]}";
}

$pageTitle = "Sitter Communication Preferences";

$breadcrumbs = "<a href='comm-prefs.php'>Communication Preferences</a> ";
if($id && !$failure) 
	$breadcrumbs .= "- <a href='provider-edit.php?tab=communication&id=$id'>{$providerNames[$id]}'s Communication Page</a>";

include "frame.html";
// ***************************************************************************
if($failure) echo "<font color=red>Insufficient rights to change this sitter's preferences.</font>";
if($message) echo "<font color=green>$message</font>";

echo "<p><form name='providerprefsform' method='POST'>";
hiddenElement('savePrefs','1');
//$options = array_merge(array('-- Select a Sitter --' => ''), array_flip($providerNames));
//if(mattOnlyTEST()) 
$options = array_merge(array('-- Select a Sitter --' => ''), 
	getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=true));
	
selectElement('Provider:', 'id', $id, $options, 'document.location.href="comm-prefs-provider.php?id="+this.options[this.selectedIndex].value');
if($id) {
	echo " ";
	echoButton('', 'Save Preferences', 'document.providerprefsform.submit()');
}
echo "<p><table>";
if($id) foreach($booleanParamKeys as $property => $label) {
	if($label == '----') echo "<tr><td colspan=2><hr></td></tr>";
	else {
		$value = getProviderPreference($id, $property, true);  // consults $_SESSION["preferences"] by default
		heritableYesNoOptionsRow($label, $property, $value);
	}
	

}
if($id && staffOnlyTEST()) {
	echo "<tr><td colspan=2><hr></td></tr>";
	$options=explodePairsLine('--|0||8pt|8pt||9pt|9pt||10pt|10pt||11pt|11pt||12pt|12pt');
	$value = getProviderPreference($id, 'visitSheetBaseFontSize');
	echo "\n<tr><td>Visit Sheet Base Font Size (LT Staff Only)</td><td>"
		.selectElement('', 'visitSheetBaseFontSize', $value, $options, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=true, $optExtras=null, $title=null)
		."</td></tr>";
}
echo "</table>";
echo "</form>";
// ***************************************************************************
echo "<br><img src='art/spacer.gif' width=1 height=300>";
include "frame-end.html";

?>

