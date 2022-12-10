<? // holidays-recurring.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "surcharge-fns.php";
require_once "holidays-future.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "appointment-fns.php";
require_once "request-fns.php";

locked('o-');

if($_GET['initial']) issueSystemHolidayNotice($exactDate=false);  // this will look for holidays from today to the lookahead period
$lookahead = $_REQUEST['lookahead']; // will be supplied only if lookahead select is offered below

if($_POST) {  // AND... 'action' is set
	$action = $_POST['action'];
	foreach($_POST as $k => $v) 
		if(strpos($k, 'appt_') === 0)
			$ids[] = substr($k, strlen('appt_'));
	if($action == 'cancel') {
		cancelAppointments($ids, 'cancel', $additionalMods=null, $generateMemo=true, $initiator='upcoming holidays');
	}
	else if($action == 'sendRequests') {
		$result = sendRequests($ids);
		if($result['noEmail']) {
			$errors = "Problems were found with the following clients:<ul>";
			foreach($result['noEmail'] as $client) {
				$descr = array();
				if(!$client['email']) $descr[] = "email address";
				if(!$client['userid']) $descr[] = "system login";
				$errors .= "<li>{$client['clientname']} - no ".join(' or ', $descr);
			}
			$errors .= "</ul>";
		}
		$message = ($result['numSent'] ? $result['numSent'] : 'No')
								.($result['numSent'] == 1 ? ' confirmation request was' : ' confirmation requests were')
								." sent.<hr>";
	}
}
$appts = upcomingHolidayRecurringAppts(false, $lookahead); // show all upcoming
//if(mattOnlyTEST()) echo count($appts)." visits.";
$pageTitle = "Upcoming Holiday Recurring Visits";

//$breadcrumbs = "<a href='comm-prefs.php'>Communication Preferences</a> ";

include "frame.html";
$lookahead = $lookahead ? $lookahead : (
	$_SESSION['preferences']['holidayVisitLookaheadPeriod'] 
		? $_SESSION['preferences']['holidayVisitLookaheadPeriod'] : 30); 
?>
<style>
.toptable td {font-size:1.2em}
.toptable {font-size:1.2em}
</style>
<div id='instructions' style='display:none;'><span class='toptable'>
This page shows you all recurring visits that are currently scheduled on holidays in the next <b><?= $lookahead ?></b> days.  It gives you the option to:
<ul>
<li>Ask clients to confirm cancellation of selected visits
<li>Cancel selected visits
<li>Add surcharges to selected visits
</ul>
<p>
The link in the Service column for each visit opens up the visit editor so you can view and change details of the visit.
<p>
The visit&apos;s <img src='art/add-surcharge.gif'> icon opens an editor for adding surcharges to the visit, and the Surcharges column shows surcharges already associated with each visit.
<p>
The number of days that LeashTime looks ahead for recurring holiday visits (currently <?= $lookahead ?>) can be changed.  See:
<p>
ADMIN > Preferences > [ Holiday Preferences ]  Holiday Visit Lookahead Period (days)
</span>
</div>
<script language='javascript'>
function showInstructions() {
	$.fn.colorbox({html:$("#instructions").html(), width:"750", height:"470", scrolling: true, opacity: "0.3"});
}
</script>
<?
if($errors) echo "<font color=red>$errors</font><p>";
if($message) echo "<span class='pagenote'>$message</span><p>";

for($i=7;$i<=75;$i++) $days[]=$i;
foreach($days as $day) $options[$day] = $day;
if(!mattOnlyTEST() && staffOnlyTEST()) {
	echo "<form name='pickdays' method='POST'>";
	labeledSelect("Look ahead:", 'lookahead', $lookahead, $options, $labelClass='fontSize1_5em', $inputClass='fontSize1_5em', $onChange="document.pickdays.submit()", $noEcho=false);
	echo "<span class='fontSize1_5em'> days.</span></form>";
}


