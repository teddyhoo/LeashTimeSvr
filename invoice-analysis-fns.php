<? // invoice-analysis-fns.php

function invoiceStats($invoiceid) {
	$invoice = fetchFirstAssoc("SELECT * FROM tblinvoice WHERE invoiceid = $invoiceid LIMIT 1");
	$subtotal = $invoice['subtotal'];
	$discountamount = $invoice['discountamount'];
	$billables = fetchAssociations(
		"SELECT tblbillable.* 
			FROM relinvoiceitem
			LEFT JOIN tblbillable ON billableid = billableptr
			WHERE relinvoiceitem.invoiceptr = $invoiceid");
	foreach($billables as $b) {
		if($b['itemtable'] == 'tblappointment') {
			$apptid = $b['itemptr'];
			$apptids[] = $apptid;
			$calcSubtotal += fetchRow0Col0(
					"SELECT charge+ifnull(adjustment,0) 
						FROM tblappointment 
						WHERE appointmentid = $apptid LIMIT 1");
		}
		else if($b['itemtable'] == 'tblsurcharge') 
			$calcSubtotal += fetchRow0Col0(
					"SELECT charge 
						FROM tblsurcharge 
						WHERE surchargeid = {$b['itemptr']} LIMIT 1");
		else if($b['itemtable'] == 'tblothercharge') 
			$calcSubtotal += fetchRow0Col0(
					"SELECT amount 
						FROM tblothercharge 
						WHERE chargeid = {$b['itemptr']} LIMIT 1");
		else if($b['itemtable'] == 'tblrecurringpackage') 
			$calcSubtotal += fetchRow0Col0(
					"SELECT totalprice 
						FROM tblrecurringpackage 
						WHERE packageid = {$b['itemptr']} LIMIT 1");
		$calculatedCreditsApplied += $b['paid'];
	}
	
	$itemDescriptions = fetchAssociations("SELECT description, prepaidamount FROM relinvoiceitem 
																		WHERE relinvoiceitem.invoiceptr = $invoiceid");
	foreach($itemDescriptions as $descr) {
		$totalPrepaidAmount += $descr['prepaidamount'];
		if($descr['description']) {
			$descr = getInvoiceItemDescription($descr['description']);
			$savedCharges += $descr['charge'];
			$invoice['descriptionsUsed'] = 'x';
		}
	}
	$invoice['descriptionSubtotal'] = $savedCharges;

	
	$invoice['calculatedSubTotal'] = $calcSubtotal;
	$invoice['calculatedCreditsApplied'] = $calculatedCreditsApplied;
	$invoice['totalPrepaidAmount'] = $totalPrepaidAmount;
	return $invoice;
	//$a = (int)(100*$calcSubtotal+100*$discountamount) / 100;
	//$b = (int)(100*$subtotal) / 100;
	//if(abs($a-$b) >= 2) echo "<font color=gray> $invoiceid: $a = $b</font> <br>";		//[".print_r($apptids, 1)."]
	//return $a == $b ? null : array($a, $b);
}

function getLatestInvoiceIds() {
	return fetchKeyValuePairs("SELECT clientptr, invoiceid FROM tblinvoice ORDER BY invoiceid");
}

