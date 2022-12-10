<? // package-analysis.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "credit-fns.php";
require_once "service-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "appointment-fns.php";
require_once "pet-fns.php";

extract($_REQUEST);

if($appt) {
	$id = $appt;
	$appt = getAppointment($appt, 1);
	echo "Charge: {$appt['charge']}".($appt['adjustment'] ? " + [{$appt['adjustment']}]" : '');
	echo "<br>Rate: {$appt['rate']}".($appt['bonus'] ? " + [{$appt['bonus']}]" : '');
	require "appointment-history-ajax.php";
	exit;
}



?>
<div style='width:900px;border: solid black 1px;'>
<?
$package = getPackage($id);
$client = getOneClientsDetails($package['clientptr']);
$providers = getProviderNames();
$packageType = $package['irregular'] ? 'EZ' :
							 ($package['monthly'] ? 'Monthly Recurring' :
							 ($package['onedaypackage'] ? 'One Day' :
							 ($package['enddate'] ? 'Nonrecurring' : 'Weekly Recurring')));
$prepaidOrPostPaid = ($package['prepaid'] ? 'PREPAID' : 'Postpaid');
$recurring = !$package['enddate'] && !$package['onedaypackage'];
$current = $package['current'] ? '<font color=green>CURRENT</font>' : '<font color=red>OLD</font>';
echo "$prepaidOrPostPaid <b>$current</b> $packageType Schedule: <b>$id</b> for {$client['clientname']}<br>";

$multipleCurrentRecurringVersions = 
	!$package['enddate'] &&
	($currents = fetchRow0Col0("SELECT COUNT(*) FROM tblrecurringpackage WHERE clientptr = {$package['clientptr']} AND current = 1", 1)) > 1;
if($multipleCurrentRecurringVersions) echo "<font color=red>THERE ARE $currents current recurring schedules for {$client['clientname']}</font>";
?>
<div style='width:800px;border: solid black 1px;'>
<?
echo packageDescriptionHTML($package, null);
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
function userName($userid) {
	$user = getUserByID($userid);
	return $user['fname'].' '.$user['lname']." ($userid)";
}
$package['createdby'] = userName($package['createdby']);
echo "PACKAGE:<br>";
print_r($package);
?>
</div>
<p>SERVICES:
<?
$services = fetchAssociations("SELECT tblservicetype.label, tblservice.*
																FROM tblservice 
																LEFT JOIN tblservicetype ON servicetypeid = servicecode
																WHERE packageptr = $id") ;
foreach($services as $service) {
	$p = $service['providerptr'];
	$service['provider'] = $providers[$p] ? "<font color=green>{$providers[$p]}</font>" : "<font color=red>Unassigned</font>";
?>
<div style='width:800px;border: solid black 1px;'>
<?

print_r($service);
?>
</div>
<?
}
?>
<p>SURCHARGES:
<?
$surcharges = fetchAssociations("SELECT tblsurchargetype.label, tblsurcharge.*
																FROM tblsurcharge 
																LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
																WHERE packageptr = $id") ;
if(!$surcharges) echo "--none--<p>";
else {
	foreach($surcharges as $surcharge) {
		$p = $surcharge['providerptr'];
		$surcharge['provider'] = $providers[$p] ? "<font color=green>{$providers[$p]}</font>" : "<font color=red>Unassigned</font>";
	?>
	<div style='width:800px;border: solid black 1px;'>
	<?

	print_r($surcharge);
	?>
	</div>
	<?
	}
}

echo "<p>Prior Versions:<br>";
$history = findPackageIdHistory($id, $package['clientptr'], $recurring);
if(count($history) == 1) echo "none<br>";
else {
	$table = $recurring ? 'tblrecurringpackage' : 'tblservicepackage';
	$total = $recurring ? 'totalprice' : 'packageprice';
	$dates = fetchAssociationsKeyedBy($sql="SELECT packageid, created, modified, $total FROM $table WHERE packageid IN (".join(',', $history).")", 'packageid');
	echo "<table><tr><th>Created<th>Modified<th>Package<th>Price";
	foreach($history as $version) 
		if($version != $id) echo 
			"<tr><td>".date('m/d/Y', strtotime($dates[$version]['created']))
			."<td>".($dates[$version]['modified'] ? date('m/d/Y', strtotime($dates[$version]['modified'])) : '')
			."<td>".vlink('Package ', $version)
			."<td>".$dates[$version]['totalprice'].'</tr>';
	echo "</table>";
}

echo "<p>Succeeding Versions:<br>";
$histories = findPackageHistories($package['clientptr'], ($recurring ? 'R' : 'N'));
$versionids = array_reverse(array_keys($histories));
foreach($versionids as $version ) {
	if($version == $id) continue;
	$history = $histories[$version];
	$table = $recurring ? 'tblrecurringpackage' : 'tblservicepackage';
	$date = fetchRow0Col0("SELECT created FROM $table WHERE packageid = $version LIMIT 1");
	if(in_array($id, $history)) {
		foreach($history as $i=>$v) {
			if($v == $version) unset($history[$i]);
			else $history[$i] = vlink('', $v);
		}
		echo vlink('Package ', $version).": $date (history: ".join(', ',$history).")</a><br>";
	}
}

function cmpDateTime($a, $b) {
	$r = strcmp($a['date'], $b['date']);
	if($r) return $r;
	return strcmp($a['starttime'], $b['starttime']);
}
	
if($package['enddate']) {


		echo "<p>Show All Appts</p><table><tr><td><table>";
//echo "package: ".print_r(print_r($package,1));	
	$appts = fetchAllAppointmentsForNRPackage($package['packageid']);
	if(!$appts && !getCurrentNRPackage($package['packageid'])) {
		echo "Current version of this package is missing!";
	}
	else {
		usort($appts, 'cmpDateTime');
		$types = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
		$provs = getProviderShortNames();
		foreach($appts as $a) {
			$serviceClass = $a['canceled'] ? "style='background-color:pink'" : '';
			echo "<tr><td valign=top>{$a['date']}<td onclick='showAppt({$a['appointmentid']})' style='color:green'>{$a['timeofday']}<td>[{$a['appointmentid']}]
							<td>{$provs[$a['providerptr']]}<td $serviceClass>{$types[$a['servicecode']]}<td style='color:gray'>{$a['created']}";
		}
		echo "</table></td><td id='apptdetail' valign=top></td></tr></table>";
	}
	
	
	echo "<p>Show All Surcharges</p><table><tr><td><table>";
	$surchs = fetchAllSurchargesForNRPackage($package['packageid']);
	if(!$surchs && !getCurrentNRPackage($package['packageid'])) {
		echo "Current version of this package is missing!";
	}
	else {
		usort($surchs, 'cmpDateTime');
		$types = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
		$provs = getProviderShortNames();
		foreach($surchs as $a) {
			$serviceClass = $a['canceled'] ? "style='background-color:pink'" : '';
			echo "<tr><td valign=top>{$a['date']}<td onclick='showSrcharge({$a['surchargeid']})' style='color:green'>{$a['timeofday']}<td>[{$a['surchargeid']}]
							<td>{$provs[$a['providerptr']]}<td $serviceClass>{$types[$a['surchargecode']]}<td style='color:gray'>{$a['created']}";
		}
		echo "</table></td><td id='apptdetail' valign=top></td></tr></table>";
	}
}
function vlink($prefix, $version) {
	return "<a href='package-analysis.php?id=$version'>$label$version</a>";
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function showAppt(apptid) {
	ajaxGet('package-analysis.php?appt='+apptid, 'apptdetail');
}
</script>