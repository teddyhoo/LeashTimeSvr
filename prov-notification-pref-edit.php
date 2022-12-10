<? // prov-notification-pref-edit.php
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

$dailyMasterScheduleEmailOption = true; //getPreference('dailyMasterScheduleEmailOption');


//scheduleDay|Send Weekly Schedules|picklist|Never,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday
//noEmptyProviderScheduleNotification|Suppress provider schedule email when there are no visits
//scheduleDaily|Send Daily Schedules by Default
extract(extractVars('scheduleDay,noEmptyProviderScheduleNotification,noAllCanceledProviderScheduleNotification,scheduleDaily,scheduleDayAll,scheduleDailyAll,noEmptyProviderScheduleNotificationAll', $_REQUEST));

if($_POST) {
//if(mattOnlyTEST()) {print_r($_POST);exit;}
	setPreference('scheduleDay', $scheduleDay);
	setPreference('noEmptyProviderScheduleNotification', $noEmptyProviderScheduleNotification ? '1' : '0');
	setPreference('noAllCanceledProviderScheduleNotification', $noAllCanceledProviderScheduleNotification ? '1' : '0');
	setPreference('scheduleDaily', $scheduleDaily);
	if($scheduleDayAll) {
		$weeklyvisitsemail = $scheduleDay == 'Never' ? '0' : '1';
		updateTable('tblprovider', array('weeklyvisitsemail'=>$weeklyvisitsemail), '1=1', 1);
	}
	if($scheduleDailyAll) {
		$scheduleDaily = $scheduleDaily ? '1' : '0';
		updateTable('tblprovider', array('dailyvisitsemail'=>$scheduleDaily), '1=1', 1);
	}
	
	setPreference('masterSchedule', $_POST['masterSchedule']);
	setPreference('masterScheduleDays', $_POST['masterScheduleDays']);
	foreach($_POST as $key => $val)
		if(strpos($key, 'master_') === 0)
			$masterScheduleRecipients[] = substr($key, strlen('master_'));
	setPreference('masterScheduleRecipients', join(',', (array)$masterScheduleRecipients));
						
	setPreference('providerScheduleEmailPrefs', 
		"Daily schedules: ".($scheduleDaily ? 'yes' : 'no')
		."<br>Weekly schedules: $scheduleDay"
		."<br>".($noEmptyProviderScheduleNotification 
						? "Don't send empty schedules" 
						: 'Send schedules even if empty')
		."<br>".($noAllCanceledProviderScheduleNotification 
						? "Don't send schedules when all visits are canceled" 
						: 'Send schedules even if all visits are canceled'));
						
	if(getPreference('dailyMasterScheduleEmailOption')) {
		setPreference('providerScheduleEmailPrefs', 
			getPreference('providerScheduleEmailPrefs')
			.(!getPreference('masterScheduleRecipients')
				? "<br>Do not send out Master Schedules"
				: "<br>Send Master Schedule ({$_POST['masterScheduleDays']} days) to ".count(explode(',', getPreference('masterScheduleRecipients')))." staff."));
	}
	echo "<script language='javascript'>
	if(window.opener && window.opener.updateProperty) 
		window.opener.updateProperty('providerScheduleEmailPrefs', 1);
	window.close();</script>";
	exit;
}

$prefs = fetchPreferences();

//if(mattOnlyTEST()) {echo "scheduleDay: {$prefs['scheduleDay']}<br>scheduleDaily: {$prefs['scheduleDaily']}<p>";print_r($prefs);}


