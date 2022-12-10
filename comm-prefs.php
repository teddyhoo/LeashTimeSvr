<? // comm-prefs.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
$locked = locked('o-');
$pageTitle = "Communication Preferences";
$breadcrumbs = "<a href='preference-list.php' title='Business Preferences'>Business Preferences</a> ";
include "frame.html";
?>
<style>
td {vertical-align:top;}
.greybox {padding:8px;background:lightblue;font:normal bold 1.4em arial,sans-serif;width:162px;}
.whitebox {padding:5px;background:white;font:normal bold 0.7em arial,sans-serif;margin-top:7px;}
</style>
<table width=100%><tr>
<td>
<div class='greybox' >
Individual Clients
<div class='whitebox' >
<a href='comm-prefs-client.php'>Client Email Preferences</a>
</div>
</div>
</td>
<td>
<div class='greybox' >
Individual Sitters
<div class='whitebox' >
<a href='comm-prefs-provider.php'>Sitter Email Preferences</a>
</div>
</div>
</td>
<td>
<div class='greybox' >
Staff Notifications
<div class='whitebox' >
<a href='event-email-monitors.php'>Event Email Monitors</a>
<? 
if($_SESSION['preferences']['enableMobileMessaging']) 
 echo "<br>&nbsp;<br><a onclick='$.fn.colorbox({href:\"mobile-communication-options-lightbox.php\", width:\"550\", height:\"650\", iframe: true, scrolling: true, opacity: \"0.3\"})'
 		><u>Mobile Messaging</u></a>";

if(FALSE &&  $_SESSION['preferences']['optionEnabledGoogleCalendarSitterVisits']) { ?>
<br>&nbsp;<br><a href='google-cal-prefs.php'>Google Calendar Prefs</a>
<? } ?>
</div>
</div>
</td>
<td>
<div class='greybox' >
Email Templates
<div class='whitebox' >
<a href='email-templates.php'>Email Templates</a>
</div>
</div>
</td>
</tr></table>
<h2>Default Communication Preferences</h2>
<?
require "preference-fns.php";

$helpStrings = "key|help||anotherkey|help";

foreach(explode('||', $helpStrings) as $pair) {
	$pair = explode('|', $pair);
	$help[$pair[0]] = $pair[1];
}

$clientEmailPrefs =		"requestResolutionEmail|Automatic Request Resolution Emails|boolean"
											."||optOutMassEmail|Mass Email: Clients Opt Out by default|boolean"
											."||autoEmailCreditReceipts|Automatic Credit Card Transaction Emails|boolean"
											."||autoEmailScheduleChanges|Schedule Change Emails|boolean"
											."||confirmNewSchedules|Always request confirmation for new schedules|boolean"
											."||confirmSchedules|Always request confirmation for schedule changes|boolean"
											."||autoEmailApptCancellations|Visit Cancellation Emails|boolean"
											."||confirmApptCancellations|Always request confirmation for visit cancellations|boolean"
											."||autoEmailApptReactivations|Visit Reactivation Emails|boolean"
											."||confirmApptReactivations|Always request confirmation for visit reactivations|boolean"
											."||autoEmailApptChanges|Visit Change Emails|boolean"
											."||confirmApptModifications|Always request confirmation for visit changes|boolean"
											."||sendScheduleAsList|Use List format for emailed schedules|boolean";								

$clientExplanations =	"requestResolutionEmail|If \"yes\", Notify User checkbox in Request Editor is automatically checked."										
											."||optOutMassEmail|If \"yes\", clients cannot be emailed from the Client Email Broadcast page."
											."||autoEmailCreditReceipts|Send an automatic message to the user whenever the client's credit card is charged."
											."||autoEmailScheduleChanges|Open an email composer whenever changes are saved to a client's schedule."
											."||confirmNewSchedules|In New Schedule message, ask the client to confirm via a web link."
											."||confirmSchedules|In Schedule Change message, ask the client to confirm via a web link."
											."||autoEmailApptCancellations|Send an automatic message when an individual visit is canceled or deleted."
											."||confirmApptCancellations|In the visit cancellation message, ask the client to confirm via a web link."
											."||autoEmailApptReactivations|Send an automatic message when an individual visit is reactivated (uncanceled)."
											."||confirmApptReactivations|In the Visit Reactivation message, ask the client to confirm via a web link."
											."||autoEmailApptChanges|Open an email composer when an individual visit is changed."
											."||confirmApptModifications|In the Visit Change message, ask the client to confirm via a web link."
											."||sendScheduleAsList|The alternative is a calendar view of visits."
											."||showClientArrivalDetails|Show the client the sitter's arrival time in the client's calendar."
											."||notifyClientArrivalDetails|Notify Client by email when the sitter arrives for a visit."
											."||showClientCompletionDetails|Show the client the visit completion time in the client's calendar."
											."||notifyClientCompletionDetails|Notify Client by email the when visit is complete."
											;								
											
