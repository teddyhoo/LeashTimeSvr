<? // client-meeting.php
// changed 4/4/2017

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "provider-memo-fns.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
include "time-framer-mouse.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-');
extract(extractVars('startdate,timeofday,clientptr,notes,prepaid,servicecode,go', $_REQUEST));
$numProviderSlots = $_SESSION['preferences']['maxmeetingsitters'] ? $_SESSION['preferences']['maxmeetingsitters'] : 2;
if($timeofday) {
	$tod = explode('-', $timeofday);
	$starttm = strtotime($tod[0]);
	$endtm = strtotime($tod[1] ? $tod[1] : $tod[0]);
	// if either is 0 or if they are not on the same day, they are bad
	if(!$starttm || !$endtm || (date('Y-m-d', $starttm) != date('Y-m-d', $endtm))) {
		$badTime = "Could not understand customer-supplied time: $timeofday";
		$timeofday = "";
	}
	else $timeofday = date('g:i a', $starttm).'-'.date('g:i a', $endtm);
	if(date('H', $starttm) < 8 || date('H', $endtm) < 8)
		$badTime = "This meeting is pretty early.  Are you sure the time is right?";
}


$client = getClientDetails(array($clientptr));
$client = $client[$clientptr];
$pageTitle = "Arrange a Meeting With {$client['clientname']}";


