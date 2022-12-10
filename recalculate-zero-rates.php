<? // recalculate-zero-rates.php

// ex schedule date range and creation date and mod date and client
// diagnostic to show zero rate appts per database


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "pet-fns.php";

$today = date('Y-m-d');
$sql =
	"SELECT *, concat(fname, ' ', lname) as pname 
	FROM `tblappointment`
	LEFT JOIN tblprovider ON providerid = providerptr
	WHERE `rate`+ifnull(bonus,0) = 0.0 AND rate = 0.0"; //date > '$today' AND 
	
$appts = fetchAssociations($sql);

//print_r($appts);
echo "<p>$db<p>".count($appts)." appts<p>";

$standardRates = getStandardRates();



foreach($appts as $appt) {
	if(!isset($allRates[$appt['providerptr']])) $allRates[$appt['providerptr']] = getProviderRates($appt['providerptr']);
	$providerRates = $allRates[$appt['providerptr']];

	if(!isset($clientPets[$appt['clientptr']])) $clientPets[$appt['clientptr']] = getClientPetNames($appt['clientptr']);
	$clientPets = $clientPets[$appt['clientptr']];

	$rate = calculateServiceRate($appt['providerptr'], $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
	if($rate) {
		updateTable('tblappointment', array('rate'=>$rate), "appointmentid = {$appt['appointmentid']}", 1);
		echo "Appointment <a target=appt href='appt-analysis.php?id={$appt['appointmentid']}'>{$appt['appointmentid']}</a> rate set to [$rate]<br>";
	}
	//echo "Appt: <a target=appt href='appt-analysis.php?id={$appt['appointmentid']}'>{$appt['appointmentid']}</a> ({$appt['pname']}): \$ $rate<br>";
}
