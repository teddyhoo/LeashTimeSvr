<? //homepage_owner.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "request-fns.php";
require_once "confirmation-fns.php";
require_once "time-framer-mouse.php";
require_once "preference-fns.php";

// Determine access privs
if(userRole() == 'd') $locked = locked('d-');
else $locked = locked('o-');

pageTimeOn();

extract($_REQUEST);
$shrink = isset($shrink) ? explode(',', $shrink) : array();
$expand = isset($expand) ? explode(',', $expand) : array();

$pageTitle = "<img src='labels/Home.gif'>"; //"Home";

if($mode)	$_SESSION['homePageMode'] = $mode;
if(isset($_SESSION['homePageMode'])) $homePageMode = $_SESSION['homePageMode'];
else $homePageMode = getUserPreference($_SESSION['auth_user_id'], 'homePageMode');
$homePageMode = $homePageMode ? $homePageMode : 'full';
include "frame.html";

// for draggable visits
//echo '<script src="http://code.jquery.com/ui/1.8.24/jquery-ui.min.js" type="text/javascript"></script>';
if(userRole() != 'd' || strpos($_SESSION['rights'], '#av'))
	$numIncomplete = countAllProviderIncompleteJobs();
//screenLogPageTime('Just did countAllProviderIncompleteJobs');
if($numIncomplete) {
	//$beforeToday = count(findIncompleteJobs(null, date('Y-m-d', strtotime('yesterday'))));
	//$beforeToday = $beforeToday == $numIncomplete ? 'all' : $beforeToday;
	$beforeToday = mysql_num_rows(findIncompleteJobsResultSet(null, date('Y-m-d', strtotime('yesterday'))));
//screenLogPageTime('Just did findIncompleteJobsResultSet');
	$beforeToday = $beforeToday ? " ($beforeToday before today)" : ''; 
	echo "<div style='float:right;position:relative;top:-40px;font-size:1.1em;font-weight:bold;color:red;' title='Click to see them.'>
	There are $numIncomplete incomplete visits$beforeToday. "
	.fauxLink('View Them', "hideShrinkDiv(\"custrequests\");hideShrinkDiv(\"dailyschedule\");if(document.getElementById(\"incomplete_hint\").innerHTML.indexOf(\"show\")) toggleShrinkDiv(\"incomplete\");showIncomplete()", 1, 0)."</div>";
}

makeTimeFramer('timeFramer', 'narrow');
// ***************************************************************************
$apptsToday = fetchRow0Col0(tzAdjustedSql("SELECT count(*) FROM tblappointment WHERE canceled IS NULL AND date = CURDATE()"));
//screenLogPageTime('Just did apptsToday');
$requestResolutions = fetchCol0("SELECT resolved FROM tblclientrequest");
$unresolved = count($requestResolutions) - array_sum($requestResolutions);
$statusTest = $_SESSION['preferences']['homePageInactiveSitters'] ? '1=1' : "active=1";
$provs = array_flip(getProviderShortNames("WHERE $statusTest ORDER BY name"));

echo "<table width=100%>\n";
//$showResolved = "<span style=''><input type='checkbox' id='showResolved' onChange='updateClientRequestSection(); return true;'><label for='showResolved'>Show resolved requests also</label></span>";
//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null)
startAShrinkSection("Customer Requests ($unresolved unresolved)", 'custrequests', in_array('custrequests', $shrink));
$showResolved = fauxLink('Show resolved requests also', 'updateClientRequestSection(true)', 'noEcho', '', 'showHideRequests');

if(FALSE && $newVersion) $showResolved = 
	"Show: "
	.join(' ', radioButtonSet('showallrequests', 0, array('Active Requests Only'=>0, 'All Requests'=>1),
		$onClick='updateClientRequestSection', $labelClass=null, $inputClass=null));
	//radioButtonRow('Show:', 'showallrequests', 0, array('Active Requests Only'=>0, 'All Requests'=>1), 
	//	$onClick='updateClientRequestSection', 
	//	$labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);

