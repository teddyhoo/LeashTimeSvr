<? // client-sched-preview.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";

$locked = locked('c-');
extract(extractVars('start,end,print,action,totalCharge', $_REQUEST));
//extract(extractVars('start,end,days,totalCharge', $_REQUEST));

if($_POST && isset($_POST['servicecode_1_1'])) {
	
	if($action == 'schedule') {  // BACK TO STEP 1
		include "client-sched-maker.php";
		exit;
	}
	
	if(false) {}
	
	else {
		$apptdays = array();
		$dayService = array();
		$day = '';
		foreach($_POST as $key=>$val) {
			if(strpos($key, 'servicecode_') === 0) {
				$specifier = substr($key, strlen('servicecode_'));
				$dayService = explode('_', $specifier);
				if($day && $dayService[0] != $day) {
					if($services) usort($services, 'serviceStartTimeSort');
					$apptdays[] = $services;
					$services = array();
				}
				$day = $dayService[0];
//echo "$key => $specifier<br>";			
				if($_POST["timeofday_$specifier"]) 
					$services[] = $_POST["servicecode_$specifier"].'#'.$_POST["timeofday_$specifier"].'#'.$_POST["pets_$specifier"];
//if(mattOnlyTEST()) {
					if(!$noTimeTravelAllowed && $day == 1) {  // strcmp($day, date('Y-m-d')) <= 0
						$startDate = date('Y-m-d', strtotime($start));
						if(strcmp($startDate, date('Y-m-d')) < 0) $noTimeTravelAllowed = true;
						else if(strcmp($startDate, date('Y-m-d')) == 0) {
							$servicetime = getServiceStartTime($services[count($services)-1]);
							if(strcmp("$startDate $servicetime", date('Y-m-d H:i')) < 0) $noTimeTravelAllowed = true;
						}
//if(mattOnlyTEST()) {echo "{$services[count($services)-1]} [$startDate $servicetime] [".date('Y-m-d H:i')."]";exit;}
					}
//}
					
			}
		}
		if($services) usort($services, 'serviceStartTimeSort');
		$apptdays[] = $services;
	}
	
	$numAppointments = 0;
	$noVisitDays = 0;
	$scheduleDays = count($apptdays);
	foreach($apptdays as $appts) {
		if($appts) $numAppointments += count($appts);
		else $noVisitDays++;
		//$scheduleDays++;
	}
	
}		

function serviceStartTimeSort($a, $b) {
	return strcmp(getServiceStartTime($a), getServiceStartTime($b));
}
	
function getServiceStartTime($s) {
	$start = strpos($s, '#')+1;
	$end = strpos($s, '-', $start);
	return date('H:i', strtotime(substr($s, $start, $end - $start)));
}

// ***************************************************************************
$currencyMark = getCurrencyMark();


$suppressMenu = 1;
include "frame-client.html";

if($error) echo "<font color='red'>$error</font><p>";
else if($finalMessage) {
	echo "<font color='green'>$finalMessage</font><p>";
	include "frame-end.html";
	exit;
}

$stepOneLink = fauxLink('Step 1: Schedule Services', 'scheduleServices()', 1, 'Return to editing your schedule..');
//$stepThreeLink = fauxLink('Step 3: Submit Request', 'submitSchedule()', 1, 'Submit your schedule request.');
if($noTimeTravelAllowed) {
	$stepThreeLink = "<span onClick='showMessage()' style='font-weight:bold;font-size:1.2em;color:red;text-decoration:underline;'>Please fix this schedule</span>";
	$detail = 'repair required';
}
else {
	$stepThreeLink = echoButton('', 'Step 3: Submit Request', 'submitSchedule()', 'BigButton', 'BigButtonDown', 1, 'Submit your schedule request.');
	$detail = 'review schedule';
}
logChange($client, 'clientScheduler', 'm', "Step 2: $detail");

?>
<table style='width:100%'>
	<tr>
	<td style='font-weight:bold;font-size:1.3em;'><?= $stepOneLink ?></td>
	<td style='font-weight:bold;font-size:1.5em;'>Step 2: Preview Visits</td>
	<td style='font-weight:bold;font-size:1.5em;'><?= $stepThreeLink ?></td>
	<td>Total Price: 
		<span style='color:green;font-size:1.4em;font-weight:bold;'><?= "$currencyMark " ?><span id='totalChargeSpan'><?= $totalCharge ?></span></span>
		<p><span style='color:green;font-size:1.4em;font-weight:bold;'><?= $numAppointments ?></span> visits
	</td>
	</tr>
</table>
<form name='clientschedmakerform' method='POST'>
<? foreach($_POST as $key => $val) hiddenElement($key, $val); 
echo "Note: <br>".str_replace("\n", "<br>", $_POST['note']);
?>
</form>

