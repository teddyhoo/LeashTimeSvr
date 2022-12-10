<? // ajax-charge-client.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "billing-fns.php";
require_once "invoice-fns.php";
require_once "cc-processing-fns.php";

locked('o-');
extract($_REQUEST);

$error = false;
// User has hit the "Charge Credit Card" button
// Confirm !expireCCAuthorization
if(!adequateRights('*cc')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
	echo "failed##-##Insufficient Access Rights to charge client's card.";
	exit;
}

$loginNeeded = is_array($expiration = expireCCAuthorization());
if($loginNeeded) {
	echo "failed##-##Not logged in to charge client credit cards.";
	exit;
}

$success = payElectronically($client, null, $amount, $reason);
if(is_array($success)) {
	if($success['FAILURECODE']) {
		chargeLogClientEntry($client, $amount, 'failed', $success['FAILURECODE']);
		echo "failed##-##{$success['FAILURECODE']}";
	}
	if($success['FAILURE']) {
		chargeLogClientEntry($client, $amount, 'failed', $success['FAILURE']);
		echo "failed##-##{$success['FAILURE']}";
	}
	else {
		chargeLogClientEntry($client, $amount, 'failed', (ccTransactionId($success) ? 'Trans #'.ccTransactionId($success)+': ' : '').ccLastMessage($success));
		echo "failed##".ccTransactionId($success)."##".ccLastMessage($success)." ";//."$lastRawCCResponse --".print_r($lastCCResponse, 1);
	}
}
else {
	echo "approved##$success## ";
	if($invoiceSectionKey) { 
		// problem, we know the client, but we do not have the other necessary details.  We need to
		// capture them in the initial call to cc-charge-clients.php, or before (in billimgMATT.php)
		chargeLogClientEntry($client, $amount, 'approved', $success);
		$invoiceSectionInfo = $_SESSION[$invoiceSectionKey]; // from billing.php via cc-charge-clients.  e.g., invoiceArgs_recurring
		if(!$invoiceSectionInfo) $error = "Invoice info not found (ajax-charge-client). key: $invoiceSectionKey client: $client";
		//if($invoiceSectionInfo && $invoiceSectionInfo['clientid'] != $client)  
		//	$error = "Invoice info client id mismatch (ajax-charge-client). key: $sendInvoice client: {$paidInvoiceToEmail['clientid']} != $client";
		if($error) logError($error);
		else {
			$invoicePaymentReference = $invoiceSectionInfo;
			$invoicePaymentReference['clientid'] = $client;
			$invoicePaymentReference['paymentptr'] = $latestPaymentId; // $latestPaymentId is from applyEPayment
			include "billing-invoice-email.php";
		}
	}
}

function chargeLogClientEntry($client, $amount, $status, $note) {
	$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $client LIMIT 1", 1);
	$clearCCs = getClearCCs(array($client));
	$cc = $clearCCs[$client];
	if($cc) {
		$cardLabel = $autopay = $cc['autopay'] ? ' [auto]' : '';
		$expiration = $cc['acctid'] ? '' : "Exp: ".shortExpirationDate($cc['x_exp_date']);
		$ccLabel  = "{$cc['company']} ************{$cc['last4']} ".$expiration.$cardLabel;
	}
	else $ccLabel = 'None';
	
	chargeLog("<tr><td>$status</td><td>$clientname</td><td>".dollarAmount($amount)."</td><td>$ccLabel</td><td>$note</td></tr>\n");
	//status|Status||clientname|Client||chargedollars|Charge||ccLabel|Credit Card||note|Note
}