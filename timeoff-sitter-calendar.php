<? // timeoff-sitter-calendar.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "appointment-fns.php"; // for function briefTimeOfDay($appt) 


$auxiliaryWindow = true; // prevent login from appearing here if session times out
extract(extractVars('provid,date,editable,open,month', $_REQUEST));
if(userRole() == 'p') {
	$locked = locked('p-');
	$provid = $_SESSION["providerid"];
	$editable = $_SESSION['preferences']['offerTimeOffProviderUI'];
}
else if(userRole() == 'd') {
	if(!adequateRights('#es')) {
		echo "Sorry, insufficient access rights.";
		exit;
	}
}
else {
	$locked = locked('o-');
	$editable = true;
	$pastEditingAllowed = true;
}	
$blackoutsEnabled = TRUE; fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableTimeoffBlackouts'", 1); //dbTEST('dogslife');
$options = array('-- All Sitters --'=>0);
if($blackoutsEnabled) $options['BLACKOUT'] = getTimeOffBlackoutId(); // provider-fns.php
foreach(fetchKeyValuePairs(
	"SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as n, providerid
	FROM tblprovider
	WHERE active = 1
	ORDER BY n") as $label => $id) $options[$label] = $id;
$provs = fetchKeyValuePairs(
	"SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname))
	FROM tblprovider
	WHERE active = 1");
$provs[getTimeOffBlackoutId()] = "<span style='color:white;background:black'>BLACKOUT</span>";
	
//selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false)	
function allOvernightsThisDay($date, $provid) {
	global $overnightsFound;
	$moonChar = '&#x01F319';
	$sql =
		"SELECT IF(providerptr = 0, 'UNASSIGNED',
								IFNULL(p.nickname, CONCAT_WS(' ', p.fname, p.lname))) as sitter, 
			CONCAT_WS(' ', c.fname, c.lname) as client, timeofday
			FROM tblappointment
			LEFT JOIN tblprovider p ON providerid = providerptr
			LEFT JOIN tblclient c ON clientid = clientptr
			WHERE canceled IS NULL AND date = '$date' AND endtime < starttime";
	if($provid) $sql .= " AND providerptr = $provid";
	$overnights = fetchAssociations($sql, 1);
//print_r($overnights);	
	if($overnights) {
		$overnightsFound = TRUE;
		foreach($overnights as $visit) {
			$sitterName = $provid ? '' : "{$visit['sitter']}<br>";
			$content[] = "$moonChar $sitterName"."({$visit['client']})<br>".briefTimeOfDay($visit);
		}
		$content = "<div style='background:lavender;color:purple;'>".join('<br>', $content)."</div>";
	}
	return $content;
}

function allTimesOffThisDay($date, $provid, $editableTimeOff=false) {
	global $options, $blackoutsEnabled;
	$timeOffBlackoutId = getTimeOffBlackoutId();
	if($provid) {
		$sittersSeeAll = userRole() == 'p' && $_SESSION['preferences']['enableTimeoffCalendarGlobalVisibility'];
}// userRole() == 'p' && 		
}// userRole() == 'p' &&
		$content .= $blackoutsEnabled ? timesOffThisDayForOneProvider($date, $timeOffBlackoutId, $editableTimeOff) : '';
		
		
		if($provid == $timeOffBlackoutId) return $content;
		$content .= timesOffThisDayForOneProvider($date, $provid, $editableTimeOff, 'omit');		
		if($sittersSeeAll) {
			$content = "<b>$content</b>";
			$slackers = providersOffThisDay($date);
			foreach($options as $nm => $p) {
				if($p != $provid && $p != $timeOffBlackoutId && in_array($p, $slackers))
					$content .= 
						"<span style='color:gray'>"
						.timesOffThisDayForOneProvider($date, $p, $editableTimeOff)
						."</span>";
			}
		}
	}
	else {
		$slackers = providersOffThisDay($date);
		
		if(in_array($timeOffBlackoutId, $slackers)) {
			$content .= $blackoutsEnabled ? timesOffThisDayForOneProvider($date, $timeOffBlackoutId, $editableTimeOff) : '';
			unset($slackers[array_search($timeOffBlackoutId, $slackers)]);
		}
		foreach($options as $nm => $p) {
			if($p != $timeOffBlackoutId && in_array($p, $slackers))
				$content .= timesOffThisDayForOneProvider($date, $p, $editableTimeOff);
		}
	}
	return $content;
}

