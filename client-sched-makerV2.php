<? // client-sched-makerV2.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";
require_once "pet-fns.php";
require_once "petpick-grid-client.php";
require_once "time-framer-mouse.php";
require_once "client-sched-request-fns.php";
require_once "request-fns.php";

if(mattOnlyTEST()) {
	require_once "preference-fns.php";
	refreshPreferences();
}

$longScheduleRequestTEST = dbTEST('tonkatest');//dogslife,

if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('c-');
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract(extractVars('start,end,print,action,totalCharge,mobileclient,copyReminderShown', $_REQUEST));

if(!$start) {
	$userNotice = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientSchedulerWelcomeNotice'");
	if($userNotice) {
		$userNotice = json_decode($userNotice, 'assoc');
		if($userNotice['props']) {
			require_once "screen-notice-fns.php";
			$template = composeTimelyScreenNotice($userNotice);
		}
		else {
			$message = "<span class='fontSize1_5em'>".$userNotice['message']."</span>";
			$template = "<h2>{$userNotice['title']}</h2>$message";
		}
	}
	$_SESSION['user_notice'] = $template;
}



$currencyMark = getCurrencyMark();

// TEMPORARY
$client = $roDispatcher ? $_REQUEST['id']  : $_SESSION['clientid'];
//$pageTitle = "Schedule Services";

$displayOn = $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';
$descriptionColor = '#B7FFDB';

if($action == 'quit') {
	clearLatestScheduleStep2Time(); // see client-sched-request-fns.php
	globalRedirect("index.php");
	exit;
}

