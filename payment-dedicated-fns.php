<? // payment-dedicated-fns.php

/*

This table represents the stated dedication of a payment to a number of
charges, NR schedules, monthly schedule billables, surcharges, and recurring visits.

It does NOT imply that the payment will cover all of these items.
It does NOT imply that all of the possible components (of a NR schedule, say) all
	of the items exist at the time of the dedication
This DOES imply that when client billables are paid, any payment in this table with an unused portion
	will be applied first to the items in this table, and afterwards to unrelated items.

CREATE TABLE IF NOT EXISTS `reldedicatedpayment` (
  `dedicatedpaymentid` int(11) NOT NULL AUTO_INCREMENT,
  `clientptr` int(11) NOT NULL,
  `expensetable` varchar(20) NOT NULL,
  `expenseptr` int(11) NOT NULL,
  `paymentptr` int(11) NOT NULL,
  PRIMARY KEY (`dedicatedpaymentid`),
  UNIQUE KEY `uniqeindex` (`expensetable`,`expenseptr`,`paymentptr`),
  KEY `expenseindex` (`expensetable`,`expenseptr`),
  KEY `paymentindex` (`paymentptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

*/
function getNRPackageExpenses($packageid, $clientptr) {
	$expenses = array();
	$history = findPackageIdHistory($packageid, $clientptr, false);
	$history[] = $packageid; // necessary?
	$history = join(',', $history);
	$found = false;
	$apptids = fetchCol0($sql =
		"SELECT appointmentid 
			FROM tblappointment 
			WHERE packageptr IN ($history) AND canceled IS NULL
			ORDER BY date, starttime");
	foreach($apptids as $id) {
		if($exp = applicableVisitOrSurcharge('tblappointment', $id)) {
			$exp['itemtable'] = 'tblappointment';
			$exp['itemptr'] = $id;
			$expenses[] = $exp;
		}
	}
	$surchargeids = fetchCol0(
		"SELECT surchargeid
			FROM tblsurcharge WHERE packageptr IN ($history) AND canceled IS NULL ORDER BY date, starttime");
	foreach($surchargeids as $id) {
		if($exp = applicableVisitOrSurcharge('tblsurcharge', $id)) {
			$exp['itemtable'] = 'tblsurcharge';
			$exp['itemptr'] = $id;
			$expenses[] = $exp;
		}
	}
	return $expenses;
}


function getCurrentExpenseBillable($expensetable, $expenseptr) {
	return fetchFirstAssoc("SELECT * FROM tblbillable WHERE itemtable  = '$expensetable' AND itemptr = $expenseptr AND superseded = 0 LIMIT 1");
}

function spendDedicatedPaymentOnBillable(&$availCredits, $billableptr, &$paidBillables) {
	$owed = fetchRow0Col0("SELECT charge - paid FROM tblbillable WHERE billableid = $billableptr AND superseded = 0 LIMIT 1");
	if($owed <= 0) continue;
	$paid =  consumeCredits($availCredits, $owed, $billableptr);
	if($paid) {
		updateTable('tblbillable', array('paid'=>sqlVal("paid + $paid")), "billableid = $billableptr");
		$paidBillables[] = $billable['billableid'];
	}
}

function spendDedicatedPayment($client, $paymentid) {  //called when a credit/payment is created
	$itemIdFields = explodePairsLine(
		"tblappointment|appointmentid||tblsurcharge|surchargeid||tblothercharge|chargeid||tblservicepackage|packageid");

	$targets = fetchAssociations("SELECT * FROM reldedicatedpayment WHERE paymentptr = $paymentid ORDER BY dedicatedpaymentid", 1);

	$availCredits = fetchAssociations("SELECT *, amount - amountused as amountleft FROM tblcredit WHERE creditid = $paymentid");

	$paidBillables = array();
$staffTest = $_SESSION['staffuser'];
//if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #10');

//print_r($availCredits);

	// Taking the items in order we must:
	foreach($targets as $target) {
		$expensetable = $target['expensetable'];
		$expenseptr = $target['expenseptr'];


		// 1. pay the billable if it is a billable and is unpaid
		if($expensetable == 'tblbillable') {
			spendDedicatedPaymentOnBillable($availCredits, $expenseptr, $paidBillables);
			continue;
		}
		$expense = fetchFirstAssoc("SELECT * FROM $expensetable WHERE {$itemIdFields[$expensetable]} = $expenseptr LIMIT 1");
		$billable = getCurrentExpenseBillable($expensetable, $expenseptr);
		$billableId = $billable['billableid'];
		// 2. or for a charge find a billable for the item and pay it if it is unpaid
		if($expensetable == 'tblothercharge') {
			if(!$billable) {
				$billable = array('clientptr'=>$client, 'itemptr'=>$expenseptr, 'itemtable'=>$expensetable, 
														'charge'=>($expense['amount'] ? $expense['amount'] : '0.0'), 'itemdate'=> $charge['issuedate'], 'billabledate'=>date('Y-m-d')); /*, 'paid'=>0*/
				// One would prefer to consume credits and then create a billable with 'paid' pre-determined,
				// but to associate a billable with one or more credits, we need to create the billable first
				// and then pay it off.
				$billableId = insertTable('tblbillable', $newBillable, 1);
			}
			spendDedicatedPaymentOnBillable($availCredits, $billableId, $paidBillables);
		}				
		// 3. or for an EZ schedule find all items
		else if($expensetable == 'tblservicepackage') {
//echo "<hr><hr>target: ".print_r($target,1)."<br>[$expensetable] [$expenseptr]";
			$items = getNRPackageExpenses($expenseptr, $client);
			foreach($items as $item) {

//echo "<hr>item: ".print_r($item,1);
				// 3.a. find a billable for the item and pay it if it is unpaid
				$billable = getCurrentExpenseBillable($item['itemtable'], $item['itemptr']);
				$billableId = $billable['billableid'];
				// 3.b. or create a billable for the item and pay it

				if(!$billable) {
					if($item['itemtable'] == 'tblappointment') {
						$discountAmount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = {$item['appointmentid']} LIMIT 1");
						$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = {$item['appointmentid']} LIMIT 1");
						$billable = createApptBillableObject($appt, $discountAmount);
						$billableId = insertTable('tblbillable', $billable, 1);

					}
					else {
						//$surch = fetchFirstAssoc("SELECT * FROM tblsurcharge WHERE surchargeid = {$item['surchargeid']} LIMIT 1");
						$billable = createSurchargeBillableObject($item['surchargeid']);
						$billableId = insertTable('tblbillable', $billable, 1);
					}
				}

				spendDedicatedPaymentOnBillable($availCredits, $billableId, $paidBillables);
			}
		}
		// 4. For visits/surcharges 
		else if(in_array($expensetable, array('tblappointment', 'tblsurcharge'))) {
		// 4.a. find a billable for the item and pay it if it is unpaid
		// 4.b. or create a billable for the item and pay it
			if(!$billable) { // $billable, $billableId are both set above
				if($expensetable == 'tblappointment') {
					$discountAmount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = $expenseptr LIMIT 1");
					$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $expenseptr LIMIT 1");
					$billable = createApptBillableObject($appt, $discountAmount);
					$billableId = insertTable('tblbillable', $billable, 1);
				}
				else {
					//$surch = fetchFirstAssoc("SELECT * FROM tblsurcharge WHERE surchargeid = {$item['surchargeid']} LIMIT 1");
					$billable = createSurchargeBillableObject($expenseptr);
					$billableId = insertTable('tblbillable', $billable, 1);
				}
			}
			spendDedicatedPaymentOnBillable($availCredits, $billableId, $paidBillables);

		}
		// TBD: CHECK ALL PLACES WHERE VISITS/SURCHARGES ARE COMPLETED TO MAKE SURE NO SPECIAL CHANGES ARE NEEDED

	}
	if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #20');
	//if($staffTest) $invoicedFirst =  false;

	if($paidBillables) {
		$invoiceids = fetchCol0("SELECT invoiceid 
																	FROM tblinvoice 
																	WHERE clientptr = $client AND paidinfull IS NULL");
	if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #50');

		foreach($invoiceids as $invoiceid) checkInvoicePaid($invoiceid);
	}
	//if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #60');
//exit;	
}

function describePaymentDedication($paymentptr) {
	$targetTypes = array_flip(explodePairsLine('other|tblothercharge||package|tblservicepackage||monthly|tblbillable'
																		.'||visit|tblappointment||surcharge|tblsurcharge'));
	$targets = fetchAssociations("SELECT * FROM reldedicatedpayment WHERE paymentptr = $paymentptr ORDER BY dedicatedpaymentid");
	if(!$targets) return array();
	$clientptr = $targets[0]['clientptr'];
	foreach($targets as $target)
		$breakdown[$target['expensetable']][] = $target['expenseptr'];
	foreach($breakdown as $tbl => $itemids) {
		if($tbl == 'tblothercharge') $descr[] = count($itemids)." miscellaneous charge(s)";
		else if($tbl == 'tblsurcharge') $descr[] = count($itemids)." surcharge(s) in ongoing schedules";
		else if($tbl == 'tblappointment') $descr[] = count($itemids)." appointment(s) in ongoing schedules";
		else if($tbl == 'tblbillable') {
			$dates = fetchCol0("SELECT monthyear FROM tblbillable WHERE billableid IN (".join(',', $itemids).")");
			foreach($dates as $date) $months[] = date('M Y', strtotime($date));
			$descr[] = "the fixed price monthly schedule for ".join(', ', $months);
		}
		else if($tbl == 'tblservicepackage') {
			foreach($itemids as $packageid) {
				$history = findPackageIdHistory($packageid, $clientptr, false);
				$package = fetchFirstAssoc("SELECT * FROM tblservicepackage WHERE packageid = ".array_pop($history)." LIMIT 1");
				$descr[] = "the short term schedule dated ".shortDate(strtotime($package['startdate']))
										.($package['enddate'] ? ' - '.shortDate(strtotime($package['enddate'])) : '');
			}
		}
	}
	return $descr;
}


function dedicatePayment($clientptr, $paymentid, $targets) {
	// targets will be a $_POST copy
	// consider only targets prefixed with: other_,package_,monthly_,visit_surcharge_
	$targetTables = explodePairsLine('other|tblothercharge||package|tblservicepackage||monthly|tblbillable'
																		.'||visit|tblappointment||surcharge|tblsurcharge');
	foreach(array_keys($targets) as $k) {
		$target = explode('_', $k);
		
		if(!($table = $targetTables[$target[0]])) continue;
		$itemid = $target[1];
		$rel = array('clientptr'=>$clientptr, 'paymentptr'=>$paymentid, 'expensetable'=>$table);
		if($table == 'tblothercharge') {
			$billableid = fetchRow0Col0( // MISC CHARGES
				"SELECT billableid FROM tblbillable 
					WHERE itemptr = $itemid
								AND itemtable = '$table'
								AND superseded = 0
								AND charge > paid");
			if($billableid) {
				$rel['expenseptr'] = $target[1];
				insertTable('reldedicatedpayment', $rel, 1);
			}
		}
		else if($table == 'tblbillable') { // MONTHLY BILLABLES
			$billableid = fetchRow0Col0(
				"SELECT billableid FROM tblbillable 
					WHERE billableid = $itemid
								AND superseded = 0
								AND charge > paid");
			if($billableid) {
				$rel['expenseptr'] = $target[1];
				insertTable('reldedicatedpayment', $rel, 1);
			}
		}
		else if(in_array($table, array('tblappointment', 'tblsurcharge'))) { // RECURRING VISITS AND SURCHARGES
			if(applicableVisitOrSurcharge($table, $itemid)) {
				$rel['expenseptr'] = $itemid;
				insertTable('reldedicatedpayment', $rel, 1);
			}
		}
		else if($table == 'tblservicepackage') { // SHORT TERM SCHEDULES
			$history = findPackageIdHistory($itemid, $clientptr, false);
			$history[] = $itemid; // necessary?
			$history = join(',', $history);
			$found = false;
			$apptids = fetchCol0(
				"SELECT appointmentid 
					FROM tblappointment 
					WHERE packageptr IN ($history) AND canceled IS NULL
					ORDER BY date, starttime");
//print_r("SELECT appointmentid FROM tblappointment WHERE packageptr IN ($history) ORDER BY date, starttime");					
			foreach($apptids as $id) {
				if(applicableVisitOrSurcharge('tblappointment', $id)) {
					$found = true;
					break;
				}
			}
			$surchargeids = fetchCol0(
				"SELECT surchargeid
					FROM tblsurcharge WHERE packageptr IN ($history) AND canceled IS NULL ORDER BY date, starttime");
			foreach($surchargeids as $id) {
				if(applicableVisitOrSurcharge('tblsurcharge', $id)) {
					$found = true;
					break;
				}
			}
			if($found)  {
				$rel['expenseptr'] = $itemid;
				insertTable('reldedicatedpayment', $rel, 1);
			}
		}
	}
}

function applicableVisitOrSurcharge($table, $itemid, $includePaidItems=false) {
	// return item if visit or surcharge has a non-zero amount owed (regardless of completion)
	// discounts may reduce the price owed to zero
	$itemidfield = $table == 'tblappointment' ? 'appointmentid' : 'surchargeid';
	$plusAdjustment = $table == 'tblappointment' ? '+ IFNULL(adjustment,0)' : '';
	$servicecode = $table == 'tblappointment' ? 'servicecode,' : '';
	$includePaidItems = $includePaidItems ? '' : "\nAND ((b.billableid IS NOT NULL AND b.charge > b.paid)\n	OR (b.billableid IS NULL AND $table.charge $plusAdjustment > 0))";
	$item = fetchFirstAssoc(
		"SELECT $table.clientptr, $itemidfield, billableid, $table.charge $plusAdjustment as charge, date,
						packageptr, $servicecode
						IF(billableid IS NULL, 0, b.charge) as bcharge, paid
			FROM $table
			LEFT JOIN tblbillable b ON b.itemptr = $itemid AND b.itemtable = '$table' AND b.superseded = 0
			WHERE $itemidfield = $itemid $includePaidItems");
	if(!$item) return;
	if($item['billableid']) {  // billables incorporate discounts
		$item['owed'] = $item['bcharge'] - $item['paid'];
		$item['finalcharge'] = $item['bcharge'];
	}
	else { // no billable -- must figure discount and tax
		$discount = 0;
		if($item['appointmentid']) {
			$discount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = $itemid");
			if($item['charge'] - $discount <= 0) return;
			$item['discount'] = $discount;
			$billable = createApptBillableObject($item, $discount);
		}
		else $billable = createSurchargeBillableObject($item);
		$item['owed'] = round($billable['charge'] - $discount, 2);
		$item['finalcharge'] = $item['owed'];
	}
	return $item;
}
