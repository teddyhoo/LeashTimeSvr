<? //service-irregular-create.php

// called from request editor
// request=client request id

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "service-fns.php";
require_once "pet-fns.php";
require_once "request-fns.php";
require_once "client-sched-request-fns.php";
locked('o-');
extract(extractVars('request,chosenprovider,officenotes', $_REQUEST));
$source = getClientRequest($request);
$dateTimeNow = longestDayAndDateAndTime();
$creationNote = "$dateTimeNow: EZ Schedule created.";
$officenotes = $officenotes ? "$officenotes\n$creationNote" : $creationNote;
updateTable('tblclientrequest', array('resolved'=>1, 'resolution'=>'honored', 'officenotes' => $officenotes), "requestid=$request", 1);
//$source = getClientRequest($request);
$schedule = scheduleFromNote($source['note']);
//if(mattOnlyTEST()) {print_r($schedule);exit;}
$package['startdate'] = date('Y-m-d', strtotime($schedule['start']));
$package['enddate'] = date('Y-m-d', strtotime($schedule['end']));
$package['client'] = $source['clientptr'];
$package['packageprice'] = $schedule['totalCharge'];
$package['prepaid'] = $_SESSION['preferences']['schedulesPrepaidByDefault'];
$package['preemptrecurringappts'] = 0;
$package['billingreminders'] = $_SESSION['preferences']['sendBillingReminders'];

$lines = explode("\n", $source['note']);
$package['notes'] = count($lines) > 2 ? urldecode($lines[2]) : '';
$packageid = saveNewIrregularPackage($package);
$day = $package['startdate'];
$clientPets = getClientPetNames($source['clientptr']);


if($schedule['services']) foreach($schedule['services'] as $i => $dayServices) { // NEVER HAPPENS! <== HUH?!
	$clientCharges = getClientCharges($package['client']);
	$standardCharges = getStandardCharges();
	$standardRates = getStandardRates();
	if($dayServices) {
		foreach($dayServices as $newTask) {
if($_SESSION['preferences']['replaceAllPetsInEZScheduleAutoCreation'] && ($newTask['pets'] == 'All Pets'))
	$newTask['pets'] = $clientPets ? $clientPets : 'All Pets'; // allow All Pets anyway if client has no pets

//echo print_r($newTask, 1).'<br>';					
			$newTask['clientptr'] = $package['client'];
			$newTask['providerptr'] = $chosenprovider ? $chosenprovider : '0';
			$newTask['serviceid'] = '0';
			$newTask['packageptr'] = $packageid;
			$newTask['charge'] = calculateServiceCharge($package['client'], $newTask['servicecode'], $newTask['pets'], $clientPets, $clientCharges, $standardCharges);
			$newTask['rate'] = calculateServiceRate($newTask['providerptr'], $newTask['servicecode'], $newTask['pets'], $clientPets, $newTask['charge'], null, $standardRates);
			$appt = createAppointment(false, null, $newTask, strtotime($day));  // NEVER HAPPENS!
			$totalCharge += $newTask['charge'];
			if($_SESSION['surchargesenabled']) {
				require_once "surcharge-fns.php";
				updateAppointmentAutoSurcharges($appt);  // NEVER HAPPENS!
			}
		}
		if(!$package['packageprice'])
			updateTable('tblservicepackage', array('packageprice'=>$totalCharge), "packageid = $packageid", 1);
	}
	$day = date('Y-m-d', strtotime('+1 day', strtotime($day)));
}

//$url = "\"calendar-package-irregular.php?packageid=$packageid&primary=\"";
?>
<script language='javascript' src="common.js"></script>
<script language='javascript'>
//openConsoleWindow("schedulepreview", <?= $url ?>, 900,700);
document.location.href="service-irregular.php?packageid=<?= $packageid?>";
</script>
