<? // service-recurring-retrofit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "zip-lookup.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "invoice-fns.php";

require_once "gui-fns.php";
include "weekday-grid.php";
include "petpick-grid.php";
include "time-framer-mouse.php";

// Determine access privs
$locked = locked('o-');
if(!$_SESSION["staffuser"]) {
	include "frame.html";
	echo "<h2>Insufficient access rights.</h2>";
	include "frame-end.html";
}

$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract(extractVars('packageid,startdate,servicecode,adjustment,bonus', $_REQUEST));

$package = getRecurringPackage($packageid);
$client = $package['clientptr'];
if($_POST) {
	$serviceNames = getServiceNamesById();
	$provNames = getProviderShortNames();
	$allPets = getClientPetNames($client);
	$startdate = date('Y-m-d', strtotime($startdate));
	$history = findPackageIdHistory($packageid, $client, 1);
	$history = join(',',$history);
	// find all package visits, unpaid, uninvoiceed, completed or incomplete or canceled, custom or otherwise
	$sql =  //getPackageAppointments
		"SELECT tblappointment.*, billableid 
			FROM tblappointment
			LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment' 
			LEFT JOIN relinvoiceitem ON billableptr = billableid AND superseded = 0
			WHERE packageptr IN ($history) AND date >= '$startdate'
				AND billableptr IS NULL
				AND (billableid IS NULL OR tblbillable.paid = 0)";
	$appts = fetchAssociations($sql);
	//echo count($appts).' appts.';
	//foreach($appts as $a) echo "[{$a['packageptr']}] {$a['date']} {$a['time']} {$serviceNames[$a['servicecode']]} {$provNames[$a['providerptr']]}<br>";
	$clientCharges = getClientCharges($client);
	$standardCharges = getStandardCharges();
	$standardRates = getStandardRates();
	$allProviderRates = array();
//echo "$history<p>";	
	foreach($appts as $appt) {
		if(!$allProviderRates[$appt['providerptr']]) $allProviderRates[$appt['providerptr']] = getProviderRates($appt['providerptr']);
		$providerRates = $allProviderRates[$appt['providerptr']];
		$charge = calculateServiceCharge($client, $servicecode, $appt['pets'], $allPets, $clientCharges, $standardCharges);
		$rate = calculateServiceRate($appt['providerptr'], $servicecode, $appt['pets'], 
								$allPets, $charge+$adjustment, $providerRates, $standardRates);
		$vals['servicecode'] = $servicecode;
		$vals['adjustment'] = $adjustment;
		$vals['bonus'] = $bonus;
		$vals['charge'] = $charge;
		$vals['rate'] = $rate;
		$vals['modified'] = 1;
		$vals = withModificationFields($vals);
		updateTable('tblappointment', $vals, "appointmentid = {$appt['appointmentid']}", 1);
		if($appt['billableid']) {
			recreateAppointmentBillable($appt['appointmentid']);
		}
		screenLog("{$appt['date']} {$appt['timeofday']} [{$appt['appointmentid']}] Sitter: {$provNames[$appt['providerptr']]} Service: {$serviceNames[$servicecode]} "
							."Price: $charge Adj: $adjustment Rate: $rate Bonus: $bonus Pets: {$appt['pets']}");
	}
	$package['previousversionptr'] = $package['packageid'];
//echo print_r($package,1)."<br>";
	$package['effectivedate'] = $startdate;
	$newpackageid = newPackage('tblrecurringpackage', $package, $showerrors=1);
	// retire old package
	if($newpackageid) {
		updateTable('tblrecurringpackage', withModificationFields(array('current'=>0)), "clientptr = $client AND packageid != $newpackageid", 1);
		updateTable('tblservice', withModificationFields(array('current'=>0)), "clientptr = $client AND packageptr != $newpackageid", 1);
	}
	echo screenLog("New package [$newpackageid] services:");
	// create all new services
	$services = getPackageServices($packageid);
	foreach($services as $service) {
		unset($service['serviceid']);
		if(!$allProviderRates[$service['providerptr']]) $allProviderRates[$service['providerptr']] = getProviderRates($service['providerptr']);
		$providerRates = $allProviderRates[$service['providerptr']];
		$charge = calculateServiceCharge($client, $servicecode, $service['pets'], $allPets, $clientCharges, $standardCharges);
		$rate = calculateServiceRate($service['providerptr'], $servicecode, $service['pets'], 
								$allPets, $charge+$adjustment, $providerRates, $standardRates);
		$service['servicecode'] = $servicecode;
		$service['adjustment'] = $adjustment;
		$service['bonus'] = $bonus;
		$service['charge'] = $charge;
		$service['rate'] = $rate;
		$service['packageptr'] = $newpackageid;
		$service['servicecode'] = $servicecode;
		$service['current'] = 1;
		$serviceid = insertTable('tblservice', $service, 1);
echo screenLog("$serviceid: ".print_r($service,1));
	}
}









$clientDetails = getClient($client);

$pageTitle = "Retrofit the Schedule";
include "frame.html";
?>
<form name='retrofitform' method='POST'>
<?
hiddenElement('packageid', $packageid);
calendarSet('Make Changes Starting On:', 'startdate', ($startdate ? date('m/d/Y', strtotime($startdate)) :  ''));
echo "<p>";
$serviceSelections = array_merge(array('Select a Service'=>''), getServiceSelections());
selectElement('', "servicecode", $servicecode, $serviceSelections, "updatePrice()");
echo "<p>Price (not counting additional pets): <span id='price'></span>";
echo "<p>";
labeledInput('Adjustment: ', 'adjustment', $adjustment);
echo "<p>Rate: <span id='rate'>Will vary by provider.</span>";
echo "<p>";
labeledInput('Bonus: ', 'bonus', $bonus);
echo "<p>";
echoButton('', 'Retrofit Schedule', 'submitChanges()');
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
function updatePrice() {
	var service = document.getElementById('servicecode');
	service = service.options[service.selectedIndex].value;
	var price = lookUpClientServiceCharge(service, client);
	document.getElementById('price').innerHTML = price ? '$ '+price : '';
}

var client = <?= $client ?>;

var clientCharges = <?= $client ? getClientChargesJSArray($client) : '[]' ?>;

function submitChanges() {
	setPrettynames('startdate', 'Start Date', 'servicecode', 'Service', 'adjustment', 'Adjustment', 'bonus', 'Bonus');
  if(MM_validateForm(
		  'startdate', '', 'R',
		  'startdate', '', 'isDate',
		  'servicecode', '', 'R',
		  'adjustment', '', 'FLOAT',
		  'bonus', '', 'FLOAT'))
		  document.retrofitform.submit();
}	


<?
dumpServiceRateJS();
dumpPopCalendarJS();
?>
</script>
<?

include "frame-end.html";
