<?

/*
ALTER TABLE tblappointment ADD column recurringpackage tinyint(1) NOT NULL default '0' AFTER packageptr;
UPDATE tblappointment SET recurringpackage=1 WHERE (
*/

// appointment-fns.php
require_once "provider-fns.php";
require_once "client-fns.php";

function appointmentDescriptionHTML($appointmentid, $package=null, $returnNull=null) {
	global $apptFields;
	$apptFields = getApptFields();
	$appt = getAppointment($appointmentid, $withNames=true, $withPayableData=false, $withBillableData=false);
	if(!$appt && $returnNull) return null;  // should probably be default
	if(isset($_SESSION) && $_SESSION) $serviceName = getServiceName($appt['servicecode']);
	else {
		require_once "service-fns.php";
		$names = getServiceNamesById();
		$serviceName = $names[$appt['servicecode']];
	}
	
	ob_start();
	ob_implicit_flush(0);
	echo "<table width=100%>";
 	labelRow($apptFields['date'].':', '', displayDate($appt['date']));
 	labelRow($apptFields['timeofday'].':', '', $appt['timeofday']);
 	labelRow($apptFields['servicecode'].':', '', $serviceName);
 	
 	$provider = getDisplayableProviderName($appt['providerptr']);
 	if($provider && is_array($provider)) $suppressProvider = $provider['none'];
 	if(!$suppressProvider) {
		$provider = $provider ? $provider : 'Unassigned';
	 	labelRow($apptFields['provider'].':', '', $provider);
	}
 	labelRow($apptFields['pets'].':', '', $appt['pets']);
 	
 	$statusClass = '';
 	if($appt['canceled']) {
		$status = "Canceled ".displayDateTime($appt['canceled']);
		$statusClass = 'canceledtask';
	}
 	else if($appt['completed']) {
		$status = "Completed ".displayDateTime($appt['completed']);
		$statusClass = 'completedtask';
	}
 	else {
		$futurity = appointmentFuturity($appt);
		if($futurity > 0) $status = 'Not yet due'; 
		else if($futurity == 0) $status = 'To be done';
		else {
			$status = "Unreported";
			$statusClass = 'noncompletedtask';
		}
	}
	$modifiers = array();
	if($source['highpriority']) $modifiers[] = '<font color=red>High Priority</font>';
	//if($source['custom']) $modifiers[] = 'Custom';
	if($modifiers) $status .= '- ['.join(', ', $modifiers).']';
 	labelRow($apptFields['status'].':', '', $status, '', $statusClass,'','','raw');
	echo "</table>";
	
	$descr = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $descr;
}

function appointmentSMSDescription($appointmentid, $package=null, $returnNull=null) {
	global $apptFields;
	$appt = getAppointment($appointmentid, $withNames=true, $withPayableData=false, $withBillableData=false);
	if(!$appt && $returnNull) return null;  // should probably be default
	if(isset($_SESSION) && $_SESSION) $serviceName = getServiceName($appt['servicecode']);
	else {
		require_once "service-fns.php";
		$names = getServiceNamesById();
		$serviceName = $names[$appt['servicecode']];
	}
	
	ob_start();
	ob_implicit_flush(0);
 	$provider = !$appt['provider'] ? 'Unassigned' : 
 		fetchRow0Col0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider
 										WHERE providerid = {$appt['providerptr']}");
 	if(!is_string($provider)) $provider = $provider[$appt['providerptr']];
	echo abbreviatedDisplayDate($appt['date'])."\n".briefTimeOfDay($appt).'   '.$serviceName."\n";
	echo "Sitter: $provider\n";
	echo "Pets: {$appt['pets']}\n";
 	
	$status = $appt['canceled'] ? "Canceled" : ( // .displayDateTime($appt['canceled'])
		$appt['completed'] ? "Completed" : null); // .displayDateTime($appt['completed'])
 	if(!$status) {
		$futurity = appointmentFuturity($appt);
		$status = $futurity > 0 ? 'Not yet due' : (
			$futurity == 0 ? 'To be done' :  
			"Unreported");
	}
	echo "Status: $status\n";
	if($source['highpriority']) echo "* High Priority *\n";;
	
	$descr = trim(ob_get_contents());
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $descr;
}

function deletedAppointmentDescriptionHTML($appt) {
	global $apptFields, $db;
	static $serviceNames, $localDB;
	if(!$serviceNames || $localDB != $db)
		$serviceNames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$localDB = $db;
	$serviceName = $serviceNames[$appt['servicecode']];
	ob_start();
	ob_implicit_flush(0);
	echo "<table width=100%>";
 	labelRow($apptFields['date'].':', '', displayDate($appt['date']));
 	labelRow($apptFields['timeofday'].':', '', $appt['timeofday']);
 	labelRow($apptFields['servicecode'].':', '', $serviceName);
	echo "</table>";
	$descr = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $descr;
}

function deletedAppointmentSMSDescription($appt) {
	global $db;
	static $serviceNames, $localDB;
	if(!$serviceNames || $localDB != $db)
		$serviceNames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$localDB = $db;
	$serviceName = $serviceNames[$appt['servicecode']];
	ob_start();
	ob_implicit_flush(0);
	
	echo abbreviatedDisplayDate($appt['date'])."\n".$appt['timeofday'].'   '.$serviceName."\n";
	if($appt['pets']) echo "Pets: {$appt['pets']}";
	$descr = trim(ob_get_contents());
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $descr;
}