//print_r($_POST);exit;
if($_POST && isset($_POST['servicecode_1_1'])) {
	if($copyReminderShown) $_SESSION['copyReminderShown'] = 1;
	foreach($_POST as $key => $val) {
		if(strpos($key, 'pets_') === 0  && $val) $_REQUEST[$key] = stripslashes($val);
		if(strpos($key, 'servicecode_') !== 0 || !$val) continue;
		// assume service is complete or empty
	}
	if($action == 'preview') {
if($_SESSION['preferences']['enableIncompleteScheduleNotifications'] && $_SESSION['clientid']) { // do this ONLY when the client himself is logged in
		setLatestScheduleStep2Time($_POST); // see client-sched-request-fns.php
}
		include "client-sched-previewV2.php";
		exit;
	}
	if($action == 'submit') {    // SUBMIT SCHEDULE (STEP 3)
		$requesttype = 'Schedule';
		//start+'&end='+end+'&days='+times+'&price='+totalCharge
		$payload = "$start|$end|$totalCharge\n";
		$services = array();
		$days = array();
		$day = '';
		
		if($longScheduleRequestTEST) {
			if($_POST['payload']) $postPayload = json_decode($_POST['payload'], $assoc=true);
			if(!$postPayload) $_POST['note'] .="\n BAD PAYLOAD? {$_POST['payload']}";
		}
		else $postPayload = $_POST;
		
		foreach($postPayload as $key=>$val) {
			if(strpos($key, 'servicecode_') === 0) {
				$specifier = substr($key, strlen('servicecode_'));
				$dayService = explode('_', $specifier);
				if($day && $dayService[0] != $day) {
					$days[] = join('|', $services);
					$services = array();
				}
//if($longScheduleRequestTEST && $dayService[0] != max($day, 1)) {
//echo "<hr>DAY: $day DAYSERVICE: {$dayService[0]}<p>";for($i=$day+1;$i<$dayService[0];$i++) $days[] = "";} // for empty days
				$day = $dayService[0];
//echo "$key => $specifier<br>";			
				if($postPayload["timeofday_$specifier"]) 
					$services[] = $postPayload["servicecode_$specifier"].'#'.$postPayload["timeofday_$specifier"].'#'.$postPayload["pets_$specifier"];
			}
		}
		$days[] = join('|', $services);
		$payload .= join('<>', $days);
		$payload .= "\n".urlencode($_POST['note']);
		
			// Note format:
			// line 0: start|end|totalCharge
			// line 2: service|service|..<>service|service|..<>
			// service: servicecode#timeofday#pets
		$request = array('note'=>$payload, 'requesttype'=>$requesttype, 'clientptr'=>$client);
		
		if(!($requestID = saveNewClientRequest($request, true))) {
			$error = mysql_error();
			logChange($client, 'clientScheduler', 'm', "Step 3: $error");

		}
		else {
			logChange($client, 'clientScheduler', 'm', "Step 3: request saved: $requestID");
		}
		if($roDispatcher && !$error) {
			echo "<script language='javascript'>if(window.opener.update) window.opener.showFrameMsg('Schedule Request has been sent.');window.close();</script>";
			exit;
		}
		
		//saveNewClientRequest($request);
		$successMessage = $_SESSION['preferences']['scheduleRequestAcknowledgement'];
		$successMessage = $successMessage 
			? $successMessage 
			: "Your schedule request has been submitted.<p>We'll be getting back to you shortly.<p>Thank you!";
if($_SESSION['preferences']['enableIncompleteScheduleNotifications']) {
		//if($inks = findIncompleteScheduleRequestClients($returnAll=TRUE))
		//	$successMessage = "$successMessage".notificationTextForIncompleteScheduleRequests($inks);
		//$successMessage = "$successMessage".print_r(findIncompleteScheduleRequestClients($returnAll=FALSE), 1);
		clearLatestScheduleStep2Time(); // see client-sched-request-fns.php
}
		if(mysql_error()) $error = mysql_error();
		else $finalMessage = $successMessage;
	}
}
if($_GET && $_SESSION['finalScheduleMessage']) {
	$finalMessage = $_SESSION['finalScheduleMessage'];
	unset($_SESSION['finalScheduleMessage']);
}
// Phase 1: Show only Dates, then 
//if(!$print) {

	// ***************************************************************************
	if($start && !$finalMessage) $suppressMenu = 1;
	if($roDispatcher) {
		$details = getOneClientsDetails($client);
		$windowTitle = "Create a Schedule for {$details['clientname']}";
		include "frame-bannerless.php";
	}
	else if($mobileclient) include "mobile-frame-client.php";
	else include "frame-client.html";
	
	if($error) echo "<font color='red'>$error</font><p>";
	else if($finalMessage) {
		if(FALSE && $_POST && mattOnlyTEST()) { // schedule just submitted
			$_SESSION['finalScheduleMessage'] = $finalMessage;
			$_SESSION['user_notice'] = $finalMessage;
			globalRedirect();
		}
		else {
			echo "<span style='color:green;font-size:1.5em'>$finalMessage</span><p>";
			if(!$roDispatcher) {
					if($mobileclient) include "mobile-frame-client-end.php";
					else include "frame-end.html";
			}
		}
		exit;
	}

	if($start) {
		$defaultPetsChoice = 'All Pets';
		$allPetNames = getClientPetNames($client);
		makePetPicker('petpickerbox',$allPetNames, 'petpicker_option', 'narrow');
		makeSimplifiedTimeFramer('timeframerdiv');
		logChange($client, 'clientScheduler', 'm', 'Step 1: create');
	}		
	
	
	$step1Img = 'art/sched-serv-1-on.jpg';
	$step2Img = 'art/sched-serv-2-off.jpg';
	$step3Img = 'art/sched-serv-3-off.jpg';
	$nextButton = "<p>&nbsp;</p>".echoButton('', 'Next', 'previewSchedule()', 'BigButton', 'BigButtonDown', 1, 'Review Visits before submitting your request.');
	$quitButton = "<p>".echoButton('', 'Quit', 'safeQuit()', null, null, 1);
