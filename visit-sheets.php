<? //visit-sheets.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "appointment-fns.php";
require_once "GoogleMapAPI.class.php";
require_once "visit-sheet-fns.php";
require_once "key-fns.php";

locked('vc');

extract($_REQUEST);

if(userRole() == 'p' && $_SESSION["providerid"] != $provider) {
  echo "<h2>Insufficient rights to view this page..<h2>";
  exit;
}

$date = isset($date) && $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

if(userRole() == 'p' && $_SESSION['preferences']['providersScheduleRetrospectionLimit']) {
	$earliestDateAllowed = strtotime("-{$_SESSION['preferences']['providersScheduleRetrospectionLimit']} days", strtotime(date('Y-m-d')));
	$tooEarly = strtotime($date) < $earliestDateAllowed;
	if($tooEarly) {
		echo "<h2>Visits before ".shortNaturalDate($earliestDateAllowed)." are not viewable.<h2>";
  	exit;
	}
}

$providerAppts = getProviderDayAppointments($provider, $date);

$clientIds = array();
foreach($providerAppts as $appt) $clientIds[] = $appt['clientptr'];
$clientIds = array_unique($clientIds);

$clientDetails = getClientDetails($clientIds, array('googleaddress', 'address'));

// Add client addresses to appointments
foreach($providerAppts as $key => $appt) $providerAppts[$key]['address'] = $clientDetails[$appt['clientptr']]['address'];
/*$clientAddresses = fetchAssociationsKeyedBy(
	"SELECT clientid, street1, street2, city, state FROM tblclient WHERE clientid in ($clientIds)", 'clientid');
foreach($providerAppts as $appt) {
	$add = array();
	foreach(array('street1','street2','city','state','zip') as $f)
	  $add[$f] = $clientAddresses[$appt['clientptr']][$f];
	$appt['address'] = oneLineAddress($addr);
}	*/


if(!$providerAppts) {
	if(!$emailingVisitSheet) {
		require_once "frame-bannerless.php";
		echo "<h2>There are no visits on ".longDayAndDate(strtotime($date))." to display</h2>";
		exit;
	}
	else $stopWithoutExiting = true;
}

if(!$stopWithoutExiting) {
	//print_r($providerAppts);exit;

	//$itinerary = getAddressList($providerAppts, $clientDetails);
	//$itinerary = 'from: '.join(' to: ', $itinerary);
	//echo $itinerary;exit;

	$useKeyDescriptions = $_SESSION['preferences']['mobileKeyDescriptionForKeyId'];
	
	foreach(getProviderKeys($provider) as $key) {
		$label = '';
		for($i=1;array_key_exists('possessor'.$i, $key);$i++)
			if($key['possessor'.$i] == $provider) $label = '-'.sprintf("%02d", $i);
		$clientDetails[$key['clientptr']]['keyLabel'] = sprintf("%04d", $key['keyid']).$label;
		$clientDetails[$key['clientptr']]['keyHook'] = $key['bin'];
		if($useKeyDescriptions) $clientDetails[$key['clientptr']]['keyLabel'] = $key['description'];

	}

	foreach($clientDetails as $clientptr => $details) {
		if(!isset($details['keyLabel'])) {
			$clientKeys = getClientKeys($clientptr);
			$clientDetails[$clientptr]['keyLabel'] = $clientKeys ? sprintf("%04d", $clientKeys[0]['keyid']) : 'No key found';
			if($useKeyDescriptions) $clientDetails[$clientptr]['keyLabel'] = $clientKeys[0]['description'];
		}
		if($details['keyHook']) $clientDetails[$clientptr]['keyLabel'] .= '<br>Hook: '.$details['keyHook'];
	}


	?>
	<script language='javascript' src='visit-sheet.js'></style>

	<script language='javascript'>
	document.onmousedown= function () {if(pb.style.display!='inline') pb.style.display='inline'};
	</script>
	<style type="text/css">
		v\:* {
			behavior:url(#default#VML);
		}
	</style>

	<?
	$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
	$providerNames = getProviderShortNames();

	if(isset($summaryOnly) && $summaryOnly) $buttonLabel = "Print this Visit Sheet Summary";
	else $buttonLabel = "Print these Visit Sheets";
	?>
	<table class='topline' width=100%><tr>
	<td>Sitter: <?= $providerNames[$provider]."'s visits" ?> <? if(!$suppressVisitSheetPrintLink) echoButton('pb', $buttonLabel, 'pb.style.display="none";window.print()'); ?></td>
	<td align=right><?= longerDayAndDate(strtotime($date)) ?></td>
	</table>
	<p>
	<?
	$secureMode = $_SESSION['preferences']['secureClientInfo'];
	$noKeyIDsAtAll = $_SESSION['preferences']['secureClientInfoNoKeyIDsAtAll'] || !$_SESSION['secureKeyEnabled'];
	$noAlarmDetailsAtAll = $_SESSION['preferences']['secureClientInfoNoAlarmDetailsAtAll'];
	providerAppointmentsTable($providerAppts, $clientDetails, $noKeyIDsAtAll);

	if(!$noAlarmDetailsAtAll) {
		echo "<center><h2>Alarm Info</h2></center>";

		foreach($clientIds as $id) {
			$data = getClient($id);
			$clabel = $secureMode ? $id : $clientDetails[$id]['clientname'];
			echo "<table><tr><td colspan=2>Client: $clabel:</td></tr>";
			dumpAlarmTable(true);
			echo "</table>";
		}
	}
	
	$cssPrefix = $emailingVisitSheet ? globalURL('') : '';
}
	?>
	<div id="directions"></div>
	<?
	if(isset($summaryOnly) && $summaryOnly) {
	?>	
	<html>
	<head><title></title>
	<style>
	.maplabel {color:black;font-size:12px;}
	</style>
	<link rel="stylesheet" href="<?= $cssPrefix ?>style.css" type="text/css" /> 
	<link rel="stylesheet" href="<?= $cssPrefix ?>pet.css" type="text/css" />
	<style>
	.topline td {font-size: 18px;}
	.dataCell {
		font-size: 1.08em; 
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
	}
	.jobstable {background: white;}
	.jobstablecell {
			font-size: 1.05em; 
			padding-bottom: 4px; 
			border-collapse: collapse;
			vertical-align: top;
			border-top: solid black 1px;
		}
	.dateRow {background: yellow;font-weight:bold;text-align:center;border:solid black 1px;}
	</style>
	<?	exit;
	}


	echo "<hr style='page-break-after: always;'>";
	/* PROBLEM: GoogleMapAPI supports just one map per page.  */
	$suppressVisitSheetPrintLink = true;
	foreach($clientIds as $index => $id) {
		if($index) echo "<hr style='page-break-after: always;'>";
		$mapId = "map_$index";
		include "visit-sheet.php";
	}

	/* PROBLEM: Framesets are screen oriented (% of page) or pixel-oriented
	$perc = array();
	for($i=0;$i<count($clientIds);$i++) $perc[] = '100%';
	$perc = join(',', $perc);
	echo "<frameset rows='$perc'>\n";
	foreach($clientIds as $index => $id) {
		//if($index) echo "<hr style='page-break-after: always;'>";
		$mapId = "map_$index";
		echo "<frame src='visit-sheet.php?id=$id&provider=$provider&date=$date'/>\n";
	}
	echo "</frameset>\n";
	*/
}