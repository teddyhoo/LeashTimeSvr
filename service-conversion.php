<?
// service-conversion.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "service-fns.php";

locked('o-');
if(!staffOnlyTEST()) {
	echo "This page is for LeashTime STaff use only.";
	exit;
}

// Offer a calendar input: First day to start conversions (past or present)
// Side by side:
// Offer a pulldown for "Old" service types
// -- when selected, for dates on/after start date show number of 
// ---- nonrecurring visits of this type
// ---- recurring visits of this type
// ---- active current nonrecurring schedules with this type
// ---- active current recurring schedules with this type
// -- show default rate/charge
// -- show sitters with custom rate for this (hover action: show calculated rate)
// -- show clients with custom rate for this (hover action: show calculated charge)

// Offer a pulldown for "new" service types
// -- when selected, for dates on/after start date show number of 
// ---- nonrecurring visits of this type
// ---- recurring visits of this type
// ---- active current nonrecurring schedules with this type
// ---- active current recurring schedules with this type
// -- show default rate/charge
// -- show sitters with custom rate for this (hover action: show calculated rate)
// -- show clients with custom rate for this (hover action: show calculated charge)

//<hr>
/*
[ ] change non-recurring schedules
[ ] change recurring schedules

[ Convert Services  ] */
$allProviderIds = fetchCol0("SELECT providerid FROM tblprovider");
$providerRates = getMultipleProviderRates($allProviderIds);
$standardRates = getStandardRates();
foreach($standardRates as $index => $rate) $standardRates[$index]['rate'] = $standardRates[$index]['defaultrate'];
$startDate = $startDate ? date('m/d/Y', strtotime($startDate)) : '';
$startDateDB = $startDate ? date('Y-m-d', strtotime($startDate)) : '';
$oldTypeId = $_REQUEST['oldTypeId'];
if($oldTypeId) $oldType = fetchFirstAssoc("SELECT * FROM tblservicetype WHERE servicetypeid = $oldTypeId", 1);
if($newTypeId) $newType = fetchFirstAssoc("SELECT * FROM tblservicetype WHERE servicetypeid = {$_REQUEST['newTypeId']}", 1);
if($_POST['convert']) {
	$oldTypeId = $_POST['oldTypeId'];
	$newType = fetchFirstAssoc("SELECT * FROM tblservicetype WHERE servicetypeid = {$_POST['newTypeId']}", 1);
	$summary = array();

	if($convertRecurring) {
		$appts = fetchAssociationsKeyedBy(
			"SELECT * 
				FROM tblappointment 
				WHERE recurringpackage = 1
					AND servicecode = $oldTypeId
					AND date >= '$startDateDB'", 
					'appointmentid', 1);
		$recurringVisitsConverted = convertAppts($appts);
		$recurringPackids = fetchCol0(
			"SELECT packageid 
				FROM tblrecurringpackage
				WHERE current 
					AND (cancellationdate IS NULL OR cancellationdate >= '$startDateDB')", 1);
		$recurringServices = fetchAssociations(
			"SELECT * 
				FROM tblservice
				WHERE servicecode = $oldTypeId
					AND packageptr IN (".join(',', $packids).")", 1);
		foreach($recurringServices as $serv) $recurringPackagesAffected[] = $serv['packageptr'];
		$recurringPackagesAffected = count(array_unique($recurringPackagesAffected));
		convertServices($recurringServices, 1);
		$summary[] = "Recurring visits converted: ".nummy($recurringVisitsConverted);
		$summary[] = "Recurring schedules converted: ".nummy($recurringPackagesAffected);

	}
	if($convertNonrecurring) {
		$appts = fetchAssociationsKeyedBy(
			"SELECT * 
				FROM tblappointment 
				WHERE recurringpackage = 0
					AND servicecode = $oldTypeId
					AND date >= '$startDateDB'", 
					'appointmentid', 1);
		$nonrecurringVisitsConverted = convertAppts($appts);
		$nonrecurringPackids = fetchCol0(
			"SELECT packageid 
				FROM tblservicepackage
				WHERE current 		
					AND (startdate >= '$startDateDB' OR enddate >= '$startDateDB')
					AND (cancellationdate IS NULL OR cancellationdate >= '$startDateDB')", 1);
		$nonrecurringServices = fetchAssociations(
			"SELECT * 
				FROM tblservice
				WHERE servicecode = $oldTypeId
					AND packageptr IN (".join(',', $nonrecurringPackids).")", 1);
		foreach($recurringServices as $serv) $nonrecurringPackagesAffected[] = $serv['packageptr'];
		$nonrecurringPackagesAffected = count(array_unique($nonrecurringPackagesAffected));
		convertServices($nonrecurringServices, 1);
		$summary[] = "Nonrecurring visits converted: ".nummy($nonrecurringVisitsConverted);
		$summary[] = "Nonrecurring schedules converted: ".nummy($nonrecurringPackagesAffected);
	}
	$summary = join('<br>', $summary);
}

$pageTitle = "Service Conversion:";

include "frame.html";
// ***************************************************************************
?>
<style>
.OLDTYPE { background-color: orange; }
.NEWTYPE { background-color: palegreen; }
.inactivetype { font-style: italic; }
</style>
<?
echo "<h1>{$_SESSION['preferences']['bizName']}</h1>";
if($summary) echo "<span class='tiplooks'>$summary</span><hr>";
?>
<form method='POST'>
<table width=90% border=1 bordercolor=black>
<tr><td colspan=2><? 	
echoButton($id, 'Convert', $onClick='checkAndPost()');
echo "  ";
calendarSet('Starting:', 'startDate', $start, null, null, true);
echo "  Convert: ";
labeledCheckbox('Recurring schedules', 'convertRecurring', $convertRecurring, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
echo "  ";
labeledCheckbox('Nonrecurring schedules', 'convertNonrecurring', $convertNonrecurring, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
?>
</td></tr>
<tr>
<td class=OLDTYPE>
<?
	serviceSelectElement('Old Service Type:', 'oldTypeId', $oldTypeId);
?>
</td>
<td class=NEWTYPE>
<?
	serviceSelectElement('New Service Type:', 'newTypeId', $newTypeId);
?>
</td>
</table>
</form>
<div style='height:300px;'></div>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function checkAndPost() {
	if(!MM_validateForm('startDate', '', 'R', 'startDate', '', 'isDate', 'oldTypeId', '', 'R', 'newTypeId', '', 'R'))
		return;
	alert('Bang!');
}
<? dumpPopCalendarJS(); ?>
</script>

<?
// ***************************************************************************
include "frame-end.html";

function serviceSelectElement($label, $selectName, $value) {
	$snames = getAllServiceNamesById($refresh=1);
	foreach(array_keys($snames) as $id) $types[] = fetchFirstAssoc("SELECT * FROM tblservicetype WHERE servicetypeid = $id", 1);
	$options[] = '<option>-- Choose a service type --';
	foreach($types as $id => $type) {
		$class = $type['active'] ? '' : 'inactivetype';
		$title = $type['active'] ? '' : 'Inactive Service';
		$options[] = "<OPTION value='$id' class='$class'>{$type['label']}</OPTION>";
	}
	$options = join("\n", $options);
	selectElement($label, $selectName, $value, $options, $onChange="serviceSelected($selectName)", $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
}

function nummy($n) { return $n ? $n : 'none'; }

function convertServices(&$services, $recurring) {
	global $newType;
	$recurring = $recurring ? 'Recurring' : 'Nnrecurring';
	foreach($services as $serv) {
		$provider = $serv['providerptr'];
		$serv['servicecode'] = $newType['servicetypeid'];
		$serv['charge'] = $newType['defaultcharge'];
		$serv['rate'] = getRate($provider, $serv);
		updateTable('tblservice', withModificationFields($serv), "serviceid={$serv['serviceid']}", 1);
		logChange($id, 'tblservice', 'm', "$recurring service converted $oldType=>$newType");
		$n++;
	}
	return $n;
}

function convertAppts(&$appts) {
	global $newType;
	foreach($appts as $id => $appt) {
		$oldType = $appt['servicecode'];
		$provider = $appt['providerptr'];
		$appt['servicecode'] = $newType['servicetypeid'];
		$appt['charge'] = $newType['defaultcharge'];
		$appt['rate'] = getRate($provider, $appt);
		$birthmarkparts = explode('_', $appt['birthmark']);
		$appt['birthmark'] = "{$birthmarkparts[0]}_{$newType['servicetypeid']}";
		require_once "invoice-fns.php";
		updateTable('tblappointment', withModificationFields($appt), "appointmentid=$id", 1);
		recreateAppointmentBillable($id);
		logChange($id, 'tblappointment', 'm', "Service converted $oldType=>$newType");
		$n++;
	}
	return $n;
}

function getRate($provider, &$appointment) {
	global $providerRates, $standardRates;
	$clientPets = getPetsForClient($appointment['clientptr']);
//echo "Pets: ";print_r($clientPets);echo "\n<p>";	
//echo "visit: ".print_r($appointment, 1)."<p>";
//echo "provider rates: [$provider] ".print_r($providerRates[$provider], 1)."<p>";
//echo "standard rates: ".print_r($standardRates[$provider], 1)."<p>";
	$newRate = NEWcalculateServiceRate($provider, $appointment['servicecode'], $appointment['pets'], $clientPets, $appointment['charge'], $providerRates[$provider], $standardRates);
//echo "Rate:: ".print_r(serviceRateExplanation($provider, $appointment['servicecode'], $appointment['pets'], $clientPets, $appointment['charge'], $providerRates[$provider], $standardRates), 1);echo "\n<p>";	
	return $newRate;
	/*$rateDesc = $provider && isset($providerRates[$provider][$appointment['servicecode']])
							? $providerRates[$provider][$appointment['servicecode']]
							: $standardRates[$appointment['servicecode']];
	return $rateDesc['ispercentage']
					? $rateDesc['rate'] / 100 * $appointment['charge']
					: $rateDesc['rate'];*/
}

function getPetsForClient($clientid) {
	require_once "pet-fns.php";	
	global $allClientsPets;
	if(!isset($allClientsPets[$clientid])) 
		$allClientsPets[$clientid] = getClientPetNames($clientid);
	return $allClientsPets[$clientid];
}
