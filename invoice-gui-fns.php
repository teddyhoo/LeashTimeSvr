<?// invoice-gui-fns.php

require_once "invoice-fns.php";

// INVOICE DISPLAY FUNCTIONS

function invoiceListTable($invoices, $throughDateInt, $oneClient=false, $list_prefix='invoice', $uninvoicedCharges=null, $checkEmail=null, $showPreviewEmailAttemptNote=false) {
//if(staffOnlyTEST()) print_r($invoices);	
	global $incompleteJobCounts, $showLTCustomerLogins;
	if($showLTCustomerLogins) $bizIds = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient");
$tableTime = microtime(1);
	if(!$invoices) {
		echo "No invoices found.";
		return;
	}
	if(!$incompleteJobCounts) $incompleteJobCounts = countAllIncompleteJobsByClient(date('Y-m-d', $throughDateInt));
	$clientIds = array();
	foreach($invoices as $invoice) $clientIds[] = $invoice['clientptr'];
	$clients = getClientDetails($clientIds, array('email'));
	$columns = $oneClient ? '' : 'cb| ||';
	//$columns = explodePairsLine($columns.'invoiceid|Invoice #||client|Client||asofdate|Through Date||subtotal|Subtotal||amount|Balance Due||notification|Notification');
	$columns = explodePairsLine($columns.'client|Client||acctBal|Acct Bal||invoiceid|Invoice #||date|Invoice Date||asofdate|Through Date||subtotal|Curr Inv||bottomline|Amount Due||uninvoiced|Uninvoiced');
	$colSorts = $oneClient ? array('date'=>null) : array();
	if($oneClient) {
		unset($columns['client']);
		unset($columns['acctBal']);
		unset($columns['uninvoiced']);
		$columns['status'] = 'Status';
	}
	else {
		unset($columns['date']);
	}
	$colClasses = array('bottomline'=>'dollaramountcell', 'acctBal'=>'dollaramountcell', 'subtotal'=>'dollaramountcell', 'uninvoiced'=>'dollaramountcell');
	//$headerClass = array('amount'=>'dollaramountheader');
	$rows = array();
$uTime = microtime(1);
	$uninvoicedCharges = getUninvoicedCharges(null, date('Y-m-d', $throughDateInt));
//screenLog("Time to getUninvoicedCharges (invoiceListTable): ".round((microtime(1)-$uTime)*1000)." ms");
$abTime = 0;	
//print_r($uninvoicedCharges);	
	foreach($invoices as $invoice) {
		$row = array('#ROW_EXTRAS#'=>"id='clientrow_{$invoice['clientptr']}_{$invoice['invoiceid']}'");
		$uninvoiced = $uninvoicedCharges ? $uninvoicedCharges[$invoice['clientptr']] : 0;
		//$row['cb'] = $invoice['invoiceid'] ? "<input type='checkbox' id='$list_prefix"."_{$invoice['invoiceid']}'>" : '&nbsp';
		$row['cb'] = !$uninvoiced ? '' : 
			(!$checkEmail || $clients[$invoice['clientptr']]['email']
				? "<input type='checkbox' id='$list_prefix"."_{$invoice['clientptr']}'>"
				: "<span style='color:red;font-size:0.8em;'>No<br>Email</span>");
		//$row['date'] = $invoice['date'] ? shortDate(strtotime($invoice['date'])) : "Preview";
		//getAccountBalance($client, $includeCredits=false, $allBillables=false)
$uTime = microtime(1);
		$acctBal = getAccountBalance($invoice['clientptr'], true, false);
$abTime += microtime(1)-$uTime;
//if($invoice['clientptr'] ==71){echo "All bills: ".getAllBillableCharges($invoice['clientptr'], 1);	exit;}
//if($invoice['clientptr'] ==8){echo "BILBALANCE: $acctBal";	exit;}
		$uninvoicedStyle = $incompleteJobCounts[$invoice['clientptr']] ? 'background:yellow' : '';
		//function fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {

		$row['uninvoiced'] = ($uninvoiced > 0 || $incompleteJobCounts[$invoice['clientptr']])
			? fauxLink(dollarAmount($uninvoiced), "editInvoice({$invoice['clientptr']})", 1, 'Review and send a new invoice', null, null, $uninvoicedStyle)
			: dollarAmount(0.0);
		$acctBal = $acctBal > 0 ? dollars($acctBal) : ($acctBal < 0 ? dollars(abs($acctBal)).'cr' : 'PAID'); 
		$row['acctBal'] = fauxLink($acctBal, "viewRecent({$invoice['clientptr']})", 1, 'Review recent invoices');
		$email = $clients[$invoice['clientptr']]['email'];
		if($invoice['invoiceid']) 
			$row['invoiceid'] = invoiceLink($invoice, $email);
		else {
			$row['invoiceid'] = '&nbsp';
		}
		//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null)
		
		
		$clientNameLabel = $clients[$invoice['clientptr']]['clientname'];
		$row['client'] = fauxLink($clientNameLabel, "viewClient({$invoice['clientptr']})", 1, 'View this client');
		if($showLTCustomerLogins) $row['client'] .= ' '.fauxLink('@#!$$', "showLogins({$bizIds[$invoice['clientptr']]})", 1, 'Show Last 30 days logins');
		if($showPreviewEmailAttemptNote)
			$row['client'] = invoicePreviewEmailAttemptNote($invoice['clientptr']).$row['client'];
		$row['subtotal'] = $invoice['invoiceid'] ? dollarAmount($invoice['subtotal']) : '-';
		// Since we are showing past due invoices on the main invoices page, we will show only specific invoice balance due rather than account balance
		$row['amount'] = dollarAmount($invoice['balancedue']);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' && $invoice['clientptr'] == 804) { screenLog(print_r($invoice,1)); }
		$bottomline = max(0.0, $invoice['balancedue'] -  ($invoice['ccpayment'] ? $invoice['ccpayment'] : 0)); // max is a kludge
		$row['bottomline'] = dollarAmount($bottomline);
		$row['asofdate'] = $invoice['asofdate'] ? shortDate(strtotime($invoice['asofdate'])) : '';
		$row['date'] = $invoice['date'] ? shortDate(strtotime($invoice['date'])) : '';
		$row['status'] = $invoice['paidinfull'] ? "PAID: ".shortDate(strtotime($invoice['paidinfull'])) : pastDue($invoice);
		$row['notification'] = $invoice['notification'] ? $invoice['notification'].' '.shortDate(strtotime($invoice['lastsent'])) : '';
		$rowClass = $rowClass != 'futuretask' ? 'futuretask' : 'futuretaskEVEN';
		$rowClasses[] = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
		//$rowClasses[] = 'futuretask';
		$rows[] = $row;
	}
	//tableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses)
	$tableWidth = $oneClient ? '90%' : '100%';
	tableFrom($columns, $rows, "WIDTH=$tableWidth",null,null,null,null,$colSorts,$rowClasses, $colClasses, 'sortInvoices');
//screenLog("Time to getAccountBalance (invoiceListTable): ".round($abTime*1000)." ms");
//screenLog("Time to build invoiceListTable: ".round((microtime(1)-$tableTime)*1000)." ms");
}

function pastDue($invoice) {
	$pastDueDays = $_SESSION['preferences']['pastDueDays'];
	$pastDueDays = strlen(''.$pastDueDays) == '0' ? 30 : $pastDueDays;
	$oneday = 24 * 3600;
	$diff = (strtotime('today') - strtotime($invoice['date'])) / $oneday;
	$status = 'UNPAID';
	if($diff >= $pastDueDays + 15 && $diff <= $pastDueDays + 29) $status = "15 days overdue";
	else if($diff >= $pastDueDays + 30 && $diff <= $pastDueDays + 44) $status = "30 days overdue";
	else if($diff >= $pastDueDays + 45) $status = "45+ days overdue";
	return "<font color=red>$status</font>";
}
	

function invoiceLink($invoice, $email=null, $noPayLink=false) {
	return fauxLink(invoiceIdDisplay($invoice['invoiceid']), "viewInvoice({$invoice['invoiceid']}, \"$email\")", 1, 'View this invoice').
		($noPayLink || $invoice['paidinfull'] 
			? '' 
			: ($_SESSION['clientid']  ? '' : ' '.fauxLink('(Pay)', "payInvoice({$invoice['invoiceid']})", 1, 'Pay this invoice')));
}	

function displayLatestInvoice($clientid) {
	$invoiceid = fetchRow0Col0("SELECT invoiceid FROM tblinvoice WHERE clientptr = $clientid ORDER BY invoiceid DESC LIMIT 1");
	displayInvoice($invoiceid);
}

function getInvoiceContents($invoiceid) {
	ob_start();
	ob_implicit_flush(0);
	displayInvoice($invoiceid);
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}	

// InvoicePreviewEmailFunctions
function preProcessInvoicePreviewEmails() {
	// find all queued emails with embedded InvoicePreview tokens
	global $invoicePreviewEmails;
  if(!($result = doQuery("SELECT emailid, body FROM tblqueuedemail"))) return null;
  while($row = mysql_fetch_row($result)) {
    if(strpos($row[1], '_InvoicePreview_'))
		if($start = strpos($row[1], '_InvoicePreview_')) {
			$start += strlen('_InvoicePreview_');
			$end = strpos($row[1], '<', $start);
			$id = substr($row[1], $start, $end-$start);
			$invoicePreviewEmails[$row[0]] = $id;
		}
	}
}

