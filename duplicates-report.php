<? // duplicates-report.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";

set_time_limit(0);
$t0 = microtime(1);
if($_GET['go'] && $db != $_GET['go']) {
	
	echo "WRONG DATABASE SUPPLIED";
	exit;
}


echo "<h2>Duplicates Report for $db</h2>";
echo "Generated ".date("l F j, Y h:i")."<p>";
$start = $_GET['start'] ? date('Y-m-d', strtotime($_GET['start'])) : date('Y-m-d', strtotime("-61 days"));
$end = $_GET['end'] ? date('Y-m-d', strtotime($_GET['end'])) : '';
if(array_key_exists('createdby', $_GET)) $createdBy = " AND createdby = {$_GET['createdby']}";

$clients = fetchAssociationsKeyedBy("SELECT * FROM tblclient WHERE active = 1 ORDER BY lname, fname", 'clientid');;


foreach($clients as $clientid => $client) {
	if($appt['clientptr'] != $client)
		showDups($clientid, $start, $end);
}
echo "<hr>Total DOOMED visits: $totalDoomed from $minDate to $maxDate<p>";
echo microtime(1)-$t0." seconds.";


function showDups($client, $start, $end=null) {
	global $clients, $createdBy, $totalDoomed, $minDate, $maxDate;
	$dups = array();
	$dupIds = array();
	$end = $end ? " AND date <= '$end'" : '';
	$appts = fetchAssociations($sql = "SELECT date, appointmentid, pets, servicecode, canceled, starttime, modified FROM tblappointment 
			WHERE clientptr = $client AND recurringpackage = 1 AND date >= '$start' $end $createdBy", 1); //  AND modified IS NULL
	//$appts = fetchAssociations("SELECT date, appointmentid, canceled, serviceptr,providerptr, recurringpackage, timeofday, servicecode, pets, charge, adjustment, rate, bonus FROM tblappointment WHERE clientptr = $client");
//echo "$client: $sql ".count($appts)."<br>";
	foreach($appts as $appt) {
		//$appt['date'] = date('m/d/Y', strtotime($appt['date']));
		$appt['date'] = date('m/d/Y', strtotime($appt['date']));

		$modified = $appt['modified'] ? 1 : 0;
		unset($appt['modified']);
		$id = $appt['appointmentid'];
		unset($appt['appointmentid']);
		
		$mods = ($canceled = $appt['canceled'])  ? '*' : '';
		unset($appt['canceled']);
		if($modified) $mods .= "#";
		$mods = $mods ? "<font color=red>$mods</font>" : '';
		$str = "{$appt['date']}|".print_r($appt,1);
//echo "$str<br>";		
		$dups[$str][] = "$id$mods";
		if($canceled) $canned[$str] += 1;
	}
	$areDups = false;
	foreach($dups as $ids) if(count($ids) > 1) $areDups = true;
	if($areDups) echo "<p>".clientLink($client, "{$clients[$client]['fname']} {$clients[$client]['lname']}")."</font><p>";
	foreach($dups as $dup => $ids) {
		if(count($ids) > 1) {
			foreach($ids as $id) $dupIds[] = ($bracket = strpos($id, '<')) ? substr($id,0, $bracket) : $id;
			for($i=0;$i<count($ids);$i++) $ids[$i] = appointmentLink($ids[$i]);
			$appdate = date('Y-m-d', strtotime(substr($dup, 0 , strpos($dup, '|'))));
			$minDate = !$minDate ? $appdate : (strcmp($minDate, $appdate) < 1 ? $minDate : $appdate);
			$maxDate = !$maxDate ? $appdate : (strcmp($appdate, $maxDate) < 1 ? $maxDate : $appdate);
			if($canned[$dup] != 0 && $canned[$dup] != count($ids)) $action = "<font color=red>SOME ARE CANCELED!</font>";
			else {
				$doomed = array();
				for($i=1; $i<count($ids); $i++) {
					$s = strip_tags($ids[$i]);
					if(strrpos($s, '*')) $s = substr($s, 0, strrpos($s, '*'));
					if(strrpos($s, '#')) $s = substr($s, 0, strrpos($s, '#'));
					$doomed[] = trim($s);
					$totalDoomed += 1;
				}
				$doomedstring = join(',', $doomed);
				$action = "Will DELETE $doomedstring.";
				if($_GET['go']) {
					deleteTable('tblappointment', "appointmentid IN ($doomedstring)", 1);
					//$action .=  "deleteTable('tblappointment', [appointmentid IN ($doomedstring)]";
					$action .= ' '.mysql_affected_rows().' visits deleted.';
					if(mysql_error()) $action .= '<font color=red>'.mysql_error().'</font>';
				}
				
			}
			echo "[".count($ids)."] ids: (".join(', ',$ids).") $action<font color=green> $drop</font><br>$dup<p>";
		}
	}
	if($dupIds) {
		$dupcreations = fetchAssociations("SELECT appointmentid, created FROM tblappointment WHERE appointmentid IN (".strip_tags(join(', ',$dupIds)).") ORDER BY created");
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
		$mods =  array();
		if(strpos($id, '*')) $mods[] = "CANCELED";
		if(strpos($id, '#')) $mods[] = "MODIFIED";
		$id = substr($id,0, $bracket);
		$title = "TITLE = 'Appointment is ".join(' and ', $mods)."'";
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