<?
// client-own-scheduler-data.php
// returns a JSON packet for the Pet Owner Portal
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "client-schedule-fns.php";
require_once "pet-fns.php";
require_once "preference-fns.php";

/*
parameters (* = required)

start * - date
end * -date
timeframes - 1/0
servicetypes * - 1/0
surchargetypes * - 1/0

(returns requested, but unscheduled, visits from unresolved requests as well)


*/

// Determine access privs
$locked = locked('c-');

$max_rows = 100;

extract($_REQUEST);

$client = $_SESSION["clientid"];



//$json['client'] = $_SESSION["clientname"];
//$json['clientid'] = $client;
if($start) {
	if(!$end) $result['error'] = 'start param supplied, but no end param';
	else {
		$start = strtotime($start);
		if(!$start) $result['error'][] = "start param [{$_REQUEST['start']}] is not a valid date";
		$end = strtotime($end);
		if(!$end) $result['error'][] = "end param [{$_REQUEST['end']}] is not a valid date";
	}
	if($result['error']) {
		echo json_encode($result);
		exit;
	}
	$start = date('Y-m-d', $start);
	$end = date('Y-m-d', $end);
	$excludecanceledvisits = null;
	$visits = fetchClientVisits($start, $end, $excludecanceledvisits, $includenotes=null, $client);
	$json['visits'] = $visits;
}

if($timeframes) {
	require_once "timeframe-fns.php";
	foreach(getTimeframes() as $tf) {
		$times = explode('-', $tf[1]);
		$entry = array('label'=>$tf[0]);
		$entry['start'] = $times[0];
		$entry['end'] = $times[1];
		$entry['startmil'] = date('H:i', strtotime("12/1/2018 {$times[0]}"));
		$entry['endmil'] = date('H:i', strtotime("12/1/2018 {$times[1]}"));
		$json['timeframes'][] = $entry;
	}
}