function postProcessInvoicePreviewEmails() {
	// record InvoicePreview emailing attempts
	global $invoicePreviewEmails;
	$attempt = array('attempted'=>date('Y-m-d H:i:s'));
	foreach((array)$invoicePreviewEmails as $emailid => $previewid) {
		$attempt['failed'] = fetchRow0Col0("SELECT 1 FROM tblqueuedemail WHERE emailid = $emailid LIMIT 1");
		updateTable('tblemailedinvoicepreview', $attempt, "previewid = $previewid", 1);
	}
}

function invoicePreviewEmailAttemptNote($clientid) {
	$attempt = fetchFirstAssoc("SELECT * FROM tblemailedinvoicepreview WHERE clientptr = $clientid LIMIT 1");
	if(!$attempt) return null;
	if($attempt['failed']) {
		$style = 'style="color:red"';
		$title = $attempt['email'] ? "Could not send invoice preview to {$attempt['email']}" : "No preview send because no email address found for client";
	}
	else if(!$attempt['attempted']) $title = "Invoice preview will be sent shortly.";
	else $title = "Invoice preview sent.";
	if($attempt['attempted']) $title = safeValue(shortDate(strtotime($attempt['attempted'])).": $title");
	return "<span $style title=\"$title\">[P]</span> ";
}
	
function clearInvoicePreviewEmailAttempt($clientid) {
	deleteTable('tblemailedinvoicepreview', "clientptr = $clientid", 1);
}

