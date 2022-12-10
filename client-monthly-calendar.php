<? // client-monthly-calendar.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "appointment-fns.php"; // for function briefTimeOfDay($appt) 

$auxiliaryWindow = true; // prevent login from appearing here if session times out
extract(extractVars('date', $_REQUEST));

if(userRole() == 'c') {
	$locked = locked('c-');
	$clientid = $_SESSION["clientid"];
}

$date = $date ? $date : ($month ? $month : date('Y-m-d'));
$start = date('Y-m', strtotime($date)).'-01';
$end = date('Y-m-t', strtotime($date));

$visits = fetchAssociations("SELECT * FROM tblappointment WHERE clientptr = $clientid AND date >= '$start' AND date <= '$end'");

if(!$_SESSION['preferences']['suppressInvoiceSitterName']) {
	$provs = fetchKeyValuePairs("SELECT providerid,  CONCAT_WS(' ', fname, lname) FROM tblprovider");
	$provs[0] = 'TBD';
}
$serviceTypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	
//selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false)	
function allVisitsThisDay($date) {
	global $visits;
	foreach((array)$visits as $i => $visit)
		if($visit['date'] == $date) {
			$content .= visitPanel($visit);
			if($visits[$i+1]['date'] == $date) $content .= '<hr>';
		}
	return $content;
}

function visitPanel($appt) {
	global $provs, $serviceTypes;
	if(!$_SESSION['preferences']['suppressTimeFrameDisplayInCLientUI'])
		$content = $appt['timeofday'].'<br>';
	$content .= $serviceTypes[$appt['servicecode']];
	if($provs[$appt['providerptr']]) $content .= "<br>{$provs[$appt['providerptr']]}";
	if($appt['canceled']) $content = "<span class='canceledtask'>$content</span>";
	return $content;
	
}


// ========== MOVE TO PROVIDER-FNS ======================

// ========== MOVE TO PROVIDER-FNS ======================
	

function dumpCalendarLooks($rowHeight, $descriptionColor) {
	global $appDayColor;
	echo <<<LOOKS
<style>
 .previewcalendar { background:white;width:100%;border:solid black 2px;margin:5px; }

 .previewcalendar td {border:solid black 1px;width:14.29%;}
 .appday {border:solid black 1px;background:$appDayColor;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}
 .today {color: red;}
 .apptable td {border:solid black 0px;}
 .empty {border:solid black 1px;background:white;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

 .month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

 .dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
 .daytop {padding:0px;margin:0px;width:100%}
 .daynumber {display:inline;font-size:1.5em;font-weight:bold;text-align:right;width:50px;}
 .apptcontrols {cursor:pointer;float:left;margin-right:3px;height:10px;width:10px; border:solid darkgray 1px;}
 .monthlink {font-size:0.75em;padding-left:20px;padding-right:20px;display:inline;}
 hr {color: lightblue;}
</style>
LOOKS;
}

function echoMonthBar($month, $day, $provid, $editableTimeOff=false) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	$baseLink = "client-monthly-calendar.php?date=";
	$prevMonthStart = date('Y-m-d', strtotime("-1 month", strtotime($day)));
	$prev = date('F Y', strtotime($prevMonthStart));
	$prev = "<div class='monthlink'><a href='$baseLink$prevMonthStart'>$prev</a></div>";
	$nextMonthStart = date('Y-m-d', strtotime("+1 month", strtotime($day)));
	$next = date('F Y', strtotime($nextMonthStart));
	$next = "<div class='monthlink'><a href='$baseLink$nextMonthStart'>$next</a></div>";
	echo "<tr><td class='month' colspan=7>$prev$month$next</td></tr>\n<tr>";
	foreach($days as $day) echo "<td class='dow'>$day</td>";
	echo "</tr>\n";
}

function echoDayBox($day, $provid, $editableTimeOff=false) {
	global $pastEditingAllowed, $editable, $unassignedDays;
	$dom = date('j', strtotime($day));
	$content = allVisitsThisDay($day);
	//$class = $content ? 'appday' : 'empty';
	$class = 'appday';
	$today = $day == date('Y-m-d') ? 'today' : '';
	echo "<td class='$class' style='position:relative' id='box_$day' valign='top'>
		<div class='daytop'>
			<div class='daynumber $today'>$dom</div>
			<div class='addtimeoffplus'>$addLink</div>
		</div>
		";
	if($class == 'empty') ;
	else {
		echo "<table class='apptable'>";
		echo "<tr><td style='text-align:left;color:black'>$content</td></tr>";
		echo "</table>";
	}
	echo "</td>";
}

$date = $date ? $date : ($month ? $month : date('Y-m-d'));
$start = date('Y-m', strtotime($date)).'-01';
$end = date('Y-m-t', strtotime($date));

$today = date('Y-m-d'); 


$windowTitle = "Service Schedule";
$extraBodyStyle = ''; //'background:white;';
$extraHeadContent = <<<HEADSTUFF
  <link rel="icon" href="/art/favicon16.ico" type="image/x-icon" />
  <link rel="shortcut icon" href="/art/favicon16.ico" type="image/x-icon" />
  <link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
	<script type="text/javascript" src="jquery.busy.js"></script> 	
	<script type="text/javascript">jQuery().busy("defaults", { img: 'art/busy.gif', offset : 0, hide : false });</script> 	
	<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>

HEADSTUFF;


require "frame-bannerless.php";

// ***************************************************************************
if($error) {
	echo "<font color='red'></font>";
	exit;
}
echo "<span class='h2'>$windowTitle</span>";
echoButton('', 'Close', 'window.close()', 'closeButton', 'closeButtonDown');
echo "<p class='h2'>&nbsp;</p>";


dumpCalendarLooks(100, 'lightblue');

echo "<table class='previewcalendar'  border=1 bordercolor=black>";

$month = '';
$dayN = 0;
// allow for appts before start...
for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime('+1 day', strtotime($day)))) {
	$dow = date('w', strtotime($day));
	if($month != date('F Y', strtotime($day))) {
		if($dow && $month) {  // finish prior month, if any
			for($i=$dow; $i < 7; $i++) echo "<td>&nbsp;</td>";
			echo "</tr>";
		}
		$month = date('F Y', strtotime($day));
		echoMonthBar($month, $day, $provid, $editable);
		echo "<tr>";
		for($i=0; $i < $dow; $i++) echo "<td>&nbsp;</td>";
	}
	if(!$dow) echo "</tr><tr>";
	echoDayBox($day, $provid, $editable);
	$dayN++;
}
if($dow && $month) {  // finish prior month, if any
	for($i=$dow+1; $i < 7; $i++) echo "<td>&nbsp;</td>";
	echo "</tr>";
}

echo "</table>";
