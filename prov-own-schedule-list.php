<?
// prov-own-schedule-list.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('p-');

$max_rows = $_SESSION['preferences']['enableProviderTeamSchedule'] ? 500 : 100;
$maxTableRows = null;

extract($_REQUEST);

$provider = $_SESSION["providerid"];

$starting = $starting ? $starting : shortDate();
$ending = $ending ? $ending : shortDate();

require_once "preference-fns.php";
$props = fetchPreferences();

if($props['providersScheduleRetrospectionLimit']) {
	$earliestDateAllowed = strtotime("-{$props['providersScheduleRetrospectionLimit']} days", strtotime(date('Y-m-d')));
	$tooEarly = strtotime(dbDate($starting)) < $earliestDateAllowed;
}


$appts = array();
if($provider) {
	$found = getProviderAppointmentCountAndQuery(dbDate($starting), dbDate($ending), $sort, $provider, $offset, $max_rows);
	$numFound = 0+substr($found, 0, strpos($found, '|'));
	$query = substr($found, strpos($found, '|')+1);
	$appts = $numFound ? fetchAssociations($query) : array();
	
	if($appts) {
		$appt = current($appts);
		$firstDateShown = $appt['date'];
		$lastDateShown = $dateFirst;
		foreach($appts as $appt) {
			if(strcmp($firstDateShown, $appt['date']) > 0) $firstDateShown = $appt['date'];
			if(strcmp($lastDateShown, $appt['date']) < 0) $lastDateShown = $appt['date'];
			if($appt['canceled']) $canceledCount++;
		}
	}

	$originalServiceProviders = originalServiceProviders($appts);

	foreach($appts as $key => $appt)
		if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
			if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
				$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];

	$displayedDateRange =  array(dbDate($starting), dbDate($ending));
	$timeOffRows = getProviderTimeOffInRange($provider, $displayedDateRange);
	foreach((array)$timeOffRows as $timeOff) {
		$starttime = $timeOff['timeofday'] 
			? date('H:i:s', strtotime(substr($timeOff['timeofday'], 0, strpos($timeOff['timeofday'], '-')))) 
			: null;
		$appts[] = array('date'=>$timeOff['date'], 
											'timeofday'=>$timeOff['timeofday'], 
											'timeoff'=>'TIME OFF:', 
											'starttime'=>$starttime,
											'note'=>$timeOff['note'],
											'providerptr'=>$provider);
		//usort($appts, 'cmpStarttime');
	}
	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {
	$surcharges = array();
	if($_SESSION['surchargesenabled'])
			$surcharges = fetchAssociations(
			"SELECT * 
				FROM tblsurcharge 
				WHERE rate > 0 AND
							date >= '$firstDateShown' AND 
							date <= '$lastDateShown' AND 
							providerptr = ".($provider == -1 ? '0' : $provider));
	$appts = array_merge($appts, $surcharges);
	if($appts) sortAgain($appts);

//}
	$nextButton = false;
	$prevButton = false;
	$firstPageButton = false;
	$lastPageButton = false;
	if($numFound > $max_rows) {
		if($offset > 0) {
			$prevButton = true;
			$firstPageButton = true;
		}
		if($numFound - $offset > $max_rows) {
			$nextButton = true;
			$lastPageButton = true;
		}
	}
}
$searchResults = ($numFound ? $numFound : 'No')." appointment".($numFound == 1 ? '' : 's')." found.  ";
if($canceledCount) $searchResults .= $canceledCount.($canceledCount == 1 ? ' is' : ' are')." canceled.  ";
$dataRowsDisplayed = min($numFound - $offset, $max_rows);
if($numFound > $max_rows) $searchResults .= "$dataRowsDisplayed appointments shown. ";
if($numFound > $max_rows) {
  $baseUrl = thisURLMinusParams(null, array('offset'));
	if($prevButton) {
		$prevButton = "<a href=$baseUrl"."offset=".($offset - $max_rows).">Show Previous $max_rows</a>";
		$firstPageButton = "<a href=$baseUrl"."offset=0>Show First Page</a>";
  }
  else {
		$prevButton = "<span class='inactive'>Show Previous</span>";
		$firstPageButton = "<span class='inactive'>Show First Page</span>";
  }
	if($nextButton) {
		$nextButton = "<a href=$baseUrl"."offset=".($offset + $max_rows).">Show Next ".min($numFound - $offset, $max_rows)."</a>";
		$lastPageButton = "<a href=$baseUrl"."offset=".($numFound - $numFound % $max_rows).">Show Last Page</a>";
  }
  else {
		$nextButton = "<span class='inactive'>Show Next</span>";
		$lastPageButton = "<span class='inactive'>Show Last Page</span>";
  }
}  

function sortAgain(&$rows) {
	global $sort;
	$parts = explode('_', ($sort ? $sort : 'date_ASC'));
	if($parts[0] == 'date') usort($rows, 'dateTimesInOrder');
	else if($parts[0] == 'date')  usort($rows, 'timeWindowsInOrder');
	else if($parts[0] == 'time')  usort($rows, 'timeWindowsInOrder');
	else if($parts[0] == 'service')  usort($rows, 'servicesInOrder');
	else if($parts[0] == 'client')  usort($rows, 'clientsInOrder');
	if(strtoupper($parts[1]) == "DESC") {
		$rev = array_reverse($rows);
		foreach($rows as $i=>$v) $rows[$i] = $rev[$i];
	}
}

$clientNames = null;
function clientsInOrder($a, $b) {
	global $clientNames;
	if(!$clientNames) $clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(', ', lname, fname) FROM tblclient");
	if($clientNames[$a['clientptr']] < $clientNames[$b['clientptr']]) return -1;
	else if($clientNames[$a['clientptr']] > $clientNames[$b['clientptr']]) return 1;
	else return dateTimesInOrder($a, $b);
}
	
function servicesInOrder($a, $b) {
	if(serviceName($a) < serviceName($b)) return -1;
	else if(serviceName($a) > serviceName($b)) return 1;
	else return dateTimesInOrder($a, $b);
}
	
function dateTimesInOrder($a, $b) {
	if($a['date'] < $b['date']) return -1;
	if($a['date'] > $b['date']) return 1;
	if($a['date'] == $b['date']) {
		if($a['starttime'] < $b['starttime']) return -1;
		if($a['starttime'] > $b['starttime']) return 1;
		return 0;
	}
}

function timeWindowsInOrder($a, $b) {
	if($a['starttime'] < $b['starttime']) return -1;
	else if($a['starttime'] > $b['starttime']) return 1;
	else {
		if($a['date'] < $b['date']) return -1;
		if($a['date'] > $b['date']) return 1;
		return 0;
	}
}

$pageTitle = "Home: {$_SESSION["shortname"]}'s Schedule";

$daysAhead = 14;
if($_SESSION['secureKeyEnabled']) $clientsMissingKeys = clientKeysMissingForDaysAhead($daysAhead, $provider);
$clientDetails = getClientDetails($clientsMissingKeys);
foreach($clientDetails as $id => $client) $names[] = $client['clientname'];
if(!$print) {
include "frame.html";
// ***************************************************************************

if($clientsMissingKeys) {
	echo "<span class='sortableListCell' style='color:darkgreen;font-weight:bold;'>You will need keys to the following clients' houses
	       for visits over the next $daysAhead days:<br>&nbsp;<br>".
	      join(', ', $names)."</span><p>";
}
?>
<form name='provschedform'>
<p style='font-weight:bold;font-size:1.2em;'>
<?   ?>
</p>
<table width=100%><tr>
<td width=15% style='width:15%;font-weight:bold;font-size:1.2em;'>List View</b></td><td width=15%>
<? $url = strpos($_SERVER['REQUEST_URI'], 'list') ? str_replace('list','cal',$_SERVER['REQUEST_URI']) : 'prov-own-schedule-cal.php';
   if(!$tooEarly) echoButton('', 'Calendar View', "document.location.href=\"$url\""); ?>
</td>
<td>&nbsp;</td>
<td width=15%>
<? if(!$tooEarly) echoButton('', 'Print This List', 'printThisList()'); ?>
</td>
<td width=15%>
<? if(!$tooEarly) echoButton('', 'Print Visit Summary', 'printVisitSheets("summary")'); ?>
</td>
<td width=15%>
<? if(!$tooEarly) echoButton('', 'Print Visit Sheets', 'printVisitSheets(null)'); ?>
</td>
<td width=15%>
<? if(!$tooEarly) echoButton('', 'Set Up Route', "setUpRoute()"); ?>
</td>
</tr>
</table>
<p>
<? 
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
?>

<? echo "<br>"; echoButton('', 'Show', 'checkAndSubmit()'); ?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
             </tr></table></td>
        <td>";

echo "</tr></table>";
?>

<p>
<?
}
else { // if($print)
	echo <<<HEAD
	<head> 
  <!--
    Created by Artisteer v{Version}
    Base template (without user's data) checked by http://validator.w3.org : "This page is valid XHTML 1.0 Transitional"
  -->
  <link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
  <link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
	</head><body style='padding:20px;background:white;'>
HEAD;
echo "<h2>$pageTitle</h2>";
//echo " <a href='javascript:window.print()'>Print this page</a> ";
if($clientsMissingKeys) {
	echo "\n<span class='sortableListCell' style='color:darkgreen;font-weight:bold;'>You will need keys to the following clients' houses
	       for visits over the next $daysAhead days:<p align=center>".
	      join(', ', $names)."</p></span><p>";
}
}


$wagPrimaryNameMode = $props['provuisched_client'];
if($props['provuisched_start'] == 'starttime') $timeColumn = '||starttime|Start';
else $timeColumn = '||time|Time';
$phoneColumn = !$props['provuisched_hidephone'] ? '||phone|Phone' : '';
$addressColumn = !$props['provuisched_hideaddress'] ? '||address|Address' : '';
$rateColumn = !$props['provuisched_hidepay'] ? '||rate|Pay' : '';
$maxServiceNameLength = 12;
$columnDataLine = "client|Client$phoneColumn$addressColumn||service|Service$timeColumn$rateColumn";

if(/*mattOnlyTEST() || */$_SESSION['preferences']['enableProviderTeamSchedule']) {
	require_once "service-fns.php";
	// find all schedules this sitter is associated with today forward
	$thisStartingDate = dbDate($starting);
	$futureSchedules = fetchAssociations(
		"SELECT distinct packageptr, clientptr, recurringpackage
		FROM tblappointment 
		WHERE providerptr = $provider AND canceled IS NULL AND date >= '$thisStartingDate'", 1);
	$allPacks = array();
	foreach($futureSchedules as $package) {
		$packageptr = findCurrentPackageVersion($package['packageptr'], $package['clientptr'], $package['recurringpackage']);
		if(!in_array($packageptr, $allPacks))
			foreach(findPackageIdHistory($packageptr, $package['clientptr'], $package['recurringpackage']) as $pid)
				$allPacks[] = $pid;
	}
	$allPacks = join(',', $allPacks);
	// find all appts by sitters other than $provider in the packages and specified date range 
	$otherAppts = !$allPacks ? array() : fetchAssociations($sql = 
		"SELECT * 
			FROM tblappointment
			WHERE providerptr != $provider
				AND date >= '".dbDate($starting)
				."' AND date <= '".dbDate($ending)
				."' AND packageptr IN ($allPacks)
				ORDER BY date, starttime", 1);
	foreach($otherAppts as $oa) $appts[] = $oa;
	function dateTimeComp($a, $b) {
		$x = strcmp($a['date'], $b['date']);
		if($x) return $x;
		return strcmp($a['starttime'], $b['starttime']);
	}
	uasort($appts, 'dateTimeComp');
}

//$wagPrimaryNameMode = 'pets/name';
//$maxServiceNameLength = 12;
//$columnDataLine = 'client|Client||phone|Phone||address|Address||service|Service||starttime|Start||rate|Pay';



if($tooEarly) {
	echo "Visits from before ".shortNaturalDate($earliestDateAllowed)." are not viewable.<br>";
}
else versaProviderScheduleTable($provider, $appts, $suppressColumns=array('date'), $noSort=true, $updateList=null, $noLinks=false, $forceDateRow=false, $providerView=false, $columnDataLine);
//else providerScheduleTable($appts, $suppressColumns=array('date'), $noSort=false, $updateList=null, $noLinks=false, $forceDateRow=true);

if($print) {
	echo "<script language='javascript'>javascript:window.print();</script>";
	exit;
}

if($dataRowsDisplayed < 5) { ?>
<div style='height:100px;'></div>
<?
}
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('provider','Sitter','starting','Starting Date','ending', 'Ending Date');	

function checkAndSubmit() {
  if(MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var starting = document.provschedform.starting.value;
		var ending = document.provschedform.ending.value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
    document.location.href='prov-own-schedule-list.php?x=1'+starting+ending;
	}
}

function printThisList() {
	var url = "<?= globalURL("prov-own-schedule-list.php?&starting=".date('Y-m-d', strtotime($starting))."&ending=".date('Y-m-d', strtotime($ending))).'&print=1' ?>";
	openConsoleWindow('printlist', url,750,700);
}

function printVisitSheets(summary) {
  if(!MM_validateForm(
		  'starting', '', 'isDate')) return;
	var summaryOnly = summary == null ? '' : 1;
	var starting = document.provschedform.starting.value;
	var ending = document.provschedform.ending.value;
	var provider = <?= $provider ?>;
	var message;
	if(!starting) message = "No starting date has been supplied.\nPrint today's Visit Sheets?";
	else if(ending != starting) message = "Print Visit Sheets for "+starting+"?";
	if(message && !confirm(message)) return;
	openConsoleWindow('visitsheets', 'visit-sheets.php?provider='+provider+'&date='+starting+"&summaryOnly="+summaryOnly,750,700);
}

function printVisitSheet(client, date) {
	openConsoleWindow('visitsheets', 'visit-sheet.php?id='+client+'&date='+date,750,700);
}

function setUpRoute() {
  if(!MM_validateForm(
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.starting.value;
	var ending = document.provschedform.ending.value;
	var provider = <?= $provider ?>;
	var message;
	if(!starting) message = "No starting date has been supplied.\nSet up today's Visit Route?";
	else if(ending != starting) message = "Set up Visit Route for "+starting+"?";
	if(message && !confirm(message)) return;
	openConsoleWindow('visitsheets', 'itinerary.php?provider='+provider+'&date='+starting,750,700);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

<?
dumpPopCalendarJS();
?>

function update(target, val) { // called by appointment-edit
	refresh(); // implemented below
}

function cancelAppt(appt, cancelFlg) {
	ajaxGetAndCallWith("appointment-cancel.php?cancel="+cancelFlg+"&id="+appt, update, 0);
}


</script>
<img src='art/spacer.gif' height=200 width=1>
<?
include "js-refresh.php";

// ***************************************************************************

include "frame-end.html";
?>
