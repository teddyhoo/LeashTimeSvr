<? // discount-fns.php

function getCurrentClientDiscount($clientptr) {
	return fetchFirstAssoc("SELECT * FROM relclientdiscount WHERE clientptr = $clientptr LIMIT 1");
}

function getDiscounts($activeOnly=false, $sort='') {
	$filter = $activeOnly ? "WHERE active=1" : '';
	$orderBy = $sort ? "ORDER BY $sort" : "ORDER BY label";
	return fetchAssociations("SELECT * FROM tbldiscount $filter $orderBy");
}

function getClientDiscounts($clientptr, $discountptr=null) {
	$filter = $discountptr ? "WHERE discountptr = $discountptr" : '';
	return fetchAssociations("SELECT * FROM relapptdiscount $filter");
}


function getDiscount($discountptr) {
	return fetchFirstAssoc("SELECT * FROM tbldiscount WHERE discountid = $discountptr LIMIT 1");
}


function getAppointmentDiscount($appointmentptr, $withName=null) {
	if($withName) {
		$andLabel = ', label';
		$leftJoin = 'LEFT JOIN tbldiscount ON discountid = discountptr';
	}
	$sql = "SELECT relapptdiscount.* $andLabel FROM relapptdiscount $leftJoin WHERE appointmentptr = $appointmentptr LIMIT 1";
	return fetchFirstAssoc($sql);
}

function getAppointmentDiscounts($appointmentptrs, $withName=null) {
	if(!$appointmentptrs) return array();
	if($withName) {
		$andLabel = ', label';
		$leftJoin = 'LEFT JOIN tbldiscount ON discountid = discountptr';
	}
	$appointmentptrs = is_array($appointmentptrs) ? join(',', $appointmentptrs) : $appointmentptrs;
	return fetchAssociationsKeyedBy(
		"SELECT relapptdiscount.* $andLabel 
			FROM relapptdiscount $leftJoin 
			WHERE appointmentptr IN($appointmentptrs)",
						'appointmentptr');
}



function getEligibleServiceTypes($discountptr) {
	return fetchCol0("SELECT serviceptr FROM relservicediscount WHERE discountptr = $discountptr");
}


