<? // ajax-invoice-charge-client.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-fns.php";
require_once "cc-processing-fns.php";
require_once "comm-fns.php";

locked('o-');
extract($_REQUEST); // args: client, amount, reason, asOfDate, sendReceipt

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
	if($success['FAILURECODE']) echo "failed##-##{$success['FAILURECODE']}";
	else echo "failed##".ccTransactionId($success)."##".ccLastMessage($result)." ";//."$lastRawCCResponse --".print_r($lastCCResponse, 1);
}
else {
	$transactionid = $success;
	// if successful, create the invoice and email a receipt
	//$ccPayment format: amount|transactionId|ccid|company|last4
	$cc = getClearPrimaryPaySource($client);
	$sourceid = $cc['ccid'] ? $cc['ccid'] : $cc['acctid'];
	$company = $cc['ccid'] ? $cc['company'] : 'Account:';
	$last4 = $cc['ccid'] ? $cc['last4'] : $cc['acctnum'];
	$invoiceid = createCustomerInvoiceAsOf($client, $asOfDate, "$amount|$transactionid|$sourceid|$company|$last4");
	if($sendReceipt) {
		$messageSuccess = emailInvoice($client, $invoiceid);
		if(!$messageSuccess) $messageSuccess = ' ';
	}
	// register the payment
	// PAYMENT IS REGISTERED IN payElectronically -- applyEPayment($client, $cc, $amount, "Auto-Pay", $transactionid, $sendReceipt);
	
	
	
	
	
	
	echo "approved##$success##$messageSuccess";
}

function emailInvoice($client, $invoiceid) {
	global $clientDetails;
	if(!is_array($client)) {
		$client = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient WHERE clientid = $client");
	}
	if(!$client['email']) return "No email address found for {$client['clientname']}.\n";
	$standardInvoiceMessage = "Hi ##FirstName## ##LastName##,<p>Here is your latest invoice reflecting the latest charge to your creditc card.<p>Sincerely,<p>##BizName##";
	$standardMessageSubject = "Your Invoice";


	if(strpos($standardInvoiceMessage, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		$client['petnames'] = getClientPetNames($client['clientid', false, true);
	}
	$msgbody = mailMerge($standardInvoiceMessage, 
												array('##FullName##' => $client['clientname'],
															'##FirstName##' => $client['fname'],
															'##LastName##' => $client['lname'],
															'##BizName##' => $_SESSION['bizname'],
															'#RECIPIENT#' => $client['clientname'],
															'#FIRSTNAME#' => $client['fname'],
															'#LASTNAME#' => $client['lname'],
															'#BIZNAME#' => $_SESSION['bizname'],
															'#PETS#' => $client['petnames']
															));
	$msgbody = plainTextToHtml($msgbody);						

	$msgbody =  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> '.
	getInvoiceContents($invoiceid);

	updateTable('tblinvoice', array('notification'=>'email', 'lastsent'=>date('Y-m-d')), "invoiceid = $invoiceid");

	//if(notifyByEmail($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html'))
	//	echo "Failed to email invoice to {$client['clientname']} ({$client['email']}):\n$error\n";
	//else $invoiceCount++;
	enqueueEmailNotification($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html');
	return null;
}