?>
<style>
.clientschednavtable td { border-width:0px; padding:0px; }
</style>
<table style='width:100%' border=0>
	<tr>
	<td style='padding-right:0px;'><img src='<?= $step1Img ?>'><img src='<?= $step2Img ?>'><img src='<?= $step3Img ?>'></td>
	<td valign=middle style='padding-right:40px;'><?= $nextButton.$quitButton ?></td>
	<? if(!$_SESSION['preferences']['suppressClientSchedulerPriceDisplay']) { ?>
	<td>Total Price: <span style='color:green;font-size:1.4em;font-weight:bold;'><?= "$currencyMark " ?><span id='totalChargeSpan'></span></span></td>
	<? } ?>
	<td>Visits: <span style='color:green;font-size:1.4em;font-weight:bold;'><span id='totalVisits'>0</span></span></td>
	</tr>
	</table>
	<? if($suppressMenu) {
		echo "<p style='text-align:center;font-size:1.1em;color:red;background:yellow;'>YOUR SCHEDULE HAS NOT BEEN SUBMITTED. PLEASE REMEMBER TO CLICK THE <span style='font-weight:bold;font-size:1.2em'>Next</span> BUTTON TO CONTINUE.</p>";
	}
	
		if(!$start && !$_SESSION['preferences']['simpleClientScheduleMaker']) {
			$youtube = FALSE ? '' : "<p style='font-size:0.8em'><a href='https://youtu.be/2PoW1WMqWJc' target='_blank'>... or click here to watch at YouTube</a></p>";
			echo "<div style='border: solid black 1px;position:absolute;top:20px;left:630px;background:white;width:110px;height:70px;text-align:center;padding:5px;padding-bottom:2px;font-size:1.2em;font-weight:bold;color:blue;cursor:pointer;'
			       ><span onclick='showVideo()'>
			Click here to watch the video <i>How To Request Services</i></span>
						$youtube</div>";
		}
	?>
	<form name='clientschedmakerform' method='POST'>
<?
	$daysToShow = $_SESSION['preferences']['clientScheduleMakerDays'] ? $_SESSION['preferences']['clientScheduleMakerDays'] : 6;

	$scheduleDays = round((strtotime($end) - strtotime($start)) / (60 * 60 * 24)) + 1;
	hiddenElement('action', '');
	hiddenElement('payload', '');
	hiddenElement('copyReminderShown',($_SESSION['copyReminderShown'] ? 1 : ''));
	hiddenElement('totalCharge', '');
	hiddenElement('daysToShow', $daysToShow);
	$dateDisplay = $start ? 'none' : 'block';
	echo "<div id='datesDiv' style='display:$dateDisplay'>"
	."<span>Please tell us what services you would like from us, and when.</span><p>";

	calendarSet('Starting on what day? ', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	$changeDateLabel = $start ? 'Change to these dates' : "Let's Start with these dates";
	echo " ";
	echoButton('', $changeDateLabel, 'changeDates()');
	if($start) {
		echo " ";
		echoButton('', 'Keep These Dates', 'showHideDates()');
	}
	echo "</div>";
// Phase 2: Show Day boxes
	if($start) {
		$filterClientServicesByPet = $_SESSION['preferences']['enableclientservicepettypefilter'] ? $_SESSION['clientid'] : null;
		$globalServiceSelections = getClientServices($filterClientServicesByPet);
//print_r($globalServiceSelections);		exit;
		//foreach($sels as $label => $id) $globalServiceSelections[substr($label, 0, min(14, strlen($label)))] = $id;

		$start = date('Y-m-d',strtotime($start));
		$end = date('Y-m-d',strtotime($end));
		
		require_once "preference-fns.php";
		$_SESSION['preferences'] = fetchPreferences();
		if($lastSchedulingDays = $_SESSION['preferences']['lastSchedulingDays']) {
			$lastSchedulingMessage = $_SESSION['preferences']['lastSchedulingMessage'];
		}
?>
<?
$daysToShow = $_SESSION['preferences']['clientScheduleMakerDays'] ? $_SESSION['preferences']['clientScheduleMakerDays'] : 6;

$scheduleDays = round((strtotime($end) - strtotime($start)) / (60 * 60 * 24)) + 1;


dumpScheduleLooks($daysToShow, $descriptionColor);
?>
<p>
<table style='width:100%;padding-left:5px;padding-right:5px;'>
<tr><td>
<? 	
		$previousLink = $longScheduleRequestTEST 
			? ' '.fauxLink("<span class='previousnextlink'>&#9673;</span>", 'slideToStart()', 1, 'Show First days', '', 'previousnextlink')
			: '';
		$previousLink .= fauxLink("<span style='display:none;' name='previousLink' class='previousnextlink'>&lt; Show Previous Days</span>", 'slideRight()', 1, 'Show prevous days', '', 'previousnextlink');
		echo $previousLink;//fauxLink('< Show Previous Days', 'slideRight()'); 
 ?>
</td>
<td align=center>
<? echo '<b>First Day:</b> '.longDayAndDate(strtotime($start)).' - <b>Last Day:</b> '.longDayAndDate(strtotime($end))."<img src='art/spacer.gif' height=1 width=10>";
   fauxLink('Change Dates', 'showHideDates()')
?>
</td>
<td align='right'>
<? 	$displayNext = $scheduleDays > $daysToShow ? 'inline' : 'none';
		$nextLink = fauxLink("<span style='display:$displayNext;' name='nextLink' class='previousnextlink'>Show Following Days ></span>", 'slideLeft()', 1, 'Show Following days', '', 'previousnextlink');
		$nextLink .= $longScheduleRequestTEST 
			? ' '.fauxLink("<span class='previousnextlink'>&#9673;</span>", 'slideToEnd()', 1, 'Show Following days', '', 'previousnextlink')
			: '';
		echo $nextLink;
 ?>
</td></tr>
</table>
<table style='width:100%;border-collapse:collapse;padding-left:0px;padding-right:0px;'>
<tr><td 
<tr>
<!-- td id='leftArrow' style='padding-top:100px;vertical-align:top;'><img src='art/prev_day_tall.gif' onclick='slideRight()' style='cursor:pointer;' title='View previous days'></td -->
<?
	$nDays = 1;
	for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
		$displayStatus = $nDays <= $daysToShow ? $displayOn : 'none';
		echo "<td id='day_$nDays' style='display:$displayStatus;vertical-align:top;'>\n";
		displayDay($day, $nDays);
		echo "</td>\n";
		$nDays++;
	}
?>
<!--td id='rightArrow' style='padding-top:100px;vertical-align:top;'><img src='art/next_day_tall.gif' onclick='return slideLeft()' style='cursor:pointer;' title='View following days'></td -->
<tr>
</table>
<table style='width:100%;padding-left:5px;padding-right:5px;'>
<tr><td>
<? 
	echo $previousLink;//fauxLink('< Show Previous Days', 'slideRight()'); ?>
</td>
<td align='right'>
<? echo $nextLink; ?>
</td></tr>
</table>
<p style='font-size:1.1em'>Note:</p>
<textarea id='note' name='note' cols=80 rows=10><?= $_POST['note'] ?></textarea>
<?
} // if($start)

