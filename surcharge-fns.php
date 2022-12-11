<? // surcharge-fns.php
function getSurcharge($id, $withNames=true, $withPayableData=false, $withBillableData=false) {
	$joins = ''.
	$extraFields = '';
	if($withNames) {
		$extraFields .= ", CONCAT_WS(' ',tblclient.fname, tblclient.lname) as client, CONCAT_WS(' ',tblprovider.fname, tblprovider.lname) as provider";
		$joins .= " LEFT JOIN tblclient ON clientid = tblsurcharge.clientptr LEFT JOIN tblprovider ON providerid = providerptr";
	}
	if($withPayableData) {
		$extraFields .= ", payableid, tblpayable.paid as providerpaid, datepaid as dateproviderpaid";
		$joins .= " LEFT JOIN tblpayable ON tblsurcharge.surchargeid = tblpayable.itemptr AND tblpayable.itemtable = 'tblsurcharge'";
	}
	if($withBillableData) {
		$extraFields .= ", billableid, tblbillable.paid as billpaid";
		$joins .= " LEFT JOIN tblbillable ON tblsurcharge.surchargeid = tblbillable.itemptr AND tblbillable.itemtable = 'tblsurcharge'";
	}
	return fetchFirstAssoc("SELECT tblsurcharge.* $extraFields FROM tblsurcharge $joins WHERE surchargeid = $id LIMIT 1");
}

function markAppointmentSurchargesComplete($apptIds) {
	if(is_array($apptIds)) $apptIds = join(',', $apptIds);
	// mark all uncanceled surcharges for client on these appointment days complete
	$datesAndClients = fetchAssociations("SELECT clientptr, date FROM tblappointment WHERE appointmentid IN ($apptIds)");
	foreach($datesAndClients as $appt) {
		$clients[] = $appt['clientptr'];
		$dates[] = $appt['date'];
	}
	$filter = "canceled IS NULL AND (appointmentptr IN ($apptIds) OR (appointmentptr = 0 AND clientptr IN ("
						.join(',', $clients).") AND date IN ('"
						.join("','", $dates)."')))";
	return markSurchargesComplete($filter, true);
}

