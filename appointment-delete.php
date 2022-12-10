<? //appointment-delete.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "invoice-fns.php";
require_once "provider-memo-fns.php";
locked('o-');

$ids = explode(',', $_GET['id']);

$allServices = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
foreach($ids as $id) {
	$appt = getAppointment($id, true, true, true);
	logAppointmentStatusChange($appt, 'deleted');

	supersedeAppointmentBillable($id);

	makeClientVisitDeletionMemo($id);
	
	deleteAppointments("appointmentid = $id");
	
	$from = $_GET['from'];
	
	$from = $from ? " [$from]" : '';
	
	logChange($appt['clientptr'], 'tblclient', 'x', 
		$note="Deleted appt {$appt['appointmentid']}  {$appt['date']} {$appt['timeofday']} {$allServices[$appt['servicecode']]} {$appt['provider']}$from");
	
	$clientid = $appt['clientptr'];
	if(!$appt['recurringpackage']) $nrPacks[$appt['packageptr']] = 1;
	
}
// update nonrecurring package prices
if($nrPacks) {
	require_once "service-fns.php";
	$histories = findPackageHistories($clientid, 'N', $current=true);
	foreach(array_keys((array)$nrPacks) as $packageid)
		foreach($histories as $version => $history)
			if(in_array($packageid, $history))
				$currentPacks[$version] = 1;
	foreach(array_keys((array)$currentPacks) as $packageid) {
		$price = calculateNonRecurringPackagePrice($packageid, $clientid);
		updateTable('tblservicepackage', array('packageprice'=>$price), "packageid = $packageid");
	}
}

echo "done!";

