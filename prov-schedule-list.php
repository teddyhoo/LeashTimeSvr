<?
// prov-schedule-list.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "time-framer-mouse.php";

// Determine access privs
$locked = locked('o-');

$max_rows = 100;

extract($_REQUEST);

if($thisweek && !$starting && !$ending) {
	$starting = shortDate();
	$ending = shortDate(strtotime("+ 6 days"));
}

$inactiveProvider = $provider && $provider != -1 && fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $provider LIMIT 1") == 0;
if(mattOnlyTEST()) echo "provider: [$provider] inactiveProvider: [$inactiveProvider]<p>"; 
$activeProviderSelections = 
		array_merge(array('--Select a Sitter--' => '', '--Unassigned--' => -1), 
					($inactiveProvider ? getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=true) : getActiveProviderSelections()));

$appts = array();
if($provider == -1) $providerName = 'Unassigned';
else if($provider)
	$providerName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provider");
if($provider) {
	$found = getProviderAppointmentCountAndQuery(dbDate($starting), dbDate($ending), $sort, $provider, $offset, $max_rows);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "$found";exit;}	
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

	foreach($appts as $key => $appt) {
		if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
			if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
				$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];
		}

	$surcharges = array();
	if($_SESSION['surchargesenabled'])
			$surcharges = fetchAssociations(
			"SELECT * 
				FROM tblsurcharge 
				WHERE date >= '$firstDateShown' AND 
							date <= '$lastDateShown' AND 
							providerptr = ".($provider == -1 ? '0' : $provider));
	$rows = array_merge($appts, $surcharges);
	
	$allTimeOff = getProviderTimeOffInRange($provider, array(dbDate($starting), dbDate($ending)));
	foreach($allTimeOff as $to) {
		$to['starttime'] = date('H:i:s', strtotime(substr($to['timeofday'], 0, strpos($to['timeofday'], '-'))));
		$rows[] = $to;
	}
	
	
	if($rows) {
		//foreach($rows as $row) $clients[] = $row['clientptr'];
		//$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', lname, fname) FROM tblclient WHERE clientid IN (".join(',', $clients).")");
		//usort($rows, 'dateSort');
		sortAgain($rows);
	}

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

$breadcrumbs = '';
if(adequateRights('#pl')) $breadcrumbs .= "<a href='provider-list.php'>Sitters</a>";
if($provider && adequateRights('#as')) $breadcrumbs .= " - <a href='provider-edit.php?id=$provider'>$providerName</a>";


function sortAgain(&$rows) {
	global $sort;
	$parts = explode('_', ($sort ? $sort : 'date_ASC'));
	if($parts[0] == 'date') usort($rows, 'dateTimesInOrder');
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

$pageTitle = "Sitter Schedule";

//$props = getUserPreferences($_SESSION['auth_user_id']);
foreach(explode(',', 'provsched_client,provsched_start,provsched_hidephone,provsched_hideaddress') as $property)
	$props[$property] = 
		getUserPreference($_SESSION['auth_user_id'], $property, $decrypted=false, $skipDefault=false);
$wagPrimaryNameMode = $props['provsched_client'];
if($props['provsched_start'] == 'starttime') $timeColumn = '||starttime|Start';
else $timeColumn = '||time|Time';
$phoneColumn = !$props['provsched_hidephone'] ? '||phone|Phone' : '';
$addressColumn = !$props['provsched_hideaddress'] ? '||address|Address' : '';
$maxServiceNameLength = 12;
$columnDataLine = "client|Client$phoneColumn$addressColumn||service|Service||date|Date$timeColumn||charge| ||buttons| ";

if($printable) {
	$printTitle = $provider == -1 ? "Unassigned" 
								: (!$provider ? "All" 
											: fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provider LIMIT 1", 1))
												."&apos;s";
	$printTitle .= " Schedule"
		.($starting && !$ending ? " starting " : "")
		.(!$starting && $ending ? " up to " : "")
		.($starting && $ending ? " from " : "")
		.($starting  ? shortestDate(strtotime($starting)) : "")
		.($starting && $ending ? " to " : "")
		.($ending  ? shortestDate(strtotime($ending)) : "");
	echo <<<PRINTABLE
<head>	
  <link rel="stylesheet" href="style.css" type="text/css" /> 
  <link rel="stylesheet" href="pet.css" type="text/css" />
</head>
<body>
<h2>$printTitle</h2>
PRINTABLE;

//versaProviderScheduleTable($providerid, $rows, $suppressColumns=null, $noSort=false, $updateList=null, $noLinks=false, $forceDateRow=false, $providerView=false, $columnDataLine=null, $emailVersion=false, $printable=false)

	versaProviderScheduleTable($provider, $rows, array('date', 'buttons'), 'noSort', $updateList, 'noLinks', !'forceDateRow', 'providerView', $columnDataLine, !'emailVersion', $printable=true);

}
else {


include "frame.html";
// ***************************************************************************
makeTimeFramer('timeFramer', 'narrow');

?>
<form name='provschedform'>
<p style='font-weight:bold;font-size:1.2em;'>
<?   ?>
</p>
<table width=100%><tr>
<td width=15% style='width:15%;font-weight:bold;font-size:1.2em;'>List View</b></td><td width=15%>
<? $url = str_replace('list','cal',$_SERVER['REQUEST_URI']);
   echoButton('', 'Calendar View', "document.location.href=\"$url\""); ?>
</td>
<td>&nbsp;</td><td width=15%>
<? echoButton('', 'Reassign Visits', 'reassignJobs()'); ?>
</td>
<td width=30%>
<? 
	echoButton('', 'Print Visit Sheets', 'printVisitSheets()'); 

	labeledCheckbox('summary only', 'summaryOnly', isset($summaryOnly), null, null, null, 'boxFirst');
?>
</td>
<td width=15%>
<? echoButton('', 'Set Up Route', "setUpRoute($prov)"); 

?>
</td>
</tr>
</table>
<p>
<? 
selectElement('Sitter:', "provider", $provider, $activeProviderSelections);
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('', 'Show', 'checkAndSubmit()');
echo " <img src='art/tiny-email-message.gif' title='Send schedule to sitter.' style='height:15px;width:25px;padding-left:10px;cursor:pointer;' onclick='sendProviderSchedule()'>";
require_once "google-cal-fns.php";

if(providerAcceptsGoogleCalendarEvents($provider ? $provider : -1)) {
	echo " <img src='art/spacer.gif.gif' width=10>";
	$whose = $provider == -1 ? 'your' : "sitter's";
	fauxLink('Update Google Calendar', 'sendToGoogleCalendar()', 0, "Send displayed visits to $whose Google Calendar", 'googlecalbutton');
	echo ' ';
	fauxLink('*', 'showRecentGoogleCalActivity()', 0, "Show recent Google Calendar activity");
}
  ?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td>";

echo "<td><table style='border-collapse: separate;'><tr>
              <td class='pagingButton'>$firstPageButton</td>
              <td class='pagingButton'>$prevButton</td>
              <td class='pagingButton'>$nextButton</td>
              <td class='pagingButton'>$lastPageButton</td>
              <td class='pagingButton'>".fauxLink('Printer Friendly', 'checkAndSubmit(1)', 1, 'Show printer-friendly calendar in a window.')."</td>            
             </tr></table></td>
        <td>";

echo "</tr></table>";
?>

<p>
<?

	versaProviderScheduleTable($provider, $rows, array('date'), 'noSort', $updateList, 0, 0, 'forceDateRow', $columnDataLine);
//function versaProviderScheduleTable($rows, $suppressColumns=null, $noSort=false, $updateList=null, $noLinks=false, $forceDateRow=false, $providerView=false, $columnDataLine=null) 

//if($_SESSION['staffuser']) 
//else providerScheduleTable($rows);


if($dataRowsDisplayed < 5) { ?>
<div style='height:100px;'></div>
<?
}
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('provider','Provider','starting','Starting Date','ending', 'Ending Date');	

function checkAndSubmit(popup) {
  if(MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var provider = document.provschedform.provider.value;
		var starting = document.provschedform.starting.value;
		var ending = document.provschedform.ending.value;
		var summaryOnly = document.getElementById('summaryOnly').checked;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
		summaryOnly = summaryOnly ? '&summaryOnly=1' : '';
		var url = 'prov-schedule-list.php?provider='+provider+starting+ending+summaryOnly;
		if(popup)	openConsoleWindow('calendar', url+'&printable=1',750,700);
    else document.location.href=url;
	}
}

function printVisitSheets() {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.starting.value;
	var ending = document.provschedform.ending.value;
	var provider = document.provschedform.provider.value;
	var message;
	if(!starting) message = "No starting date has been supplied.\nPrint today's Visit Sheets?";
	else if(ending != starting) message = "Print Visit Sheets for "+starting+"?";
	if(message && !confirm(message)) return;
	var url = 'visit-sheets.php';
	if(document.getElementById('summaryOnly').checked) url += '?summaryOnly=1&';
	else url += '?';
	openConsoleWindow('visitsheets', url+'provider='+provider+'&date='+starting,750,700);
}

function setUpRoute() {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.starting.value;
	var ending = document.provschedform.ending.value;
	var provider = document.provschedform.provider.value;
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

function reassignJobs() {
	var provider = document.provschedform.provider.value;
	var starting = document.provschedform.starting.value;
	document.location.href='job-reassignment.php?fromprov='+provider+'&date='+starting;
}

function cancelAppt(appt, cancelFlg, surcharge) {
	<? if($_SESSION['preferences']['confirmVisitCancellationInLists']) 
		echo "var action = cancelFlg ? 'Cancel' : 'Un-cancel';\n
					if(!confirm(action+' this '+(surcharge ? 'surcharge?' : 'visit?'))) {alert('Ok then.'); return;}";
	?>
	var url = surcharge ? 'surcharge-cancel.php' : 'appointment-cancel.php';
	ajaxGetAndCallWith(url+"?cancel="+cancelFlg+"&id="+appt, update, 0);
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
	ajaxGetAndCallWith('appointment-quickedit.php?save=1&id='+appt+'&p='+p+'&t='+t+'&s='+s, update, 'appointments');  // must update all appointments since provider may have changed
	document.getElementById('editor_'+appt).parentNode.style.display = 'none';
}

<?
dumpPopCalendarJS();
dumpTimeFramerJS('timeFramer');

?>

function showRecentGoogleCalActivity() {
	var provider = document.getElementById('provider');
	provider = provider.options[provider.selectedIndex].value;
	if(provider < 1) {
		alert("Please select a sitter first.");
		return;
	}
	$.fn.colorbox({href:"google-cal-recent-activity.php?prov="+provider, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}

function sendToGoogleCalendar()
 {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'R',
		  'ending', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) return;
	
	// provider, starting, ending
	var provider = document.getElementById('provider');
	provider = provider.options[provider.selectedIndex].value;
	var starting = escape(document.getElementById('starting').value);
	var ending = escape(document.getElementById('ending').value);
	var revert = document.getElementById('googlecalbutton').innerHTML;
	document.getElementById('googlecalbutton').innerHTML = 'Please wait...';
	document.getElementById('googlecalbutton').onclick = null;
	ajaxGetAndCallWith('google-push-visits-ajax.php?prov='+provider
												+'&start='+starting+'&end='+ending, 
											function(revert, text) {
												document.getElementById('googlecalbutton').innerHTML = revert;
												document.getElementById('googlecalbutton').onclick = sendToGoogleCalendar;
												alert(text);} , revert);
}


function sendProviderSchedule() {
  if(!MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'R',
		  'ending', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) return;
	
	// provider, starting, ending
	var provider = document.getElementById('provider');
	provider = provider.options[provider.selectedIndex].value;
	var starting = document.getElementById('starting').value;
	var ending = document.getElementById('ending').value;
	openConsoleWindow('emailcomposer', 'prov-schedule-email.php?prov='+provider+'&starting='+escape(starting)+'&ending='+escape(ending),750,700);
}

function update(target, val) { // called by appointment-edit
	if(target != 'messages') {
		if(val != null && val.indexOf('MISASSIGNED') != -1) alert('Because of scheduled time off or exclusive service conflicts, this visit has been marked UNASSIGNED.');
		else if(val != null && val.indexOf('EXCLUSIVECONFLICT') != -1) alert('Because of an already scheduled exclusive visit, this visit has been marked UNASSIGNED.');
		else if(val != null && val.indexOf('INACTIVESITTER') != -1) alert('Because the sitter is now inactive, this visit has been marked UNASSIGNED.');
		refresh(); // implemented below
	}
}
</script>
<p>
<img src='art/spacer.gif' width=1 height=160>
<?
include "js-refresh.php";

// ***************************************************************************

include "frame-end.html";
}

if($printable) {
}

function dateSort($a, $b) {
	global $clients;
	$result = strcmp($a['starttime'], $b['starttime']);
	if(!$result) {
		$result = strcmp($clients[$a['clientptr']], $clients[$b['clientptr']]);
	}
	if(!$result) {
		$a = isset($a['appointmentid']) ? '1' : 2;
		$b = isset($b['appointmentid']) ? '1' : 2;
		$result = strcmp($a, $b);
	}
	return $result;
}
?>
