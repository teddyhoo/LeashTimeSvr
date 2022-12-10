<? // refund-fns.php
/*
CREATE TABLE IF NOT EXISTS `relrefundcredit` (
  `refundptr` int(10) unsigned NOT NULL auto_increment,
  `creditptr` int(10) unsigned NOT NULL default '0',
  `amount` float(5,2) NOT NULL,
  PRIMARY KEY  (`refundptr`,`creditptr`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 
*/



define('LT_EXCESSIVE_CHARGE', 1);

function getClientRefundsSince($clientid, $date) {
	return fetchAssociations(
		"SELECT *, amount 
			FROM tblrefund 
			WHERE clientptr = $clientid AND issuedate >= '$date'
			ORDER BY issuedate ASC");
}

function fetchClientRefundsSinceLastInvoice($clientid, $all=true) {
	$allFilter = $all ? '' : "AND consumedcredit > 0";
	return fetchAssociations(
		"SELECT tblrefund.*, sum(relrefundcredit.amount) as consumedcredit
		 FROM tblrefund 
		 LEFT JOIN relinvoicerefund ON relinvoicerefund.refundptr = refundid 
		 LEFT JOIN relrefundcredit ON relrefundcredit.refundptr = refundid
		 WHERE invoiceptr IS NULL AND tblrefund.clientptr = $clientid $allFilter
		 GROUP BY refundid
		 ORDER BY issuedate");
}

/*
CREATE TABLE IF NOT EXISTS `relinvoicerefund` (
  `invoiceptr` int(11) NOT NULL,
  `refundptr` int(11) NOT NULL,
  PRIMARY KEY  (`invoiceptr`,`refundptr`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

*/



function refundListTable($refunds, $oneClient=false) {
	if(!$refunds) {
		echo "No refunds found.";
		return;
	}
	$clientIds = array();
	foreach($refunds as $refund) $clientIds[] = $refund['clientptr'];
	$clients = getClientDetails($clientIds);
	$columns = explodePairsLine('issuedate|Date||client|Client||amount|Amount||reason|Note||creditcard|Credit Card');
	$colSorts = $oneClient ? array('date'=>null) : array();
	if($oneClient) {
		unset($columns['client']);
	}
	$colClasses = array('amount'=>'dollaramountcell');
	$rows = array();
	foreach($refunds as $refund) {
		$row = array();
		$row['issuedate'] = shortDate(strtotime($refund['issuedate']));
		$row['client'] = fauxLink($clients[$refund['clientptr']]['clientname'], "viewClient({$refund['clientptr']})", 'View this client', 1);
		$row['amount'] = refundLink($refund);
		$row['creditcard'] = strpos($refund['sourcereference'] , 'CC: ') === 0 ? substr($refund['sourcereference'] , strlen('CC: ')) : '';
		// if refund for a payment, link to payment here
		$row['reason'] = $row['paymentptr'] ? refundLink($refund)." {$refund['reason']}" : $refund['reason'];  
		
		$rows[] = $row;
	}
	//$colClasses['amount'] = 'amountcolumn';
	$colClasses = array('amount'=>'dollaramountheader');
	//echo "<style>.amountcolumn {width: 150px;}</style>\n";
	tableFrom($columns, $rows, 'WIDTH=100% ',null,null,null,null,$colSorts,null, $colClasses, 'sortInvoices');
}

function refundLink($refund) {
	return fauxLink(dollarAmount($refund['amount']), "editRefund({$refund['refundid']})", "Edit this refund", 1);
}

function paymentLink($paymentptr) {
	return fauxLink('Payment', "editPayment($paymentptr)", "View this payment", 1);
}

function refundEPayment($clientid, $ccOrAch, $amount, $reason, $sendReceipt=null, $paymentptr=null, $paymentCCTransactionId=null) {
	global $greatestCCPayment, $ccDebug;
	require_once("cc-processing-fns.php");
	if(!$ccOrAch) {
		$ccOrAch = getPrimaryPaySource($clientid);
	}
	if(!$ccOrAch) return array('FAILURE'=>'No payment method found for client.');
	
	$email = fetchRow0Col0("SELECT email FROM tblclient WHERE clientid = $clientid LIMIT 1");
	if($email) $ccOrAch['email'] = $email;
	if($ccOrAch['ccid']) $ccOrAch['cardcompany'] = guessCreditCardCompany($ccOrAch['x_card_num']);
	
	if($ccOrAch['ccid']) {
		$sourceId = $ccOrAch['ccid'];
		$changeLogTable = 'ccrefund';
		$refPrefix = 'CC: ';
		$institution = $ccOrAch['company'];
		$accttype = 'credit card';
		$description = "credit card ({$ccOrAch['company']} ************{$ccOrAch['last4']})";
	}
	else {
		$sourceId = $ccOrAch['acctid'];
		$changeLogTable = 'achrefund';
		$refPrefix = 'ACH: ';
		$institution = $ccOrAch['abacode'];
		$accttype = 'bank account';
		$description = "bank account (************{$ccOrAch['last4']})";
	}
	if($amount > $greatestCCPayment) return array('FAILURECODE'=>LT_EXCESSIVE_CHARGE);
	/*if(staffOnlyTEST()) */$payment = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $paymentptr LIMIT 1");
	// Calculate TOTAL original payment amount, including gratuities
	$gratuityTotal = fetchRow0Col0("SELECT IFNULL(SUM(amount), 0) FROM tblgratuity WHERE paymentptr = $paymentptr", 1);
	$success = makeERefund($amount, $ccOrAch, $paymentCCTransactionId, $payment['amount']+$gratuityTotal);
//echo "[".print_r($success,1)."]";exit;
	if(is_array($success)) { // failure
		if($ccDebug) print_r($success);
		logChange($sourceId, $changeLogTable, 'f', "Failed refund for trans: $paymentCCTransactionId");
		logChange($sourceId, $changeLogTable, 'f', ccErrorLogMessage($success, $amount));
		return $success;
	}
	$transactionid = $success;
	if($amount != $payment['amount']) {
		$reason = trim(join(' ', array($reason, "(returned ".number_format($amount, 2).")")));
	}
	$refund = array('paymentptr'=>$paymentptr,'externalreference'=>"$refPrefix$transactionid", 
										'sourcereference'=>"$refPrefix{$ccOrAch['last4']} ($institution)", 'reason'=>$reason, 
										'amount'=>$amount, 'clientptr'=>$clientid, 'issuedate'=>date('Y-m-d'));
	$refundid = insertTable('tblrefund', $refund, 1);
	//require_once("invoice-fns.php"); // for getCurrentBillablesInvoicedFirst()
	logChange($refundid, 'tblrefund', 'c', $clientid);
	logChange($sourceId, $changeLogTable, 'p', "Approved-$amount|$success");
	
	if($sendReceipt === null) { // override only if 1 or 0
		require_once "preference-fns.php";
		$sendReceipt = getClientPreference($clientid, 'autoEmailCreditReceipts');
	}
	
	if($sendReceipt) {
		require_once "client-fns.php";
		$client = getOneClientsDetails($clientid, array('email'));
		if($client['email']) {
			$names = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('bizName', 'shortBizName')", 1);
			$bizName = $names['bizName'] ? $names['bizName'] : (
									$names['shortBizName'] ? $names['shortBizName'] : $_SESSION['bizName']);
			require_once "comm-fns.php";
			$message = "Dear {$client['clientname']},<p>This note is to inform you that we have refunded to your $description"
									. " the amount of ".dollarAmount($amount)
									. ".  (Transaction #$transactionid)\nThank you for your business.<p>Sincerely,<p>$bizName";
			enqueueEmailNotification($client, "Refund to your $accttype", $message, $cc=null, $_SESSION["auth_login_id"], $hasHtml=true);
		}
	}
	return array('TRANSACTIONID'=>$transactionid, 'REFUNDID'=>$refundid);
}

function refundCredits(&$credits, $refundptr, $upToAmount) { // called repeatedly after a new credit is added
	if($upToAmount == 0)  return 0;
	$totalSpent = 0;
	foreach($credits as $index => $credit) {
		if($credits[$index]['amountleft'] <= 0) continue;
		$spent = min($upToAmount, $credits[$index]['amountleft']);
		$totalSpent += $spent;
		$credits[$index]['amountused'] += $spent;
		$credits[$index]['amountleft'] -= $spent;
		$mods = array('amountused'=>$credits[$index]['amountused']);
		updateTable('tblcredit', addModificationFields($mods), "creditid = {$credit['creditid']}", 1);
		$upToAmount -= $spent;
		insertTable('relrefundcredit', array('refundptr'=>$refundptr, 'creditptr'=>$credit['creditid'], 'amount'=>$spent), 1);
		
		if($upToAmount <= 0) return $totalSpent;
	}
	return $totalSpent;
}

