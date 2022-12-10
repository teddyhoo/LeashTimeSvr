<?
/* appointment-quickedit.php
*
* Mode 1 Parameters: 
* id - id of appointment to be edited
* save - appointment is to be saved
* providerptr - value to be saved
* starttime - value to be saved
* endtime - value to be saved
* rate - value to be saved
* charge - value to be saved
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";
require_once "pet-fns.php";
require_once "appointment-fns.php";
require_once "provider-memo-fns.php";

locked('ea');

$id = $_REQUEST['id'];


if($_REQUEST['save']) {
	$oldAppt = fetchFirstAssoc("SELECT providerptr, clientptr, pets, date, packageptr, charge, adjustment, timeofday, recurringpackage, servicecode 
															FROM tblappointment WHERE appointmentid=$id LIMIT 1");
	$tod = $_REQUEST['t'];
	$appt = array('providerptr'=>$_REQUEST['p'], 'timeofday'=>$tod, 'servicecode'=>$_REQUEST['s'], 'custom'=>1);
	if($appt['providerptr'] && providerIsOff($appt['providerptr'], $oldAppt['date'], $appt['timeofday'])) {
		$misassignedAppt = 'timeoff';
		$appt['providerptr'] = 0;
	}
	else {
		foreach($appt as $k => $v) $apptWithDate[$k] = $v;
		$apptWithDate['appointmentid'] = $id;
		$apptWithDate['date'] = $oldAppt['date'];
		if(detectVisitCollision($apptWithDate, $appt['providerptr'])) {
			$misassignedAppt = 'conflict';
			$appt['providerptr'] = 0;
		}
	}
	$appt['starttime'] = date("H:i", strtotime(substr($tod, 0, strpos($tod, '-'))));
	$appt['endtime'] = date("H:i", strtotime(substr($tod, strpos($tod, '-')+1)));
	$clientPets = getClientPetNames($oldAppt['clientptr']);
	$appt['charge'] = calculateServiceCharge($oldAppt['clientptr'], $appt['servicecode'], $oldAppt['pets'], $clientPets);
	$appt['rate'] = calculateServiceRate($appt['providerptr'], $appt['servicecode'], $oldAppt['pets'], $clientPets, $appt['charge']);
//echo print_r($appt['pets'],1).' - '.print_r($clientPets,1);	
	updateTable('tblappointment', withModificationFields($appt), "appointmentid=$id", 1);
	foreach(array('servicecode','timeofday','providerptr') as $key)
		if($appt[$key] != $oldAppt[$key]) $modFields[] = $key."[{$oldAppt[$key]}=>{$appt[$key]}]";
	if($modFields) logChange($id, 'tblappointment', 'm', 'QuickMods: '.join(', ', $modFields));
	$appt["appointmentid"] = $id;
	$appt["date"] = $oldAppt['date'];
	$appt["clientptr"] = $oldAppt['clientptr'];
	$appt["packageptr"] = $oldAppt['packageptr'];
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		if($oldAppt['recurringpackage'] && $appt['providerptr'] != $oldAppt['providerptr'])
			reassignExistingAppointmentSurcharges($appt["appointmentid"]);
		else if(!$oldAppt['recurringpackage'] ) {
			if($appt['timeofday'] != $oldAppt['timeofday']
					|| $appt['providerptr'] != $oldAppt['providerptr'])
			updateAppointmentAutoSurcharges($appt);
		}
	}


	if($oldAppt['charge']+$oldAppt['adjustment'] != $appt['charge']+$oldAppt['adjustment']) // value changed
		resetAppointmentDiscountValue($id, $appt['charge']+$appt['adjustment']);


	if($oldAppt['providerptr'] && $oldAppt['providerptr'] != $appt['providerptr'])
		makeClientVisitChangeMemo($oldAppt['providerptr'], $oldAppt['clientptr'], $id);
	if($appt['providerptr']) makeClientVisitChangeMemo($appt['providerptr'], $oldAppt['clientptr'], $id);
	
	require_once "invoice-fns.php";
	recreateAppointmentBillable($id);
	
	// update nonrecurring package price
	if(!$oldAppt['recurringpackage']) {
		$packageid = findCurrentPackageVersion($oldAppt['packageptr'], $oldAppt['clientptr'], false);
		$price = calculateNonRecurringPackagePrice($packageid, $oldAppt['clientptr']);
		updateTable('tblservicepackage', array('packageprice'=>$price), "packageid = $packageid");
	}

	
	$appt['appointmentid'] = $id;
	
	$sections = array($appt['providerptr'] == -1 ? 0 : $appt['providerptr']);
	if($oldAppt['providerptr'] != $appt['providerptr']) $sections[] = $oldAppt['providerptr'];
	echo join(',', $sections);
	$snafus = array('timeoff'=>'MISASSIGNED', 'conflict'=>'EXCLUSIVECONFLICT', 'inactive'=>'INACTIVESITTER');
	if($misassignedAppt) echo ",".$snafus[$misassignedAppt];
	exit;
}
$source = getAppointment($id);

availableProviderSelectElement($source['clientptr'], $source['date'], "providerptr_$id", array('--Unassigned--' => '-1'), $source['providerptr'], '');

//$activeProviderSelections = array_merge(array('--Unassigned--' => '-1'), getActiveProviderSelections($source['date'], $source['zip']));
//selectElement("Assign to: ", "providerptr_$id", $source['providerptr'], $activeProviderSelections);

echo "  Time: ";
buttonDiv("div_timeofday_$id", "timeofday_$id", "showTimeFramerInContentDiv(event, \"div_timeofday_$id\")",
		($source['timeofday'] ? $source['timeofday'] : ''), ($source['timeofday'] ? $source['timeofday'] : ''), 'display:inline;width:300px;padding-right:3px;');

$serviceSelections = getServiceSelections();
selectElement(' Service: ', "servicecode_$id", $source['servicecode'], $serviceSelections);
echo " ";
echoButton('', 'Done', "updateAppointmentVals($id)");
echo " ";
echoButton('', 'Quit', "document.getElementById(\"editor_$id\").parentNode.style.display=\"none\"");