if($go) {
	$startdate = date('Y-m-d', strtotime($startdate));
	$data = array_merge($_REQUEST);
	$data['prepaid'] = $_SESSION['preferences']['schedulesPrepaidByDefault'];
	$data['startdate'] = $startdate;
	$data['enddate'] = $startdate;
	$data['preemptrecurringappts'] = 0;
	$data['current'] = 1;
	$data['irregular'] = 2;
	$packageid = saveNewIrregularPackage($data);
	foreach($_REQUEST as $k => $v)
		if($v && strpos($k, 'provider') === 0)
			$attendees[] = $v;
	
	setPreference('lastMeetingServiceCodeSelected', $servicecode);
	$unavailableProviders = array();
	foreach($attendees as $i => $prov) {
		if(!$prov) {
			unset($attendees[$i]);
			continue;
		}
		$serviceType = serviceDetails($servicecode, $clientptr, $prov);
		$charge = isset($serviceType['client']['charge']) ? $serviceType['client']['charge'] : $serviceType['standard']['defaultcharge'];
		$thisTypeRate = $serviceType['provider'] ? $serviceType['provider'] : $serviceType['standard'];
		if(array_key_exists('defaultrate', (array)$thisTypeRate)) $thisTypeRate['rate'] = $thisTypeRate['defaultrate'];
		$rate = $thisTypeRate['ispercentage'] ? $charge * $thisTypeRate['rate']/100 : $thisTypeRate['rate'];
		//$rate = isset($serviceType['provider']['rate']) ? $serviceType['provider']['rate'] : $serviceType['standard']['defaultrate'];
		$task = array('packageptr'=>$packageid, 'daysofweek'=>'', 'timeofday'=>$timeofday, 'servicecode'=>$servicecode, 
									'pets'=>'', 'charge'=>$charge, 'adjustment'=>0, 'rate'=>$rate, 'bonus'=>$bonus, 'recurring'=>false,
									'clientptr'=>$clientptr, 'providerptr'=>$prov, 'surchargenote'=>'', 'serviceid'=>'0',
									'note'=>$notes);		
		$task['canceled'] = null;
		$task['completed'] = null;
		if(TRUE || mattOnlyTEST()) {
			if(!fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $prov LIMIT 1")) {
				$unavailableProviders[$prov] = 'who is inactive';
			}
			else if(providerIsOff($prov, $startdate, $timeofday)) {
				$unavailableProviders[$prov] = 'who has time off at that time';
			}
			else {
				$appt = createAppointment(false, $packageid, $task, strtotime($startdate), null, $simulation=true);
				if(detectVisitCollision($appt, $prov)) {
					$unavailableProviders[$prov] = 'who has a conflicting visit';
				}
			}
			if(!$unavailableProviders[$prov]) 
				createAppointment(false, $packageid, $task, strtotime($startdate));
		}
		//else {
		//	$appt = createAppointment(false, $packageid, $task, strtotime($startdate));
		//}
	}
	$providers = getProviderNames("WHERE providerid IN (".join(',', $attendees).")");
	
	$actuallyAvailableProviders = (array)$providers+array();
	foreach($unavailableProviders as $prov => $reason) {
		$unavailableNames[] = "{$providers[$prov]} ($reason)";
		unset($actuallyAvailableProviders[$prov]);
	}
//if(mattOnlyTEST()) {print_r($actuallyAvailableProviders);exit;}
	$attendees = array_keys($actuallyAvailableProviders);
	foreach($attendees as $i=>$prov) if(!$prov) unset($attendees[$i]);
	if(!$attendees) {
		deleteNRPackage($packageid, $descndents=false, $ancestors=false);
		$unavailableNameList = $unavailableNames ? join('<br>', $unavailableNames) : '';
		$unavailableNames = $unavailableNames ? join(', ', $unavailableNames) : '';
		$_SESSION['frame_message'] = 
			"No Meeting was arranged because none of the sitters are available: $unavailableNames";
		if(TRUE || $_SESSION['preferences']['enableMeetingSetupLightbox']) $_SESSION['user_notice'] = 
			"<span class='fontSize1_8em'>No Meeting was arranged because none of the sitters are available:</span>"
			."<span class='fontSize1_5em'><p>$unavailableNameList</span>";
	}
	else if($attendees) {
		$_SESSION['frame_message'] = 
			"Meeting arranged".
				($unavailableNames ? ", except for ".join(', ', $unavailableNames) : '');
		if($unavailableNames && (TRUE || dbTEST('scvgotpaws')) ) $_SESSION['user_notice'] = 
			"<span class='fontSize1_8em'>Meeting arranged</span>"
			."<span class='fontSize1_5em'><p>"."except for ".join('<br>', $unavailableNames)."</span>";

		//client-meeting-composer.php startdate,timeofday,clientptr
		$bizName = $_SESSION['preferences']['shortBizName'] 
							? $_SESSION['preferences']['shortBizName']
							: $_SESSION['preferences']['bizName'];

		if($notes) {
			$notes = str_replace("|", '&#166;', $notes);
			$notes = str_replace("\r", '', $notes);
			$notes = str_replace("\n\n", '<p>', $notes);
			$notes = str_replace("\n", '<br>', $notes);

		}
		$memo = "You have been scheduled for a meeting between client {$client['clientname']} and with $bizName staff"
						.'<ul>'
						.'<li>'.join('<li>', $actuallyAvailableProviders)
						.'</ul>'
						." on ".longestDayAndDate(strtotime($startdate)).' at '.substr($timeofday, 0, strpos($timeofday, '-')).'.'
						.($notes ?  "<p>$notes" : '');
						
		foreach($attendees as $prov) {
			if(!$unavailableProviders[$prov]) 
				makeProviderMemo($prov, $memo, $clientptr);
		}

		$destination = globalURL("client-edit.php?id=$clientptr&tab=services");
		$composer = "client-meeting-composer.php?clientptr=$clientptr&startdate=$startdate&timeofday=$timeofday"
								."&providers=".join(',', $attendees);
		echo <<<HTML
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
<title>Your Page Title</title>
<meta http-equiv="REFRESH" content="0;url=$destination"></HEAD>
<BODY>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
openConsoleWindow('composer', '$composer',600,600);
</script>
<p><span style='font-style:italic;'>Meeting has been scheduled.  Please wait a moment...</span>
</BODY>
</HTML>	
HTML;
		exit;
	}
}
$breadcrumbs = "<a href='client-edit.php?id=$clientptr&tab=services'>{$client['clientname']}</a>";	
include "frame.html";
// ***************************************************************************
if($_SESSION['frame_message']) {
	include "frame-end.html";
	exit;
}
//print_r($_REQUEST);
?>
<table width=100%  style='font-size:1.1em'>
<tr>
<td valign=top>
<form name='savemeeting' method='POST'>
<?
hiddenElement('clientptr', $clientptr);
hiddenElement('go', 1);
$d = $startdate ? $startdate : shortDate();
calendarSet('Date:', 'startdate', $d, $labelClass=null, $inputClass=null, $includeArrowWidgets=true, $secondDayName=null, $onChange='checkProviderAvailability()');
echo " Time: <table style='display:inline;width:100px'><tr><td><img src='art/spacer.gif' width=100 height=1>";
buttonDiv("div_timeofday", "timeofday", "showTimeFramerInContentDiv(event, \"div_timeofday\")",$timeofday);
echo "</td></tr></table>";
if($badTime) echo "<br><span class='tiplooks'>$badTime</span>";
echo "<p>";
$options = array('- Choose Meeting Type -'=>0);
foreach(fetchKeyValuePairs("SELECT label, servicetypeid FROM tblservicetype WHERE active ORDER BY menuorder")
				as $label => $val)
		$options[$label] = $val;