?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='client-sched-visit-payload.js'></script>




<script language='javascript'>
setPrettynames('start','Starting Day', 'end', 'Ending Day');
<? // PHASE 2
if($nDays) {
	
dumpClientScheduleDisplayJS($displayOn, $nDays-1);
?>	
function safeQuit() {
	if(confirm('Are you sure you want to quit?  No schedule request will be sent.'))
		document.location.href='client-sched-makerV2.php?action=quit';
}

function addService(dayIndex) {
	var blockStyle = 'block';
	var maxServicesPerDay = <?= $maxServicesPerDay ?>;
	var specifier = 'service_'+dayIndex+'_';
	for(var serv = 1; serv <= maxServicesPerDay; serv++) {
		if(document.getElementById(specifier+serv).style.display == 'none' 
		   && document.getElementById('serviceview_'+dayIndex+'_'+serv).innerHTML == '') {
			document.getElementById(specifier+serv).style.display = blockStyle;
			return;
		}
	}
}
	
function deleteService(dayIndex, serv) {
	var specifier = dayIndex+'_'+serv;
	var visible = 0;
	var maxServicesPerDay = <?= $maxServicesPerDay ?>;
	for(var serv = 1; serv <= maxServicesPerDay; serv++)
		if(document.getElementById('service_'+dayIndex+'_'+serv).style.display != 'none') visible++;
	/*if(visible > 1)*/
	document.getElementById('service_'+specifier).style.display = 'none';
	document.getElementById('serviceview_'+specifier).style.display = 'none';
	document.getElementById("div_timeofday_"+specifier).innerHTML = '';
	document.getElementById("timeofday_"+specifier).value = '';
	document.getElementById("div_pets_"+specifier).innerHTML = '<?= $defaultPetsChoice ?>';
	document.getElementById("pets_"+specifier).value = '<?= $defaultPetsChoice ?>';
	document.getElementById("servicecode_"+specifier).selectedIndex = 0;
	document.getElementById("serviceview_"+specifier).innerHTML = '';
	document.getElementById("span_charge_"+specifier).innerHTML = '';
	document.getElementById("charge_"+specifier).value = 0;
	setTotalCharge();
}

function editAction(dayIndex, serv) {
	var blockStyle = 'block';
	var specifier = dayIndex+'_'+serv;
	document.getElementById("serviceview_"+specifier).style.display = 'none';
	document.getElementById('service_'+specifier).style.display = blockStyle;
}

function copyAction(element, dayIndex, serv) {
	var blockStyle = '<?= $_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-row' : 'block' ?>';
	document.getElementById("copyrow_"+dayIndex+"_"+serv).style.display = blockStyle;
	IGNORECLICK = true;
	document.body.onclick= function () {
		if(!IGNORECLICK) {
			var allTRs = document.getElementsByTagName('tr');
			for(var i=0; i < allTRs.length; i++)
				if(allTRs[i].id.indexOf("copyrow_") == 0)
					allTRs[i].style.display = 'none';
		}
		IGNORECLICK = false;
		};
	//alert(document.body.onclick);
}

function doneAction(dayIndex, serv) {
	var blockStyle = 'block';
	var specifier = dayIndex+'_'+serv;
	preSubmit();
	if(document.getElementById("timeofday_"+specifier).value == '' 
			|| document.getElementById("servicecode_"+specifier).selectedIndex == 0) {
		alert('Please specify both time and service first.');
		return;
	}

	if(document.getElementById('copyReminderShown') && document.getElementById('copyReminderShown').value != 1) {
		alert('Remember you can save time by using the "Copy" feature to copy this visit to Tomorrow, to All Future Days, or to All Days.');
		document.getElementById('copyReminderShown').value = 1;
	}

	document.getElementById("serviceview_"+specifier).innerHTML = makeServiceDescription(dayIndex, serv);
	document.getElementById("serviceview_"+specifier).style.display = blockStyle;
	document.getElementById('service_'+specifier).style.display = 'none';
	setTotalCharge();
}

function makeServiceDescription(dayIndex, serv) {
	var specifier = dayIndex+'_'+serv;
	var sel = document.getElementById("servicecode_"+specifier);
	var service = sel.options[sel.selectedIndex].innerHTML;
	var charge = document.getElementById("span_charge_"+specifier).innerHTML;
	var timeofday = document.getElementById("div_timeofday_"+specifier).innerHTML;
	var pets = document.getElementById("div_pets_"+specifier).innerHTML;
	
	return( "<table align=center class='descriptiontable'>"
	//+ <b>Service:</b><br>
	+ "<tr><td colspan=3>"+service +"</td><tr>\n"
  //+"<tr><td colspan=3><b>Charge:</b> \$"+charge+"</td></tr>"
	+/*<b>Time of Day:</b><br>*/"<tr><td colspan=3>"+timeofday+"</td></tr>"
	+/*<b>Pets:</b><br>*//*"<tr><td colspan=3>"+pets+"</td></tr>"*/""
	+"<tr><td>"
	+"<a class='fauxlink' onclick='copyAction(this, "+dayIndex+", "+serv+")' title='Copy this service'>Copy</a>"
	+'</td><td>'
	+"<a class='fauxlink' onclick='editAction("+dayIndex+", "+serv+")' title='Edit this service'>Edit</a>"
	+'</td><td>'
	+"<a class='redfauxlink' onclick='deleteService("+dayIndex+", "+serv+")' title='Delete this service'>Delete</a>"
	+"</td></tr>"
	
	+"<tr id='copyrow_"+specifier+"' style='display:none;'>"
	+"<td colspan=3 class='fauxlink'><a onclick='copyToTomorrow("+dayIndex+", "+serv+")'>...to Tomorrow</a>"
	+(dayIndex > 1 && dayIndex < <?= $scheduleDays ?> ? "<br><a onclick='copyToAllFutureDays("+dayIndex+", "+serv+")'>...to All Future Days</a>" : "")
	+"<br><a onclick='copyToAllDays("+dayIndex+", "+serv+")'>...to All Days</a></td></tr>"
	
	+"</table>");
	
}

function findServiceOnDay(serviceIndex, timeofday, pets, dayIndex) {
	var maxServicesPerDay = <?= $maxServicesPerDay ?>;
	for(var serv = 1; serv <= maxServicesPerDay; serv++) {
		if(document.getElementById("div_timeofday_"+dayIndex+"_"+serv).innerHTML == timeofday &&
				document.getElementById("servicecode_"+dayIndex+"_"+serv).selectedIndex == serviceIndex)
//alert("Found service at "+serv);
				return serv;
			}
	return 0;
}

function findAndShowEmptyServiceOnDay(newDay, editmode) {
	var maxServicesPerDay = <?= $maxServicesPerDay ?>;
	for(var serv = 1; serv <= maxServicesPerDay; serv++) {
		var pets = document.getElementById("div_pets_"+newDay+"_"+serv).innerHTML;
		if(document.getElementById("div_timeofday_"+newDay+"_"+serv).innerHTML == ''
				&& (pets == '' || pets == '<?= $defaultPetsChoice ?>')
				&& document.getElementById("servicecode_"+newDay+"_"+serv).selectedIndex == 0) {
			if(editmode) {
				document.getElementById('service_'+newDay+'_'+serv).style.display = 'block';
				document.getElementById('serviceview_'+newDay+'_'+serv).style.display = 'none';
			}
			else {
				document.getElementById('service_'+newDay+'_'+serv).style.display = 'none';
				document.getElementById('serviceview_'+newDay+'_'+serv).style.display = 'block';
			}
//alert('bang!');
			return serv;
		}
	}
	return 0;
}	
	
function copyToTomorrow(dayIndex, serv) {
	copyToNewDay(dayIndex, serv, dayIndex+1);
	setTotalCharge();
	
}

function copyToAllDays(dayIndex, serv) {
	for(var i=1; i <= <?= $scheduleDays ?>; i++)
		if(i != dayIndex) copyToNewDay(dayIndex, serv, i);
	setTotalCharge();
}

function copyToAllFutureDays(dayIndex, serv) {
	for(var i=dayIndex+1; i <= <?= $scheduleDays ?>; i++)
		if(i != dayIndex) copyToNewDay(dayIndex, serv, i);
	setTotalCharge();
}

function copyToNewDay(dayIndex, serv, newDay) {
	var serviceIndex = document.getElementById("servicecode_"+dayIndex+"_"+serv).selectedIndex;
	var timeofday = document.getElementById("div_timeofday_"+dayIndex+"_"+serv).innerHTML;
	var pets = document.getElementById("div_pets_"+dayIndex+"_"+serv).innerHTML;
	var charge = document.getElementById("span_charge_"+dayIndex+"_"+serv).innerHTML;
	
	if(serviceIndex == 0 && timeofday == '' && (pets == '' || pets == '<?= $defaultPetsChoice ?>'))
		return;
	
	var targetServ = findServiceOnDay(serviceIndex, timeofday, pets, newDay);
	if(targetServ == 0) targetServ = findAndShowEmptyServiceOnDay(newDay);
	if(targetServ == 0) alert("Could not copy the service to Day #"+newDay);
	else {
		document.getElementById("div_timeofday_"+newDay+"_"+targetServ).innerHTML = timeofday;
		document.getElementById("div_pets_"+newDay+"_"+targetServ).innerHTML = pets;
		document.getElementById("servicecode_"+newDay+"_"+targetServ).selectedIndex = serviceIndex;
		document.getElementById("span_charge_"+newDay+"_"+targetServ).innerHTML = charge;
		preSubmit();
		document.getElementById("serviceview_"+newDay+"_"+targetServ).innerHTML = makeServiceDescription(newDay, targetServ);
	}
	
}
/*
td id='day_1' style='display:block'
table id='daytable_1' 
div id='service_1_1' 
*/

<? 
dumpPetGridJS('petpickerbox',$allPetNames);
dumpTimeFramerJS('timeframerdiv');
if($lastSchedulingDays) {
	if(strtotime($start) - time() < $lastSchedulingDays * 24 * 60 * 60) {
		$warning = str_replace("\r", "", str_replace('"', '\"', $lastSchedulingMessage));
		$warning = str_replace("\n\n", "<p>", $warning);
		$warning = str_replace("\n", "<br>", $warning);
?>		
var warning = "<div style='font-size:1.5em;padding:15px;'><?= $warning ?></div>";
$(function() {$.fn.colorbox({html:warning, width:"600", height:"400", scrolling: true, opacity: "0.3"});});
<?
}}

}  // END PHASE 2 INCLUSION
?>
var clientCharges = <?= getClientChargesJSArray($client) ?>;
var standardCharges = <?= getStandardChargeDollarsJSArray() ?>;
var extraPetCharges = <?=   getServicRateDollarsJSArray(getStandardExtraPetChargeDollars()); ?>;
setAllCharges();
setTotalCharge();

