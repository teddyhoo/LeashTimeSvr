<? // prepayment-invoice-email.php
/* Four Modes:
Automatic - called by AJAX: 
	GET with ids and send=1: automatically email all invoices
	echos status.
Automatic - included by another script: 
	isset(ids): automatically email all invoices
	echos status.
Manual 
	GET with id and no send - open a composer for one invoice
	POST - to close composer and send email
*/
set_time_limit(15 * 60);


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "prepayment-fns.php";
require_once "comm-composer-fns.php";


if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 

$emailFromName = $_SESSION['preferences']['invoiceEmailsFromCurrentManager'] ? getUsersFromName() : null;


//$excludeStylesheets = false;
$messageTag = getPrepaidInvoiceTag();
if(isset($ids) || ($_GET && $_GET['ids'] && $_GET['send'] == 1)) { // Automatic mode
	if($_GET) extract($_GET);
	$clients = explode(',',$ids);
	set_time_limit(min(30, count($clients)*2));
	$includePriorUnpaidBillables = !$excludePriorUnpaidBillables;
	// automatic emailing
	$clientDetails = getClientDetails($clients, array('lname','fname', 'email'));
	$invoiceCount = 0;
	foreach($clients as $clientid) {
		$client = $clientDetails[$clientid];
		if(!$client['email']) {
			echo "No email address found for {$client['clientname']}.\n";
			continue;
		}
		$msgbody = preprocessMessage($standardInvoiceMessage, $client);
		
		$invoice = getPrepaidInvoice($clientid, $firstDay, $lookahead, $showOnlyCountableItems=true, $scope); // when set, $scope may be a NR packageid
		//global $origbalancedue, $creditApplied, $tax, $credits;
		$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
		
		$msgbody = mailMerge($msgbody, 
											array(
														'#AMOUNTDUE#' => $invoice['']
														));
		

		$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.
		//getInvoiceContents($invoiceid);
		
		getPrepaidInvoiceContents($invoice, $firstDay, $lookahead, $showOnlyCountableItems=true, $includePriorUnpaidBillables);
		
		//if(notifyByEmail($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html'))
		//	echo "Failed to email invoice to {$client['clientname']} ({$client['email']}):\n$error\n";
		//else $invoiceCount++;
//if($db == 'yourdogsmiles') $TESTEMAILOVERRIDE = 'ted@leashtime.com';
		
		enqueueEmailNotification($client, $standardMessageSubject, $msgbody, null, $emailFromName, 'html', null, $messageTag); // $_SESSION["auth_login_id"]
		$invoiceCount++;
	}
	echo "$invoiceCount invoice".($invoiceCount ==  1 ? '' : 's')." emailed.";
	exit;
}

if($_POST) {  // Manual mode - send email
	//print_r($_POST);
	$auxiliaryWindow = true; // prevent login from appearing here if session times out
	extract($_REQUEST);
	$includePriorUnpaidBillables = !$excludePriorUnpaidBillables;

	$properties = explode('|', $properties);
	$preprocessedMessage = preprocessMessage($msgbody, getClient($_POST['correspid']));
	$invoice = getPrepaidInvoice($correspid, $firstDay, $lookahead, ($properties[5] ? true : false), $scope); // when set, $scope may be a NR packageid
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($invoice); }
	
	//global $origbalancedue, $creditApplied, $tax, $credits;
	$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);

	$preprocessedMessage = mailMerge($preprocessedMessage, 
										array(
													'#AMOUNTDUE#' => $invoice['']
														));
	
	$msgbody = $preprocessedMessage;
	//$msgbody = plainTextToHtml($msgbody);						
	$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.
	getPrepaidInvoiceContents($invoice, $properties[1], $properties[3], $showOnlyCountableItems=true, $includePriorUnpaidBillables);
	//print_r($outboundMessage);exit;
	$recipients = array("\"$corresname\" <$correspaddr>");
	if($_POST['clientemail2']) $recipients[] = $_POST['clientemail2'];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $error = "TEST ERROR"; }
	if(!$error) {
		$error = sendEmail($recipients, $subject, $msgbody, null, 'html', $emailFromName);
	}

	if($error) {
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
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
}
}

// Manual mode - open composer
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";
require_once "client-fns.php";
extract($_REQUEST);
$includePriorUnpaidBillables = !$excludePriorUnpaidBillables;
$client = $_GET['ids'];
$wholeClient = getClient($client);
//foreach((array)getClientLoginCreds($client) as $k => $v)
//	$wholeClient[$k] = $v;


$messageBody = $failedMessageBody ? $failedMessageBody 
								: preprocessMessage($standardInvoiceMessage, $wholeClient);
$invoice = getPrepaidInvoice($client, $firstDay, $lookahead, $showOnlyCountableItems=true, $scope); // when set, $scope may be a NR packageid
//global $origbalancedue, $creditApplied, $tax, $credits;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($invoice); }
$amountDue = $origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
$messageBody = mailMerge($messageBody, 
									array(
												'#AMOUNTDUE#' => $amountDue
													));
								
								
								
$messageBody = htmlToPlainText($messageBody);
$messageSubject = $standardMessageSubject;
$properties = "firstDay|{$_REQUEST['firstDay']}|lookahead|{$_REQUEST['lookahead']}|includePriorUnpaidBillables|$includePriorUnpaidBillables";
$tags = $messageTag;


include "comm-composer.php";