selectElement('Meeting:', 'servicecode', $value=$_SESSION['preferences']['lastMeetingServiceCodeSelected'], $options);

for($i=1; $i <= $numProviderSlots; $i++) {
	echo "<p id='providerline$i'> Sitter: ";
	availableProviderSelectElement($clientptr, $date=null, "provider$i", $nullChoice, $choice, $onchange='checkProviderAvailability()');
	if($i >= 5 && $i < $numProviderSlots) { 
		$legend = $i == 5 ? " <span class='tiplooks'>Click the plus sign to add more sitters.</span>" : '';
		echo " <span id='plus$i' onclick='openNext($i)' style='font-weight:bold;cursor:pointer;' title='Show another sitter slot'>&#65291; $legend</span>";
	}
	$nullChoice = '(optional)';
}
echo "<p> Note:<br><textarea id='notes' name='notes' cols=40 rows=3></textarea><p>";
echoButton('', 'Set Up Meeting', 'makeMeeting()');
?>
</form>
<? makeTimeFramer('timeFramer', 'narrow'); ?>

</td>
<td valign=top>
<b>Upcoming Meetings with this client: </b>
<?= upcomingMeetingTable($clientptr) ?>
<p>
<? $allMeetings = upcomingMeetingTable($clientptr, $not=true);?>
<p>
<b>Upcoming Meetings with other clients: </b>
<?= $allMeetings == 'none' 
		? $allMeetings 
		: fauxLink('Show', "document.getElementById(\"allmeetings\").style.display=\"block\"", 1, 'Show meetings for all clients.'); ?>
<?= 
"<div id='allmeetings' style='display:none;margin-top:-5px;background:#CDFECD;'>"
.fauxLink('(Hide)<p>', "document.getElementById(\"allmeetings\").style.display=\"none\"", 1, 'Hide meetings for all clients.')
.$allMeetings."</div>" ?>
<p>
<div id='schedules' style='display:block'>
</div>
</td>
</tr>
</table>
<img src='art/spacer.gif' width=1 height=100>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
setPrettynames('timeofday','Meeting time','servicecode','Meeting Type');

function openNext(plusIndex) {
	$('#plus'+plusIndex).toggle();
	$('#providerline'+(plusIndex+1)).toggle();
}

function makeMeeting() {
	var test = collectProvidersSpecified().length == 0
								? 'Please choose at least one sitter first.' : '';
	document.getElementById('timeofday').value = document.getElementById('div_timeofday').innerHTML;
	if(MM_validateForm(
		'startdate', '', 'R',
		'startdate', '', 'isDate',
		'timeofday', '', 'R',
		test, '', 'MESSAGE'
		)) {
			document.savemeeting.submit();
	}
}

function collectProvidersSpecified() {
	var provs = new Array();
	for(var i=1; i<= <?= $numProviderSlots ?>; i++)
		if(document.getElementById('provider'+i).value) 
			provs[provs.length] = document.getElementById('provider'+i).value;
	return provs;
}

function checkProviderAvailability() {
	var date;
	if(validateUSDate(date = document.getElementById('startdate').value)) {
		ddate = mdy(date);
		if(ddate.indexOf('-'))	ddate = ddate[2]+'-'+ddate[0]+'-'+ddate[1];
		else ddate = ddate[2]+'-'+ddate[1]+'-'+ddate[0];
		var provs = collectProvidersSpecified();
//alert(provs);		
		ajaxGetAndCallWith('provider-availability-ajax.php?date='+ddate+'&provids='+provs.join(','), update, date);
	}
	else document.getElementById('schedules').innerHTML = '';
}