function submitForm(action) {
	//alert('Patience is a virtue, Ted'); 
	if(allDone() && MM_validateForm(
			'start', '', 'R',
			'end', '', 'R',
			'start', '', 'isDate',
			'start', 'NOT', 'isPastDate',
			'start','end','datesInOrder'
			) ) {
		document.getElementById('action').value = action;
		preSubmit();
<? if($longScheduleRequestTEST) echo "if(document.getElementById('payload')) document.getElementById('payload').value = getVisitPayload();\n" ?>
		document.clientschedmakerform.submit();
	}
	else return;
	
}

function previewSchedule() {
	submitForm('preview');
}

function submitSchedule() { 
	submitForm('submit');
}

function showHideDates() {
	var div = document.getElementById('datesDiv');
	if(div.style.display == 'none') div.style.display = 'block';
	else div.style.display = 'none';
}

function setTotalCharge() {
	document.getElementById('totalCharge').value = calculateTotalCharge();
	if(document.getElementById('totalChargeSpan')) document.getElementById('totalChargeSpan').innerHTML 
		= floatDot2(document.getElementById('totalCharge').value/*+" "+countVisits()+" visits."*/);
	document.getElementById('totalVisits').innerHTML  = countVisits();
}

function countVisits() {
	var visitCount = 0;
	$('select').each(function(index, el) {if(el.selectedIndex) visitCount++;});
	return visitCount;
}