function getInvoicePreviewContents($clientid, $asOfDate, $previewId) {
	ob_start();
	ob_implicit_flush(0);
	
	global $canceledAppointments;
	$invoice = createInvoicePreview($clientid, null, true, $asOfDate);
	echo "<span style='display:none'>_InvoicePreview_$previewId</span>";
	
	//$canceledAppointments = getCanceledVisitsForInvoice($invoiceid, $invoice['clientptr']);

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
	$client = getClient($invoice['clientptr']);
	dumpReturnSlip($invoice, $client);
	echo "<p align=center><b>INVOICE</b><p>";
	dumpAccountSummary($invoice, $client); // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<p>";
	dumpCurrentCredits($clientid);
	dumpCurrentCharges($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	dumpCurrentPastInvoiceSummaries($invoiceid); // Invoice #, Invoice Date, Items, Subtotal
	dumpMessage($invoice);  // should we add a message field to invoice?
	dumpFooter();
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}	

// END InvoicePreviewEmailFunctions

function displayInvoice($invoiceOrInvoiceid, $firstInvoicePrinted=true, $emailablePreview=false) {
	global $canceledAppointments;
	if(is_array($invoiceOrInvoiceid)) {
		$invoice = $invoiceOrInvoiceid;
		$invoiceid = $invoice['invoiceid'];
	}
	else {
		$invoice = getInvoice($invoiceOrInvoiceid);
		$invoiceid = $invoiceOrInvoiceid;
	}
	
	$canceledAppointments = getCanceledVisitsForInvoice($invoiceid, $invoice['clientptr']);

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
	$client = getClient($invoice['clientptr']);
	dumpReturnSlip($invoice, $client);
	echo "<p align=center><b>INVOICE</b><p>";
	dumpAccountSummary($invoice, $client); // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<p>";
	dumpInvoiceCredits($invoice['invoiceid']);//($client['clientid']);
	dumpCurrentCharges($invoice, $client['clientid']); // Invoice #, Invoice Date, Items, Subtotal
	dumpCurrentPastInvoiceSummaries($invoiceid); // Invoice #, Invoice Date, Items, Subtotal
	dumpMessage($invoice);  // should we add a message field to invoice?
	dumpFooter();
}

function dumpCurrentCredits($clientid) {
	echo "<div style='width:95%'>\n";
dumpSectionBar("Payments, Credits and Refunds since last invoice", '');
//else	dumpSectionBar("Payments and Credits since last invoice", '');
	$credits = fetchAssociations(
		"SELECT tblcredit.* 
		 FROM tblcredit LEFT JOIN relinvoicecredit ON creditptr = creditid 
		 WHERE invoiceptr IS NULL AND tblcredit.clientptr = $clientid AND bookkeeping <> 1 AND hide = 0
		 ORDER BY issuedate");
	$refunds = fetchAssociations(
		"SELECT tblrefund.*, tblcredit.issuedate as paymentdate, tblcredit.amount as paymentamount, creditid
		 FROM tblrefund 
		 LEFT JOIN relinvoicerefund ON refundptr = refundid 
		 LEFT JOIN tblcredit ON creditid = paymentptr 
		 WHERE invoiceptr IS NULL AND tblrefund.clientptr = $clientid
		 ORDER BY issuedate");
	dumpInvoiceCreditTable($credits, $refunds);
	echo "</div>";
}

function dumpInvoiceCredits($invoiceid) {
	echo "<div style='width:95%'>\n";
dumpSectionBar("Payments, Credits and Refunds since last invoice", '');
//else	dumpSectionBar("Payments and Credits since last invoice", '');
	$credits = fetchAssociations(
		"SELECT tblcredit.* FROM tblcredit LEFT JOIN relinvoicecredit ON creditptr = creditid 
		 WHERE invoiceptr = $invoiceid
		 ORDER BY issuedate");
	$refunds = fetchAssociations(
		"SELECT tblrefund.*, tblcredit.issuedate as paymentdate, tblcredit.amount as paymentamount, creditid 
		 FROM tblrefund 
		 LEFT JOIN relinvoicerefund ON refundptr = refundid 
		 LEFT JOIN tblcredit ON creditid = paymentptr 
		 WHERE invoiceptr = $invoiceid
		 ORDER BY issuedate");
	dumpInvoiceCreditTable($credits, $refunds);
	echo "</div>";
}

function dumpInvoiceCreditTable($credits, $refunds) {
	if(!$credits && !$refunds) {
			echo "No payments, credits or refunds since last invoice.<p>";
			return;
	}
	echo "<style>.leftheader {font-size: 1.05em; padding-bottom: 5px; border-collapse: collapse; text-align: left;}</style>";
	
	foreach($credits as $credit) {
		
		if($credit['voided']) {
			require_once "item-note-fns.php";
			$voidReason = getItemNote('tblcredit', $credit['creditid']);
			$voidReason = $voidReason ? truncatedLabel($voidReason['note'], 25) : '';
			$voidedDate = shortDate(strtotime($credit['voided']));
			$reason = "<font color=red>VOID ($voidedDate): \${$credit['voidedamount']} ".$voidReason.'</font>';
		}
		else $reason = $credit['reason'];
		
		
		
		
		$lineItems[] = array('date'=>shortDate(strtotime($credit['issuedate'])), 'type'=> ($credit['payment'] ? 'payment' : 'credit'),
										'reason' => $reason, 'amount'=>dollars($credit['amount']));
	}
										
										
	//if($_SESSION['staffuser']) 
	foreach((array) $refunds as $refund) {
		$reason = $refund['reason'];
		if($refund['paymentdate']) $reason = "(of payment #{$refund['creditid']}: ".shortDate(strtotime($refund['paymentdate']))." ".dollarAmount($refund['paymentamount']).") $reason";
		$lineItems[] = array('date'=>shortDate(strtotime($refund['issuedate'])), 'type'=> 'refund',
										'reason' => $reason, 'amount'=>"(".dollars($refund['amount']).")");
	}
	uasort($lineItems, 'orderByIssueDate');

	$columns = explodePairsLine('date|Date||amount|Amount||type|Type||reason|Reason');
	//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	tableFrom($columns, $lineItems, "WIDTH=90%", null, 'leftheader', null, 'sortableListCell', null, array('amount'=>'dollaramountcell'));
	echo '<p>';
}

function orderByIssueDate($a, $b) {
	return strcmp($a['issuedate'], $b['issuedate']);
}



function editClientInvoice($clientid, $asOfDate) {
	// This may be called in a SESSION or outside of it (cronjob)
	echo <<<STYLE
  <link rel="stylesheet" href="style.css" type="text/css" /> 
  <link rel="stylesheet" href="pet.css" type="text/css" /> 
	
	<style>
	.right {text-align:right;}
	.bigger-right {font-size:1.1em;text-align:right;}
	.bigger-left {font-size:1.1em;text-align:left;}
	</style>
	<!-- body 'style=font-size:12px;padding:10px;' -->
STYLE;
	$invoice = createInvoicePreview($clientid, null, true, $asOfDate);
	//$previousInvoices = getPriorUnpaidInvoices($invoice);
	$client = getClient($clientid);
	echo "<p align=center><b>INVOICE PREVIEW</b><p>";
	dumpAccountSummary($invoice, $client); // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<p>";
	dumpCurrentCredits($clientid);
	hiddenElement('pastbalancedue', $invoice['pastbalancedue']);
	editCurrentCharges($invoice, $asOfDate); // Invoice #, Invoice Date, Items, Subtotal
	dumpCurrentPastInvoiceSummaries($invoiceid); // Invoice #, Invoice Date, Items, Subtotal
	dumpMessage($invoice);  // should we add a message field to invoice?
	dumpFooter();
}

function dumpReturnSlip($invoice, $client) {
	$savedInvoice = $invoice['invoiceid'];
	echo "<table width='95%' border=0 bordercolor=red>";
	echo "<tr><td>";
	$amountDue = $invoice['origbalancedue'];
	if($savedInvoice) dumpBusinessLogoDiv($amountDue, null, false, $client['clientid']);
	else dumpBusinessLogoDiv($amountDue, emailedInvoicePreviewLogoDivContents(), true, $client['clientid']);
	echo "</td><td align=right>";
	dumpInvoiceHeader($invoice, $client); // customer #, customer invoice #, invoice date, Amount Due
	echo "</td></tr>";
	echo "<tr><td>";
	dumpClientAddress($client); // mailing address or home address if no mailing address
	echo "</td><td align=right>";
	if($savedInvoice) dumpInvoiceBarcode(invoiceIdDisplay($savedInvoice));
	echo "</td></tr></table>";
	if(!$_SESSION['preferences']['suppressDetachHereLine'] && $savedInvoice) echo "<p align=center>Please detach here and return with payment.<p><hr>";
}

function dumpInvoiceBarcode($invoiceDisplayId) {
	global $mein_host;
	$this_dir = $mein_host.substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	echo "<img src='$this_dir/barcode/image.php?code=$invoiceDisplayId&style=196&type=C128A&width=120&height=60&xres=1&font=5'>";
}	

function dumpAccountSummary($invoice, $client) {  // Customer #, Address, Prev Balance, Payments/Credits, Other Charges/Invoices, This Invoice Total Acct Balance due
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	dumpSectionBar('Account Summary', "Customer Number: {$client['clientid']}");
	echo "</td>";
	echo "<tr><td style='text-align:left;vertical-align:top;'>";
	dumpClientAddress($client);
	echo "</td><td align=right>";
	dumpBalances($invoice);
	echo "</td></tr>";
	echo "</table>";
	
}

function dumpCurrentCharges($invoice, $clientid) { // Invoice #, Invoice Date, Items, Subtotal
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	$invoiceid = $invoice['invoiceid'];	
	$asofdate = shortDate(strtotime($invoice['asofdate']));
	$invoiceNumberDisplay = $invoiceid ? "Invoice Number: ".invoiceIdDisplay($invoiceid) : '';
	dumpSectionBar("Current Invoice Charges as of: $asofdate", $invoiceNumberDisplay);
	echo "</td></tr><tr><td>";
	$lineItems = $invoiceid ? getInvoiceLineItems($invoiceid, $clientid) : getPreviewLineItems($invoice['clientptr'], $asofdate);
//if($_SESSION['staffuser']) echo $clientid.'<p>';	
//if($_SESSION['staffuser']) foreach($lineItems as $lineItem) echo print_r($lineItem,1).'<p>';	
	if(!$lineItems) {
		echo "<center>No Current Charges Found.</center></td></tr></table>";
		return;
	}

	$finalLineItems = array();
	$columns = explodePairsLine('date|Date||timeofday|Time of Day||service|Service||provider|Sitter||charge|Charge');
	if($_SESSION['preferences']['suppressInvoiceTimeOfDay']) unset($columns['timeofday']);
	if($_SESSION['preferences']['suppressInvoiceSitterName']) unset($columns['provider']);
	$appointmentsStarted = $lineItems && isset($lineItems[0]['servicecode']);
	$headerLine = "<tr><th class='sortableListHeader'>".join("</th><th class='sortableListHeader'>", $columns)."</th></tr>";
	$dateWidth = $_SESSION['preferences']['suppressInvoiceTimeOfDay'] ? 1 : 2;
	$descWidth = $_SESSION['preferences']['suppressInvoiceSitterName'] ? 1 : 2;
	if(!$appointmentsStarted) {
		if($columns['timeofday']) $columns['timeofday'] = '&nbsp;';
		if($columns['provider']) $columns['provider'] = '&nbsp;';
	}
	//Date	Time of Day	Service	Sitter	Charge
	$numCols = count($columns);
	
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	$rowClasses = array();
	$prepaidAmount = 0;
	foreach($lineItems as $index => $lineItem) {
//echo print_r($lineItem,1)."<br>";
		//$subtotal += 0+substr($lineItem['charge'], 2);
		// yuck.  this needs to be cleaned up.
		$realCharge = floatFromDollars($lineItem['charge']);
		$subtotal += 0+$realCharge;
		$lineItem['charge'] = dollarAmount($realCharge);
		
		//$yuck = $lineItem['charge'];
		//if(strpos($yuck, '$ ') !== FALSE) $yuck = 0+substr($lineItem['charge'], 2);
		//else $lineItem['charge'] = dollarAmount($yuck);
		//$subtotal += $yuck;
		
		if($lineItem['amountpaid']) {
			//$lineItem['charge'] = "({$lineItem['amountpaid']}) {$lineItem['charge']}";
			$prepaidAmount += floatFromDollars($lineItem['amountpaid']);
		}
		if($lineItem['canceled']) {
			if(!$appointmentsStarted) {
				$appointmentsStarted = true;
				//$line = "<tr><th class='sortableListHeader'>Date</th><th class='sortableListHeader'>Time of Day</th>".
				//					"<th class='sortableListHeader'>Service</th><th class='sortableListHeader'>Sitter</th<th class='sortableListHeader'>Charge</th>";
				$finalLineItems[] = array('#CUSTOM_ROW#'=> $headerLine);
				$rowClasses[] = null;
			}
//$lineItem['service'] = 	"{$lineItem['service']} [{$lineItem['appointmentid']}]";
//if($lineItem['appointmentid'] == 26970) $lineItem['service'] = 	"{$lineItem['service']} ".print_r($lineItem, 1);
			$finalLineItems[] = $lineItem;
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
		}
		else if($lineItem['servicecode'] || $lineItem['surchargecode']) {
			if(!$appointmentsStarted) {
				$appointmentsStarted = true;
				//$line = "<tr><th class='sortableListHeader'>Date</th><th class='sortableListHeader'>Time of Day</th>".
				//					"<th class='sortableListHeader'>Service</th><th class='sortableListHeader'>Sitter</th<th class='sortableListHeader'>Charge</th>";
				$finalLineItems[] = array('#CUSTOM_ROW#'=> $headerLine);
				$rowClasses[] = null;
			}
			$finalLineItems[] = $lineItem;
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';

		}
		else if($lineItem['issuedate']) {  // Misc Charge
			$description = $lineItem['reason'];
			$date = shortDate(strtotime($lineItem['issuedate']));
			$line = "<tr class='$rowClass'><td class='sortableListCell' colspan=$dateWidth>$date</td>".
								"<td class='sortableListCell' colspan=$descWidth>$description</td><td class='right'>{$lineItem['charge']}</td>";
			$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
		}
		else { // package
			$description = isset($lineItem['tblservicepackage']) 
											? ($lineItem['onedaypackage'] ? 'One day' : 'Short term') 
											: (isset($lineItem['tblrecurringpackage']) 
													? ($lineItem['monthly'] ? 'Monthly' : 'Weekly')
													: 'Miscellaneous Charges');
			
			if($description != 'Miscellaneous Charges') $description .= ' '.($lineItem['prepaid'] ? 'prepaid' : 'postpaid');
			if($lineItem['cancellationdate']) $description .= ' Canceled: '.shortNaturalDate(strtotime($lineItem['cancellationdate']));
			$rowClass = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
			$rowClasses[] = $rowClass;
			$line = "<tr class='$rowClass'>";
			$date = isset($lineItem['monthyear']) 
				? $lineItem['monthyear'] 
				: ($lineItem['enddate'] ? $lineItem['startdate']."-".$lineItem['enddate'] : $lineItem['startdate']);
			
			$line .= "<td class='sortableListCell' colspan=$dateWidth>$date</td>".
								"<td class='sortableListCell' style='font-weight:bold' colspan=$descWidth>$description</td><td class='right'>{$lineItem['charge']}</td></tr>";
//print_r($line);exit;
			$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
		}
	}
	
	if($invoice['discountlabel']) {
		$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																						." style='text-align:left;'><b>Discount Applied: </b>{$invoice['discountlabel']}</td><td class='right'>"
																						.dollarAmount($invoice['discountamount'])
																						."</td><tr>");
		$subtotal += $invoice['discountamount'];
	}

	$subtotalDollars = dollars($subtotal);
	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td id='subtotalTD' colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotalDollars</td><tr>");

	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																					." style='text-align:left;'><b>Credits and Payments Applied: </b></td><td class='right'>"
																					.dollarAmount(0-$prepaidAmount)
																					."</td><tr>");
	if($_SESSION['preferences']['includeInvoiceGratuityLine']) {	
		$gratuityLine = "<tr><td colspan=".($numCols-1)
																					." style='text-align:left;vertical-align:top;'>
																					<b>If you would like to add a gratuity, please let us know how much you want to add.<br>
																					Thanks for your continued business.</b></td></td><tr>";
/*"<tr class='$rowClass'><td colspan=".($numCols-1)
																					." style='text-align:left;vertical-align:top;'>
																					<b>If you would like to add a gratuity, please write in an amount here.<br>
																					Thanks for your continued business.</b></td>
																					<td class='right' style='border-bottom:solid #000000 1px;'>"
																					."\$________</td><tr>"*/																					

		$finalLineItems[] = array('#CUSTOM_ROW#'=> $gratuityLine);
	}
	
	
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	//if($prepaidAmount) $columns['charge'] ='(Paid) '.$columns['charge'];
	$headerClasses = array('charge'=>'dollaramountheader_right');
	tableFrom($columns, $finalLineItems, 'WIDTH=100% ',null,$headerClasses,null,null,null,$rowClasses, array('charge'=>'right'));
	echo "</td></tr></table>";

}

function floatFromDollars($dollars) {  // THis KLUDGE is necessary because I formatted the currency when building the lineItems.  sh*t.
	$realAmt = $dollars;
	if(strpos($dollars, ';') !== FALSE)
		$realAmt = substr($dollars, strpos($dollars, ';')+1);
	else if(strpos($dollars, '$') === 0) $realAmt = substr(dollars, 2);
	return $realAmt;
}

function createInvoiceDetailView($asOfDate, $clientid=null, $invoiceid=null) {
	if(!$invoiceid) {
		if($asOfDate) $conditions[] = "itemdate <= '".date('Y-m-d', strtotime($asOfDate))."'";
		$onlyTheseBillables = $conditions ? "AND ".join(' AND ', $conditions) : '';
		$billables = getUninvoicedBillables($clientid, $onlyTheseBillables); // Include paid billables for subtotal.  -- AND charge > paid 
	}
	else 
		$billables = fetchAssociationsKeyedBy(
			"SELECT billableid, tblbillable.clientptr, itemptr, itemtable, monthyear, itemdate, billabledate, 
						relinvoiceitem.charge as owed, tblbillable.tax, relinvoiceitem.prepaidamount as paid, tblbillable.charge
			 FROM relinvoiceitem 
			 LEFT JOIN tblbillable ON billableid = 	billableptr
			 WHERE relinvoiceitem.invoiceptr = $invoiceid", 'billableid');
	$provs = getProviderShortNames();
	$servs = getServiceNamesById();
	$discountTypes = fetchAssociationsKeyedBy("SELECT * FROM tbldiscount", 'discountid');
	usort($billables, 'orderByItemDate');
	$totals = array();
	foreach($billables as $b) {
		$row = $b;
		$row['date'] = shortDate(strtotime($row['itemdate']));
		if($b['itemtable'] == 'tblappointment') {
			$item = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = {$b['itemptr']} LIMIT 1");
			$row['service'] = serviceLink($item);
			$row['provider'] = $provs[$item['providerptr']];
			$row['charge0'] = $item['charge'];
			$totals['charge0'] += $item['charge'];
			$row['rate'] = $item['rate'];
			$totals['rate'] += $item['rate'];
			$row['bonus'] = $item['bonus'];
			$totals['bonus'] += $item['bonus'];
			$row['adjustment'] = $item['adjustment'];
			$totals['adjustment'] += $item['adjustment'];
			$row['tod'] = $item['timeofday'];
			$discount = fetchFirstAssoc("SELECT * FROM relapptdiscount WHERE appointmentptr = {$item['appointmentid']} LIMIT 1");
			if($discount) {
				$type = $discountTypes[$discount['discountptr']];
				$type = "{$type['label']} (".($type['ispercentage'] ? "{$type['amount']}%" : dollarAmount($type['amount'])).")";
				$row['discount'] = "<span style='text-decoration:underline;' title='$type'>{$discount['amount']}</span>";
				$totals['discount'] += $item['discount'];
			}
			$totals['tax'] += $row['tax'];
			$totals['charge'] += $row['charge'];
		}
		if($b['itemtable'] == 'tblsurcharge') {
			$item = fetchFirstAssoc("SELECT * FROM tblsurcharge WHERE surchargeid = {$b['itemptr']} LIMIT 1");
			$row['service'] = surchargeLink($item);
			$row['provider'] = $provs[$item['providerptr']];
			$row['charge0'] = $item['charge'];
			$totals['charge0'] += $item['charge'];
			$row['rate'] = $item['rate'];
			$totals['rate'] += $item['rate'];
			$row['bonus'] = $item['bonus'];
			$totals['bonus'] += $item['bonus'];
			$row['adjustment'] = $item['adjustment'];
			$totals['adjustment'] += $item['adjustment'];
			$row['tod'] = $item['timeofday'];
			$totals['tax'] += $row['tax'];
			$totals['charge'] += $row['charge'];
		}
		if($b['itemtable'] == 'tblothercharge') {
			$item = fetchFirstAssoc("SELECT * FROM tblothercharge WHERE chargeid = {$b['itemptr']} LIMIT 1");
			$row['service'] = chargeLink($item, $b['billableid']);
		}
		$rows[] = $row;
	}
	foreach($totals as $k => $v) $totals[$k] = $v ? dollarAmount($v) : '';
	$rows[] = $totals;
//print_r($rows);	
	$columns = explodePairsLine('date|Date||tod|Time of Day||service|Service||provider|Sitter||rate|Rate||bonus|Bonus||charge0|Base Charge||adjustment|Adj||discount|Discount||tax|Tax||charge|Final');
	if($_SESSION['preferences']['suppressInvoiceTimeOfDay']) unset($columns['timeofday']);
	if($_SESSION['preferences']['suppressInvoiceSitterName']) unset($columns['provider']);
	
	$colClasses = array('charge'=>'dollaramountcell', 'provider'=>'futuretaskEVEN', 'rate'=>'futuretaskEVEN', 'bonus'=>'futuretaskEVEN');
	tableFrom($columns, $rows, 'WIDTH=100% border=1 class="futuretask"',null,$headerClasses,null,null,null,$rowClasses, $colClasses);
	
}

function orderByItemDate($a, $b) {
	return strcmp($a['itemdate'], $b['itemdate']);
}



function editCurrentCharges($invoice, $asOfDate) { // Invoice #, Invoice Date, Items, Subtotal
	global $lockedPreview;
	echo "<table width='95%'>";
	echo "<tr><td colspan=2>";
	//$invoiceid = $invoice['invoiceid'];
	
	$asOfDate = shortDate(strtotime($asOfDate));
	if($lockedPreview) {
		$calWidgets =  $asOfDate;
	}
	else {
		ob_start();
		ob_implicit_flush(0);
		calendarSet('', 'asOfDate', $asOfDate);
		echo " ";
		echoButton('', 'Show', "changeAsOfDate({$invoice['clientptr']})");
		$calWidgets = ob_get_contents();
		//echo 'XXX: '.ob_get_contents();exit;
		ob_end_clean();

		ob_start();
		ob_implicit_flush(0);
		echoButton('', 'Add Miscellaneous Charge', "openChargeWindow({$invoice['clientptr']})");
		$makeChargeWidgets = ob_get_contents();
		//echo 'XXX: '.ob_get_contents();exit;
		ob_end_clean();
	}
	dumpSectionBar("Current Invoice Charges as of: $calWidgets", $makeChargeWidgets, 'padding-top:3px;height:25px;');
	echo "</td></tr><tr><td>";
	
	
	$columns = explodePairsLine('cb| ||date|Date||timeofday| ||service|Service||provider| ||charge|Charge');
	// Suppress all column headers if there are no services
	$lineItems = getPreviewLineItems($invoice['clientptr'], $asOfDate);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { foreach($lineItems as $b) echo print_r($b, 1).'<p>';exit;}	
	if($lineItems && isset($lineItems[0]['servicecode'])) foreach(array_keys($columns) as $index) $columns[$index] = '&nbsp;';
	$columns = explodePairsLine('cb| ||date|Date||timeofday|Time of Day||service|Service||provider|Sitter||charge|Charge');
	if($_SESSION['preferences']['suppressInvoiceTimeOfDay']) unset($columns['timeofday']);
	if($_SESSION['preferences']['suppressInvoiceSitterName']) unset($columns['provider']);
	//Date	Time of Day	Service	Sitter	Charge
	$numCols = count($columns);
	$headerLine = "<tr><th class='sortableListHeader'>".join("</th><th class='sortableListHeader'>", $columns)."</th></tr>";
	$dateWidth = $_SESSION['preferences']['suppressInvoiceTimeOfDay'] ? 1 : 2;
	$descWidth = $_SESSION['preferences']['suppressInvoiceSitterName'] ? 1 : 2;
	
	$appointmentsStarted = false;
	
//print_r($lineItems);exit;	

	$rowClasses = array();
	$prepaidAmount = 0;
	foreach($lineItems as $index => $lineItem) {
//echo "CHARGE - ".print_r($lineItem, 1)/*substr($lineItem['charge'], 2)*/.'<p>';
		// yuck.  this needs to be cleaned up.
		//$yuck = $lineItem['charge'];
		//if(strpos($yuck, '$ ') !== FALSE) $yuck = 0+substr($lineItem['charge'], 2);
		//else $lineItem['charge'] = dollarAmount($yuck);
		//$subtotal += $yuck;
		$realCharge = floatFromDollars($lineItem['charge']);
		$subtotal += 0+$realCharge;

		
		if($lineItem['amountpaid']) {
			//$lineItem['charge'] = "({$lineItem['amountpaid']}) {$lineItem['charge']}";
			$prepaidAmount += $lineItem['pretaxpaid'];//+$lineItem['taxpaid'];//trim(substr($lineItem['amountpaid'], 1));			
			$prepaidTaxTotal += $lineItem['taxpaid'];
		}
		if($lineItem['canceled']) {
			if(!$appointmentsStarted) {
				$appointmentsStarted = true;
				$line = $headerLine;
				//$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
				//$rowClasses[] = null;
			}
//$lineItem['service'] = 	"{$lineItem['service']} [{$lineItem['appointmentid']}]";
//if($lineItem['appointmentid'] == 26970) $lineItem['service'] = 	"{$lineItem['service']} ".print_r($lineItem, 1);
			$lineItem['cb'] = $lockedPreview
				? '&nbsp;'
				: "<input type='checkbox' onClick='toggleContainer(this)' container='{$lineItem['container']}' name='canceled"."_{$lineItem['appointmentid']}'>";
			$finalLineItems[] = $lineItem;
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
		}
		else if($lineItem['servicecode'] || $lineItem['surchargecode']) {
			if(!$appointmentsStarted) {
				$appointmentsStarted = true;
				$line = $headerLine;
				//$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
				//$rowClasses[] = null;
			}
//$lineItem['service'] = 	"{$lineItem['service']} [{$lineItem['appointmentid']}]";
//if($lineItem['appointmentid'] == 26970) $lineItem['service'] = 	"{$lineItem['service']} ".print_r($lineItem, 1);
			if($lineItem['charge']) $lineItem['cb'] = $lockedPreview
				? '&nbsp;'
				: "<input type='checkbox' onClick='toggleContainer(this)' container='{$lineItem['container']}' name='item"."_{$lineItem['billableid']}' CHECKED>";
			$finalLineItems[] = $lineItem;
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
		}
		else if($lineItem['issuedate']) {  // Misc Charge
			$description = $lineItem['reason'];
			$date = shortDate(strtotime($lineItem['issuedate']));
			$cb = "<input type='checkbox' onClick='toggleContainer(this)' container='{$lineItem['container']}' name='item"."_{$lineItem['billableid']}' CHECKED>";
			$line = "<tr class='$rowClass'><td>$cb</td><td class='sortableListCell' colspan=$dateWidth>$date</td>".
								"<td class='sortableListCell' colspan=$descWidth>$description</td><td class='right'>{$lineItem['charge']}</td>";
			$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
			$rowClasses[] = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
		}
		else { // package
//print_r($lineItem);echo "[".array_search('tblservicepackage', $lineItem)."]<p>";
			$description = isset($lineItem['tblservicepackage']) 
											? ($lineItem['onedaypackage'] ? 'One day' : 'Short term') 
											: (isset($lineItem['tblrecurringpackage']) 
													? ($lineItem['monthly'] ? 'Monthly' : 'Weekly')
													: 'Miscellaneous Charges');
			
			if($description != 'Miscellaneous Charges') $description .= ' '.($lineItem['prepaid'] ? 'prepaid' : 'postpaid');
			if($lineItem['cancellationdate']) $description .= ' - Canceled: '.shortNaturalDate(strtotime($lineItem['cancellationdate']));
											
			$rowClasses[] = null;
			$rowClass = $lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
			$line = "<tr class='$rowClass'><td><input type='checkbox' onClick='toggleContainer(this)' containerid='{$lineItem['sectionLabel']}' name='item"."_{$lineItem['billableid']}' CHECKED></td>";
			$date = isset($lineItem['monthyear']) 
				? $lineItem['monthyear'] 
				: ($lineItem['enddate'] ? $lineItem['startdate']."-".$lineItem['enddate'] : $lineItem['startdate']);
//print_r($lineItem);			
			$line .= "<td class='sortableListCell' colspan=$dateWidth>$date</td>".
								"<td class='sortableListCell' style='font-weight:bold' colspan=$descWidth>$description</td><td class='right'>{$lineItem['charge']}</td>";
			$finalLineItems[] = array('#CUSTOM_ROW#'=> $line);
		}
	}
	
	$discountInfo = getDiscountInfoFromLineitems($lineItems); // array('label'=>, 'amount'=>)
	if($discountInfo && $discountInfo['amount'] != 0) {
		$rowClass = 'futuretask';//$lineItem['stripe'] == 'white' ? 'futuretask' : 'futuretaskEVEN';
		$subtotal += $discountInfo['amount'];
		$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																							." style='text-align:left;'><b>Discount Applied: </b>{$discountInfo['label']}</td><td class='right'>"
																							.dollarAmount($discountInfo['amount'])
																							."</td><tr>");
																							
	}
	
	
	$subtotal = dollars($subtotal); //  - $prepaidAmount);
	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr><td colspan=$numCols style='text-align:right;font-weight:bold'>Subtotal: $subtotal</td><tr>");

	$prepaidTaxTotal = $prepaidTaxTotal ? " (less ".dollarAmount($prepaidTaxTotal)." applied to tax)" : '';																							
	$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																					." style='text-align:left;'><b>Credits and Payments Applied:$prepaidTaxTotal</b></td><td class='right'>"
																					.dollarAmount(0-$prepaidAmount)
																					."</td><tr>");
	if($_SESSION['preferences']['includeInvoiceGratuityLine']) {																		
		$finalLineItems[] = array('#CUSTOM_ROW#'=> "<tr class='$rowClass'><td colspan=".($numCols-1)
																					." style='text-align:left;vertical-align:top;'>
																					<b>If you would like to add a gratuity, please write in an amount here.<br>
																					Thanks for your continued business.</b></td>
																					<td class='right' style='border-bottom:solid #000000 1px;'>"
																					."\$________</td><tr>");
	}


//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	//if($prepaidAmount) $columns['charge'] ='(Paid) '.$columns['charge'];
	$headerClasses = array('charge'=>'dollaramountheader_right');
	if($finalLineItems) {
		fauxLink('Select All', 'checkAllLineItems(1)');
		echo "<img src='art/spacer.gif' width=20 height=1>";
		fauxLink('Deselect All', 'checkAllLineItems(0)');
		tableFrom($columns, $finalLineItems, 'WIDTH=100% ',null,$headerClasses,null,null,null,$rowClasses, array('charge'=>'right'));
	}
	echo "</td></tr></table>";

}


function getDiscountInfoFromLineitems($lineitems) {
	if(!$_SESSION['discountsenabled']) return null;
	require_once "discount-fns.php";
	if(!$lineitems) return;
	// collect appointmentids from billables
	$ids = array();
	foreach($lineitems as $lineitem)
		if($lineitem['appointmentid'])
			$ids[] = $lineitem['appointmentid'];
	return aggregateDiscountInfo($ids);
}




function dumpBalances($invoice) {
	echo "<table width=60%>";
	labelRow('Previous Balance', '', dollars($invoice['pastbalancedue']), '', 'right', null, null, 'raw');
	labelRow('Payments &amp; Credits', '', dollars($invoice['creditsapplied']), '', 'right', null, null, 'raw');
	labelRow("This Invoice ".invoiceIdDisplay($invoice['invoiceid']), 'thisInvoiceTD', dollars($invoice['subtotal']), '', 'right', null, null, 'raw');
	labelRow("Tax", '', dollars($invoice['tax']), 'taxTD', 'right', null, null, 'raw');
	
	$ccpayment = $invoice['ccpayment'] ? explode('|', $invoice['ccpayment']) : '';
	$finalBalancetDue = $ccpayment ? $invoice['origbalancedue'] - $ccpayment[0] : $invoice['origbalancedue'];
	// Since the credit card payment was made before the invoice is generated, the origbalancedue already takes the ccpayment into account.
	
	$important = array('bigger-right', '', 'border: solid black 1px;');
	
	$emphasis = $invoice['origbalancedue'] == $finalBalancetDue ? $important : array('right', null, null);
//labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false) {
	
	$balanceToDisplay = $invoice['origbalancedue'];
//if(staffOnlyTEST()) echo "invoice: ".print_r($invoice, 1)."<br>";

	labelRow('Total Account Balance Due', 'totalAccountBalanceDueTD', dollars($balanceToDisplay), $labelClass=null, $emphasis[0], $emphasis[1], $emphasis[2], 'raw');
	if($ccpayment) {
		//$ccPayment format: amount|transactionId|ccid|company|last4|acctid
		labelRow("<b>Paid electronically</b>", '', dollars($ccpayment[0]), 'ccpayment', 'right', null, null, 'raw');
		if($ccpayment[2]) labelRow("{$ccpayment[3]} {$ccpayment[4]}", '', "Thank You!", 'ccpayment', 'right');
		else if($ccpayment[5]) labelRow("Bank Account {$ccpayment[4]}", '', "Thank You!", 'ccpayment', 'right');

		if($finalBalancetDue > 0)
			labelRow('Balance Due after payment', 'finalBalancetDue', dollars($finalBalancetDue), $labelClass=null, $important[0], $important[1], $important[2], 'raw');
	}
	if($finalBalancetDue && $invoice['duedate']) {
		
		if($_SESSION['preferences']['statementsDueOnPastDueDays'] != 'Suppress') {
			// $invoice['duedate'] will be zero Upon Receipt and if retrieved from DB
			$dateDue = $invoice['duedate'] == date('Y-m-d', strtotime($invoice['date'])) ? 'Upon Receipt' : $invoice['duedate'];
			labelRow('Date Due:', '', $dateDue, $labelClass=null, 'bigger-right', '', 'border: solid black 1px;');
		}
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
	echo "<table width=200>";
	labelRow('Customer Number', '', $client['clientid'], '', 'right');
	if($invoice['invoiceid']) {
		labelRow('Invoice Number', '', invoiceIdDisplay($invoice['invoiceid']), '', 'right');
		labelRow('Invoice Date', '', shortDate(strtotime($invoice['date'])), '', 'right');
	}
	$ccpayment = $invoice['ccpayment'] ? explode('|', $invoice['ccpayment']) : '';
	$finalBalancetDue = max(0, ($ccpayment ? $invoice['origbalancedue'] - $ccpayment[0] : $invoice['origbalancedue']));
	
	labelRow('Amount Due:', '', dollars($finalBalancetDue), $labelClass=null, 'bigger-right', '', 'border: solid black 1px;', 'raw');
	if($_SESSION['preferences']['statementsDueOnPastDueDays'] != 'Suppress') {
		$dateDue = $invoice['duedate'] == date('Y-m-d', strtotime($invoice['date'])) ? 'Upon Receipt' : $invoice['duedate'];
		labelRow('Date Due:', '', $dateDue, $labelClass=null, 'bigger-right', '', 'border: solid black 1px;');
	}
	echo "</table>";
}

function emailedInvoicePreviewLogoDivContents() {
	return $preferences['emailedInvoicePreviewHeader']
			? $preferences['emailedInvoicePreviewHeader']
			: generateDefaultBusinessLogoDivContentsForInvoicePreview(getBizLogoImage());
	
}

function dumpBusinessLogoDiv($amountDue, $html=null, $preview=false, $clientid=null) {
	global $preferences;
	$headerBizLogo = getBizLogoImage(); 
	if(!$html) 
		$html = $preferences['invoiceHeader']
			? $preferences['invoiceHeader']
			: ($preview ? generateDefaultBusinessLogoDivContentsForInvoicePreview($headerBizLogo)
					:generateDefaultBusinessLogoDivContents($headerBizLogo));
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
	$headerBizLogo = $headerBizLogo ? $headerBizLogo :  getBizLogoImage(); 
	$headerBizLogo = $headerBizLogo 
		? "#LOGO#\n" . oneLineTextLogo($preferences, $raw)
		: textLogo($preferences, $raw);
	return $headerBizLogo;
}

function generateDefaultBusinessLogoDivContentsForInvoicePreview($headerBizLogo=null, $raw=null) {
	global $preferences;
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

function oneLineTextLogo($preferences, $raw=false) {
	$fields = explodePairsLine('PHONE|bizPhone||FAX|bizFax||EMAIL|bizEmail||HOMEPAGE|bizHomePage||ADDRESS|bizAddress');
	$labels = explodePairsLine('PHONE|Phone:||FAX|FAX:||EMAIL|Email:||HOMEPAGE| ||ADDRESS| ');
	foreach($fields as $token => $field)
		if($token != 'ADDRESS' && $preferences[$field]) $parts[] = $labels[$token].' '.($raw ? "#$token#" : $preferences[$field]);
	$logo =	join(' - ', (array)$parts);
	
	if($preferences['bizAddress']) $logo = ($raw ? "#$token#" : $preferences['bizAddress'])."\n$logo";
	return $logo;
}

function textLogo($preferences, $raw) {
	$labels = explodePairsLine('EMAIL|Email:');
	foreach(explodePairsLine('BIZNAME|bizName||ADDRESS|bizAddress||PHONE|bizPhone') as $token => $field)
		if($preferences[$field]) $parts[] = $labels[$token].($raw ? "#$token#" : $preferences[$field]);
	$logo =	"<div style='width:369;height:90'>".join("\n", $parts);
	if($preferences['bizFax']) $logo .=	" - FAX: ".($raw ? "#FAX#" : $preferences['bizFax']);
	foreach(explodePairsLine('EMAIL|bizEmail||HOMEPAGE|bizHomePage') as $token => $field)
		if($preferences[$field]) $logo .=	"\n".$labels[$token].' '.($raw ? "#$token#" : $preferences[$field]);
	return $logo;
}

function getInvoiceLineItems($invoiceid, $clientid) {
	global $providers, $billables, $lineitems, $allBilledAppointments, $appointmentBillables, $allBilledSurcharges, $surchargeBillables;
	$providers = getProviderNames();
	$billables = fetchAssociationsKeyedBy(
		"SELECT billableid, tblbillable.clientptr, itemptr, itemtable, monthyear, itemdate, billabledate, 
					relinvoiceitem.charge as owed, tblbillable.tax, relinvoiceitem.prepaidamount as paid,
					relinvoiceitem.description as description
		 FROM relinvoiceitem 
		 LEFT JOIN tblbillable ON billableid = 	billableptr
		 WHERE relinvoiceitem.invoiceptr = $invoiceid
		 ORDER BY itemdate", 'billableid');
	$lineitems = array();
	$apptids = array();
	$chargeids = array();  // billableid => chargeid
	$surchargeids = array();
	$appointmentBillables = array(); // appointmentid => billable
	$chargeBillables = array(); // chargeid => billable
	$surchargeBillables = array(); // appointmentid => billable
	foreach($billables as $i => $billable) {
		$billable['descriptionFields'] = getInvoiceItemDescription($billable);
		$billables[$i] = $billable;
		if($billable['itemtable'] == 'tblappointment') {
			$apptids[$billable['billableid']] = $billable['itemptr'];
			$appointmentBillables[$billable['itemptr']] = $billable;
		}
		else if($billable['itemtable'] == 'tblothercharge') {
			$chargeids[$billable['billableid']] = $billable['itemptr'];
			$chargeBillables[$billable['itemptr']] = $billable;
		}
		else if($billable['itemtable'] == 'tblsurcharge') {
			$surchargeids[$billable['billableid']] = $billable['itemptr'];
			$surchargeBillables[$billable['itemptr']] = $billable;
		}
	}
	
	
	$allBilledAppointments = !$apptids ? array() : 
		fetchAssociationsKeyedBy(
			"SELECT appointmentid, packageptr, date, timeofday, servicecode, providerptr, clientptr, pets, charge+ifnull(adjustment, 0) as charge, canceled
				FROM tblappointment
				WHERE appointmentid IN (".join(', ',$apptids).") ORDER BY date, starttime", 'appointmentid');
	
	// existence of $surchargeids implies existence of tblsurcharge
	$allBilledSurcharges = !$surchargeids ? array() : 
		fetchAssociationsKeyedBy(
			"SELECT surchargeid, packageptr, date, timeofday, surchargecode, providerptr, clientptr, charge
				FROM tblsurcharge
				WHERE surchargeid IN (".join(', ',$surchargeids).") ORDER BY date, starttime", 'surchargeid');
	
	$allBilledCharges = !$chargeids ? array() : 
		fetchAssociationsKeyedBy(
			"SELECT chargeid, issuedate, reason, amount as charge
				FROM tblothercharge
				WHERE chargeid IN (".join(', ',$chargeids).") ORDER BY issuedate", 'chargeid');
	

	/// IMPORTANT: NEED TO IDENTIFY ALL VERSIONS OF EACH CURRENT PACKAGE SINCE COMPLETED APPOINTMENTS MAY BE
	//  ASSOCIATED WITH NON-CURRENT VERSIONS
	
	require_once "service-fns.php";
	$stripe = 'white';
	
	// Strategy: show misc charges first, monthly appts last, and all other appts in between
	$lineitems = array();
	$stripe = miscChargeRows($stripe, $allBilledCharges, $chargeBillables);
	$stripe = regularAppointmentRows($stripe, $clientid);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($lineitems);}		 
	
	$sql = "SELECT clientptr, packageid, startdate, cancellationdate, totalprice as charge, prepaid, monthly, 'tblrecurringpackage'
					FROM tblrecurringpackage
					WHERE monthly = 1 AND clientptr = $clientid
					ORDER BY packageid DESC LIMIT 1"; //current = 1 AND 
	$stripe = monthlyPackageRows($sql, $stripe);
//if(mattOnlyTEST()) {print_r($lineitems); exit;}
	uasort($lineitems, 'lineItemsByTimestamp');
	$stripe = 'white';
	for($i=0; $i < count($lineitems); $i++) {
		$lineitems[$i]['stripe'] = ($stripe = $stripe == 'white' ? 'grey' : 'white');
		//echo "$stripe<br>";
	}

	return $lineitems;
	
}
		
function getPreviewLineItems($clientid, $asOfDate=null) {
	global $providers, $billables, $lineitems, $allBilledAppointments, $appointmentBillables, $allBilledSurcharges, $surchargeBillables, $canceledAppointments;
	$providers = getProviderNames();
	$asOfDate = date('Y-m-d', strtotime($asOfDate));
	
	if($asOfDate) $conditions[] = "itemdate <= '$asOfDate'";
	$onlyTheseBillables = $conditions ? "AND ".join(' AND ', $conditions) : '';
	$billables = array();
	foreach(getUninvoicedBillables($clientid, $onlyTheseBillables) as $billable)
		$billables[$billable['billableid']] = $billable;
		
	$apptids = array();  // billableid => appointmentid
	$chargeids = array();  // billableid => chargeid
	$surchargeids = array();  // billableid => chargeid
	$appointmentBillables = array(); // appointmentid => billable
	$chargeBillables = array(); // chargeid => billable
	$surchargeBillables = array(); // chargeid => billable
	foreach($billables as $billable) {
		if($billable['itemtable'] == 'tblappointment') {
			$apptids[$billable['billableid']] = $billable['itemptr'];
			$appointmentBillables[$billable['itemptr']] = $billable;
		}
		else if($billable['itemtable'] == 'tblothercharge') {
			$chargeids[$billable['billableid']] = $billable['itemptr'];
			$chargeBillables[$billable['itemptr']] = $billable;
		}
		else if($billable['itemtable'] == 'tblsurcharge') {
			$surchargeids[$billable['billableid']] = $billable['itemptr'];
			$surchargeBillables[$billable['itemptr']] = $billable;
		}
	}
	
	$allBilledAppointments = !$apptids ? array() : 
		fetchAssociationsKeyedBy(
			"SELECT appointmentid, packageptr, date, timeofday, servicecode, providerptr, clientptr, pets, charge+ifnull(adjustment, 0) as charge, canceled
				FROM tblappointment
				WHERE appointmentid IN (".join(', ',$apptids).") ORDER BY date, starttime", 'appointmentid');
	
	$allBilledCharges = !$chargeids ? array() : 
		fetchAssociationsKeyedBy(
			"SELECT chargeid, issuedate, reason, amount as charge
				FROM tblothercharge
				WHERE chargeid IN (".join(', ',$chargeids).") ORDER BY issuedate", 'chargeid');
	
	$allBilledSurcharges = !$surchargeids ? array() : 
		fetchAssociationsKeyedBy(
			"SELECT surchargeid, packageptr, date, timeofday, surchargecode, providerptr, clientptr, charge
				FROM tblsurcharge
				WHERE surchargeid IN (".join(', ',$surchargeids).") ORDER BY date", 'surchargeid');

	$canceledAppointments = getCanceledVisits($clientid, $asOfDate);
	/// IMPORTANT: NEED TO IDENTIFY ALL VERSIONS OF EACH CURRENT PACKAGE SINCE COMPLETED APPOINTMENTS MAY BE
	//  ASSOCIATED WITH NON-CURRENT VERSIONS
	
	require_once "service-fns.php";
	$stripe = 'white';
	
	// Strategy: show misc charges first, monthly appts last, and all other appts in between
	
	$lineitems = array();
	$stripe = miscChargeRows($stripe, $allBilledCharges, $chargeBillables);
//echo "STRIPE 0: $stripe<p>";	
	$stripe = regularAppointmentRows($stripe, $clientid);
	$sql = "SELECT clientptr, packageid, startdate, cancellationdate, totalprice as charge, prepaid, monthly, 'tblrecurringpackage'
					FROM tblrecurringpackage
					WHERE current = 1 AND monthly = 1 AND clientptr = $clientid
					ORDER BY packageid DESC LIMIT 1";
	$stripe = monthlyPackageRows($sql, $stripe);
	uasort($lineitems, 'lineItemsByTimestamp');
	$stripe = 'white';
	for($i=0; $i < count($lineitems); $i++)
		$lineitems[$i]['stripe'] = ($stripe = $stripe == 'white' ? 'grey' : 'white');
		
	return $lineitems;
}

function miscChargeRows($stripe, $allBilledCharges, $chargeBillables) {
	global $billables, $lineitems;
	if(!$chargeBillables) return;
	$lineitem = array();
	$lineitem['stripe'] = ($stripe = $stripe == 'white' ? 'grey' : 'white');
	$sectionLabel = 'Miscellaneous Charges';
	$lineitem['sectionLabel'] = $sectionLabel;
	$lineitems[] = $lineitem;
	
	foreach($allBilledCharges as $charge) {
		$billable = $chargeBillables[$charge['chargeid']];
		$charge['billableid'] = $billable['billableid'];
		if($billable['descriptionFields']) 
			foreach($billable['descriptionFields'] as $k => $v) $charge[$k] = $v;
		$charge['stripe'] = $stripe;
		$charge['charge'] = dollars($charge['charge']);//dollars($billable['owed']);
		$charge['amountpaid'] = $billable['paid'] > 0 ? dollars($billable['paid']) : '';
		$charge['pretaxpaid'] = $billable['paid'];
		$charge['date'] = shortDate(strtotime($charge['issuedate']));
		$charge['container'] = $sectionLabel;
		
		$lineitems[] = $charge;
	}
	return $stripe;
}

function lineItemsByTimestamp($a, $b) {
	return BETTERlineItemsByTimestamp($a, $b);	
	// OBSELETE BELOW
	if(!$a['timestamp'] || !$b['timestamp']) 
		return strcmp($a['date'], $b['date']);
	return $a['timestamp'] < $b['timestamp'] ? -1 : (
					$a['timestamp'] > $b['timestamp'] ? 1 : 0);
}

function BETTERlineItemsByTimestamp($a, $b) {
	$aa = substr((string)$a['timeofday'], 0, strpos((string)$a['timeofday'], '-'));
	$ta = $a['timestamp'] ? $a['timestamp'] : strtotime(trim("{$a['date']} $aa"));
	$bb = substr((string)$b['timeofday'], 0, strpos((string)$b['timeofday'], '-'));
	$tb = $b['timestamp'] ? $b['timestamp'] : strtotime(trim("{$b['date']} $bb"));
	
//echo ($a['timestamp'] ? '*' : '')."({$a['date']} $aa), ".($b['timestamp'] ? '*' : '')."({$b['date']} $bb) [".($ta < $tb ? -1 : ($ta > $tb ? 1 : 0))."]<br>";
	return $ta < $tb ? -1 : (
					$ta > $tb ? 1 : 0);
}

function regularAppointmentRows($stripe, $clientid) {  // billables keyed by billableid, packageBillables: packageid=>billableid
	global $providers, $billables, $lineitems, $allBilledAppointments, $appointmentBillables, $canceledAppointments, $allBilledSurcharges, $surchargeBillables;
	static $serviceNames;
	if(!$serviceNames) $serviceNames = getAllServiceNamesById(true, true);

	$newLineitems = array();
	$allPets = array();
	if(count($allBilledAppointments)) {
		require_once "pet-fns.php";
		$allPets = getClientPetNames($clientid);
	}
	
	
	foreach($allBilledAppointments as $i => $appt) {
		$apptIds[] = $appt['appointmentid'];
	}
	
	//$discounts = $apptIds 
	//	? fetchKeyValuePairs("SELECT appointmentptr, amount FROM relapptdiscount WHERE appointmentptr IN (".join(',', $apptIds).")")
	//	: array();
	
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r(join(',',$apptIds));echo "<p>"; }


//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') print_r($allBilledAppointments);	
	foreach($allBilledAppointments as $appt) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173' && in_array($appt['appointmentid'], array(4802,4804,4806,38726))) print_r($appt);	
		if($appt['canceled']  && isset($canceledAppointments[$appt['appointmentid']])) continue;
		
//if($appt['appointmentid'] = 62914) print_r($appt);
//echo "STRIPE {$appt['date']}: $stripe => ";	
//echo "{$appt['date']}: $stripe<p>";	
		// if appt not in package history
		$billable = $appointmentBillables[$appt['appointmentid']];
//if($appt['appointmentid'] == 26970)	{print_r($billable);exit;}				
		$appt['billableid'] = $billable['billableid'];
		if(!$billable['descriptionFields']) {  // older invoice without item descriptions
			$appt['service'] = $serviceNames[$appt['servicecode']];
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
		}
		else {
			foreach($billable['descriptionFields'] as $k => $v) $appt[$k] = $v;
		}
		addPretaxAndPaymentsTo($billable, $appt);
		$appt['amountpaid'] = $appt['pretaxpaid'] > 0 ? dollars($appt['pretaxpaid']) : '';
		$appt['dbdate'] = $appt['date'];
		$timeofday = $appt['timeofday'];
		if($timeofday) $appt['timestamp'] = strtotime($appt['dbdate'].' '.substr($timeofday, 0, strpos($timeofday, '-')));
		$appt['date'] = shortDate(strtotime($appt['date']));
//echo "A: ".print_r($appt,1)."<br>B: ".print_r($billable,1);exit;
		if($section) $appt['charge'] = '';
		else {
//echo print_r($appt,1).'<p>disc: '.;	
			
			//$charge = $appt['pretax']-$appt['pretaxpaid'] + $discounts[$appt['appointmentid']];  // add discount back in for invoice display
			$charge = $appt['charge']; 
			$appt['charge'] = $section ? '' : dollars($charge);
		}
		$newLineitems[] = $appt;
	}
	foreach($allBilledSurcharges as $surcharge) {
		$billable = $surchargeBillables[$surcharge['surchargeid']];
		$surcharge['billableid'] = $billable['billableid'];
		if(!$billable['descriptionFields']) {  // older invoice without item descriptions
			$surcharge['service'] = 'Surcharge: '.getSurchargeName($surcharge['surchargecode']);
			$surcharge['provider'] = $providers[$surcharge['providerptr']];
		}
		else foreach($billable['descriptionFields'] as $k => $v) $surcharge[$k] = $v;
		addPretaxAndPaymentsTo($billable, $surcharge);
		$surcharge['amountpaid'] = $surcharge['pretaxpaid'] > 0 ? dollars($surcharge['pretaxpaid']) : '';
		$surcharge['dbdate'] = $surcharge['date'];
		$surcharge['date'] = shortDate(strtotime($surcharge['date']));
		//$surcharge['charge'] = $section ? '' : dollars($surcharge['pretax']-$surcharge['pretaxpaid']);
		$newLineitems[] = $surcharge;
	}
	
	foreach($canceledAppointments as $appt) {
		if($appt['monthly']) continue;
		$appt['service'] = $_SESSION['servicenames'][$appt['servicecode']]." <span style='font-variant:small-caps;color:red'>canceled</span>";
		$appt['provider'] = $providers[$appt['providerptr']];
		$appt['charge'] = 0;
		addPretaxAndPaymentsTo(null, $appt);
		$appt['amountpaid'] = 0;
		$appt['dbdate'] = $appt['date'];
		$timeofday = $appt['timeofday'];
		if($timeofday) $appt['timestamp'] = strtotime($appt['dbdate'].' '.substr($timeofday, 0, strpos($timeofday, '-')));
		$appt['date'] = shortDate(strtotime($appt['date']));
		$newLineitems[] = $appt;
	}
	
	uasort($newLineitems, 'dateSort');
	foreach($newLineitems as $i => $appt) {
		$stripe = $stripe == 'white' ? 'grey' : 'white';
		$newLineitems[$i]['stripe'] = $stripe;
	}
	
	$lineitems = array_merge($lineitems, $newLineitems);
	return $stripe;
}


function sortByDate($a, $b) {
	return strcmp("{$a['dbdate']} {$a['starttime']}", "{$b['dbdate']} {$b['starttime']}");
}

function dateSort($a, $b) {
	global $clients;
	$result = strcmp($a['dbdate'], $b['dbdate']);
	if(!$result) {
		$result = strcmp($a['starttime'], $b['starttime']);
	}
	if(!$result) {
		$result = strcmp("{$clients[$a['clientptr']]}", "{$clients[$b['clientptr']]}");
	}
	if(!$result) {
		$a = isset($a['appointmentid']) ? '1' : 2;
		$b = isset($b['appointmentid']) ? '1' : 2;
		$result = strcmp($a, $b);
	}
	return $result;
}

	
function monthlyPackageRows($sql, $stripe) {  // billables keyed by billableid, packageBillables: packageid=>billableid
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($sql); }
	global $providers, $billables, $lineitems, $allBilledAppointments, $appointmentBillables, $canceledAppointments;
	$currentPackages = fetchAssociations($sql);
	if($currentPackages) $histories = findPackageHistories($currentPackages[0]['clientptr'], 'R');
	
	$currentPackages = array($currentPackages[0]);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo print_r($currentPackages,1)."<br>"; }
	
	//echo print_r($currentPackages, 1).'<p>';
	foreach($currentPackages as $n => $package) {
		// ignore non-monthly packages
		if(!$package['monthly']) continue;
		$history = $histories[$package['packageid']] ? $histories[$package['packageid']] : $package['packageid'];
		// Sections: monthly packages will be broken down by month
		$sections = array();
		foreach($billables as $billable) if($billable['monthyear'])  $sections[] = $billable;
		$monthlyAppts = fetchAssociations(
			"SELECT appointmentid, packageptr, date, timeofday, servicecode, providerptr, charge+ifnull(adjustment, 0) as charge, pets, canceled
				FROM tblappointment
				WHERE packageptr IN (".join(',', $history).") ORDER BY date, starttime");
		foreach($sections as $section) {
			// ensure there are appts in a nonmonthly before proceeding
		
			$lineitem = $package;

			$sectionLabel = $sectionMonthYear;
			$billable = $section;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') 	print_r($billable );		
			if($billable['descriptionFields']) $lineitem['charge'] = dollars($billable['descriptionFields']['charge']);
			
			else $lineitem['charge'] = dollars($billable['charge']);  // "owed" is billable.charge-billable.paid for preview, relinvoiceitem.charge for invoice
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($billable['descriptionFields']); }
			$lineitem['sectionLabel'] = $sectionLabel;
			$lineitem['billableid'] = $billable['billableid'];
			addPretaxAndPaymentsTo($billable, $lineitem);			
			$lineitem['amountpaid'] = $lineitem['pretaxpaid'] > 0 ? dollars($lineitem['pretaxpaid']) : '';
			$lineitem['monthyear'] = date('F Y', strtotime($billable['monthyear']));
			$sectionMonthYear = substr($billable['monthyear'], 0, 7);

			$lineitem['stripe'] = ($stripe = $stripe == 'white' ? 'grey' : 'white');
			$lineitem['startdate'] = '';
			

			$lineitems[] = $lineitem;
			$newLineitems = array();
			//if($section && strpos($appt['date'], $sectionMonthYear) !== 0) continue;
			foreach($monthlyAppts as $appt) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo $appt['packageptr'].' '.$appt['date']." in [$sectionMonthYear]<br>"; }
				// if monthly and not in section's monthyear skip it
				if(strpos($appt['date'], $sectionMonthYear) !== 0) continue;
				if(!in_array($appt['packageptr'], $history)) continue;
				$appt['stripe'] = $stripe;
				$appt['service'] = $_SESSION['servicenames'][$appt['servicecode']];
if($appt['canceled']) $appt['service'] = $appt['service']." <span style='font-variant:small-caps;color:red'>canceled</span>";
				$appt['provider'] = $providers[$appt['providerptr']];
				$appt['charge'] = $section ? '' : dollars($appt['charge']);
				$appt['dbdate'] = $appt['date'];
				$appt['date'] = shortDate(strtotime($appt['date']));
				$appt['container'] = $sectionLabel;
				$newLineitems[] = $appt;
			}
			foreach($canceledAppointments as $appt) {
				if(!in_array($appt['packageptr'], $history)) continue;
				if(strpos($appt['date'], $sectionMonthYear) !== 0) continue;
				$stripe = $stripe == 'white' ? 'grey' : 'white';
				$appt['stripe'] = $stripe;
				$appt['service'] = $_SESSION['servicenames'][$appt['servicecode']]." <span style='font-variant:small-caps;color:red'>canceled</span>";
				$appt['provider'] = $providers[$appt['providerptr']];
				$appt['charge'] = 0;
				$appt['amountpaid'] = 0;
				$appt['dbdate'] = $appt['date'];
				$appt['date'] = shortDate(strtotime($appt['date']));
				$newLineitems[] = $appt;
			}
			uasort($newLineitems, 'sortByDate');
			$lineitems = array_merge($lineitems, $newLineitems);

		}
	}
	return $stripe;
}

function invoiceIdDisplay($invoiceid, $prefix="LT") {
	return $invoiceid? $prefix.sprintf("%04d", $invoiceid) : '(New)';
}


function dollars($amount) {
	$amount = $amount ? $amount : 0;
	/*if(strpos($amount, ';') !== FALSE)
		$amount = substr($amount, strpos($amount, ';')+1);
	else if(strpos($amount, '$') === 0) $amount = trim(substr($amount, 1));
	*/
	
	return dollarAmount((float)$amount, $cents=true, $nullRepresentation='', $nbsp=' ');
}
	
function getAddress($client, $prefix) {
	foreach(array('street1','street2','city','state','zip') as $field) $address[$field] = $client["$prefix$field"];
	return $address;
}



function dumpCurrentPastInvoiceSummaries($invoiceid) {}// Invoice #, Date, Balance Due
function dumpMessage($invoice) {
	global $lineitems;
if($_SESSION['staffuser']) {
//print_r($lineitems);	
	if($lineitems && in_array('relitemnote', fetchCol0("SHOW TABLES"))) {
		require_once "item-note-fns.php";

		foreach((array)$lineitems as $lineitem) {
			if($lineitem['chargeid']) {
				$id = $lineitem['chargeid'];
				$itemtable = 'tblothercharge';
				$description = 'Misc. Charge '.$lineitem['reason'];
			}
			else if($lineitem['surchargeid']) {
				$id = $lineitem['surchargeid'];
				$itemtable = 'tblsurcharge';
				$description = 'Surcharge '.$lineitem['service'];
			}
			else if($lineitem['appointmentid']) {
				$id = $lineitem['appointmentid'];
				$itemtable = 'tblappointment';
				$description = 'Visit '.$lineitem['service'];
			}
			else if($lineitem['packageid']) {
				$id = $lineitem['packageid'];
				$itemtable = 'tblrecurringpackage';
				$description = 'Monthly Package'.date('F Y', strtotime($lineitem['monthyear']));
			}
			else continue;
			$lineitem['itemtable'] = $itemtable;
			$lineitem['itemptr'] = $id;
			$lineitem['description'] = $description;
			//$pairs[] = "(itemtable = '$itemtable' AND itemptr = $id)";
			$pairs[] = array($itemtable, $id);
			$itemsByPair["$itemtable"."_$id"] = $lineitem;
		}
		$itemnotes = getItemNotesForList($pairs);
		if(!$itemnotes) return;
		foreach($itemnotes as $inote) {
			if(!$inote['note']) continue;
			else $atLeastOneInote = 1;
			$lineitem = $itemsByPair["{$inote['itemtable']}_{$inote['itemptr']}"];
//echo "[{$inote['itemtable']}_{$inote['itemptr']}]: ".print_r($lineitem, 1)."<br>";			
			$rows[] = array('date'=>$lineitem['date'], 'description'=>$lineitem['description'], 'charge'=>$lineitem['charge']);
			$note = $inote['note'];
			if(strpos($note, "\n") !== FALSE && strpos($note, "<") === FALSE) {
				$note = str_replace("\n\n", "<p>", $note);
				$note = str_replace("\n", "<br>", $note);
			}
			$rows[] = array('#CUSTOM_ROW#'=> "<tr><td style='border-bottom: solid black 1px' class='' colspan=3>$note</td></tr>");
		}
		if(!$atLeastOneInote) return;
		echo "<table width='95%'>";
		echo "<tr><td colspan=2>";
		dumpSectionBar("Notes", '');
		echo "</td></tr><tr><td>";
		$columns = explodePairsLine('date|Date||description|Description||charge|Charge');
		tableFrom($columns, $rows, "WIDTH=75%",null,null,null,null,$colSorts,$rowClasses, $colClasses, 'sortInvoices');

	}
}
}// should we add a message field to invoice?
function dumpFooter() {}
	