$showMore = fauxLink('Show more requests', 'updateClientRequestSection(true)', 'noEcho', '', 'showMoreRequests', null, 'display:none');
$requestReport = fauxLink('Open Request Report', 'document.location.href="client-request-page.php"', 'noEcho');
if(tableExists('tblconfirmation')) {
	
if(mattOnlyTEST()) {	
	$heavyCheckMark = "<span style='color:green' style='confirmed'>&#10004;: NUM</span>";
	$heavyXMark = "<span style='color:red' style='declined'>&#10008;: NUM</span>";
	$heavyTearDropAsterisk = "<span style='color:black' style='pending'>&#10045;: NUM</span>";
	$marks = explodePairsLine("received|$heavyCheckMark||declined|$heavyXMark||overdue|$heavyTearDropAsterisk");
	$confStats = currentConfirmationStats();
	$summary[] = "Client:";
	$confTitle[] = "Client:";
	if(!($confCountClient = array_sum($confStats))) {
		array_pop($summary);
		array_pop($confTitle);
	}
	else foreach($marks as $key => $mark) {
		if($confStats[$key]) {
			$summary[] = str_replace('NM', $confStats[$key], $marks[$key]);
			$confTitle[] = "$key: {$confStats[$key]}";
		}
	}
	$confStats = currentConfirmationStats('tblprovider');
	//print_r($confStats);
	$confCountProvider = array_sum($confStats);
	if($confCountProvider && $confCountClient) $summary[] = "<br>";
	$summary[] = "Sitter:";
	$confTitle[] = "Sitter:";
	if($confCountProvider+$confCountClient == 0) {
		$summary = array("none");
		$confTitle = array("No confirmations");
	}
	else if(!$confCountProvider) {
		array_pop($summary);
		array_pop($confTitle);
	}
	else foreach($marks as $key => $mark) {
		if($confStats[$key]) {
			$summary[] = str_replace('NUM', $confStats[$key], $marks[$key]);
			$confTitle[] = "$key: {$confStats[$key]}";
		}
	}
//screenLogPageTime('Just did currentConfirmationStats');
	$confStats = join(' ', $summary);
	$confStats = "Recent confirmations<br>$confStats";
	$confTitle = join(' ', $confTitle);
	$statsClass = strlen("$confStats") > 35 ? 'fontSize0_8em' : null;
	//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)
	$confirmationStats = fauxLink($confStats, "document.location.href=\"confirmations.php\"", 1, "View confirmations page. $confTitle", null, $statsClass);
}
else {
	$confStats = currentConfirmationStats();
//screenLogPageTime('Just did currentConfirmationStats');
	$confStats = !array_sum($confStats) ? 'None' : "Confirmed: {$confStats['received']} Declined: {$confStats['declined']} Overdue: {$confStats['pending']}";
	$confStats = "Recent confirmations - $confStats";
	$statsClass = strlen("$confStats") > 35 ? 'fontSize0_8em' : null;
	//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)
	$confirmationStats = fauxLink($confStats, "document.location.href=\"confirmations.php\"", 1, "View confirmations page. $confTitle", null, $statsClass);
}
}
if(tableExists('tblreminder')) {
	$reminderButton = echoButton('', 'Edit Reminders', "document.location.href=\"reminders.php\"", 'SmallButton', 'SmallButtonDown', 1, 'Edit automatic reminders');
}
if(getPreference('enableSMS')) {
	require_once "comm-fns.php";
	$lastReviewDate = getUserPreference($_SESSION['auth_user_id'], 'lastRecentSMSReviewDate');

//if(mattOnlyTEST())	$lastReviewDate =  '2018-01-01 11:06:26';

	$lastReviewDate = $lastReviewDate ? $lastReviewDate : date('Y-m-d H:i:s', strtotime("- 24 hours"));
	$recentTitle = 'Recent mobile messages';
	$recentCount = count(getSMSCommsFor(-1, $filter="datetime > '$lastReviewDate' AND inbound = 1", $clientflg=false, $totalMsg=false));
screenLogPageTime("Just did getSMSCommsFor (last review date: $lastReviewDate) [userid: {$_SESSION['auth_user_id']} count: $recentCount)");
	if($recentCount) {
		$recentButtonColor = 'background: yellow;';
		$plural = $recentCount == 1 ? '' : 's';
		$recentCount = $recentCount ? $recentCount : 'No';
		$recentTitle .= ": $recentCount response$plural since you last checked.";
	}
	$recentSMSButton = '<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">'
		."<div class='fa fa-mobile fa-2x' style='float:right;margin-left:10px;cursor:pointer;$recentButtonColor' title='$recentTitle'
		onclick='$(this).css(\"background\", \"inherit\"); $.fn.colorbox({href:\"sms-recent-view.php\", width:\"750\", height:\"500\", iframe: true, scrolling: true, opacity: \"0.3\"})'>
		</div>";
}

$sepr = "<img src='art/spacer.gif' width=10 height=1>";
echo "<table border=0 width=100%>
<tr><td>$showResolved$sepr$showMore$sepr$requestReport</td><td style='text-align:right;font-size:1.2em;font-weight:bold;'>$recentSMSButton $reminderButton $confirmationStats</td></tr></table>\n";
echo "<div id='clientrequests' style='width:100%'>";
clientRequestSection('clientrequests');
screenLogPageTime('Just did clientRequestSection');
echo "</div>";
endAShrinkSection();

