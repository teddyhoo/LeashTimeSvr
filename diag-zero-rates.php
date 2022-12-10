<? // diag-zero-rates.php

// ex schedule date range and creation date and mod date and client
// diagnostic to show zero rate appts per database


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";
require_once "pet-fns.php";
require_once "provider-fns.php";


$sql =
	"SELECT *, concat(fname, ' ', lname) as pname 
	FROM `tblappointment`
	LEFT JOIN tblprovider ON providerid = providerptr
	WHERE `rate`+ifnull(bonus,0) = 0.0 AND rate = 0.0";
	
$appts = fetchAssociations($sql);


//print_r($appts);
echo "<h2>Zero-rate appts</h2>";
echo "Appointments where Rate is zero and Bonus is zero.<p>";
if($nonzero = $_REQUEST['nonzero']) echo "<a href='diag-zero-rates.php'>Show All Zero Rate Appointments</a>";
else echo "<a href='diag-zero-rates.php?nonzero=1'>Show Only Appointments with non-Zero Expected Rates</a>";
echo "<p>$db<p>".count($appts)." appts</h2>";

$standardRates = getStandardRates();
$clients = fetchKeyValuePairs("SELECT clientid, concat_ws(' ', fname, lname) FROM tblclient");
$services = getServiceNamesById();
echo "<style>.red {color:red;}</style>";
echo "<table border=1><tr><th>ID<th>Date<th>Service<th>Client<th>Provider<th><font color=red>Exp Rate</font><th>Chg<th>Adj<th>Created<th>Modified";


foreach($appts as $appt) {
	if(!isset($allRates[$appt['providerptr']])) $allRates[$appt['providerptr']] = getProviderRates($appt['providerptr']);
	$providerRates = $allRates[$appt['providerptr']];

	if(!isset($clientPets[$appt['clientptr']])) $clientPets[$appt['clientptr']] = getClientPetNames($appt['clientptr']);
	$clientPets = $clientPets[$appt['clientptr']];

	$rate = calculateServiceRate($appt['providerptr'], $appt['servicecode'], $appt['pets'], $clientPets[$appt['clientptr']], $appt['charge'], $providerRates, $standardRates);
	if(!$nonzero || $rate) 
		echo "<tr><td><a target=appt href='appt-analysis.php?id={$appt['appointmentid']}'>{$appt['appointmentid']}</a>"
			 ."<td>{$appt['date']}"
	     ."<td>{$services[$appt['servicecode']]}<td>{$clients[$appt['clientptr']]}<td>{$appt['pname']}<td class=red>$rate"
	     ."<td>{$appt['charge']}<td>{$appt['adjustment']}<td>{$appt['created']}<td>{$appt['modified']}</tr>";
	
	//echo "Appt: <a target=appt href='appt-analysis.php?id={$appt['appointmentid']}'>{$appt['appointmentid']}</a> ({$appt['pname']}): \$ $rate<br>";
}
