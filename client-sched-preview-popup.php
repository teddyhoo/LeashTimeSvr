<? // client-sched-preview-popup.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('c-');
extract(extractVars('start,end,days,price,showlive', $_REQUEST)); // showlive (if supplied) is a client ID
$windowTitle = 'Service Schedule Preview';
$extraBodyStyle = 'background:white;';
require "frame-bannerless.php";
// ***************************************************************************
$month = date('F', strtotime($start));
$rowHeight = 100;
?>
<style>
.previewcalendar { background:white;width:100%;border:solid black 2px;margin:5px; }

.previewcalendar td {border:solid black 1px;width:14.29%;}
.app {border:solid black 1px;background:#B7FFDB;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
.empty {border:solid black 1px;background:lightgrey;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

.month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

.dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
.daynumber {font-size:1.5em;font-weight:bold;text-align:right;display:block;}
.scheduled {color:#666666;}
.canceled {color:red;text-decoration:line-through;}
.proposed {font-weight:bold;}
</style>
<?
$numAppointments = 0;
$noVisitDays = 0;
$scheduleDays = 0;
foreach(explode('|', $days) as $appts) {
	if($appts) $numAppointments += count(explode(',', $appts));
	else $noVisitDays++;
	$scheduleDays++;
}

$globalServiceSelections = array_flip(getClientServices());
$globalActualServiceSelections = getAllServiceNamesById();
$currencyMark = getCurrencyMark();

if($showlive) { ?>
<span id='legendlabel' style='font-weight:bold;color:green;' onclick="document.getElementById('legend').style.display='block';this.style.display='none';">Legend >></span>
<div id='legend' style='display:none;padding:5px;width:160px;border: solid 1px black;' onclick="document.getElementById('legendlabel').style.display='inline';this.style.display='none';">
<span  style='font-weight:bold;color:green;'><< Legend:</span><br><span class='proposed'>Requested Visit</span><br><span class='scheduled'>Existing visit</span><br><span class='canceled'>Canceled Existing visit</span>
</div>
<?
}
?>
<table style='font-size:1.4em;width:100%;text-align:center;'>
<tr><td colspan=3>
Schedule starts on : <b><?= longDayAndDate(strtotime($start)) ?></b> and ends on <b><?= longDayAndDate(strtotime($end))."</b> ($scheduleDays days)<p>" ?>
</td></tr>
<tr><td>Visits: <?= $numAppointments ?></td><td>Days without visits: <?= $noVisitDays ?></td><td>Price: <?= $currencyMark.number_format($price, 2) ?><td></tr>
</table>


<table class='previewcalendar'>
<?
$start = date('Y-m-d',strtotime($start));
$end = date('Y-m-d',strtotime($end));
$apptdays = explode('|', $days);
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
	if($showlive) echoDayBoxWithExistingVisits($day, $apptdays[$dayN]);
	else echoDayBox($day, $apptdays[$dayN]);
	$dayN++;
}
if($dow && $month) {  // finish prior month, if any
	for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
	echo "</tr>";
}
?>
</table>
<?
function echoMonthBar($month) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	echo "<tr><td class='month' colspan=7>$month</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBoxWithExistingVisits($day, $appts) {
	global $globalServiceSelections, $globalActualServiceSelections, $showlive;
	$dom = date('j', strtotime($day));
	$appts = explode(',', $appts);
	foreach(getClientApptsForDay($showlive, $day) as $appt) $appts[] = $appt;
	if($appts) usort($appts, 'apptStartTimeSort');
	$class = $appts && $appts[0] ? 'app' : 'empty';
	echo "<td class='$class'><div class='daynumber'>$dom</div>";
	if($class == 'empty') echo "<span style='color:red'>No visits.</span>";
	else echo "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	foreach($appts as $timeAndService) {
		if(!$timeAndService) continue;
		$pair = explode('#', $timeAndService);
		if(count($pair) == 2) echo "<hr><span class='proposed'>{$pair[0]}<br>{$globalServiceSelections[$pair[1]]}</span>\n";
		else echo
			"<hr><span class={$pair[2]}>{$pair[0]}<br>{$globalActualServiceSelections[$pair[1]]}<br>{$pair[3]}</span>\n";
	}
	echo "</td>";
}

function getClientApptsForDay($clientptr, $day) {
	return fetchCol0(
		"SELECT CONCAT_WS('#', timeofday, servicecode, IF(canceled, 'canceled', 'scheduled'), IF(providerptr=0, 'Unassigned', IFNULL(nickname, CONCAT_WS(' ', fname, lname))))
			FROM tblappointment
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE clientptr = $clientptr AND date = '$day'");
}

function echoDayBox($day, $appts) {
	global $globalServiceSelections;
	$dom = date('j', strtotime($day));
	$appts = explode(',', $appts);
	if($appts) usort($appts, 'apptStartTimeSort');
	$class = $appts && $appts[0] ? 'app' : 'empty';
	echo "<td class='$class'><div class='daynumber'>$dom</div>";
	if($class == 'empty') echo "<span style='color:red'>No visits.</span>";
	else echo "<span style='color:blue'>".count($appts)." visit".(count($appts) == 1 ? '' : 's').".</span>";
	foreach($appts as $timeAndService) {
		if(!$timeAndService) continue;
		$pair = explode('#', $timeAndService);
		echo "<hr>{$pair[0]}<br>{$globalServiceSelections[$pair[1]]}\n";
	}
	echo "</td>";
}

function apptStartTimeSort($a, $b) {
	return strcmp(getApptStartTime($a), getApptStartTime($b));
}
	
function getApptStartTime($s) {
	$end = strpos($s, '-');
	return date('H:i', strtotime(substr($s, 0, $end)));
}
