<? // lastMinuteSchedule-pref-edit.php

/* Params
none
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require "preference-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
$failure = false;

if($failure) {
	$windowTitle = 'Insufficient Access Rights';
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}
extract(extractVars('warnOfLateScheduling,lastSchedulingDays,lastSchedulingMessage', $_REQUEST));

if($_POST) {
	setPreference('warnOfLateScheduling',  ($warnOfLateScheduling ? 1 : 0) );
	setPreference('lastSchedulingDays',  $lastSchedulingDays );
	setPreference('lastSchedulingMessage',  $lastSchedulingMessage );
	//$CLOSE = ""; //mattOnlyTEST() ? "" : "window.close();";
	echo "<script language='javascript'>window.opener.updateProperty(\"warnOfLateScheduling\", null);window.close();</script>";
	exit;
}

$windowTitle = "Warn clients who schedule at the last minute";;
require "frame-bannerless.php";

?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<table>
<?
$currentSetting = $_SESSION['preferences']['warnOfLateScheduling'] ? 1 : 0;
selectRow('Warn clients who schedule at the last minute', 'warnOfLateScheduling', $currentSetting, array('yes'=>1, 'no'=>0), 'warningToggled(this)');

$currentSetting = $_SESSION['preferences']['lastSchedulingDays'];
inputRow('Days before the first visit should the warning be shown', 'lastSchedulingDays', max(1, $currentSetting));

$currentSetting = $_SESSION['preferences']['lastSchedulingMessage'];
if(!$currentSetting)
	$currentSetting = "Please note that owing to the lateness of this request, we may not be able to schedule all of your visits.";
textRow('Warning to show the client (may include HTML tags)', 'lastSchedulingMessage', $currentSetting, $rows=4, $cols=60);
echo "</table>";
echoButton('', 'Save', 'save()');
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</form>";
?>
<hr>
Warning Message as it will appear: <span style='color:blue;cursor:pointer;'>Click to refresh</span>
<br>
<div id='preview' style='font-size:1.3em;border: solid black 1px; background:white;width:90%;padding:3px;'><?= $currentSetting ?></div>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('lastSchedulingDays', 'Days Before', 'lastSchedulingMessage', 'Warning Message');
function save() {
	if(document.getElementById('warnOfLateScheduling').value == 1) {
		if(!MM_validateForm(
				'lastSchedulingMessage', '', 'R',
				'lastSchedulingDays', '', 'R',
				'lastSchedulingDays', '', 'UNSIGNEDINT',
				'lastSchedulingDays', '1', 'MIN')) return;
		document.propertyeditor.submit();
	}
	else document.propertyeditor.submit();
}

function refreshWarning() {
	document.getElementById('preview').innerHTML = 
		document.getElementById('lastSchedulingMessage').value;
}

function warningToggled(el) {
	$off = el.options[el.selectedIndex].value == '0';
	document.getElementById('lastSchedulingDays').disabled  = $off;
	document.getElementById('lastSchedulingMessage').disabled  = $off;
	document.getElementById('preview').style.background = $off ? 'verylightgrey' : 'white';
	document.getElementById('preview').style.color = $off ? 'lightgrey' : 'black';
}
warningToggled(document.getElementById('warnOfLateScheduling'));
document.getElementById('lastSchedulingMessage').onchange=refreshWarning;
</script>