if(userRole() != 'd' || strpos($_SESSION['rights'], '#av') || strpos($_SESSION['rights'], '#ev')) {
	startAShrinkSection("Daily Service Visits ($apptsToday today)", 'dailyschedule', in_array('dailyschedule', $shrink));
	?>
	<form name='provschedform'>
	<table width=100%>
	<tr><td>
	<? 
	$appointmentdate = shortDate($appointmentdate ? strtotime($appointmentdate) : getLocalTime());
	calendarSet('Date:', 'appointmentdate', $appointmentdate, null, null, true, null, 'searchForAppointments()');
	echo "<img src='art/spacer.gif' width=5 height=1>";
	if(!array_key_exists('showCanceledOnHomePage', $_SESSION)) $_SESSION['showCanceledOnHomePage'] = 1;
	if(!array_key_exists('showSurchargesOnHomePage', $_SESSION)) $_SESSION['showSurchargesOnHomePage'] = 1;

	labeledCheckbox('Show Canceled:', 'showCanceled', $_SESSION['showCanceledOnHomePage'], null, null, 'searchForAppointments()' );
	labeledCheckbox('Show Surcharges:', 'showSurcharges', $_SESSION['showSurchargesOnHomePage'], null, null, 'searchForAppointments()' );

	echo "<img src='art/spacer.gif' width=20 height=1><div style='display:inline;' id='total-revenue'></div>";
	echo "<img src='art/spacer.gif' width=20 height=1>";
	
// ICONIC VERSION	
?>
<div style='display:inline-block; border: solid verydarkgrey 1px; height:30px;float:right;'>
<img src='art/wag.gif' title='Week at a Glance' onclick="openConsoleWindow('wag', 'wag.php',750,700)" style='cursor:pointer'>
<img src='art/spacer.gif' width=10 height=1>
<img src='art/printer20.gif' title='Printed List' onclick="printedList()" style='cursor:pointer'>
<? if(userRole() != 'd' || strpos($_SESSION['rights'], '#es')) { ?>
<img src='art/spacer.gif' width=10 height=1>
<img src='art/clock20.gif' title='Open the Sitter Time Off Calendar' 
		onclick="openConsoleWindow('timeoffcalendar', 'timeoff-sitter-calendar.php?editable=1',850,700)"
		style='cursor:pointer'>
<? } ?>
<?
	if($_SESSION['preferences']['optionEnabledGoogleCalendarSitterVisits'] &&
	    (getUserPreference($_SESSION['auth_user_id'], 'pushUnassignedToGoogleCalendar')
			|| getPreference('allowSittersToUseGoogleCalendar'))) {
		echo "<img src='art/spacer.gif' width=10 height=1>";
		echo "<img id='googlecalbutton' src='art/googlecal20.gif' title=\"Send this day's visits to Google Calendar\" 
						onclick='sendToGoogleCalendar()' style='cursor:pointer'>";
	}
?>
<?
	if($_SESSION['secureKeyEnabled']) {
	?>
	<img src='art/spacer.gif' width=10 height=1>
	<img src='art/small-key.gif' height=20 onClick='showKeysNeeded()' title='Show sitters who need to get keys in the next 14 days' style='cursor:pointer;'>
	<? 
	}
?>
</div>
<?
// END ICONIC VERSION	
	?>

	</td>
	</tr>
	</table>
	<?

	$longDay = longestDayAndDate(strtotime($appointmentdate));
	echo "<p>";
	echo "\n<div id='apptdatediv' style='border: solid black 1px; text-align:center;background:lightblue;font-weight:bold;'>Visits for $longDay</div>\n";
	echo "<p>";
	//echo " <div id='revenue_prov_section_$prov' style='display:inline'></div>";
	$otherMode = $homePageMode == 'full' ? 'brief' : 'full';
	if($homePageMode == 'full') {
			fauxLink('Show the Daily Summary Instead', "toggleHomePageDisplayMode(\"$otherMode\")");
			
			echo " ";
			echoButton('', 'Options', 
				'$(document).ready(function(){$.fn.colorbox({href:"prov-schedule-list-options-lightbox.php", width:"750", height:"470", scrolling: true, iframe: true, opacity: "0.3"})})');
if(TRUE) {
			echo '';
			echoButton('', 'Confirm Sitter Logins', 
				'$(document).ready(function(){$.fn.colorbox({href:"login-check.php", width:"450", height:"470", scrolling: true, iframe: true, opacity: "0.3"})})');
			echoButton('', 'Sitter Arrivals/Completions', "document.location.href=\"reports-performance.php\"");
}
if($_SESSION['preferences']['enableSitterNotesChatterMods']) {
			echoButton('', 'Sitter Notes', "dailyNotes()");
}
			echo "<br>";
			
		$unassignedColor = "color='red'";
		$sevenDaysHence = date('Y-m-d', strtotime("+ 7 days"));
		$today = date('Y-m-d');
		$sevenDayUnassignedCount = fetchRow0Col0($sql = "SELECT COUNT(*) FROM tblappointment WHERE canceled IS NULL AND providerptr = 0 AND date >= '$today' AND date <= '$sevenDaysHence'", 1);
		if(!$sevenDayUnassignedCount) $unassignedColor = '';
		providerCalendarSection("<font $unassignedColor count='$sevenDayUnassignedCount'>Unassigned Visits</font>", '0');
		foreach($provs as $name => $prov)
			providerCalendarSection("$name's Visits", $prov);
	}
	else {
		fauxLink('Show the Full Home Page Instead', "toggleHomePageDisplayMode(\"$otherMode\")");
if(TRUE) {
			echo ' ';
			echoButton('', 'Confirm Sitter Logins', 
				'$(document).ready(function(){$.fn.colorbox({href:"login-check.php", width:"450", height:"470", scrolling: true, iframe: true, opacity: "0.3"})})');
			echoButton('', 'Sitter Arrivals/Completions', "document.location.href=\"reports-performance.php\"");
}
if($_SESSION['preferences']['enableSitterNotesChatterMods']) {
			echoButton('', 'Sitter Notes', "dailyNotes()");
}
		echo "<br>";
		echo "<div id='daily_summary_div' style='padding:0px;width:100%;'></div>\n";
	}
	echo "\n</form>\n";
	endAShrinkSection();
}

if(userRole() != 'd' || strpos($_SESSION['rights'], '#av')) {
	//$numIncomplete = countAllProviderIncompleteJobs();
	startAShrinkSection("Incomplete Visits (Total: $numIncomplete)", 'incomplete', !in_array('incomplete', $expand));
	?>
	<form name='incompleteform'>
	<? 
	calendarSet('Starting:', 'incompletestart', shortDate(strtotime("-35 days")), null, null, true, 'incompleteend');
	calendarSet('ending:', 'incompleteend', shortDate());
	echo " ";
	echoButton('', 'Show Incomplete', 'showIncomplete()');
	echo " ";
	echoButton('', 'Show All Incomplete', 'showAllIncomplete()');
	if(staffOnlyTEST()) {
		echo " ";
		echoButton('', 'Show Future Visits Also', 'showIncomplete("future")');
	}
	echo "<div id='incomplete_list'></div>";
	echo "</form>";
	endAShrinkSection();
}

