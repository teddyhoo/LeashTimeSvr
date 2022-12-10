<? // credit-fns.php
define('LT_EXCESSIVE_CHARGE', 1);

function creditAppliedToTable($credit) {
	if($credit['amountused'] > 0.0) {
		$billables = fetchAssociations(
			"SELECT b.*, bp.amount as applied
				FROM relbillablepayment bp
				LEFT JOIN tblbillable b ON billableid = billableptr
				WHERE paymentptr = {$credit['creditid']}");
		return billableTable($billables, $display=false);
	}
}

function billableTable($billables, $display=true) {
//if(mattOnlyTEST()) print_r($billables);	
	if(!$billables) return;
	ob_start();
	ob_implicit_flush(0);
	$hide = $display ? '' : "display:none;";
	echo "<table id='billablestable' style='width:100%; $hide'><th colspan=2>Applied to...</th>";
	require_once "appointment-fns.php";
	require_once "surcharge-fns.php";
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$surcharges = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	foreach($billables as $b) {
		$row = array();
		$row['chargedisplay'] = dollarAmount($b['charge']);
		if($b['charge'] != $b['applied']) {
			$row['chargedisplay'] = "<i>(".dollarAmount($b['applied']).")</i> "
			.' '.$row['chargedisplay'];
			$explainItalics = true;
		}
		if($b['itemtable'] == 'tblappointment') {
			$item = getAppointment($b['itemptr'], $withNames=true);
			if(!$item) {
				$row['date'] = shortDate(strtotime($b['itemdate']));
				$row['description'] = "Missing visit.";
				if(staffOnlyTEST()) $row['description'] .= "(billable: {$b['billableid']} itemptr: {$b['itemptr']})";
			}
			else {
				$row['date'] = shortDate(strtotime($item['date'])).' '.briefTimeOfDay($item);
				$row['description'] = "{$services[$item['servicecode']]} ({$item['pets']})";
			}
		}
		else if($b['itemtable'] == 'tblsurcharge') {
			$item = getSurcharge($b['itemptr'], $withNames=true);
			$btod = $item['appointmentptr'] ? briefTimeOfDay(getAppointment($item['appointmentptr'])) : '';
			$row['date'] = shortDate(strtotime($item['date'])).' '.$btod;
			$row['description'] = "(surcharge) {$surcharges[$item['surchargecode']]}";
		}
		else if($b['itemtable'] == 'tblothercharge') {
			$item = fetchFirstAssoc("SELECT * FROM tblothercharge WHERE chargeid = {$b['itemptr']} LIMIT 1");
			$row['date'] = shortDate(strtotime($item['issuedate']));
			$row['description'] = $item['reason'] ? "Misc: {$item['reason']}" : "Miscellaneous charge";
		}
		else if($b['itemtable'] == 'tblrecurringpackage') {
			//$item = fetchFirstAssoc("SELECT * FROM tblrecurringpackage WHERE packageid = {$b['itemptr']} LIMIT 1");
			$row['date'] = shortDate(strtotime($b['itemdate']));
			$row['description'] = "Monthly Package ".date('F Y', strtotime($b['monthyear']));
		}
		$superseded = $b['superseded'] ? 'text-decoration: line-through;' : '';
		echo "<tr style='border-bottom:solid gray 1px;'><td>{$row['date']}</td><td>{$row['description']}</td><td style='text-align:right;$superseded'>{$row['chargedisplay']}</td></tr>";
	}
	if($explainItalics)
		echo "<tr class='tiplooks'><td colspan=3>A dollar amount in italics indicates the portion applied toward the charge.</td></tr>";
	echo "</table>";
	$descr = ob_get_contents();
	ob_end_clean();
	return $descr;
}

function unVoidCredit($voidedCreditID) {
	$credit = fetchFirstAssoc(
		"SELECT *, CONCAT_WS(' ',fname, lname, CONCAT('(', clientid, ')')) as client, amount
			FROM tblcredit
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE creditid = $voidedCreditID AND voided IS NOT NULL LIMIT 1");
	if(!$credit) return null;
	$now = date('Y-m-d H:i:s');
	updateTable('tblcredit', 
		array('amount'=>$credit['amount'],
					'amountused'=>0,
					'voided'=>sqlVal(null),
					'voidedamount'=>sqlVal(null),
					'modified'=>$now,
					'modifiedby'=>$_SESSION['auth_user_id'],
					'hide'=>$hide),
		"creditid = $voidedCreditID", 1);
	payOffClientBillables($credit['clientptr']);
	return true;
}

function voidCredit($deleteCredit, $reason='', $hide=0, $retainGratuities=false) {
	$doomed = fetchFirstAssoc(
		"SELECT *, CONCAT_WS(' ',fname, lname, CONCAT('(', clientid, ')')) as client, amount
			FROM tblcredit
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE creditid = $deleteCredit 
			AND voided IS NULL
			LIMIT 1");
	if(!$doomed) return null;
	$now = date('Y-m-d H:i:s');
	updateTable('tblcredit', 
		array('amount'=>0,
					'amountused'=>0,
					'voided'=>$now,
					'voidedamount'=>$doomed['amount'],
					'modified'=>$now,
					'modifiedby'=>$_SESSION['auth_user_id'],
					'hide'=>$hide),
		"creditid = $deleteCredit", 1);
	if(!$retainGratuities) deleteTable('tblgratuity', "paymentptr = $deleteCredit", 1);	
	//deleteTable('relrefundcredit', "creditptr = $deleteCredit", 1);	
	$billables = fetchAssociations(
			"SELECT billableid, invoiceptr, amount, paid, itemtable, itemptr 
				FROM relbillablepayment 
				LEFT JOIN tblbillable ON billableid = billableptr
				WHERE paymentptr = $deleteCredit");
//if(mattOnlyTEST()) { print_r($billables);exit;}				
	foreach($billables as $billable) {
		if($billable['invoiceptr']) $invoiceptrs[] = $billable['invoiceptr'];
		$amount = $billable['amount'] ? $billable['amount'] : '0.0';
		if($billable['billableid']) {
			$surchargeOrAppointment = in_array($billable['itemtable'], array('tblappointment', 'tblsurcharge'));
			
			if($surchargeOrAppointment) {
				$idfield = substr($billable['itemtable'], 3).'id';
				$item = fetchFirstAssoc("SELECT *  FROM {$billable['itemtable']} WHERE $idfield = {$billable['itemptr']} LIMIT 1", 1);
			}
			
			// NEW -- item may no longer exist.  If so, supersede billable
			if(staffOnlyTEST() && $surchargeOrAppointment && !$item) {
					updateTable('tblbillable', 
									array('superseded'=>"1"), 
									"billableid = {$billable['billableid']}", 1);
					continue;
			}
			// END NEW
			
			updateTable('tblbillable', 
							array('paid'=>sqlVal("paid - $amount")), 
							"billableid = {$billable['billableid']}", 1);
							
			// CHANGE OF PLAN: NEVER DELETE A BILLABLE BECAUSE OF A VOID
			// CHANGE OF PLAN 2: DELETE A BILLABLE BECAUSE OF A VOID 
			//    ONLY WHEN IT IS A RECURRING VISIT OR A SURCHARGE ASSOCIATED W/ A RECURRING VISIT
			// check that visits,surcharges are marked complete (for dedicated payment voiding)
			// delete the billable if its item is not completed
			if($billable['itemptr'] // prob unneccessary
					&& $billable['paid'] - $amount == 0 // necessary because another dedicated payment may still apply to incomplete visit/surcharge
					&& $surchargeOrAppointment ) {
				
				
				
				// item may be an appt or surcharge.
				if($item['surchargeid'] && !$item['completed'] && $item['appointmentptr'])
					$item = fetchFirstAssoc("SELECT *  FROM tblappointment WHERE appointmentid = {$item['appointmentptr']} LIMIT 1", 1);
				//if(!fetchRow0Col0("SELECT completed FROM {$billable['itemtable']} WHERE $idfield = {$billable['itemptr']} LIMIT 1",1))
				if($item['appointmentid'] && !$item['completed'] && $item['recurringpackage'])
					deleteTable('tblbillable', "billableid = {$billable['billableid']}",1);
			}
		}
	}
	deleteTable('relbillablepayment', "paymentptr = $deleteCredit", 1);
	
	// there may be recurring visit billables or surcharge billables 
	//   associated with recurring visitsdedicated to this payment
	// these billables should be deleted if the items are not marked
	$moreBillables = fetchAssociations(
		"SELECT b.* 
			FROM reldedicatedpayment 
			LEFT JOIN tblbillable b ON itemtable = expensetable AND itemptr = expenseptr
			WHERE paymentptr = $deleteCredit", 1);
	if($moreBillables) {
		foreach($moreBillables as $billable) {
			if(in_array($billable['itemtable'], array('tblappointment', 'tblsurcharge'))) {
				$idfield = substr($billable['itemtable'], 3).'id';
				$item = fetchFirstAssoc("SELECT *  FROM {$billable['itemtable']} WHERE $idfield = {$billable['itemptr']} LIMIT 1", 1);
				// item may be an appt or surcharge.
				if($item['surchargeid'] && !$item['completed'] && $item['appointmentptr'])
					$item = fetchFirstAssoc("SELECT *  FROM tblappointment WHERE appointmentid = {$item['appointmentptr']} LIMIT 1", 1);
				//if(!fetchRow0Col0("SELECT completed FROM {$billable['itemtable']} WHERE $idfield = {$billable['itemptr']} LIMIT 1",1))
				if($item['appointmentid'] && !$item['completed'] && $item['recurringpackage'])
					deleteTable('tblbillable', "billableid = {$billable['billableid']}",1);
			}
		}
	}
	
	deleteTable('reldedicatedpayment', "paymentptr = $deleteCredit", 1);
	if($invoiceptrs) {
		$invoiceptrs = array_unique($invoiceptrs);
		$invoiceList = join(',', $invoiceptrs);
		updateTable('tblinvoice', array('paidinfull'=>sqlVal("NULL")), "invoiceid IN ($invoiceList)", 1);
	}
	else $invoiceList = '0';
	$type = $doomed['payment'] ? 'payment' : 'credit';
	if($reason) {
		require_once "item-note-fns.php";
		$voidenoteid = updateNote(array('itemtable'=>'tblcredit', 'itemptr'=>$deleteCredit), $reason);
	}
	logChange($deleteCredit, 'tblcredit', 'm', 
						$note="VOIDED Client: {$doomed['client']} Type: $type Amount: \${$doomed['amount']} "
									."Issued: {$doomed['issuedate']} Void note: $voidenoteid Invoices affected: $invoiceList "
									."Billables affected: ".count($billables)."User: {$_SESSION['auth_user_id']}");
	payOffClientBillables($doomed['clientptr']);
}

function reapplyCredit($creditid, $showInvoice) {
	$credit = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $creditid");
	$hide = $showInvoice ? 0 : 1;
	$mods = array('voided'=>sqlVal('NULL'), 'amount'=>sqlVal('voidedamount'), 'voidedamount'=>sqlVal('NULL'), 'hide'=>$hide);
	$mods = withModificationFields($mods);
	updateTable('tblcredit', $mods, "creditid=$creditid");
	payOffClientBillables($credit['clientptr']);
}


function deleteCredit($deleteCredit, $reason='') {
	$doomed = fetchFirstAssoc(
		"SELECT *, CONCAT_WS(' ',fname, lname, CONCAT('(', clientid, ')')) as client
			FROM tblcredit
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE creditid = $deleteCredit LIMIT 1");
	deleteTable('tblcredit', "creditid = $deleteCredit");
	deleteTable('tblgratuity', "paymentptr = $deleteCredit", 1);	
	deleteTable('relrefundcredit', "creditptr = $deleteCredit", 1);	
	deleteTable('relinvoicecredit', "creditptr = $deleteCredit", 1);	// holy crap!  NO!  STOP!!
	$billables = fetchAssociations(
			"SELECT billableid, invoiceptr, amount 
				FROM relbillablepayment 
				LEFT JOIN tblbillable ON billableid = billableptr
				WHERE paymentptr = $deleteCredit");
	foreach($billables as $billable) {
		if($billable['invoiceptr']) $invoiceptrs[] = $billable['invoiceptr'];
		updateTable('tblbillable', 
							array('paid'=>sqlVal("paid - {$billable['amount']}")), 
							"billableid = {$billable['billableid']}", 1);
		deleteTable('relbillablepayment', "paymentptr = $deleteCredit", 1);
	}
	deleteTable('relitemnote', "itemtable = 'tblcredit' AND itemptr = $deleteCredit", 1);
	if($invoiceptrs) {
		$invoiceptrs = array_unique($invoiceptrs);
		$invoiceList = join(',', $invoiceptrs);
		updateTable('tblinvoice', array('paidinfull'=>sqlVal("NULL")), "invoiceid IN ($invoiceList)", 1);
	}
	else $invoiceList = '0';
	$type = $doomed['payment'] ? 'payment' : 'credit';
	logChange($deleteCredit, 'tblcredit', 'd', 
							$note="Client: {$doomed['client']} Type: $type Amount: \${$doomed['amount']} "
									."Issued: {$doomed['issuedate']} Void reason: $reason Invoices affected: $invoiceList "
									."Billables affected: ".count($billables)."User: {$_SESSION['auth_user_id']}");
	payOffClientBillables($doomed['clientptr']);
}

function unpayBillable($billableid) {
	if(!$billableid) return;
	$paymentAssociations = fetchAssociations("SELECT * FROM relbillablepayment WHERE billableptr = $billableid");
	deleteTable('relbillablepayment', "billableptr = $billableid");
	foreach($paymentAssociations as $assoc) {
		$amount = $assoc['amount'] ? $assoc['amount'] : '0.0';
		$mods = array('amountused'=>sqlVal("if(amountused - $amount < 0, 0, amountused - $amount)"));
		updateTable('tblcredit', addModificationFields($mods), "creditid = {$assoc['paymentptr']}", 1);
		updateTable('tblbillable', array('paid'=>sqlVal("if(paid - $amount < 0, 0, paid - $amount)")), "billableid = {$assoc['billableptr']}", 1);
	}
}

function getClientCredits($clientid, $nonZero=null) {
	$nonZeroFrag = $nonZero ? "AND amountused < amount" : '';
	return fetchAssociations("SELECT *, amount - amountused as amountleft FROM tblcredit WHERE clientptr = $clientid $nonZeroFrag ORDER BY issuedate ASC");
}

function getClientCreditsSince($clientid, $date) {
	return fetchAssociations(
		"SELECT *, amount - amountused as amountleft 
			FROM tblcredit 
			WHERE clientptr = $clientid AND issuedate >= '$date'
			ORDER BY issuedate ASC");
}

function getUnusedClientCreditTotal($clientid) {
	return fetchRow0Col0(
		"SELECT sum(amount - amountused)
			FROM tblcredit 
			WHERE clientptr = $clientid");
}

function NEWgetTotalClientCreditsSinceLastInvoice($clientid, $excludeSystemCredits=false) {
	// find last invoice and grab (date, ccpayment)
	$oldinvoice = fetchFirstAssoc("SELECT * FROM tblinvoice WHERE clientptr = $clientid ORDER BY date DESC LIMIT 1");
	// if(date), find sum of all credits AFTER date
	$excludeSystemCredits = $excludeSystemCredits ? "AND bookkeeping = 0 AND (reason IS NULL OR (reason NOT LIKE '%created. (v:%' AND reason NOT LIKE '%superseded.'))" : '';
	if($oldinvoice) {
		if($oldinvoice['ccpayment']) {
			$ccpayment = explode('|', $oldinvoice['ccpayment']);
			$transid = $ccpayment[1];
			$orIsCCPayment = "OR externalreference = 'CC: $transid'";
		}
		$sum = fetchRow0Col0("SELECT sum(amount) FROM tblcredit WHERE clientptr = $clientid AND (issuedate >= '{$oldinvoice['date']}' $orIsCCPayment) $excludeSystemCredits");
		return $sum;
	}
	else return fetchRow0Col0("SELECT sum(amount) FROM tblcredit WHERE clientptr = $clientid $excludeSystemCredits");
}

function getTotalClientCreditsSinceLastInvoice($clientid, $excludeSystemCredits=false) {
	if(dbTEST('leashtimecustomers,themonsterminders')) return NEWgetTotalClientCreditsSinceLastInvoice($clientid, $excludeSystemCredits);
	$excludeSystemCredits = $excludeSystemCredits ? "AND bookkeeping = 0 AND (reason IS NULL OR (reason NOT LIKE '%created. (v:%' AND reason NOT LIKE '%superseded.'))" : '';
	$sql = 
			"SELECT sum(amount) 
			 FROM tblcredit 
			 LEFT JOIN relinvoicecredit ON creditptr = creditid 
		 WHERE invoiceptr IS NULL AND tblcredit.clientptr = $clientid $excludeSystemCredits";
	return fetchRow0Col0($sql);
		 		 //  ALTER TABLE `tblcredit` ADD INDEX `clientptrindex` ( `clientptr` )
		 		 // ALTER TABLE `relinvoicecredit` ADD INDEX `creditptrindex` ( `creditptr` ) 
}

function fetchClientCreditsSinceLastInvoice($clientid) {
	return fetchAssociations(
		"SELECT tblcredit.* 
		 FROM tblcredit 
		 LEFT JOIN relinvoicecredit ON creditptr = creditid 
		 WHERE invoiceptr IS NULL AND tblcredit.clientptr = $clientid AND hide = 0
		 ORDER BY issuedate");
}

/*function consumeClientCredits($clientid, $upToAmount) { // called for a new billable
	$credits = getClientCredits($clientid, 1);
	
	foreach($credits as $credit) {
		$spent = min($upToAmount, $credit['amountleft']);
		$totalSpent += $spent;
		$credit['amountused'] += $spent;
		updateTable('tblcredit', array('amountused'=>($credit['amountused'] - $spent)), "creditid = {$credit['creditid']}", 1);
		$upToAmount -= $spent;
		if($upToAmount == 0) return $totalSpent;
	}
	return cXonsumeCredits(getClientCredits($clientid, 1), $upToAmount);
}*/

function payOffClientBillables($client, $startingWithBillables=null, $invoicedFirst=true) {  //called when a credit/payment is created
	$availCredits = getClientCredits($client, 'nonzero');
	$paidBillables = array();
$staffTest = $_SESSION['staffuser'];
if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #10');
//print_r($availCredits);
	// Pay these billables first.
	if($startingWithBillables) {
		if($startingWithBillables['billableid']) $startingWithBillables = array($startingWithBillables);
		foreach($startingWithBillables as $billable) {
			$paid =  consumeCredits($availCredits, $billable['charge'] - $billable['paid'], $billable['billableid']);
	//echo "<p>Up to: ".($billable['charge'] - $billable['paid'])." paid: $paid";
			if($paid) {
				updateTable('tblbillable', array('paid'=>sqlVal("paid + $paid")), "billableid = {$billable['billableid']}");
				$paidBillables[] = $billable['billableid'];
			}
		}
	}
if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #20');
//if($staffTest) $invoicedFirst =  false;
		
	// Then pay any remaining billables while credit lasts.
	$billables = $invoicedFirst ? getCurrentBillablesInvoicedFirst($client) : getCurrentBillables($client);
//$xx=0;foreach($billables as $bb) $xx += $bb['charge']-$bb['paid'];
//echo "Credits: ".count($availCredits)."<br>".print_r($availCredits, 1)."<p>Billables: ".count($billables)."[$xx]<br>".print_r($billables, 1);exit;
if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #30');
	foreach($billables as $billable) {
//echo 'owed: '.($billable['charge'] - $billable['paid']);		
		$paid =  consumeCredits($availCredits, $billable['charge'] - $billable['paid'], $billable['billableid']);
//echo "  paid: $paid  new balance: ".($billable['charge'] - $billable['paid'])."<br>";
//echo "<p>Up to: ".($billable['charge'] - $billable['paid'])." paid: $paid";
		if($paid) {
			updateTable('tblbillable', array('paid'=>sqlVal("paid + $paid")), "billableid = {$billable['billableid']}");
			$paidBillables[] = $billable['billableid'];
		}
	}
if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #40');
	// find affected invoices.  Nah.  Check ALL of the client's unpaid invoices.
	if($paidBillables) {
		/*$paidBillables = join(',',$paidBillables);
		$invoiceids = fetchCol0("SELECT invoiceptr 
																	FROM tblbillable 
																	LEFT JOIN relinvoiceitem ON billableptr = billableid
																	WHERE billableid IN ($paidBillables)");
		$invoiceids = array_diff(array_unique($invoiceids), array(null));*/
		$invoiceids = fetchCol0("SELECT invoiceid 
																	FROM tblinvoice 
																	WHERE clientptr = $client AND paidinfull IS NULL");
if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #50');
		
		foreach($invoiceids as $invoiceid) checkInvoicePaid($invoiceid);
	}
if($staffTest) logChange(9999, 'payOffClientBillables', 'f', 'check #60');
//exit;	
}

function consumeCredits(&$credits, $upToAmount, $billableptr) { // called repeatedly after a new credit is added
	if($upToAmount == 0)  return 0;
	$totalSpent = 0;
	foreach((array)$credits as $index => $credit) {
		if($credits[$index]['amountleft'] <= 0) continue;
		$spent = min($upToAmount, $credits[$index]['amountleft']);
		$totalSpent += $spent;
		$credits[$index]['amountused'] += $spent;
		$credits[$index]['amountleft'] -= $spent;
		$mods = array('amountused'=>$credits[$index]['amountused']);
		updateTable('tblcredit', addModificationFields($mods), "creditid = {$credit['creditid']}", 1);
		$upToAmount -= $spent;
		// NEED TO ENSURE that paymentptr-billableptr is updated rather than created if it already exists
		// in some cases, a payment (A) may be applied to complete payment on a partially-paid billable
		// and not be completely consumed.  if another payment applied to the billable is subsequently
		// voided, then payment A may be applied again to the billable.  If so, then the relbillablepayment
		// must be updated instead of creating a new one.

		$bp = fetchFirstAssoc("SELECT * FROM relbillablepayment WHERE billableptr = $billableptr AND paymentptr = {$credit['creditid']}");
		if($bp) 
			updateTable('relbillablepayment', array('amount'=>$bp['amount'] + $spent), "billableptr = $billableptr AND paymentptr = {$credit['creditid']}", 1);
		else 
			insertTable('relbillablepayment', array('billableptr'=>$billableptr, 'paymentptr'=>$credit['creditid'], 'amount'=>$spent), 1);
		
		if($upToAmount <= 0) return $totalSpent;
	}
	return $totalSpent;
}

function creditListTable($credits, $oneClient=false) {
	if(!$credits) {
		echo "No credits found.";
		return;
	}
	$clientIds = array();
	foreach($credits as $credit) $clientIds[] = $credit['clientptr'];
	$clients = getClientDetails($clientIds);
	$columns = explodePairsLine('issuedate|Date||client|Client||amount|Original Amount||amountused|Amount Used||amountleft|Amount Left||reason|Note');
	$colSorts = $oneClient ? array('date'=>null) : array();
	if($oneClient) {
		unset($columns['client']);
	}
	$colClasses = array('amount'=>'dollaramountcell', 'amountused'=>'dollaramountcell', 'amountleft'=>'dollaramountcell');
	//$headerClass = array('amount'=>'dollaramountheader');
	$rows = array();
	foreach($credits as $credit) {
		$row = array();
		$row['issuedate'] = shortDate(strtotime($credit['issuedate']));
		$row['client'] = fauxLink($clients[$credit['clientptr']]['clientname'], "viewClient({$credit['clientptr']})", 'View this client', 1);
		$row['amount'] = creditLink($credit);
		$row['amountused'] = dollarAmount($credit['amountused'], $cents=true, $nullRepresentation='', $nbsp=' ');
		$row['amountleft'] = dollarAmount($credit['amountleft'], $cents=true, $nullRepresentation='', $nbsp=' ');
		if($credit['voided']) {
			require_once "item-note-fns.php";
			$voidReason = getItemNote('tblcredit', $credit['creditid']);
			$voidReason = $voidReason ? truncatedLabel($voidReason['note'], 25) : '';
			$voidedDate = shortDate(strtotime($credit['voided']));
			$voidedAmount = dollarAmount($credit['voidedamount']);
			$creditPrefix = $credit['payment'] ? '' : '[CR] ';
			$row['reason'] = "<font color=red>{$creditPrefix}VOID ($voidedDate): $voidedAmount ".$voidReason.'</font>';
		}
		else {
			$credittype = ($credit['refundptr'] ? '(refunded) ' : '').($credit['payment'] ? 'PAYMENT' : 'CREDIT');
			$row['reason'] = $credittype.($credit['reason'] ? ': '.$credit['reason'] : '');
		}
		
		$rows[] = $row;
	}
	tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,$colSorts,null, $colClasses, 'sortInvoices');
}

function creditLink($credit) {
	if(userRole() == 'c') return dollarAmount($credit['amount']);
	$payment = $credit['payment'];
	$amountLabel = $credit['amount'];
	$title = "Edit this ".($credit['payment'] ? 'payment' : 'credit');
	if($payment && $credit['amount']) {
		$gratTotal = fetchRow0Col0($sql = "SELECT sum(amount) FROM tblgratuity WHERE paymentptr = {$credit['creditid']}", 1);
		if($gratTotal) 
			{
				$title .= ". Total payment: ".dollarAmount($credit['amount']+$gratTotal)
								." - gratuities: ".dollarAmount($gratTotal)
								." = ". dollarAmount($credit['amount']);
				$amountLabel = dollarAmount($amountLabel)." *";
			}
	}
	if($credit['payment']) { // added payment source hint 7/15/2020
		$source = $credit['sourcereference'];
		foreach(array('CC','ACH') as $esource) {
			$pat = "/^$esource:.+\([A-Z,a-z]+\)$/";
			if(preg_match($pat, $source)) {
				$lastOpenParen = strrpos($source, '(');
				$source = substr($source, $lastOpenParen, strlen($source)-$lastOpenParen);
				$title .= " $source";
			}
		}
		if(!$lastOpenParen /* no CC/ACH match */ && strpos(strtoupper($source), 'PAYPAL') !== FALSE)
				$title .= " (PayPal)";
		//echo "<br><b>$s</b> match [$pat]: ".preg_match($pat, $s);

	}
	return fauxLink(dollarAmount($amountLabel, $cents=true, $nullRepresentation='', $nbsp=' '), "editCredit({$credit['creditid']}, $payment)", 1, $title);
}

function payElectronically($clientid, $paymentSource, $amount, $reason, $sendReceipt=null, $dontApplyPayment=false, $gratuity=0, $noLoginPayment=false) {
	global $greatestCCPayment, $ccDebug, $latestPaymentId;
	require_once("cc-processing-fns.php");
	if(!$paymentSource)  $paymentSource = getPrimaryPaySource($clientid);
	if(!$paymentSource) return array('FAILURE'=>'No primary payment source found for client.');
	if($amount > $greatestCCPayment) return array('FAILURECODE'=>LT_EXCESSIVE_CHARGE);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r( array($amount, $paymentSource));exit;}
//if(mattOnlyTEST()) { //(IPAddressTEST('173.23.162.252'))
//	echo "applyEPayment : ".print_r($paymentSource, 1); exit;	}
	//echo "applyEPayment($clientid, $paymentSource, $amount-$gratuity, $reason, $transactionid, $sendReceipt, $gratuity)";exit;	}
//if(mattOnlyTEST()) {echo "payElectronically amount: $amount<br>gratuity: $gratuity"; exit;}	
	$success = makeEPayment($amount, $paymentSource, $noLoginPayment);
	// ##APLYANDREGISTER## -- SEE NOTE BELOW
	$logChangeTable = $paymentSource['adhoc'] ? 'ccpaymentadhoc' : ($paymentSource['ccid'] ? 'ccpayment' : 'achpayment');
	$epayid = $paymentSource['acctid'] ? $paymentSource['acctid'] : $paymentSource['ccid'];
	if(is_array($success)) { // failure
		if($ccDebug) print_r($success);
		//logError(print_r($success,1));
		$errorMessage = $success['FAILURE'] ? $success['FAILURE'] : ccErrorLogMessage($success, $amount);
		logChange($epayid, $logChangeTable, 'f', $errorMessage);
		return $success;
	}
	$gateway = 	merchantInfoSupplied();
	$gateway = 	$gateway['ccGateway'];
	logChange($epayid, $logChangeTable, 'p', "Approved-$amount|$success|Gate:$gateway");
	$transactionid = $success;

	saveEPayment($clientid, $paymentSource, $amount, $reason, $transactionid, $sendReceipt, $gratuity, $payOffBillables=!$dontApplyPayment);

	//if(!$dontApplyPayment) applyEPayment($clientid, $paymentSource, $amount-$gratuity, $reason, $transactionid, $sendReceipt, $gratuity);
	return $transactionid;
}

function applyAndRegisterEPaymentAttempt($success, $clientid, $paymentSource, $amount, $reason, $sendReceipt=null, $dontApplyPayment=false, $gratuity=0, $noLoginPayment=false) {
	// The call to applyEPayment sets the global $latestPaymentId
	// ONCE THIS METHOD IS TESTED, SUBSTITUTE IN  A CALL TO IT AT ##APLYANDREGISTER##, ABOVE
	$logChangeTable = $paymentSource['adhoc'] ? 'ccpaymentadhoc' : ($paymentSource['ccid'] ? 'ccpayment' : 'achpayment');
	$epayid = $paymentSource['acctid'] ? $paymentSource['acctid'] : $paymentSource['ccid'];
	if(is_array($success)) { // failure
		if($ccDebug) print_r($success);
		//logError(print_r($success,1));
		$errorMessage = $success['FAILURE'] ? $success['FAILURE'] : ccErrorLogMessage($success, $amount);
		logChange($epayid, $logChangeTable, 'f', $errorMessage);
		return $success;
	}
	$gateway = 	merchantInfoSupplied();
	$gateway = 	$gateway['ccGateway'];
	logChange($epayid, $logChangeTable, 'p', "Approved-$amount|$success|Gate:$gateway");
	$transactionid = $success;
	if(!$dontApplyPayment) 
		applyEPayment($clientid, $paymentSource, $amount/*-$gratuity*/, $reason, $transactionid, $sendReceipt, $gratuity);
	return $transactionid;
}

function applyEPayment($clientid, $paymentSource, $amount, $reason, $transactionid, $sendReceipt=null, $gratuity=0) {
	return saveEPayment($clientid, $paymentSource, $amount, $reason, $transactionid, $sendReceipt, $gratuity, $payOffBillables=true);
}

function saveEPayment($clientid, $paymentSource, $amount, $reason, $transactionid, $sendReceipt=null, $gratuity=0, $payOffBillables=true) {
	global $greatestCCPayment, $ccDebug, $latestPaymentId;
	require_once("cc-processing-fns.php");
	require_once "preference-fns.php";
	if($_SESSION['preferences']['ccGateway'] == 'TestCCGateway') $reason .= ' [CCTest]';
	$paymentSourcePrefix = $paymentSource['acctid'] ? 'ACH:' : 'CC:';
	$paymentSourceDesc = $paymentSource['acctid'] ? $paymentSource['acctnum'] : "{$paymentSource['last4']} ({$paymentSource['company']})";
	$amount = $amount ? $amount : 0;  // avoid NUL not allowed when payment is all gratuity
	$creditAmount = $amount - $gratuity;
	$creditAmount = $creditAmount ? $creditAmount : "0";
	$gratuity = $gratuity ? $gratuity : 0;
	$credit = array('payment'=>1,'externalreference'=>"$paymentSourcePrefix $transactionid", 
											'sourcereference'=>"$paymentSourcePrefix $paymentSourceDesc", 'reason'=>$reason, 
											'amount'=>$creditAmount, 'clientptr'=>$clientid, 'issuedate'=>date('Y-m-d H:i:s'));
	$creditid = insertTable('tblcredit', addCreationFields($credit), 1);
	$latestPaymentId = $creditid;
	require_once("invoice-fns.php"); // for getCurrentBillablesInvoicedFirst()
	if($payOffBillables) payOffClientBillables($clientid);
	logChange($creditid, 'tblcredit', 'c', $clientid);
	
	if(!$_SESSION["preferences"]) $_SESSION["preferences"] = fetchPreferences();  // in case of default in the absence of a login (Pay Now)
	if($sendReceipt === null) { // override only if 1 or 0
		$sendReceipt = getClientPreference($clientid, 'autoEmailCreditReceipts');
	}
	
	if($sendReceipt) {
		$paymentSourceType = $paymentSource['acctid'] ? 'bank account' : 'credit card';
		if($paymentSource['acctnum']) {
			$maskedAccountNum = $paymentSource['acctnum'];
			$stars = strlen($maskedAccountNum) > 4 ? -4 : strlen($maskedAccountNum)-2;
			$maskedAccountNum = str_pad(substr($maskedAccountNum, $stars), strlen($maskedAccountNum), "*", STR_PAD_LEFT);
			$paymentSourceDesc = $maskedAccountNum;
		}
		else {
			$paymentSourceDesc = $paymentSource['last4'] 
				? "{$paymentSource['company']} ************{$paymentSource['last4']}"
				: "{$paymentSource['company']}";
		}
		require_once "client-fns.php";
		
		//$client = getOneClientsDetails($clientid, array('email'));
		$client = getClient($clientid);
		$client['clientname'] = "{$client['fname']} {$client['lname']}";
		if($client['email']) {
			require_once "comm-fns.php";
			if($gratuity) 
				$gratuityInclusion = 
					", which includes the ".dollarAmount($gratuity, $cents=true, $nullRepresentation='', $nbsp=' ')
					." gratuity";
			$subjectLine = "Charge to your $paymentSourceType";
			$message = "Dear {$client['clientname']},<p>This note is to inform you that we have charged your $paymentSourceType "
									. "($paymentSourceDesc) in the amount of ".dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ')
									. "$gratuityInclusion.  (Transaction #$transactionid)<p>Thank you for your business.<p>Sincerely,<p>{$_SESSION['preferences']['bizName']}";
			$clientOriginated = !in_array(userRole(), array('o', 'd'));
			if($clientOriginated/* && ((mattOnlyTEST() && dbTEST('leashtimecustomers')) || dbTEST('tonkapetsitters'))*/ ) {
				$subjectLine = "Thanks for your payment!";
				$message = "Dear {$client['clientname']},<p>This note is to thank you for your $paymentSourceType payment "
										. "($paymentSourceDesc) in the amount of ".dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ')
										. "$gratuityInclusion.  (Transaction #$transactionid)<p>Thank you for your business.<p>Sincerely,<p>{$_SESSION['preferences']['bizName']}";
			}
			
			//TEST
			if(dbTEST('leashtimecustomers') || $_SESSION['preferences']['enableChargeEmailTemplates'])
			$subjectAndBody = ePaymentMessageSubjectAndBody($client, $clientOriginated, $paymentSourceType, $paymentSourceDesc, $amount, $gratuity, $transactionid);
			if($subjectAndBody) {
				$subjectLine = $subjectAndBody['subject'];
				$message = $subjectAndBody['body'];
			}
			
			$mgrname = getUsersFromName();
			enqueueEmailNotification($client, $subjectLine, $message, $cc=null, $mgrname, $hasHtml=true);
//if(mattOnlyTEST()) {echo "sendReceipt: [$sendReceipt] ".print_r($client, 1);exit;}									
		}
	}
	return $transactionid;
}

function ePaymentMessageSubjectAndBody($client, $clientOriginated, $paymentSourceType, $paymentSourceDesc, $amount, $gratuity, $transactionid) {
	require_once "email-template-fns.php"; // JUST UNTIL
	ensureStandardTemplates('others'); // THE TEMPLATES ARE UNIVERSALLY INSTALLED
	
	$templateLabel = $clientOriginated ? '#STANDARD - Thanks for your Credit Card/Bank Account Payment' 
										: '#STANDARD - Credit Card/Bank Account Charged';
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$templateLabel' LIMIT 1", 1);
	if(!$template) return;
	require_once "gui-fns.php";
	require_once "comm-fns.php";
	require_once "comm-composer-fns.php";
	
	$result['subject'] = str_replace('#PAYMENTTYPE#', $paymentSourceType, $template['subject']);

	$totalDollars = dollarAmount($amount, $cents=true, $nullRepresentation='', $nbsp=' ');
	if($gratuity) 
		$gratuityInclusion = 
			", which includes the ".dollarAmount($gratuity, $cents=true, $nullRepresentation='', $nbsp=' ')
			." gratuity";
	$result['body'] = preprocessMessage($template['body'], $client);
	$result['body'] = str_replace('#PAYMENTTYPE#', $paymentSourceType, $result['body']);
	$result['body'] = str_replace('#PAYMENTSOURCE#', $paymentSourceDesc, $result['body']);
	$result['body'] = str_replace('#PAYMENTAMOUNT#', $totalDollars, $result['body']);
	$result['body'] = str_replace('#GRATUITY#', $gratuityInclusion, $result['body']);
	$result['body'] = str_replace('#TRANSACTIONID#', $transactionid, $result['body']);
	return $result;
}

function paymentAllocation($paymentptr) {
	$payment = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $paymentptr LIMIT 1", 1);
	$billables = fetchAssociations(
		"SELECT b.*, r.amount as applied
			FROM relbillablepayment r
			LEFT JOIN tblbillable b ON billableid = billableptr
			WHERE paymentptr = $paymentptr", 1);
	foreach($billables as $b) {
		if(!$b['charge']) continue;
		$percentPaid = $b['applied'] / $b['charge'];
		$taxPercentage = ($b['tax'] ? $b['tax'] : 0) / $b['charge'];
		$revPercentage = 1 - $taxPercentage;
		$payment['total'] += $b['applied'];
		$payment['revenue'] += $b['applied'] * $revPercentage;
		$payment['tax'] += $b['applied'] * $taxPercentage;
		$payment['items'] += 1;
//if(mattOnlyTEST() && $b['clientptr'] == 818) echo "[{$payment['items']}] ".print_r($b, 1)."<hr>";
	}
	//echo print_r($payment,1).'<br>';
	return $payment;
}
