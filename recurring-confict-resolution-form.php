<?
// recurring-confict-resolution-form.php
// included by service-repeating
/*
  $conflicts['timeConflicts'] = findTimeConflicts($newpackageid, $clientptr); // date=>new|old=>conflicts
  $conflicts['unassignedAppointments'] = findUnassignedAppointments($newpackageid);// date=>conflicts
  $conflicts['customConflicts'] = findCustomConflicts($packageid);  //date=>conflicts
*/

if(!$clientid) $clientid = $client; // from service-repeating

if($_GET['conflictid']) {
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	require_once "service-fns.php";
	require_once "appointment-calendar-fns.php";
//if(mattOnlyTEST()) { print_r($_REQUEST); exit;}	
	$packageid = $_GET['conflictid'];
	$conflicts = findConflicts($_GET['client'], $_GET['conflictid'], 1);
}
	
if($_GET['undeletableAppointments']) {
	$undeletableAppointments = explode(',', $_GET['undeletableAppointments']);
}

$problems = array();
$dates = array();
foreach($conflicts as $conftype => $group) {
  if($conftype == 'timeConflicts')
    foreach($group as $date => $newOld) {
      $dates[] = $date;
      foreach($newOld['old'] as $row) {
				if(!$clientid) $clientid = $row['clientptr'];
				$row['timeconflict'] = 1;
				$row['conflictType'] = $conftype;
				$row['title'] = " ($conftype)";
				$problems[$date][] = $row;
			}
    }
  else foreach($group as $date => $rows) {
			$dates[] = $date;
			foreach($rows as $row) {
				if(!$clientid) $clientid = $row['clientptr'];
				$row['conflictType'] = $conftype ? $conftype : print_r(array_keys($conflicts),1);
				$row['title'] = " ({$row['conflictType']})";
				$problems[$date][] = $row;
			}
  }
}
//if(mattOnlyTEST()) print_r($problems);
ksort($problems);
$displayDates = $_REQUEST['dates'] ? $_REQUEST['dates'] : ($dates ? join(',', $dates) : '');
//print_r($problems);exit;

$newAppointments = getAllTBDAppointments($packageid, true);

if(TRUE /*staffOnlyTEST()*/) {
foreach($newAppointments as $date => $rows)
	foreach($rows as $row) {
		if(!$clientid) $clientid = $row['clientptr'];
		$undeletableAppointments[] = $row['appointmentid'];
	}
}
else {
if(!$clientid) foreach($newAppointments as $date => $rows)
	foreach($rows as $row) 
		if(!$clientid) $clientid = $row['clientptr'];
}
$clientDetails = getOneClientsDetails($clientid);
$providerNames = getProviderShortNames();

$dates = array_unique($dates);
sort($dates);

if($_GET['conflictid']) {
	dispayProblemsInCalendarForm($problems, $newAppointments, $displayDates);
	exit;
}


?>
<style>
.conflicts {width:100%;}
.conflicts td {font-size:1.1em;}
.redcaps {color:red;font-variant:small-caps;};
.caption { font-size:1.1em; }
</style>
<?

/*if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' || $_SESSION['staffuser']) {*/
	echo "<span class='caption'>There are potential conflicts on the days shown below.  You may wish to edit or delete some of the visits.</span>\n";
	//echoButton('', 'Refresh', 'update()');
	//echo '';
	echoButton('','Delete Selected Visits','deleteSelections()', 'HotButton', 'HotButtonDown');
	echo ' ';
	echoButton('', 'Done', 'done()');
	//fauxLink('Done', "document.location.href=\"client-edit.php?id=$clientid\"");
	require_once "appointment-calendar-fns.php";
	echo "<div name='apptkiller' id='apptkiller'>\n";
	dispayProblemsInCalendarForm($problems, $newAppointments, $displayDates);
	echo "</div>";
/*}
else {
	echo "<table width='100%'><tr><td>There are visit issues you may wish to address.<br><span class='redcaps'>red</span> messages indicate possible problems.  Hover over them for details.</td><td align=right>";
	echoButton('','Delete Selected Visits','checkAndSubmit()', 'HotButton', 'HotButtonDown');
	echo " ";
	echoButton('','Quit',"document.location.href=\"client-edit.php?id={$_POST['client']}&tab=services\"");
	echo "</td></tr></table>";
	//echo "PACKAGE: $packageid ";//print_r($newAppointments);
	//echo print_r($problems);
	echo "<form name='apptkiller' method='POST'>\n";
	hiddenElement('killAppointments', 1);
	hiddenElement('client', $_POST['client']);
	dispayProblemsInListForm($problems, $newAppointments);
	echo "</form>";
}*/

