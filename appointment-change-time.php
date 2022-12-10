<? //appointment-change-time.php
// id may be one id or id1,id2,...
// called by calendar-package-irregular.php
// tod format = 1:30 pm-2:30 pm
// all appts belong to same client
// based on appointment-reassign, started 11/17/2020

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "invoice-fns.php";
//require_once "pet-fns.php";
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
$pnames = getProviderShortNames("WHERE 1=1");

$tod = $_GET['tod'];
foreach($appts as $i=>$appt) {
	$prov = $appt['providerptr'];
	$pname = $pnames[$prov];

	if($prov && providerIsOff($prov, $appt['date'], $tod)) {
	//if(in_array(strtotime($appt['date']), $times)) 
		$timeofflabels[] = 
									"<b>".date('F j', strtotime($appt['date']))."</b> {$appt['timeofday']} "
									.$_SESSION['servicenames'][$appt['servicecode']]
									." assigned to $pname";
		continue;
	}
	$proposedAppt = array_merge($appt);
	$proposedAppt['timeofday'] = $tod;
if($FIX_ENABLED = TRUE) {	// remove test here (and below) when we are sure it works
	$times = timesForApptStartAndEnd($proposedAppt);
	$proposedAppt['starttime'] = date('H:i', $times['start']);
	$proposedAppt['endtime'] = date('H:i', $times['end']);
}
	
	if(detectVisitCollision($proposedAppt, $prov)) {
		$conflictlabels[] = 
									"<b>".date('F j', strtotime($appt['date']))."</b> {$appt['timeofday']} "
									.$_SESSION['servicenames'][$appt['servicecode']]
									." assigned to $pname";
		continue;
	}
	
	// Add a check here for CANCELED or COMPLETED visit, if desired/applicable.
	if($appt['canceled'] || $appt['completed']) {
		$statusLabels[] = 
									"<b>".date('F j', strtotime($appt['date']))."</b> {$appt['timeofday']} "
									.$_SESSION['servicenames'][$appt['servicecode']]
									." is marked ".($appt['canceled'] ? 'CANCELED' : 'COMPLETE').".";
		continue;
	}
	
	$successes[] = $appt['appointmentid'];
	$mods = array();
	$mods['timeofday'] = $_GET['tod'];
if($FIX_ENABLED) {	
	$mods['starttime'] = $proposedAppt['starttime'];
	$mods['endtime'] = $proposedAppt['endtime'];
}
	updateTable('tblappointment', withModificationFields($mods), "appointmentid={$appt['appointmentid']}", 1);
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		updateAppointmentAutoSurcharges($appt['appointmentid']);
	}

	if($prov) makeClientVisitChangeMemo($prov, $appt['clientptr'], $appt['appointmentid']);
}

foreach($successes as $id) {
	// supersedeAppointmentBillable($id); No reason tp supersede billable
	$appt = getAppointment($id, false, true, true);
	logAppointmentStatusChange($appt, "EZ Schedule times change to $tod");
	//if(!((int)($appt['billpaid'] + $appt['providerpaid']))) {
	//	if($appt['payableid']) deleteTable('tblpayable', "payableid = {$appt['payableid']}", 1);
	//}
}


if($timeofflabels || $conflictlabels || $statusLabels) {
	$pname = $pnames[$prov];
	$message = "<span style='font-size:1.5em'>";
	if($timeofflabels) 
		$message .= "Because of sitter time off these visits were not changed:"
								."<ul><li>".join('<li>', $timeofflabels)."</ul>";
	if($conflictlabels) 
		$message .= "Because of exclusive service conflicts, these visits were not changed:"
								."<ul><li>".join('<li>', $conflictlabels)."</ul>";
	if($statusLabels) 
		$message .= "These visits were not changed because:"
								."<ul><li>".join('<li>', $statusLabels)."</ul>";
	$message .= "</span>";
	$_SESSION['user_notice'] = $message;
}
else echo "MESSAGE:Done!";