function calculateTotalCharge() {
	var total = 0;
	visitCount = 0;
	var charges = document.getElementsByTagName('input');
	for(var i=0; i < charges.length; i++) {
		if(charges[i].id.indexOf('charge_') == 0) {
			visitCount += 1;
			var ch = parseFloat(charges[i].value);
//if(!confirm((typeof ch)+'= number: '+((typeof ch) == 'number'))) return;
			if(!isNaN(ch)) total += ch;
//if(!confirm('ch: '+ch+' total: '+total)) return;
		}
	}
	return total;
}

function setCharge(sel, chargeid) {
	var charge, service, client = <?= $client ?>;
	var specifier = chargeid.split('_');
	specifier = '_'+specifier[specifier.length-2]+'_'+specifier[specifier.length-1];
//alert("servicecode"+specifier);	
	if(!sel) sel = document.getElementById("servicecode"+specifier);
	if(sel.selectedIndex == 0) charge = 0;
	else charge = lookUpClientServiceCharge(service = sel.options[sel.selectedIndex].value, client);
	var allPets = '<?= addslashes($allPetNames) ?>'.split(',');
	var pets = document.getElementById('div_pets'+specifier).innerHTML;
	var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
	if(numPets > 1)
		charge +=  (numPets - 1) * lookUpExtraPetCharge(service, client);
	
	document.getElementById(chargeid).value = charge;
	document.getElementById('span_'+chargeid).innerHTML = charge;
	setTotalCharge();
}