function getDeletableCustomAppointments($packageptr, $recurring, $effectiveDate=null) {
//2. Collect any incomplete, uncanceled custom appointments for the current schedule
	$effective = $effectiveDate ? "AND date >= '".date('Y-m-d', strtotime($effectiveDate))."'" : '';
	$recurring = $recurring ? 1 : 0;
	// $recordInFuture = recordInFutureSQL('date', 'starttime');
  $appts = fetchAssociations(tzAdjustedSql("SELECT * FROM tblappointment WHERE packageptr = $packageptr and recurringpackage = $recurring
                              AND completed is null AND custom =  1 AND canceled is null 
                              AND (date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME())) $effective"));
	return $appts;
}

function getDayAppointments($clientid, $date) {
	return fetchAssociations("SELECT * FROM tblappointment WHERE clientptr = $clientid 
                              AND date = '$date' AND canceled IS NULL ORDER BY starttime");
}

function getProviderDayAppointments($prov, $date) {
	return fetchAssociations("SELECT * FROM tblappointment WHERE providerptr = $prov 
                              AND date = '$date' AND canceled IS NULL ORDER BY starttime");
}

function timeWindowInMinutes($appt) {
	$times = timesForApptStartEnd($appt);
	return ($times['end'] - $times['start'])/60;
}

function timesForApptStartAndEnd($appt) {
	$starttime = substr($appt['timeofday'], 0, strpos($appt['timeofday'], '-'));
	$endtime = substr($appt['timeofday'], strpos($appt['timeofday'], '-')+1);
	
	$dateInt = strtotime($appt['date']);
	if(strtotime($endtime) < strtotime(($starttime))) 
		$endtime = strtotime(date('Y-m-d', strtotime("+ 1 day", $dateInt))." $endtime");
	else $endtime = strtotime(date('Y-m-d', $dateInt)." $endtime");
	return array('start'=>strtotime("{$appt['date']} $starttime"), 'end'=>$endtime);
}


function appointmentFuturity($appt, $now=null) {
	if($now == null) $now = getLocalTime();//time();
	// return -1 if appointment is completely past, 0 if now is in appointment's timeframe, or 1 if appointment timeframe is totally in the future
	$start = strtotime("{$appt['date']} {$appt['starttime']}");
	if($start > $now) return 1;

	$end = ''.$appt['endtime'];
	$endsNextDay = $end < $appt['starttime'];
	$end = $endsNextDay ? strtotime("+ 1 day", strtotime("{$appt['date']} $end")) : strtotime("{$appt['date']} $end");
  return $end < $now ? -1 : (
		      ($start <= $now) && ($end >= $now) ? 0 : 1);
}

function deleteAllIncomplete($packageHistory, $recurring, $customToo=false, $effectiveDate=null) {
//3. Delete all future incomplete, non-custom, uncanceled appointments associated with this schedule
	$effective = $effectiveDate ? "AND date >= '".date('Y-m-d', strtotime($effectiveDate))."'" : '';

  $customPhrase = $customToo ? '' : 'AND custom is null';
	$recurring = $recurring ? 1 : 0;
	$packageIds = join(',', $packageHistory);
	// $recordInFuture = recordInFutureSQL('date', 'starttime');
  deleteAppointments(tzAdjustedSql("packageptr IN ($packageIds) and recurringpackage = $recurring
                              AND completed is null AND canceled is null $customPhrase 
                              AND (date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME())) $effective"));
  if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
  	dropAutoSurchargesWhere(tzAdjustedSql("packageptr IN ($packageIds)
                              AND completed is null $customPhrase 
                              AND (date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME()))"));
	}
}

function deleteAppointments($where) {
	if($_SESSION['discountsenabled'] || $_SESSION['surchargesenabled'] ) $ids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE $where");
	if($_SESSION['surchargesenabled'] && $ids) {
		require_once "surcharge-fns.php";
		dropAppointmentSurcharges($ids, false);
		foreach($ids as $id) checkNonspecificSurcharges($id);
	}
  doQuery("DELETE FROM tblappointment WHERE $where");
	if($_SESSION['discountsenabled'] && $ids) {
		require_once "discount-fns.php";
		dropAppointmentDiscounts($ids);
	}
	if($ids) {
		$ids = join(',', $ids);
		deleteTable('relreassignment', "appointmentptr IN ($ids)", 1);
		deleteTable('tblgeotrack', "appointmentptr IN ($ids)", 1);
	}
}

function setAppointmentDiscounts($appts, $on, $force=false) {
	global $CRON_DiscountsAreEnabled;
	if(!$appts) return;
	if($_SESSION && !$_SESSION['discountsenabled']) return;
	else if(!$_SESSION && !$CRON_DiscountsAreEnabled) return;
	
	require_once "discount-fns.php";
	
	if($force || !$on) {
		foreach($appts as $appt) $apptIds[] = is_array($appt) ? $appt['appointmentid'] : $appt;
//echo "APPTS: ".print_r($appts, 1);
		if($apptIds) dropAppointmentDiscounts($apptIds);
	}
	if($on) foreach($appts as $appt) discountAppointment($appt);
}

function resetAppointmentDiscountValue($apptId, $totalCharge) {
	if(!($discount = fetchFirstAssoc("SELECT * FROM relapptdiscount WHERE appointmentptr = $apptId LIMIT 1"))) return;
	$clientid = $discount['clientptr'];
	$clientDiscount = fetchFirstAssoc("SELECT * FROM relclientdiscount
																			WHERE discountptr = {$discount['discountptr']} AND clientptr = {$discount['clientptr']} LIMIT 1");
	$discountType = fetchFirstAssoc("SELECT * FROM tbldiscount WHERE discountid = {$discount['discountptr']} LIMIT 1");
	if(!$discountType['unlimiteddollar']) {
		$discountTotal = 0;
		if($started = $clientDiscount['start']) {
			require_once "discount-fns.php";
			$discountTotal = getDiscountTotal($clientid, $discountType, $started);
		}
		$discountTotal -= $discount['amount'];
		if(!$discountType['ispercentage'] && $totalDiscount >= $discountType['amount']) return null;
		$maxDiscount = $discountType['ispercentage'] 
										? $discountType['amount'] / 100 * $totalCharge
										: min($totalCharge, $discountType['amount'] - $discountTotal);
	}
	else $maxDiscount = $discountType['ispercentage'] 
										? $discountType['amount'] / 100 * $totalCharge
										: min($totalCharge, $discountType['amount']);
//echo "discountTemplate: {$discountTemplate['amount']} CURRENT: $maxDiscount";exit;
	if($maxDiscount !=  $discount['amount'])
		updateTable('relapptdiscount', array('amount'=>$maxDiscount), "appointmentptr = $apptId", 1);
}


function getAppointmentSignature($appt) {
	return $appt['date'].'_'.$appt['clientptr'].'_'.$appt['birthmark'];
}

function getNewAppointmentSignature($appt) {
	return $appt['date'].'_'.$appt['clientptr'].'_'.getServiceSignature($appt);
}

function getServiceSignature($apptOrService) {
	return $apptOrService['timeofday'].'_'.$apptOrService['servicecode'];
}

function appointmentSignaturesEqual($a, $b) {
	return strcmp($a, $b) == 0;
}

function appointmentSignaturesMatchIgnoringService($a, $b) {
	return !strcmp(substr($a, 0, strrpos($a, '_')), substr($b, 0, strrpos($b, '_')));
}

function appointmentExists($apptSig, $preexistingAppointments, $comparisonFunction) {
	foreach($preexistingAppointments as $preApt)
		if(call_user_func_array($comparisonFunction, array($preApt, $apptSig)))
			return true;
	return false;
}

function createScheduleAppointments($package, $services, $recurring, $intervalToIgnore=null, $simulation=null, $allowRetroactiveAppointments=false, $preexistingAppointments=null, $preexistingAppointmentMatch='appointmentSignaturesEqual') {
	global $projectionStartTime, $projectionEndTime;
	require_once "service-fns.php";
	require_once "pet-fns.php";
	$scheduleStart = strtotime($package['startdate']);
	$start = $scheduleStart;
	
	if($package['effectivedate']) $start = max($start, strtotime($package['effectivedate']));
	if(!$allowRetroactiveAppointments) $start = max($start, strtotime(date("Y-m-d")));
	if($projectionStartTime) $start = max($start, $projectionStartTime);
	// Assumption: enddate may not fall before today
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
						: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job

	$recurringLookaheadDays = $prefs['recurringScheduleWindow'] ? $prefs['recurringScheduleWindow'] : 30;
	$recurringEndLimit = $projectionEndTime ? $projectionEndTime : strtotime("+ $recurringLookaheadDays days");
	$end = $recurring ? $recurringEndLimit 
										: ($package['onedaypackage'] ? strtotime($package['startdate']) : strtotime($package['enddate']));
  // if there is a cancellation date										
	if($package['cancellationdate'])
	  // set end to the day before it if it falls before end's current value
	  $end = min($end, strtotime("- 1 day",strtotime($package['cancellationdate'])));
	// , strtotime(date("Y-m-d")));what does today have to do with anything?
	$blackoutRanges = array();
	$blackoutReasons = array();
	if($intervalToIgnore) {
		$intervalToIgnore[0] = strtotime($intervalToIgnore[0]);
		$intervalToIgnore[1] = strtotime($intervalToIgnore[1]);
	}
	$appointments = array();
//1. if recurring
	if($recurring) {
     //if(date in suspended period) quit
     //foreach(nonrecurringschedules)
       //if (preemptrecurringappts and newappt date in nonrecurringschedule daterange) quit
		if($package['suspenddate']) {
		  $blackoutRanges[] = array(strtotime($package['suspenddate']), 
		                            strtotime("- 1 day", strtotime($package['resumedate'])));
			$blackoutReasons[] = 'Canceled: Planned suspension';
		}
    foreach(getCurrentClientPackages($package['clientptr'], 'tblservicepackage') as $nonrec) 
			if($nonrec['preemptrecurringappts']) {
		    $blackoutRanges[] = array(strtotime($package['startdate']), 
		                            strtotime($package['enddate']));
				$blackoutReasons[] = 'Preempted by another service package.';
			}
  }
  
  $today = strtotime(date('Y-m-d'));
	$allProviderRates = array();
	$standardRates = getStandardRates();
	$clientPets = getClientPetNames($package['clientptr']);
	for($day = $start; $day <= $end; $day = strtotime("+ 1 day", $day)) {
		if($day < $today  && !$allowRetroactiveAppointments) continue;
		if($intervalToIgnore && $day >= $intervalToIgnore[0] && $day <= $intervalToIgnore[1]) continue;
		$weekNumber = weekNumber($day, $package);
		$canceledBecause = null;
		foreach($blackoutRanges as $index => $blackout)
		  if($blackout[0] <= $day && $blackout[1] >= $day) {
				$canceledBecause = $blackoutReasons[$index];
				break;
			}		
		$dayOfWeek = dayOfWeek($day);
		foreach($services as $service) {
			if(dbTEST('dogslife') && $package['clientptr'] == 47) 
				bagText(date('H:i')." [".print_r(date('Y-m-d', $start), 1)." - ".date('Y-m-d', $end)
									."] Week: ($weekNumber) Day: ".print_r(date('Y-m-d', $day), 1)
									." Svc: ".print_r($service, 1), 'MULTITEST');

			if( $service['week'] != $weekNumber) continue;
			// confirm that the provider's rate on the service is up-to-date
			if(!isset($allProviderRates[$service['providerptr']])) 
				$allProviderRates[$service['providerptr']] = getProviderRates($service['providerptr']);
			$providerRates = $allProviderRates[$service['providerptr']];

			$service['rate'] = calculateServiceRate($service['providerptr'], $service['servicecode'], $service['pets'], $clientPets, $service['charge'], $providerRates, $standardRates);
//global $db;if($db = 'yourdogsmiles' && $service['clientptr'] = 958) $service['note'] = "CALCED: {$service['rate']}";
			if($service['firstLastOrBetween'] == 'first' && $day != $scheduleStart) continue;
			if($service['firstLastOrBetween'] == 'last' && $day != $end) continue;
			if($service['firstLastOrBetween'] == 'between' && ($day == $end || $day == $scheduleStart)) continue;
	    // If service is not scheduled for this day of the week, don't create an appontment
	    if(!$service['daysofweek'] || in_array($dayOfWeek, daysOfWeekArray($service['daysofweek']))) {
	      // If appointment timeframe ended earlier today, don't create it
	      if($day == $today) {
					$starttime = substr($service['timeofday'], 0, strpos($service['timeofday'], '-'));
					$endtime = substr($service['timeofday'], strpos($service['timeofday'], '-')+1);
					if(strtotime($endtime) < strtotime(($starttime))) 
					  $endtime = strtotime(date('Y-m-d', strtotime("+ 1 day", $day))." $endtime");
					else $endtime = strtotime(date('Y-m-d', $day)." $endtime");
					if(strtotime(date('Y-m-d H:i')) > $endtime  && !$allowRetroactiveAppointments) continue;
				}
				$signature = getNewAppointmentSignature(
						array('date'=>date('Y-m-d', $day), 
									'servicecode'=>$service['servicecode'],
									'timeofday'=>$service['timeofday'],
									'clientptr'=>$service['clientptr']));
//echo "[$signature]<br>";									
//if($package['packageid'] == 678) echo ">> $signature<br>";
//echo ">> ".print_r($providerRates, 1)."<br>";exit;
				if($preexistingAppointments && appointmentExists($signature, $preexistingAppointments, $preexistingAppointmentMatch)) continue;
				$appointments[] = createAppointment($recurring, $package, $service, $day, $canceledBecause, $simulation);
			}
		}
	}
	if(!$recurring && !$simulation && $_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		createScheduleAutoSurcharges($package);
	}	
//exit;	
	return $appointments;
}


function createAppointment($recurring, $package, $task, $date, $canceledBecause=null, $simulation=null) {
	// 'daysofweek,Days of Week,timeofday,Time of Day,providerptr,Sitter,pets,Pets,servicecode,Service Type,'.
  // 'charge,Charge,adjustment,Adjustment,rate,Rate,bonus,Bonus'
	global $serviceFields, $tempApptTable;
  // confirm provider is not off today.  otherwise set providerptr to null
  $prov = $task['providerptr'];
if(mattOnlyTEST()) {logError("providerIsOff(prov: $prov, date: $date, tod: {$task['timeofday']})<br>");}
	$appt = array_merge($task);
	$appt['rate'] = $appt['rate'] == 0 ? '0.00' : $appt['rate'];
	$appt['charge'] = $appt['charge'] == 0 ? '0.00' : $appt['charge'];
	$appt['serviceptr'] = $task['serviceid'];
	$appt['birthmark'] = getServiceSignature($appt);
	$appt['date'] = date('Y-m-d', strtotime($date));
	unset($appt['serviceid']);
	unset($appt['daysofweek']);
	unset($appt['recurring']);
	unset($appt['current']);
	unset($appt['firstLastOrBetween']);
	if($prov) {
		if(!fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $prov LIMIT 1")) {
			$prov = 0;
			$providerUnassigned = 'inactive';
		}
		else if(providerIsOff($prov, $date, $task['timeofday'])) {
			$prov = 0;
			$providerUnassigned = 'timeoff';
		}
		else if(detectVisitCollision($appt, $prov)) {
			$prov = 0;
			$providerUnassigned = 'conflict';
		}
		$appt['providerptr'] = $prov;
	}
  // insert appointment here...
	$starttime = date("H:i", strtotime(substr($appt['timeofday'], 0, strpos($appt['timeofday'], '-'))));
	$endtime = date("H:i", strtotime(substr($appt['timeofday'], strpos($appt['timeofday'], '-')+1)));
	$appt['starttime'] = $starttime;
	$appt['endtime'] = $endtime;
	$appt['recurringpackage'] = $recurring ? 1 : 0;
	if($canceledBecause) {
		$appt['canceled'] = date('Y-m-d');
		$appt['cancellationreason'] = $canceledBecause;
	}
	
	// Retroactive appointments
	$starttime = substr($appt['timeofday'], 0, strpos($appt['timeofday'], '-'));
	$endtime = substr($appt['timeofday'], strpos($appt['timeofday'], '-')+1);
	
	$today = strtotime(date('Y-m-d'));
	$dateInt = strtotime($appt['date']);
	if(strtotime($endtime) < strtotime(($starttime))) 
		$endtime = strtotime(date('Y-m-d', strtotime("+ 1 day", $dateInt))." $endtime");
	else $endtime = strtotime(date('Y-m-d', $dateInt)." $endtime");
	$retroactive = $dateInt < $today || ($dateInt == $today && strtotime(date('Y-m-d H:i')) > $endtime);
	if($retroactive) { // Retroactive appts are automatically completed
		//$appt['completed'] = date('Y-m-d H:i:s');
		//$appt['note'] = 'Appointment scheduled retroactively.';
	}
	
	if(!$appt['pets']) $appt['pets'] = '--';
	if(array_key_exists('week', $appt))
		unset($appt['week']);

	if(!$simulation) {
		addCreationFields($appt);
		$table = $tempApptTable ? $tempApptTable : 'tblappointment';
	  $apptId = insertTable($table, $appt, 1);
	  if($providerUnassigned) {
			global $misassignedAppts;
			$misassignedAppts[$apptId] = $providerUnassigned; // 'timeoff' or 'conflict' or 'inactive'
		}
	  if(!$tempApptTable) {
			$appt['appointmentid'] = $apptId;
			// create billable here if completed
			if($appt['completed']) {
				require_once 'invoice-fns.php';
				createBillablesForNonMonthlyAppts($apptId);
			}
			if($canceledBecause) {
				logAppointmentStatusChange($appt, $canceledBecause);
			}
			setAppointmentDiscounts(array($appt['appointmentid']), true);
		}
	}

	return $appt;
	
}

function appointmentTimeFrameTimes($appt) { // return start time(), end time(), duration as seconds
	//$starttime = date("H:i:00", strtotime(substr($appt['timeofday'], 0, strpos($appt['timeofday'], '-'))));
	//$endtime = date("H:i:00", strtotime(substr($appt['timeofday'], strpos($appt['timeofday'], '-')+1)));
	$starttime = $appt['starttime'];
	$endtime = $appt['endtime'];
	if(!$starttime) echo "<br>appointmentTimeFrameTimes missing starttime: ".print_r($appt, 1);
	if(!$endtime) echo "<br>appointmentTimeFrameTimes missing endtime: ".print_r($appt, 1);
	$enddate = strcmp($endtime,$starttime) < 0 ? date("Y-m-d", strtotime("+1 day", strtotime($appt['date']))) : $appt['date'];
	$result['starttime'] = strtotime($appt['date'].' '.$starttime);
	$result['endtime'] = strtotime("$enddate $endtime");
	$result['framedurationseconds'] = $result['endtime']-$result['starttime'];
	return $result;
}



function detectVisitCollision($appt, $provid) { // $appt may or may not exist in the db
	// return true if any of provider's visit timeframes overlap the appt's timeframe
	// AND if any of the overlapping visits (including appt) are exclusive
	if(!$provid) return false;
	$starttime = date("H:i:00", strtotime(substr($appt['timeofday'], 0, strpos($appt['timeofday'], '-'))));
	$endtime = date("H:i:00", strtotime(substr($appt['timeofday'], strpos($appt['timeofday'], '-')+1)));
	$enddate = strcmp($endtime,$starttime) < 0 ? date("Y-m-d", strtotime("+1 day", strtotime($appt['date']))) : $appt['date'];
	$endtime = "$enddate $endtime";
	$starttime = $appt['date'].' '.$starttime;
	$excludeAppt = $appt['appointmentid'] ? "AND appointmentid != {$appt['appointmentid']}" : "";
	
	$GT = '>=';
	$LT = '<=';
	if(getTimeFrameOverlapPolicy() == 'permissive') {
		$GT = '>';
		$LT = '<';
	}
	
	$rowStartTime = "CONCAT_WS(' ', date, starttime)";
	$rowEndTime = "if(endtime<starttime,
													CONCAT_WS(' ', DATE_ADD(date, INTERVAL 1 DAY), endtime),
													CONCAT_WS(' ', date, endtime))";
	$equalityTEST = "(CONCAT_WS(' ', date, starttime) = '$starttime' AND $rowEndTime = '$endtime') ";
	// logic: 
	//				row timeframe matches appt timeframe
	//				OR row start time  is in appt timeframe
	//				OR row end time  is in appt timeframe
	//				OR appt start is in row timeframe
	//				OR appt end time  is in row timeframe
	$sql = "SELECT servicecode 
					FROM tblappointment
					WHERE providerptr = $provid
						$excludeAppt
						AND date = '{$appt['date']}'
						AND canceled IS NULL
						AND (($rowStartTime = '$starttime' AND $rowEndTime = '$endtime')
									OR ($rowStartTime $GT '$starttime' AND $rowStartTime $LT '$endtime')
									OR ($rowEndTime $GT '$starttime' AND $rowEndTime $LT '$endtime')
									OR ('$starttime' $GT $rowStartTime AND '$starttime' $LT $rowEndTime)
									OR ('$endtime' $GT $rowStartTime AND '$endtime' $LT $rowEndTime)
									)
									";

/*						AND ((starttime >= '$starttime' AND starttime <= '$endtime')
									OR ('$starttime' >= starttime AND '$starttime' <= endtime))
*/									
	$serviceCodes = fetchCol0($sql);

}	
	if($serviceCodes) { // there may be a collision
		$existingExclusive = fetchRow0Col0(
						"SELECT hoursexclusive
							FROM tblservicetype 
							WHERE hoursexclusive = 1
								AND servicetypeid IN (".join(',', $serviceCodes).") 
							LIMIT 1");
		if($existingExclusive) $result = true;
		else $result = fetchRow0Col0(
						"SELECT hoursexclusive 
							FROM tblservicetype 
							WHERE hoursexclusive = 1
								AND servicetypeid = {$appt['servicecode']} 
							LIMIT 1");
		
	}
	
	return $result;
}

function visitTimesOverlap($appt, $appt2) {
	// return true if visit timeframes overlap.  Take overnights into account. Return false if appointmentids match.
	if($appt['appointmentid'] == $appt2['appointmentid']) return false;
	$starttime = date("H:i:00", strtotime(substr($appt['timeofday'], 0, strpos($appt['timeofday'], '-'))));
	$endtime = date("H:i:00", strtotime(substr($appt['timeofday'], strpos($appt['timeofday'], '-')+1)));
	$enddate = strcmp($endtime,$starttime) < 0 ? date("Y-m-d", strtotime("+1 day", strtotime($appt['date']))) : $appt['date'];
	$endtime = "$enddate $endtime";
	$starttime = $appt['date'].' '.$starttime;
	
	$starttime2 = date("H:i:00", strtotime(substr($appt2['timeofday'], 0, strpos($appt2['timeofday'], '-'))));
	$endtime2 = date("H:i:00", strtotime(substr($appt2['timeofday'], strpos($appt2['timeofday'], '-')+1)));
	$enddate2 = strcmp($endtime2,$starttime2) < 0 ? date("Y-m-d", strtotime("+1 day", strtotime($appt2['date']))) : $appt2['date'];
	$endtime2 = "$enddate2 $endtime2";
	$starttime2 = $appt2['date'].' '.$starttime2;
	
	if(getTimeFrameOverlapPolicy() == 'permissive') {
		if(($starttime == starttime2 && $endtime == endtime2) // real timeframes identical
		 	//|| ($starttime2 > $starttime && $endtime2 < $endtime) // appt2 inside appt
		 	|| ($starttime > $starttime2 && $starttime < $endtime2) // appt starts during appt2
		 	|| ($starttime2 > $starttime && $starttime2 < $endtime) // appt2 starts during appt
		 ) return true;
	}
	else {
		if(($starttime == starttime2 && $endtime == endtime2) // real timeframes identical
		 	//|| ($starttime2 >= $starttime && $endtime2 <= $endtime)
		 	|| ($starttime >= $starttime2 && $starttime <= $endtime2)
		 	|| ($starttime2 >= $starttime && $starttime2 <= $endtime)
		 	) return true;
	}
}

function getTimeFrameOverlapPolicy() {
	global $db;
	static $prefs, $lastDb;
	if(isset($_SESSION['preferences'])) $prefs = $_SESSION['preferences'];
	else {
		if($lastDb != $db) {
			$lastDb = $db;
			$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
		}
	}
	return $prefs['timeframeOverlapPolicy'];
}

function handleCancellationCredit($apptOrApptId) {
	// Need to figure out whether:
	// a. a payment is dedicated to this visit itself (weekly)
	// b. a payment is dedicated to this visit's package (TRICKY)
	// How about this?
	//    if billable and billable.paid
	//      find payment id
	//      if(SELECT * FROM reldedicatedpayment WHERE paymentptr = paymentid)
	//        then payment has been dedicated to this visit
	//		PROBLEM
	//    What if payment was not orinally and explicitly dedicated to a visit?
	//		EXAMPLE
	//		Payment A id dedicated to package P and applied completely to P's 10 visits
	//		Two of P's visits are deleted.  The billables for two visits are superseded.
	//    The remainder of P is applied to to random charges TO WHICH IT IS NOT DEDICATED.
	//
	//		PROBLEM deleteAllIncomplete (called when a recurring schedule is changed) deletes future incomplete 
	//     visits, assuming they have no billables.  They MAY have been paid for (dedicated), so this method
	//     is now a possible source of error.
	
	
	// This function is designed to handle cancellation of visits
	// and it treats cancellation of dedicated visits specially
	// This is in TEST mode at present.
	// Returns TRUE when a credit is created
	
	$apptid = is_array($apptOrApptId) ? $appt['appointmentid'] : $apptOrApptId;
	$appt = is_array($apptOrApptId) ? $apptOrApptId : fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $apptid LIMIT 1");
	if(!$appt['canceled']) return;
	if(!dbTEST('dogslife')) {
		return;
	}
	// ...
  // If paid by a dedicated payment, leave any payment alone create a credit
  $dedication = fetchAssociations(
		"SELECT * 
			FROM reldedicatedpayment
			WHERE expenseptr = '$apptid' AND expensetable = 'tblappointment'", 1);
	if($dedication) {
		$billable = fetchFirstAssoc(
			"SELECT * 
				FROM tblbillable 
				WHERE itemptr = $apptid AND itemtable = 'tblappointment'
					AND superseded = 0
				LIMIT 1", 1);
		$charge = $billable['charge'];
		insertTable('tblcredit', 
								array('clientptr'=>$appt['clientptr'], 'amount'=>$charge, 'issuedate'=>date('Y-m-d H:i:s'),
											'externalreference'=>$apptid, 'sourcereference'=>'tblappointment',
											'created'=>date('Y-m-d H:i:s'), 'createdby'=>$_SESSION['auth_user_id'],
											'reason'=>'Cancellation of visit on '.shortDate(strtotime($appt['date']))), 1);
		return true;
	}
}

function changeAppointmentDate($appointmentid, $newDate) {
	
	global $scheduleDiscount;
	$oldAppt = getAppointment($appointmentid);
	if($oldAppt['recurringpackage']) 
		return changeRecurringAppointmentDate($appointmentid, $newDate);
	
	$appt = array('date'=>date('Y-m-d', strtotime($newDate)));
	
	// HANDLE ASSOCIATED SURCHARGES, PART 1
	$filter = "appointmentptr = $appointmentid";
	// DROP associated auto surcharges
	dropSurchargesWhere($filter, $automaticOnly=true);
	//UPDATE any other surcharges
	updateTable('tblsurcharge', $appt, $filter, 1);
	
	// HANDLE SITTER ASSIGNMENT
	if($providerptr = $oldAppt['providerptr']) {			
		require_once "provider-fns.php";
				
		global $misassignedAppts;
		if($val == '-1') $appt['providerptr'] = 0;
		else if(!fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $providerptr LIMIT 1")) {
			$appt['providerptr'] = 0;
			$misassignedAppts[$appointmentid] = 'inactive';
		}
		else if(providerIsOff($providerptr, $appt['date'], $oldAppt['timeofday'])) {
			$appt['providerptr'] = 0;
			$misassignedAppts[$appointmentid] = 'timeoff';
		}
		else {
			$testAppt = array_merge($appt, array('timeofday'=>$oldAppt['timeofday'], 'servicecode'=>$oldAppt['servicecode']));
			if(detectVisitCollision($testAppt /* for date, timeofday, and servicecode */, $providerptr)) {
				$appt['providerptr'] = 0;
				$misassignedAppts[$appointmentid] = 'conflict';
			}
		}
	}
	
	updateTable('tblappointment', withModificationFields($appt), "appointmentid=$appointmentid", 1);
	
	$appt = getAppointment($appointmentid);


	// HANDLE ASSOCIATED SURCHARGES, PART 2
	if($_SESSION['surchargesenabled']) {
		$providerChanged = $appt['providerptr'] != $oldAppt['providerptr'];
		if($providerChanged && in_array($_POST['packageCode'], array('MON', 'REC'))) {
			reassignExistingAppointmentSurcharges($appointmentid);
		}
		else if("NOT A RECURRING VISIT") {
			require_once "surcharge-fns.php";
			updateAppointmentAutoSurcharges($appointmentid);
			checkNonspecificSurcharges($appt);
			$surcharges = fetchAssociations(
				"SELECT *
					FROM tblsurcharge
					WHERE date = '{$oldAppt['date']}' 
								AND clientptr = {$oldAppt['clientptr']}
								AND completed IS NULL", 1);
			foreach($surcharges as $surch)
				if(!justifySurcharge($surch)) 
					dropSurchargesWhere("surchargeid = {$surch['surchargeid']}", $automaticOnly=true);

		}
	}
	

	// HANDLE DISCOUNTS
	require_once "discount-fns.php";
	$currentDiscount = getAppointmentDiscount($appointmentid);
	if($currentDiscount) {
		resetAppointmentDiscountValue($appointmentid, $appt['charge']+$appt['adjustment']);
	}
	
	logChange($appointmentid, "tblappointment", 'm', "date|{$oldAppt['date']}=>{$appt['date']}");
	//print_r($appt);exit;
	return array('oldAppointment'=>$oldAppt, 'newAppointment'=>$appt);
}

function changeRecurringAppointmentDate($appointmentid, $newDate) {
	$oldAppt = getAppointment($appointmentid);
	foreach(explode(',', 'client,zip,provider') as $fld) unset($oldAppt[$fld]);
	require_once "discount-fns.php";
	$currentDiscount = getAppointmentDiscount($appointmentid);
	
	$appt = array_merge($oldAppt);
	cancelAppointments($appointmentid, $cancel=true, $additionalMods=null, $generateMemo=true, $initiator='Date change');  // return affected providers as a csv string
		
	// HANDLE SITTER ASSIGNMENT
	if($providerptr = $oldAppt['providerptr']) {			
		require_once "provider-fns.php";
				
		global $misassignedAppts;
		if($val == '-1') $appt['providerptr'] = 0;
		else if(!fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $providerptr LIMIT 1")) {
			$appt['providerptr'] = 0;
			$misassignedAppts[$appointmentid] = 'inactive';
		}
		else if(providerIsOff($providerptr, $appt['date'], $oldAppt['timeofday'])) {
			$appt['providerptr'] = 0;
			$misassignedAppts[$appointmentid] = 'timeoff';
		}
		else {
			$testAppt = array_merge($appt, array('timeofday'=>$oldAppt['timeofday'], 'servicecode'=>$oldAppt['servicecode']));
			if(detectVisitCollision($testAppt /* for date, timeofday, and servicecode */, $providerptr)) {
				$appt['providerptr'] = 0;
				$misassignedAppts[$appointmentid] = 'conflict';
			}
		}
	}
		

	
	// ###################################################################################################
	if(dbTEST('dogslife') && $package['clientptr'] == 47) 
		bagText(date('H:i')." [".print_r(date('Y-m-d', $start), 1)." - ".date('Y-m-d', $end)
							."] Week: ($weekNumber) Day: ".print_r(date('Y-m-d', $day), 1)
							." Svc: ".print_r($service, 1), 'MULTITEST');

	$packs = getCurrentClientPackages($oldAppt['clientptr'], 'tblrecurringpackage');
	$package = $packs[0];
	$allProviderRates = array();
	$standardRates = getStandardRates();
	require_once "pet-fns.php";
	$clientPets = getClientPetNames($package['clientptr']);

	// confirm that the provider's rate on the service is up-to-date
	if(!isset($allProviderRates[$appt['providerptr']])) 
		$allProviderRates[$appt['providerptr']] = getProviderRates($appt['providerptr']);
	$providerRates = $allProviderRates[$appt['providerptr']];

	$appt['appointmentid'] = null;
	$appt['date'] = date('Y-m-d', strtotime($newDate));
	$appt['rate'] = calculateServiceRate($appt['providerptr'], $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge'], $providerRates, $standardRates);
//global $db;if($db = 'yourdogsmiles' && $service['clientptr'] = 958) $service['note'] = "CALCED: {$service['rate']}";
	// If service is not scheduled for this day of the week, don't create an appontment
	$appt['serviceid'] = '0';
//print_r($appt);echo "<hr>";
	$appt = createAppointment('recurring', $package, $appt, $appt['date'], $canceledBecause=null, $simulation=false);
//print_r($appt);exit;
	$appt['custom'] = 1;
	updateTable('tblappointment', withModificationFields($appt), "appointmentid={$appt['appointmentid']}", 1);
	
	
	
	
	/*if(!$service['daysofweek'] || in_array($dayOfWeek, daysOfWeekArray($service['daysofweek']))) {
		// If appointment timeframe ended earlier today, don't create it
		if($day == $today) {
			$starttime = substr($service['timeofday'], 0, strpos($service['timeofday'], '-'));
			$endtime = substr($service['timeofday'], strpos($service['timeofday'], '-')+1);
			if(strtotime($endtime) < strtotime(($starttime))) 
				$endtime = strtotime(date('Y-m-d', strtotime("+ 1 day", $day))." $endtime");
			else $endtime = strtotime(date('Y-m-d', $day)." $endtime");
			if(strtotime(date('Y-m-d H:i')) > $endtime  && !$allowRetroactiveAppointments) continue;
		}
		$signature = getNewAppointmentSignature(
				array('date'=>date('Y-m-d', $day), 
							'servicecode'=>$service['servicecode'],
							'timeofday'=>$service['timeofday'],
							'clientptr'=>$service['clientptr']));
//echo "[$signature]<br>";									
//if($package['packageid'] == 678) echo ">> $signature<br>";
//echo ">> ".print_r($providerRates, 1)."<br>";exit;
		// CREATE appt no matter what if($preexistingAppointments && appointmentExists($signature, $preexistingAppointments, $preexistingAppointmentMatch)) continue;
		$oldAppt['date'] = date('Y-m-d', strtotime($newDate));
		$appt = createAppointment($recurring, $package, $oldAppt, $day, $canceledBecause=null, $simulation=false);
	} */
	// ###################################################################################################
	
	// HANDLE ASSOCIATED SURCHARGES
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		updateAppointmentAutoSurcharges($appt['appointmentid']);
	}
	

	// HANDLE DISCOUNTS
	if($currentDiscount) {
		resetAppointmentDiscountValue($appointmentid, $appt['charge']+$appt['adjustment']);
	}
	
	logChange($appointmentid, "tblappointment", 'm', "date|{$oldAppt['date']}=>{$appt['date']}||recurring|1");
	//print_r($appt);exit;
	return array('oldAppointment'=>$oldAppt, 'newAppointment'=>$appt);
}
// #######################################################################

function updateAppointment() {
	global $scheduleDiscount;
	$appointmentid = $_POST['appointmentid'];

	$changeableFields = explode(',', 'providerptr,cancellation,timeofday,pets,servicecode,charge,adjustment,rate,bonus,note,surchargenote'); // ,highpriority,completed
	$appt = array();
	foreach($changeableFields as $field) {
		$val = $_POST[$field];
		if($field == 'cancellation') {
			$appt['canceled'] = $val == 1 ? date("Y-m-d H:i") : null;
			$appt['completed'] = $val == 2 ? date("Y-m-d H:i") : null;
		}
		else if($field == 'timeofday') {
			$appt['timeofday'] = $val;
			$appt['starttime'] = date("H:i:00", strtotime(substr($val, 0, strpos($val, '-'))));
			$appt['endtime'] = date("H:i:00", strtotime(substr($val, strpos($val, '-')+1)));
		}
		else if($field == 'providerptr' && $_POST['providerptr']) {			
			require_once "provider-fns.php";
				
			global $misassignedAppts;
			if($val == '-1') $appt[$field] = 0;
			else if($_POST['providerptr'] && !fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = {$_POST['providerptr']} LIMIT 1")) {
				$appt[$field] = 0;
				$misassignedAppts[$appointmentid] = 'inactive';
			}
			else if($_POST['providerptr'] && providerIsOff($_POST['providerptr'], $_POST['date'], $_POST['timeofday'])) {
				$appt[$field] = 0;
				$misassignedAppts[$appointmentid] = 'timeoff';
			}
			else if(detectVisitCollision($_POST /* for date, timeofday, and servicecode */, $val)) {
				$appt[$field] = 0;
				$misassignedAppts[$appointmentid] = 'conflict';
			}

			else $appt[$field] = $val;
		}
		else $appt[$field] = $val;
	}
	$appt['highpriority'] = isset($_POST['highpriority']) ? 1 : 0;
	
	$customFieldsStr = "providerptr, timeofday, pets, servicecode, charge, adjustment, rate, bonus, note, highpriority, surchargenote";
	$oldAppt = fetchFirstAssoc("SELECT $customFieldsStr, completed, canceled, clientptr, packageptr, recurringpackage
	                            FROM tblappointment WHERE appointmentid = $appointmentid LIMIT 1");
	$customFields = explode(', ', $customFieldsStr);
	foreach($oldAppt as $field => $val) 
		if(in_array($field, $customFields) && $appt[$field] != $val) $appt['custom'] = 1;
	if(!$appt['pets']) $appt['pets'] = '--';
	updateTable('tblappointment', withModificationFields($appt), "appointmentid=$appointmentid", 1);
	$additionalSectionsToRedisplay = array();

	if($_SESSION['surchargesenabled']) {
		$providerChanged = $appt['providerptr'] != $oldAppt['providerptr'];
		if($providerChanged && in_array($_POST['packageCode'], array('MON', 'REC'))) {
			reassignExistingAppointmentSurcharges($appointmentid);
		}
		else if(!in_array($_POST['packageCode'], array('MON', 'REC'))) {
			require_once "surcharge-fns.php";
			$significantChange = 
				$providerChanged|| $appt['timeofday'] != $oldAppt['timeofday'];
			// If status goes incomplete => complete
			if($appt['completed'] && !$oldAppt['completed'] && !$oldAppt['canceled']) {
			// - - - if no other change has been made
				if(!$significantChange) {}
			// - - - - - - then no new surcharges should be created.
			// - - - else if time of day has changed
				else if($significantChange) {
			// - - - - - - then applicable auto surcharges should be created
					updateAppointmentAutoSurcharges($appointmentid);
				}
			}
			// If status goes canceled => complete
			else if($appt['completed'] && $oldAppt['canceled']) {
			// - - - then applicable auto surcharges should be created
				updateAppointmentAutoSurcharges($appointmentid);
			}
			else if($significantChange) {
				updateAppointmentAutoSurcharges($appointmentid);
			}

			if($appt['completed']) {
				$surchargesCompleted = markAppointmentSurchargesComplete($appointmentid);
				foreach($surchargesCompleted as $surcharge) $additionalSectionsToRedisplay[] = $surcharge['providerptr'];
			}
			else checkNonspecificSurcharges($_POST);
	}
}
	
	
/*	if($oldAppt['charge']+$oldAppt['adjustment'] != $appt['charge']+$appt['adjustment'])  // value changed
		resetAppointmentDiscountValue($appointmentid, $appt['charge']+$appt['adjustment']);
	else if((!$oldAppt['canceled'] && $appt['canceled'])) 
		setAppointmentDiscounts(array($appointmentid), $appt['completed'], 'force');
*/		
		
	$discount = $_POST['discount'];

	if($discount == -1 || (!$oldAppt['canceled'] && $appt['canceled']))
		setAppointmentDiscounts(array($appointmentid), $appt['completed'] && $discount != -1, 'force');
	else if($discount) {
		$currentDiscount = getAppointmentDiscount($appointmentid);
		$currentDiscount = $currentDiscount ? $currentDiscount['discountptr'] : null;
		$discount = explode('|', $discount );
		$discount = $discount[0];
//echo $memberid;exit;		
		if($discount == $currentDiscount &&
				($oldAppt['charge']+$oldAppt['adjustment'] != $appt['charge']+$appt['adjustment'])) {

			resetAppointmentDiscountValue($appointmentid, $appt['charge']+$appt['adjustment']);
		}
		else if($discount != $currentDiscount) {
//$error = "New discount: $discount	Old: $currentDiscount	";
			$scheduleDiscount = 
				array('clientptr'=>$clientptr, 'discountptr'=>$discount, 'start'=>date('Y-m-d'), 'memberid'=>$memberid);
			$numDiscountedAppts = applyScheduleDiscountWhereNecessary((string)$appointmentid);
			if($numDiscountedAppts == 0) $error = "Your changes were saved, but discount [$discount] could not be applied.";
//if($error && $_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo $error;exit; }			
		}
	}		

	$oldStatus = $oldAppt['canceled'] ? 'canceled' : ($oldAppt['completed'] ? 'completed' : 'incomplete');
	$newStatus = $appt['canceled'] ? 'canceled' : ($appt['completed'] ? 'completed' : 'incomplete');
	
	require_once "invoice-fns.php";
	if($oldStatus != 'canceled' && $newStatus == 'canceled') {
		if(!handleCancellationCredit($appt))
			recreateAppointmentBillable($appointmentid);
	}
	else recreateAppointmentBillable($appointmentid);
	
	// update nonrecurring package price
	if(!$oldAppt['recurringpackage']) {
		$packageid = findCurrentPackageVersion($oldAppt['packageptr'], $oldAppt['clientptr'], false);
		$price = calculateNonRecurringPackagePrice($packageid, $oldAppt['clientptr']);
		updateTable('tblservicepackage', array('packageprice'=>$price), "packageid = $packageid");
	}
	
	/*if($appt['completed'] && !$oldAppt['completed']) {
	}*/
	$appt['appointmentid'] = $appointmentid;
	if($newStatus != $oldStatus)
		logAppointmentStatusChange($appt, 'appointment editor');
	else logChange($appointmentid, 'tblappointment', 'm', 'Visit editor.  No status change.');
	//print_r($appt);exit;
	return array('oldAppointment'=>$oldAppt, 'newAppointment'=>$appt, 'additionalSectionsToRedisplay'=>$additionalSectionsToRedisplay);
}

/*
  `appointmentid` int(10) unsigned NOT NULL auto_increment,
  `serviceptr` int(10) unsigned NOT NULL default '0',
  `packageptr` int(10) unsigned NOT NULL default '0',
  `completed` datetime default NULL,
  `timeofday` varchar(45) NOT NULL default '0',
  `providerptr` int(10) unsigned NOT NULL default '0',
  `servicecode` int(10) unsigned NOT NULL default '0',
  `pets` varchar(45) NOT NULL default '',
  `charge` float(5,2) NOT NULL default '0.00',
  `adjustment` float(5,2) default NULL,
  `rate` float(5,2) NOT NULL default '0.00',
  `bonus` float(5,2) default NULL,
  `date` date NOT NULL default '0000-00-00',
  `clientptr` int(10) unsigned NOT NULL default '0',
  `canceled` datetime default NULL,
  `custom` tinyint(1) default NULL COMMENT 'Modified since creation',
  `starttime` time NOT NULL default '00:00:00',
  `endtime` time NOT NULL default '00:00:00',
  `highpriority` tinyint(1) default NULL,
*/


function getApptFields() {
	global $apptFields;
	if(!$apptFields) {
		$raw = explode(',', 'completed,Completed,timeofday,Time of Day,provider,Sitter,pets,Pets,servicecode,Service Type,'.
												'charge,Charge,adjustment,Adjustment,rate,Rate,bonus,Bonus,date,Date,client,Client, canceled,Canceled,'.
												'custom,Custom,status,Status,chargeline,Charge / Adjustment,rateline,Rate / Bonus,highpriority,High Priority,'.
												'totalcharge,Total Charge,totalpay,Total Pay,note,Note,packageType,Package Type,'.
												'cancelcomp,Pay sitter for canceled visit?,surchargenote,Surcharge Reason,discount,Discount');
		for($i=0;$i < count($raw) - 1; $i+=2) $apptFields[$raw[$i]] = $raw[$i+1];
	}
	return $apptFields;;
}

getApptFields();


function displayAppointmentEditor($source, $updateList=null) {
	global $apptFields;

	$changeDateEnabled = 
		dbTEST('dogslife')
		&& $source['appointmentid']
		&& (!$source['recurringpackage'] || mattOnlyTEST())
		&& $source['date'] >= date('Y-m-d')
		&& !$source['completed']
		&& !$source['canceled'];
	if($changeDateEnabled) {
		require_once "surcharge-fns.php";
		$dayUncanceledSurcharges = fetchAssociations(
			"SELECT *
				FROM tblsurcharge
				WHERE date = '{$source['date']}' 
							AND clientptr = {$source['clientptr']}
							AND canceled IS NULL", 1);
							
		foreach($dayUncanceledSurcharges as $surch) {
			$justification = justifySurcharge($surch);
			if(!$justification || !in_array($source['appointmentid'], $justification))
				continue;
			if($surch['completed']) $completedSurcharges[] = $surch;
		}
		$changeDateEnabled = !$completedSurcharges;
	}
	if($changeDateEnabled) $changeLink = 
		fauxLink('Change', "document.location.href=\"appt-change-date-dialog.php?id={$source['appointmentid']}\"",
							1, "Move visit to another day.");
		
	
	echo "<form name='appteditor' method='POST'>";
	echo "\n<table width=100%><tr><td>Client: ".
	       apptClientLink($source)."</td>\n<td>Date: ".displayDate(($source['date']))." $changeLink</td></tr>".
	       "<tr><td>";
	$status = $source['canceled'] ? 1 : ($source['completed'] ? 2 : 0);
	labeledRadioButton('Incomplete', 'cancellation', 0, $status, 'freezeApptEditor(0)');
	labeledRadioButton('Canceled', 'cancellation', 1, $status, 'freezeApptEditor(1)');
	labeledRadioButton('Completed', 'cancellation', 2, $status, 'freezeApptEditor(1)');
	echo "</td></tr></table>\n";
	$stati = array('incomplete','canceled','completed');
	hiddenElement('oldstatus', $stati[$status]);
	hiddenElement('updateList', $updateList);
	hiddenElement('appointmentid', $source['appointmentid']);
	hiddenElement('clientptr', $source['clientptr']);
	hiddenElement('oldCancelcomp', $source['cancelcomp']);
	hiddenElement('date', $source['date']);
	hiddenElement('payableid', $source['payableid']);
	hiddenElement('providerpaid', $source['providerpaid']);
	hiddenElement('oldproviderptr', $source['providerptr']);
	hiddenElement('dateproviderpaid', $source['dateproviderpaid']);
	hiddenElement('billableid', $source['billableid']);
	hiddenElement('billpaid', $source['billpaid']);
	hiddenElement('oldTotalCharge', $source['charge']+$source['adjustment']);
	hiddenElement('oldCharge', $source['charge']);
	hiddenElement('oldRate', $source['rate']);
	hiddenElement('packageCode', $source['packageCode']);
	hiddenElement('packageptr', $source['packageptr']);
	hiddenElement('date', $source['date']);
	hiddenElement('notifyclient', '');
	hiddenElement('deleteAction', '');
	echo "\n<hr>\n";
	echo "\n<table>\n";
	
	
	$packageTypeLink = $source['packageType'];
	require_once "service-fns.php";
	$apkid = findCurrentPackageVersion($source['packageptr'], $source['clientptr'], $source['recurringpackage'], $lastestIfNoneActive=true);

	$appack = getPackage($apkid);
	
	if(staffOnlyTEST() || dbTEST('pawlosophy')) {
		$url = $appack['irregular'] ? "service-irregular.php" : (
						$appack['onedaypackage'] ? "service-oneday.php" : (
						!$source['recurringpackage'] ? "service-nonrepeating.php" : (
						$appack['monthly'] ? "service-monthly.php" : "service-repeating.php")));
		$packageTypeLink = fauxLink($packageTypeLink, "if(window.opener) window.opener.location.href=\"$url?packageid=$apkid\"", 1, "Edit this schedule in main window.");
		$packageTypeRowStyle = 'height:35px;';
	}
//function labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
	
 	labelRow($apptFields['packageType'].':', '', $packageTypeLink, null, null, null, $packageTypeRowStyle);
 	
	$options = availableProviderSelectElementOptions($source['clientptr'], $source['date'],  '--Unassigned--');
	selectRow($apptFields['provider'].':', 'providerptr', $source['providerptr'], $options, 'updateAppointmentVals(this)');
	if($source['appointmentid'] && $source['providerptr'] && !providerInArray($source['providerptr'], $options)) {
		$selectProv = getProvider($source['providerptr']);
		$pName =  providerShortName($selectProv);
		$reason = providerNotListedReason($selectProv, $source);
		echo "<tr><td style='color:red;'colspan=2>This visit is assigned to $pName but should not be because $pName $reason.</td></tr>";
	}
	
	
	
	checkboxRow($apptFields['cancelcomp'], 'cancelcomp', $source['cancelcomp']);

	echo "<tr><td style='width:180px;'>{$apptFields['timeofday']}:</td>";
	echo "\n<td style='padding:0px;'>";
	buttonDiv("div_timeofday", "timeofday", "if(document.getElementById(\"cancellation_0\").checked) showTimeFramer(event, \"div_timeofday\")",
						($source['timeofday'] ? $source['timeofday'] : ''));
	echo "</td></tr>";

	echo "<tr><td>{$apptFields['pets']}:</td>";
	echo "\n<td style='padding:0px;'>";
	$source['pets'] = strip_tags($source['pets']);
	$petsTitle = getActiveClientPetsTip($source['clientptr'], $source['pets']);
	buttonDiv("div_pets", "pets", "if(document.getElementById(\"cancellation_0\").checked) showPetGrid(event, \"div_pets\")",
						 ($source['pets'] ? $source['pets'] : 'All Pets'), '', '', $petsTitle);
	echo "</td></tr>";
	
  $serviceTypes = getStandardRates();
  $serviceSelections = array_merge(array(''=>''), getServiceSelections());
	selectRow($apptFields['servicecode'].':', "servicecode", $source['servicecode'], $serviceSelections, 'updateAppointmentVals(this)');
	
	if($source['servicecode']) {
		if(!in_array($source['servicecode'], $serviceSelections)) {
			$inactiveServiceType = true;
			$servname = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$source['servicecode']} LIMIT 1");
			labelRow('', '', "Inactive Service: $servname", '', 'tiplooks');
		}
		$currentCharges = getStandardCharges();
		foreach(getClientCharges($source['clientptr']) as $code => $chrg) $currentCharges[$code] = $chrg;
		$currentCharge = (float)($currentCharges[$source['servicecode']]['defaultcharge']);

		$chargeWarning = ($inactiveServiceType || (float)$source['charge'] != $currentCharge) ? "(Saved charge is ".dollarAmount($source['charge']).")" : '';
	}
		
	$chargeWarningDeco = "title='The charge has changed for this service since the visit was last saved. Click Save Visit to apply changes to this visit.' style='text-decoration:underline'";
		
	echo "<tr><td>{$apptFields['charge']}:</td>";
	echo "<td><input id='charge' type='hidden' name='charge' size=2 value='{$source['charge']}'>
	          <div id='div_charge' style='display:inline'>$currentCharge</div> <span id='chargewarning' $chargeWarningDeco>$chargeWarning</span></td></tr>";
	          
	          
	echo "<tr><td colspan=2 id='extrapetchargediv'></td>";
	if($_SESSION['discountsenabled'] && $source['appointmentid']) {
		require_once "discount-fns.php";
		$discount = getAppointmentDiscount($source['appointmentid'], 'withLabel');
		hiddenElement('olddiscount', $discount['discountptr']);
		if($discount) $cDiscount = "Currently, a discount of ".dollarAmount($discount['amount'])." will be applied. ({$discount['label']})";
		echo "<tr id='currentdiscountrow'><td colspan=2>$cDiscount</td></tr>";
		echo "<tr id='newdiscountrow' style='display:none;'><td colspan=2>Discount will be calculated when appointment is saved.</td></tr>";
	}
	hiddenElement('currentdiscount', $discount['discountptr']);
	echo "<tr><td>{$apptFields['discount']}:</td>";
	echo "<td>";
	dumpServiceDiscountEditor(getClient($source['clientptr']), $includeLabel=false, $discount, $clientDefault=false);
	echo "</td></tr>";
	
	echo "<tr><td>{$apptFields['adjustment']}:</td>";
	echo "<td><input name='adjustment' id='adjustment' size=4 value='{$source['adjustment']}' autocomplete='off'></td></tr>";
	
	echo "<tr><td>{$apptFields['rate']}:</td>";
	
	$allPets = getClientPetNames($source['clientptr']);
	$currentRate = calculateServiceRate($source['providerptr'], $source['servicecode'], $source['pets'], 
								$allPets, $source['charge']);
	$actualRate = $source['rate'];
	
	$rateWarning = ($inactiveServiceType || $actualRate != $currentRate) ? "(Saved rate is ".dollarAmount($actualRate).")" : '';
	echo "<td><input id='rate' type='hidden' name='rate' size=2 value='{$source['rate']}'>
	          <div id='div_rate' style='display:inline'>{$actualRate}</div> $rateWarning</td></tr>";
// WHY IS $rateWarning SHOWN TWICE?!! if(mattOnlyTEST()) echo "<tr><td><font color=red>{$source['rate']}</font>";
	echo "<tr><td>{$apptFields['bonus']}:</td>";
	echo "<td><input name='bonus' id='bonus' size=4 value='{$source['bonus']}' autocomplete='off'></td></tr>"; 	
	echo "<tr><td>{$apptFields['surchargenote']}:</td>";
	echo "<td><input name='surchargenote' id='surchargenote' size=35 value='{$source['surchargenote']}' autocomplete='off'></td></tr>"; 	
	
	//radioButtonRow('', 'cancellation', $canceled, array('Canceled'=>1,'Active'=>0),'');
	
	checkboxRow($apptFields['highpriority'].':', 'highpriority', $source['highpriority']);
	
	//checkboxRow($apptFields['completed'].':', 'completed', $completed);
 	
 	if((dbTEST('jordanspetcare')) && ($packNotes = trim($appack['notes']))) {
		echo "<tr><td colspan=2>";
		$shortMax = 60;
		$style = 'color:darkgreen;font-style:italic;';
		if(strlen(strip_tags($packNotes)) > $shortMax) {
			$toggleToggle = '$("#shortpacknote").toggle();$("#packnote").toggle();';
			$shortPack = "<span id='shortpacknote' onclick='$toggleToggle'>
				Schedule Note: <span style='$style;cursor:pointer;' title='Click to see full note.'>".truncatedLabel(strip_tags($packNotes), $shortMax)."</span></span>";
			$hideFullNotes ="style='display:none;cursor:pointer;'";
		}
		$packNotes = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $packNotes));
		echo "$shortPack<div id='packnote' onclick='$toggleToggle'' $hideFullNotes>
					Schedule Note: <span style='$style'>$packNotes</span></div>";
			
	}

	textRow($apptFields['note'].':', 'note', $source['note'], $rows=3, $cols=60);
	
	if($source['appointmentid'] && $_SESSION['preferences']['includeOriginalNotesInVisitLists']) {
		$oldnote = fetchRow0Col0(
			"SELECT value 
				FROM tblappointmentprop 
				WHERE appointmentptr = {$source['appointmentid']} AND property = 'oldnote' LIMIT 1", 1);
		if($oldnote)
			textDisplayRow('Original Note:', $originalnote, $oldnote, $emptyTextDisplay=null, $labelClass=null, $inputClass='boxedtext', $rowId=null,  $rowStyle=null, $convertEOLs=true);
	}

	if($source['custom'])
 	  echo "<tr><td colspan=2>This appointment has been modified since the time it was first scheduled.</td></tr>";
 	
	echo "</table></form>";
	$disabled = $status ? true : false;
	echo <<<JS
<script language='javascript'>

function hideCancelcompdiv() {
	var hide = !document.getElementById('cancellation_1').checked;
	var cancelcompinput = document.getElementById('cancelcomp');
  cancelcompinput.parentNode.parentNode.style.display = hide ? 'none' : '{$_SESSION['tableRowDisplayMode']}';
  if(hide) cancelcompinput.checked = false;
}


function freezeApptEditor(disabled) {
	hideCancelcompdiv();
	// disable/enable all selects, one-liners, checkboxes, pets, and timeofday
	var targets = 'providerptr|servicecode|adjustment|bonus|highpriority|discount|memberid'.split('|');
	for(var i=0;i < targets.length; i++) if(document.getElementById(targets[i])) document.getElementById(targets[i]).disabled = disabled;
	
	var adiv = document.getElementById('timeofday');
	if(adiv) {
		adiv.parentNode.style.color = disabled ? 'gray' : 'black';
		adiv.parentNode.style.background = disabled ? 'lightgrey' : 'white';
	}
	adiv = document.getElementById('pets');
	if(adiv) {
		adiv.parentNode.style.color = disabled ? 'gray' : 'black';
		adiv.parentNode.style.background = disabled ? 'lightgrey' : 'white';
	}
}
freezeApptEditor($disabled);
hideCancelcompdiv();
</script>
JS;

}

function describeEZVisitTemplate($form) {
	foreach($form as $key => $val) {
		if(strpos($key, "day_")===0) $days[] = substr($key, strlen("day_"));
		if(strpos($key, "selected_")===0) {
			$n = substr($key, strlen("selected_"));
			if(!$form["timeframe_$n"]) continue;
			$timeframes[] = $form["timeframe_$n"].','.($form["excluded_$n"] ? '1' : '0').','.($form["templateservice_$n"] ? $form["templateservice_$n"] : '0');
		}
	}
	if($timeframes) 
	  return "days:".join(',', $days)."\n"
	  				.join("\n", $timeframes);
}

function templateFromForm($form) {
	foreach($form as $key => $val) {
		if(strpos($key, "day_")===0) $days[] = substr($key, strlen("day_"));
		if(strpos($key, "selected_")===0) {
			$n = substr($key, strlen("selected_"));
			if(!$form["timeframe_$n"]) continue;
			$timeframes[] = array($form["timeframe_$n"], ($form["excluded_$n"] ? '1' : '0'),  ($form["templateservice_$n"] ? $form["templateservice_$n"] : '0'));
		}
	}
	return array('daysofweek'=>$days, 'timeframes'=>$timeframes);
}

function displayEZVisitTemplate($clientptr) {
	require_once "timeframe-fns.php";

	$templateRows = fetchRow0Col0("SELECT value FROM tblclientpref WHERE clientptr = $clientptr AND property = 'ezvisittemplate' LIMIT 1");
	// note max template size is 255 chars per tblclientpref
	// template makeup:
	// days:M,Tu,W,Th,Fri,Sa,Su  (max)
	// timeframe1,excludelastday1
	// [timeframe2,excludelastday2
	// [timeframe3,excludelastday3
	// [timeframe4,excludelastday4
	// [...]]]]
	// timeframe = 12:00 am - 12:00 pm
	if($templateRows) {
		$templateRows = explode("\n", $templateRows);
		$template['daysofweek'] = explode(',', substr($templateRows[0], strlen('days:')));
		for($i=1; $i < count($templateRows);$i++) {
			$frame = explode(',',$templateRows[$i]);
			$template['timeframes'][$i] =  array($frame[0], 1, $frame[1], $frame[2]);
		}
	}

	$servicetimes = fetchTimeframes();
//if(staffOnlyTEST()) print_r($servicetimes);	
	$menuChoices = array_merge(array('--Choose--'=>0), getTimeframeMenuChoices());
	$numRows = 4;
	for($i=1;$i <= $numRows; $i++) {
		$timeframe = $template['timeframes'][$i];
		if($timeframe) $timeframes[$i] = $timeframe;
		else if(!$template) $timeframes[$i] = array(next($menuChoices));
	}
	$serviceSelections = array_merge(array('Above Service Type'=>''), getServiceSelections());
	foreach($servicetimes as $label => $time) {
		$n++;
		$row['timeframe'] = selectElement('', "timeframe_$n", $timeframes[$n][0], $menuChoices, 'clearLine(this)', $labelClass=null, $inputClass=null, $noEcho=true);
		$row['selected'] = labeledCheckbox('', "selected_$n", $timeframes[$n][1], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=true);
		$row['exclude'] = labeledCheckbox('', "excluded_$n", $timeframes[$n][2], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=true);
		$row['service'] = selectElement('', "templateservice_$n", $timeframes[$n][3], $serviceSelections, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=true)	;

		
		
		$rows[] = $row;
		if(count($rows)==$numRows) break;
	}
	$columns = explodePairsLine('timeframe|Time Frame||selected|Copy||exclude|Exclude<br>Last Day||service|Service Type');
	$colClasses = array('selected'=>'center', 'exclude'=>'center');
	echo "<table><tr><td>";
	tableFrom($columns, $rows, '', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
	echo "<style>.dowtable td {padding-right:5px;}</style>";
	echo "<table class='dowtable'><tr>";
	$days = explode(',', 'M,T,W,Th,F,Sa,Su');
	$rawDays = $template['daysofweek'] ? $template['daysofweek'] : $days;
	foreach($rawDays as $day) $selectedDays[$day] = 1;
	foreach($days as $day) echo "<th>$day</th>";
	echo "</tr><tr>";
	foreach($days as $day) echo "<td>".labeledCheckbox('', "day_$day", $selectedDays[$day], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=true)."</td>";
	echo "</tr></table>";
	echo "</td></tr></table>";
}	


function displayEZVisitEditor($source, $updateList=null, $notificationPrefs=null) {
	if($_SESSION['frameLayout'] == 'fullScreenTabletView' && isIPad()) return displayTabbedEZVisitEditor($source, $updateList, $notificationPrefs);  // 
	global $apptFields;
	echo "<form name='appteditor' method='POST'>";
	$status = $source['canceled'] ? 1 : ($source['completed'] ? 2 : 0);
	$stati = array('incomplete','canceled','completed');
	hiddenElement('oldstatus', $stati[$status]);
	hiddenElement('updateList', $updateList);
	hiddenElement('appointmentid', $source['appointmentid']);
	hiddenElement('clientptr', $source['clientptr']);
	hiddenElement('oldCancelcomp', $source['cancelcomp']);
	hiddenElement('date', $source['date']);
	hiddenElement('payableid', $source['payableid']);
	hiddenElement('providerpaid', $source['providerpaid']);
	hiddenElement('oldproviderptr', $source['providerptr']);
	hiddenElement('dateproviderpaid', $source['dateproviderpaid']);
	hiddenElement('billableid', $source['billableid']);
	hiddenElement('billpaid', $source['billpaid']);
	hiddenElement('oldTotalCharge', $source['charge']+$source['adjustment']);
	hiddenElement('oldCharge', $source['charge']);
	hiddenElement('oldRate', $source['rate']);
	hiddenElement('packageCode', $source['packageCode']);
	hiddenElement('packageptr', $source['packageptr']);
	hiddenElement('date', $source['date']);
	hiddenElement('notifyclient', '');
	echo "\n<table width=100%>\n";
	echo "<tr>
	<td style='text-align:left;font-size:1.1em;'>Client: ".apptClientLink($source)."</td><td style='text-align:right;'>";
	$operation = $source['appointmentid'] ? 'Save' : 'Add';
	echoButton('', "$operation Visit", "checkAndSubmit()");
	echo " ";
	echoButton('', "$operation Visit & Notify Client", "checkAndSubmit(\"notify\")");
	echo " ";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');

	echo "</td></tr><tr><td colspan=2><hr></tr>";	
	echo "</table>";
	echo "<table border=0>";
	// TIME OF DAY
	echo "<tr><td style=''>{$apptFields['timeofday']}:</td>";
	echo "\n<td style='padding:4px;'>";
	buttonDiv("div_timeofday", "timeofday", "if(document.getElementById(\"cancellation_0\").checked) showTimeFramer(event, \"div_timeofday\")",
						($source['timeofday'] ? $source['timeofday'] : ''));
	echo "</td></tr>";

	// SERVICE
  $serviceSelections = array_merge(array(''=>''), getServiceSelections());
	selectRow($apptFields['servicecode'].':', "servicecode", $source['servicecode'], $serviceSelections, 'updateAppointmentVals(this)');
	
	if($source['servicecode']) {
		if(!in_array($source['servicecode'], $serviceSelections)) {
			$servname = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$source['servicecode']} LIMIT 1");
			labelRow('', '', "Inactive Service: $servname", '', 'tiplooks');
		}
		$currentCharges = getClientCharges($source['clientptr']);
		$currentCharge = (float)$currentCharges[$source['servicecode']];
		$chargeWarning = (float)$source['charge'] != $currentCharge ? "(Saved charge is ".dollarAmount($source['charge']).")" : '';
	}
		
 	// SITTER
	$options = availableProviderSelectElementOptions($source['clientptr'], $source['date'],  '--Unassigned--');
	selectRow($apptFields['provider'].':', 'providerptr', $source['providerptr'], $options, 'updateAppointmentVals(this)');
	if($source['appointmentid'] && $source['providerptr'] && !providerInArray($source['providerptr'], $options)) {
		$selectProv = getProvider($source['providerptr']);
		$pName =  providerShortName($selectProv);
		$reason = providerNotListedReason($selectProv, $source);
		echo "<tr><td style='color:red;'colspan=2>This visit is assigned to $pName but should not be because $pName $reason.</td></tr>";
	}

	// PETS
	echo "<tr><td>{$apptFields['pets']}:</td>";
	echo "\n<td style='padding:4px;'>";
	$source['pets'] = strip_tags($source['pets']);
	$petsTitle = getActiveClientPetsTip($source['clientptr'], $source['pets']);
	buttonDiv("div_pets", "pets", "if(document.getElementById(\"cancellation_0\").checked) showPetGrid(event, \"div_pets\")",
						 ($source['pets'] ? $source['pets'] : 'All Pets'), '', '', $petsTitle);
	echo "</td></tr>";
	
	// COPIES
	$copyOptions = array('No Copies'=>0, 
												'All Days'=>'all', 
												'All Future Days'=>'allfuture', 
												'Every Other Future Day'=>'futureskip_2', 
												'Every Third Future Day'=>'futureskip_3', 
												'Every Weekday'=>'weekdays',
												'Template' => 'template');
												
	if(TRUE) { // dbTEST('k9krewe')
		array_pop($copyOptions);
		$otherOptions = explodePairsLine('Every Week|futureskip_7||Every Other Week|futureskip_14||Every Three Weeks|futureskip_21||Every Four Weeks|futureskip_28');
		foreach($otherOptions as $k=>$v) $copyOptions[$k] = $v;
		$copyOptions['Template'] = 'template';
	}
		
	selectRow('Copy To:', 'copies', null, $copyOptions, 'updateAppointmentVals(this)');
	
	echo "<tr id='templaterow' style='display:none;'><td colspan=2>";
	displayEZVisitTemplate($source['clientptr']);
	echo "</td></tr>";		
		
	echo "<tr><td>";
	echoButton('', 'Show More Details', "toggleOptionalRows(this)");
	
	echo "</td>";
	// STATUS
	echo "<tr class='optionalrow'><td colspan=2>";
	ob_start();
	$status = $source['canceled'] ? 1 : ($source['completed'] ? 2 : 0);
	labeledRadioButton('Incomplete', 'cancellation', 0, $status, 'freezeApptEditor(0)');
	labeledRadioButton('Canceled', 'cancellation', 1, $status, 'freezeApptEditor(1)');
	labeledRadioButton('Completed', 'cancellation', 2, $status, 'freezeApptEditor(1)');
	checkboxRow($apptFields['cancelcomp'], 'cancelcomp', $source['cancelcomp']);
	echo "</td>";
	// CHARGE
	echo "<tr class='optionalrow'><td>{$apptFields['charge']}:</td>";
	$chargeWarningDeco = "title='The charge has changed for this service since the visit was last saved. Click Update Visit to apply changes to this visit.' style='text-decoration:underline'";
	echo "<td><input id='charge' type='hidden' name='charge' size=2 value='{$source['charge']}'>
	          <div id='div_charge' style='display:inline'>$currentCharge</div> <span id='chargewarning' $chargeWarningDeco>$chargeWarning</span></td></tr>";
	echo "<tr class='optionalrow'><td colspan=2 id='extrapetchargediv'></td>";
	if($_SESSION['discountsenabled'] && $source['appointmentid']) {
		require_once "discount-fns.php";
		$discount = getAppointmentDiscount($source['appointmentid'], 'withLabel');
		hiddenElement('olddiscount', $discount['discountptr']);
		if($discount) $cDiscount = "Currently, a discount of ".dollarAmount($discount['amount'])." will be applied. ({$discount['label']})";
		echo "<tr id='currentdiscountrow' class='optionalrow'><td colspan=2>$cDiscount</td></tr>";
		echo "<tr id='newdiscountrow' style='display:none;' class='optionalrow'><td colspan=2>Discount will be calculated when appointment is saved.</td></tr>";
	}
 	
	
	// DISCOUNT
	hiddenElement('currentdiscount', $discount['discountptr']);
	echo "<tr class='optionalrow'><td>{$apptFields['discount']}:</td>";
	echo "<td>";
	$clientDefault = $source['appointmentid'] ? false : true;
	dumpServiceDiscountEditor(getClient($source['clientptr']), $includeLabel=false, $discount, $clientDefault);
	echo "</td></tr>";
	
	echo "<tr class='optionalrow'><td>{$apptFields['adjustment']}:</td>";
	echo "<td><input name='adjustment' id='adjustment' size=4 value='{$source['adjustment']}' autocomplete='off'></td></tr>";
	
	echo "<tr class='optionalrow'><td>{$apptFields['rate']}:</td>";
	
	$allPets = getClientPetNames($source['clientptr']);
	$currentRate = calculateServiceRate($source['providerptr'], $source['servicecode'], $source['pets'], 
								$allPets, $source['charge']);
	$actualRate = $source['rate'];
	$rateWarning = $actualRate != $currentRate ? "(Saved rate is ".dollarAmount($actualRate).")" : '';
	
	//global $db;if($db = 'yourdogsmiles' && $source['clientptr'] = 958) $rateWarning .= "[{$actualRate}] [{$currentRate}]";

	echo "<td><input id='rate' type='hidden' name='rate' size=2 value='{$source['rate']}'>
	          <div id='div_rate' style='display:inline'>{$source['rate']}</div> $rateWarning</td></tr>";
	echo "<tr class='optionalrow'><td>{$apptFields['bonus']}:</td>";
	echo "<td><input name='bonus' id='bonus' size=4 value='{$source['bonus']}' autocomplete='off'></td></tr>"; 	
	echo "<tr class='optionalrow'><td>{$apptFields['surchargenote']}:</td>";
	echo "<td><input name='surchargenote' id='surchargenote' size=35 value='{$source['surchargenote']}' autocomplete='off'></td></tr>"; 	
	
	//radioButtonRow('', 'cancellation', $canceled, array('Canceled'=>1,'Active'=>0),'');
	//$labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null
	checkboxRow($apptFields['highpriority'].':', 'highpriority', $source['highpriority'], null, null, null, null, null, 'optionalrow');
	
	//checkboxRow($apptFields['completed'].':', 'completed', $completed);
 	

	textRow($apptFields['note'].':', 'note', $source['note'], $rows=3, $cols=60, null, null, null, null, null, 'optionalrow');

	if($source['appointmentid'] && $_SESSION['preferences']['includeOriginalNotesInVisitLists']) {
		$oldnote = fetchRow0Col0(
			"SELECT value 
				FROM tblappointmentprop 
				WHERE appointmentptr = {$source['appointmentid']} AND property = 'oldnote' LIMIT 1", 1);
		if($oldnote)
			textDisplayRow('Original Note:', $originalnote, $oldnote, $emptyTextDisplay=null, $labelClass=null, $inputClass='boxedtext', $rowId=null,  $rowStyle=null, $convertEOLs=true, $rowClass='optionalrow');
	}
	
	if($source['custom'])
 	  echo "<tr><td colspan=2>This appointment has been modified since the time it was first scheduled.</td></tr>";
	
 	//labelRow($apptFields['packageType'].':', '', $source['packageType']);
	echo "</tr><tr><td colspan=2><hr></tr>";	
	echo "<tr ><td colspan=2 style='text-align: right'>";
	echoButton('', "$operation Visit", "checkAndSubmit()");
	echo " ";
	echoButton('', "$operation Visit & Notify Client", "checkAndSubmit(\"notify\")");
	echo " ";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
	echo "</tr>";
	echo "<tr><td colspan=2>$notificationPrefs</tr>";
	echo "</table></form>";
	$disabled = $status ? true : false;
	echo <<<JS
<script language='javascript'>
function toggleOptionalRows(button) {
	var mode = button.value.toLowerCase().indexOf('show') != -1 ? '{$_SESSION['tableRowDisplayMode']}' : 'none';
	button.value = mode == 'none' ? 'Show More Details' : 'Hide Details';
	$(".optionalrow").css("display", mode);
	//$("window").css("overflow-y", (mode == 'none' ? 'hidden' : 'scroll' ));
}

function hideCancelcompdiv() {
	var hide = !document.getElementById('cancellation_1').checked;
	var cancelcompinput = document.getElementById('cancelcomp');
  cancelcompinput.parentNode.parentNode.style.display = hide ? 'none' : '{$_SESSION['tableRowDisplayMode']}';
  if(hide) cancelcompinput.checked = false;
}


function freezeApptEditor(disabled) {
	hideCancelcompdiv();
	// disable/enable all selects, one-liners, checkboxes, pets, and timeofday
	var targets = 'providerptr|servicecode|adjustment|bonus|highpriority|discount|memberid'.split('|');
	for(var i=0;i < targets.length; i++) if(document.getElementById(targets[i])) document.getElementById(targets[i]).disabled = disabled;
	
	var adiv = document.getElementById('timeofday');
	if(adiv) {
		adiv.parentNode.style.color = disabled ? 'gray' : 'black';
		adiv.parentNode.style.background = disabled ? 'lightgrey' : 'white';
	}
	adiv = document.getElementById('pets');
	if(adiv) {
		adiv.parentNode.style.color = disabled ? 'gray' : 'black';
		adiv.parentNode.style.background = disabled ? 'lightgrey' : 'white';
	}
}
freezeApptEditor($disabled);
hideCancelcompdiv();
$(".optionalrow").css('display', 'none');
</script>
JS;

}  // END displayEZEditor

function displayTabbedEZVisitEditor($source, $updateList=null, $notificationPrefs=null) {
	global $apptFields;
	echo "<form name='appteditor' method='POST'>";
	$status = $source['canceled'] ? 1 : ($source['completed'] ? 2 : 0);
	$stati = array('incomplete','canceled','completed');
	hiddenElement('oldstatus', $stati[$status]);
	hiddenElement('updateList', $updateList);
	hiddenElement('appointmentid', $source['appointmentid']);
	hiddenElement('clientptr', $source['clientptr']);
	hiddenElement('oldCancelcomp', $source['cancelcomp']);
	hiddenElement('date', $source['date']);
	hiddenElement('payableid', $source['payableid']);
	hiddenElement('providerpaid', $source['providerpaid']);
	hiddenElement('oldproviderptr', $source['providerptr']);
	hiddenElement('dateproviderpaid', $source['dateproviderpaid']);
	hiddenElement('billableid', $source['billableid']);
	hiddenElement('billpaid', $source['billpaid']);
	hiddenElement('oldTotalCharge', $source['charge']+$source['adjustment']);
	hiddenElement('oldCharge', $source['charge']);
	hiddenElement('oldRate', $source['rate']);
	hiddenElement('packageCode', $source['packageCode']);
	hiddenElement('packageptr', $source['packageptr']);
	hiddenElement('date', $source['date']);
	hiddenElement('notifyclient', '');
	echo "\n<table width=100%>\n";
	echo "<tr>
	<td style='text-align:left;font-size:1.1em;'>Client: ".apptClientLink($source)."</td><td style='text-align:right;'>";
	$operation = $source['appointmentid'] ? 'Save' : 'Add';
	echoButton('', "$operation Visit", "checkAndSubmit()");
	echo " ";
	echoButton('', "$operation Visit & Notify Client", "checkAndSubmit(\"notify\")");
	echo " ";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');

	echo "</td></tr>";	
	echo "</table>";
	$labelAndIds = array("basic"=>'Basic Info', "details"=>'Details');
	$tabWidths = array('##default##'=>87);
	startTabBox(540, $labelAndIds, 'basic', $tabWidths);
	startFixedHeightTabPage('basic', 'basic', $labelAndIds, $boxHeight);

	echo "<table border=0 bordercolor=red>";
	// TIME OF DAY
	echo "<tr><td style=''>{$apptFields['timeofday']}:</td>";
	echo "\n<td style='padding:4px;'>";
	buttonDiv("div_timeofday", "timeofday", "if(document.getElementById(\"cancellation_0\").checked) showTimeFramer(event, \"div_timeofday\")",
						($source['timeofday'] ? $source['timeofday'] : ''));
	echo "</td></tr>";

	// SERVICE
  $serviceSelections = array_merge(array(''=>''), getServiceSelections());
	selectRow($apptFields['servicecode'].':', "servicecode", $source['servicecode'], $serviceSelections, 'updateAppointmentVals(this)');
	
	if($source['servicecode']) {
		if(!in_array($source['servicecode'], $serviceSelections)) {
			$servname = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$source['servicecode']} LIMIT 1");
			labelRow('', '', "Inactive Service: $servname", '', 'tiplooks');
		}
		$currentCharges = getClientCharges($source['clientptr']);
		$currentCharge = (float)$currentCharges[$source['servicecode']];
		$chargeWarning = (float)$source['charge'] != $currentCharge ? "(Saved charge is ".dollarAmount($source['charge']).")" : '';
	}
		
 	// SITTER
	$options = availableProviderSelectElementOptions($source['clientptr'], $source['date'],  '--Unassigned--');
	selectRow($apptFields['provider'].':', 'providerptr', $source['providerptr'], $options, 'updateAppointmentVals(this)');
	if($source['appointmentid'] && $source['providerptr'] && !providerInArray($source['providerptr'], $options)) {
		$selectProv = getProvider($source['providerptr']);
		$pName =  providerShortName($selectProv);
		$reason = providerNotListedReason($selectProv, $source);
		echo "<tr><td style='color:red;'colspan=2>This visit is assigned to $pName but should not be because $pName $reason.</td></tr>";
	}

	// PETS
	echo "<tr><td>{$apptFields['pets']}:</td>";
	echo "\n<td style='padding:4px;'>";
	$source['pets'] = strip_tags($source['pets']);

	$petsTitle = getActiveClientPetsTip($source['clientptr'], $source['pets']);
	buttonDiv("div_pets", "pets", "if(document.getElementById(\"cancellation_0\").checked) showPetGrid(event, \"div_pets\")",
						 ($source['pets'] ? $source['pets'] : 'All Pets'), '', '', $petsTitle);
	echo "</td></tr>";
	
	// COPIES
	$copyOptions = array('No Copies'=>0, 
												'All Days'=>'all', 
												'All Future Days'=>'allfuture', 
												'Every Other Future Day'=>'futureskip_2', 
												'Every Third Future Day'=>'futureskip_3', 
												'Every Weekday'=>'weekdays',
												'Template' => 'template');
	selectRow('Copy To:', 'copies', null, $copyOptions, 'updateAppointmentVals(this)');
	
	echo "<tr id='templaterow' style='display:none;'><td colspan=2>";
	displayEZVisitTemplate($source['clientptr']);
	echo "</td></tr>";		
	echo "</table>";
	endTabPageSansNav();
	
	
	startFixedHeightTabPage('details', 'basic', $labelAndIds, $boxHeight);

	
	// STATUS
	echo "<table border=0 bordercolor=blue>";
	echo "<tr><td colspan=2>";
	$status = $source['canceled'] ? 1 : ($source['completed'] ? 2 : 0);
	labeledRadioButton('Incomplete', 'cancellation', 0, $status, 'freezeApptEditor(0)');
	labeledRadioButton('Canceled', 'cancellation', 1, $status, 'freezeApptEditor(1)');
	labeledRadioButton('Completed', 'cancellation', 2, $status, 'freezeApptEditor(1)');
	checkboxRow($apptFields['cancelcomp'], 'cancelcomp', $source['cancelcomp']);
	echo "</td>";
	// CHARGE
	echo "<tr><td>{$apptFields['charge']}:</td>";
	echo "<td><input id='charge' type='hidden' name='charge' size=2 value='{$source['charge']}'>
	          <div id='div_charge' style='display:inline'>$currentCharge</div> $chargeWarning</td></tr>";
	echo "<tr><td colspan=2 id='extrapetchargediv'></td>";
	if($_SESSION['discountsenabled'] && $source['appointmentid']) {
		require_once "discount-fns.php";
		$discount = getAppointmentDiscount($source['appointmentid'], 'withLabel');
		hiddenElement('olddiscount', $discount['discountptr']);
		if($discount) $cDiscount = "Currently, a discount of ".dollarAmount($discount['amount'])." will be applied. ({$discount['label']})";
		echo "<tr id='currentdiscountrow'><td colspan=2>$cDiscount</td></tr>";
		echo "<tr id='newdiscountrow' style='display:none;'><td colspan=2>Discount will be calculated when appointment is saved.</td></tr>";
	}
 	
	
	// DISCOUNT
	hiddenElement('currentdiscount', $discount['discountptr']);
	echo "<tr><td>{$apptFields['discount']}:</td>";
	echo "<td>";
	$clientDefault = $source['appointmentid'] ? false : true;
	dumpServiceDiscountEditor(getClient($source['clientptr']), $includeLabel=false, $discount, $clientDefault);
	echo "</td></tr>";
	
	echo "<tr><td>{$apptFields['adjustment']}:</td>";
	echo "<td><input name='adjustment' id='adjustment' size=2 value='{$source['adjustment']}' autocomplete='off'></td></tr>";
	
	echo "<tr><td>{$apptFields['rate']}:</td>";
	
	$allPets = getClientPetNames($source['clientptr']);
	$currentRate = calculateServiceRate($source['providerptr'], $source['servicecode'], $source['pets'], 
								$allPets, $source['charge']);
	$actualRate = $source['rate'];
	$rateWarning = $actualRate != $currentRate ? "(Saved rate is ".dollarAmount($actualRate).")" : '';
	
	//global $db;if($db = 'yourdogsmiles' && $source['clientptr'] = 958) $rateWarning .= "[{$actualRate}] [{$currentRate}]";

	echo "<td><input id='rate' type='hidden' name='rate' size=2 value='{$source['rate']}'>
	          <div id='div_rate' style='display:inline'>{$source['rate']}</div> $rateWarning</td></tr>";
	echo "<tr><td>{$apptFields['bonus']}:</td>";
	echo "<td><input name='bonus' id='bonus' size=2 value='{$source['bonus']}' autocomplete='off'></td></tr>"; 	
	echo "<tr><td>{$apptFields['surchargenote']}:</td>";
	echo "<td><input name='surchargenote' id='surchargenote' size=35 value='{$source['surchargenote']}' autocomplete='off'></td></tr>"; 	
	
	//radioButtonRow('', 'cancellation', $canceled, array('Canceled'=>1,'Active'=>0),'');
	//$labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null
	checkboxRow($apptFields['highpriority'].':', 'highpriority', $source['highpriority'], null, null, null, null, null, 'optionalrow');
	
	//checkboxRow($apptFields['completed'].':', 'completed', $completed);
 	

	textRow($apptFields['note'].':', 'note', $source['note'], $rows=3, $cols=60, null, null, null, null, null, 'optionalrow');

	if($source['custom'])
 	  echo "<tr><td colspan=2>This appointment has been modified since the time it was first scheduled.</td></tr>";
	
 	//labelRow($apptFields['packageType'].':', '', $source['packageType']);
 	
 	echo "</table>";
	endTabPageSansNav();
	endTabBox();
	
	
	echo "<tr><td colspan=2>$notificationPrefs</tr>";
	echo "</table></form>";
	$disabled = $status ? true : false;
	echo <<<JS
<script language='javascript'>
function hideCancelcompdiv() {
	var hide = !document.getElementById('cancellation_1').checked;
	var cancelcompinput = document.getElementById('cancelcomp');
  cancelcompinput.parentNode.parentNode.style.display = hide ? 'none' : '{$_SESSION['tableRowDisplayMode']}';
  if(hide) cancelcompinput.checked = false;
}


function freezeApptEditor(disabled) {
	hideCancelcompdiv();
	// disable/enable all selects, one-liners, checkboxes, pets, and timeofday
	var targets = 'providerptr|servicecode|adjustment|bonus|highpriority|discount|memberid'.split('|');
	for(var i=0;i < targets.length; i++) if(document.getElementById(targets[i])) document.getElementById(targets[i]).disabled = disabled;
	
	var adiv = document.getElementById('timeofday'); if(adiv) { adiv.parentNode.style.color = disabled ? 'gray' : 'black'; adiv.
	parentNode.style.background = disabled ? 'lightgrey' : 'white'; } adiv = document.getElementById('pets'); if(adiv) { adiv.parentNode.
	style.color = disabled ? 'gray' : 'black'; adiv.parentNode.style.background = disabled ? 'lightgrey' : 'white'; } } freezeApptEditor(
	$disabled); hideCancelcompdiv();


JS;
dumpClickTabJS();
echo "</script>";

}  // END displayTabbedEZVisitEditor

function ezDayDisplay($date, $extraStyle) {
	$time = strtotime($date);
	$dow = date('l', $time);
	$dom = date('j', $time);
	$mon = date('F', $time);
	$year = date('Y', $time);
	?>
	<style>
	.oneDayCalendarPage {border:solid black 2px;background:white;width:90px}
	.oneDayCalendarPage td {padding-top:0px;padding-bottom:0px;}
	.monthline {font-size:8pt;}
	.domline {font-size:16pt;font-weight:bold;text-align:center;}
	.dowline {font-size:8pt;;text-align:center;}
	</style>
	<?
	return "<table class='oneDayCalendarPage' style='$extraStyle'>
	<tr class='monthline'><td>$mon</td><td style='text-align:right'>$year</td></tr>
	<tr class='domline'><td colspan=2>$dom</td></tr>
	<tr class='dowline'><td colspan=2>$dow</td></tr>
</table>";
}

function copyAppointments($ids, $packageid, $target, $clientid, $providerptr=-1) {
	require_once "service-fns.php";

	$originals = fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE appointmentid IN ($ids)", 'appointmentid');
	$sigs = array();
	// discard duplicates
	foreach($originals as $i => $appt) {
		if($providerptr != -1) $originals[$i]['providerptr'] = $providerptr;
		$sigs[$appt['timeofday'].'|'.$appt['servicecode']] = $originals[$i];
	}


	$packageid =findCurrentPackageVersion($packageid, $clientid, !"recurring");
	$history = findPackageIdHistory($packageid, $clientid, false);
	$history = join(',', $history);
	$existingAppts = fetchAssociations("SELECT date, CONCAT_WS('|',timeofday, servicecode) as sig
															FROM tblappointment 
															WHERE packageptr IN ($history)");
	$existingSigs = array();
	foreach($existingAppts as $appt) $existingSigs[$appt['date']][] = $appt['sig'];

	$interval = 1;
	if(is_array($target)) { // template
		// do nothing.  see below
	}
	else if($target == 'all' || $target == 'weekdays')
		$range = fetchFirstAssoc("SELECT startdate, enddate FROM tblservicepackage WHERE packageid = $packageid LIMIT 1");
	else if(strpos($target, 'allfuture_') === 0) {
		$day = date('Y-m-d', strtotime("+1 day", strtotime(substr($target, strlen('allfuture_')))));
		$range = fetchFirstAssoc("SELECT '$day' as startdate, enddate FROM tblservicepackage WHERE packageid = $packageid LIMIT 1");
	}
	else if(strpos($target, 'futureskip_') === 0) {
		$skipIntervalStart = explode('_', $target);
		$interval = $skipIntervalStart[1];
		$day = date('Y-m-d', strtotime("+$interval days", strtotime($skipIntervalStart[2])));
		$range = fetchFirstAssoc("SELECT '$day' as startdate, enddate FROM tblservicepackage WHERE packageid = $packageid LIMIT 1");
	}
	else $range = array('startdate'=>$target, 'enddate'=>$target); 
	
	$oldVersions = array();
	
	if(is_array($target)) applyEZCopyTemplate($originals, $existingSigs, $packageid, $target, $clientid, $oldVersions);
	else for($day = $range['startdate']; $day <= $range['enddate']; $day = date('Y-m-d', strtotime("+$interval days", strtotime($day)))) {
		if($target == 'weekdays' && in_array(date('D', strtotime($day)), array('Sun', 'Sat'))) continue;
		foreach($sigs as $sig => $appt)
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "<p>({$range['startdate']} - {$range['enddate']}) APP: $sig =>".print_r($existingSigs[$day], 1); if($nnn > 1) exit;$nnn=1; }
			if(!($existingSigs[$day] && in_array($sig, $existingSigs[$day]))) {
				// create copy on $day;
				$newTask = array_merge($appt);
				unset($newTask['completed']); 
				unset($newTask['appointmentid']); 
				unset($newTask['canceled']); 
				unset($newTask['cancellationreason']); 
				unset($newTask['pendingchange']); 
				unset($newTask['modified']); 
				unset($newTask['modifiedby']);
				unset($newTask['serviceptr']);
				$newTask['serviceid'] = '0';
				$newTask['providerptr'] = $newTask['providerptr'] ? $newTask['providerptr'] : "0";
				$newTask['packageptr'] = $packageid;
				$newappt = createAppointment(false, null, $newTask, strtotime($day));
				if($_SESSION['surchargesenabled']) {
					require_once "surcharge-fns.php";
					updateAppointmentAutoSurcharges($newappt);  // NEVER HAPPENS!
				}
				$oldVersions[$newappt['appointmentid']] = $appt['appointmentid'];
			}
	}
	if($originals) 
		$discounts = fetchAssociationsKeyedBy("SELECT * FROM relapptdiscount WHERE appointmentptr IN (".join(',', array_keys($originals)).")", 
																				'appointmentptr');

	if($oldVersions) { // $discounts && -- when no discounts, STILL need to applyScheduleDiscountWhereNecessary
		require_once "discount-fns.php";
		global $scheduleDiscount;
		foreach($oldVersions as $newapptid => $apptid) {
			$scheduleDiscount = 
					($discount = $discounts[$apptid])
					? array('clientptr'=>$clientid, 'discountptr'=>$discount['discountptr'], 'start'=>date('Y-m-d'), 'memberid'=>$discount['memberid'])
					: null;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo "scheduleDiscount: [".print_r($scheduleDiscount,1)."]<br>";
					
			applyScheduleDiscountWhereNecessary((string)$newapptid);
		}
	}
}

function applyEZCopyTemplate($originals, $existingSigs, $packageid, $template, $clientid, &$oldVersions) {
	// Rules:
	// Do not create any visits before the the date/time of the original visit
	// Do not create visits at the same time as other visits of the same service type
	$range = fetchFirstAssoc("SELECT startdate, enddate FROM tblservicepackage WHERE packageid = $packageid LIMIT 1");
	$appt = current($originals);  // there should be only one new visit
	$existingSigs[$appt['date']][] = $appt['timeofday'].'|'.$appt['servicecode'];
	
	// require_once "service-fns.php";
	// require_once "pet-fns.php";
	$clientCharges = getClientCharges($clientid);
	$clientPets = getClientPetNames($clientid);
	$standardCharges = getStandardCharges();
	$standardRates = getStandardRates();
	$providerRates = array();

//print_r($existingSigs);	
	for($day = $appt['date']/*$range['startdate']*/; $day <= $range['enddate']; $day = date('Y-m-d', strtotime("+1 days", strtotime($day)))) {
		if($day == $range['enddate']) $lastDay = true;
		$firstDay = $day == $range['startdate'];
		$dow = date('D', strtotime($day));
		if(in_array($dow, array('Thu','Sat','Sun'))) $dow = substr($dow, 0, 2);
		else $dow = substr($dow, 0, 1);
//print_r($template);exit;		
		if(!in_array($dow, $template['daysofweek'])) continue;
		foreach($template['timeframes'] as $timeframe) {
			$servicecode = $timeframe[2] ? $timeframe[2] : $appt['servicecode'];
			if(($existingSigs[$day] && in_array(($sig = "$timeframe[0]|$servicecode"), $existingSigs[$day])) // not already there
					|| ($lastDay && $timeframe[1])) continue;  // not to be excluded on the last day
			$starttime = date("H:i", strtotime(substr($timeframe[0], 0, strpos($timeframe[0], '-'))));
			$endtime = date("H:i", strtotime(substr($timeframe[0], strpos($timeframe[0], '-')+1)));
			if($firstDay && $starttime < $appt['starttime']) continue;
			// create copy on $day;
//echo($sig.'<p>');
			$newTask = array_merge($appt);
			unset($newTask['completed']); 
			unset($newTask['appointmentid']); 
			unset($newTask['canceled']); 
			unset($newTask['cancellationreason']); 
			unset($newTask['pendingchange']); 
			unset($newTask['modified']); 
			unset($newTask['modifiedby']);
			unset($newTask['serviceptr']);
			
			$newTask['charge'] = calculateServiceCharge($clientid, $servicecode, $newTask['pets'], $clientPets, $clientCharges, $standardCharges);
			if($provptr = $newTask['providerptr'])
				if(!isset($providerRates[$provptr]))
					$providerRates[$provptr] = getProviderRates($provptr);
			$newTask['rate'] = calculateServiceRate($provptr, $servicecode, $newTask['pets'], $clientPets, 
																						$newTask['charge'], $providerRates[$provptr], $standardRates);
			$newTask['providerptr'] = $provptr ? $provptr : '0';
			$newTask['servicecode'] = $servicecode;
			$timeofday = explode('-', $timeframe[0]);
			$timeofday = date('g:i a', strtotime($timeofday[0])).'-'.date('g:i a', strtotime($timeofday[1]));
			$newTask['timeofday'] = $timeofday;
			$newTask['starttime'] = $starttime;
			$newTask['endtime'] = $endtime;
			$newTask['serviceid'] = '0';
			$newTask['packageptr'] = $packageid;
			$newappt = createAppointment(false, null, $newTask, strtotime($day));
			if($_SESSION['surchargesenabled']) {
				require_once "surcharge-fns.php";
				updateAppointmentAutoSurcharges($newappt);  // NEVER HAPPENS!
			}
			$oldVersions[$newappt['appointmentid']] = $appt['appointmentid'];
		}
	}
//exit;	
}


function getOtherCompForAppointment($id) {
	return fetchFirstAssoc("SELECT * FROM tblothercomp WHERE appointmentptr = $id LIMIT 1");
}


function displayAppointment($source, $showClientCharge=true, $showProviderRate=true) {
	global $apptFields;
	echo "\n<table width=100%>\n";
	echo "<tr><td valign=top>\n<table>\n"; // COL 1
	
	//function labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) {
	       
 	labelRow($apptFields['client'].':', '', apptClientLink($source), '','','','','raw');
 	labelRow($apptFields['date'].':', '', displayDate($source['date']));
 	labelRow($apptFields['servicecode'].':', '', getServiceName($source['servicecode']));
	$source['pets'] = strip_tags($source['pets']);
 	
 	labelRow($apptFields['pets'].':', '', $source['pets']);
 	
 	$statusClass = '';
 	if($source['canceled']) {
		$status = "Canceled ".displayDateTime($source['canceled']);
		$statusClass = 'canceledtask';
	}
 	else if($source['completed']) {
		$status = "Completed ".displayDateTime($source['completed']);
		$statusClass = 'completedtask';
	}
 	else {
		$futurity = appointmentFuturity($source);
		if($futurity > 0) $status = 'Not yet due'; 
		else if($futurity == 0) $status = 'To be done';
		else {
			$status = "Unreported";
			$statusClass = 'noncompletedtask';
		}
	}
	$modifiers = array();
	if($source['highpriority']) $modifiers[] = '<font color=red>High Priority</font>';
	if($source['custom']) $modifiers[] = 'Custom';
	if($modifiers) $status .= '- ['.join(', ', $modifiers).']';
 	labelRow($apptFields['status'].':', '', $status, '', $statusClass,'','','raw');
 	labelRow($apptFields['packageType'].':', '', $source['packageType']);

	echo "</td></tr></table><td valign=top style='padding-left: 5px'><table>"; // COL 2
	
	$sitterName = getDisplayableProviderName($source['providerptr']);
	if(!array_key_exists('none', (array)$sitterName)) labelRow($apptFields['provider'].':', '', $sitterName);
 	//if($clientProviderNameDisplayMode != 'none')
 	
 	labelRow($apptFields['timeofday'].':', '', $source['timeofday']);
 	if($showClientCharge) {
//function labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) {
		
 	  labelRow($apptFields['chargeline'].':', '', dollarAmount($source['charge'], true, '', $nbsp='').' / '.dollarAmount($source['adjustment'], true, '', $nbsp=''), null, null, null, null, true);
 	  $label = $source['charge'] + ($source['adjustment'] ? $source['adjustment'] : 0);
 	  $label = dollarAmount($label, true, '', $nbsp='');
 		if(!$showProviderRate) labelRow($apptFields['surchargenote'].':', '', $source['surchargenote']);
 	  labelRow($apptFields['totalcharge'].':', '', $label, null, null, null, null, 'raw');
		if($_SESSION['discountsenabled'] && $source['appointmentid']) {
			require_once "discount-fns.php";
			$discount = getAppointmentDiscount($source['appointmentid'], 'withLabel');
			if($discount) 
				echo "<tr><td colspan=2>A discount of ".dollarAmount($discount['amount'])." will be applied.<br>({$discount['label']})</td></tr>";
		}

	}
	if($_SESSION['preferences']['sittersPaidHourly']) {
		$hours = fetchRow0Col0("SELECT hours FROM tblservicetype WHERE servicetypeid = {$source['servicecode']} LIMIT 1");
		labelRow('Hours:', '', $hours, null, null, null, null, 'raw');
	}
 	else if($showProviderRate || userRole() == 'o') {
		labelRow($apptFields['rateline'].':', '', 
								dollarAmount($source['rate'], true, '', $nbsp='').' / '.dollarAmount($source['bonus'], true, '', $nbsp=''),
								null, null, null, null, 'raw');
 		if($source['surchargenote']) labelRow($apptFields['surchargenote'].':', '', $source['surchargenote']);
	}
	
	/* HIDDEN AT FELICIA'S REQUEST
	if($showProviderRate) {
 		$totalPay = $source['rate'] + ($source['bonus'] ? $source['bonus'] : 0);
		labelRow($apptFields['totalpay'].':', '', dollarAmount($totalPay, true, '', $nbsp=''), null, null, null, null, 'raw');
	}
	*/

	echo "</td></tr></table></td></tr>"; // END COL 2

	if($source['cancelcomp']) echo "<tr><td valign=top colspan=2>Sitter (will be) compensated for canceled appointment.</td></tr>"; // NOTES

	echo "<tr><td valign=top colspan=2><table>"; // NOTES
	$rows = 3;
	$cols = 50;
//textDisplayRow($label, $name, $value=null, $emptyTextDisplay=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null)

	textDisplayRow("Note:", 'notes', $source['note']);
	echo "</table></td></tr>"; // COL 2
	echo "</table>";

}

function apptClientLink($source) {
	return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id={$source['clientptr']}\",700,500)'>
	       {$source['client']}</a> ";
}

function getAppointment($id, $withNames=true, $withPayableData=false, $withBillableData=false) {
	$joins = ''.
	$extraFields = '';
	if($withNames) {
		$extraFields .= ", CONCAT_WS(' ',tblclient.fname, tblclient.lname) as client, "
										."tblclient.zip as zip, CONCAT_WS(' ',tblprovider.fname, tblprovider.lname) as provider";
		$joins .= " LEFT JOIN tblclient ON clientid = tblappointment.clientptr LEFT JOIN tblprovider ON providerid = providerptr";
	}
	if($withPayableData) {
		$extraFields .= ", payableid, tblpayable.paid as providerpaid, datepaid as dateproviderpaid";
		$joins .= " LEFT JOIN tblpayable ON tblappointment.appointmentid = tblpayable.itemptr AND tblpayable.itemtable = 'tblappointment'";
	}
	if($withBillableData) {
		$extraFields .= ", billableid, tblbillable.paid as billpaid";
		$joins .= " LEFT JOIN tblbillable ON tblappointment.appointmentid = tblbillable.itemptr AND tblbillable.itemtable = 'tblappointment'";
	}
	return fetchFirstAssoc("SELECT tblappointment.* $extraFields FROM tblappointment $joins WHERE appointmentid = $id LIMIT 1");
}

function originalServiceProviders($appts) {
	if(!$appts) return array();
	$originatingServices = array();
	foreach($appts as $appt) $originatingServices[] = $appt['serviceptr'];
	$originatingServices = join(',',array_unique($originatingServices));
	if(!$originatingServices) return array();
	return fetchAssociationsKeyedBy(
		"SELECT serviceid, providerptr, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as providername 
	    FROM tblservice
	    LEFT JOIN tblprovider ON providerid = providerptr
	    WHERE serviceid IN ($originatingServices)", 'serviceid');
}

function appointmentUnassignedFrom($apptOrApptId) {
	if(dbTEST('poochydoos')) {
		$appt = is_array($apptOrApptId) ? $apptOrApptId 
						: fetchFirstAssoc("SELECT appointmentid, providerptr FROM tblappointment WHERE appointmentid = $apptOrApptId LIMIT 1", 1);
		if(!$appt['providerptr']) { // if UNASSIGNED
			// see if this was wiped due to time off
			$wipedProvider = fetchFirstAssoc(
				"SELECT providerptr, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as providername 
					FROM relwipedappointment
					LEFT JOIN tblprovider ON providerptr = providerid
					WHERE appointmentptr = {$appt['appointmentid']}
					ORDER BY `time` DESC
					LIMIT 1", 1);
			return $wipedProvider['providername'];
		}
	}
}

function logAppointmentStatusChange($appt, $context) {
	$status = $appt['canceled'] ? 'canceled' : ($appt['completed'] ? 'completed' : 'incomplete');
	logChange($appt['appointmentid'], 'tblappointment', 'm', "status: $status [$context]");
}

function cancelAppointments($ids, $cancel, $additionalMods=null, $generateMemo=true, $initiator=null) { // return affected providers as a csv string
	require_once "invoice-fns.php";
	require_once "invoice-fns.php";
	require_once "provider-memo-fns.php";
	$ids = is_array($ids) ? $ids : explode(',', $ids);
	$cancel = $cancel ? date('Y-m-d H:i:s') : null;
	$cancellation = withModificationFields(array('canceled'=>$cancel, 'completed'=>null));
	foreach((array)$additionalMods as $key=>$val) $cancellation[$key] = $val;
	
	$alreadyCanceled = fetchKeyValuePairs(
		"SELECT appointmentid, canceled 
			FROM tblappointment 
			WHERE appointmentid IN (".join(',', $ids).")", 1);

	// UPDATE tblappointment
	updateTable('tblappointment', $cancellation, "appointmentid IN (".join(',', $ids).")", 1);
	
	
	if($_SESSION['surchargesenabled']) {
		require_once "surcharge-fns.php";
		if($cancel) {
			foreach($ids as $id) {
				dropAppointmentSurcharges($id, false);
				checkNonspecificSurcharges($id);
			}
		}
		else foreach($ids as $id) updateAppointmentAutoSurcharges($id);
	}


	// UPDATE DISCOUNTS
	setAppointmentDiscounts($ids, !$cancel);


	// undo billable - appt is now either canceled or uncompleted
	foreach($ids as $id) {
		$appt = getAppointment($id, false, true, true);
		
		
		if(!$alreadyCanceled[$id]) {
			if(!handleCancellationCredit($id))
				supersedeAppointmentBillable($id);
		}
		else supersedeAppointmentBillable($id);
		
		
		
		if(!$appt['recurringpackage']) $nrPacks[$appt['packageptr']] = 1;
		$clientid = $appt['clientptr'];
// LOG CHANGE		
		if($appt['appointmentid']) logAppointmentStatusChange($appt, $initiator);  // appt may no longer exist ??
		if(!((int)($appt['billpaid'] + $appt['providerpaid']))) {
			if($appt['payableid']) deleteTable('tblpayable', "payableid = {$appt['payableid']}", 1);
		}
		if($generateMemo && appointmentFuturity($appt) >= 0) makeClientVisitStatusChangeMemo($appt['providerptr'], $appt['clientptr'], $id, $cancel);
		// echo providers 
		$sections[] = $appt['providerptr'];
	}
	
	// update nonrecurring package prices
	if($nrPacks) {
		$histories = findPackageHistories($clientid, 'N', $current=true);
		foreach(array_keys((array)$nrPacks) as $packageid)
			foreach($histories as $version => $history)
				if(in_array($packageid, $history))
					$currentPacks[$version] = 1;


		foreach(array_keys((array)$currentPacks) as $packageid) {
			$price = calculateNonRecurringPackagePrice($packageid, $clientid);
	//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($price);echo "<p>"; }	
			updateTable('tblservicepackage', array('packageprice'=>$price), "packageid = $packageid");
		}
	}
	return join(',', $sections);
}

function deleteAppointment($id) {
	deleteAppointments("appointmentid = $id");
	deleteTable('tblpayable', "itemptr = $id AND itemtable = 'tblappointment'", 1);
	require_once "invoice-fns.php";
	supersedeAppointmentBillable($id);
}

function hasPaidPayables($id) {
	if(fetchFirstAssoc(
		"SELECT * 
			FROM tblpayable 
			WHERE paid > 0
				AND itemptr = $id AND itemtable = 'tblappointment'
			LIMIT 1", 1)) return true;
			
	$otherComps = fetchCol0("SELECT compid FROM tblothercomp WHERE appointmentptr = $id", 1);
	if($otherComps)
		return fetchFirstAssoc(
		"SELECT * 
			FROM tblpayable 
			WHERE paid > 0
				AND itemptr IN (".join(',', $otherComps).") AND itemtable = 'tblothercomp'
			LIMIT 1", 1);
}

function briefTimeOfDay($appt) {
	$tod = explode('-', $appt['timeofday']);
	$start = explode(':', $tod[0]);
	$start = ''+$start[0].(substr($start[1], 0, 1) == '00' ? '' : ":".substr($start[1], 0, 2)).(strpos($start[1], 'a') ? 'a' : 'p');
	$end = explode(':', $tod[1]);
	$end = ''+$end[0].(substr($end[1], 0, 1) == '00' ? '' : ":".substr($end[1], 0, 2)).(strpos($end[1], 'a') ? 'a' : 'p');
	return $start.'-'.$end;
	
}

function lastVisitNote($clientptr) { // appointmentid, note, providerptr, sitter, completed, mobilecomplete
	// used in visit-sheet-mobile and native-prov-multiday-list.php
	// find the most recent completed visit
	$appt = fetchFirstAssoc(
		"SELECT appointmentid, note, providerptr, completed,
						IFNULL(nickname, fname) as sitter
			FROM tblappointment
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE clientptr = $clientptr AND completed IS NOT NULL
			ORDER BY date DESC, starttime DESC
			LIMIT 1", 1);
	if(!$appt) return "No previous completed visits.";
	$mobileCompletion = fetchRow0Col0(
		"SELECT date FROM tblgeotrack 
			WHERE appointmentptr = {$appt['appointmentid']} AND event = 'completed'
			ORDER BY date DESC
			LIMIT 1", 1);
	if($mobileCompletion) $appt['mobilecomplete'] = $mobileCompletion;
	return $appt;
}