function dispayProblemsInListForm($problems, $newAppointments) {
	echo "<table class='conflicts'>";
	$duplicates = array();
	foreach($problems as $day => $rows) {
		// display the day header
		echo "<tr><td colspan=6 style='background:lightblue;font-weight:bold;'>".longerDayAndDate(strtotime($day))."</td></tr>\n";
		// display the day's new appointments
		if($newAppointments[$day]) foreach($newAppointments[$day] as $appt) displayAppointmentConflict($appt, 'new');
		// display the day's problem appointments (minus any unassigned new appointments)
		foreach($problems[$day] as $appt) {
			if(!in_array($appt['appointmentid'], $duplicates))
				if($appt['packageptr'] != $packageid) displayAppointmentConflict($appt, false);
			$duplicates[] = $appt['appointmentid'];
		}
	}
	echo "</table>";
}

function dispayProblemsInCalendarForm($problems, $newAppointments, $dates=null) {
	global $suppressNoVisits, $showModifiedTag, $clientid;
	$appts = array();
	if($dates) {
		$dates = explode(',', $dates);
		$appts = fetchAssociations("SELECT * FROM tblappointment
																WHERE clientptr = $clientid
																	AND date IN ('".join("','", $dates)."')
																	ORDER BY date, starttime");
	}
	else foreach((array)$problems as $date => $cluster) {
		$appts = array_merge($appts, $cluster);
		if($newAppointments[$date]) $appts = array_merge($appts, $newAppointments[$date]);
		uasort($appts, 'datetimeSort');
	}
//if(mattOnlyTEST()) print_r($problems);
	foreach((array)$problems as $problem) {
		if(!$problem['title']) continue;
		foreach((array)$appts as $i=>$appt) {
			if($appt['appointmentid'] == $problem['appointmentid'])
				$appts[$i]['title'] = $problem['title'];
		}
	}
	
//echo "PROBS: ";print_r($problems);echo "newAppointments: ";print_r($newAppointments);	
	dumpCalendarLooks(100, 'lightblue');
	$suppressNoVisits = 1;
	$showModifiedTag = true;
  appointmentTable($appts, $packageDetails = null, $editable=false, $allowSurchargeEdit=false, $showStats=false, $includeApptLinks=true, $surcharges=null);
}

function datetimeSort($a, $b) {
	return strcmp($a['date'], $b['date']) ? strcmp($a['date'], $b['date']) : strcmp($a['starttime'], $b['starttime']);
}

function displayAppointmentConflict($appt, $new=false) {
	global $clientDetails, $providerNames;
	$class = $new ? '' : 'class="olderappointment"';
	$idstr = "'appt_{$appt['appointmentid']}'";
	$date = shortDate(strtotime($appt['date']));
	$prov = $appt['providerptr'] ? $providerNames[$appt['providerptr']] : "<span class='warning'>Unassigned</span>";
	$timeofday = $appt['timeconflict'] ? "<span class='warning'>{$appt['timeofday']}</span>" : $appt['timeofday'];
	$timeTip = $appt['timeconflict'] ? 'A new visit occurs at the same time as this earlier-scheduled visit' : '';
	$serviceNote = $appt['canceled'] ? "<span class='redcaps'>canceled </span>" : (
									$new ? "<span style='color:green;font-variant:small-caps;'>new </span>" : (
									$appt['custom'] ? "<span class='redcaps'>custom </span>" : (
									"<span style='color:black;font-variant:small-caps;'>old </span>")));
	$serviceTip = $appt['canceled'] ? 'The system will not automatically replace canceled visits.' : (
								$appt['custom'] ? 'The system will not automatically replace user-modified visits.': '');
	//echo "<tr $class><td colspan=7>".print_r($appt, 1)."</td>";
//$conflictType = ($_SERVER['REMOTE_ADDR'] == '68.225.89.173') ? "[{$appt['conflictType']}]" : '';
	echo "<tr $class><td><input type='checkbox' id=$idstr name=$idstr></td>";
	echo "<td title='$timeTip'>$timeofday</td>";
	echo "<td>{$clientDetails['clientname']}</td>";
	echo "<td>{$appt['pets']}</td>";
	echo "<td title='$serviceTip'>$serviceNote".serviceLink($appt)."</td>";
	echo "<td>$prov</td>";
	echo "</tr\n";
}

function clientLink($clientptr, $clients) {
	global $clientDetails;
	return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id=$clientptr\",700,500)'>
	       {$clientDetails['clientname']}</a> ";
}
function serviceLink($row) {
	$petsTitle = $row['pets'] 
	  ? htmlentities("Pets: {$row['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	$targetPage = 'appointment-view.php';
	$label = $row['custom'] ? '<b>(M)</b> ' : '';
	$label .= $_SESSION['servicenames'][$row['servicecode']];
	return "<a href=# 
	       onClick='openConsoleWindow(\"editappt\", \"$targetPage?id={$row['appointmentid']}\",530,450)' 
	       >$label</a>"; //title='$petsTitle'
}

include "js-refresh.php";

?>
<script language='javascript' src='appointment-calendar-fns.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
var undeletableAppointments = new Array(<?= $undeletableAppointments ? join(',', $undeletableAppointments) : '' ?>);

function isApptDeletable(apptid) {
	for(var i=0; i < undeletableAppointments.length; i++)
		if(undeletableAppointments[i] == apptid) return false;
	return true;
}

function done() {
	document.location.href="client-edit.php?tab=services&id=<?= $clientid ?>";
}

function toggleVisitOrSurcharge(el) {
	var onOff = el.style.borderColor == 'red' ? 0 : 1;
	onOff = (onOff == 'off' || onOff == 0) ? false : onOff;
	el.style.borderColor= onOff ? 'red' : 'black';;
	el.style.borderWidth= onOff ? 'thick' : 'thin';

}

function update(arg1, responseText) {
	//ajaxGet('recurring-confict-resolution-form.php?conflictid=<?= $packageid ?>&client=<?= $clientid ?>', 'apptkiller');
	if(responseText && (typeof responseText == 'string') && responseText.indexOf('MISASSIGNED') != -1) alert('Because of scheduled time off, this visit has been marked UNASSIGNED.');
	else if(responseText && (typeof responseText == 'string') && responseText.indexOf('EXCLUSIVECONFLICT') != -1) alert('Because of an already scheduled exclusive visit, this visit has been marked UNASSIGNED.');	
	else if(responseText && (typeof responseText == 'string') && responseText.indexOf('INACTIVESITTER') != -1) alert('Because the sitter is now inactive, this visit has been marked UNASSIGNED.');	
	ajaxGetAndCallWith('recurring-confict-resolution-form.php?conflictid=<?= $packageid ?>&client=<?= $clientid ?>&dates=<?= $displayDates ?>&undeletableAppointments=<?= join(',', $undeletableAppointments) ?>', 
		restockConflicts, 0);
}

function restockConflicts(unused, responseText) {
	//document.write(responseText);
	document.getElementById('apptkiller').innerHTML = responseText;
	setupSelections();
	$('.BlockContent-body').busy("hide");
}

function deleteSelections() {
	var appts = new Array();
	$('.selectable').each(function (i, el) 
		{ 
			if(el.style.borderColor == 'red') {
				if(el.id.indexOf('appt_')==0) {
					el = el.id.split('_');
					appts[appts.length] = el[1]; 
				}
			}
		});
	if(appts.length > 0 && confirm("You are about to permanently delete "+appts.length+" visits.  Proceed?")) deleteAppt(appts.join(','));
	else alert("Please select one or more visits to delete.");
} 

function checkAndSubmit() {  // For old version
	for(var i=0;i<document.apptkiller.elements.length;i++)
	  if(document.apptkiller.elements.item(i).checked) {
	    document.apptkiller.submit();
	    return;
		}
	alert("No appointments were selected for deletion.\n Use the Quit button if you wish to retain all the appointments.");
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

function setupSelections() {
	$('.selectable').each(
		function (index, el) {
			if(el.id.indexOf('appt_')==0) {
				var elid = el.id.split('_');
				if(!isApptDeletable(elid[1])) {
					$('#'+el.id).removeClass('selectable');
				}
			}

		});
	$('.selectable').click(function (event) {toggleVisitOrSurcharge(event.target); });
}

setupSelections();
</script>
