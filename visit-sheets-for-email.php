<? //visit-sheets-for-email.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "appointment-fns.php";
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

$clientDetails = getClientDetails($clientIds, array('googleaddress', 'address', 'lname'));

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
	<style type="text/css">
		v\:* {
			behavior:url(#default#VML);
		}
	</style>

	<?
	$date = isset($date) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
	$providerNames = getProviderShortNames();

	?>
	<table width=100%>
	<tr bgcolor="#CBE5E9"><td colspan=2><font size=+1><b>Sitter <?= $providerNames[$provider]."'s visits" ?></b></font></td>
	<td align=right><?= longerDayAndDate(strtotime($date)) ?></td>
	</table>
	<p>
	<?
	$secureMode = $_SESSION['preferences']['secureClientInfo'];
	$noKeyIDsAtAll = $_SESSION['preferences']['secureClientInfoNoKeyIDsAtAll'] || !$_SESSION['secureKeyEnabled'];
	$noAlarmDetailsAtAll = $_SESSION['preferences']['secureClientInfoNoAlarmDetailsAtAll'];
	
	foreach($clientDetails as $i => $detail) {
		if($detail['clientid']) // why not?!
			$clientDetails[$i]['label'] = clientLabel($detail);
	}
	
	providerAppointmentsTableForEmail($providerAppts, $clientDetails, $noKeyIDsAtAll);

	if(!$noAlarmDetailsAtAll) {
		echo "<center><h2>Alarm Info</h2></center>";

		foreach($clientIds as $id) {
			$data = getClient($id);
			$clabel = $secureMode ? $id : $clientDetails[$id]['clientname'];
			echo "<table><tr><td colspan=2>Client: $clabel:</td></tr>";
			dumpAlarmTable(true, false, $data);//dumpAlarmTable(true);
			echo "</table>";
		}
	}
	
	$cssPrefix = $emailingVisitSheet ? globalURL('') : '';
}
	?>
	<div id="directions"></div>
	<?

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