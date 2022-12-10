<?
//pay-fns.php

$payTypes = array('regular'=>'Regular', 'adhoc'=>'Ad-hoc');


// #######################################################


function generatePayables($lastDay, $prov=null, $deleteAllFirst=false) {
	if($_SESSION['generatePayables_in_progress']) return;
	$_SESSION['generatePayables_in_progress'] = date('Y-m-d');
  $lastDay = date('Y-m-d 23:59:59', strtotime($lastDay));
  $today = date('Y-m-d');
  $providerFilter = $prov ? "AND tblappointment.providerptr = $prov" : '';
$start = microtime(1);
  // delete all unpaid payables
  if($deleteAllFirst) deleteTable('tblpayable', 'paid = 0.00',1);
$stop = microtime(1);  

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
if('FOREVERYONE') {//  ####### START NEWER
	// find all apptids for all appts without payables
	
	$apptids = fetchCol0($sql =
	"SELECT appointmentid
		 FROM tblappointment
		 		LEFT JOIN tblpayable ON itemptr = appointmentid AND itemtable = 'tblappointment'
		 WHERE completed IS NOT NULL 
		 			AND canceled IS NULL $providerFilter 
		 			AND rate+ifnull(bonus,0) > 0.0 
		 			AND tblappointment.date <= '$lastDay'
		 			AND itemptr IS NULL");
screenLog("appointment search: ".((microtime(1) - $stop) * 1000).' ms'." found ".count($apptids)." apptids."); $stop = microtime(1);

} //  ####### END NEWER
else if('FOREVERYONE') {//  ####### START NEW
	$apptids = fetchKeyValuePairs($sql =
  "SELECT appointmentid, 1
     FROM tblappointment 
     WHERE completed IS NOT NULL AND canceled IS NULL $providerFilter AND rate+ifnull(bonus,0) > 0.0 AND tblappointment.date <= '$lastDay'");
screenLog("appointment search: ".((microtime(1) - $stop) * 1000).' ms'." found ".count($apptids)." apptids."); $stop = microtime(1);
//if(mattOnlyTEST()) echo "generatePayables apptids: ".count($apptids);
	// whittle down $apptids
	if($apptids) {
		$result = doQuery("SELECT itemptr FROM tblpayable WHERE itemptr IN (".join(',', array_keys($apptids)).") AND itemtable = 'tblappointment'", 1);
		while($row = mysql_fetch_row($result)) {
//echo mysql_num_rows($result)." rows found. ".print_r($row,1);exit;
			unset($apptids[$row[0]]);
		}
		$apptids = array_keys($apptids);
//print_r($apptids);exit;
	}
} //  ####### END NEW
else {//  ####### START OLD
	$apptids = fetchCol0($sql =
  "SELECT appointmentid
     FROM tblappointment 
     WHERE completed IS NOT NULL AND canceled IS NULL $providerFilter AND rate+ifnull(bonus,0) > 0.0 AND tblappointment.date <= '$lastDay'");
//if(mattOnlyTEST()) echo "$sql<hr>".print_r($apptids, 1)."<hr>";
screenLog("appointment search (old): ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
	//$payableitems = fetchCol0("SELECT itemptr FROM tblpayable WHERE itemptr IN (".join(',', $apptids).") AND itemtable = 'tblappointment'");
	$payableitems = $apptids ?
										fetchCol0("SELECT itemptr FROM tblpayable WHERE itemptr IN (".join(',', $apptids).") AND itemtable = 'tblappointment'")
										: array();
screenLog("apptids: ".count($apptids). " - ".count($payableitems)." = ".(count($apptids) - count($payableitems)));

	$apptids = array_diff($apptids, $payableitems);
}	//  ####### END OLD

if(FALSE && !staffOnlyTEST()) { // causes memory size exceeded error RETIRED 12/17/2019
  $unpaidCompletedAppointments = !$apptids 
  	? array() 
  	:fetchAssociationsKeyedBy(
			"SELECT tblappointment.providerptr, appointmentid, rate+ifnull(bonus,0) as amount, tblappointment.date 
				 FROM tblappointment 
				 WHERE appointmentid IN (".join(',', $apptids).")", 'appointmentid');				 
screenLog("unpaidCompletedAppointments search: ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
screenLog("Found ".count($unpaidCompletedAppointments)." unpaidCompletedAppointments.");
  foreach($unpaidCompletedAppointments as $id => $appt)
    insertTable('tblpayable', 
      array('providerptr'=>$appt['providerptr'], 'itemptr'=>$id, 'itemtable'=>'tblappointment', 
            'amount'=>$appt['amount'], 'date'=>$appt['date'], 'gendate'=>$today));
}

else { // memory safe replacement
  $unpaidCompletedAppointmentsResult = !$apptids 
  	? null
  	:doQuery(
			"SELECT tblappointment.providerptr, appointmentid, rate+ifnull(bonus,0) as amount, tblappointment.date 
				 FROM tblappointment 
				 WHERE appointmentid IN (".join(',', $apptids).")", 1);
				 
if($unpaidCompletedAppointmentsResult) {
screenLog("unpaidCompletedAppointments search: ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
screenLog("Found ".mysql_num_rows($unpaidCompletedAppointmentsResult)." unpaidCompletedAppointments.");
  while($appt = mysql_fetch_array($unpaidCompletedAppointmentsResult, MYSQL_ASSOC)) {
		$id = $appt['appointmentid'];
    insertTable('tblpayable', 
      array('providerptr'=>$appt['providerptr'], 'itemptr'=>$id, 'itemtable'=>'tblappointment', 
            'amount'=>$appt['amount'], 'date'=>$appt['date'], 'gendate'=>$today));
	}
}
}
            
            
            
screenLog("visit payable creation: ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  if($_SESSION['surchargesenabled']) {
		$providerFilter = $prov ? "AND providerptr = $prov" : '';
		$surchargeids = fetchCol0(
		"SELECT surchargeid
			 FROM tblsurcharge 
			 WHERE completed IS NOT NULL AND canceled IS NULL AND rate > 0.0 $providerFilter AND date <= '$lastDay'");
		$payableitems = $surchargeids ?
											fetchCol0("SELECT itemptr FROM tblpayable WHERE itemptr IN (".join(',', $surchargeids).") AND itemtable = 'tblsurcharge'")
											: array();
		$surchargeids = array_diff($surchargeids, $payableitems);
		$unpaidCompletedSurcharges = !$surchargeids 
			? array() 
			:fetchAssociationsKeyedBy(
				"SELECT tblsurcharge.providerptr, surchargeid, rate as amount, date 
					 FROM tblsurcharge 
					 WHERE surchargeid IN (".join(',', $surchargeids).")", 'surchargeid');
	screenLog("surcharges search: ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
	screenLog("Found ".count($unpaidCompletedSurcharges)." unpaidCompletedSurcharges.");
		foreach($unpaidCompletedSurcharges as $id => $appt)
			insertTable('tblpayable', 
				array('providerptr'=>$appt['providerptr'], 'itemptr'=>$id, 'itemtable'=>'tblsurcharge', 
							'amount'=>$appt['amount'], 'date'=>$appt['date'], 'gendate'=>$today));
}
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
  // Generate payables for all other compensation items
  $stop = microtime(1);
  $providerFilter = $prov ? "providerptr = $prov AND" : '';
     
  $allOtherCompIds = fetchCol0("SELECT compid
																FROM tblothercomp
																	 WHERE $providerFilter date <= '$lastDay'");
																	 

  $payableitems = 
  		$allOtherCompIds ? fetchCol0("SELECT itemptr FROM tblpayable WHERE itemptr IN (".join(',', $allOtherCompIds).") AND itemtable = 'tblothercomp'")
			:  array();
	$otherCompIds = array_diff($allOtherCompIds, $payableitems);
	
	$unpaidOtherComps = !$otherCompIds ? array() : fetchAssociationsKeyedBy(
		"SELECT providerptr, compid, amount, date  
			 FROM tblothercomp
			 WHERE compid  IN (".join(',', $otherCompIds).")", 'compid');
//if(mattOnlyTEST()) print_r($unpaidOtherComps);
			
  /*$unpaidOtherComps = fetchAssociationsKeyedBy(
  "SELECT tblothercomp.providerptr, compid, tblothercomp.amount, tblothercomp.date  
     FROM tblothercomp LEFT JOIN tblpayable ON itemtable = 'tblothercomp' AND itemptr = compid
     WHERE payableid IS NULL $providerFilter AND tblothercomp.date <= '$lastDay'", 'compid');
   */  
     

screenLog("other compensation search found ".count($unpaidOtherComps).": ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
  foreach($unpaidOtherComps as $id => $comp)
    insertTable('tblpayable', array('providerptr'=>$comp['providerptr'], 'itemptr'=>$id, 'itemtable'=>'tblothercomp', 
            'amount'=>$comp['amount'], 'date'=>$comp['date'], 'gendate'=>$today));
     
screenLog("other payable creation: ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
  // Generate payables for gratuities
  $providerFilter = $prov ? "AND tblothercomp.providerptr = $prov" : '';
  $unpaidGratuities = fetchAssociationsKeyedBy(
  "SELECT tblgratuity.providerptr, gratuityid, tblgratuity.amount, issuedate  
     FROM tblgratuity 
     LEFT JOIN tblpayable ON itemtable = 'tblgratuity' AND itemptr = gratuityid
     WHERE payableid IS NULL AND issuedate <= '$lastDay'", 'gratuityid');
screenLog("gratuity search: ".((microtime(1) - $stop) * 1000).' ms found '.count($unpaidGratuities).' records.'); $stop = microtime(1);
  foreach($unpaidGratuities as $id => $gratuity)
    insertTable('tblpayable', array('providerptr'=>$gratuity['providerptr'], 'itemptr'=>$id, 'itemtable'=>'tblgratuity', 
            'amount'=>$gratuity['amount'], 'date'=>$gratuity['issuedate'], 'gendate'=>$today));
screenLog("gratuity payable creation: ".((microtime(1) - $stop) * 1000).' ms'); $stop = microtime(1);
screenLog("generatePayables total: ".((microtime(1) - $start) * 1000).' ms');
	unset($_SESSION['generatePayables_in_progress']);
}

// #######################################################

function getUnpaidPayables($lastDay, $prov=null, $firstDay=null) {
  //$providerFilter = $prov ? "AND providerptr = $prov" : ''; // allow Unassigned
  $providerFilter = "AND providerptr = $prov";
  $firstDay = $firstDay ? "AND date >= '$firstDay'" : '';
	return fetchAssociationsKeyedBy(
		"SELECT * FROM tblpayable 
		  WHERE paid < amount $providerFilter AND date <= '$lastDay' $firstDay
		  ORDER BY date", 'payableid');
}

function getUnpaidPayablesDBResult($lastDay, $prov=null, $firstDay=null) {
  $providerFilter = $prov ? "AND providerptr = $prov" : '';
  $firstDay = $firstDay ? "AND date >= '$firstDay'" : '';
	return mysql_query(
		"SELECT * FROM tblpayable 
		  WHERE paid < amount $providerFilter AND date <= '$lastDay' $firstDay
		  ORDER BY date");
}

function payProvider($prov, $amount, $type, $throughDate, $note, $transactionId, $startDate=null, $adjustment=null, $paymentDate=null) {
	$paymentDate = $paymentDate ? date('Y-m-d', strtotime($paymentDate)) : date('Y-m-d');
	// create a provider payment (tblproviderpayment)
	$payment = array('providerptr'=>$prov, 'amount'=>$amount, 'paymenttype'=>$type, 
										'enddate'=>$throughDate,  'note'=>$note, 'transactionId'=>$transactionId, 
										'startdate'=>$startDate, 'paymentdate'=>$paymentDate, 'adjustment'=>$adjustment);
	$paymentId = insertTable('tblproviderpayment', $payment, 1);						
	// go through provider's unpaid payables, incrementing each payable.paid by the remainder of $amount until none is left
	$payables = getUnpaidPayables($throughDate, $prov, $startDate);
	
	foreach($payables as $payable) {
		if($payable['itemtable'] == 'tblappointment') {
			if(!$travelAllowanceSet) {
				$travelAllowanceSet = true;
				$travelAllowance = 
					fetchRow0Col0("SELECT value FROM tblproviderpref WHERE providerptr = $prov AND property = 'travelAllowance'");
				}
			$mileageCompensation += $travelAllowance;
		}
	}
	
	if($mileageCompensation) {  // $amount already includes mileageCompensation
		$mileagePayable = array('providerptr'=>$prov, 'itemtable'=>'travelAllowance', 'itemptr'=>-1, 'amount'=>$mileageCompensation,
														'date'=>date('Y-m-d'), 'gendate'=>date('Y-m-d H:i:s'));
		$mileagePayable['payableid'] = insertTable('tblpayable', $mileagePayable, 1);
		$payables[$mileagePayable['payableid']] = $mileagePayable;
		/*
			$compptr =
				insertTable("tblothercomp", 
					array('amount'=>$amount, 'descr'=>$note, 'providerptr'=>$providerptr, 'comptype'=>'adhoc', 'date'=>$today), 1);
			$payable = 
				array('amount'=>$amount, 'providerptr'=>$providerptr, 'itemtable'=>'tblothercomp', 'itemptr'=>$compptr, 'date'=>$today);
			if($usage == "immediate") {
				$payable['paid'] = $amount;
				$payable['datepaid'] = $today;
			}
			$payableptr =  insertTable("tblpayable", $payable, 1);
		*/
	}


	
	
	$negatories = getNegativePayments($throughDate, $prov, $startDate);
	// first apply negative payments to payables
	$negsPaid = applyNegativePayments($payables, $negatories, $paymentId, $prov);
	foreach($negsPaid as $n => $justPaid)
		updateTable('tblnegativecomp', array('paid'=>$negatories[$n]['paid']), "negcompid={$negatories[$n]['negcompid']}", 1);
	
	
	foreach($payables as $payable) {
		if(!$amount) break;
		$pay = min($amount, $payable['amount'] - $payable['paid']);
		$amount -= $pay;
		// update payable.paid with pay
		updateTable('tblpayable', array('paid'=>$payable['paid']+$pay), "payableid={$payable['payableid']}", 1);
	  // associate the payable with the payment in relproviderpayablepayment
		insertTable('relproviderpayablepayment', 
								array('payableptr'=>$payable['payableid'], 'providerpaymentptr'=>$paymentId, 'providerptr'=>$prov), 1);
	}
}

function applyNegativePayments(&$payables, &$negatories, $paymentId, $prov) {
	$negatoriesApplied = array();
	foreach($negatories as $n => $unused1) {
		$applied = false;
		if($negatories[$n]['paid'] < $negatories[$n]['amount']) 
			foreach($payables as $p => $unused2) {
				if($payables[$p]['paid'] < $payables[$p]['amount']) {
					$toPay = min(($negatories[$n]['amount'] - $negatories[$n]['paid']), ($payables[$p]['amount'] - $payables[$p]['paid']));
					$payables[$p]['paid'] += $toPay;
					$negatories[$n]['paid'] += $toPay;
					$negatoriesApplied[$n] += $toPay;
					if(!$applied) 
						insertTable('relproviderpayablepayment', 
											array('negative'=>1, 'payableptr'=>$negatories[$n]['negcompid'], 'providerpaymentptr'=>$paymentId, 'providerptr'=>$prov), 1);
					$applied = true;

				}
			}
	}
	return $negatoriesApplied;
}

function getPaymentPayables($id) {
	return fetchAssociationsKeyedBy(
		"SELECT tblpayable.* 
		  FROM relproviderpayablepayment 
		  LEFT JOIN tblpayable ON payableid = payableptr
		  WHERE providerpaymentptr = $id AND (negative IS NULL OR negative = 0) ORDER BY date", 'payableid');
}

function getPaymentNegativeComps(&$payables, $id) {
	$negatories = fetchAssociationsKeyedBy(
		"SELECT tblnegativecomp.* 
		  FROM relproviderpayablepayment LEFT JOIN tblnegativecomp ON negcompid = payableptr
		  WHERE providerpaymentptr = $id AND negative = 1 ORDER BY date", 'negcompid');
	foreach($negatories as $neg) {
		$neg['clientptr'] = 0;
		$neg['amount'] = 0 - $neg['amount']; //($neg['amount'] - $neg['paid']);
		$payables['NEG_'.$neg['negcompid']] = $neg;
	}
}


function getProviderPayment($id) {
	return fetchFirstAssoc(
		"SELECT * FROM tblproviderpayment WHERE paymentid = $id LIMIT 1");
}

function getProviderPayments($prov, $where=null, $orderby=null) {
	$sql = "SELECT * FROM tblproviderpayment WHERE providerptr = $prov";
	if($where) $sql .= " AND $where";
	if($orderby) $sql .= " ORDER BY $orderby";
	return fetchAssociations($sql);
}

function getPayableDetails(&$payables) {
	foreach($payables as $payable) {
		if($payable['itemtable'] == 'tblappointment') {
			$appointmentids[$payable['itemptr']] = $payable['payableid'];
			$appointmentPay += $payable['amount']-$payable['paid'];
		}
//print_r($appointmentids);exit;	
		if($payable['itemtable'] == 'tblsurcharge') {
			$surchargeids[$payable['itemptr']] = $payable['payableid'];
			$surchargePay += $payable['amount']-$payable['paid'];
		}
		else if($payable['itemtable'] == 'tblothercomp') {
			// othercomp comptype may be cancelcomp or cancelsurcharge
			$compids[$payable['itemptr']] = $payable['payableid'];
			//$rcompids[$payable['payableid']] = $payable['itemptr'];
			$otherPay += $payable['amount']-$payable['paid'];
		}
		else if($payable['itemtable'] == 'tblgratuity') {
			$gratuityids[$payable['itemptr']] = $payable['payableid'];
			$gratuityPay += $payable['amount']-$payable['paid'];
		}
	}

	$sqlselect = "SELECT appointmentid, clientptr, timeofday, starttime, servicecode, pets, rate, bonus, surchargenote ";

	//print_r($appointmentids);		exit;
	if($appointmentids) {
		$idstring = join(',',array_keys($appointmentids));
		foreach(fetchAssociations("$sqlselect FROM tblappointment WHERE appointmentid IN ($idstring)") as $appt) {
			$payables[$appointmentids[$appt['appointmentid']]] = 
				array_merge($payables[$appointmentids[$appt['appointmentid']]], $appt);
		}
	}

	if($surchargeids) {
		$idstring = join(',',array_keys($surchargeids));
		foreach(fetchAssociations("SELECT surchargeid, clientptr, timeofday, starttime, surchargecode, rate FROM tblsurcharge WHERE surchargeid IN ($idstring)") as $surcharge) {
			$payables[$surchargeids[$surcharge['surchargeid']]] = 
				array_merge($payables[$surchargeids[$surcharge['surchargeid']]], $surcharge);
		}
	}

	//print_r($payables);		exit;
	if($compids) {
		//$idstring = join(',',array_keys($compids));
		$idstring = join(',',array_keys($compids));
		$comps =  fetchAssociationsKeyedBy(
				"SELECT compid, appointmentptr, descr, amount, comptype FROM tblothercomp WHERE compid IN ($idstring)", 
				'compid', 1);
		foreach($comps as $compid => $comp) {
			if($comp['comptype'] == 'adhoc') {
			}
			else if($comp['comptype'] == 'cancelcomp') {
				$appt = fetchFirstAssoc(
							"SELECT appointmentid, clientptr, timeofday, starttime, servicecode, pets, rate, bonus, surchargenote 
									FROM tblappointment
									WHERE appointmentid = {$comp['appointmentptr']}", 1);
				foreach($appt as $k => $v) $comps[$compid][$k] = $v;
			}
			else if($comp['comptype'] == 'cancelsurcharge') {
				if($comp['appointmentptr']) {
					$surch = fetchFirstAssoc(
							"SELECT surchargeid, clientptr, timeofday, starttime, surchargecode, rate
									FROM tblsurcharge
									WHERE surchargeid = {$comp['appointmentptr']}", 1);
					foreach($surch as $k => $v) $comps[$compid][$k] = $v;
				}
			}
		}
			
		foreach($comps as $compid => $comp) {
	//print_r($comp);		exit;		
			$payables[$compids[$comp['compid']]] = 
				array_merge($payables[$compids[$comp['compid']]], $comp);
//if(mattOnlyTEST())print_r($payables[$compids[$comp['compid']]]);
		}
	}
	if($gratuityids) {
		$idstring = join(',',array_keys($gratuityids));
		$sel = "gratuityid, tipnote as descr, amount, clientptr, 'gratuity' as comptype, paymentptr, issuedate FROM tblgratuity"; 
		foreach(fetchAssociations("SELECT $sel WHERE gratuityid IN ($idstring)") as $gratuity) {
	//print_r($comp);		exit;		
			$payables[$gratuityids[$gratuity['gratuityid']]] = 
				array_merge($payables[$gratuityids[$gratuity['gratuityid']]], $gratuity);
		}
	}
}

function getNegativePaymentDetails(&$payables, $through, $provider=null) {
	$date = date('Y-m-d', strtotime($through));
	$provider = $provider ? "AND providerptr = $provider" : '';
	$negatories = fetchAssociations("SELECT * FROM tblnegativecomp WHERE paid < amount AND date <= '$date' $provider");
	foreach($negatories as $neg) {
		$neg['clientptr'] = 0;
		$neg['amount'] = 0 - ($neg['amount'] - $neg['paid']);
		$payables['NEG_'.$neg['negcompid']] = $neg;
	}
}

function getNegativePayments($through, $provider=null, $firstDay=null) {
	$date = date('Y-m-d', strtotime($through));
	$provider = $provider ? "AND providerptr = $provider" : '';
  $firstDay = $firstDay ? "AND date >= '$firstDay'" : '';
	return fetchAssociations("SELECT * FROM tblnegativecomp WHERE paid < amount AND date <= '$date' $provider $firstDay");
}

function payablesSort($a, $b) {
	global $clients;
	$result = strcmp($a['date'], $b['date']);
	if(!$result) {
		$result = strcmp($clients[$a['clientptr']], $clients[$b['clientptr']]);
	}
	if(!$result) {
		$result = strcmp($a['starttime'], $b['starttime']);
	}
	if(!$result) {
		$result = strcmp($clients[$a['clientptr']], $clients[$b['clientptr']]);
	}
	if(!$result) {
		$a = isset($a['appointmentid']) ? '1' : 2;
		$b = isset($b['appointmentid']) ? '1' : 2;
		$result = strcmp($a, $b);
	}
	return $result;
}

function payableSummaryTable($payables) {
	require_once "service-fns.php";
	getAllServiceNamesById();
	$surchargeNames = getSurchargeTypesById();
	foreach($payables as $k => $payable) {
		if($payable['itemtable'] == 'travelAllowance') continue;
		if($payable['appointmentid']) {
			$amount = $payable['rate']+$payable['bonus'];
			$byServiceAmount[$_SESSION['allservicenames'][$payable['servicecode']]] += $amount;
			$byServiceCount[$_SESSION['allservicenames'][$payable['servicecode']]] += 1;
		}
		else if($payable['surchargeid']) {
			require_once "surcharge-fns.php";
			$amount = $payable['rate'];
			$byServiceAmount[$surchargeNames[$payable['surchargecode']]] += $amount;
			$byServiceCount[$surchargeNames[$payable['surchargecode']]] += 1;
		}
		else if($payable['negcompid']){
//print_r($row);exit;			
			$amount = $payable['amount'];
			$byServiceAmount['Negative Compensation'] += $amount;
			$byServiceCount['Negative Compensation'] += 1;
		}
		else {
			$amount = $payable['amount'];
			$comptype = $payable['comptype'] == 'gratuity' ? 'Gratuity' : (
									$payable['comptype'] == 'adhoc' ? 'Ad Hoc' : (
									$payable['comptype'] ? $payable['comptype'] : (
									$payable['itemtable'] == 'travelAllowance' ? 'Travel Allowance' : (
									
							'--'.(staffOnlyTEST() ? $payable['itemtable']."[{$payable['itemptr']}]" : '')))));
			$byServiceAmount[$comptype] += $amount;
			$byServiceCount[$comptype] += 1;
		}
	}
	$rows = array();
	if($byServiceAmount) {
		ksort($byServiceAmount);
		foreach($byServiceAmount as $service => $amount)
			$rows[] = array('service'=>$service, 'count'=>$byServiceCount[$service], 'total'=>dollarAmount($amount));
	}
	$columns = explodePairsLine('service|Service||count|Count||total|Total');
	$colClasses = array('count'=>'dollaramountcell', 'total'=>'dollaramountcell');
	tableFrom($columns, $rows, 'WIDTH=600', null, null, null, null, null, null, $colClasses);
}

function payablesTable(&$payables, $noEdit=false, $showPaid=false, $noLinks=false, $suppressCols='') {
	usort($payables, 'payablesSort');
	$clientids = array();
	foreach($payables as $k => $payable) if($payable['clientptr']) $clientids[] = $payable['clientptr'];

	if($payables) $clients = getClientDetails(array_unique($clientids));
	

	$noEdit = $noEdit ? '?noedit=1' : '?';
	$columns = explodePairsLine('service|Service||amount|Pay||client|Client||timeofday|Time of Day||pets|Pets');
	$suppressCols = !$suppressCols ? array() : (is_array($suppressCols) ? $suppressCols : explode(',', $suppressCols));
	foreach($suppressCols as $col) unset($columns[$col]);
	$colClasses = array('amount'=>'dollaramountcell');
	$lastDate = null;
	require_once "service-fns.php";
	getAllServiceNamesById();
	foreach($payables as $k => $payable) {
		if($payable['itemtable'] == 'travelAllowance') continue;
		$payable['date'] = shortDate(strtotime($payable['date']));
		if($lastDate != $payable['date']) {
			$lastDate = $payable['date'];
			$rows[] = array('#CUSTOM_ROW#'=>"<tr><td class='dateRow boldfont' colspan=".(count($columns)).">".longerDayAndDate(strtotime($lastDate))."</td></tr>");
		}
		$amount = $showPaid ?  $payable['paid'] : $payable['amount']-$payable['paid'];
		$amount = dollarAmount($amount);
		$row = array(); 
		$row['amount'] = $amount;
		$row2 = array();
		if($payable['appointmentid']) {
			if(!$showPaid && $payable['bonus'] && $payable['surchargenote']) {
				$row2['amount'] = dollarAmount($payable['bonus']);
				$row2['service'] = 'Bonus: '.$payable['surchargenote'];
				$row['amount'] = dollarAmount($payable['rate']);
			}
			$row['service'] = $_SESSION['allservicenames'][$payable['servicecode']];
			if($payable['comptype'] == 'cancelcomp') {
				$row['service'] .= "<span title='Sitter compensated for a canceled appointment.' style='color:red;font-size:9px;font-variant: small-caps'> Canceled</span>";
			}
			$row['service'] = fauxLink($row['service'], "openConsoleWindow(\"editappt\", \"appointment-view.php$noEdit&id={$payable['appointmentid']}\",530,450)", 1);

			$row['client'] = $clients[$payable['clientptr']]['clientname'];
			$row['timeofday'] = $payable['timeofday'];
			$row['pets'] = $payable['pets'];
		}
		else if($payable['surchargeid']) {
			require_once "surcharge-fns.php";
			$surchargeNames = getSurchargeTypesById();
			$row['service'] = $surchargeNames[$payable['surchargecode']];
			if($payable['comptype'] == 'cancelsurcharge') {
				$row['service'] .= "<span title='Sitter compensated for a canceled surcharge.' style='color:red;font-size:9px;font-variant: small-caps'> Canceled</span>";
			}
			$row['service'] = fauxLink($row['service'], "openConsoleWindow(\"editappt\", \"surcharge-edit.php$noEdit&id={$payable['surchargeid']}\",530,450)", 1);

			$row['client'] = $clients[$payable['clientptr']]['clientname'];
			$row['timeofday'] = $payable['timeofday'];
		}
		else if($payable['negcompid']){
//print_r($row);exit;			
			$amount = dollarAmount($payable['amount']);
			$link = userRole() == 'p'
				? "<span title='NegCompID: {$payable['negcompid']}'>Negative Compensation</span>"
				: fauxLink('Negative Compensation', "openConsoleWindow(\"paydocker\", \"neg-compensation-edit.php?id={$payable['negcompid']}\",530,450)", 1);
			$row = array('#CUSTOM_ROW#'=>"<tr><td class='sortableListCell'>$link</td><td class='dollaramountcell'>$amount</td><td  class='sortableListCell' colspan=3>{$payable['reason']}</td></tr>");
		}
		else {
			$row['service'] = $payable['comptype'] ? $payable['comptype'].(0 && mattOnlyTEST() ? print_r($payable, 1) : '' ) : (
				$payable['itemtable'] == 'travelAllowance' ? 'Travel Allowance' : (
				$payable['itemtable'] == 'tblgratuity' ? "Gratuity [{$payable['itemptr']}]" : (
				'--'.(staffOnlyTEST() ? $payable['itemtable']."[{$payable['itemptr']}]" : ''))));
			if($payable['itemtable'] != 'tblgratuity') $unknownPayableIDs[] = $payable['payableid'];
			if(!$payable['clientptr']) {
				if($payable['comptype'] == 'adhoc')
					$row['service'] = fauxLink('Ad Hoc', "openConsoleWindow(\"paymentedit\", \"provider-adhoc-payment-payable.php?payableptr={$payable['payableid']}\",530,450)", 1);
				if(strpos($row['service'], '[')) { // lost details
					$row['descr'] = 'Item deleted. Details unavailable.';
					if(mattOnlyTEST()) {
						$gg = fetchFirstAssoc("SELECT * FROM tblgratuity WHERE gratuityid = {$payable['itemptr']} LIMIT 1", 1);
						$row['service'] = gratuityLink($gg);
						$row['descr'] .= print_r($payable, 1);
					}
				}
				else {
					$row['descr'] = $payable['descr'] ? safeValue($payable['descr']) : '&nbsp;';
				if($row['descr'] == '&nbsp;') {
					if($payable['comptype'] == 'cancelsurcharge') {
						$row['descr'] = "Sitter compensated for a canceled surcharge.";
					}
				}
				}
				$row = array('#CUSTOM_ROW#'=>"<tr><td class='sortableListCell'>{$row['service']}</td><td class='dollaramountcell'>$amount</td><td  class='sortableListCell' colspan=3>{$row['descr']}</td></tr>");
			}
			else {
				$row['client'] = $clients[$payable['clientptr']]['clientname'];
				$row['descr'] = $payable['descr'] ? safeValue($payable['descr']) : '&nbsp;';
				if($row['descr'] == '&nbsp;') {
					if($payable['comptype'] == 'cancelsurcharge') {
						$row['descr'] = "Sitter compensated for a canceled surcharge.";
					}
				}
				if($payable['comptype'] == 'gratuity') {
					$row['service'] = gratuityLink($payable);
					//if(mattOnlyTEST()) $row['descr'] .= print_r($payable, 1);
				}
				
				$row = array('#CUSTOM_ROW#'=>"<tr><td class='sortableListCell'>{$row['service']}</td><td class='dollaramountcell'>$amount</td>"
																			. "<td class='sortableListCell'>{$row['client']}</td>"
																			. "<td class='sortableListCell' colspan=3>{$row['descr']}</td></tr>");
			}
		}
		
		if($noLinks && $row['service']) $row['service'] = strip_tags($row['service']);
		
		$rows[] = $row;
		if($row2) $rows[] = $row2;
	}
	if(mattOnlyTEST() && $unknownPayableIDs) echo "<div style='width:400px;display:block;'>Unknown payable IDs: ".join(', ', $unknownPayableIDs)."</div>";
	tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, null, null, $colClasses);
}

function payablesCSV(&$payables, $noEdit=false, $showPaid=false, $noLinks=false, $suppressCols='') {
	usort($payables, 'payablesSort');
	$clientids = array();
	foreach($payables as $k => $payable) if($payable['clientptr']) $clientids[] = $payable['clientptr'];

	if($payables) $clients = getClientDetails(array_unique($clientids));
	

	$columns = explodePairsLine('service|Service||amount|Pay||client|Client||timeofday|Time of Day||pets|Pets');
	$suppressCols = !$suppressCols ? array() : (is_array($suppressCols) ? $suppressCols : explode(',', $suppressCols));
	foreach($suppressCols as $col) unset($columns[$col]);
	$lastDate = null;
	require_once "service-fns.php";
	getAllServiceNamesById();
	dumpCSVRow($columns);
	foreach($payables as $k => $payable) {
		if($payable['itemtable'] == 'travelAllowance') continue;
		$payable['date'] = shortDate(strtotime($payable['date']));
		$amount = $showPaid ?  $payable['paid'] : $payable['amount']-$payable['paid'];
		$row = array(); 
		$row['amount'] = $amount;
		$row2 = array();
		if($payable['appointmentid']) {
			$row['service'] = $_SESSION['allservicenames'][$payable['servicecode']];
			if($payable['comptype'] == 'cancelcomp') {
				$row['service'] .= "Compensated for canceled appointment";
			}
			$row['client'] = $clients[$payable['clientptr']]['clientname'];
			$row['timeofday'] = $payable['timeofday'];
			$row['pets'] = $payable['pets'];
		}
		else if($payable['surchargeid']) {
			require_once "surcharge-fns.php";
			$surchargeNames = getSurchargeTypesById();
			$row['service'] = $surchargeNames[$payable['surchargecode']];
			if($payable['comptype'] == 'cancelsurcharge') {
				$row['service'] .= "Compensated for canceled  surcharge";
			}
			$row['client'] = $clients[$payable['clientptr']]['clientname'];
			$row['timeofday'] = $payable['timeofday'];
		}
		else if($payable['negcompid']){
//print_r($row);exit;			
			$row['service'] = "Negative comp: {$payable['reason']}";
			$row['amount'] .= $payable['amount'];
		}
		else {
			$row['service'] = $payable['comptype'] ? $payable['comptype'].(0 && mattOnlyTEST() ? print_r($payable, 1) : '' ) : (
				$payable['itemtable'] == 'travelAllowance' ? 'Travel Allowance' : (
				$payable['itemtable'] == 'tblgratuity' ? "Gratuity [{$payable['itemptr']}]" : (
				'--'.(staffOnlyTEST() ? $payable['itemtable']."[{$payable['itemptr']}]" : ''))));
			if($payable['itemtable'] != 'tblgratuity') $unknownPayableIDs[] = $payable['payableid'];
			if(!$payable['clientptr']) {
				if($payable['comptype'] == 'adhoc')
					$row['service'] = 'Ad Hoc';
				if(strpos($row['service'], '[')) { // lost details
					$row['service'] = 'Item deleted. Details unavailable.';
					if(0 && mattOnlyTEST()) {
						$gg = fetchFirstAssoc("SELECT * FROM tblgratuity WHERE gratuityid = {$payable['itemptr']} LIMIT 1", 1);
						$row['service'] = gratuityLink($gg);
						$row['descr'] .= print_r($payable, 1);
					}
				}
				else {
					$row['service'] = $row['service'] ? safeValue($row['service']) : '';
				if($row['descr'] == '&nbsp;') {
					if($payable['comptype'] == 'cancelsurcharge') {
						$row['service'] = "Sitter compensated for a canceled surcharge";
					}
				}
				}
			}
			else {
				$row['client'] = $clients[$payable['clientptr']]['clientname'];
				$row['descr'] = $payable['descr'] ? safeValue($payable['descr']) : '&nbsp;';
				if($row['service'] == '') {
					if($payable['comptype'] == 'cancelsurcharge') {
						$row['service'] = "Sitter compensated for a canceled surcharge";
					}
				}
				if($payable['comptype'] == 'gratuity') {
					$row['service'] = 'Gratuity';
					//if(mattOnlyTEST()) $row['descr'] .= print_r($payable, 1);
				}
				
			}
		}
		dumpCSVRow($row, $cols=array_keys($columns)); // defined in including script
		//$rows[] = $row;
		//if($row2) $rows[] = $row2;
	}
}

function gratuityLink($gratuity) {
	if(!$gratuity['paymentptr']) {
		$time = strtotime($gratuity['issuedate']);
		return fauxLink('Gratuity', 
					"openConsoleWindow(\"editappt\", \"gratuity-edit.php?client={$gratuity['clientptr']}&issuedate=$time\",600,480)",
					"View this gratuity.", 1);
	}
	else return fauxLink('Gratuity', "openConsoleWindow(\"editappt\", \"payment-edit.php?id={$gratuity['paymentptr']}\",600,480)",
													"View this gratuity and its payment", 1);
	//"editCredit({$gratuity['paymentptr']}, 1)", "View this gratuity and its payment", 1);
}


function dumpProviderPayForm($provider) {
	echo "<p>";
//if($_SESSION['staffuser']) {global $db; echo "[$db]: ".conservativeDate('m/d/Y', 'now').' '.shortDate();}
  calendarSet('Payments made starting:', 'paystarting', (isset($starting) ? $starting : shortDate(strtotime(date('Y-01-01')))), null, null, true, 'ending');
  calendarSet('ending:', 'payending', (isset($ending) ? $ending : shortDate()));

	echoButton('', 'Show', 'showPayments()');
	$offerDailyPay = userRole() == 'p' ?  'enableDailyPaySitter'  : 'enableDailyPayOffice';
	$offerDailyPay = $_SESSION['preferences']['enableDailyPayOffice'];
	if($offerDailyPay) {
		//echo " <img src='art/spreadsheet-32x32.png' style='cursor:pointer;' title='Spreadsheet of Daily Pay for completed visits and gratuities.' onclick='dailyPay()'>";
		echo "<img src='art/spacer.gif' width=20>";
		echoButton('', 'Daily Pay Spreadsheet', 'dailyPay()', $class='', $downClass='', $noEcho=false, $title='Spreadsheet of Daily Pay for completed visits and gratuities.');
	}
	echo "<p>";
	
  echo <<<JSCODE
<div id='payhistory'></div>
<script language='javascript'>
function dailyPay() {
	setPrettynames('paystarting','Starting','payending','Ending');
  if(MM_validateForm(
		  'paystarting', '', 'R',
		  'payending', '', 'R',
		  'paystarting', '', 'isDate',
		  'payending', '', 'isDate')) {
		var prov = document.getElementById('providerid').value;
		prov = prov == -1 ? 0 : prov;
		var starting = document.getElementById('paystarting').value;
		var ending = document.getElementById('payending').value;
		if(starting) starting = '&paystarting='+starting;
		if(ending) ending = '&payending='+ending;
		//if(sort) sort = '&sort='+sort;
		var url = 'pay-history.php';
		// opens a spreadsheet
    document.location.href=url+'?dailySheet=1&prov='+prov+starting+ending;
	}
}

function showPayments() {
	setPrettynames('paystarting','Starting','payending','Ending');
  if(MM_validateForm(
		  'paystarting', '', 'isDate',
		  'payending', '', 'isDate')) {
		var prov = document.getElementById('providerid').value;
		prov = prov == -1 ? 0 : prov;
		var starting = document.getElementById('paystarting').value;
		var ending = document.getElementById('payending').value;
		if(starting) starting = '&paystarting='+starting;
		if(ending) ending = '&payending='+ending;
		//if(sort) sort = '&sort='+sort;
		var url = 'pay-history.php';
    //alert(url+'?prov='+prov+starting+ending);		
    ajaxGet(url+'?prov='+prov+starting+ending, 'payhistory')
	}
}
</script>
JSCODE;
}

function rollbackPayment($paymentid) {  // DANGER _ EXPERIMENTAL
	// payable itemtables: tblappointment, tblgratuity, tblsurcharge
	
	$payableReferences = fetchAssociations(
		"SELECT * 
		 FROM relproviderpayablepayment 
		 WHERE providerpaymentptr = $paymentid");
	
	// if paymenttype is adhoc, first delete the associated adhoc payable's tblothercomp
	$paymenttype = fetchRow0Col0("SELECT paymenttype FROM tblproviderpayment WHERE paymentid = $paymentid LIMIT 1");
	if($paymenttype == 'adhoc') {
		$payableid = fetchRow0Col0(
			"SELECT payableptr
			FROM relproviderpayablepayment 
			WHERE providerpaymentptr = $paymentid LIMIT 1");
		if(!$payableid) {
			logError("adhoc rollbackPayment could not find relproviderpayablepayment for $payableid");
			return;
		}
		$adhocptr = fetchRow0Col0("SELECT itemptr FROM tblpayable WHERE payableid = $payableid AND itemtable = 'tblothercomp' LIMIT 1");
		if(!$adhocptr) {
		 logError("adhoc rollbackPayment could not find payableid $payableid");
			return;
		}
		deleteTable('tblothercomp', "compid = $adhocptr", 1);
	}

	foreach($payableReferences as $ref) {
		if($ref['negative']) {
			deleteTable('tblnegativecomp', "negcompid={$ref['payableptr']}", 1);
		}
		else {
			deleteTable('tblpayable', "payableid={$ref['payableptr']}", 1);
		}
	}
	deleteTable('relproviderpayablepayment', "providerpaymentptr = $paymentid", 1);
	deleteTable('tblproviderpayment', "paymentid = $paymentid", 1);
	return true;
}
		