function timesOffThisDayForOneProvider($date, $provid, $editableTimeOff=false, $omitName=false) {
	global $provs, $notesFound;
	if(!$times = timesOffThisDay($provid, $date, 'complete')) return;

	$content .= $omitName ? '' : $provs[$provid].'- ';
	foreach($times as $i => $t) {
		$label = $t['timeofday'] ? briefTimeOfDay($t) : 'All day';
		if($provid == getTimeOffBlackoutId()) $label = "<span class='reverso'>$label</span>";
		$editTip = "Edit this time off.";
		if($t['note']) {
			$label .= '*';
			$editTip .= " * There is a note attached to this time off.";
			$notesFound = true;
		}
		if(userRole() != 'p' || $provid == $_SESSION["providerid"])
			$times[$i] = fauxLink($label, "editTimeOff({$t['timeoffid']})", true, $editTip);
		else $times[$i] = $label;
	}
	if($times) $content .= join(', ', $times);
	$content .= '<br>';
	//if($editableTimeOff) 
	//	$content = fauxLink($content, "id=$provid&tab=timeoff\"", true, "Edit time off for this sitter.");
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
.apptable td {border:solid black 0px;}
.empty {border:solid black 1px;background:white;width:14.29%;vertical-align:top;height:<?= $rowHeight ?>px;}

.month {border:solid black 1px;background:#E0FFFF;font-size:1.4em;font-weight:bold;text-align:center;height:40px;}

.dow {border:solid black 1px;background:white;font-size:1.2em;text-align:center;height:30px;}
.daytop {padding:0px;margin:0px;width:100%}
.daynumber {display:inline;font-size:1.5em;font-weight:bold;text-align:right;width:50px;}
.addtimeoffplus {clear:right;float:right;padding-right:5px;}
.apptcontrols {cursor:pointer;float:left;margin-right:3px;height:10px;width:10px; border:solid darkgray 1px;}
.monthlink {font-size:0.75em;padding-left:20px;padding-right:20px;display:inline;}
</style>
LOOKS;
}

function echoMonthBar($month, $day, $provid, $editableTimeOff=false) {
	$days = explode(',', 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
	$baseLink = "timeoff-sitter-calendar.php?provid=$provid&editable=$editableTimeOff&date=";
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
	$provid = $provid ? $provid : '0';
	if($pastEditingAllowed ||  $editable && $day >= date('Y-m-d'))
		$addLink = fauxLink('<img src="art/ez-add.gif">', "editTimeOff(null, $provid, \"$day\")", true, "Add new time off.");
	$content = allTimesOffThisDay($day, $provid, $editableTimeOff);
	$showOvernights = $_SESSION['preferences']['overnightsontimeoffcalendar']; //staffOnlyTEST() || dbTEST('tonkapetsitters'); // 'overnightsontimeoffcalendar'
	if($showOvernights)	$content .= allOvernightsThisDay($day, $provid);
	//$class = $content ? 'appday' : 'empty';
	$class = 'appday';
	$month = $_REQUEST['month']; // ugh.
	// ERASES RIGHT BORDER -->if($month && (date('Y-m-d', strtotime($month)) == $day) && mattOnlyTEST()) $highlight .= "background:#F7FFA1;";
	if(in_array($day, $unassignedDays)) $rbutton = reassignmentButton($day);
	echo "<td class='$class' style='position:relative;$highlight' id='box_$day' valign='top'>
		<div class='daytop'>
			<div class='daynumber'>$dom $rbutton</div>
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

function reassignmentButton($date) {
	if(strcmp(date('Y-m-d'), $date) > 0) return;
	$onclick = "goDad(\"job-reassignment.php?fromprov=-1&date=$date\")";
	$title = 'There are unassigned visits on this day.  Review them with the Reassign Jobs page.';
	return "<img style='cursor:pointer;' src='art/button-reassignment.gif' onclick='$onclick' title='$title'>";
}

$date = $date ? $date : ($month ? $month : date('Y-m-d'));
$start = date('Y-m', strtotime($date)).'-01';
$end = date('Y-m-t', strtotime($date));

$today = date('Y-m-d'); 
$unassignedDays = 
	userRole() != 'p' 
				? array_unique(fetchCol0("SELECT date FROM tblappointment WHERE providerptr = 0 AND canceled IS NULL AND date >= '$today' AND date >= '$start' AND date <= '$end'"))
				: array();


$windowTitle = "Sitter Time Off".($provid ? " for {$provs[$provid]}" : '');
$extraBodyStyle = ''; //'background:white;';
$extraHeadContent = <<<HEADSTUFF
  <link rel="icon" href="/art/favicon16.ico" type="image/x-icon" />
  <link rel="shortcut icon" href="/art/favicon16.ico" type="image/x-icon" />
  <link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
	<script type="text/javascript" src="jquery.busy.js"></script> 	
	<script type="text/javascript">jQuery().busy("defaults", { img: 'art/busy.gif', offset : 0, hide : false });</script> 	
	<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
	<style>.reverso {background: black; color:white;}</style>

HEADSTUFF;


require "frame-bannerless.php";

// ***************************************************************************
if($error) {
	echo "<font color='red'></font>";
	exit;
}
echo "<span class='h2'>$windowTitle</span>";
echoButton('', 'Close', 'window.close()', 'closeButton', 'closeButtonDown');
echo "<p>&nbsp;</p>";


if(userRole() != 'p') {
	selectElement('Sitter', 'provid', $provid, $options, $onChange="pickSitter(this)");
	if(TRUE) // timeoffreportenabled enabled for all 11/19/2020
		echo "<div style='float:right;cursor:pointer;text-align:center;margin-bottom:3px;' onclick='goDad(\"reports-sitter-time-off.php\")' title='View the Time Off Report'><img src='art/spreadsheet-32x32.png'><br>Time Off Report</div>";
	}

if($provid) {
	$days = array();
	$months = array();
	foreach(getProviderTimeOff($provid) as $timeOff) $days = array_merge($days, daysOffInInterval($timeOff));
	foreach($days as $day) $months[] = date('Y-m', strtotime($day)).'-01';
	sort($months);
	$baseLink = "timeoff-sitter-calendar.php?provid=$provid&editable=$editable&date=";
	foreach(array_unique($months) as $month) {
		$shortmonth = date('M Y', strtotime($month));
		echo " - <a href='$baseLink$month'>$shortmonth</a>";
	}
}

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
if($notesFound) echo "* = Note has been supplied.<br>";
if($unassignedDays) echo "<img valign=bottom style='cursor:pointer;' src='art/button-reassignment.gif'> = Unassigned visits.  Cick to review with the Job Reassignment page.";
$moonChar = '&#x01F319';
if($overnightsFound) echo "<br><span style='background:lavender;color:purple;'>$moonChar</span> = Overnight visit.";
/*$sURL = $_SERVER['REQUEST_URI'];
// strip open tag from sURL
$base = substr($sURL, 0, ($qm = strpos($sURL, '?')));
foreach(explode('&', substr($sURL, $qm)) as $pair)
	if(strpos($pair, 'open=') === FALSE)
		$args[] = $pair;
$sURL = $base.join('&', $args);
// include "js-refresh.php"; -- use refresh below instead
*/
?>
<script language='javascript'>
function refresh()
{
	<? if(userRole() == 'p') { ?>
		if(window.opener && window.opener.location.href.indexOf('prov-time-off-page.php'))
			window.opener.location.href = 'prov-time-off-page.php'; 
	<? } ?>
	var sel = $('#provid').get(0);
	if(sel) sitter = sel.options[sel.selectedIndex].value;
	else sitter = <?= $provid ? $provid : '0' ?>;
	window.location.href='timeoff-sitter-calendar.php?provid='+sitter+'&editable=<?= $editable ?>&date=<?= $date ?>';
}



function pickSitter(el) {
	var sitter = el.options[el.selectedIndex].value;
	window.location.href='timeoff-sitter-calendar.php?provid='+sitter+'&editable=<?= $editable ?>&date=<?= $date ?>';
}

function editTimeOff(id, prov, date) {
	var args = id ? '?id='+id : '?prov='+prov+'&date='+date;
	$.fn.colorbox({href: "timeoff-edit.php"+args, width:"710", height:"500", iframe:true, scrolling: "auto", opacity: "0.3"});
}

function warn(warningHTML) {
	//alert(warningHTML);
	$.fn.colorbox({html: warningHTML, width:"710", height:"500", iframe:true, scrolling: "auto", opacity: "0.3"});
}

<? if($open) {
	if(fetchRow0Col0("SELECT timeoffid FROM tbltimeoffinstance WHERE timeoffid = $open LIMIT 1"))
		echo "$(document).ready(function() {editTimeOff($open);});\n"; 
	else echo "$(document).ready(function() {
		$.fn.colorbox({html:\"<h2 style='color:black'>This time off has been deleted.</h2>\", width:'500', height:'300', iframe:false, scrolling: 'auto', opacity: '0.3'});
		});\n"; 
	}
?>
</script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function goDad(url) {
	if(window.opener) {
		window.opener.location.href=url;
		window.opener.focus();
	}
}

<? if($_SESSION['timeoffcalendarwarning']) {
		//echo "var warning = '{$_SESSION['timeoffcalendarwarning']}';\n";
		//fuck it: "\n$(document).ready(function() {warn(warning);});\n";
		$bullet = ' - ';
		$warning = str_replace('<li>', '\n'.$bullet, $_SESSION['timeoffcalendarwarning']);
		$warning = str_replace('<\h2>', '\n\n', $warning);
		$warning = str_replace('<p>', '\n\n', $warning);
		$warning = str_replace('<br>', '\n', $warning);
		$warning = strip_tags($warning);
		echo "var warning = '$warning';\n";
		echo "alert(warning);\n";
		unset($_SESSION['timeoffcalendarwarning']);
	}
?>
</script>