<?
include "frame-end.html";
$step1Link = "<p><a href='javascript:scheduleServices();'>Click Here to Return to Step 1</a><p>or<p>";
$closeLink = "<p><a href='javascript:$.fn.colorbox.close();'>Click Here to Continue</a>";
$reminder = 
  $noTimeTravelAllowed 
		? "<h2 style='color:red;'>Whoa!</h2><div style='font-size:1.5em;'><img src='art/lightning-smile-small.jpg' style='float:right;'>At least one of these visits starts in the past!<p>Please fix this schedule by returning to Step 1.$step1Link$closeLink</div>"
		: "<h2>Remember!</h2><div style='font-size:1.5em'><img src='art/lightning-smile-small.jpg' style='float:right;'>You must click the big <p><div style='font-size:1.2em;font-weight:bold;display:inline;border:solid black 1px;padding: 4px;'>Step 3: Submit Request</div><p> button next to the Total Price to send this Schedule Request to us!$closeLink</div>";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

var reminder = "<?= $reminder ?>";

function showMessage() {
	$(function() {$.fn.colorbox({html:reminder, width:"600", height:"400", scrolling: true, opacity: "0.3"});});
}

showMessage();

function submitSchedule() {
	submitForm('submit');
}

function scheduleServices() {
	submitForm('schedule');
}

function submitForm(action) {
	//alert('Patience is a virtue, Ted');
	<? if($numAppointments == 0) 
				echo 
					"if(action == 'submit') {alert('You have not requested any new visits.  Please return to Step 1.'); return;}\n";
	?>
	if(allDone() && MM_validateForm(
			'start', '', 'R',
			'end', '', 'R',
			'start', '', 'isDate',
			'start', 'NOT', 'isPastDate',
			'start','end','datesInOrder'
			) ) {
		document.getElementById('action').value = action;
		preSubmit();
		document.clientschedmakerform.submit();
	}
	else return;
	
}

function preSubmit() {
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
</script>
<?
$month = date('F', strtotime($start));
$rowHeight = 100;
?>
<style>
.previewcalendar { background:white;width:99%;border:solid black 2px;margin:5px; }

.previewcalendar td {border:solid black 1px;width:14.29%;}
.app {border:solid black 1px;background:#B7FFDB;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
.empty {border:solid black 1px;background:lightgrey;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

.month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

.dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
.daynumber {font-size:1.5em;font-weight:bold;text-align:right;display:block;}
</style>
<?

$globalServiceSelections = array_flip(getClientServices());
$currencyMark = getCurrencyMark();
?>
<div style='background:white;display:block;position:relative;top:-35px;'>
<table style='font-size:1.4em;width:100%;text-align:center;'>
<tr><td colspan=3>
Schedule starts on : <b><?= longDayAndDate(strtotime($start)) ?></b> and ends on <b><?= longDayAndDate(strtotime($end))."</b> ($scheduleDays days)<p>" ?>
</td></tr>
<tr><td>Visits: <?= $numAppointments ?></td><td>Days without visits: <?= $noVisitDays ?></td><td>Price: <?= $currencyMark.number_format($totalCharge, 2) ?><td></tr>
</table>


<table class='previewcalendar'>
<?
$start = date('Y-m-d',strtotime($start));
$end = date('Y-m-d',strtotime($end));
$month = '';
$dayN = 0;
for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
	$dow = date('w', strtotime($day));
	if($month != date('F', strtotime($day))) {
		if($dow && $month) {  // finish prior month, if any
			for($i=$dow; $i < 7; $i++) echo "<td>&nbsp;</td>";
			echo "</tr>";
		}
		$month = date('F', strtotime($day));
		echoMonthBar($month);
		echo "<tr>";
		for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
	}
	if(!$dow) echo "</tr><tr>";
	echoDayBox($day, $apptdays[$dayN]);
	$dayN++;
}
if($dow && $month) {  // finish prior month, if any
	for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
	echo "</tr>";
}
?>
</table>
</div>
<?
function echoMonthBar($month) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	echo "<tr><td class='month' colspan=7>$month</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBox($day, $appts) {
	global $globalServiceSelections;
	$dom = date('j', strtotime($day));
	$class = $appts && $appts[0] ? 'app' : 'empty';
	echo "<td class='$class'><div class='daynumber'>$dom</div>";
	if($class == 'empty') echo "<span style='color:red'>No visits.</span>";
	else echo "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	if($appts) foreach($appts as $timeAndService) {
		if(!$timeAndService) continue;
		$pair = explode('#', $timeAndService);
		echo "<hr>{$pair[1]}<br>{$globalServiceSelections[$pair[0]]}\n";
	}
	echo "</td>";
}