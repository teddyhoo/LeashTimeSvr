<?
// prov-schedule-cal.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "day-calendar-fns.php";

// Determine access privs
$locked = locked('o-');
$canEditProviders = adequateRights('#es');

$max_rows = 100;

extract($_REQUEST);

if($thisweek && !$starting && !$ending) {
	$starting = shortDate();
	$ending = shortDate(strtotime("+ 6 days"));
}

$inactiveProvider = $provider && fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $provider LIMIT 1") == 0;

$activeProviderSelections = 
		array_merge(array('--Select a Sitter--' => '', '--Unassigned--' => -1), 
					($inactiveProvider ? getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=true) : getActiveProviderSelections()));

$appts = array();
if($provider) {
	$providerName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provider");
	$found = getProviderAppointmentCountAndQuery(dbDate($starting), dbDate($ending), 'date_ASC', $provider, $offset, $max_rows);
	$numFound = 0+substr($found, 0, strpos($found, '|'));
	$query = substr($found, strpos($found, '|')+1);
	$appts = $numFound ? fetchAssociations($query) : array();
	
	
if(TRUE) { // Fill in missing visits on the first and last days being shown
		// Fill in missing visits on the first day being shown	
		if($numFound > $max_rows) {
			$firstDate = $appts[0]['date'];
			$firstDayFound = getProviderAppointmentCountAndQuery($firstDate, $firstDate, 'date_ASC', $provider, 0, $max_rows);
			$firstDayNumFound = 0+substr($firstDayFound, 0, strpos($firstDayFound, '|'));
			$query = substr($firstDayFound, strpos($firstDayFound, '|')+1);
//echo "BANG! $firstDayNumFound: $query";exit;			
			$firstDayAppts = $firstDayNumFound ? fetchAssociations($query) : array();
			foreach($appts as $appt) {
				$apptids[$appt['appointmentid']] = 1;
			}
			$addedRows = 0;
			foreach($firstDayAppts as $appt) {
				if(!$apptids[$appt['appointmentid']]) {
					$addedRows += 1;
					$appts[] = $appt;
				}
			}
		}
		function cmpApptTime($a, $b) {
			$atime = "{$a['date']} {$a['starttime']}";
			$btime = "{$b['date']} {$b['starttime']}";
			return strcmp($atime, $btime);
		}
		if($addedRows) usort($appts, 'cmpApptTime');
	
	
		// Fill in missing visits on the last day being shown	
		if($numFound > $max_rows) {
//echo "numFound: $numFound<br>max_rows: $max_rows<br>appts count: ".count($appts)."<p>";
			$lastDate = $appts[count($appts)-1]['date'];
			$lastDayFound = getProviderAppointmentCountAndQuery($lastDate, $lastDate, 'date_ASC', $provider, 0, $max_rows);
			$lastDayNumFound = 0+substr($lastDayFound, 0, strpos($lastDayFound, '|'));
			$query = substr($lastDayFound, strpos($lastDayFound, '|')+1);
//echo "BANG! $lastDayNumFound ($lastDate): $query";
//echo "<hr>".print_r($appts, 1);
			$lastDayAppts = $lastDayNumFound ? fetchAssociations($query) : array();
			foreach($appts as $appt) {
				$apptids[$appt['appointmentid']] = 1;
			}
			$addedRows = 0;
			foreach($lastDayAppts as $appt) {
				if(!$apptids[$appt['appointmentid']]) {
					$addedRows += 1;
					$appts[] = $appt;
				}
			}
		}
}
	
	
	$originalServiceProviders = originalServiceProviders($appts);

	foreach($appts as $key => $appt) {
		if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
			if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
				$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];
		if($appt['canceled']) $canceledCount++;
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
$searchResults = ($numFound ? $numFound : 'No')." visit".($numFound == 1 ? '' : 's')." found.  ";
if($canceledCount) $searchResults .= $canceledCount.($canceledCount == 1 ? ' is' : ' are')." canceled.  ";
$dataRowsDisplayed = min($numFound - $offset, $max_rows+$addedRows);
if($numFound > $max_rows) $searchResults .= "$dataRowsDisplayed visits shown. ";
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

//$breadcrumbs = "<a href='provider-list.php'>Sitters</a>";
//if($provider && $canEditProviders) $breadcrumbs .= " - <a href='provider-edit.php?id=$provider'>$providerName</a>";
$breadcrumbs = '';
if(adequateRights('#pl')) $breadcrumbs .= "<a href='provider-list.php'>Sitters</a>";
if($provider && adequateRights('#as')) $breadcrumbs .= " - <a href='provider-edit.php?id=$provider'>$providerName</a>";


$pageTitle = "Sitter Schedule";

if($printable) {
	//provider=48&starting=07/02/2013&ending=07/03/2013
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
}
else {
include "frame.html";
// ***************************************************************************

?>
<form name='provschedform'>
<p style='font-weight:bold;font-size:1.2em;'>
<?   ?>
</p>
<table width=100%><tr>
<td width=15% style='width:15%;font-weight:bold;font-size:1.2em;'>Calendar View</b></td><td width=15%>
<? $url = str_replace('cal','list',$_SERVER['REQUEST_URI']);
   echoButton('', 'List View', "document.location.href=\"$url\""); ?>
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
<? echoButton('', 'Set Up Route', "setUpRoute($prov)"); ?>
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
	fauxLink('Update Google Calendar', 'sendToGoogleCalendar()', 0, "Send displayed visits to sitter's Google Calendar", 'googlecalbutton');
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
}
if($provider) {
	$displayedDateRange =  array(dbDate($starting), dbDate($ending));
	$timeOffRows = getProviderTimeOffInRange($provider, $displayedDateRange);
	foreach((array)$timeOffRows as $timeOff) {
		$starttime = $timeOff['timeofday'] 
			? date('H:i:s', strtotime(substr($timeOff['timeofday'], 0, strpos($timeOff['timeofday'], '-')))) 
			: null;
		$appts[] = array('date'=>$timeOff['date'], 
											'timeofday'=>$timeOff['timeofday'], 
											'timeoff'=>'TIME OFF', 
											'starttime'=>$starttime,
											'note'=>$timeOff['note'],
											'providerptr'=>$provider,
											'timeoffid'=>$timeOff['timeoffid']);
		//usort($appts, 'cmpStarttime');
	}
}

function cmpStarttime($a, $b) {
	$result = strcmp($a['date'], $b['date']);
	return $result ? $result : strcmp($a['starttime'], $b['starttime']);
}

providerCalendarTable($appts);

if($printable) exit;


if($dataRowsDisplayed < 5) { ?>
<div style='height:100px;'></div>
<?
}
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('provider','Sitter','starting','Starting Date','ending', 'Ending Date');	

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
		var url = 'prov-schedule-cal.php?provider='+provider+starting+ending+summaryOnly;
		if(popup)	openConsoleWindow('calendar', url+'&printable=1',750,700);
    else document.location.href=url;
	}
}

