<? // billing-invoice-email.php
/* Five Modes:
Automatic - called by AJAX: 
	GET with ids and send=1: automatically email all invoices
	echos status.
Automatic - included by another script: 
  if $invoicePaymentReference, send out
  	echos nothing
	isset(ids): automatically email all invoices
		echos status.
Manual 
	GET with id and no send - open a composer for one invoice
	POST - to close composer and send email
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "billing-fns.php";
require_once "comm-composer-fns.php";

if(FALSE && !staffOnlyTEST()) {
	echo "Under construction.  Please check in again later.";
	exit;
}

if($_GET['paymentptr']) 
	$invoicePaymentReference = array('clientid'=>$_GET['ids'], 'paymentptr'=>$_GET['paymentptr']);

if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 

//$excludeStylesheets = false;
$messageTag = getBillingInvoiceTag();
if(isset($ids) || ($_GET && $_GET['ids'] && $_GET['send'] == 1)) { // Automatic mode
	if($_GET) extract($_GET);
	// automatic emailing
	$clients = explode(',',$ids);
	$clientDetails = getClientDetails($clients, array('lname','fname', 'email'));
	$invoiceCount = 0;
	foreach($clients as $clientid) {
		$client = $clientDetails[$clientid];
		if(!$client['email']) {
			echo "No email address found for {$client['clientname']}.\n";
			continue;
		}
		$msgbody = preprocessMessage($standardInvoiceMessage, $client);
		
		$invoice = getBillingInvoice($clientid, $firstDay, $lookahead, $literal, $showOnlyCountableItems=true, $_REQUEST['packageptr']);
		$invoice['clientid'] = $clientid;
}	
		//$invoice = getPrepaidInvoice($clientid, $firstDay, $lookahead, $showOnlyCountableItems=true, null);
		//global $origbalancedue, $creditApplied, $tax, $credits;
		$amountDue = calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
		
		$msgbody = mailMerge($msgbody, 
											array(
														'#AMOUNTDUE#' => $amountDue
														));
		

		if($_REQUEST['includePayNowLink']) {
			$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>calculateAmountDue()); // calculateAmountDue() uses gloabls from getBillingInvoice
		}
		$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.
		//getInvoiceContents($invoiceid);
		
		//function getBillingInvoiceContents($clientid, $firstDay, $lookahead, $literal=false, $showOnlyCountableItems=false, $includePayNowLink=false) 
		getBillingInvoiceContents($invoice, $firstDay, $lookahead, $literal, $showOnlyCountableItems=true, $payNowInfo, $_REQUEST['packageptr']);
		//if(notifyByEmail($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html'))
		//	echo "Failed to email invoice to {$client['clientname']} ({$client['email']}):\n$error\n";
		//else $invoiceCount++;
//if($db == 'yourdogsmiles') $TESTEMAILOVERRIDE = 'ted@leashtime.com';
		
		enqueueEmailNotification($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html', null, $messageTag);
		$invoiceCount++;
	}
	echo "$invoiceCount invoice".($invoiceCount ==  1 ? '' : 's')." emailed.";
	exit;
}

if($invoicePaymentReference['section']) {  // Auto mode - send email with a paid invoice
	//invoicePaymentReference includes firstDay, lookahead, includePayNowLink
	$auxiliaryWindow = true; // prevent login from appearing here if session times out
	$clientid = $invoicePaymentReference['clientid'];
	$client = getClient($clientid);
	$message = getPaidInvoiceSubjectAndMessage();
	$preprocessedMessage = preprocessMessage($message['body'], getClient($client));

	$invoice = getBillingInvoice(
							$clientid, 
							$invoicePaymentReference['firstDay'], 
							$invoicePaymentReference['lookahead'], 
							$invoicePaymentReference['literal'], 
							$showOnlyCountableItems=true);
	
	$amountDue = calculateAmountDue();
	$preprocessedMessage = mailMerge($preprocessedMessage, 
										array(
													'#AMOUNTDUE#' => $amountDue
														));
	
	$msgbody = $preprocessedMessage;

	$invoice['clientid'] = $clientid;
	//if($_REQUEST['includePayNowLink']) {
	//	$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>$amountDue); // calculateAmountDue() uses gloabls from getBillingInvoice
	//}
	$invoiceContents = getBillingInvoiceContents(
											$invoice, 
											$invoicePaymentReference['firstDay'], 
											$invoicePaymentReference['lookahead'], 
											$invoicePaymentReference['literal'], 
											$showOnlyCountableItems=true
											//$payNowInfo
											);
											
	$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.$invoiceContents;

	$recipients = array("\"{$client['fname']} {$client['lname']}\" <{$client['email']}>");
	//if($_POST['clientemail2']) $recipients[] = $_POST['clientemail2'];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $error = "TEST ERROR"; }
	$result = enqueueEmailNotification($client, $message['subject'], $msgbody, null, $_SESSION["auth_login_id"], 'html', null, $messageTag);

	if(is_array($result)) {
		logError('cc-charge-clients>billing-invoice-email: '.$result[0]);
	}
	else {
		$outboundMessage = array_merge();
		$outboundMessage['subject'] = $message['subject'];
		$outboundMessage['body'] = $msgbody;
		$outboundMessage['tags'] = $messageTag;
		$outboundMessage['correspaddr'] = $client['email'];
		$outboundMessage['correspid'] = $clientid;
		$outboundMessage['correstable'] = 'tblclient';
		
		saveOutboundMessage($outboundMessage);
	}
}


if($_POST) {  // Manual mode - send email
	//print_r($_POST);
	$auxiliaryWindow = true; // prevent login from appearing here if session times out
	extract($_REQUEST);

	$properties = explode('|', $properties);
	$preprocessedMessage = preprocessMessage($msgbody, getClient($_POST['correspid']));
	//$invoice = getPrepaidInvoice($correspid, $firstDay, $lookahead, ($properties[5] ? true : false));
	$invoice = getBillingInvoice($correspid, $firstDay, $lookahead, $literal, $showOnlyCountableItems=true, $_REQUEST['packageptr']);
	
	//global $origbalancedue, $creditApplied, $tax, $credits;
	$amountDue = calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
	$preprocessedMessage = mailMerge($preprocessedMessage, 
										array(
													'#AMOUNTDUE#' => $amountDue
														));
	
	$msgbody = $preprocessedMessage;
	//$msgbody = plainTextToHtml($msgbody);
	$invoice['clientid'] = $correspid;
	if($_REQUEST['includePayNowLink']) {
		$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>calculateAmountDue()); // calculateAmountDue() uses gloabls from getBillingInvoice
	}
	$invoiceContents = getBillingInvoiceContents($invoice, $firstDay, $lookahead, $literal, $showOnlyCountableItems=true, $payNowInfo, $_REQUEST['packageptr']);
//if(staffOnlyTEST()) {echo "[[{$_REQUEST['packageptr']}]]<p>";print_r($invoiceContents); exit;}
	$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.$invoiceContents;
	//getPrepaidInvoiceContents($invoice, $properties[1], $properties[3], $showOnlyCountableItems=true, $includePriorUnpaidBillables);

	$recipients = array("\"$corresname\" <$correspaddr>");
	if($_POST['clientemail2']) $recipients[] = $_POST['clientemail2'];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $error = "TEST ERROR"; }
	if($error || ($error = sendEmail($recipients, $subject, $msgbody, null, 'html'))) {
		$failedMessageBody = $preprocessedMessage;
		$failedFROM = $_POST['mgrname']; //unused for now
		$failedSUBJECT = $subject;
		$failedEMAIL = $_POST['correspaddr'];
		$failedALTEMAIL = $_POST['clientemail2'];
		//echo "<font color='red'>Mail error:<p>$error</font><p>";
//if($_SERVER['REMOTE_ADDR'] != '68.225.89.173') 
//		exit;
	}
	else {
		$outboundMessage = array_merge($_POST);
		$outboundMessage['msgbody'] = $msgbody;
		$outboundMessage['tags'] = $messageTag;
		saveOutboundMessage($outboundMessage);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', $correspid);window.close();</script>";
		exit;
}
}

// Manual mode - open composer
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";
extract($_REQUEST);
$client = $_GET['ids'];
$messageBody = $failedMessageBody ? $failedMessageBody 
								: preprocessMessage($standardInvoiceMessage, getClient($client));
								
//$invoice = getPrepaidInvoice($client, $firstDay, $lookahead, $showOnlyCountableItems=true, null);
$invoice = getBillingInvoice($client, $firstDay, $lookahead, $literal, $showOnlyCountableItems=true, $_REQUEST['packageptr']);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($invoice); }
$amountDue = calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);

$messageBody = mailMerge($messageBody, 
									array(
												'#AMOUNTDUE#' => $amountDue
													));

$messageBody = htmlToPlainText($messageBody);
$messageSubject = $standardMessageSubject;
$properties = "firstDay|{$_REQUEST['firstDay']}|lookahead|{$_REQUEST['lookahead']}|literal|$literal|includePayNowLink|{$_REQUEST['includePayNowLink']}";
$tags = $messageTag;


include "comm-composer.php";