$windowTitle = 'Sitter Schedule Email Preferences';
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>\n";
?>
<style>table td {padding-right: 5px;}</style>
<?
echoButton('', 'Save', 'document.prefeditor.submit()');
echo "\n<form method='POST' name='prefeditor'>\n";
echo "\n<table>";
echo "\n<tr><td>Send Weekly Schedules: </td><td>";
$options = explode(',','Never,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
$options = array_combine($options, $options);
selectElement('', 'scheduleDay', $prefs['scheduleDay'], $options);
echo "</td><td >";
$applyToAll = "Apply to current sitters: ";
labeledCheckbox($applyToAll, 'scheduleDayAll', $value=null);
echo "</td></tr>";
echo "<tr><td>Send Daily Schedules: </td><td>";
$options = array('Yes'=>'1', 'No'=>0);
selectElement('', 'scheduleDaily', $prefs['scheduleDaily'], $options);
echo "</td><td>";
labeledCheckbox($applyToAll, 'scheduleDailyAll', $value=null);
echo "</td></tr>";
echo "<tr><td>Suppress sitter schedule email<br>when there are no visits: </td><td>";
$options = array('Yes'=>'1', 'No'=>0);
selectElement('', 'noEmptyProviderScheduleNotification', $prefs['noEmptyProviderScheduleNotification'], $options, $onChange='noEmptyScheduleChanged(this)');
echo "</td><td class='tiplooks' style='text-align:left'>Applies to all sitters.</td></tr>";

if(true) { // added 11 June 2020
	$noAllCanceledProviderScheduleNotification = $prefs['noAllCanceledProviderScheduleNotification'] ? $prefs['noAllCanceledProviderScheduleNotification'] : 0;
	echo "<tr><td>Suppress sitter schedule email<br>when there are <b>only canceled</b> visits: </td><td>";
	$options = array('Yes'=>'1', 'No'=>0);
	selectElement('', 'noAllCanceledProviderScheduleNotification', $noAllCanceledProviderScheduleNotification, $options, $onChange='updateNoEmptySchedule(this)');
	echo "</td><td class='tiplooks' style='text-align:left'>Applies to all sitters.</td></tr>";
}

if($dailyMasterScheduleEmailOption) {
	$options = array('Yes'=>'1', 'No'=>0);
	echo "<tr><td colspan=4><hr></td><td>";
	echo "<tr><td>Send Master Schedule Daily</td><td>";
	selectElement('', 'masterSchedule', $prefs['masterSchedule'], $options, $onChange='masterToggled()');
	$options = explode(',','3,4,5,6,7,8,9,10,11,12,13,14');
	$options = array_combine($options, $options);
	$masterScheduleDays = $prefs['masterScheduleDays'] ? $prefs['masterScheduleDays'] : 7;
	echo "</td><td>Master Schedule Days</td><td>";
	selectElement('', 'masterScheduleDays', $prefs['masterScheduleDays'], $options, $onChange=null, $labelClass=null, $inputClass='masterschedulerecipient');
	"</td></tr>";
	echo "<tr><td colspan=2>Send Master Schedule to:</td></tr><tr><table>";
	require_once "common/init_db_common.php";
	$pool = fetchAssociations(
		"SELECT u.*, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(' ', lname, fname) as sortname
			FROM tbluser u
			WHERE active = 1 AND bizptr = {$_SESSION['bizptr']} AND ltstaffuserid = 0  AND email IS NOT NULL
				AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
			ORDER BY sortname");
	$chosen = explode(',', (string)$prefs['masterScheduleRecipients']);
	$maxCols = 2;
	$col = 0;
	foreach($pool as $i => $recipient) {
		if(!trim($recipient['name'])) $recipient['name'] = $recipient['email'];
		$options[$recipient['name']] = $recipient['userid'];
		if($col == 0) echo "<tr>";
		echo "<td>";
		$value = in_array($recipient['userid'], $chosen);
		labeledCheckbox($recipient['name'], "master_{$recipient['userid']}", $value, $labelClass=null, $inputClass='masterschedulerecipient', $onClick=null, $boxFirst=true, $noEcho=false, $title=$recipient['email']);
		echo "</td>";
		if($col == $maxCols-1) {
			echo "</tr>";
			$col = 0;
		}
		else $col += 1;
	}
	if($col < $maxCols-1) echo "</tr>";
	echo "</table>\n";
}
echo "</table>\n";
echo "</form>";
?>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script language='javascript'>
function masterToggled() {
	var disabled = $('#masterSchedule').val() != 1;
	$('.masterschedulerecipient').prop('disabled', disabled);
}

function updateNoEmptySchedule(noCanceledElement) {
	// if noCanceledElement is 'Yes', set noEmpty to 'Yes' too
	if(noCanceledElement.options[noCanceledElement.selectedIndex].value == 1)
		document.getElementById('noEmptyProviderScheduleNotification').selectedIndex = 0;
}

function noEmptyScheduleChanged(noEmptyElement) {
	var noCanceledElement = document.getElementById('noAllCanceledProviderScheduleNotification');
	if(noCanceledElement == null) return;
	// if noEmptyElement is "no", set noCanceledElement to "no" also
	if(noEmptyElement.options[noEmptyElement.selectedIndex].value == 0)
		noCanceledElement.selectedIndex = 1;
}

masterToggled();
</script>