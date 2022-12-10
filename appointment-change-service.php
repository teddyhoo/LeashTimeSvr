<? //appointment-change-service.php
// id may be one id or id1,id2,...
// called by calendar-package-irregular.php
// servicecode must be > 0
// all appts belong to same client

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "invoice-fns.php";
require_once "pet-fns.php";
require_once "service-fns.php";
require_once "provider-memo-fns.php";
locked('o-');

// for each appointment, find the provider's rate and reassign to the provider
$appts = fetchAssociations("SELECT providerptr, clientptr, date, pets, appointmentid, servicecode, timeofday, adjustment, completed, canceled FROM tblappointment WHERE appointmentid IN ({$_GET['ids']})");
$servicecode = $_GET['servicecode'];
$standardRates = getStandardRates();  // servicecode => (value=>, ispercentage=>)
$standardCharges = getStandardCharges();  // servicecode => (value=>, ispercentage=>)
$client = $appts[0]['clientptr'];
$clientCharges = getClientCharges($client);
$allPets = getClientPetNames($client);
//$times = getTimeOffDates($prov);
$failures =  array();
$successes = array();
$allRates = array();
foreach($appts as $i=>$appt) {
	if($appt['completed'] || $appt['canceled']) {
		$failures[] = $appt['appointmentid'];
		continue;
	}
	$successes[] = $appt['appointmentid'];
	$prov = $appt['providerptr'];
	if(!isset($allRates[$prov])) $allRates[$prov] = $prov ? getProviderRates($prov) : array();
	$providerRates = $allRates[$prov];
	$mods = array('servicecode'=>$servicecode);
	$charge = calculateServiceCharge($client, $servicecode, $appt['pets'], $allPets, $clientCharges, $standardCharges);
	$mods['charge'] = $charge;
	$mods['rate'] = calculateServiceRate($prov, $servicecode, $appt['pets'], $allPets, $charge+$appt['adjustment'], $providerRates, $standardRates);
	updateTable('tblappointment', withModificationFields($mods), "appointmentid={$appt['appointmentid']}", 1);
	resetAppointmentDiscountValue($appt['appointmentid'], $charge);	// discountAppointment discounts charge only, not charge+adjustment
	/* ADDED 2013-11-12
	require_once "invoice-fns.php";
	recreateAppointmentBillable($appt['appointmentid']);
	// update nonrecurring package price
	if(!$oldAppt['recurringpackage']) {
		$packageid = findCurrentPackageVersion($oldAppt['packageptr'], $oldAppt['clientptr'], false);
		$price = calculateNonRecurringPackagePrice($packageid, $oldAppt['clientptr']);
		updateTable('tblservicepackage', array('packageprice'=>$price), "packageid = $packageid");
	}
	*/
}

foreach($successes as $id) {
	// supersedeAppointmentBillable($id); No reason tp supersede billable
	$appt = getAppointment($id, false, true, true);
	logAppointmentStatusChange($appt, 'EZ Schedule service change');
	if(!((int)($appt['billpaid'] + $appt['providerpaid']))) {
		if($appt['payableid']) deleteTable('tblpayable', "payableid = {$appt['payableid']}", 1);
	}
}

if($failures) echo "MESSAGE:FAILURE-Could not change ".count($failures)." visits because they are marked complete or canceled.";
else echo "MESSAGE:Done!";