function getStartableDiscounts() {
	$discounts = fetchAssociations(
		"SELECT * 
			FROM tbldiscount 
			WHERE active = 1 
				AND (start IS NULL OR start <= '".date('Y-m-d').")
				AND (end IS NULL OR end >= '".date('Y-m-d').")");
	return $discounts;
}

function getEligibleDiscounts($appointmentOrService) {  // BOGUS
	$discounts = fetchCol0("SELECT discountptr FROM relservicediscount WHERE serviceptr = {$appointmentOrService['sevicecode']}");
	if(!$discounts) return;
	$discounts = fetchAssociations("SELECT * FROM tbldiscount WHERE active = 1 AND discountid IN (".join(',', $discounts).")");
	foreach($discounts as $i => $discount)
		if(!apptIsEligible($appointmentOrService, $discount))
			unset($discounts[$i]);
	return $discounts;
}

function dropAppointmentDiscount($appt) {
	$appt = is_array($appt) ? $appt['appointmentid'] : $appt;
	deleteTable('relapptdiscount', "appointmentptr = $appt", 1);
}

function dropAppointmentDiscounts($apptIds, $filter=null) {
	$filter = $filter ? "AND $filter" : '';
	deleteTable('relapptdiscount', "appointmentptr IN (".join(',', $apptIds).") $filter", 1);
}

function dropClientDiscount($client) {
	$client = is_array($client) ? $client['clientid'] : $client;
	deleteTable('relclientdiscount', "clientptr = $client", 1);
}

function applyScheduleDiscountWhereNecessary($appts) {
//echo 'before: '.count($appts);	
	global $scheduleDiscount;  // set when applying a discount to a package: array(clientptr,discountptr)
	if(!$appts) return;
	$appts = is_string($appts) ? explode(',',$appts) :  $appts;
	
	// Get appointment ids
	if(is_array($appts[0])) foreach($appts as $appt) $apptIds[] = $appt['appointmentid'];
	else $apptIds = $appts;
	
	// exclude paid or invoiced appointments
	$sql = "SELECT tblappointment.*, billableid
						FROM tblappointment 
							LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment' AND superseded = 0
						WHERE canceled IS NULL AND appointmentid IN (".join(',', $apptIds).") 
							AND (billableid IS NULL
								OR (invoiceptr IS NULL AND (paid IS NULL OR paid = 0.0)))"; 
	$appts = fetchAssociationsKeyedBy($sql, 'appointmentid');
//if(mattOnlyTEST())	{echo "BONK! ".print_r($appts, 1);exit;} //  && $app['appointmnetid'] == 53360
	// quit if there are no more appointments
	if(!$appts) return;
	// Get remaining appointment ids
	$apptIds = array_keys($appts);
	
//echo '<p>after: '.count($apptIds);	
	// find current scheduleDiscount (set in service editor POST, to client's default or to -1 or to another discount)
	$discountCodeOrNull = !$scheduleDiscount || $scheduleDiscount == -1 ? null : $scheduleDiscount['discountptr'];
	if(!$discountCodeOrNull) $scheduleDiscount = -1;
	$apptDiscounts = fetchKeyValuePairs(
		"SELECT appointmentptr, discountptr 
			FROM relapptdiscount
			WHERE appointmentptr IN (".join(',', $apptIds).")");
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo join(',', $apptIds).": [".print_r($apptDiscounts,1)."] ----> [$discountCodeOrNull]<br>";	
	$numDiscountedAppointments = 0;
	foreach($apptIds as $id) {
		if($apptDiscounts[$id] != $discountCodeOrNull) {
			if($apptDiscounts[$id]) dropAppointmentDiscount($id);
			if(discountAppointment($id))
					$numDiscountedAppointments++;
			if($appts[$id]['billableid']) {
				require_once "invoice-fns.php";
				recreateAppointmentBillable($id);
			}
		}
	}
//echo print_r($apptIds,1).'<p>',print_r($apptDiscounts,1);exit;
	return $numDiscountedAppointments;
}
		
	

function discountAppointment($appt) {
	global $scheduleDiscount;  // set when applying a discount to a package: array(clientptr,discountptr)
	if(!is_array($appt)) $appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $appt LIMIT 1");
	if(!$appt) return;
	// can't discount the same appt twice
	if(fetchRow0Col0("SELECT appointmentptr FROM relapptdiscount WHERE appointmentptr = {$appt['appointmentid']} LIMIT 1"))
		return null;
	// Which discount are we talking about?
	if($scheduleDiscount == -1) return null;  // Apply no discount to current schedule
	$clientDiscount = $scheduleDiscount;
	if(!$clientDiscount) $clientDiscount = fetchFirstAssoc("SELECT * FROM relclientdiscount WHERE clientptr = {$appt['clientptr']} LIMIT 1");
	if(!$clientDiscount) return null;
	// when was it started? $start is always non-null!
	$started = $clientDiscount['start'] != '0000-00-00' ? $clientDiscount['start'] : null;
	$discountTemplate = getDiscount($clientDiscount['discountptr']);
	if(!$started) { // because it is being applied ad-hoc
		// if appt date is out of range, discount cannot be started
		if($discountTemplate['start'] && ($discountTemplate['start'] > $appt['date'])) return null;
		else if($discountTemplate['end'] && ($discountTemplate['end']< $appt['date'])) return null;
	}
		
}	
	if($started && $discountTemplate['end'] && ($discountTemplate['end']< $appt['date']) // if appt date is past end and it is not duration limited
			&& (!$discountTemplate['durationlimited'] ||
						date('Y-m-d', strtotime("+ {$discountTemplate['duration']} days", strtotime($started))) < $appt['date'])) // discount is expired
			return null;
			
	if($started && $discountTemplate['duration']) { // if the discount has a duration and that duration is past
		$lastDate = date('Y-m-d', strtotime("+ {$discountTemplate['duration']} days", strtotime($started)));
		if($appt['date'] >= $lastDate)
			return null;
	}
	if($started && isOneTime($discountTemplate)) {
		$totalDiscount = getDiscountTotal($appt['clientptr'], $discountTemplate, $started);
		if($totalDiscount >= $discountTemplate['amount']) return null;
	}
	else $totalDiscount = 0;
	
	$amountToDiscount = $discountTemplate['ispercentage'] 
												? $appt['charge'] * $discountTemplate['amount'] / 100
												: min($discountTemplate['amount']-$totalDiscount, $appt['charge']);
												
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "DISCOUNT ($started): {$discountTemplate['amount']}-$totalDiscount".print_r($discountTemplate, 1); exit;}
	if($amountToDiscount) {
		insertTable('relapptdiscount', array('appointmentptr'=>$appt['appointmentid'], 
																					'clientptr'=>$appt['clientptr'],
																					'discountptr'=>$discountTemplate['discountid'],
																					'memberid'=>$clientDiscount['memberid'],
																					'amount'=>$amountToDiscount), 1);
		if(/*!$scheduleDiscount &&*/ !$started)  // ONLY makes sense when client discount is set
			updateTable('relclientdiscount', array('start'=>$appt['date']), 
										"clientptr = {$clientDiscount['clientptr']} AND discountptr = {$clientDiscount['discountptr']}", 1);
//echo "scheduleDiscount: $scheduleDiscount";exit;
	}
	return true;
}

function isOneTime($discountTemplate) {
	return !$discountTemplate['ispercentage'] && !$discountTemplate['unlimiteddollar'];
}

function getDiscountTotal($clientid, $discountPtrOrTemplate, $started) {  // assume discount has been started
	$totalDiscount = 0;
	if(!$discountPtrOrTemplate) return $totalDiscount;
	$discountTemplate = is_array($discountPtrOrTemplate) ? $discountPtrOrTemplate : getDiscount($discountPtrOrTemplate);
	// find applications of this discount in the maximum date range for this discount
	if($discountTemplate['end'] || $discountTemplate['duration']) {
		if($discountTemplate['durationlimited'])  // durationlimited = start+duration is the limit.  if!durationlimited, end is the limit
			$lastDate = date('Y-m-d', strtotime("+ {$discountTemplate['duration']} days", strtotime($started)));
		else $lastDate = $discountTemplate['end'];
//echo "LAST DATE: $lastDate<br>";
		$lastDate = "AND date < '$lastDate'";
	}
	else $lastDate = '';
	$apptDiscounts = fetchAssociationsKeyedBy(
		"SELECT * 
			FROM relapptdiscount
			WHERE clientptr = $clientid
				AND discountptr = {$discountTemplate['discountid']}", 'appointmentptr');
	$appts = $apptDiscounts ? fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE appointmentid IN ("
																	.join(',', array_keys($apptDiscounts))
																	.") AND date >= '$started' $lastDate", 'appointmentid')
													: null;
	if($appts) foreach($appts as $id => $unused) $totalDiscount += $apptDiscounts[$id]['amount'];
	return $totalDiscount;
}

/*function totalDiscountApplication($scheduleDiscount, $discountTemplate=null) {
	if(!$discountTemplate) $discountTemplate = getDiscount($scheduleDiscount['discountptr']);
	$startParam = $discountTemplate['start'] ? "start > '{$discountTemplate['start']}'" : '';
	$endParam = $discountTemplate['end'];
	if($discountTemplate['durationlimited'] && $discountTemplate['duration'])
		$endParam = date("+ {$discountTemplate['duration']} days", strtotime($scheduleDiscount['start']);
*/
function replaceDiscountServices($discountptr, $serviceTypes) {
	deleteTable('relservicediscount', "discountptr = $discountptr", 1);
	if($serviceTypes) 
		foreach($serviceTypes as $serviceptr)
			insertTable('relservicediscount', array('discountptr'=>$discountptr, 'serviceptr'=>$serviceptr), 1);
}

function aggregateDiscountInfo($apptIds) {
	if(!$apptIds) return array();
	
	$discounts = fetchAssociations("SELECT appointmentptr, relapptdiscount.amount, label 
																	FROM relapptdiscount 
																	LEFT JOIN tbldiscount ON discountid = discountptr 
																	WHERE appointmentptr IN (".join(',', $apptIds).")");
																	
	$labels = array();
	foreach($discounts as $discount) {
		$labels[] = $discount['label'];
		$sum += $discount['amount'];
	}
//foreach($discounts as $b) echo print_r($b, 1).'<br>';echo "(".count($discounts).") \$ $sum<br>";
	return array('label'=>join(', ', array_unique($labels)), 'amount'=>(0-$sum));
}
	