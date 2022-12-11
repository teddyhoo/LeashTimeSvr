<? // prepayment-fns.php
require_once "credit-fns.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "tax-fns.php";
require_once "email-template-fns.php";

$prepaidInvoiceTag = 'prepayment';
$standardInvoiceMessage = "Hi #RECIPIENT#,<p>Here is your latest invoice.<p>Sincerely,<p>#BIZNAME#";
$standardMessageSubject = "Your Invoice";
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email'");
if($template) {
	$standardInvoiceMessage = $template['body'];
	$standardMessageSubject = $template['subject'];
}

function getPrepaidInvoiceTag() {return 'prepayment';} // in case of successive load variable erasure
/*
findPrepayments for each client specified find
prepayment due (actually payment due) including
	- prepaid visits in the range EXCEPT FOR those with billables
*/
function findPrepayments($firstDay, $lookahead, $client=null, $includeall=false) {
	$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
	$firstDayDB = date('Y-m-d', $firstDayInt);
	$lookaheadLastDayInt = strtotime("+ $lookahead days", $firstDayInt);
	$lookaheadLastDay = date('Y-m-d', $lookaheadLastDayInt);
	$clientFilter = $client == null ? '' : "AND clientptr = $client";
	$NRDateFilter = $includeall ? '' : "AND startdate >= '$firstDayDB' AND startdate <= '$lookaheadLastDay'";
	// Find all current prepaid NR packages for clients  [ sum(packageprice) as prepayment,  ]
	$sql = "SELECT clientptr, packageid, CONCAT_WS(' ', fname, lname) as clientname, invoiceby, lname, fname, email
					FROM tblservicepackage
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE current=1 AND prepaid=1 $clientFilter $NRDateFilter
					ORDER BY lname, fname";
	foreach(fetchAssociations($sql) as $clientpackage)
		$clientpackages[$clientpackage['clientptr']][] = $clientpackage;
	$prepayments = array();
	$standardTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : '';
	
	$packagesToExcludeInIncompleteVisits = array();
	$excludeBillables = "billableid IS NULL AND";
	if($clientpackages) foreach($clientpackages as $clientptr => $packs) {
		$taxRates = getClientTaxRates($clientptr);
		$allTaxRates[$clientptr] = $taxRates;
		$prepayments[$clientptr] = $packs[0];
		$history = array();		
		foreach($packs as $i => $pack) {
			$history[] = join(',', findPackageIdHistory($pack['packageid'],  $clientptr/*$client*/, false));
			if($includeall) $packagesToExcludeInIncompleteVisits[$clientptr][] = $history[$i];
		}
		$history = join(',', $history);
				
		$NRDateFilter = $includeall ? '' : "AND date >= '$firstDayDB' AND date <= '$lookaheadLastDay'";
		
		
		$appts = fetchAssociationsKeyedBy($sql = 
			"SELECT appointmentid, servicecode, 
					tblappointment.charge + ifnull(tblappointment.adjustment,0) as charge, 
					ifnull(tblbillable.paid, 0) as paid
				FROM tblappointment
				LEFT JOIN tblbillable ON appointmentid = itemptr AND itemtable = 'tblappointment' 
				WHERE $excludeBillables packageptr IN ($history) AND CANCELED IS NULL $NRDateFilter", 'appointmentid');
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' && $clientptr == 749) { echo print_r(array_keys($appts),1); }					
		$surcharges = fetchAssociations(
			"SELECT surchargeid, tblsurcharge.charge, ifnull(tblbillable.paid, 0) as paid, appointmentptr
				FROM tblsurcharge
				LEFT JOIN tblbillable ON surchargeid = itemptr AND itemtable = 'tblsurcharge' 
				WHERE $excludeBillables packageptr IN ($history) AND CANCELED IS NULL $NRDateFilter");

		$NRDateFilter = $includeall && $appts 
										? "appointmentid IN (".join(',', array_keys($appts)).")" 
										: ($includeall ? '1=1' : "packageptr IN ($history)");
		$discounts = fetchKeyValuePairs(
			"SELECT appointmentptr, amount
				FROM tblappointment
				LEFT JOIN relapptdiscount ON appointmentptr = appointmentid  
				WHERE $NRDateFilter AND CANCELED IS NULL");
		
		$sum = 0;
		foreach($appts as $apptid => $appt) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { if($clientptr == 35) echo "<br>".print_r($appt, 1);}
			$charge = $appt['charge'] - $discounts[$apptid];
			$tax = round($taxRates[$appt['servicecode']] * $charge) / 100;
			$sum += $charge + $tax - $appt['paid'];
		}

		if($surcharges) foreach($surcharges as $surcharge) {
			$charge = $surcharge['charge'];
			$taxRate = $surcharge['appointmentptr']
					? $taxRates[$appts[$surcharge['appointmentptr']]['servicecode']]
					: $standardTaxRate;
			$tax = round($taxRate * $charge) / 100;
			$sum += $charge + $tax - $surcharge['paid'];
			$surchargeIds[] = $surcharge['surchargeid'];
		}
//if($clientptr == 251) print_r($sum);				

		$prepayments[$clientptr]['prepayment'] = $sum;
	}

	
	/*
	
	//Find sum all NR package prices that are prepaid and that begin in the next $lookahead days, grouped by client ordered by client.
	$sql = "SELECT clientptr, CONCAT_WS(' ', fname, lname) as clientname, sum(packageprice) as prepayment, invoiceby, lname, fname, email
					FROM tblservicepackage
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE current AND prepaid $clientFilter AND startdate >= '$firstDayDB' AND startdate <= '$lookaheadLastDay'
					GROUP BY clientptr
					ORDER BY lname, fname";
	$prepayments = fetchAssociationsKeyedBy($sql, 'clientptr');
	*/
	//========================================
	//Find all current nonmonthly recurring services uncancelled before $firstDayDB
	$sql = "SELECT packageid, clientptr, startdate, cancellationdate
					FROM tblrecurringpackage
					WHERE prepaid = 1 AND monthly != 1 AND current = 1 and (cancellationdate IS NULL OR cancellationdate >= '$firstDayDB')
						 AND startdate <= '$lookaheadLastDay'";
	$packages = fetchAssociations($sql);

	$rClientids = array();
	foreach($packages as $pck) $rClientids[] = $pck['clientptr'];
	// find earliest recurring package start date for each client
	/*$startdates = !$rClientids ?  array() 
				: fetchKeyValuePairs(
				"SELECT clientptr, startdate FROM tblrecurringpackage 
					 WHERE clientptr IN (".join(',', $rClientids).")
					 ORDER BY startdate DESC");*/

	// find uncancelled recurring appointments for each client from max(startdate, specified firstDay) to extent of lookahead period
	// add in charges for these appointments to prepayments
	$day1 = $firstDayInt;//max(strtotime($startdates[$clientid]), $firstDayInt);
	$lastDay = strtotime("+ $lookahead days", $day1);
	$day1 = date('Y-m-d', $day1);
	$lastDay = date('Y-m-d', $lastDay);
	foreach($rClientids as $clientid) {
		$taxRates = isset($allTaxRates[$clientid]) ? $allTaxRates[$clientid] : getClientTaxRates($clientid);

		$fields = "tblappointment.charge+ifnull(adjustment, 0) as charge, packageptr,
								appointmentid, servicecode, ifnull(tblbillable.paid, 0) as paid";
		//if(!isset($prepayments[$clientid])) $fields .= ", tblappointment.clientptr, invoiceby, CONCAT_WS(' ', fname, lname) as clientname, lname, fname, email";

		$appts = fetchAssociationsKeyedBy($sql = "SELECT $fields
					FROM tblappointment
					LEFT JOIN tblbillable ON appointmentid = itemptr AND itemtable = 'tblappointment' 
					WHERE $excludeBillables recurringpackage = 1 AND canceled IS NULL AND tblappointment.clientptr = $clientid AND date >= '$day1' AND date <= '$lastDay'",
					'appointmentid'); //					LEFT JOIN tblclient ON clientid = clientptr
//if(mattOnlyTEST() && $clientid == 749) {echo "clientid: $clientid<br>#appts: ".count($appts)."<br>";foreach($appts as $appt) {$totch+= $appt['charge'];$totpd+= $appt['paid'];}echo "charge: $totch<br>paid: $totpd<br>diff: ".($totch-$totpd);}				
//if(mattOnlyTEST() && $clientid == 749) {echo "$sql<p>clientid: $clientid<br>#appts: ".count($appts)."<br>";foreach($appts as $appt) {$totch+= $appt['charge'];$totpd+= $appt['paid'];}echo "charge: $totch<br>paid: $totpd<br>diff: ".($totch-$totpd);}				
				

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { if($clientid == 240) screenLog("NR: ".print_r($appts, 1));}

		$packageptrs= array();				
		$apptIds= array();				
		foreach($appts as $appt) {
			$packageptrs[] = $appt['packageptr'];
			$apptIds[] = $appt['appointmentid'];
		}
		$packageptrs = array_unique($packageptrs);
		
		$history = array();		
		foreach($packageptrs as $packageptr) $history[] = join(',', findPackageIdHistory($packageptr, $clientid, true));
		$history = join(',', $history);

		if($history) $surcharges = fetchAssociations(
			"SELECT tblsurcharge.charge, ifnull(tblbillable.paid, 0) as paid, appointmentptr
				FROM tblsurcharge
				LEFT JOIN tblbillable ON surchargeid = itemptr AND itemtable = 'tblsurcharge' 
				WHERE $excludeBillables packageptr IN ($history) AND CANCELED IS NULL AND date >= '$day1' AND date <= '$lastDay'");

		if($apptIds) $discounts = fetchKeyValuePairs(
			"SELECT appointmentptr, amount
				FROM relapptdiscount  
				WHERE appointmentptr IN (".join(',', $apptIds).")");
		
		$sum = 0;
		
		
		if(!isset($prepayments[$clientid]) && ($appts || $surcharges)) {
			$prepayments[$clientid] = getOneClientsDetails($clientid, array('lname', 'fname', 'email', 'invoiceby'));
			$prepayments[$clientid]['clientptr'] = $clientid;
		}
			
		foreach($appts as $apptid => $appt) {
			$charge = $appt['charge'] - $discounts[$apptid];
			$tax = round($taxRates[$appt['servicecode']] * $charge) / 100;
			$sum += $charge + $tax - $appt['paid'];
		}
				
		if($surcharges) foreach($surcharges as $surcharge) {
			if(in_array($surcharge['surchargeid'], (array)$surchargeIds)) continue;
			$charge = $surcharge['charge'];
			$taxRate = $surcharge['appointmentptr']
					? $taxRates[$appts[$surcharge['appointmentptr']]['servicecode']]
					: $standardTaxRate;
			$tax = round($taxRate * $charge) / 100;

			$sum += $charge + $tax - $surcharge['paid'];
		}
		if($sum) $prepayments[$clientid]['prepayment'] += $sum;
//if(mattOnlyTEST() && $clientid == 749){echo "So far: [{$prepayments[$clientid]['prepayment']}]<p>HIST: ".print_r($sum,1).'<p>';exit;}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { if($clientid == 240) screenLog("REC + surcharges: ".print_r($prepayments[$clientid], 1));}
		
		
	}
	/*foreach($prepayments as $clientid => $pp)
		$prepayments[$clientid]['prepayment'] += calculateTotalPrepaidInvoiceTax($clientid, $firstDay, $lookahead);
		*/
	global $availableCredits;
if(TRUE || $prepayments) {
	$dateToCheck = FALSE && staffOnlyTEST() ? $firstDayDB : $lastDay;
	$unpaidBillableAmounts = fetchKeyValuePairs($upsql = 
			"SELECT clientptr, sum(charge - paid) FROM tblbillable 
				WHERE superseded = 0
					AND itemdate <= '$dateToCheck'
					$clientFilter 
					AND paid < charge
					GROUP BY clientptr
					ORDER BY itemdate");
	foreach($unpaidBillableAmounts as $clientid => $unused) {
		if(!$prepayments[$clientid]) {
			$prepayments[$clientid] = getOneClientsDetails($clientid, array('lname', 'fname', 'email', 'invoiceby'));
			$prepayments[$clientid]['clientptr'] = $clientid;
		}
	}
				
	//$allClientIds = array_merge(array_keys($unpaidBillableAmounts), array_keys((array)$prepayments));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog("Lastday: $lastDay");screenLog($upsql);screenLog("prepaymenrs 753: ".print_r($prepayments[753],1), 1);screenLog("billables 753: ".$unpaidBillableAmounts[753], 1);}
	foreach($prepayments as $clientid => $pp) {
		$totalIncomplete = 0;
		$allIncomplete = getIncompleteAppointmentsFor($clientid, $firstDayDB, null, null, null, $packagesToExcludeInIncompleteVisits[$clientid]);
		foreach((array)$allIncomplete as $line) $totalIncomplete += $line['charge'];
		$credit = getUnusedClientCreditTotal($clientid);
		$prepayments[$clientid]['prepayment'] += 
			$unpaidBillableAmounts[$clientid]
			//+ min($totalIncomplete, $credit);
			+ max(0, ($totalIncomplete - $credit)) ;
//if(mattOnlyTEST() && $clientid == 749){echo "So far: [{$prepayments[$clientid]['prepayment']}]".'<p>';exit;}
		$availableCredits[$clientid] = max(0, ($credit - $totalIncomplete));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' && $clientid==882) screenLog("[[$totalIncomplete - $credit]]"); 
//screenLog("[[".print_r($prepayments[882], 1)."]]");	
	}
	
	// find all clients with any uncanceled visits in the period
	/*$alsoRan = fetchKeyValuePairs(
		"SELECT clientptr, appointmentid 
		 FROM tblappointment 
		 WHERE canceled IS NULL AND '$day1' AND date <= '$lastDay'");
	foreach($alsoRan as $clientid => $unused)
		if(!isset($prepayments[$clientid]))
			$prepayments[$clientid]['prepayment'] = 0;
if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog(print_r($prepayments,1));}		 */
	
	
}	else foreach($prepayments as $clientid => $pp)	$availableCredits[$clientid] = getUnusedClientCreditTotal($clientid);
	return $prepayments;
}


// ###########################################################


function getPrepaidInvoice($clientid, $firstDay, $lookahead, $includePriorUnpaidBillables=false, $scope=null) {
	// UNUSED: scope: null = all, 'recurring', or a packageid

	global $credits, $tax, $providers, $allItemsSoFar, $creditApplied; // , $origbalancedue
	$allItemsSoFar = array();  // if(!$allItemsSoFar) 
	$providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider");
	$tax = 0;
	$creditApplied = 0;
	$invoice = array(
			'date'=>shortDate(), 
			'lineitems' => getPrepaidInvoiceLineItems($clientid, $firstDay, $lookahead, $scope), 
			'firstDay'=>$firstDay, 
			'lookahead'=>$lookahead,
			'clientptr'=>$clientid
			);
	//$credits = min(getUnusedClientCreditTotal($clientid), $origbalancedue);	
	$credits = getUnusedClientCreditTotal($clientid);
	
	$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
	$lookaheadLastDayInt = strtotime("+ $lookahead days", $firstDayInt);
	$lookaheadLastDay = date('Y-m-d', $lookaheadLastDayInt);

	
	
	if($includePriorUnpaidBillables) {
		$invoice['priorunpaiditems'] = getPriorUnpaidLineItems($clientid, $firstDay, $lookaheadLastDay);
//if(staffOnlyTEST()) print_r($invoice['priorunpaiditems']);	
		
		foreach((array)$invoice['priorunpaiditems'] as $billable) $tax += $billable['tax'];
	}
if(FALSE && $_SESSION['staffuser']) {	
	echo "<p>APPOINTMENTS: (".count((array)$allItemsSoFar['tblappointment']).")<p>";
	foreach((array)$allItemsSoFar['tblappointment'] as $i => $billable) {
		if(!$billable['billableid']) 
			$billable = fetchFirstAssoc("SELECT * FROM tblbillable 
																		WHERE itemptr = {$billable['appointmentid']} AND superseded = 0 AND itemtable = 'tblappointment'");
		echo "{$billable['itemdate']} #{$billable['itemptr']} billable charge: \${$billable['charge']} paid: \${$billable['paid']}<br>";
		$paidTotal += $billable['paid'];
		if($billable['itemptr']) $itemptrs[] = $billable['itemptr'];
	}
	echo "<p>SURCHARGES: (".count((array)$allItemsSoFar['tblsurcharge']).")<p>";
	foreach((array)$allItemsSoFar['tblsurcharge'] as $i => $billable) {
		if(!$billable['billableid']) 
			$billable = fetchFirstAssoc("SELECT * FROM tblbillable 
																		WHERE itemptr = {$billable['surchargeid']} AND superseded = 0 AND itemtable = 'tblsurcharge'");
		echo "{$billable['itemdate']} #{$billable['itemptr']} billable charge: \${$billable['charge']} paid: \${$billable['paid']}<br>";
		$paidTotal += $billable['paid'];
	}
	echo "Total paid: \$$paidTotal<p>";
	
	if($itemptrs) print_r(fetchKeyValuePairs("SELECT itemptr, count(*) FROM tblbillable 
															WHERE superseded = 0 AND itemtable = 'tblappointment' AND itemptr IN (".join(',', $itemptrs).")
															GROUP BY itemptr"));
														
}
	
	$tax = round($tax * 100) / 100.0;
	return $invoice;
}

function getPriorUnpaidLineItems($clientid, $firstDay, $lastDay) {
	global $credits, $allItemsSoFar;
	$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
	$firstDayDB = date('Y-m-d', $firstDayInt);
	$unpaidBillables = fetchAssociations(
			"SELECT * FROM tblbillable 
				WHERE superseded = 0
					AND clientptr = $clientid 
					AND paid < charge
					AND itemdate <= '$lastDay'
					ORDER BY itemdate"); //AND itemdate < '$firstDayDB'



	foreach($unpaidBillables as $i => $billable) {
		if($billable['itemtable'] == 'tblappointment') {
			if(isset($allItemsSoFar['tblappointment'][$billable['itemptr']])) {
				unset($unpaidBillables[$i]);
				continue;
			}
			$allItemsSoFar['tblappointment'][$billable['itemptr']] = $billable;
			$unpaidVisits[] = $billable['itemptr'];
		}
		else if($billable['itemtable'] == 'tblsurcharge') {
			if(isset($allItemsSoFar['tblsurcharge'][$billable['itemptr']])) {
				unset($unpaidBillables[$i]);
				continue;
			}
			$allItemsSoFar['tblsurcharge'][$billable['itemptr']] = $billable;
			$unpaidSurcharges[] = $billable['itemptr'];
		}
		else if($billable['itemtable'] == 'tblothercharge') $unpaidCharges[] = $billable['itemptr'];
		else if($billable['itemtable'] == 'tblrecurringpackage') $unpaidMonthlies[] = $billable['itemptr'];
	}
	if($unpaidVisits) $unpaidVisits = 
		fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE appointmentid IN (".join(',', $unpaidVisits).")", 'appointmentid');
	if($unpaidSurcharges) $unpaidSurcharges = 
		fetchAssociationsKeyedBy("SELECT * FROM tblsurcharge WHERE surchargeid IN (".join(',', $unpaidSurcharges).")", 'surchargeid');
	if($unpaidCharges) $unpaidCharges = 
		fetchAssociationsKeyedBy("SELECT * FROM tblothercharge WHERE chargeid IN (".join(',', $unpaidCharges).")", 'chargeid');
	if($unpaidMonthlies) $unpaidMonthlies = 
		fetchAssociationsKeyedBy("SELECT * FROM tblrecurringpackage WHERE packageid IN (".join(',', $unpaidMonthlies).")", 'packageid');
	$serviceTypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$surchargeTypes = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	$providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname)  FROM tblprovider");
	foreach($unpaidBillables as $billable) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo print_r(	$billable, 1).'<p>';	
		$line = array(
			'paid' => ($billable['paid'] ? $billable['paid'] : 0), 
			'tax'=>$billable['tax'], 
			'countablecharge'=>$billable['charge'],  // will be added in to total
			'charge'=>$billable['charge']-$billable['tax'],
			'date' =>shortDate(strtotime($billable['itemdate'])));
		$item = $billable['itemtable'] == 'tblappointment' ? $unpaidVisits[$billable['itemptr']] : (
						$billable['itemtable'] == 'tblsurcharge' ? $unpaidSurcharges[$billable['itemptr']] : (
						$billable['itemtable'] == 'tblothercharge' ? $unpaidCharges[$billable['itemptr']] 
						: $unpaidMonthlies[$billable['itemptr']]));

		$missingItemTypes = explodePairsLine('tblappointment|Visit||tblsurcharge|Surcharge||tblothercharge|Misc Charge||tblrecurringpackage|Monthly Package');
						
		$line['service'] = $item['appointmentid'] ? $serviceTypes[$item['servicecode']] : (
												$item['surchargeid'] ? 'surcharge: '.$surchargeTypes[$item['surchargecode']] : (
												$item['chargeid'] ? $item['reason'] : (
												$item['packageid'] ? date('F Y', strtotime($billable['monthyear']))
												:
												"Unknown {$missingItemTypes[$billable['itemtable']]} "
												."[{$billable['billableid']}/{$billable['itemptr']}] "
												.date('m/j/Y', strtotime($billable['itemdate'])))));

		$line['stripe'] = $stripe;

		$line['provider'] = $providers[$item['providerptr']];
		$line['sortdate'] = $billable['itemdate'].' '.$item['starttime'];
		$line['timeofday'] = $item['timeofday'];
		$localItems[] = $line;
	}
	
	$allIncomplete = getIncompleteAppointmentsFor($clientid, $firstDayDB, $serviceTypes, $surchargeTypes, $providers);
	if($allIncomplete) {
		uasort($allIncomplete, 'dateSort');
		$freeCredits = $credits;
		foreach($allIncomplete as $i => $item) {
			if($freeCredits >= $item['charge']) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "FREE CRED: $freeCredits<br>";}			
				$item['service'] = '[C] '.$item['service'];
			}
			else $item['countablecharge'] = $item['charge'];  // count charge in total
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "FREE CRED: $freeCredits [".print_r($item, 1)."]<br>";}			
			$localItems[] = $item;
			$freeCredits -= $item['charge'];
		}
	} 
	if($localItems) uasort($localItems, 'dateSort');
	
	return $localItems;
}

function getIncompleteAppointmentsFor($clientid, $firstDayDB, $serviceTypes=null, $surchargeTypes=null, $providers=null, $packagesToExclude=null) {
	$monthlyPackages = fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE clientptr = $clientid AND monthly = 1");
	$monthlyPackages = $monthlyPackages ? "AND packageptr NOT IN (".join(',', $monthlyPackages).")" : "";
	if($packagesToExclude) $packagesToExclude = "AND packageptr NOT IN (".join(',', $packagesToExclude).")";
	$incompleteVisits = fetchAssociations(
			"SELECT * FROM tblappointment
				WHERE canceled IS NULL
					AND completed IS NULL
					AND clientptr = $clientid 
					AND date < '$firstDayDB'
					$monthlyPackages
					$packagesToExclude
					ORDER BY date");
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' && $clientid == 750 )	screenLog("");
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' && $clientid == 750 )	screenLog(print_r($incompleteVisits, 1));
					
	//$incompleteTag = "<span title='Not yet marked complete.' style='font-weight:bold'>[i]</span> ";
	$incids = array();
	foreach($incompleteVisits as $visit)
		$incids[] = $visit['appointmentid'];
	if($incids) $discounts = fetchKeyValuePairs(
			"SELECT appointmentptr, amount 
				FROM relapptdiscount 
				WHERE appointmentptr IN (".join(',', $incids).")");
	foreach($incompleteVisits as $item) {
		$line = array(
			'paid' => 0, 
			'charge'=>$item['charge']+$item['adjustment']-$discounts[$item['appointmentid']],
			'date' =>shortDate(strtotime($item['date'])),
			'clientptr' =>$item['clientptr'],
			'service'=>$incompleteTag.$serviceTypes[$item['servicecode']],
			'provider'=> $providers[$item['providerptr']],
			'sortdate'=> $item['date'].' '.$item['starttime'],
			'timeofday'=> $item['timeofday'],
			'incomplete'=>1,
			'packageptr'=>$item['packageptr']
			);
		$line['tax'] = figureTaxForAppointment($line);
		$line['charge'] = $line['charge']+$line['tax'];
		$allIncomplete[] = $line;
		//$localItems[] = $line;
	}

	$incompleteSurcharges = fetchAssociations(
			"SELECT * FROM tblsurcharge
				WHERE canceled IS NULL
					AND completed IS NULL
					AND clientptr = $clientid 
					AND date < '$firstDayDB'
					ORDER BY date");
	foreach($incompleteSurcharges as $item) {
		$line = array(
			'paid' => 0, 
			'charge'=>$item['charge'],
			'date' =>shortDate(strtotime($item['date'])),
			'clientptr' =>$item['clientptr'],
			'service'=>$incompleteTag.'surcharge: '.$surchargeTypes[$item['surchargecode']],
			'provider'=> $providers[$item['providerptr']],
			'sortdate'=> $item['date'].' '.$item['starttime'],
			'timeofday'=> $item['timeofday'],
			'incomplete'=>1
			);
		$allIncomplete[] = $line;
	}
	return $allIncomplete;
}

function dateSort($a, $b) {return strcmp($a['sortdate'], $b['sortdate']);}

function getPrepaidInvoiceContents($clientid, $firstDay, $lookahead, $showOnlyCountableItems=false, $includePriorUnpaidBillables=true) {
	ob_start();
	ob_implicit_flush(0);
	NEWdisplayPrepaymentInvoice($clientid, $firstDay, $lookahead, false, $includePriorUnpaidBillables, $showOnlyCountableItems);
	//else displayPrepaymentInvoice($clientid, $firstDay, $lookahead);
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}	


function calculateTotalPrepaidInvoiceTax($clientid, $firstDay, $lookahead) {  // used in prepayments.php for each client
	$globalTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : 0;
	$clientTaxRates = getClientTaxRates($clientid);
	$tax = 0;
	$lookahead = isset($lookahead) && $lookahead ? $lookahead :  30;
	$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
	$firstDayDB = date('Y-m-d', $firstDayInt);
	$lookaheadLastDayInt = strtotime("+ $lookahead days", $firstDayInt);
	$lookaheadLastDay = date('Y-m-d', $lookaheadLastDayInt);
	$sql = "SELECT 'tblservicepackage', onedaypackage, startdate, enddate, packageid, clientptr, packageid, 
						invoiceby
					FROM tblservicepackage
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE clientptr = $clientid AND current AND prepaid AND startdate >= '$firstDayDB' AND startdate <= '$lookaheadLastDay'
					ORDER BY startdate";
	require_once "service-fns.php";
	$currentPackages = fetchAssociations($sql);
	if($currentPackages) {
		//$package = $currentPackages[0];
		$histories = findPackageHistories($clientid, 'N');
		foreach($currentPackages as $package) {
			$history = $histories[$package['packageid']] ? $histories[$package['packageid']] : array($package['packageid']);
	//if($clientid == 620) print_r($history);		
	//echo '[['.print_r($histories,1).']]';exit;		
			if($history) {
				$appts = fetchAssociations(
						"SELECT tblappointment.*, relapptdiscount.amount as discount FROM tblappointment 
							LEFT JOIN relapptdiscount ON appointmentptr = appointmentid  
							WHERE canceled IS NULL AND packageptr IN (".join(',', $history).")");
			}
			else $appts = array();
			foreach($appts as $appt)
				$tax += round(($appt['charge'] + $appt['adjustment'] - $appt['discount']) * $clientTaxRates[$appt['servicecode']]) / 100;

			if($history) $surcharges = fetchAssociations("SELECT * FROM tblsurcharge WHERE canceled IS NULL AND packageptr IN (".join(',', $history).")");
			else $surcharges = array();
			foreach($surcharges as $surcharge) {
				$taxRate = $globalTaxRate;
				if($surcharge['appointmentptr']) {
					$appt = getAppointment($surcharge['appointmentptr'], false);
					$taxRate = getClientServiceTaxRate($surcharge['clientptr'], $appt['servicecode']);
				}
				$tax += round($surcharge['charge'] * $taxRate) / 100;	
			}
				
		}
	}
	//Find all current nonmonthly recurring services uncancelled before $firstDayDB
	$sql = "SELECT packageid, clientptr, startdate, cancellationdate
					FROM tblrecurringpackage
					WHERE clientptr = $clientid AND prepaid = 1 AND monthly != 1 AND current = 1 and (cancellationdate IS NULL OR cancellationdate >= '$firstDayDB')
						 AND startdate <= '$lookaheadLastDay'";
						 
	$packages = fetchAssociations($sql);
	if($packages) {
		// find earliest recurring package start date for each client
		/*$startdate =fetchRow0Col0(
					"SELECT startdate FROM tblrecurringpackage 
						 WHERE clientptr = $clientid
						 ORDER BY startdate ASC LIMIT 1");*/

		// find uncancelled recurring appointments for each client from max(startdate, specified firstDay) to extent of lookahead period
		// add in charges for thes appointments to prepayments
		$day1 = $firstDayInt; //max(strtotime($startdate), $firstDayInt);
		$lastDay = strtotime("+ $lookahead days", $day1);
		$day1 = date('Y-m-d', $day1);
		$lastDay = date('Y-m-d', $lastDay);
		$fields = "sum(charge+ifnull(adjustment, 0)) as prepayment";
		$sql = "SELECT *
					FROM tblappointment
					WHERE recurringpackage = 1 AND canceled IS NULL AND clientptr = $clientid AND date >= '$day1' AND date <= '$lastDay'";
//if(	$clientid == 208)  {echo "CURR PACK: ".print_r($sql, 1);exit;}
		$appts = fetchAssociations($sql);
		foreach($appts as $appt)
			$tax += round(($appt['charge'] + $appt['adjustment']) * $clientTaxRates[$appt['servicecode']]) / 100;
	}
	return $tax;
}

function getPrepaidInvoiceLineItems($clientid, $firstDay, $lookahead, $scope=null) {
	// UNUSED: scope: null = all, 'recurring', or a packageid

	global $origbalancedue, $lineitems, $includeallAppointmentsInPrepaymentInvoice;
	$firstDayInt = strtotime($firstDay ? $firstDay : date('Y-m-d'));
	$firstDayDB = date('Y-m-d', $firstDayInt);
	$lookaheadLastDayInt = strtotime("+ $lookahead days", $firstDayInt);
	$lookaheadLastDay = date('Y-m-d', $lookaheadLastDayInt);

	$lineitems = array();
	$origbalancedue = 0;
//Find sum all client's NR package prices that are prepaid and that begin in the next $lookahead days
	$includeall = $includeallAppointmentsInPrepaymentInvoice;
	
	if($scope == 'all') $scope = null;

	if($scope != 'recurring') {
		
		$NRDateFilter = FALSE && $includeall ? '' : "AND startdate >= '$firstDayDB' AND startdate <= '$lookaheadLastDay'";
		if($scope && is_numeric($scope) && (int)$scope == $scope) {
			$history = findPackageIdHistory($scope, $clientid, false/*$recurring*/);
			$scopeFilter = "AND packageid IN (".join(',', $history).")";
			$NRDateFilter = '';
		}
		else $prepaidFilter = "AND prepaid";
		
		// The following causes memory problems for clients with large numbers of packages
		$sql = "SELECT 'tblservicepackage', onedaypackage, startdate, enddate, packageid, clientptr, packageid, CONCAT_WS(' ', fname, lname) as clientname, 
							invoiceby, lname, fname, email
						FROM tblservicepackage
						LEFT JOIN tblclient ON clientid = clientptr
						WHERE clientptr = $clientid AND current=1 $prepaidFilter $NRDateFilter $scopeFilter
						ORDER BY startdate";
						
		/// ...so...
/*if(FALSE && mattOnlyTEST()) {
		$clientDetails =
			fetchFirstAssoc("SELECT CONCAT_WS(' ', fname, lname) as clientname, lname, fname, invoiceby, email
												FROM tblclient
												WHERE clientid = $clientid
												LIMIT 1");
		$clientDetails['clientname'] = mysqli_real_escape_string((string)$clientDetails['clientname']);
		$clientDetails['fname'] = mysqli_real_escape_string((string)$clientDetails['fname']);
		$clientDetails['lname'] = mysqli_real_escape_string((string)$clientDetails['lname']);
		$clientDetails['email'] = mysqli_real_escape_string((string)$clientDetails['email']);
		$clientDetails['invoiceby'] = $clientDetails['invoiceby'] ? $clientDetails['invoiceby'] : '0';
		$sql = "SELECT 'tblservicepackage', onedaypackage, startdate, enddate, packageid, clientptr, packageid, 
							'{$clientDetails['clientname']}' as clientname,
							'{$clientDetails['fname']}' as fname,
							'{$clientDetails['lname']}' as lname,
							'{$clientDetails['email']}' as email,
							'{$clientDetails['invoiceby']}' as invoiceby
						FROM tblservicepackage
						WHERE clientptr = $clientid AND current=1 $prepaidFilter $NRDateFilter $scopeFilter
						ORDER BY startdate";
						
}						*/
if(FALSE && mattOnlyTEST()) {
	echo "$sql<p>";					
	foreach(fetchAssociations($sql) as $p) echo print_r($p, 1).'<p>';	
}
		require_once "service-fns.php";
		$stripe = 'white';
		$stripe = packageRows($sql, 'N', $stripe, $clientid, $firstDayDB, $lookaheadLastDay);
	}

	if(!$scope || $scope == 'recurring') {
		
		//Find all current nonmonthly recurring services uncancelled before $firstDayDB
		$sql = "SELECT packageid, clientptr, startdate, cancellationdate
						FROM tblrecurringpackage
						WHERE clientptr = $clientid AND prepaid = 1 AND monthly != 1 AND current = 1 and (cancellationdate IS NULL OR cancellationdate >= '$firstDayDB')
							 AND startdate <= '$lookaheadLastDay'";

		$packages = fetchAssociations($sql);
		if($packages) {
			// find earliest recurring package start date for each client
			/*$startdate =fetchRow0Col0(
						"SELECT startdate FROM tblrecurringpackage 
							 WHERE clientptr = $clientid
							 ORDER BY startdate ASC LIMIT 1");*/

			// find uncancelled recurring appointments for each client from max(startdate, specified firstDay) to extent of lookahead period
			// add in charges for thes appointments to prepayments
			$day1 = $firstDayInt; //max(strtotime($startdate), $firstDayInt);
			$lastDay = strtotime("+ $lookahead days", $day1);
			$day1 = date('Y-m-d', $day1);
			$lastDay = date('Y-m-d', $lastDay);
			$fields = "sum(charge+ifnull(adjustment, 0)) as prepayment";
			$sql = "SELECT *
						FROM tblappointment
						LEFT JOIN tblclient ON clientid = clientptr
						WHERE recurringpackage = 1 AND canceled IS NULL AND clientptr = $clientid AND date >= '$day1' AND date <= '$lastDay'";
			$stripe = packageRows($sql, 'R', $stripe, $clientid, $firstDayDB, $lastDay);			
		}
	}
	
	// *sigh*
	$stripe = 'white';
	foreach($lineitems as $i => $li)
		$lineitems[$i]['stripe'] = ($stripe = $stripe == 'white' ? 'grey' : 'white');

	return $lineitems;
}

function packageRows($sql, $RorN, $stripe, $clientid, $firstDay=null, $lastDay=null) {  // billables keyed by billableid, packageBillables: packageid=>billableid
	global $providers, $lineitems, $origbalancedue, $tax, $creditApplied;
	global $allItemsSoFar;  // tblappointment=>array(id1, id2...), tblsurcharge=>array(id1, id2...)
	$localItems = array();
	$globalTaxRate = $_SESSION['preferences']['taxRate'] ? $_SESSION['preferences']['taxRate'] : 0;
	
	$clientTaxRates = getClientTaxRates($clientid);
	if($RorN == 'N') {
		$currentPackages = fetchAssociations($sql);
		if(!$currentPackages) return $stripe;
		$histories = findPackageHistories($clientid, $RorN);
	}
	else $currentPackages = array(1);
		
	$currentPackages = $RorN == 'N' ? fetchAssociations($sql) : array(1);
	
	if($currentPackages) $histories = findPackageHistories($clientid, $RorN);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {foreach($histories as $id => $p)echo "$id=>".print_r($p,1).'<br>';foreach($currentPackages as $p)$pids[] = $p['packageid'];print_r($pids);}			
	$appts = array();
	$surcharges = array();
	foreach($currentPackages as $n => $package) {
		// Sections: monthly packages will be broken down by month
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog(print_r($package, 1));}
		if($RorN == 'R') {
			$appts = fetchAssociationsKeyedBy($sql, 'appointmentid');
			$package = array('tblrecurringpackage'=>1);
			foreach($appts as $appt) $recpacks[] = $appt['packageptr'];
			$recpacks = array_unique((array)$recpacks);
			if($appts) {
				$discounts = fetchAssociationsKeyedBy("SELECT * FROM relapptdiscount WHERE appointmentptr IN ("
																								.join(',', array_keys($appts)).")", 'appointmentptr');
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($discounts); }																								
				foreach($discounts as $discount) 
					$appts[$discount['appointmentptr']]['discount'] = $discount['amount'];
				$surcharges = fetchAssociations($sql = 
					"SELECT *
					 FROM tblsurcharge
					 WHERE canceled IS NULL 
						AND packageptr IN (".join(',', $recpacks).")"
					.($firstDay ? " AND date >= '$firstDay'" : '')
					.($lastDay ? " AND date <= '$lastDay'" : '')); // include days to the end of the package
//if($_SESSION['staffuser']) echo "[R] $sql (".print_r($surcharges, 1).")<p>";
			}
		}
		else if($RorN == 'N'){
			$history = $histories[$package['packageid']] ? $histories[$package['packageid']] : array($package['packageid']);
			global $includeallAppointmentsInPrepaymentInvoice;
			if($history) {
				// for nonrecurring schedules, include only schedules with appointments in the date range
				$NRDateFilter = $includeallAppointmentsInPrepaymentInvoice ? "AND date >= '$firstDay'" : "AND date >= '$firstDay' AND date <= '$lastDay'";
				$appts = fetchAssociations(
				"SELECT tblappointment.*, relapptdiscount.amount as discount
				 FROM tblappointment
				 LEFT JOIN relapptdiscount ON appointmentptr = appointmentid  
				 WHERE canceled IS NULL 
				 	AND packageptr IN (".join(',', $history).")
				 	$NRDateFilter");  // include days to the end of the package
				$surcharges = fetchAssociations($sql = 
				"SELECT *
				 FROM tblsurcharge
				 WHERE canceled IS NULL 
				 	AND packageptr IN (".join(',', $history).")
				 	$NRDateFilter"); // include days to the end of the package
//if($_SESSION['staffuser']) echo "[N] $sql (".print_r($surcharges, 1).")<p>";
			}
			else $appts = array();
		}
		// ensure there are appts in a nonmonthly before proceeding
		if(!$appts && !$surcharges) continue;
		$lineitem = $package;

		if($RorN == 'R') { // a monthly billable
			$sectionLabel = 'XXXX';
			$lineitem['sectionLabel'] = $sectionLabel;
		}
		else {
			$sectionLabel = $package['packageid'];
			$lineitem['sectionLabel'] = $sectionLabel;
		}

		$lineitem['stripe'] = ($stripe = $stripe == 'white' ? 'grey' : 'white');
		if($RorN == 'N') {
			$lineitem['startdate'] = shortDate(strtotime($package['startdate']));
			$lineitem['enddate'] = $package['enddate']  && $package['enddate'] != '0000-00-00' ? shortDate(strtotime($package['enddate'])) : '';
		}
		else {
			$lineitem['startdate'] = '';
		}


		//if($RorN == 'R') $localItems[] = $lineitem;

		$allPets = array();
		if(count($appts)) {
			require_once "pet-fns.php";
			$allPets = getClientPetNames($clientid);
		}

		if(!$allItemsSoFar) $allItemsSoFar = array();
			//if($section && strpos($appt['date'], $sectionMonthYear) !== 0) continue;
		foreach($appts as $appt) {
			if(isset($allItemsSoFar['tblappointment'][$appt['appointmentid']])) continue;
			$allItemsSoFar['tblappointment'][$appt['appointmentid']] = $appt;
			$serviceCodes[$appt['appointmentid']] = $appt['servicecode'];
			$paid = fetchRow0Col0("SELECT sum(paid) FROM tblbillable WHERE superseded = 0 AND itemptr = {$appt['appointmentid']} AND itemtable = 'tblappointment'");
			$paid = $paid ? $paid : 0;
			$tax += round(($appt['charge'] + $appt['adjustment'] - $appt['discount']) * $clientTaxRates[$appt['servicecode']]) / 100;
			$appt['stripe'] = $stripe;
			$appt['service'] = getPrepaymentServiceName($appt['servicecode']); //$_SESSION['servicenames'][$appt['servicecode']];
			
			if($pets = $appt['pets']) {
				require_once "client-fns.php";
				$standardCharges = !$standardCharges ? getStandardCharges() : $standardCharges;
				$extraCharge = $standardCharges[$appt['servicecode']]['extrapetcharge'];
				if($extraCharge && $extraCharge > 0) {
					if($pets == 'All Pets') $pets = $allPets;
					$extraPets = max(0, count(explode(',', $pets))-1);
					if($extraPets) $appt['service'] .= " (incl. charge for $extraPets add'l pet".($extraPets == 1 ? '' : 's').")";
				}
			}
			
			
			$appt['provider'] = $providers[$appt['providerptr']];
			$origbalancedue += $appt['charge']+$appt['adjustment'] - $appt['discount'];// - $paid;
			$creditApplied += $paid;

			$appt['charge'] = $section ? '' : dollars($appt['charge']+$appt['adjustment'] - $appt['discount'] /*- $paid*/);
			//if($paid) $appt['paid'] = "(".dollars($paid)." paid) ";
			$appt['sortdate'] = $appt['date'].' '.$appt['starttime'];
			$appt['date'] = shortDate(strtotime($appt['date']));
			$appt['container'] = $sectionLabel;
			$localItems[] = $appt;
		}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "<b>[{$package['packageid']}]</b><br>";foreach($localItems as $appt) echo print_r($appt,1).'<br>';}			
		global $surchargeNames;
		if(!$surchargeNames) $surchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
		if($surcharges) foreach($surcharges as $surcharge) {
			if(isset($allItemsSoFar['tblsurcharge'][$surcharge['surchargeid']])) continue;
			$allItemsSoFar['tblsurcharge'][$surcharge['surchargeid']] = $surcharge;
			$paid = fetchRow0Col0("SELECT sum(paid) FROM tblbillable WHERE itemptr = {$surcharge['surchargeid']} AND itemtable = 'tblsurcharge'");
			$paid = $paid ? $paid : 0;
			
			if($surcharge['appointmentptr']) $taxRate = $clientTaxRates[$serviceCodes[$surcharge['appointmentptr']]];
			else $taxRate = $globalTaxRate;
			$tax += round($surcharge['charge'] * $taxRate) / 100;
			$surcharge['stripe'] = $stripe;
			$surcharge['service'] = 'Surcharge: '.$surchargeNames[$surcharge['surchargecode']];
			
			$surcharge['provider'] = $providers[$surcharge['providerptr']];
			$origbalancedue += $surcharge['charge'];// - $paid;
			$creditApplied += $paid;
			$surcharge['charge'] = dollars($surcharge['charge'] /*- $paid */);
			//if($paid) $surcharge['paid'] = "(".dollars($paid)." paid) ";
			$surcharge['sortdate'] = $surcharge['date'].' '.$surcharge['starttime'];
			$surcharge['date'] = shortDate(strtotime($surcharge['date']));
			$surcharge['container'] = $sectionLabel;
			$localItems[] = $surcharge;
		}
	}
	if($localItems) {
		$lineitems = array_merge($lineitems, $localItems);
		usort($lineitems, 'cmpLineItems');
		//foreach($localItems as $i => $item) $lineitems[$i] = $item;
	}
return $stripe;
}

function getPrepaymentServiceName($servicecode) {
	static $serviceNames;
	$serviceNames = $serviceNames ? $serviceNames : fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	return $serviceNames[$servicecode];
}



function cmpLineItems($a, $b) {
	return strcmp($a['sortdate'], $b['sortdate']);
}

function displayPrepaymentInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true) {
	if(is_array($invoiceOrClientId)) {
		$invoice = $invoiceOrClientId;
		$clientid = $invoice['clientid'];
	}
	else {
		$invoice = getPrepaidInvoice($invoiceOrClientId, $firstDay, $lookahead);			
		$clientid = $invoiceOrClientId;
	}
	// This may be called in a SESSION or outside of it (cronjob)
	if($firstInvoicePrinted) echo <<<STYLE
	
	<style>
	.right {text-align:right;  font-size: 1.05em;}
	.bigger-right {font-size:1.1em;text-align:right;}
	.bigger-left {font-size:1.1em;text-align:left;}
	.sortableListHeader {
		font-size: 1.05em;
		padding-bottom: 5px; 
		border-collapse: collapse;
	}

	.sortableListCell {
		font-size: 1.05em; 
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
	}
	</style>
STYLE;
//	<body 'style=font-size:12px;padding:10px;'>
	//$previousInvoices = getPriorUnpaidInvoices($invoice);
	$client = getClient($clientid);
	dumpReturnSlip($invoice, $client);
	echo "<p align=center><b>PREPAYMENT INVOICE</b><p>";
	dumpAccountSummary($invoice, $client); // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<p>";
	//dumpInvoiceCredits($client['clientid']);
	dumpPrepaidBillables($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	dumpRecentPayments($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	//dumpCurrentPastInvoiceSummaries($invoiceid); // Invoice #, Invoice Date, Items, Subtotal
	dumpMessage($invoice);  // should we add a message field to invoice?
	dumpFooter();
}

function NEWdisplayPrepaymentInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true, $includePriorUnpaidBillables=false, $showOnlyCountableItems=false, $scope=null) {
//prepayment-fns.php(206): displayPrepaymentInvoice($clientid, $firstDay, $lookahead);
//prepayment-fns.php(509): function displayPrepaymentInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true) {
//prepayment-invoice-print.php(41): displayPrepaymentInvoice($id, $firstDay, $lookahead, $first);
//prepayment-invoice-view.php(51): displayPrepaymentInvoice($id, $firstDay, $lookahead);

// UNUSED: scope: null = all, 'recurring', or a packageid

	if(is_array($invoiceOrClientId)) {
		$invoice = $invoiceOrClientId;
		$clientid = $invoice['clientptr'];
	}
	else {
		$invoice = getPrepaidInvoice($invoiceOrClientId, $firstDay, $lookahead, $includePriorUnpaidBillables, $scope);
		$clientid = $invoiceOrClientId;
	}
	// This may be called in a SESSION or outside of it (cronjob)
	if($firstInvoicePrinted) echo <<<STYLE
	
	<style>
	 body {background-image: none;}

	.right {text-align:right;  font-size: 1.05em;}
	.bigger-right {font-size:1.1em;text-align:right;}
	.bigger-left {font-size:1.1em;text-align:left;}
	.sortableListHeader {
		font-size: 1.05em;
		padding-bottom: 5px; 
		border-collapse: collapse;
	}

	.sortableListCell {
		font-size: 1.05em; 
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
	}
	</style>
STYLE;
//	<body 'style=font-size:12px;padding:10px;'>
	//$previousInvoices = getPriorUnpaidInvoices($invoice);
	$client = getClient($clientid);
	
	dumpReturnSlip($invoice, $client);
	$statementTitle = $_SESSION['preferences']['statementTitle'] ? $_SESSION['preferences']['statementTitle'] : 'STATEMENT';
	echo "<p align=center><b>$statementTitle</b><p>"; // PREPAYMENT INVOICE
	dumpAccountSummary($invoice, $client, $showOnlyCountableItems); // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<p>";
	//dumpInvoiceCredits($client['clientid']);
	dumpPriorUnpaidBillables($invoice, $clientid, $showOnlyCountableItems);
	dumpPrepaidBillables($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	dumpRecentPayments($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	//dumpCurrentPastInvoiceSummaries($invoiceid); // Invoice #, Invoice Date, Items, Subtotal
	dumpMessage($invoice);  // should we add a message field to invoice?
	dumpFooter();
}

function dumpReturnSlip($invoice, $client) {
	global $origbalancedue, $creditApplied, $tax, $credits;

	echo "<table width='95%' border=0 bordercolor=red>";
	echo "<tr><td style='padding-bottom:8px'>";
	$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
	dumpBusinessLogoDiv($amountDue, $invoice['invoiceid'], false, $client['clientid']);
	//dumpBusinessLogoDiv($amountDue, null, false, $client['clientid']);

	echo "</td><td align=right>";
	dumpInvoiceHeader($invoice, $client); // customer #, customer invoice #, invoice date, Amount Due
	echo "</td></tr>";
	echo "<tr><td>";
	dumpClientAddress($client); // mailing address or home address if no mailing address
	echo "</td><td align=right>";
	dumpInvoiceBarcode(invoiceIdDisplay($invoice['invoiceid']));
	echo "</td></tr></table>";
	if(!$_SESSION['preferences']['suppressDetachHereLine']) 
		echo "<p align=center>Please detach here and return with payment.<p><hr>";
}

function dumpInvoiceBarcode($invoiceDisplayId) {}	

function dumpAccountSummary($invoice, $client, $showOnlyCountableItems=false) {  // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar('Account Summary', "Customer Number: {$client['clientid']}");
	echo "</td>";
	echo "<tr><td style='text-align:left;vertical-align:top;'>";
	dumpClientAddress($client);
	echo "</td><td align=right>";
	dumpBalances($invoice, $client['clientid'], $showOnlyCountableItems);
	echo "</td></tr>";
	echo "</table>";
	
}

function dumpRecentPayments($invoice, $clientid) {
	//firstDay'=>$firstDay, 'lookahead'=>
/*if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "SELECT * FROM tblcredit
		 WHERE clientptr = $clientid 
		  AND issuedate >= '{$invoice['firstDay']}' 
		 	AND reason NOT LIKE '%New billable created%'
		 ORDER BY issuedate"; }*/

	echo "<div style='width:95%'>\n";
	dumpSectionBar("Recent Payments and Credits", '');
	$firstDay = date('Y-m-d', strtotime($invoice['firstDay']));
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') 	echo "firstDay: {$invoice['firstDay']}";exit;
//if(mattOnlyTEST() $excludingRepayments = "AND (tblcredit.reason IS NULL OR tblcredit.reason NOT LIKE '%(v: %')";

	$credits = fetchAssociations(
		"SELECT tblcredit.*, refundid, sum(tblgratuity.amount) as gratuity, tblrefund.amount as refundamount
			FROM tblcredit
			LEFT JOIN tblrefund ON tblrefund.paymentptr = creditid
			LEFT JOIN tblgratuity ON tblgratuity.paymentptr = creditid
		 WHERE tblcredit.clientptr = $clientid 
		  AND tblcredit.issuedate >= '$firstDay' 
		 	AND (tblcredit.reason IS NULL OR tblcredit.reason NOT LIKE '%New billable created%')
		 	AND hide = 0
		 GROUP BY creditid
		 ORDER BY tblcredit.issuedate");
 }
		 
		  //AND issuedate < FROM_DAYS(TO_DAYS('{$invoice['firstDay']}')+{$invoice['lookahead']})
	dumpInvoiceCreditTable($credits, $invoice['firstDay']);
	echo "</div>";
}

function dumpInvoiceCreditTable($credits, $firstDay) {
	if(!$credits) {
			echo "No payments or credits since ".shortDate().".<p>";
			return;
	}
	echo "<style>.leftheader {font-size: 1.05em; padding-bottom: 5px; border-collapse: collapse; text-align: left;}</style>";

	foreach($credits as $credit) {
		if($credit['hide']) continue;
		if($credit['voided']) {
			require_once "item-note-fns.php";
			$voidReason = getItemNote('tblcredit', $credit['creditid']);
			$voidReason = $voidReason ? truncatedLabel($voidReason['note'], 25) : '';
			$voidedDate = shortDate(strtotime($credit['voided']));
			$reason = "<font color=red>VOID ($voidedDate): \${$credit['voidedamount']} ".$voidReason.'</font>';
		}
		else {
			$ccPrefix = 'CC: ';
			if(strpos($credit['sourcereference'], $ccPrefix) === 0)
				$reason = "Payment via CC# **** **** **** ".substr($credit['sourcereference'], strlen($ccPrefix));
			else $reason = $credit['reason'];
		}
		$refundAmount = $credit['refundamount'] ? dollarAmount($credit['refundamount']) : '';
		$refund = $credit['refundid'] ? " ($refundAmount refunded)" : '';
		$totalAmount = $credit['amount']+$credit['gratuity'];
		$lineItems[] = array('date'=>shortDate(strtotime($credit['issuedate'])), 'type'=> ($credit['payment'] ? 'payment' : 'credit'),
										'reason' => $reason, 'amount'=>dollars($totalAmount).$refund);
		if($credit['gratuity'])
			$lineItems[] = array('date'=>shortDate(strtotime($credit['issuedate'])), 'type'=> ($credit['payment'] ? 'payment' : 'credit'),
										'reason' => $reason, 'amount'=>"(".dollars($credit['gratuity']).") gratuity"/*.$refund*/);

	}
	$columns = explodePairsLine('date|Date||type|Type||reason|Reason||amount|Amount');
	//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	tableFrom($columns, $lineItems, "WIDTH=90%", null, 'leftheader', null, 'sortableListCell', null, array('amount'=>'dollaramountcell'));
	echo '<p>';
}

function dumpPriorUnpaidBillables($invoice, $clientid, $showOnlyCountableItems=false) { // Invoice #, Invoice Date, Items, Subtotal
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar("Prior Unpaid Charges", "");
	echo "</td></tr><tr><td>";
	$lineItems = $invoice['priorunpaiditems'];
	if(!$lineItems) {
		echo "<center>No Prior Unpaid Charges Found.</center></td></tr></table>";
		return;
	}
	$finalLineItems = array();
	$columns = explodePairsLine('date|Date||timeofday|Time of Day||service|Service||provider|Sitter||charge|Charge');
	if($_SESSION['preferences']['suppressInvoiceTimeOfDay']) unset($columns['timeofday']);
	if($_SESSION['preferences']['suppressInvoiceSitterName']) unset($columns['provider']);
	$numCols = count($columns);
	foreach($lineItems as $index => $lineItem) {
		if($showOnlyCountableItems && !$lineItem['countablecharge']) continue;
		$subtotal += $lineItem['charge'];
		$lineItem['charge'] = dollarAmount($lineItem['charge']);
		$finalLineItems[] = $lineItem;
		$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
	}
	$subtotalDollars = dollars($subtotal);
	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotalDollars</td><tr>");
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	if(!$invoice['lineitems'] && $_SESSION['preferences']['includeInvoiceGratuityLine']) 
		$finalLineItems[] = gratuityLine($numCols);
	tableFrom($columns, $finalLineItems, 'WIDTH=100% ',null,null,null,null,null,$rowClasses, array('charge'=>'right'));
	echo "</td></tr></table>";
	//print_r($invoice['priorunpaiditems']);
}

function dumpPrepaidBillables($invoice, $clientid) { // Invoice #, Invoice Date, Items, Subtotal
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar("Current Charges", "");//Charges to be Prepaid
	echo "</td></tr><tr><td>";
	$lineItems = $invoice['lineitems'];
	if(!$lineItems) {
		echo "<center>No Current Charges Found.</center></td></tr></table>";
		return;
	}

	$finalLineItems = array();
	$columns = explodePairsLine('date|Date||timeofday|Time of Day||service|Service||provider|Sitter||charge|Charge');
	if($_SESSION['preferences']['suppressInvoiceTimeOfDay']) unset($columns['timeofday']);
	if($_SESSION['preferences']['suppressInvoiceSitterName']) unset($columns['provider']);

	$appointmentsStarted = $lineItems && isset($lineItems[0]['servicecode']);
	if(!$appointmentsStarted && !$_SESSION['preferences']['suppressInvoiceTimeOfDay']) {
		$columns['timeofday'] = '&nbsp;';
		if(!$_SESSION['preferences']['suppressInvoiceSitterName']) $columns['provider'] = '&nbsp;';
	}
	//Date	Time of Day	Service	Walker	Charge
	$numCols = count($columns);
	
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	$rowClasses = array();
//if(staffOnlyTEST()) echo "$subtotal<br>";	
	foreach($lineItems as $index => $lineItem) {
		if(strpos($lineItem['charge'], ';') !== FALSE)
			$realCharge = substr($lineItem['charge'], strpos($lineItem['charge'], ';')+1);
		else $realCharge = substr($lineItem['charge'], 2);
		
		$subtotal += 0+$realCharge;
		
		
		
//if(staffOnlyTEST()) echo print_r($lineItem, 1)."<br>{$lineItem['charge']}<br>[".substr($lineItem['charge'], 2)."]<br>$subtotal<br>";
		if($lineItem['servicecode'] || $lineItem['surchargecode']) {
			if(!$appointmentsStarted && $lineItem['recurring']) {
				$appointmentsStarted = true;
				$line = "<tr><th class='sortableListHeader'>Date</th><th class='sortableListHeader'>Time of Day</th>".
									"<th class='sortableListHeader'>Service</th><th class='sortableListHeader'>Walker</th<th class='sortableListHeader'>Charge</th>";
				$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
				$rowClasses[] = null;
			}
			$lineItem['charge'] = $lineItem['paid'].$lineItem['charge'];
			$finalLineItems[] = $lineItem;
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';

		}
		else { // package

			$description = isset($lineItem['tblservicepackage']) 
											? ($lineItem['onedaypackage'] ? 'One day' : 'Short term') 
											: (isset($lineItem['tblrecurringpackage']) 
													? ($lineItem['monthly'] ? 'Monthly' : 'Weekly')
													: 'Miscellaneous Charges');
			
			$description .= ' prepaid';
			if($lineItem['cancellationdate']) $description .= ' Canceled: '.shortNaturalDate(strtotime($lineItem['cancellationdate']));
			$rowClasses[] = null;
			$rowClass = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
			$line = "<tr class='$rowClass'>";
			$date = isset($lineItem['monthyear']) 
				? $lineItem['monthyear'] 
				: ($lineItem['enddate'] ? $lineItem['startdate']."-".$lineItem['enddate'] : $lineItem['startdate']);
			
			$line .= "<td class='sortableListCell' colspan=2>$date</td>".
								"<td class='sortableListCell' style='font-weight:bold' colspan=2>$description</td><td class='right'>{$lineItem['charge']}</td></tr>";
//print_r($line);exit;
			$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
		}
	}
//if(staffOnlyTEST()) echo print_r($subtotal,1)."<br>";
	
	$subtotalDollars = dollars($subtotal);
	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotalDollars</td><tr>");
	
	if($_SESSION['preferences']['includeInvoiceGratuityLine']) {																		
		$finalLineItems[] = gratuityLine($numCols);
		/*$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td colspan=".($numCols-1)
																					." style='text-align:left;vertical-align:top;'>
																					<b>If you would like to add a gratuity, please write in an amount here.<br>
																					Thanks for your continued business.</b></td>
																					<td class='right' style='border-bottom:solid #000000 1px;'>"
																					."\$________</td><tr>");*/
	}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	tableFrom($columns, $finalLineItems, 'WIDTH=100% ',null,null,null,null,null,$rowClasses, array('charge'=>'right'));
	echo "</td></tr></table>";

}

function gratuityLine($numCols) {
		$gratuityLine = "<tr><td colspan=".($numCols-1)
																					." style='text-align:left;vertical-align:top;'>
																					<b>If you would like to add a gratuity, please let us know how much you want to add.<br>
																					Thanks for your continued business.</b></td></td><tr>";
/*"<tr><td colspan=".($numCols-1)
																					." style='text-align:left;vertical-align:top;'>
																					<b>If you would like to add a gratuity, please write in an amount here.<br>
																					Thanks for your continued business.</b></td>
																					<td class='right' style='border-bottom:solid #000000 1px;'>"
																					."\$________</td><tr>"*/																					
	
	return array('#CUSTOM_ROW#'=> $gratuityLine);
}

function priorUnpaidItemTotal($invoice, $showOnlyCountableItems=false) {
	if($invoice['priorunpaiditems']) 
		foreach($invoice['priorunpaiditems'] as $item) {
			if($showOnlyCountableItems) $total += $item['countablecharge']-$item['paid'];
			else $total += $item['charge']-$item['paid'];
	}
	return $total;
}

function dumpBalances($invoice, $clientid, $showOnlyCountableItems=false) {
	global $origbalancedue, $credits, $tax, $creditApplied;
//if($_SESSION['staffuser']) echo "credits $credits	+ creditApplied $creditApplied<p>";
	echo "<table width=60%>";
	labelRow('Current Charges', '', dollars($origbalancedue), $labelClass=null, 'right', '', '', 'raw');
	$taxLabel = $_SESSION['preferences']['taxLabel'] ? $_SESSION['preferences']['taxLabel'] : 'Tax';
	labelRow($taxLabel, '', dollars($tax), $labelClass=null, 'right', '', '', 'raw');
	//$unusedCredits = fetchRow0Col0("SELECT sum(amount - ifnull(paid,0)) FROM tblcredit WHERE clientptr = $clientid");
	labelRow('Prior Unpaid Charges', '', dollars(priorUnpaidItemTotal($invoice, $showOnlyCountableItems)), $labelClass=null, 'right', '', '', 'raw');
//if($_SESSION['staffuser']) screenLog("creditApplied: $creditApplied + credits: $credits");
	$creditValue = $creditApplied+$credits;
//if($_SESSION['staffuser']) echo "XXX 	creditApplied: $creditApplied + credits: $credits<p>";
	if($showOnlyCountableItems) $creditValue -= priorUnpaidItemTotal($invoice) - priorUnpaidItemTotal($invoice, true);
	labelRow('Payments & Credits', '', dollars($creditValue), $labelClass=null, 'right', '', '', 'raw');
	//labelRow('Amount Due', '', dollars(max(0, $origbalancedue - $credits + $tax)), $labelClass=null, 'bigger-right', '', 'border: solid black 1px;');
//print_r($tax);	
	$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
	$amountDue = $amountDue < 0 ?  dollars(abs($amountDue)).'cr' : dollars($amountDue);
	labelRow('Amount Due', '', $amountDue, $labelClass=null, 'bigger-right', '', 'border: solid black 1px;', 'raw');
	$dateDue = $_SESSION['preferences']['pastDueDays'];
	if(!$dateDue) $dateDue = "0";
	global $db;
	
	$dueDateChoice = $_SESSION['preferences']['statementsDueOnPastDueDays'];
	if($dueDateChoice != 'Suppress') {
		$dateDue = (!$dueDateChoice || $dueDateChoice == 'Upon Receipt')
			? 'Upon Receipt'
			: shortDate(strtotime("+ $dateDue days")) ;
		//if(!$dateDue) $dateDue = "0";
		//$dateDue =   $dateDue != "0" ? shortDate(strtotime("+ $dateDue days")) : 'Upon Receipt';

		labelRow('Date Due', '', $dateDue, $labelClass=null, 'bigger-right', '', 'border: solid black 1px;');
	}
	
	echo "</table>";
}
//labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)

function dumpSectionBar($leftLabel, $rightLabel, $plusStyle='') {
	$background = 'lightblue';
	echo "<div style='width:100%;border:solid black 1px;font-weight:bold;background:$background;height:20px;$plusStyle'>";
	echo "<span style='float:left;'>$leftLabel</span><span style='float:right;'>$rightLabel</span>";
	echo "</div>";
}
	

function dumpClientAddress($client){ // mailing address or home address if no mailing address
	// if not "mail to home" try the mailing address, otherwise mail
	if(!$client['mailtohome']) 
		$address = getAddress($client, 'mail');
  // if still no address, try home
	if(!join('', (array)$address))
		$address = getAddress($client, '');
	echo "{$client['fname']} {$client['lname']}<br>";
	if(!join('', $address))
		echo "No Address On Record";
	else echo htmlFormattedAddress($address);
}

function dumpInvoiceHeader($invoice, $client) {  // customer #, customer invoice #, invoice date, Amount Due
	global $origbalancedue, $credits, $tax, $creditApplied;
	echo "<table width=290>";
	echo "<tr><td colspan=2 style='font-weight:bold'>Statement</td><tr>"; // Prepayment Invoice
	labelRow('Customer Number:', '', $client['clientid'], '', 'right');
	labelRow('Invoice Date:', '', shortDate(strtotime($invoice['date'])), '', 'right');
	//$amountDue = $origbalancedue - $creditApplied + $tax - $credits;
	//$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
	$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
	$amountDue = $amountDue < 0 ?  dollars(abs($amountDue)).'cr' : dollars($amountDue);
	labelRow("<img height=16 width=20 src='https://{$_SERVER["HTTP_HOST"]}/art/redarrowright.png'>Amount Due:", '', $amountDue,
								$labelClass='fontSize1_8em', 'rightAlignedTD fontSize1_8em', 
								'', 'border: solid black 1px;', 'raw');
	//labelRow('Amount Due:', '', $amountDue, $labelClass=null, 'bigger-right', '', 'border: solid black 1px;', 'raw');
	echo "</table>";
}

/*function dumpBusinessLogoDiv() {
	global $bizptr, $preferences, $mein_host;  // in absence of SESSION, $bizptr must be set to the business's id number
	if($_SESSION && isset($_SESSION["bizfiledirectory"]))
		$headerBizLogo = $_SESSION["bizfiledirectory"];
	else $headerBizLogo = "bizfiles/biz_$bizptr/";
	if($headerBizLogo) {
		if(file_exists($_SESSION["bizfiledirectory"].'logo.jpg')) $headerBizLogo .= 'logo.jpg';
		else if(file_exists($_SESSION["bizfiledirectory"].'logo.gif')) $headerBizLogo .= 'logo.gif';
		else $headerBizLogo = '';
		if($headerBizLogo) {
			$this_dir = $mein_host.substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
			$headerBizLogo = "<img src='$this_dir/$headerBizLogo' />";
		}
	}
	$headerBizLogo = $headerBizLogo 
		? $headerBizLogo . '<br>' . oneLineTextLogo($preferences)
		: textLogo($preferences);
	echo $headerBizLogo;
}
*/
function dumpBusinessLogoDiv($amountDue, $html=null, $preview=false, $clientid) {
	global $preferences;
	if(!$preferences) $preferences = $_SESSION['preferences'];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r($preferences);	
	$headerBizLogo = getBizLogoImage(); 
	if(!$html) {
		$headerIndex =  $preview ? 'emailedInvoicePreviewHeader' : 'invoiceHeader';
		$html = $preferences[$headerIndex] //emailedInvoicePreviewHeader
			? $preferences[$headerIndex]
			: ($preview ? generateDefaultBusinessLogoDivContentsForInvoicePreview($headerBizLogo)
					:generateDefaultBusinessLogoDivContents($headerBizLogo));
	}
}	
	$html = str_replace('#LOGO#', $headerBizLogo, $html);
	$html = str_replace('#PHONE#', $preferences['bizPhone'], $html);
	$html = str_replace('#FAX#', $preferences['bizFax'], $html);
	$html = str_replace('#EMAIL#', $preferences['bizEmail'], $html);
	$html = str_replace('#HOMEPAGE#', $preferences['bizHomePage'], $html);
	$html = str_replace('#ADDRESS#', $preferences['bizAddress'], $html);
	$html = str_replace('#BIZNAME#', $preferences['bizName'], $html);
	$html = str_replace('#AMOUNTDUE#', $amountDue, $html);
	if($clientid) $html = str_replace('#CLIENTID#', $clientid, $html);
	
	if($preferences['bizAddressJSON']) // NEW bizAddress handling
		foreach(json_decode($preferences['bizAddressJSON']) as $k => $v)
			$html = str_replace("#".strtoupper($k)."#", $v, $html);
	else {
		$addressParts = explode(' | ', $preferences['bizAddress']);
		foreach(array('#STREET1#','#STREET2#','#CITY#','#STATE#','#ZIP#') as $i => $token) 
			$html = str_replace($token, $addressParts[$i], $html);
	}

	$html = str_replace("\n", '<br>', $html);
	echo $html;
}

function generateDefaultBusinessLogoDivContents($headerBizLogo=null, $raw=null) {
	global $preferences;
	if(!$preferences) $preferences = $_SESSION['preferences'];
	$headerBizLogo = $headerBizLogo ? $headerBizLogo :  getBizLogoImage(); 
	$headerBizLogo = $headerBizLogo 
		? "#LOGO#\n" . oneLineTextLogo($preferences, $raw)
		: textLogo($preferences, $raw);
	return $headerBizLogo;
}

function generateDefaultBusinessLogoDivContentsForInvoicePreview($headerBizLogo=null, $raw=null) {
	global $preferences;
	if(!$preferences) $preferences = $_SESSION['preferences'];
	$headerBizLogo = $headerBizLogo ? $headerBizLogo :  getBizLogoImage(); 
	$headerBizLogo = $headerBizLogo 
		? "#LOGO#\n" . oneLineTextLogo($preferences, $raw)
			."<hr><p align='center'>Please review the visits and charges below and let us know if there is anything that needs to be corrected.
We will charge your credit card within 48 hours unless we hear from you.</p>"
		: textLogo($preferences, $raw);
	return $headerBizLogo;
}

function getBizLogoImage() {
	global $bizptr;  // in absence of SESSION, $bizptr must be set to the business's id number
	if($_SESSION && isset($_SESSION["bizfiledirectory"]))
		$headerBizLogo = $_SESSION["bizfiledirectory"];
	else $headerBizLogo = "bizfiles/biz_$bizptr/";
	if($headerBizLogo) {
		$headerBizLogo = getHeaderBizLogo($headerBizLogo);
		if($headerBizLogo) {
			$imgSrc = globalURL($headerBizLogo);
			$headerBizLogo = "<img src='$imgSrc'>";
		}
	}
	return $headerBizLogo;
}




function oneLineTextLogo($preferences) {
	$parts = array();
	if($preferences['bizPhone']) $parts[] = "Phone: {$preferences['bizPhone']}";
	if($preferences['bizFax']) $parts[] = "FAX: {$preferences['bizFax']}";
	if($preferences['bizEmail']) $parts[] = "{$preferences['bizEmail']}";
	if($preferences['bizHomePage']) $parts[] = "{$preferences['bizHomePage']}";
	$logo =	join(' - ', $parts);
	if($preferences['bizAddress'] && trim(join('', explode('|', $preferences['bizAddress'])))) $logo = $preferences['bizAddress']."<br>$logo";
	return $logo;
}

function textLogo($preferences) {
	$logo =	"<div style='width:369;height:90'>{$preferences['bizName']}<br>{$preferences['bizAddress']}<br>Phone: {$preferences['bizPhone']}";
	if($preferences['bizFax']) $logo .=	" - FAX: {$preferences['bizFax']}";
	$logo .=	"<br>Email: {$preferences['bizEmail']}<br>{$preferences['bizHomePage']}</div>";
	return $logo;
}




function invoiceIdDisplay($invoiceid, $prefix="LT") {
	return $invoiceid? $prefix.sprintf("%04d", $invoiceid) : '(New)';
}


function dollars($amount) {
	$amount = $amount ? $amount : 0;
	return dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
}
	
function getAddress($client, $prefix) {
	foreach(array('street1','street2','city','state','zip') as $field) $address[$field] = $client["$prefix$field"];
	return $address;
}



function dumpCurrentPastInvoiceSummaries($invoiceid) {}// Invoice #, Date, Balance Due
function dumpMessage($invoice) {
	if($db == 'leashtimecustomers') return dumpLeashTimeCustomerStats($invoice);
}// should we add a message field to invoice?

function dumpLeashTimeCustomerStats($invoice) {
	require_once 'provider-fns.php';
	if($db != 'leashtimecustomers') return;
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar("Activity in this period", "");
	
	$rows = getProviderVisitCountForMonth($date);
	foreach($rows as $row) $total += $row['visits'];
	$rows = array_merge(array(array('name'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
	
	
	
	
	
	echo "</td></tr><tr><td>";
	echo "</td></tr></table>";
}



function dumpFooter() {
	if($_SESSION['preferences']['statementFooter']) echo $_SESSION['preferences']['statementFooter'];

}
	
function prepaymentClientLink($pp) {
	return "<a href=#
	       onClick='openConsoleWindow(\"viewclient\", \"client-view.php?id={$pp['clientptr']}\",700,500)'>
	       {$pp['clientname']}</a> ";
}

function ccStatus($cc) {
	static $ccStatus;
	if(!$ccStatus) {
		$ccStatus = array();
		$ccStatusRAW = <<<CCSTATUS
		No Credit Card on file,nocc.gif,NO_CC
		Card expired: #CARD#,ccexpired.gif,CC_EXPIRED
		Autopay not enabled: #CARD#,ccnoautopay.gif,CC_NO_AUTOPAY
		Valid card on file: #CARD#,ccvalid.gif,CC_VALID
		Valid ACH info on file: #CARD#,ccvalid.gif,ACH_VALID
CCSTATUS;
		foreach(explode("\n", $ccStatusRAW) as $line) {
			$set = explode(",", trim($line));
			$ccStatus[$set[2]] = $set;
		}
	}

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { if($cc['clientptr'] == 959) { print_r($ccStatus);exit; }}
	if(!$cc) return $ccStatus['NO_CC'] ;
	else if(!$cc['company']) return $ccStatus['ACH_VALID'];
	else if(strtotime(date('Y-m-t', strtotime($cc['x_exp_date']))) < strtotime('Y-m-d')) return $ccStatus['CC_EXPIRED'];
	else if(!$cc['autopay']) return $ccStatus['CC_NO_AUTOPAY'];
	else return $ccStatus['CC_VALID'];
}

function ccStatusDisplay(&$prepayment) {
	global $clearCCs, $ccStatus;
	$clientid = $prepayment['clientptr'];
	if(!$_SESSION['ccenabled']) return '';
	$cc = $clearCCs[$clientid];
	$status = ccStatus($cc);
	if($cc) {
		$prepayment['autopay'] = $cc['autopay'];
		$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
		$cardLabel = $cc['company'] 
			?"{$cc['company']} ************{$cc['last4']} Exp: ".shortExpirationDate($cc['x_exp_date']).$cardLabel
			: "E-Checking acct: ************{$cc['last4']} $cardLabel";
	}
	$title = str_replace('#CARD#', $cardLabel, $status[0]);
	return "<img src='art/".$status[1]."' title='$title' />";
}

function ccStatusDisplayForClientId($clientid) {
	return ccStatusDisplayForCC(getClearCC($clientid));
}

function ccStatusDisplayForCC($cc) {
	if(!$_SESSION['ccenabled']) return '';
	$status = ccStatus($cc);
	if($cc) {
		$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
		$expiration = $cc['acctid'] ? '' : "Exp: ".shortExpirationDate($cc['x_exp_date']);
		$cardLabel = "{$cc['company']} ************{$cc['last4']} ".$expiration.$cardLabel;
	}
	$title = str_replace('#CARD#', $cardLabel, $status[0]);
	return "<img src='art/".$status[1]."' title='$title' />";
}

function paymentLink($clientid, $amount) {
	$url = "prepayment-invoice-payment.php?client=$clientid&amount=$amount";
		return fauxLink('Pay', "openConsoleWindow(\"paymentwindow\", \"$url\",600,400)", 1, "Record a payment for this prepayment invoice.");
}

function historyLink($clientid, $repeatCustomers) {
	if(in_array($clientid, $repeatCustomers)) 
		return fauxLink('History', "viewRecent($clientid)", 1, "View recent prepayment invoices");
}