$helpButton = "<img src='art/help.jpg' height=30 width=30 title='Instructions' onclick='showInstructions()'>";
if(!$appts) {
	echo "<table class='toptable' border=0><tr><td>";
	echo "There are no upcoming (uncanceled) recurring visits on holidays in the next $lookahead days.";
	echo "</td><td>$helpButton</td>
	</tr></table>
	<div style='height:300px;'>&nbsp;</div>
	<script language='javascript' src='common.js'></script>";
	include "frame-end.html";
	exit;
}
?>
<table class='toptable' border=0><tr><td>
<p class='pagenote'>
The following clients have upcoming visits on recurring schedules that fall on a Holiday. 
<br>
You may email them asking them to confirm they want the selected visits to be canceled.  
</p>
<p class='pagenote'>
With the selected visits....  
</p>
</td><td><?= $helpButton ?></td>
</tr>
</table>
<p class='toptable' style='text-align:center'>
<?
echoButton('', 'Ask Clients to Confirm Cancellation of Appointments', 'sendRequests()');
echo " ";
echoButton('', 'Cancel Appointments', 'cancelAppts()');
echo "<p>";
fauxLink('Select All Visits', 'selectAll(1)');
echo "<img src='art/spacer.gif' width=20 height=1>";
fauxLink('Select All UncanceledVisits', 'selectAll(-1)');
echo "<img src='art/spacer.gif' width=20 height=1>";
fauxLink('Deselect All Visits', 'selectAll(0)');
?>
<form name='apptsform' method='POST'>
<?
hiddenElement('action', '');
hiddenElement('lookahead', $lookahead);
upcomingHolidayRecurringApptsTable($appts);
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function selectAll(on) {
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled) {
			if(on == -1 && cbs[i].getAttribute('class') == "canceled") continue;
			else cbs[i].checked = on ? true : false;
		}
}

function disableAllButtons() {
	$('.Button').prop('disabled', true);
	$('.ButtonDown').prop('disabled', true);
}

function enableAllButtons() {
	$('.Button').prop('disabled', false);
	$('.ButtonDown').prop('disabled', false);
}

function sendRequests() {
	disableAllButtons();
	var noSelections = countSelections() == 0 ? "Please select at least one visit first." : '';
	if(MM_validateForm(
			noSelections, '', 'MESSAGE')) {
		document.getElementById('action').value = 'sendRequests';
		document.apptsform.submit();
	}
	else enableAllButtons();
}

function cancelAppts() {
	disableAllButtons();
	var numSelections = countSelections();
	var noSelections = numSelections == 0 ? "Please select at least one visit first." : '';
	if(MM_validateForm(
			noSelections, '', 'MESSAGE')) {
		if(!confirm("You are about to CANCEL "+numSelections+" visit"+(numSelections == 1 ? '' : 's')+".  Continue?")) {
			enableAllButtons();
			return;
		}
		document.getElementById('action').value = 'cancel';
		document.apptsform.submit();
	}
	else enableAllButtons();
}

function countSelections() {
	var selCount = 0;
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			if(cbs[i].checked) selCount++;
	return selCount;
}
	
function addSurcharge(appointmentid) {
	openConsoleWindow("editsurcharge", "surcharge-edit.php?appointmentptr="+appointmentid,530,450)
}
		
function update() {
	refresh();
}
</script>
<?
include "js-refresh.php";

include "frame-end.html";

function sendRequests($ids) {
	$appts = fetchAssociations("SELECT * FROM tblappointment WHERE appointmentid IN (".join(',', $ids).")");
	foreach($appts as $appt) $clusters[$appt['clientptr']][] = $appt;
	$clientDetails = getClientDetails(array_keys($clusters), array('email', 'userid'));
	$numSent = 0;
	foreach($clusters as $clientptr => $cluster) {
		if(!$clientDetails[$clientptr]['email'] || !$clientDetails[$clientptr]['userid'])
			$noEmail[] = $clientDetails[$clientptr];
		else {
			sendCancellationConfirmationRequest($clientDetails[$clientptr], $cluster);
			$numSent++;
		}
	}
	return array('noEmail'=>$noEmail, 'numSent'=>$numSent);
}


