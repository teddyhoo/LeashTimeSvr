<? // rollover-diagnostic.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";

$mostRecentRollover = fetchFirstAssoc("SELECT * FROM tblchangelog WHERE note LIKE 'ROLLOVER FINISHED%' ORDER BY time DESC LIMIT 1");

echo "<h2>Rollover Report for $db</h2>";
echo "Generated ".date("l F j, Y h:i")."<p>";
echo "Rollover finished: ".date("l F j, Y h:i", strtotime($mostRecentRollover['time']))."<br>";
$frags = explode(' ', $mostRecentRollover['note']);
echo "Appointments created: {$frags[2]}<hr>";

$rolloverStart = date('Y-m-d 00:00', strtotime($mostRecentRollover['time']));
$rolloverEnd = date('Y-m-d h:i', strtotime("+ 4 hour", strtotime($rolloverStart)));

$rolloverAppts = fetchAssociations($sql =
	"SELECT *, CONCAT_WS(' ', tblclient.fname, tblclient.lname) AS clientname,
	    CONCAT_WS(' ', tblprovider.fname, tblprovider.lname) AS providername, label as service
	 FROM tblappointment 
	 LEFT JOIN tblclient ON clientid = clientptr
	 LEFT JOIN tblprovider ON providerid = providerptr
	 LEFT JOIN tblservicetype ON servicetypeid = servicecode
	 WHERE createdby IS NULL AND created >= '$rolloverStart' AND created < '$rolloverEnd'
	 ORDER BY tblclient.lname ASC, tblclient.fname ASC, date ASC, starttime ASC");
	 
	 	 
$client = null;
$counts = array();
foreach($rolloverAppts as $appt) $counts[$appt['clientptr']] += 1;



foreach($rolloverAppts as $appt) {
	if($appt['clientptr'] != $client) {
		if($client) {
			echo "</table>";
			showDups($client);
		}
		$client = $appt['clientptr'];
		echo "\n<p><u>".clientLink($client, $appt['clientname'])."</u> ({$counts[$client]} appointments created)<br>";
		//$package = getPackage($appt['packageptr'], 'R');
		echo "\n<div style='border: solid black 1px;width: 800px;'>";
		recurringPackageSummary($appt['packageptr']);
		echo "\n</div>";
		echo "<table width=800 border=1 bordercolor=black>";
	}
	echo "<tr><td>[{$appt['appointmentid']}]<td>".shortDateAndDay(strtotime($appt['date']))."<td>{$appt['timeofday']}".
				"<td>{$appt['service']}<td>{$appt['providername']}<td>{$appt['pets']}";
}
if($client) {
	echo "</table>";
	showDups($client);
}



function showDups($client) {
	$dups = array();
	$dupIds = array();
	$appts = fetchAssociations("SELECT date, appointmentid, canceled, starttime FROM tblappointment WHERE clientptr = $client", 1);
	//$appts = fetchAssociations("SELECT date, appointmentid, canceled, serviceptr,providerptr, recurringpackage, timeofday, servicecode, pets, charge, adjustment, rate, bonus FROM tblappointment WHERE clientptr = $client");
	foreach($appts as $appt) {
		$minDate = strcmp($minDate, $appt['date']) < 1 ? $minDate : $appt['date'];
		$maxDate = strcmp($appt['date'], $maxDate) < 1 ? $maxDate : $appt['date'];
		//$appt['date'] = date('m/d/Y', strtotime($appt['date']));
		$appt['date'] = date('m/d/Y', strtotime($appt['date']));

		$id = $appt['appointmentid'];
		unset($appt['appointmentid']);
		$canceled = $appt['canceled'] ? '<font color=red>*</font>' : '';
		unset($appt['canceled']);
		$str = print_r($appt,1);
		$dups[$str][] = "$id$canceled";
	}
	$areDups = false;
	foreach($dups as $ids) if(count($ids) > 1) $areDups = true;
	if($areDups) echo "<p><font color=red><b>POTENTIAL DUPLICATES:</b></font><p>";
	foreach($dups as $dup => $ids) {
		if(count($ids) > 1) foreach($ids as $id) $dupIds[] = ($bracket = strpos($id, '<')) ? substr($id,0, $bracket) : $id;
		for($i=0;$i<count($ids);$i++) $ids[$i] = appointmentLink($ids[$i]);
		if(count($ids) > 1) {
			echo "[".count($ids)."] ids: (".join(', ',$ids).")<font color=green> $drop</font><br>$dup<p>";
		}
	}
	if($dupIds) {
		$dupcreations = fetchAssociations("SELECT appointmentid, created FROM tblappointment WHERE appointmentid IN (".join(', ',$dupIds).") ORDER BY created");
		$dupCreationTimes = array();
		foreach($dupcreations as $creation) {
			$created = strtotime($creation['created']) ? date('m/d/Y', strtotime($creation['created'])) : 'unknown date';
			$dupCreationTimes[$created][] = $creation['appointmentid'];
		}
		foreach($dupCreationTimes as $time => $apptids)
			echo "created $time: ".join(', ',$apptids)."<br>";
	}
}

function clientLink($client, $clientname) {
	return "<a onClick='viewClientServices($client)'><span style='cursor:pointer;color:blue'><u>$clientname</u></span></a>";
}

function appointmentLink($id) {
	$label = $id;
	if($bracket = strpos($id, '<')) {
		$id = substr($id,0, $bracket);
		$title = "TITLE = 'Appointment is CANCELED'";
	}
	return "<a onClick='viewAppointment($id)' $title><span style='cursor:pointer;color:blue'><u>$label</u></span></a>";
}

?>

<script language='javascript'>
function viewAppointment(id) {
	var url = "appointment-edit.php?id="+id;
  var w = window.open("",'editappointment',
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+800+',height='+700);
  w.document.location.href=url;
  if(w) w.focus();
}

function viewClientServices(client) {
	var url = "client-edit.php?id="+client+"&tab=services";
  var w = window.open("",'clientviewer',
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+800+',height='+700);
  w.document.location.href=url;
  if(w) w.focus();
}
</script>