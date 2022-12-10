<? // appointment-explain-rate.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "service-fns.php";
require_once "pet-fns.php";

locked('o-');

$appt = getAppointment($_REQUEST['id']);
$allPets = getClientPetNames($appt['clientptr']);

explainRate($appt['providerptr'], $appt['servicecode'], $appt['pets'], $allPets, 
						$appt['charge'], $providerRates=null, $standardRates=null);

function explainRate($provider, $service, $pets, $allPets, $totalCharge, $providerRates=null, $standardRates=null) {
	$explanation = serviceRateExplanation($provider, $service, $pets, $allPets, $totalCharge, $providerRates, $standardRates);
	$ex = "{$explanation['baseCharge']}<p>"
				."{$explanation['extraPetChargePerPet']}<p>"
				."{$explanation['extraPets']}<p>"
				."{$explanation['totalCharge']}<hr>"
				."{$explanation['rateApplied']}<p>";
	if($explanation['rawNumExtraPets']) {
		$ex .= "{$explanation['standardExtraPetRate']}<p>";
		if($explanation['customExtraPetRate']) $ex .= "{$explanation['customExtraPetRate']}<p>";
	}
	foreach((array)$explanation['rateFinal'] as $line) $ex .= "$line<p>";
	echo "Rate Calculation:<p>$ex";
}
