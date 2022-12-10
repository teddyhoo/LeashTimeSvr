<? // client-schedule-prefs.php
// Edit schedule list prefs for one user at a time
// params: client - id of client to return to only on POST: savePrefs,servicesListSpan,weeksPrior,weeksSubsequent
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";

$failure = false;
// Determine access privs
locked('o-');

extract(extractVars('client,savePrefs,servicesListSpan,weeksPrior,weeksSubsequent,showrateinclientschedulelist', $_REQUEST));

if(!$failure && $_POST && $savePrefs) {
	if($servicesListSpan == 'month')
		setUserPreference($_SESSION['auth_user_id'], 'servicesListSpan', 'month');
	else
		setUserPreference($_SESSION['auth_user_id'], 'servicesListSpan', "$weeksPrior|$weeksSubsequent");
	// moved to Your Own Preferences -- setUserPreference($_SESSION['auth_user_id'], 'showrateinclientschedulelist', $showrateinclientschedulelist);
	$message = "Client Schedule Default Preferences saved.";
}

$clientDetails = getOneClientsDetails($client);

$pageTitle = "{$_SESSION['auth_login_id']}'s Client Services Tab Default Preferences";

if($client) {
	$clientDetails = getOneClientsDetails($client);
	$breadcrumbs = "<a href='client-edit.php?tab=services&id=$client'>Back to {$clientDetails['clientname']}'s Services Page</a>";
}
include "frame.html";
// ***************************************************************************
if($message) echo "<font color=green>$message</font><p>";

echo "What range of days would you like the Client Services tab to display when you first open it?<p>";
$servicesListSpan = getUserPreference($_SESSION['auth_user_id'], 'servicesListSpan');
$servicesListSpan = $servicesListSpan ? $servicesListSpan : '1|3';
$radioChoice = $servicesListSpan == 'month' ? $servicesListSpan : 'range';

echo "<p><form name='userprefsform' method='POST'>";
hiddenElement('client',$client);
hiddenElement('savePrefs','1');
labeledRadioButton('Show Current Month', 'servicesListSpan', 'month', $radioChoice, 'toggleRanges(this)');
echo "<p>";
labeledRadioButton('Show Range of ', 'servicesListSpan', 'range', $radioChoice, 'toggleRanges(this)');
echo " ";
$rangeOptions = array(1=>1, 2=>2, 3=>3);
$currentSelections = explode('|', $servicesListSpan == 'month' ? '1|3' : $servicesListSpan);
selectElement('Weeks Prior to today:', 'weeksPrior', $currentSelections[0], $rangeOptions);
echo " and ";
selectElement('Weeks Subsequent to today:', 'weeksSubsequent', $currentSelections[1], $rangeOptions);
echo "<p>";
// moved to Your Own Preferences -- labeledCheckbox('Show Visit Rates', 'showrateinclientschedulelist', getUserPreference($_SESSION['auth_user_id'], 'showrateinclientschedulelist'));
//echo "<p>";
echoButton('', 'Save Preferences', 'document.userprefsform.submit()');

echo "<p><table>";
echo "</table>";
echo "</form>";
?>
<script language='javascript'>
function toggleRanges(el) {
	document.getElementById('weeksPrior').disabled = (el.value == 'month');
	document.getElementById('weeksSubsequent').disabled = (el.value == 'month');
}
</script>
<?
// ***************************************************************************

include "frame-end.html";
?>


