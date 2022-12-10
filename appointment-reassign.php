<? //appointment-reassign.php
// id may be one id or id1,id2,...
// called by calendar-package-irregular.php
// if prov == -1, unassign
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
$appts = fetchAssociations(
	"SELECT providerptr, clientptr, date, pets, appointmentid, servicecode, timeofday, 
					charge+ifnull(adjustment,0) as charge, completed, canceled,
					CONCAT_WS(' ', fname, lname) as clientname
			FROM tblappointment
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE appointmentid IN ({$_GET['ids']})");
$prov = $_GET['prov'];
$providerRates = getProviderRates($prov);
$standardRates = getStandardRates();  // servicecode => (value=>, ispercentage=>)
//$times = getTimeOffDates($prov);
$timeofflabels =  array();
$conflictlabels =  array();
$successes = array();
$allPets = getClientPetNames($appts[0]['clientptr']);
foreach($appts as $i=>$appt) {
	if($prov && providerIsOff($prov, $appt['date'], $appt['timeofday'])) {
	//if(in_array(strtotime($appt['date']), $times)) 
		$timeofflabels[] = 
									"<b>".date('F j', strtotime($appt['date']))."</b> {$appt['timeofday']} "
									.$_SESSION['servicenames'][$appt['servicecode']]
									." for {$appt['clientname']}";
		continue;
	}
	if(detectVisitCollision($appt, $prov)) {
		$conflictlabels[] = 
									"<b>".date('F j', strtotime($appt['date']))."</b> {$appt['timeofday']} "
									.$_SESSION['servicenames'][$appt['servicecode']]
									." for {$appt['clientname']}";
		continue;
	}
	
	if($appt['canceled'] || $appt['completed']) {
		$statusLabels[] = 
									"<b>".date('F j', strtotime($appt['date']))."</b> {$appt['timeofday']} "
									.$_SESSION['servicenames'][$appt['servicecode']]
									." is marked ".($appt['canceled'] ? 'CANCELED' : 'COMPLETE').".";
		continue;
	}
	
	
	$successes[] = $appt['appointmentid'];
	$mods = array();
	$mods['rate'] = calculateServiceRate($prov, $appt['servicecode'], $appt['pets'], $allPets, $appt['charge'], $providerRates, $standardRates);
	$mods['providerptr'] = $prov == -1 ? 0 : $prov;
	if($appt['providerptr'] != $prov) makeClientVisitReassignmentMemo($appt['providerptr'], $appt['clientptr'], $appt['appointmentid']);
	updateTable('tblappointment', withModificationFields($mods), "appointmentid={$appt['appointmentid']}", 1);
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		updateAppointmentAutoSurcharges($appt['appointmentid']);
	}

	if($prov != -1) makeClientVisitReassignmentMemo($prov, $appt['clientptr'], $appt['appointmentid']);
}

foreach($successes as $id) {
	// supersedeAppointmentBillable($id); No reason tp supersede billable
	$appt = getAppointment($id, false, true, true);
	logAppointmentStatusChange($appt, 'EZ Schedule reassign');
	if(!((int)($appt['billpaid'] + $appt['providerpaid']))) {
		if($appt['payableid']) deleteTable('tblpayable', "payableid = {$appt['payableid']}", 1);
	}
}


if($timeofflabels || $conflictlabels || $statusLabels) {
	$pname = getProviderShortNames("WHERE providerid=$prov");
	$pname = $pname[$prov];
	$message = "<span style='font-size:1.5em'>";
	if($timeofflabels) 
		$message .= "$pname has time off and so was <b>not</b> assigned:"
								."<ul><li>".join('<li>', $timeofflabels)."</ul>";
	if($conflictlabels) 
		$message .= "Because of exclusive service conflicts, $pname was <b>not</b> assigned:"
								."<ul><li>".join('<li>', $conflictlabels)."</ul>";
	if($statusLabels) 
		$message .= "These visits were not changed because:"
								."<ul><li>".join('<li>', $statusLabels)."</ul>";
	$message .= "</span>";
	$_SESSION['user_notice'] = $message;
}
else echo "MESSAGE:Done!";