if($servicetypes) {
	require_once "service-fns.php";
	require_once "client-services-fns.php";
	$activeServices = fetchAssociationsKeyedBy("SELECT * FROM tblservicetype WHERE active = 1", 'servicetypeid');
	$fields = getClientServiceFields();
	for($i=1; $i<=count($fields)+10; $i++) {
		if($fields["client_service_$i"][1] && array_key_exists($fields["client_service_$i"][1], $activeServices)) {
			$clientServices[$fields["client_service_$i"][0]] = $activeServices[$fields["client_service_$i"][1]];
		}
	}

	$custom = fetchAssociationsKeyedBy(
		"SELECT servicetypeptr, charge, extrapetcharge, taxrate 
			FROM relclientcharge 
			WHERE clientptr = $client", "servicetypeptr");
//if(mattOnlyTEST()) {print_r($custom);exit;}
	$baseRate = $_SESSION['preferences']['taxRate'];
	foreach($clientServices as $label => $servicetype) {
		$servicetypeid = $servicetype['servicetypeid'];
		$customType = $custom[$servicetypeid];
//if(mattOnlyTEST()) {echo "CT: ".print_r($customType,1);}
//if(mattOnlyTEST()) {echo "<br>ST[$label]: ".print_r($servicetype,1);}
		
		//if($label == 'Dog Walk 1 hr') {print_r($servicetype); exit;}
		
		$taxRate = $customType && $customType['taxrate'] != -1 ? $customType['taxrate'] : (
								$serviceType['taxable'] ? $baseRate : 0);
		$charge = $customType && ((float)$customType['charge']) != -1 ? $customType['charge'] 
							: $servicetype['defaultcharge'];
		$extrapetcharge = $customType && ((float)$customType['extrapetcharge']) != -1 ? $customType['extrapetcharge'] 
							: $servicetype['extrapetcharge'];
//if(mattOnlyTEST()) echo "<br>[$charge, $extrapetcharge, $taxRate]";
		$thisServiceType = array(
			'label'=>$label,
			'servicetypeid'=>$servicetypeid,
			'description'=>$servicetype['description'],
			'charge' => $charge,
			'extrapetcharge' => $extrapetcharge,
			'taxrate' => (float)$taxRate);
		$json['servicetypes'][$servicetypeid] = $thisServiceType;
	}
}


if($surchargetypes) {
	$activeSurchargeTypes = fetchAssociationsKeyedBy("SELECT * FROM tblsurchargetype WHERE active = 1", 'surchargetypeid');
	foreach($activeSurchargeTypes as $id => $stype) {
		$surch = array('surchargetypeid'=>$id, 'charge'=>(float)$stype['defaultcharge']);
		$surch['label'] = $stype['label'];
		$surch['description'] = $stype['description'];
		$surch['automatic'] = $stype['automatic'] ? 1 : 0;
		$surch['pervisit'] = $stype['pervisit'] ? 1 : 0;
		if($stype['filterspec']) {
			$parts = explode('_', $stype['filterspec']);
			$surch['type'] = $parts[0];
			if($surch['type'] == 'weekend') {
				$surch['saturday'] = strpos($parts[1], 'Sa') !== FALSE;
				$surch['sunday'] = strpos($parts[1], 'Su') !== FALSE;
			}
			else if($parts[1]) $surch['time'] = $parts[1];
		}
		else if($stype['date']) {
			$surch['type'] = 'holiday';
			$surch['date'] = $stype['date'];
		}
		else $surch['type'] = 'other';
		
		$json['surchargetypes'][] = $surch;
	}
}

if(TRUE /* requested visits */)
	$json['requestedvisits'] = fetchRequestedVisits($start, $end, $client=null);

if(!$json) echo "No info requested.";
else if($test) print_r($json);
else {
	header("Content-type: application/json");
	echo json_encode($json);
}

function fetchRequestedVisits($start, $end, $client=null) {
	// for each unresolved schedule request return the visits
	// segment the list by request and present in chron order
	// so: requestid=>{note: note, requestid: id, visits: [visit, visit, ...]}
	$clientid = $client ? $client : $_SESSION["clientid"];
	$requests = fetchAssociations(
		"SELECT * 
			FROM tblclientrequest
			WHERE clientptr = $clientid AND requesttype = 'Schedule' AND resolved = 0
			ORDER BY requestid", 1);
	$chunks = array();
	if($requests) require_once "client-sched-request-fns.php";
	foreach($requests as $request) {
		if(!($schedule = scheduleFromNote($request['note']))) continue;
		$schedule['clientptr'] = $clientid;
		$schedule = mockedUpSchedule($schedule);
//print_r($schedule);exit;		
		$chunks[] = array(
			'requestid'=>$request['requestid'],
			'note'=>$schedule['note'],
			'received'=>$request['received'],
			'visits'=>$schedule['services']);
	}
	return $chunks;
}

function mockedUpSchedule($schedule) { // lifted from service-irregular-create.php
	require_once "appointment-fns.php";
	require_once "service-fns.php";
	$package['startdate'] = date('Y-m-d', strtotime($schedule['start']));
	$package['enddate'] = date('Y-m-d', strtotime($schedule['end']));
	$package['client'] = $schedule['clientptr'];
	$package['packageprice'] = $schedule['totalCharge'];
	$package['prepaid'] = $_SESSION['preferences']['schedulesPrepaidByDefault'];
	$package['preemptrecurringappts'] = 0;
	$package['billingreminders'] = $_SESSION['preferences']['sendBillingReminders'];

	$package['note'] = trim($schedule['note']);
	//$packageid = saveNewIrregularPackage($package);
	$day = $package['startdate'];
	$clientPets = getClientPetNames($package['client']);
	$serviceTypes = fetchAssociationsKeyedBy("SELECT * FROM tblservicetype", 'servicetypeid', 1);
	$clientServiceTypes = getClientServiceMenu();
	if($schedule['services']) foreach($schedule['services'] as $i => $dayServices) { // NEVER HAPPENS! <== HUH?!
		$clientCharges = getClientCharges($package['client']);
		$standardCharges = getStandardCharges();
		$standardRates = getStandardRates();

		if($dayServices) {
			foreach($dayServices as $newTask) {
	//echo print_r($newTask, 1).'<br>';					
				$newTask['clientptr'] = $package['client'];
				$newTask['providerptr'] = $chosenprovider ? $chosenprovider : '0';
				$newTask['serviceid'] = '0';
				//$newTask['packageptr'] = $packageid;
				$newTask['charge'] = calculateServiceCharge($package['client'], $newTask['servicecode'], $newTask['pets'], $clientPets, $clientCharges, $standardCharges);
				$newTask['rate'] = calculateServiceRate($newTask['providerptr'], $newTask['servi1cecode'], $newTask['pets'], $clientPets, $newTask['charge'], null, $standardRates);
				//createAppointment($recurring, $package, $task, $date, $canceledBecause=null, $simulation=null)
				$appt = createAppointment(false, null, $newTask, strtotime($day), $canceledBecause=null, $simulation=TRUE);  // NEVER HAPPENS! <== Are you HIGH?!
				$totalCharge += $newTask['charge'];
				/*if($_SESSION['surchargesenabled']) {
					require_once "surcharge-fns.php";
					updateAppointmentAutoSurcharges($appt);  // NEVER HAPPENS!
				}*/
				// pretty up the appt a bit...
				$appt['starttime'] .= ':00';
				$appt['endtime'] .= ':00';
				$appt['servicelabel'] = $serviceTypes[$appt['servicecode']]['label'];
				$appt['clientservicelabel'] = $clientServiceTypes[$appt['servicecode']];
				$appt['hours'] = $serviceTypes[$appt['servicecode']]['hours'];
				if(trim("{$appt['hours']}")) {
					$parts = explode(":", $appt['hours']);
					$appt['formattedhours'] = ((int)$parts[0] * 60 + (int)$parts[1])/60;
				}
				else $appt['formattedhours'] = $appt['hours'];
				$appt['packagetype'] = 'short term';
				$package['services'][] = $appt;
			}
			//if(!$package['packageprice'])
			//	updateTable('tblservicepackage', array('packageprice'=>$totalCharge), "packageid = $packageid", 1);
		}
		$day = date('Y-m-d', strtotime('+1 day', strtotime($day)));
	}
	return $package;
}


function fetchClientVisits($start, $end, $excludecanceledvisits=null, $includenotes=null, $client=null) {
	$clientid = $client ? $client : $_SESSION["clientid"];
	$rows = array();
	if($clientid && $clientid != -1) $filter[] = "a.clientptr = $clientid";
	if($excludecanceledvisits) $filter[] = "a.canceled IS NOT NULL";
	$filter = $filter ? "AND ".join(' AND ', $filter) : '';
	
	if($includenotes) $includenotes = " note,";
	//$formattedFields = explodePairsLine('charge|charge||adjustment|adjustment||rate|rate||bonus|bonus');
	//if(!$csv) foreach($formattedFields as $field) 
	//	$formattedFields[$field] = "IF($field IS NULL, $field, CONCAT_WS(' ', '".getCurrencyMark()."', FORMAT($field, 2))) as formatted$field";
	//$formattedFields = join(', ', $formattedFields);
	//					tblappointment.modified as apptmodified,
	//					tblappointment.created as apptcreated,

	$sql = "SELECT a.date, a.starttime, a.endtime, a.timeofday, a.appointmentid, a.providerptr, a.servicecode,
					a.charge, a.adjustment, a.rate, a.bonus, $includenotes
					IF(recurringpackage, 
						IF(monthly = 1,'fixed price', 'ongoing'),
						'short term') as packagetype,
					IF(completed IS NOT NULL, 'completed', IF(canceled IS NOT NULL, 'CANCELED', 'INCOMPLETE')) AS status,hours,
					IF(hours IS NULL, hours,  FORMAT(TIME_TO_SEC(CONCAT(hours, ':00')) / 3600, 3)) as formattedhours,
					IFNULL(arrivaltrack.date, null) as arrived,
					IFNULL(completiontrack.date, null) as completed,
					label as servicelabel,
					tax,
					vr.value as visitreport,
					pendingchange, pets
					FROM tblappointment a
					LEFT JOIN tblclient ON clientid = clientptr
					LEFT JOIN tblprovider ON providerid = providerptr
					LEFT JOIN tblservicetype ON servicetypeid = servicecode					
					LEFT JOIN tblrecurringpackage ON packageid = packageptr					
					LEFT JOIN tblgeotrack arrivaltrack ON arrivaltrack.appointmentptr = appointmentid AND arrivaltrack.event = 'arrived'				
					LEFT JOIN tblgeotrack completiontrack ON completiontrack.appointmentptr = appointmentid AND completiontrack.event = 'completed'			
					LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment' AND superseded = 0
					LEFT JOIN tblappointmentprop vr ON vr.appointmentptr = appointmentid AND property = 'reportIsPublic'
					WHERE a.date >= '$start' AND a.date <= '$end' $filter
					ORDER BY date, starttime";
//if(mattOnlyTEST()) {echo $sql;exit;}					

// ADD: sitter, servicelabel, clientservicelabel


	$rows = fetchAssociations($sql, 1);
	if($rows) {
		require_once "client-services-fns.php";
		require_once "provider-fns.php";
		// Find client services that refer uniquely to a service type
		$allCS = getClientServices();
		$clientservicemenu = array();
		foreach($allCS as $label => $stype)
			$stypes[$stype] += 1;
		foreach($allCS as $label => $stype)
			if($stypes[$stype] == 1)
				$clientservicemenu[$stype] = $label;
		foreach($rows as $i => $row) {
			$rows[$i]['clientservicelabel'] = $clientservicemenu[$row['servicecode']];
			$rows[$i]['sitter'] = 
				$row['providerptr'] ? getDisplayableProviderName($row['providerptr'])
				: 'unassigned';
			$apptid =$row['appointmentid'];
			$rows[$i]['visitreportstatus'] = getVisitReportStatus($apptid);
			$rows[$i]['visitreportstatus']['url'] = globalURL("visit-report-data.php?id=$apptid");
			require_once "appointment-client-notification-fns.php";
			$nugget = visitReportDataPacketNugget($apptid);
			$rows[$i]['visitreportstatus']['externalurl'] = 
				globalURL("visit-report-data.php?nugget=$nugget");

			
			
			
			
			
			if($row['pendingchange'] && $row['pendingchange'] < 0) $rows[$i]['pendingchangetype'] = 'cancel';
			else {
				$req = fetchFirstAssoc(
					"SELECT requesttype, extrafields FROM tblclientrequest 
						WHERE requestid = ".abs($row['pendingchange'])
						." LIMIT 1", 1);
				if($req['requesttype'] != 'schedulechange') $rows[$i]['pendingchangetype'] = $req['requesttype'];
				else { // handle new requesttype: schedulechange
					require_once "request-fns.php";
					$extras = getHiddenExtraFields($req);
					$rows[$i]['pendingchangetype'] = $extras['changetype'];
				}
			}
			
		}
	}
	
	return $rows;
}

function getVisitReportStatus($apptid) {
	$aprops = getAppointmentProperties($apptid, $properties='reportIsPublic,visitreportreceived,visitphotoreceived,visitphotouploadfail');
	$finalPhotoFail = $aprops['visitphotouploadfail'] && 
			(!$aprops['visitphotoreceived'] || 
							strcmp($aprops['visitphotoreceived'], $aprops['visitphotouploadfail']) < 0);
//if(mattOnlyTEST()) print_r($aprops);exit;	
	if($aprops['visitreportreceived']) $status['received'] = 1;
	else $status['received'] = 0;
	$status['photo'] = $finalPhotoFail ? 'uploadfailed' : (
			$aprops['visitphotoreceived'] ? 'photoreceived' :
			'nophoto');
	$status['sent'] = $aprops['reportIsPublic'] ? 1 : 0;
	return $status;
}

function getClientServiceMenu() {
	// Find client services that refer uniquely to a service type
	$allCS = getClientServices();
	$clientservicemenu = array();
	foreach($allCS as $label => $stype)
		$stypes[$stype] += 1;
	foreach($allCS as $label => $stype)
		if($stypes[$stype] == 1)
			$clientservicemenu[$stype] = $label;
	return $clientservicemenu;
}