$providerEmailPrefs =	""									
											//"||optOutMassEmail|Mass Email: Providers Opt Out by default|boolean"
											."autoEmailScheduleChangesProvider|Schedule Change Emails|boolean"
											."||confirmNewSchedulesProvider|Always request confirmation for new schedules|boolean"
											."||confirmSchedulesProvider|Always request confirmation for schedule changes|boolean"
											."||autoEmailApptCancellationsProvider|Visit Cancellation Emails|boolean"
											."||confirmApptCancellationsProvider|Always request confirmation for visit cancellations|boolean"
											."||autoEmailApptReactivationsProvider|Visit Reactivation Emails|boolean"
											."||confirmApptReactivationsProvider|Always request confirmation for visit reactivations|boolean"
											."||autoEmailApptChangesProvider|Visit Change Emails|boolean"
											."||confirmApptModificationsProvider|Always request confirmation for visit changes|boolean"								
											."||sendScheduleAsCalendar|Use Calendar format for emailed Client schedules|boolean"
											."||enableSitterTipMemos|Notify sitters when Gratuities are received|boolean"
											;
											
$sitterExplanations =	""										
											."autoEmailScheduleChangesProvider|When a schedule is saved, email a memo to the sitter."
											."||confirmNewSchedulesProvider|In New Schedule memos, ask the sitter to confirm via a web link."
											."||confirmSchedulesProvider|In the Schedule Change memos, ask the sitter to confirm via a web link."
											."||autoEmailApptCancellationsProvider|When an individual visit is canceled or deleted, email a memo to the sitter."
											."||confirmApptCancellationsProvider|In the visit cancellation memo, ask the sitter to confirm via a web link."
											."||autoEmailApptReactivationsProvider|When an individual visit is uncancelled (reactivated), email a memo to the sitter."
											."||confirmApptReactivationsProvider|In the visit reactivation memo, ask the sitter to confirm via a web link."
											."||autoEmailApptChangesProvider|When changes to an individual visit are saved, email a memo to the sitter."
											."||confirmApptModificationsProvider|In the visit change memo, ask the sitter to confirm via a web link."								
											."||sendScheduleAsCalendar|The alternative is a line-by-line list of visits."
											."||enableSitterTipMemos|Allow sitters to receive notices when Gratuities are received."
											;
											
if(FALSE && mattOnlyTEST()) {
	$clientEmailPrefs	= str_replace('|boolean', '|client_boolean', $clientEmailPrefs);
	$providerEmailPrefs	= str_replace('|boolean', '|provider_boolean', $providerEmailPrefs);
}

$realTimeVisitNotification = TRUE; //$_SESSION['preferences']['clientArrivalCompletionRealTimeNotification'];
if(staffOnlyTEST() || $realTimeVisitNotification) {
	$clientEmailPrefs	.= 	
		"||HR"
		."||showClientCompletionDetails|Show Client Visit Completion details on calendar|client_boolean"
		.($realTimeVisitNotification ? "||notifyClientCompletionDetails|Notify Client by email on Visit Completion|client_boolean" : '')
		."||showClientArrivalDetails|Show Client Visit Arrival details on calendar|client_boolean"
		.($realTimeVisitNotification ? "||notifyClientArrivalDetails|Notify Client by email when sitter Arrives|client_boolean" : '');
}

$prefListSections = 
						array(
									'Client Email'=>$clientEmailPrefs,
									'Sitter Email'=>$providerEmailPrefs
									);
									
$explanations = 									
						array(
									'Client Email'=>$clientExplanations,
									'Sitter Email'=>$sitterExplanations
									);
if(mattOnlyTEST()) $sectionParams =
						array(
									//'Client Email'=>$clientExplanations,
									'Sitter Email'=>array('applyToAll'=>'sitters')
									);
$allOrNone = array('all'=>'all', 'none'=>array());									
									
$showSections = 
	isset($allOrNone[$_REQUEST['show']]) 
	? $allOrNone[$_REQUEST['show']]
	: ($_REQUEST['show'] ? explode(',', $_REQUEST['show']) : 'all');

preferencesTable($prefListSections, $help, $showSections, null, $explanations, $sectionParams);

if($_REQUEST['dump']) {
	foreach($prefListSections as $label => $section) {
		echo "<p># $label<br>";
		$section = explode('||', $section);
		foreach($section as $value) {
			$value = explode('|', $value);
			$k = $value[0];
			echo "$k = \"{$_SESSION['preferences'][$k]}\"<br>";
		}
	}
}
		
//preferencesEditorLauncher($prefsDescription);
?>
<script language='javascript'>
<? 
dumpPrefsJS();
dumpShrinkToggleJS();
?>

function openSections() {
	var open = new Array();
	var numClosed = 0;
	var el;
	for(var i=1; el = document.getElementById('section'+i); i++) {
		if(el.style.display == 'none') numClosed++;
		else open.push(i);
	}
	if(numClosed == 0) return 'all';
	else return open.join(',');
}

function updateProperty(property, value) {
	//window.refresh();
	document.location.href='<?= basename($_SERVER['SCRIPT_NAME']) ?>?show='+openSections();
	document.getElementById('prop_'+property).scrollIntoView();
}

</script>

<?
if(!$show) { ?>
<img src='art/spacer.gif' width=1 height=300>
<?
}
// ***************************************************************************
include "frame-end.html";