function update(date, resultxml) {
	var root = getDocumentFromXML(resultxml).documentElement;
	if(root.tagName == 'ERROR') {
		alert(root.nodeValue);
		return;
	}
	var nodes = root.getElementsByTagName('appts') ;
	if(nodes.length == 1) {
		var appts = nodes[0].getElementsByTagName('appt');
		if(appts.length > 0) {
			var atable = "<table>";
			for(var i=0; i < appts.length; i++) {
				atable += "<tr><td>"+appts[i].getElementsByTagName('t')[0].firstChild.nodeValue+"<td>"
					+"<td>"+appts[i].getElementsByTagName('p')[0].firstChild.nodeValue+"<td>"
					+"<td>"+appts[i].getElementsByTagName('s')[0].firstChild.nodeValue+"<td>"
					+"</tr>";
			}
			atable += "</table>";
		}
	}
	nodes = root.getElementsByTagName('offs') ;
	if(nodes.length == 1) {
		var offs = nodes[0].getElementsByTagName('off');
		if(offs.length > 0) {
			var otable = "<table>";
			for(var i=0; i < offs.length; i++) {
				otable += "<tr><td>"+offs[i].getElementsByTagName('p')[0].firstChild.nodeValue+"<td>"
					+"<td class='warning'>"+offs[i].getElementsByTagName('t')[0].firstChild.nodeValue+"<td>"
					+"</tr>";
			}
			otable += "</table>";
		}
	}
	document.close();
	var schedules = '<b>Sitters off '+date+':</b>'+(otable == undefined ? ' none' : otable);
	if(collectProvidersSpecified().length > 0) 
		schedules += '<p><b>Visits Scheduled for These Sitters '+date+':</b><p>'+(atable == undefined ? 'none' : atable);
	document.getElementById('schedules').innerHTML = schedules;
}

<?

dumpPopCalendarJS();
dumpTimeFramerJS('timeFramer');

for($i=6; $i <= $numProviderSlots; $i++) {
	echo "$('#providerline$i').toggle();\n";
}

?>
checkProviderAvailability();
</script>
<?
include "frame-end.html";

function upcomingMeetingTable($clientptr=null, $not=false) {
	$today = date('Y-m-d');
	$clientptr = $not ? "clientptr != $clientptr" : "clientptr = $clientptr";
	$meets = fetchAssociationsKeyedBy(
		"SELECT packageid, startdate, clientptr 
			FROM tblservicepackage
			WHERE $clientptr 
				AND current = 1
				AND irregular = 2
				AND startdate >= '$today'
			ORDER BY startdate", 'packageid');
	ob_start();
	ob_implicit_flush(0);
	if($meets) {
		foreach($meets as $meet) $clients[] = $meet['clientptr'];
		$clients = getClientDetails(array_unique($clients));
		$provs = fetchAssociations(
			"SELECT packageptr, timeofday, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as nm
				FROM tblappointment
				LEFT JOIN tblprovider ON providerid = providerptr
				WHERE packageptr IN (".join(',', array_keys($meets)).")
				ORDER BY nm");
		echo "<table>";
		foreach($meets as $packageid => $meet) {
			$staff = array();
			$timeofday = null;
			foreach($provs as $p) if($p['packageptr'] == $packageid) {
				$timeofday = $timeofday ? $timeofday : $p['timeofday'];
				$staff[] = $p['nm'];
			}
			$staff = $staff ? join(', ', array_unique($staff)) : 'none';
			$link = fauxLink(shortDate(strtotime($meet['startdate']))." $timeofday", "document.location.href=\"service-irregular.php?packageid=$packageid\"", 1, 'Edit this meeting');
			$includeClient = $not ? "<td>({$clients[$meet['clientptr']]['clientname']})</td>" : '';
			echo "<tr><td>".$link."</td>$includeClient<td>$staff</td><tr>";
		}
		echo "</table>";
	}
	else {
		echo "none";
	}
	$descr = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $descr;
}