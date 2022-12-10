<?
// package-preview.php
// show a calendar of the appointments which will be set up for this package

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "contact-fns.php";
require_once "service-fns.php";

require_once "client-schedule-fns.php";
require_once "day-calendar-fns.php";

// Determine access privs
$locked = locked('o-');
extract($_REQUEST);
$recurring = !isset($enddate) && !$onedaypackage;

function simulateAppointments($recurring) {
	extract($_REQUEST);
	$package = $_REQUEST;
	
	$services = saveServices(99, $recurring, 'simulation');
	if($recurring) preprocessRepeatingPackage($package);
	else preprocessNonrepeatingPackage($package);
	return createScheduleAppointments($package, $services, $recurring, null, 'simulation');
}

$appts = simulateAppointments($recurring);
/*if(!$recurring && $_SESSION['surchargesenabled']) {
	require_once "surcharge-fns.php";
	$package = $_REQUEST;
	$package['clientptr'] = $client;
	$surcharges = createScheduleAutoSurcharges($package, $simulation=true, $simulatedAppts=$appts);
}	
*/
include "frame-bannerless.php";
?>
<h2><?= $recurring ? 'Ongoing' : '' ?> Service Visits Preview for <?= $clientName ?></h2>

<?
if($appts) clientCalendarTable($appts);
else echo "<h3><i>None</i></h3>";