function petsUpdated(petsDivId) {
	var specifier = petsDivId.split('_');
	specifier = '_'+specifier[specifier.length-2]+'_'+specifier[specifier.length-1];
	var chargeid = "charge"+specifier;
	setCharge(null, chargeid);
}


function setAllCharges() {
	var inputTags = document.getElementsByTagName('select');
	for(var i=0; i < inputTags.length; i++) {
		var sel = inputTags[i];
		if(sel.id.indexOf('servicecode_') === 0) {
			var chargeid = 'charge_'+sel.id.substring('servicecode_'.length);
			setCharge(sel, chargeid);
		}
	}
}

	

function lookUpClientServiceCharge(service, client) {
	if(client) {
		var rate = lookUpServiceCharge(service, clientCharges);
		if(rate != -1) return rate[0];
	}
	rate = lookUpServiceCharge(service, standardCharges);
	return rate[0];
}

function lookUpServiceCharge(service, rates) {  // return [value, ispercentage]
	for(var i=0;i<rates.length;i+=3)  // servicetype,value,ispercentage
	  if(rates[i] == service)
	    return [rates[i+1],rates[i+2]];
	return -1;
}

function lookUpExtraPetCharge(service, client) {
	for(var i=0;i<extraPetCharges.length;i+=3)  // servicetype,value,ispercentage
	  if(extraPetCharges[i] == service)
	    return extraPetCharges[i+1];
	return 0;
}