$columnDataLine = 'name|Sitter||date|Date||time|Time Window||service|Service||charge|Charge||pets|Pets||buttons| ';
if(userRole() == 'c') $columnDataLine = 'date|Date||time|Time Window||service|Service||pets|Pets||name|Sitter';



function sendCancellationConfirmationRequest($clientDetail, $appts) {
	require_once "client-schedule-fns.php";
	require_once "response-token-fns.php";
	require_once "confirmation-fns.php";
	ob_start();
	ob_implicit_flush(0);
	clientScheduleTable($appts, $suppressColumns=array('name','charge','buttons'));
	$apptTable = ob_get_contents();
	ob_end_clean();
	
	$apptIds = array();
	$holidates = array();
	foreach($appts as $appt) {
		$apptIds[] = $appt['appointmentid'];
		$holidates[$appt['date']] = 1;
	}
	$holidayNamesSubstitution = staffOnlyTEST() || dbTEST('dogonfitnessrockville');
if($holidayNamesSubstitution) {
	if($holidates) {
		foreach(array_keys($holidates) as $date) {
			$date = '%-'.date('m-d', strtotime($date));
			$holidays[] = 
				fetchRow0Col0($sql = "SELECT label FROM tblsurchargetype WHERE date LIKE '$date' LIMIT 1", 1);
		}
		if(count($holidays) > 1) {
			//$holidays = '';//print_r($holidates,1).' ';
			$lastName = array_pop($holidays);
			$holidays = join(', ', $holidays)." and $lastName";
		}
		else {
			$holidays = $holidays[0];
		}
	}
}
	$holidays = $holidays ? $holidays : '';

	$apptIds = join(',', $apptIds);
	
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Upcoming Holiday Visits Cancellation'");
	if($template) {
		$subject = $template['subject'] ? $template['subject'] : 'Upcoming Holiday Visits Cancellation';
		$confirmationRequestText = $template['body'];
	}
	else {
		$subject = 'Upcoming Holiday Visits Cancellation';
		$confirmationRequestText = <<<MSG
Dear #RECIPIENT#,

The following visit(s) fall on a Holiday.  Please click one of the following links to
<a href='##ConfirmationURL_CANCEL##'>Cancel the visits</a> or <a href='##ConfirmationURL_RETAIN##'>Confirm the visits</a>:
#VISIT_TABLE#

Sincerely,

#BIZNAME#
MSG;
	}
	
	if($holidayNamesSubstitution) $subject = str_replace('#HOLIDAYS#', $holidays, $subject);
	
	$dueIntervalMinutes = $dueIntervalMinutes ? $dueIntervalMinutes : 3 * 24 * 60;
	$expirationMinutes = $expirationMinutes ? $expirationMinutes : 5 * 24 * 60;

	$due = date('Y-m-d H:i:s', time()+($dueIntervalMinutes * 60));
	$expiration = date('Y-m-d H:i:s', time()+($expirationMinutes * 60));

	$client = getClient($clientDetail['clientid']);
	$emailRecipient = $clientDetail['email'];
	$recipients = array("\"{$clientDetail['clientname']}\" <$emailRecipient>");
	$responseURL_CANCEL = generateResponseURL($_SESSION['bizptr'], $client, "confirm-cancellation-holiday.php?cancel=$apptIds&token=", false, $expiration, true);
	if(is_array($responseURL_CANCEL)) {echo $responseURL_CANCEL[0];return;}
	if($responseURL_CANCEL) {
		$token = substr($responseURL_CANCEL, strpos($responseURL_CANCEL, '?token=')+strlen('?token='));
		$confid = saveNewConfirmation(0, $client['clientid'], 'tblclient', $token, $due, $expiration);
		$confirmationIds[] = $confid;
		$responseURL_RETAIN = generateResponseURL($_SESSION['bizptr'], $client, "confirm-cancellation-holiday.php?cancel=0&confid=$confid&token=", false, $expiration, true);
//echo "<p>";print_r(array($_SESSION['bizptr'], $client, "confirm-cancellation-holiday.php?cancel=0&confid=$confid&token=", false, $expiration, true));	
		if(is_array($responseURL_RETAIN)) {echo $responseURL_RETAIN[0];return;}
	}
if(!$confid) {echo "Bad conf!";return;}
	$target = $client;
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	$headerBizLogo = $headerBizLogo ? "<img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes>" :'';

	$message = $confirmationRequestText;
	if(strpos($message, '<p>') == FALSE && strpos($message, '<br>') == FALSE) {
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	$message = str_replace('##ConfirmationURL_CANCEL##', $responseURL_CANCEL, $message);
	$message = str_replace('##ConfirmationURL_RETAIN##', $responseURL_RETAIN, $message);
	$message = str_replace('#RECIPIENT#', "{$target['fname']} {$target['lname']}", $message);
	$message = str_replace('#FIRSTNAME#', $target['fname'], $message);
	$message = str_replace('#LASTNAME#', $target['lname'], $message);
	$message = str_replace('#LOGO#', $headerBizLogo, $message);
	$message = str_replace('#BIZHOMEPAGE#', $_SESSION['preferences']['bizHomePage'], $message);
	$message = str_replace('#BIZEMAIL#', $_SESSION['preferences']['bizEmail'], $message);
	$message = str_replace('#BIZPHONE#', $_SESSION['preferences']['bizPhone'], $message);
	$message = str_replace('#VISIT_TABLE#', $apptTable, $message);
	$message = str_replace('#BIZLOGINPAGE#', "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}", $message);
	$message = str_replace('#HOLIDAYS#', $holidays, $message);
	$bizName = $_SESSION['preferences']['shortBizName'] 
		? $_SESSION['preferences']['shortBizName'] 
		: $_SESSION['preferences']['bizName'];
	$message = str_replace('#BIZNAME#', $bizName, $message);
	
	if(strpos($message, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		$petnames = getClientPetNames($target['clientid'], false, true);
		$message = str_replace('#PETS#', $petnames, $message);
	}
	if(strpos($message, '#MANAGER#') !== FALSE) {
		$managerNickname = fetchRow0Col0(
			"SELECT value 
				FROM tbluserpref 
				WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
		$message = str_replace('#MANAGER#', ($managerNickname ? $managerNickname : $_SESSION["auth_username"]), $message);
	}
	
	$message = msgStyle()."$message<span confirmationid=$confid></span>";


	require_once "comm-fns.php";
	//if($messageAppendix) $message .= html_entity_decode($messageAppendix);
	
	$subject = str_replace('#RECIPIENT#', "{$target['fname']} {$target['lname']}", $subject);
	$subject = str_replace('#FIRSTNAME#', $target['fname'], $subject);
	$subject = str_replace('#LASTNAME#', $target['lname'], $subject);	

	enqueueEmailNotification($client, $subject, $message, $cc=null, $_SESSION['auth_login_id'], $html=true);
	insertTable('tblqueuedconf', array('confid'=>$confid), 1); 
}	
	// a cron job will scan each outgoing message for confirmationid=NNNNN
	// tblqueuedconf will be searched for the confirmationid
	// if found, that confirmation will be linked to the confirmation
	
	
function msgStyle() {
	return  <<<HTML
<style>
.completedtask {
	background: lightgreen;
}

.completedtaskEVEN {
	background: #CDFECD;
}

.noncompletedtask {
	background: #FEFF99;
}

.noncompletedtaskEVEN {
	background: #FEFFB5;
}

.canceledtask {
	background: #FFC0CB;
}

.canceledtaskEVEN {
	background: #FF93A5;
}

.futuretask {
	background: white;
}

.futuretaskEVEN {
	background: #EEE5FF;/* #FFE3E3; rose*/ /* #F6E5F7 lilac*/ /* #EEEEFF VERY light blue */
}
.daycalendardaterow { /* daycalendar td which displays date */
	background:lightblue;
	text-align:center;
	border: solid black 1px;
	font-weight:bold;
}

</style>
HTML;
}