function quickEdit(id) {
	openConsoleWindow('editappt', 'appointment-edit.php?updateList=&id='+id,530,550);
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

function toggleDate(rowId) {
	var el = document.getElementById(rowId+'_headers');
	el.style.display = el.style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var el = document.getElementById(rowId+'_row');
	el.style.display = el.style.display == 'none' ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
	var n = rowId.split('_');
	n = n[1];
	document.getElementById('day-shrink-'+n).src = (el.style.display == 'none' ? 'art/down-black.gif' : 'art/up-black.gif');
}


<?
dumpPopCalendarJS();
?>

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
	if(target != 'messages')
		if(val != null && val.indexOf('MISASSIGNED') != -1) alert('Because of scheduled time off or exclusive service conflicts, this visit has been marked UNASSIGNED.');
		else if(val != null && val.indexOf('EXCLUSIVECONFLICT') != -1) alert('Because of an already scheduled exclusive visit, this visit has been marked UNASSIGNED.');
		else if(val != null && val.indexOf('INACTIVESITTER') != -1) alert('Because the sitter is now inactive, this visit has been marked UNASSIGNED.');
		refresh(); // implemented below
}
</script>

<img src='art/spacer.gif' width=1 height=160>

<?
include "js-refresh.php";

// ***************************************************************************

include "frame-end.html";
?>