function changeDates() {
	if(allDone() && MM_validateForm(
			'start', '', 'R',
			'end', '', 'R',
			'start', '', 'isDate',
			'start', 'NOT', 'isPastDate',
			'start','end','datesInOrder'
			) ) {
		preSubmit();
		document.clientschedmakerform.submit();
	}
}

function allDone() {
	var divTags = document.getElementsByTagName('div');
	for(var i=0; i < divTags.length; i++) {
		if(divTags[i].id.indexOf("service_") == 0 && divTags[i].style.display != 'none') {
			alert("Please mark all your services 'Done' before continuing.");
			return false;
		}
	}
	return true;
}
		
function preSubmit() {
	var inputTags = document.getElementsByTagName('input');
	for(var i=0; i < inputTags.length; i++) {
		var input = inputTags[i];
		if(input.id.indexOf("pets_") == 0 || input.id.indexOf("timeofday_") == 0) 
			input.value = document.getElementById('div_'+input.id).innerHTML;
		if(input.id.indexOf("charge_") == 0) 
			input.value = document.getElementById('span_'+input.id).innerHTML;
	}		
}

var stopit=false;

function floatDot2(str) {
	if(isNaN(parseFloat(str))) return str;
	return parseFloat(str).toFixed(2);
}

function test() {
	alert(document.getElementById('totalCharge').value);return;
	var total = 0;
	var charges = document.getElementsByTagName('input');
	for(var i=0; i < charges.length; i++) {
		if(charges[i].id.indexOf('charge_') == 0) {
			var ch = parseFloat(charges[i].value);
//if(!confirm((typeof ch)+'= number: '+((typeof ch) == 'number'))) return;
			if(!isNaN(ch) && ch > 0) {total += ch; alert('ch: '+ch+' total: '+total);
//if(!confirm('ch: '+ch+' total: '+total)) return;
		}
	}
}}

function showVideo() {
	<? if(strpos($_SERVER["HTTP_USER_AGENT"], 'MSIE')) echo 'alert(\'Please click "No" if you are asked if you want to display only secure webpage content.\');'; ?>
	$.fn.colorbox({href:"HowToRequestServiceV2.htm", width:"870", height:"700", scrolling: true, opacity: "0.3", iframe: "true"});
}
<? 
dumpPopCalendarJS(); 

?>

</script>




<br><img src='art/spacer.gif' width=1 height=300>
<? //fauxLink('test', 'test()');
	// ***************************************************************************
	if(!$roDispatcher) {
		if($mobileclient) include "mobile-frame-client-end.php";
		else include "frame-end.html";
	}