echo "\n</table>\n";

//phpinfo();
echo "<div style='height:300px;'></div>";
// ***************************************************************************
	
function providerCalendarSection($provName, $prov) {
	$starting = date('Y-m-d');
	$schedProv = $prov ? $prov : -1;
	echo "<div id='providersectiondiv_$prov' style='padding:0px;'>\n";
	echo "<span style='font-weight:bold;font-size:1.3em;'><a href='prov-schedule-list.php?provider=$schedProv&starting=$starting'>$provName</a></span>:\n";
	echoButton('', 'Reassign Visits', "reassignJobs($prov)");
	$includeMap = staffOnlyTEST() || dbTEST('doggiewalkerdotcom'); 
	if($prov) {
		echo " ";
		echoButton('', 'Print Visit Sheets', "printVisitSheets($prov)");

		if($_SESSION['preferences']['mobileSitterAppEnabled']) { echo " "; echoButton('', 'Map', "mapVisits($prov)"); }


		echo " ";
		echoButton('', 'Set Up Route', "setUpRoute($prov)");
		
if($prov && staffOnlyTEST()) {
	echo " ".fauxLink('&#9776;', "apptTimeChart($prov, document.getElementById(\"appointmentdate\").value)", 1, 'View a chart (Staff Only for now)');
}
		

		
		
		echo " <div id='revenue_prov_section_$prov' style='display:inline'></div>";
		$provideractive = fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $prov");
/*		$clickableTimesOff = dbTEST('careypet,dogslife');
		if($provideractive) foreach(getUpcomingTimeOff($prov, 14) as $row) {
			$timeoffDateRange = representTimeOffRange($row, $futureOnly=true);
			if($clickableTimesOff) {
				$timeOffStartDay = timeOffStartDayAndPtr($row, $futureOnly=true);
				$onclickedit = "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?&editable=1&provid=$prov&month={$timeOffStartDay['date']}&open={$timeOffStartDay['timeoffid']}\",850,700)";
				$timeoffDateRange = "<span style='cursor:pointer;' onclick='$onclickedit'>$timeoffDateRange</span>";
				if(!$upcomingTimeOff[$timeOffStartDay['timeoffid']])
					$upcomingTimeOff[$timeOffStartDay['timeoffid']] = $timeoffDateRange;
			}
			else {
				$upcomingTimeOff[] = representTimeOffRange($row, $futureOnly=true);
			}
		}
		
		if($upcomingTimeOff) {
			if(!$clickableTimesOff) $upcomingTimeOff = array_unique($upcomingTimeOff);
			echo "<br>Upcoming time off: ".join(', ', $upcomingTimeOff);
		}
*/		
	}
	else {
		if($includeMap) echo " "; echoButton('', 'Map', "mapVisits(\"0\")"); 
		echo "<div id='revenue_prov_section_$prov' style='display:inline;'></div><img src='art/spacer.gif' width=20 height=1><br>";
		showWeeksUnassignedAppointments();
	}
	echo "\n<p>\n\n";
	echo "<div id='prov_section_$prov' style='padding-top:0px;'></div>\n<p>\n<div style='width:99%;border:solid black 1px;background-color: #ccdcff;margin-bottom: 5px;'><img src='art/spacer.gif' width=1 height=10></div>\n";
	echo "</div>\n";
}

