<? // client-sched-previewV3.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";

$locked = locked('c-');
extract(extractVars('start,end,print,action,totalCharge', $_REQUEST));
//extract(extractVars('start,end,days,totalCharge', $_REQUEST));

// THIS FILE IS INCLUDED (REQUIRED) BY  client-sched-makerV3.php
$schedulePriceFootnote = $_SESSION['preferences']['schedulePriceFootnote'];
$schedulePriceFootnoteTitle = safeValue(strip_tags($schedulePriceFootnote));

if($_POST && isset($_POST['servicecode_1_1'])) {
	
	if($action == 'schedule') {  // BACK TO STEP 1
		include "client-sched-makerV3.php";
		exit;
	}
	
$payLoadTEST = $longScheduleRequestTEST;

$onLoadFragments[] = 'showMessage();';

	
	if(false) {}
	
	else {
		$apptdays = array();
		$dayService = array();
		$day = 0 ; // was ''
		
		if($payLoadTEST && $_POST['payload']) {
			$postPayload = json_decode($_POST['payload'], $assoc=true);
		}
		else $postPayload = $_POST;
//if($payLoadTEST) print_r($postPayload);		
		foreach($postPayload as $key=>$val) {
			if(strpos($key, 'servicecode_') === 0) {
				$specifier = substr($key, strlen('servicecode_'));
				$dayService = explode('_', $specifier);
				if($day && $dayService[0] != $day) {
					if($services) usort($services, 'serviceStartTimeSort');
					$apptdays[] = $services;
					$services = array();
				}
if($payLoadTEST) {
				//echo "<br><b>$key</b> = max[$day, 1] ".max($day, 1)."<br>";
				if($dayService[0] != max($day, 1)) {
					//echo "<hr>DAY: $day DAYSERVICE: {$dayService[0]}<p>";
					for($i=$day+1;$i<$dayService[0];$i++) $apptdays[] = array();
				} // for empty days
}
				$day = $dayService[0];
//echo "$key => $specifier<br>";			
				if($postPayload["timeofday_$specifier"]) 
					$services[] = $postPayload["servicecode_$specifier"].'#'.$postPayload["timeofday_$specifier"].'#'.$postPayload["pets_$specifier"];
//if(mattOnlyTEST()) {
					if(!$noTimeTravelAllowed && $day == 1) {  // strcmp($day, date('Y-m-d')) <= 0
						$startDate = date('Y-m-d', strtotime($start));
						if($val && strcmp($startDate, date('Y-m-d')) < 0) {
							$noTimeTravelAllowed = true;
						}
						else if(strcmp($startDate, date('Y-m-d')) == 0) {
							$servicetime = getServiceStartTime($services[count($services)-1]);
							if($val && strcmp("$startDate $servicetime", date('Y-m-d H:i')) < 0) {
								$noTimeTravelAllowed = true;
								if(mattOnlyTEST()) $noTimeTravelAllowedFault = " (fault: servicecode:$key startDate=$startDate/$servicetime)";
								//print_r($_POST);
							}
						}
}
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
	if($start > strlen($s)-1) return;
	$end = strpos($s, '-', $start);
	return date('H:i', strtotime(substr($s, $start, $end - $start)));
}

// ***************************************************************************
$currencyMark = getCurrencyMark();



$suppressMenu = 1;

if($mobileclient) {
	include "mobile-frame-client.php";
	$frameEndURL = "mobile-frame-client-end.php";
}
else if($_SESSION["responsiveClient"]) {
	$extraHeadContent = "<style>body {font-size:1.2em;} .tiplooks {font-size:14pt;} 
			.dayblock {display:inline;} 
			.dateblock {margin-top:17px;}
			.timeofdaybuttondiv {height:20px !important;}
			.petspecifierbuttondiv {height:20px !important;} 
			.navButt {vertical-align:center;}</style>";
	include "frame-client-responsive.html";
	$frameEndURL = "frame-client-responsive-end.html";
}
else if(userRole() == 'c') {
	include "frame-client.html";
	$frameEndURL = "frame-end.html";
}								

if($error) echo "<font color='red'>$error</font><p>";
else if($finalMessage) {
	echo "<font color='green'>$finalMessage</font><p>";
	include $frameEndURL;
	exit;
}




logChange($client, 'clientScheduler', 'm', "Step 2: $detail");

$step1Img = 'art/sched-serv-1-off.jpg'; // 192 x 90
$step1Size = scaledSize(192, 90, .75);
$step2Img = 'art/sched-serv-2-on.jpg'; // 184 x 90
$step2Size = scaledSize(184, 90, .75);
$step3Img = 'art/sched-serv-3-off.jpg'; // 101 x 90
$step3Size = scaledSize(101, 90, .75);




$prevButton = echoButton('', 'Back', 'scheduleServices()', 'm-r-10 pull-right', null, 1, 'Return to editing your schedule.');
$quitButton = echoButton('', 'Cancel Request', 'safeQuit()', 'm-r-10 pull-right', null, 1);
$nextButton = echoButton('', 'Submit', 'submitSchedule()', 'btn btn-success pull-right', 'BigButtonDown', 1, 'Submit your schedule request.');

$prevButton = "<input type='button' onclick='scheduleServices()' value='Back' class='m-r-10 pull-right' title='Return to editing your schedule.'>";
$quitButton = "<input type='button' onclick='safeQuit()' value='Cancel Request' class='m-r-10 pull-right' title=''>";
$nextButton = "<input type='button' onclick='submitSchedule()' value='Submit' class='btn btn-success pull-right' title='Submit your schedule request.'>";


$spacer = '<img src="art/spacer.gif" width=20>';
?>
<style>
.clientschednavtable td { border-width:0px; padding:0px; }
.redborder {border: solid red 4px;}
</style>
<table>
	<!--tr>
	<td style='padding-right:0px;'><img src='<?= $step1Img ?>' <?= $step1Size ?>></td>
	<td style='padding-right:0px;'><img src='<?= $step2Img ?>' <?= $step2Size ?>></td>
	<td style='padding-right:0px;'><img src='<?= $step3Img ?>'  <?= $step3Size ?>></td>
	</tr -->
</table>

<div class='row'><?= $nextButton ?><?= $quitButton ?><?= $prevButton ?></div>

<? if($suppressMenu) {
	//echo "<p style='text-align:center;font-size:1.1em;color:red;background:yellow;'>YOUR SCHEDULE HAS NOT BEEN SUBMITTED. PLEASE REMEMBER TO CLICK THE <span style='font-weight:bold;font-size:1.2em'>Submit</span> BUTTON</p>";
} ?>
<form name='clientschedmakerform' method='POST'>
<? foreach($_POST as $key => $val) hiddenElement($key, $val); 
echo "Note: <br>".str_replace("\n", "<br>", $_POST['note']);
?>
</form>

<?
$step1Link = "<p><a href='javascript:scheduleServices();'>Click Here to Return to Step 1</a><p>or<p>";
$closeLink = "<p><b><a href=# onclick='lightBoxIFrameClose();'>Click Here to Continue</a></b>";

//$submitButtonInstruction = "big <p><div style='font-size:1.2em;font-weight:bold;display:inline;border:solid black 1px;padding: 4px;'>Submit</div><p> button next to the Total Price";
//if(true) $submitButtonInstruction = "big Submit button next to the Total Price"; //<p><img src='/art/sched_serv_click_the_submit_button.jpg'><p>

$reminder = 
  $noTimeTravelAllowed 
		? "<h2 style='color:red;'>Whoa!</h2><div style='font-size:1.5em;'><img src='art/lightning-smile-small.jpg' style='float:right;'>At least one of these visits starts in the past!$noTimeTravelAllowedFault<p>Please fix this schedule by returning to Step 1.$step1Link$closeLink</div>"
		: "<h2>Remember!</h2><div style='font-size:1.5em'><img src='art/lightning-smile-small.jpg' style='float:right;'>You must click the <br><b>SUBMIT</b> button to send<br>this Schedule Request to us!<p>$closeLink</div>";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

var reminder = "<?= $reminder ?>";

function showMessage() {
	//$(function() {$.fn.colorbox({html:reminder, width:"600", height:"400", scrolling: true, opacity: "0.3"});});
	$(function() {lightBoxHTML(reminder, 350, 400);});
}

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

function safeQuit() {
	if(confirm('Are you sure you want to quit?  No schedule request will be sent.'))
		document.location.href='client-sched-makerV3.php?action=quit';
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
<div style='background:white;display:block;'>
<table style='font-size:1.4em;width:100%;text-align:center;'>
<tr><td colspan=3>
Schedule starts : <b><?= longDayAndDate(strtotime($start)) ?></b> and ends <b><?= longDayAndDate(strtotime($end))."</b> ($scheduleDays days)<p>" ?>
</td></tr>
<tr><td>Visits: <?= $numAppointments ?></td><td>Days without visits: <?= $noVisitDays ?></td>
	<? if(!$_SESSION['preferences']['suppressClientSchedulerPriceDisplay']) { ?>
<td title='<?= $schedulePriceFootnoteTitle ?>'>Price: <?= $currencyMark.number_format($totalCharge, 2) ?><span id='schedulepricefootnote'><?= $schedulePriceFootnote ? '*' : '' ?></span><td>
<? } ?>
</tr>
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
<?
if($schedulePriceFootnote) echo "<p>* $schedulePriceFootnote";

//echoButton('', 'Submit', 'submitSchedule()', 'BigButton redborder', 'BigButtonDown redborder', $noecho=0, 'Submit your schedule request.');
?>
</div>
<?
include $frameEndURL;

function echoMonthBar($month) {
	$days = explode(',', 'Sun,Mon,Tue,Wed,Thu,Fri,Sat');
	echo "<tr><td class='month' colspan=7>$month</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function briefTODTime($tod) {
	$tod = explode(':', $tod);
	$tod = ''.$tod[0]
		.(substr($tod[1], 0, 1) == '00' ? '' : ":".substr($tod[1], 0, 2)).(strpos($tod[1], 'a') ? 'a' : 'p');
	return $tod;
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
		list($service, $tod) = explode('#', $timeAndService);
		list($start, $end) = explode('-', $tod);
		//echo "($timeAndService) $tod \[$start, $end]";
		$tod = briefTODTime($start).'-'.briefTODTime($end);
		echo "<hr>{$tod}<br>{$globalServiceSelections[$service]}\n";
	}
	echo "</td>";
}