function markSurchargesComplete($idsOrFilter, $isFilter=false) {
	if(!idsOrFilter) return array();
	if($isFilter) $filter = $idsOrFilter;
	else {
		if(is_array($idsOrFilter)) $idsOrFilter = join(',', $idsOrFilter);
		$filter = "canceled IS NULL AND surchargeid IN ($idsOrFilter)";
	}
	$oldSurcharges = fetchAssociations("SELECT * FROM tblsurcharge WHERE $filter");

	updateTable('tblsurcharge', array('completed'=>date('Y-m-d H:i:s'), 'canceled'=>null), $filter);
	foreach($oldSurcharges as $oldSurcharge) {
		if(!$oldSurcharge['completed']) {
			require_once "invoice-fns.php";
			// we need to ensure there is no existing unsuperseded surcharge billable before we proceed...
			if(!fetchRow0Col0(
				"SELECT billableid 
					FROM tblbillable
					WHERE itemtable = 'tblsurcharge'
						AND itemptr = {$oldSurcharge['surchargeid']}
						AND superseded = 0
					LIMIT 1", 1))
			createSurchargeBillable($oldSurcharge);
		}
	}
	return fetchAssociations("SELECT * FROM tblsurcharge WHERE $filter");
}

function reinitializeNationalHolidays($country) {
	deleteTable('tblsurchargetype', "permanent = 1 AND date IS NOT NULL", 1);
	initializeNationalHolidays($country);
}

function initializeNationalHolidays($country) {
	$i18n = getI18NProperties($country);
	$holidays = $i18n['Holidays'];
	$menuorder = 3;
	foreach($holidays as $label => $dates) {
		$dates = explode(',', $dates);
		foreach($dates as $d) if(substr($d, 0, 4) == date('Y')) $date = $d;
		$date = $date ? $date : $d;
		insertTable('tblsurchargetype',
			array('label'=>$label, 'descr'=>'', 'date'=>$date, 'automatic'=>1, 'recurring'=>0, 'pervisit'=>1, 'filterspec'=>'',
						'defaultrate'=>'0.00', 'defaultcharge'=>'0.00', 'active'=>'1', 'permanent'=>'1', 'menuorder'=>$menuorder),
			1);
		/*doQuery(
		"INSERT INTO `tblsurchargetype` 
			(`label`, `descr`, `date`, `automatic`, `recurring`, `pervisit`, `filterspec`, `defaultrate`, `defaultcharge`, `active`, `permanent`, `menuorder`)
			VALUES
			('$label', NULL, '$date', 1, 0, 1, NULL, 0.00, 0.00, '1', 1, $index)");*/
		$menuorder++;
	}
	return true;
}

function getSurchargeTypes($filter=null, $order="active desc, menuorder, label") {
	$filter = $filter ? "WHERE $filter" : '';
	return fetchAssociationsKeyedBy("SELECT * FROM tblsurchargetype $filter ORDER BY $order", 'surchargetypeid');
}

function getPermanentSurchargeTypeIds() {
	$perms = fetchCol0("SELECT DISTINCT surchargetypeid FROM tblsurchargetype WHERE permanent = 1");
	return array_merge($perms, fetchCol0("SELECT DISTINCT surchargecode FROM tblsurcharge"));
	
}

function getSurchargeName($id) {
	$types = getSurchargeTypesById($refresh=0, $inactiveAlso=1);
	return $types[$id];
}

function getSurchargeTypesById($refresh=0, $inactiveAlso=0) {
	static $scriptSurchargeTypes;
	if($scriptSurchargeTypes && !$refresh) return $scriptSurchargeTypes;
	$inactiveAlso = $inactiveAlso ? '1=1' : 'active=1'; 
	$names = fetchKeyValuePairs($sql = "SELECT surchargetypeid, label  FROM tblsurchargetype WHERE $inactiveAlso ORDER BY active desc, menuorder, label");
	if($_SESSION) $_SESSION['surchargetypes'] = $names;
	return $names;
}

function scheduleASurcharge($packageptr, $surcharge, $servicecode=null) {
	$surchargeTypes = getSurchargeTypesById();
	// ...
	if(!$surchargeTypes[$surcharge['surchargetypeid']]['permanent']) {
		updateTable('tblsurchargetype', array('permanent'=>1), 1);
		getSurchargeTypesById('refresh');
	}
}

function initializeAnyTimeSurcharge() {
	if(!fetchFirstAssoc("SELECT * FROM tblsurchargetype WHERE filterspec = 'anytime_'"))
		doQuery(
			"INSERT INTO `tblsurchargetype` (
				`surchargetypeid` ,
				`label` ,
				`descr` ,
				`date` ,
				`automatic` ,
				`recurring` ,
				`pervisit` ,
				`filterspec` ,
				`defaultrate` ,
				`defaultcharge` ,
				`active` ,
				`permanent` ,
				`menuorder`
				)
				VALUES (
				NULL , 'Any Time', NULL , NULL , '0', '0', '1', 'anytime_', '0.00', '0.00', '0', '1', '0'
				);");
}

function findApplicableSurcharges($appt) { //surchargeCollisionPolicy
	$types = getSurchargeTypes('automatic = 1 AND active = 1');
	$today = date('m/d', strtotime($appt['date']));
	$time = substr($appt['starttime'], 0, 5);
	$candidates = array();
	foreach($types as $type) {
		if($type['date'] && date('m/d', strtotime($type['date'])) == $today)
			$candidates[] = $type;
		else if($filterspec = $type['filterspec']) {
			$filterspec = explode('_', $filterspec);
			if($filterspec[0] == 'weekend' && strpos($filterspec[1], substr(date('D', strtotime($appt['date'])), 0, 2)) !== FALSE)
				$candidates[] = $type;
			else if($filterspec[0] == 'before' && (strcmp($time, $filterspec[1]) < 0))
				$candidates[] = $type;
			else if($filterspec[0] == 'after' && (strcmp($filterspec[1], $time) < 0))
				$candidates[] = $type;
			else if($filterspec[0] == 'anytime')
				$candidates[] = $type;
		}
	}
	if(count($candidates) > 1) {
		/* TEMPORARY */
		require_once "service-fns.php";
		$_SESSION['preferences']['surchargeCollisionPolicy'] = fetchPreference('surchargeCollisionPolicy');
		/* END TEMPORARY */
		$policy = $_SESSION['preferences']['surchargeCollisionPolicy'];
		
		$pickit = null;
		if(!$policy || strpos($policy, 'great'))
			foreach($candidates as $type) 
				if(!$pickit || $type['defaultcharge'] > $pickit['defaultcharge'] )
					$pickit = $type;
		if(strpos($policy, 'small'))
			foreach($candidates as $type) 
				if(!$pickit || $type['defaultcharge'] < $pickit['defaultcharge'] )
					$pickit = $type;
		if($pickit) $candidates = array($pickit);
	}
	return $candidates;
}

function justifySurcharge($surchargeOrSurchargeId) {
	// given a surcharge, figure out why it is there
	// return array of with associated appointmentid (appointmentptr) if there is one
	// return array of appointmentids for automatic surcharge
	// return userid of creator if not automatic
	$surcharge = is_array($surchargeOrSurchargeId) ? $surchargeOrSurchargeId : getSurcharge($surchargeOrSurchargeId);
	if(!$surcharge) return null;
	if(!$surcharge['automatic']) return $surcharge['createdby'];
	if($surcharge['appointmentptr']) return array($surcharge['appointmentptr']);
	$causes = array();
	$surchargecode = $surcharge['surchargecode'];
	$daysAppointments = fetchAssociations(
		"SELECT appointmentid, date, starttime 
			FROM tblappointment 
			WHERE clientptr = {$surcharge['clientptr']} 
				AND date = '{$surcharge['date']}'
				AND canceled IS NULL", 1);
	foreach($daysAppointments as $appt) {
		// BECAUSE THE VISIT MAY HAVE BEEN MOVED, the surch may no longer be applicable
		$candidates = findApplicableSurcharges($appt);
		foreach($candidates as $cand)
			if($cand['surchargetypeid'] == $surchargecode)
				$causes[] = $appt['appointmentid'];
	}
	return $causes;
}

function getPackageSurcharges($packageptr, $clientptr, $date=null) {
	$history = findPackageIdHistory($packageptr, $clientptr, false);
	$history[] = $packageptr;
	$history = join(',', $history);
	$date = $date ? "AND date = '$date'" : '';
	return fetchAssociations("SELECT * FROM tblsurcharge WHERE packageptr IN ($history) $date");
}
				

function createScheduleAutoSurcharges($packageptr, $simulation=false, $simulatedAppts=null) {  // Applies ONLY to nonrecurring schedules
	global $projectionStartTime, $projectionEndTime;
	require_once "service-fns.php";
	require_once "pet-fns.php";
	require_once "invoice-fns.php";
	
	if(is_array($packageptr)) {
		$package = $packageptr;
		$packageptr = $package['packageid'];
	}
	else $package = getPackage($packageptr);
	
	$allowRetroactiveAppointments = $package['onedaypackage']	;
	$scheduleStart = strtotime($package['startdate']);
	$start = $scheduleStart;
	
	if($package['effectivedate']) $start = max($start, strtotime($package['effectivedate']));
	if(!$allowRetroactiveAppointments) $start = max($start, strtotime(date("Y-m-d")));
	if($projectionStartTime) $start = max($start, $projectionStartTime);

	$end = $package['onedaypackage'] ? strtotime($package['startdate']) : strtotime($package['enddate']);
  // if there is a cancellation date										
	if($package['cancellationdate'])
	  // set end to the day before it if it falls before end's current value
	  $end = min($end, strtotime("- 1 day",strtotime($package['cancellationdate'])));
	  
	if($simulation) $appts = $simulatedAppts;
	else {
		$history = findPackageIdHistory($packageptr, $package['clientptr'], false);
		$history[] = $packageptr;
		$history = join(',', $history);
		$appts = fetchAssociations("SELECT * FROM tblappointment WHERE packageptr IN ($history) AND canceled IS NULL");
	}
	
	
	$surcharges = fetchAssociations("SELECT * FROM tblsurcharge WHERE clientptr = {$package['clientptr']}");
	for($day = $start; $day <= $end; $day = strtotime("+ 1 day", $day)) {
		$ymd = date('Y-m-d', $day);
		$dayCharges = array();
		foreach($surcharges as $surcharge) if($surcharge['date'] == $ymd) $dayCharges[] = $surcharge['surchargecode'];
		$surchargesPreview = $surcharges;
		foreach($appts as $appt) {
			if($appt['date'] != $ymd) continue;
//echo "APPT: ".print_r($appt,1)."<br>TYPES: ".print_r(findApplicableSurcharges($appt), 1)."SURCHARGES: ".print_r($dayCharges,1)."<br>";
			foreach(findApplicableSurcharges($appt) as $type) {
				$typeid = $type['surchargetypeid'];
				if(!$type['pervisit'] && in_array($typeid, $dayCharges)) continue;
				$dayCharges[] = $typeid;
					$newSurchargeOrId = createSurcharge($package['clientptr'], $package['packageid'], $type, $appt['date'],
												1, /* auto */
												$appt['providerptr'],
												($type['pervisit'] ? $appt : null),
												$note=null, $completed=null, $canceled=null, $simulation); // for appointmentptr, starttime, endtime)
				if($simulation) $surchargesPreview[] = $newSurchargeOrId;
//echo "<br>newSurchargeId: $newSurchargeId<p>";												
				//require_once "invoice-fns.php";
				//createSurchargeBillable($newSurchargeId);  -- NO BILLABLE REQUIRED HERE

			}
		}
	}
	if($simulation) return $surchargesPreview;
}

function createSurcharge($clientptr, $packageptr, $typeOrTypeptr, $date, $automatic, $providerptr, $appt=null, $note=null, $completed=null, $canceled=null, $simulation=false) {
	global $scriptPrefs;
	$type = is_array($typeOrTypeptr) 
			? $typeOrTypeptr 
			: fetchFirstAssoc("SELECT * FROM tblsurchargetype WHERE surchargetypeid = $typeOrTypeptr LIMIT 1");

	/*$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences'] : $scriptPrefs;
	$taxRate = $prefs['taxRate'] ? $prefs['taxRate'] : 0;
	if($appt) {
		require_once "tax-fns.php";
		$taxRate = getClientServiceTaxRate($clientptr, $appt['servicecode']);
	}
	$tax = $type['defaultcharge'] * $taxRate / 100;
		'tax'=>$tax,*/

	$surcharge = array(
		'packageptr'=>$packageptr,
		'surchargecode'=>$type['surchargetypeid'],
		'appointmentptr'=>($appt ? $appt['appointmentid'] : 0),
		'date'=>$date,
		'timeofday'=>($appt ? $appt['timeofday'] : null),
		'charge'=>$type['defaultcharge'],
		'rate'=>$type['defaultrate'],
		'providerptr'=>$providerptr,
		'clientptr'=>$clientptr,
		'automatic'=>$automatic,
		'starttime'=>($appt ? $appt['starttime'] : null),
		'endtime'=>($appt ? $appt['endtime'] : null),
		'note'=>$note,
		'completed'=>$completed,
		'canceled'=>$canceled,
		'created'=>date('Y-m-d'),
		'createdby'=>$_SESSION['auth_user_id']);
	return $simulation ? $surcharge : insertTable('tblsurcharge', $surcharge, 1);
}
										
function dropAppointmentSurcharges($apptIds, $automaticOnly=true) {  // Covers billables.
	if(is_array($apptIds)) $apptIds = join(',', $apptIds);
	if($apptIds) {
		$filter = "appointmentptr IN ($apptIds)";
		//if($automaticOnly) dropAutoSurchargesWhere($filter);  // all appointment surcharges are assumed to be automatic for now (5 Mar 2010)
		dropSurchargesWhere($filter, $automaticOnly);
	}
}

function checkNonspecificSurcharges($apptOrApptId) {
	$appt = is_array($apptOrApptId) 
		? $apptOrApptId 
		: fetchFirstAssoc("SELECT date, packageptr, clientptr FROM tblappointment WHERE appointmentid = $apptOrApptId");
	if(!$appt['clientptr'] || !$appt['date'])  // if appt has been deleted
		return;
	$completedAppointments = fetchAssociations(
		"SELECT * FROM tblappointment 
			WHERE 
				clientptr = {$appt['clientptr']} 
				AND completed IS NOT NULL 
				AND date = '{$appt['date']}'");
	if(!$completedAppointments) {
		$surchargesToMarkIncomplete = fetchCol0(
			"SELECT surchargeid FROM tblsurcharge 
				WHERE 
					clientptr = {$appt['clientptr']} 
					AND appointmentptr = 0
					AND completed IS NOT NULL
					AND date = '{$appt['date']}'");
		markSurchargesIncomplete($surchargesToMarkIncomplete);
	}
}


function markSurchargesIncomplete($surchargeIds) {  // Covers billables.
		if(!$surchargeIds) return;
		require_once "invoice-fns.php";
		$surchargeIds = is_array($surchargeIds) ? $surchargeIds : explode(',', $surchargeIds);
		foreach($surchargeIds as $surchargeId) 
			supersedeSurchargeBillable($surchargeId);
		updateTable('tblsurcharge', array('completed'=>null, 'canceled'=>null), "surchargeid IN (".join(',', $surchargeIds).")", 1);
}



function dropAutoSurchargesWhere($filter) {  // Covers billables.
	dropSurchargesWhere($filter, $auto=true);
}

function dropSurchargesWhere($filter, $auto=false) {  // Covers billables.
	$auto = $auto ? "automatic = 1 AND " : "";
	$surchargeIds = fetchCol0( "SELECT surchargeid FROM tblsurcharge WHERE $auto $filter ");
	dropSurcharges($surchargeIds);
}
		
function dropSurcharges($surchargeIds) {  // Covers billables.
		if(!$surchargeIds) return;
		require_once "invoice-fns.php";
		$surchargeIds = is_array($surchargeIds) ? $surchargeIds : explode(',', $surchargeIds);
		foreach($surchargeIds as $surchargeId) 
			supersedeSurchargeBillable($surchargeId);
		$surcharges = fetchAssociations(
			"SELECT surchargeid, billableid, paid FROM tblsurcharge 
				LEFT JOIN tblbillable ON itemptr = surchargeid AND itemtable = 'tblsurcharge'
				WHERE surchargeid IN (".join(',', $surchargeIds).")", 1);
		if(!$surcharges) return;
		
		foreach($surcharges as $surcharge) {
			if($surcharge['paid'] && $surcharge['paid'] > 0) $toCancel[] = $surcharge['surchargeid'];
			else $toDelete[] = $surcharge['surchargeid'];
		}
		if($toDelete) deleteTable('tblsurcharge', "surchargeid IN (".join(',', $toDelete).")", 1);
		if($toCancel) updateTable('tblsurcharge', array('canceled'=>date('Y-m-d'), 'completed'=>null), "surchargeid IN (".join(',', $toCancel).")", 1);
}

// usage: if($_SESSION['surchargesenabled']) dropAutoSurchargesWhere($filter);								
function updateAppointmentAutoSurchargesWhere($condition) {
	$appts = fetchAssociations($sql = "SELECT * FROM tblappointment WHERE $condition");
}
	foreach($appts as $appt) updateAppointmentAutoSurcharges($appt);
}

function reassignExistingAppointmentSurcharges($apptOrApptId) {
	$apptid = is_array($apptOrApptId) ? $apptOrApptId['appointmentid'] : $apptOrApptId;
	$providerptr = is_array($apptOrApptId) ? $apptOrApptId['providerptr'] 
									: fetchRow0Col0("SELECT providerptr FROM tblappointment WHERE appointmentid = $apptid LIMIT 1");
	updateTable('tblsurcharge', array('providerptr'=>$providerptr), "appointmentptr = $apptid", 1);
}

function updateAppointmentAutoSurcharges($apptOrApptId) {
	/*if(is_array($apptOrApptId))
		if(!($apptOrApptId['starttime'] && $apptOrApptId['date'] && $apptOrApptId['appointmentid']))
			$apptOrApptId['appointmentid'];*/
	$appt = is_array($apptOrApptId) 
		? $apptOrApptId 
		: fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $apptOrApptId LIMIT 1");
	$surchargeCancelations = fetchKeyValuePairs("SELECT surchargecode, canceled 
																				FROM tblsurcharge 
																				WHERE appointmentptr = {$appt['appointmentid']} AND canceled IS NOT NULL");
//print_r($surchargeCancelations);	exit;
	dropAppointmentSurcharges(array($appt['appointmentid']), true); // 
	if($appt['canceled']) return;
	$surcharges = fetchCol0("SELECT surchargecode FROM tblsurcharge WHERE clientptr = {$appt['clientptr']} AND date = '{$appt['date']}'");
	foreach(findApplicableSurcharges($appt) as $type) {
		$typeid = $type['surchargetypeid'];
		if(!$type['pervisit'] && in_array($typeid, $surcharges)) continue;
		$surcharges[] = $typeid;
		$newSurchargeId = createSurcharge($appt['clientptr'], $appt['packageptr'], $type, $appt['date'],
										1, /* auto */
										($appt['providerptr'] ? $appt['providerptr'] : '0'),
										($type['pervisit'] ? $appt : null),
										null,
										($surchargeCancelations[$typeid] ? null : $appt['completed']),
										($surchargeCancelations[$typeid] ? $surchargeCancelations[$typeid] : null)
										); // for appointmentptr, starttime, endtime)
		if($appt['completed']) {
			require_once "invoice-fns.php";
			createSurchargeBillable($newSurchargeId);
		}
	}
}



										
// ############################
function isThereCanceledComp($surchargeid) {	
	if(!$surchargeid) return 0;
	if(fetchFirstAssoc("SELECT * FROM tblothercomp WHERE appointmentptr = $surchargeid AND comptype = 'cancelsurcharge'", 1))
		return 1;
}


function displayEZSurchargeEditor($source, $updateList=null) {
	global $surchargeRates;
	$raw = explode(',', 'completed,Completed,timeofday,Time of Day,provider,Sitter,pets,Pets,servicecode,Service Type,'.
											'charge,Charge,adjustment,Adjustment,rate,Rate,bonus,Bonus,date,Date,client,Client, canceled,Canceled,'.
											'custom,Custom,status,Status,chargeline,Charge / Adjustment,rateline,Rate / Bonus,highpriority,High Priority,'.
											'totalcharge,Total Charge,totalpay,Total Pay,note,Note,packageType,Package Type,'.
											'cancelcomp,Pay sitter for canceled visit?,surchargenote,Surcharge Reason');
	for($i=0;$i < count($raw) - 1; $i+=2) $apptFields[$raw[$i]] = $raw[$i+1];

	foreach(fetchAssociations("SELECT * FROM tblsurchargetype") as $type) {
		$surchargeRates[] = $type['surchargetypeid'];
		$surchargeRates[] = $type['defaultcharge'];
		$surchargeRates[] = $type['defaultrate'];
	}
	$surchargeRates = join(',', $surchargeRates);



	echo "<form name='appteditor' method='POST'>";
	echo "\n<table width=100%><tr><td style='text-align:left;font-size:1.1em;'>Client: ".
	       apptClientLink($source)."</td>
	       <td style='text-align:right'>";
	$operation = $source['surchargeid'] ? 'Save' : 'Add';
	if(!$roDispatcher) echoButton('', "$operation Surcharge", "checkAndSubmit()");
	echo " ";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
	//echo " ";
	if($id && !$undeletable) echoButton('', "Delete Surcharge", "deleteSurcharge($id)", 'HotButton', 'HotButtonDown');
	       
	echo "</td></tr>".
	       "<tr><td colspan=2>";
	echo "\n<hr>\n";
	$status = $source['canceled'] ? 1 : ($source['completed'] ? 2 : 0);
	
	$oldCancelComp = isThereCanceledComp($source['surchargeid']);
	
	labeledRadioButton('Incomplete', 'cancellation', 0, $status, 'freezeApptEditor(0)');
	if($source['surchargeid']) labeledRadioButton('Canceled', 'cancellation', 1, $status, 'freezeApptEditor(1)');
	labeledRadioButton('Completed', 'cancellation', 2, $status, 'freezeApptEditor(1)');
	echo "</td></tr></table>\n";
	$stati = array('incomplete','canceled','completed');
	hiddenElement('oldstatus', $stati[$status]);
	hiddenElement('updateList', $updateList);
	hiddenElement('surchargeid', $source['surchargeid']);
	hiddenElement('clientptr', $source['clientptr']);
	hiddenElement('oldCancelcomp', $oldCancelComp);
	hiddenElement('date', $source['date']);
	hiddenElement('payableid', $source['payableid']);
	hiddenElement('providerpaid', $source['providerpaid']);
	hiddenElement('oldproviderptr', $source['providerptr']);
	hiddenElement('dateproviderpaid', $source['dateproviderpaid']);
	hiddenElement('billableid', $source['billableid']);
	hiddenElement('billpaid', $source['billpaid']);
	hiddenElement('oldTotalCharge', $source['charge']);
	hiddenElement('packageCode', $source['packageCode']);
	hiddenElement('packageptr', $source['packageptr']);
	hiddenElement('date', $source['date']);
	hiddenElement('automatic', $source['automatic']);
	hiddenElement('notifyclient', '');
	hiddenElement('action', '');
	echo "\n<table>\n";
	
	$surchargeTypes = getSurchargeTypesById();
	if($source['automatic']) {
		labelRow('Surcharge Type:', '', $surchargeTypes[$source['surchargecode']]);
		hiddenElement('surchargecode', $source['surchargecode']);
	}
		
	else {
		$surchargeSelections = array_merge(array('' => ''), array_flip($surchargeTypes));
		selectRow('Surcharge Type:', "surchargecode", $source['surchargecode'], $surchargeSelections, 'updateSurchargeVals(this)');
	}
	
	if($source['appointmentptr']) {
		$appt = getAppointment($source['appointmentptr'], $withNames=true, $withPayableData=true, $withBillableData=true);
		labelRow($apptFields['timeofday'].':', "timeofday", $source['timeofday']);
		$serviceNames = getServiceNamesById();
		labelRow($apptFields['servicecode'].':', "servicecode", $serviceNames[$appt['servicecode']]);
		hiddenElement("providerptr", $appt['providerptr']);
		labelRow($apptFields['provider'].':', "provider", $appt['provider']);
		
	}
	else {
		labelRow($apptFields['packageType'].':', '', $source['packageType']);
		//$activeProviderSelections = array_merge(array('--Unassigned--' => '-1'), getActiveProviderSelections($source['date']));
		//$activeProviderSelections = availableProviderSelectElementOptions($source['clientptr'], $source['date'], '--Unassigned--');
		$activeProviderSelections = availableProviderSelectElementOptions($source['clientptr'], $source['date'], $nullChoice=array(), $noZIPSection=false, $offerUnassigned=true);
		selectRow($apptFields['provider'].':', "providerptr", $source['providerptr'], $activeProviderSelections);
		if($source['surchargeid'] && $source['providerptr'] && 
				!providerInArray($source['providerptr'], $activeProviderSelections)) {
			$selectProv = getProvider($source['providerptr']);
			$pName =  providerShortName($selectProv);
			$reason = providerNotListedReason($selectProv, $source);
			echo "<tr><td style='color:red;'colspan=2>This surcharge is assigned to $pName but should not be because $pName $reason.</td></tr>";
		}
	}
	
	checkboxRow($apptFields['cancelcomp'], 'cancelcomp', $oldCancelComp);


	echo "<tr><td>{$apptFields['charge']}:</td>";
	echo "<td><input id='charge' type='hidden' name='charge' size=2 value='{$source['charge']}'>
	          <div id='div_charge'>{$source['charge']}</div></td></tr>";
	
	echo "<tr><td>{$apptFields['rate']}:</td>";
	echo "<td><input id='rate' type='hidden' name='rate' size=2 value='{$source['rate']}'>
	          <div id='div_rate'>{$source['rate']}</div></td></tr>";

//function textRow($label, $name, $value=null, $rows=3, $cols=20, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {
	textRow($apptFields['note'].':', 'note', $source['note'], $rows=3, $cols=60);

	if($source['custom'])
 	  echo "<tr><td colspan=2>This surcharge has been modified since the time it was first scheduled.</td></tr>";
 	
	echo "</table></form>";
	$disabled = $status ? true : false;
	echo <<<JS
<script language='javascript'>

function hideCancelcompdiv() {
	var hide = !document.getElementById('cancellation_1') || !document.getElementById('cancellation_1').checked;
	var cancelcompinput = document.getElementById('cancelcomp');
  cancelcompinput.parentNode.parentNode.style.display = hide ? 'none' : '{$_SESSION['tableRowDisplayMode']}';
  if(hide) cancelcompinput.checked = false;
}


function freezeApptEditor(disabled) {
	// disable/enable all selects, one-liners, checkboxes, pets, and timeofday
	hideCancelcompdiv();
	var targets = 'providerptr|note|surchargecode'.split('|');
	for(var i=0;i < targets.length; i++) document.getElementById(targets[i]).disabled = disabled;
}
freezeApptEditor($disabled);
</script>
JS;

}

function logSurchargeChanges($source, $surcharge) {
	require_once "provider-fns.php";
	require_once "gui-fns.php";
	$surchargeid = $source['surchargeid'];
	$status0 = $source['canceled'] ? 'canceled' : ($source['completed'] ? 'completed' : 'incomplete');
	$status1 = $surcharge['canceled'] ? 'canceled' : ($surcharge['completed'] ? 'completed' : 'incomplete');
	if($status0 != $status1) $changes[] = "$status0 => $status1";
	if($source['providerptr'] != $surcharge['providerptr']) {
		$shortNames = getProviderShortNames($filter='');
		$changes[] = "{$shortNames[$source['providerptr']]} => {$shortNames[$surcharge['providerptr']]}";
	}
	if($source['surchargecode'] != $surcharge['surchargecode']) {
		$surchTypes = getSurchargeTypesById();
		$changes[] = truncatedLabel($surchTypes[$source['surchargecode']], 10)." => ".truncatedLabel($surchTypes[$surcharge['surchargecode']], 10);
	}
	if($surcharge['charge'] != $source['charge']) 
		$changes[] = number_format($source['charge'],2)." => ".number_format($surcharge['charge'],2);
	$changes = $changes ? join(', ', $changes) : 'no changes';
	logChange($surchargeid, 'tblsurcharge', 'm', $changes);
}


function displaySurchargeEditor($source, $updateList=null) {

	$raw = explode(',', 'completed,Completed,timeofday,Time of Day,provider,Sitter,pets,Pets,servicecode,Service Type,'.
											'charge,Charge,adjustment,Adjustment,rate,Rate,bonus,Bonus,date,Date,client,Client, canceled,Canceled,'.
											'custom,Custom,status,Status,chargeline,Charge / Adjustment,rateline,Rate / Bonus,highpriority,High Priority,'.
											'totalcharge,Total Charge,totalpay,Total Pay,note,Note,packageType,Package Type,'.
											'cancelcomp,Pay sitter for canceled visit?,surchargenote,Surcharge Reason');
	for($i=0;$i < count($raw) - 1; $i+=2) $apptFields[$raw[$i]] = $raw[$i+1];


	echo "<form name='appteditor' method='POST'>";
	echo "\n<table width=100%><tr><td>Client: ".
	       apptClientLink($source)."</td>\n<td>Date: ".displayDate(($source['date']))."</td></tr>".
	       "<tr><td>";
	$status = $source['canceled'] ? 1 : ($source['completed'] ? 2 : 0);
	$oldCancelComp = isThereCanceledComp($source['surchargeid']);

	labeledRadioButton('Incomplete', 'cancellation', 0, $status, 'freezeApptEditor(0)');
	if($source['surchargeid']) labeledRadioButton('Canceled', 'cancellation', 1, $status, 'freezeApptEditor(1)');
	labeledRadioButton('Completed', 'cancellation', 2, $status, 'freezeApptEditor(1)');
	echo "</td></tr></table>\n";
	$stati = array('incomplete','canceled','completed');
	hiddenElement('oldstatus', $stati[$status]);
	hiddenElement('updateList', $updateList);
	hiddenElement('surchargeid', $source['surchargeid']);
	hiddenElement('appointmentid', $source['appointmentid']);
	hiddenElement('clientptr', $source['clientptr']);
	hiddenElement('oldCancelcomp', $oldCancelComp);
	hiddenElement('date', $source['date']);
	hiddenElement('payableid', $source['payableid']);
	hiddenElement('providerpaid', $source['providerpaid']);
	hiddenElement('oldproviderptr', $source['providerptr']);
	hiddenElement('dateproviderpaid', $source['dateproviderpaid']);
	hiddenElement('billableid', $source['billableid']);
	hiddenElement('billpaid', $source['billpaid']);
	hiddenElement('oldTotalCharge', $source['charge']);
	hiddenElement('oldTotalRate', $source['rate']);
	$surchargeType = fetchFirstAssoc("SELECT defaultcharge, defaultrate FROM tblsurchargetype WHERE surchargetypeid = '{$source['surchargecode']}'");
	hiddenElement('surchargeTypeCharge', $surchargeType['defaultcharge']);
	hiddenElement('surchargeTypeRate', $surchargeType['defaultrate']);
	hiddenElement('packageCode', $source['packageCode']);
	hiddenElement('packageptr', $source['packageptr']);
	hiddenElement('date', $source['date']);
	hiddenElement('automatic', $source['automatic']);
	hiddenElement('notifyclient', '');
	hiddenElement('action', '');
	echo "\n<hr>\n";
	echo "\n<table>\n";
	
	$surchargeTypes = getSurchargeTypesById();
	if($source['automatic']) {
		labelRow('Surcharge Type:', '', $surchargeTypes[$source['surchargecode']]);
		hiddenElement('surchargecode', $source['surchargecode']);
	}
		
	else {
		$surchargeSelections = array_merge(array('' => ''), array_flip($surchargeTypes));
		selectRow('Surcharge Type:', "surchargecode", $source['surchargecode'], $surchargeSelections, 'updateSurchargeVals(this)');
	}
	
	if($source['appointmentptr']) {
		$appt = getAppointment($source['appointmentptr'], $withNames=true, $withPayableData=true, $withBillableData=true);
		labelRow($apptFields['timeofday'].':', "timeofday", $source['timeofday']);
		$serviceNames = getServiceNamesById();
		labelRow($apptFields['servicecode'].':', "servicecode", $serviceNames[$appt['servicecode']]);
		hiddenElement("providerptr", $appt['providerptr']);
		labelRow($apptFields['provider'].':', "provider", $appt['provider']);
		
	}
	else {
		labelRow($apptFields['packageType'].':', '', $source['packageType']);
		//$activeProviderSelections = availableProviderSelectElementOptions($source['clientptr'], $source['date'], '--Unassigned--');
		$activeProviderSelections = availableProviderSelectElementOptions($source['clientptr'], $source['date'], $nullChoice=array(), $noZIPSection=false, $offerUnassigned=true);
		selectRow($apptFields['provider'].':', "providerptr", $source['providerptr'], $activeProviderSelections);
		if($source['surchargeid'] && $source['providerptr'] && 
				!providerInArray($source['providerptr'], $activeProviderSelections)) {
			$selectProv = getProvider($source['providerptr']);
			$pName =  providerShortName($selectProv);
			$reason = providerNotListedReason($selectProv, $source);
			echo "<tr><td style='color:red;'colspan=2>This surcharge is assigned to $pName but should not be because $pName $reason.</td></tr>";
		}
	}
	
	checkboxRow($apptFields['cancelcomp'], 'cancelcomp', $oldCancelComp);


	echo "<tr><td>{$apptFields['charge']}:</td>";
	echo "<td><input id='charge' type='hidden' name='charge' size=2 value='{$source['charge']}'>
	          <div id='div_charge'>{$source['charge']}</div></td></tr>";
	
	echo "<tr><td>{$apptFields['rate']}:</td>";
	echo "<td><input id='rate' type='hidden' name='rate' size=2 value='{$source['rate']}'>
	          <div id='div_rate'>{$source['rate']}</div></td></tr>";

//function textRow($label, $name, $value=null, $rows=3, $cols=20, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null) {
	textRow($apptFields['note'].':', 'note', $source['note'], $rows=3, $cols=60);

	if($source['custom'])
 	  echo "<tr><td colspan=2>This surcharge has been modified since the time it was first scheduled.</td></tr>";
 	
	echo "</table></form>";
	$disabled = $status ? true : false;
	echo <<<JS
<script language='javascript'>



function freezeApptEditor(disabled) {
	// disable/enable all selects, one-liners, checkboxes, pets, and timeofday
	var targets = 'providerptr|note|surchargecode'.split('|');
	for(var i=0;i < targets.length; i++) document.getElementById(targets[i]).disabled = disabled;
}
freezeApptEditor($disabled);
</script>
JS;

}