function showWeeksUnassignedAppointments() {
	$unassignedSchedule = fetchKeyValuePairs(tzAdjustedSql(
		"SELECT date, count(*) as num
		 FROM tblappointment 
		 WHERE providerptr = 0 AND canceled IS NULL AND date BETWEEN CURDATE() AND FROM_DAYS(TO_DAYS(CURDATE())+7)
		 GROUP BY date
		 ORDER BY date"));
  if(!$unassignedSchedule)
		echo " No unassigned appointments for the next seven days.";
	else {
		echo " Upcoming unassigned appointments: ";
		$n = 0;
		foreach($unassignedSchedule as $date => $num) {
		  if($n) echo ' - ';
		  else $n++;
		  $d = strtotime($date);
		  fauxLink(shortNaturalDate($d, 'noYear').": ($num)", 'switchToDate("'.shortDate($d).'")');
		}
	}
		  
}
$devTest = $_SERVER['REMOTE_ADDR'] == '68.225.89.173' ? '1': '0';
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='incomplete-appts.js'></script>

<script language='javascript'>
var shortdateformat = '<?= getI18Property('shortdateformat', 'm/d/Y'); ?>';
var currencyMark = '<?= getCurrencyMark(); ?>';
document.getElementById('showMoreRequests').style.display = 'none';

var devTest = '<?= $devTest ?>';

<? 
dumpPopCalendarJS();
dumpTimeFramerJS('timeFramer');
?>
setPrettynames('appointmentdate', 'Date');
function sendToGoogleCalendar()
 {
	if(!confirm("Push visits to sitters' Google Calendars?")) return;
  if(!MM_validateForm(
		  'appointmentdate', '', 'R')) return;
	
	// provider, starting, ending
	var starting = escape(document.getElementById('appointmentdate').value);
	var revert = document.getElementById('googlecalbutton').value;
	var googlecalbutton = document.getElementById('googlecalbutton');
	if(googlecalbutton.type == 'button') {
		googlecalbutton.value = 'Please wait...';
		googlecalbutton.onclick = null;
	}
	else {
		googlecalbutton.src = 'art/hourglass20.gif';
		googlecalbutton.title = 'Please wait...';
		googlecalbutton.onclick = null;
	}
	ajaxGetAndCallWith('google-push-visits-ajax.php?unassigned=1&start='+starting+'&end='+starting, 
											function(revert, text) {
												var googlecalbutton = document.getElementById('googlecalbutton');
												if(googlecalbutton.type == 'button') {
													googlecalbutton.value = revert;
													googlecalbutton.onclick = sendToGoogleCalendar;
												}
												else {
													googlecalbutton.src = 'art/googlecal20.gif';
													googlecalbutton.title = 'Export visits to Google calendar again';
													googlecalbutton.onclick = sendToGoogleCalendar;
												}
												alert(text);} , revert);
}

function toggleProviderSection(prov, date) { //providersectiondiv_$prov
	var div = document.getElementById('providersectiondiv_'+prov);
	if(div.style.display == 'none') updateProviderCalendarSection(prov);
	div.style.display = div.style.display == 'none' ? 'block' : 'none';
}

function toggleAllProviderSummarySections(triggerEl) {
	var hide = triggerEl.innerHTML.indexOf('Shrink') != -1;
//var stopNow=false;
	$('.providersectiondiv').each(
			function(ind, div) {
//if(!stopNow) alert(div.id);stopNow=true;
				if(hide) div.style.display = 'none';
				else {
					var prov = div.getAttribute('prov');
					var oldDivID = div.id;
					updateProviderCalendarSection(prov);
					if((div = document.getElementById(oldDivID)).getAttribute('visits') == 0)
						div.parentNode.parentNode.style.display = 'none';
				}
			});
	triggerEl.innerHTML = hide ? 'Expand All' : 'Shrink All'
}

function toggleHomePageDisplayMode(mode) {
	document.location.href='index.php?appointmentdate=<?= shortDate(strtotime($appointmentdate)) ?>&mode='+mode;
}

function apptTimeChart(provid, apptdate) {
	$.fn.colorbox({href:'appt-time-chart.php?id='+provid+'&date='+apptdate, iframe: true, width:"770", height:"500", scrolling: true, opacity: "0.3"});

}

function switchToDate(date) {
	document.getElementById('appointmentdate').value = date;
	document.getElementById('apptdatediv').innerHTML = 'Visits for '+makePrettyDate(date);
	updateProviderCalendarSections()
}

function makePrettyDate(date) {
	var days = "Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday".split(',');
	var months = "January,February,March,April,May,June,July,August,September,October,November,December".split(',');
	var prettydate = mdy(date);
	prettydate = new Date(prettydate[2], prettydate[0]-1, prettydate[1]);
	if(shortdateformat == 'm/d/Y')
		return days[prettydate.getDay()]+", "+months[prettydate.getMonth()]+" "+prettydate.getDate()+", "+prettydate.getFullYear();
	else if(shortdateformat == 'd.m.Y')
		return days[prettydate.getDay()]+", "+prettydate.getDate()+" "+months[prettydate.getMonth()]+" "+prettydate.getFullYear();
}

function dailyNotes() {
  if(MM_validateForm('appointmentdate', '', 'isDate')) 
  	openConsoleWindow("dailynotes", "daily-notes.php?day="+escape(document.getElementById('appointmentdate').value),750,700);
}

function printedList() {
  if(MM_validateForm('appointmentdate', '', 'isDate')) 
  	openConsoleWindow("wag", "reports-daily-visits.php?date="+escape(document.getElementById('appointmentdate').value),750,700);
}

function searchForAppointments() {
  if(MM_validateForm('appointmentdate', '', 'isDate')) {
		document.getElementById('apptdatediv').innerHTML = 'Visits for '+makePrettyDate(document.getElementById('appointmentdate').value);
		updateProviderCalendarSections();
	}
}
function update(target, val) {
//alert(val);	
	if(target == 'providersections' || (target == 'appointments' && val)) {
		var sections = val.split(',');
		for(var i = 0; i < sections.length; i++) {
			if(sections[i] == 'MISASSIGNED') alert('Because of scheduled time off or a service conflict, this visit has been marked UNASSIGNED.');
			else if(sections[i] == 'EXCLUSIVECONFLICT') alert('Because of an already scheduled exclusive visit, this visit has been marked UNASSIGNED.');
			else if(sections[i] == 'INACTIVESITTER') alert('Because the sitter is now inacive, this visit has been marked UNASSIGNED.');
			else {
				if(!document.getElementById("prov_section_"+sections[i]))
					alert("prov_section_"+sections[i]+'? ['+val+']');
				updateProviderCalendarSection(sections[i]);
			}
		}
	}
	else if(target == 'appointments'|| target == 'incomplete_list') {
	  updateProviderCalendarSections();
	  updateIncompleteAppointments(lastSort);
	  updateIncompleteSectionHeader(-1);
	}
	else if(target == 'clientrequests')
	  updateClientRequestSection(false);
}

function reassignJobs(fromprov) {
	var oneDay = document.getElementById('appointmentdate').value;
	if(!oneDay) oneDay = '<?= date('Y-m-d') ?>';
	if(fromprov == 0) fromprov =-1;
	document.location.href='job-reassignment.php?fromprov='+fromprov+'&date='+oneDay;
}


function updateProviderCalendarSections() {
<?
if($homePageMode == 'brief') echo 'updateDailySummarySection();';
else {
	echo 'updateProviderCalendarSection(0);';
	foreach($provs as $prov) echo "updateProviderCalendarSection($prov);\n";
}
?>
}

function updateDailySummarySection() {
<? 
	//$test=
	//	$_SESSION['preferences']['optionEnabledEnhancedDailySummaryView'] /*|| dbTEST('themonsterminders,carolinapetcare,tonkapetsitters')*/;/*mattOnlyTEST()*/
	//$URL = $test ? 'daily-summary-sectionNEW.php'	: 'daily-summary-section.php';
	$URL = 'daily-summary-sectionNEW.php';
?>
	var url = '<?= $URL ?>?updateList=dailysummary&';
	var showCanceled = document.getElementById('showCanceled').checked ? '&showCanceled=1' : '';
	var oneDay = document.getElementById('appointmentdate').value;
	if(!oneDay) oneDay = '<?= date('Y-m-d') ?>';
	if(document.getElementById('daily_summary_div')) 
		document.getElementById('daily_summary_div').innerHTML = 'Please wait...';
	ajaxGetAndCallWith(url+'&oneDay='+oneDay+showCanceled, reportAppointmentsBriefly, 'daily_summary_div')
	//ajaxGet(url+'provider='+prov+'&oneDay='+oneDay+showCanceled, 'prov_section_'+prov)
}

function reportAppointmentsBriefly(divId, tabletext) {
	var parts = tabletext.split('|');
	var daysRevenue = parts[0];
	var totalVisits = parts[1];
	document.getElementById(divId).innerHTML = parts[2];
	var showThisSection = true;
	document.getElementById(divId).parentNode.style.display = 'inline';
	//document.getElementById('revenue_'+divId).innerHTML = daysRevenue ? " Day's revenue: $"+daysRevenue : '';
	if(document.getElementById('dailysummaryrev')) 
		document.getElementById('dailysummaryrev').value = daysRevenue;
	document.getElementById("section_title_dailyschedule").innerHTML = 
		"Daily Service Visits ("+totalVisits+" on "+document.getElementById("appointmentdate").value+")";
}

<? if(FALSE && $newVersion) { ?>
<? } else { ?>function updateClientRequestSection(toggle, offset) {
	var url = 'client-request-section.php?updateList=clientrequests&';
	var oneDay = 0; //document.getElementById('appointmentdate').value;
	if(!oneDay) oneDay = '<?= date('Y-m-d') ?>';
	var toggleEl = document.getElementById('showHideRequests');
	var displayed = toggleEl.innerHTML.indexOf('Show') == 0 ? 'unresolved' : 'all';
	if(toggle) {
		displayed = displayed == 'all' ? 'unresolved' : 'all';
		toggleEl.innerHTML = displayed == 'unresolved' ? 'Show resolved requests also' : "Don't show resolved requests";
	}
	var unresolvedOnly = displayed == 'all' ? 0 : 1;
	document.getElementById('clientrequests').innerHTML = 'Please wait...';
	//ajaxGet(url+'&oneDay='+oneDay+'&unresolvedOnly='+unresolvedOnly, 'clientrequests');
	document.getElementById('showMoreRequests').style.display = 'none';
	if(typeof offset == 'undefined') offset = '';
	ajaxGetAndCallWith(url+'&oneDay='+oneDay+'&unresolvedOnly='+unresolvedOnly+'&offset='+offset, 
		updateClientRequestSectionCallback, 'unused');
	return true;
}
<? } ?>

function updateClientRequestSectionCallback(arg, returnText) {
	document.getElementById('clientrequests').innerHTML = returnText;
	var start = returnText.indexOf('!-- OFFSET');
	if(start > -1) {
		start += '!-- OFFSET'.length;
		var end = returnText.indexOf('OFFSET --');
		var newoffset = returnText.substring(start, end);
//alert(new Array(start, end, offset));				
		document.getElementById('showMoreRequests').onclick = function() {updateClientRequestSection(false, newoffset)};
		document.getElementById('showMoreRequests').style.display = 'inline';				
	}
	else {
		document.getElementById('showMoreRequests').style.display = 'none';
	}
}


function quickEdit(id) {
	ajaxGet('appointment-quickedit.php?id='+id, 'editor_'+id);
	document.getElementById('editor_'+id).parentNode.style.display='<?= $_SESSION['tableRowDisplayMode'] ?>';
	return true;
}
	
function updateAppointmentVals(appt) {
	var p, t, s;
	p = document.getElementById('providerptr_'+appt);
	p = p.options[p.selectedIndex].value;
	t = document.getElementById('div_timeofday_'+appt).innerHTML;
	s = document.getElementById('servicecode_'+appt);
	s = s.options[s.selectedIndex].value;
	//ajaxGet('appointment-quickedit.php?save=1&id='+appt+'&p='+p+'&t='+t+'&s='+s, 'editor_'+appt);
	ajaxGetAndCallWith('appointment-quickedit.php?save=1&id='+appt+'&p='+p+'&t='+t+'&s='+s, update, 'providersections');  // must update all appointments since provider may have changed
	document.getElementById('editor_'+appt).parentNode.style.display = 'none';
}

function updateProviderCalendarSection(prov) {
<? if($homePageMode == 'brief') $xmlArg = "xml=1&"; ?>
	var url = 'prov-schedule-list-section.php?<?= $xmlArg ?>updateList=appointments&';
	var showCanceled = document.getElementById('showCanceled').checked ? '&showCanceled=1' : '';
	var showSurcharges = document.getElementById('showSurcharges').checked ? '&showSurcharges=1' : '';
	var oneDay = document.getElementById('appointmentdate').value;
	if(!oneDay) oneDay = '<?= date('Y-m-d') ?>';
	document.getElementById('prov_section_'+prov).innerHTML = 'Please wait...';
<? if(FALSE && mattOnlyTEST()) { ?>
if(prov==53) alert(url+'provider='+prov+'&oneDay='+oneDay+showCanceled+showSurcharges);
<? } ?>
	ajaxGetAndCallWith(url+'provider='+prov+'&oneDay='+oneDay+showCanceled+showSurcharges, reportAppointments, 'prov_section_'+prov)
	//ajaxGet(url+'provider='+prov+'&oneDay='+oneDay+showCanceled, 'prov_section_'+prov)

}

function reportAppointments(divId, tabletext) {
<? //if(mattOnlyTEST()) echo "if(divId=='prov_section_118') alert(divId+'\\n\\n'+tabletext);"; ?>
<? if($homePageMode == 'brief') { ?>
	var prov = divId.split('_');
	prov = prov[prov.length-1];
	var resultxml = tabletext;
	var root = getDocumentFromXML(resultxml).documentElement;
	if(root.tagName != 'resultxml') {
		alert(resultxml);
		return;
	}
	var nodes = root.getElementsByTagName('visitcounts') ;
	//alert('visitcounts_'+prov+': '+document.getElementById('visitcounts_'+prov));
	if(nodes.length == 1 && document.getElementById('visitcounts_'+prov))
		document.getElementById('visitcounts_'+prov).innerHTML = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('visits') ;
	if(nodes.length == 1 && document.getElementById(divId))
		document.getElementById(divId).innerHTML = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('provrev') ;
<? $omitRevenue = getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');
	 if(!$omitRevenue) {
?>
	if(nodes.length == 1 && document.getElementById('provrev_'+prov))
		document.getElementById('provrev_'+prov).innerHTML = nodes[0].firstChild.nodeValue;
<? }} 
	 else { ?>
	document.getElementById(divId).innerHTML = tabletext;
<? } ?>
//if(tabletext.indexOf('No visit') != -1) alert(tabletext);
<? if(0  && mattOnlyTEST()) { ?>
if(1 || divId == "prov_section_50") alert(tabletext.indexOf('No visit'));
<? } ?>
	var showThisSection = 
		tabletext.indexOf('No visit') == -1 || 
		tabletext.indexOf('SITTER TIME OFF') != -1 || 
		divId == 'prov_section_0' ||
		tabletext.indexOf('Service') != -1; // Allow surcharge-only sitters to appear
	document.getElementById(divId).parentNode.style.display = showThisSection ? 'inline' : 'none';
	
	// make rows for this provider visible in case they were invisible before
	var prov = divId.split('_');
<? //if(mattOnlyTEST()) echo "alert(prov[2]);"; ?>
	//$('.provrow_'+prov[2]).prop('style', ''); // fails in MSIE 10
	$('.provrow_'+prov[2]).css('display', '<?= $_SESSION['tableRowDisplayMode'] ?>');
<? /*if(mattOnlyTEST()) echo "alert(document.getElementById('provrow_'+prov[2]));"; */ ?>
	//if(document.getElementById('provrow_'+prov[2])) {
	//	document.getElementById('provrow_'+prov[2]).style = '';
	//}
	
	var tds = document.getElementById(divId).getElementsByTagName('TD');
	var sum = 0;
	for(var i=0;i<tds.length;i++) { // This section shows the per-sitter revenue total
		if(tds[i].className == 'revenuecell' && isUnsignedFloat(tds[i].innerHTML)) sum += parseInt(tds[i].innerHTML);
	}
	<? if(!getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay')) { ?>
		document.getElementById('revenue_'+divId).innerHTML = sum ? " Day's revenue: "+currencyMark+sum : '';
	<? } ?>
	reportTotalRevenue();
	/*$(function() {
		$( ".dragme" ).draggable({
   stop: function(event, ui) {alert(this.id);}
		});
		});*/
}

function reportTotalRevenue() {
	var tds = document.getElementsByTagName('TD');
	var sum = 0;
	var apptcount = 0;
//var TEST = <?= mattOnlyTEST() ? '1' : '0' ?>;	
	for(var i=0;i<tds.length;i++) {
		if(tds[i].className == 'revenuecell') {
			if(isUnsignedFloat(tds[i].innerHTML)) sum += parseInt(tds[i].innerHTML);
			//apptcount++;
		}
		if(tds[i].innerHTML.indexOf('quickEdit') != -1 
				&& tds[i].innerHTML.indexOf('<table') == -1 
				&& tds[i].innerHTML.indexOf('<TABLE') == -1) {
			//if(TEST) alert(tds[i].innerHTML);
			apptcount++;
		}
	}
<? //if(mattOnlyTEST()) echo "alert(sum)"; ?>	
	<? if(!getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay')) { ?>
	if(true || sum)
		document.getElementById('total-revenue').innerHTML = " Day's revenue: "+currencyMark+sum;
	<? } ?>
	document.getElementById("section_title_dailyschedule").innerHTML = 
		"Daily Service Visits ("+apptcount+" on "+document.getElementById("appointmentdate").value+")";

}

function mapVisits(provider) {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.appointmentdate.value;
	var message;
	if(!starting) message = "No starting date has been supplied.\Map today's Visits?";
	if(message && !confirm(message)) return;
	document.location.href="provider-map.php?id="+provider+"&date="+starting;
}

function printVisitSheets(provider) {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.appointmentdate.value;
	var message;
	if(!starting) message = "No starting date has been supplied.\nPrint today's Visit Sheets?";
	if(message && !confirm(message)) return;
	openConsoleWindow('visitsheets', 'visit-sheets.php?provider='+provider+'&date='+starting,750,700);
}

function setUpRoute(provider) {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.appointmentdate.value;
	var message;
	if(!starting) message = "No starting date has been supplied.\nSet up today's Visit Route?";
	if(message && !confirm(message)) return;
	openConsoleWindow('itinerary', 'itinerary.php?provider='+provider+'&date='+starting,750,700);
}

function showKeysNeeded() {
	openConsoleWindow('keysneeded', 'keys-needed.php',600,450);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function cancelAppt(appt, cancelFlg, surcharge) {
	<? if($_SESSION['preferences']['confirmVisitCancellationInLists']) 
		echo "var action = cancelFlg ? 'Cancel' : 'Un-cancel';\n
					if(!confirm(action+' this '+(surcharge ? 'surcharge?' : 'visit?'))) {alert('Ok then.'); return;}";
	?>
	var url = surcharge ? 'surcharge-cancel.php' : 'appointment-cancel.php';
	if(!surcharge && document.getElementById('can_'+appt)) {
	 //alert('hey');
	 document.getElementById('can_'+appt).src='art/anim-daisy.gif';
	}
	
	ajaxGetAndCallWith(url+"?cancel="+cancelFlg+"&id="+appt, update, 'providersections');
}

function selectAllIncomplete(onoff) {
	var inputs;
	inputs = document.getElementsByTagName('input');
	for(var i=0; i < inputs.length; i++)
		if(inputs[i].type=='checkbox' && inputs[i].id.indexOf("appt_") == 0)
			inputs[i].checked = (onoff ? 1 : 0);
}

// ************** REQUEST MARKER SCRIPT *******************
function requestMarkerClicked(elid, event) { // elid = requestid
	var readonly = <?= userIsACoordinator() ? 'false' : 'true' ?>; // defined in request-fns.php
	if(readonly) $('#r'+elid).toggle();
	else {
		var markerform = "<span style='font-size:1.2em'>This request is "+$('#r'+elid).html()+'<hr>'
		+"<form name='assignrequest' method='POST'>"
		+"<? 
			$s = str_replace("\n", "", requestOwnerPullDownMenu($request, $label='Reassign to:', $noEchoNow=true)); 
			$s = str_replace("id='owner'", "id='owner' requestid='#REQID#'", $s);
			echo $s;
			?>"
		+"</form></span>";
		markerform = markerform.replace("#REQID#", elid);
		$.fn.colorbox({html:markerform, width:"600", height:"400", scrolling: true, opacity: "0.3"});
	}
	if (event.stopPropagation) event.stopPropagation(); // W3C standard variant
	else event.cancelBubble = true; // IE variant
}

function assignRequest(el) {
	var val = el.options[el.selectedIndex].value;
	var reqid = el.getAttribute('requestid');
	if(val == 0) val = -1;
	ajaxGetAndCallWith('request-edit.php?id='+reqid+'&reassign='+reqid+'&adm='+el.options[el.selectedIndex].value, 
		assignedTo, null);
		
}

function assignedTo(argument, responseText) {
	if(responseText == 'ERROR') {
		alert('Sorry, no can do.');
		return;
	}
	if(window.update) {
		window.update('clientrequests');
	}
}


/**** THIS FUNCTIONALITY IS USED WHEN THE "COMPLETED" BUTTON IS DISPLAYED NEXT TO A SERVICE ****/
function markVisitReported(apptid, elid) {
	if(confirm('Click OK to mark visit reported to client.'))
		ajaxGetAndCallWith('mark-visit-reported.php?id='+apptid+'&elid='+elid, 
			updateVisitIndicator, elid);
	else alert('Visit not changed.');
}
	
function updateVisitIndicator(argument, responseText) {
	if(responseText == 'OK') {
		if(document.getElementById(argument))
			document.getElementById(argument).src = 'art/visit_report_sent_29_trimmed.png';
	}
	else {
		alert('Sorry, no can do: '+responseText);
		return;
	}
}
/**** END "COMPLETED" BUTTON FUNCTIONALITY ****/


function showRequestHistory() {
	// make an ajax call and dump the reults to requesthistorydiv
	var reqid = document.getElementById('owner').getAttribute('requestid');
	ajaxGet('request-edit.php?id='+reqid+'&assignmenthistory='+reqid, 'requesthistorydiv');
}
// ************** REQUEST MARKER SCRIPT *******************

<?
dumpShrinkToggleJS(); 
?>
updateProviderCalendarSections();

<?
if($showIncomplete) {
	if(strpos($showIncomplete, 'days') !== FALSE && ($daysBack = (int)substr($showIncomplete, strlen('days'))))
		// set start date and find visits
		$incomStartDate = startDate(strtotime("- $daysBack days"));
	if($incomStartDate)
		echo "\ndocument.getElementById('incompletestart').value = '$incomStartDate';
						pleaseWaitWhileIncompleteListIsBuilt();
						showIncomplete();\n";
}
?>

</script>
<?
include "frame-end.html";
?>

