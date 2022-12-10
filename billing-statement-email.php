<? // billing-statement-email.php
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
set_time_limit(5 * 60);

if(FALSE && !staffOnlyTEST()) {
	echo "Under construction.  Please check in again later.";
	exit;
}

function processMessageAdHocSubstitutions($message) {
	if(mattOnlyTEST() || dbTEST('dogwalkingdc')) {
		require_once "email-template-fns.php";
		$message = processAdHocSubstitutions($message);
	}
	return $message;
}

$showOnlyCountableItems = TRUE  || staffOnlyTEST() ? false : true;

$suppressPriorUnpaidCreditMarkers =  true;  // Suppress [C] marker

$emailFromName = $_SESSION['preferences']['invoiceEmailsFromCurrentManager'] ? getUsersFromName() : null;

if($_GET['paymentptr']) 
	$invoicePaymentReference = array('clientid'=>$_GET['ids'], 'paymentptr'=>$_GET['paymentptr']);

if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 
//billing-statement-email.php?send=1&ids=2012&firstDay=2015-10-01&lookahead=30&includePayNowLink=1&literal=0
//$excludeStylesheets = false;
$messageTag = getBillingInvoiceTag();
if($_GET && $_GET['ids'] && $_GET['summary'] == 1) { // produce a SUMMARY
	if($_GET) extract($_GET);
	// automatic emailing
	$clients = explode(',',$ids);
	$clientDetails = getClientDetails($clients, array('lname','fname', 'email', 'userid'));
	$invoiceCount = 0;
	$excludePriorIDs = explode(', ', "{$_REQUEST['excludePriors']}");
	foreach($clients as $clientid) {
		$client = $clientDetails[$clientid];
		if(!$client['email']) {
			echo "No email address found for {$client['clientname']}.\n";
			continue;
		}

		require_once "billing-statement-display-class.php";
		$billingStatement = new BillingStatement($clientid);
		
		$excludePriorUnpaidBillables = in_array($clientid, $excludePriorIDs);
		$billingStatement->
			populateBillingInvoice($firstDay, $lookahead, 
						$literal, $showOnlyCountableItems, 
						$_REQUEST['packageptr'], ($excludePriorUnpaidBillables ? 1 : 0));
						
//print_r($billingStatement);exit;
		$amountDue = $billingStatement->calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
		
		$lines[] = array(
			'name'=>"{$clientDetails[$clientid]['fname']} {$clientDetails[$clientid]['lname']} (@$clientid)",
			'count'=>count($billingStatement->priorunpaiditems)+count($billingStatement->lineitems),
			'tax'=>dollarAmount($billingStatement->tax),
			'total'=>dollarAmount($amountDue));
		$visitCount += count($billingStatement->priorunpaiditems)+count($billingStatement->lineitems);
		$grandTotal += $amountDue;
	}
	
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Summary'");
	if($template) {
		$standardInvoiceMessage = $template['body'];
		$standardMessageSubject = $template['subject'];
	}
	else {
		$lastDate = shortDate(strtotime("+".($lookahead-1)." DAYS", strtotime($firstDay)));
		$standardInvoiceMessage = "Here is a summary of ".count($clients)." invoices covering the period "
															.shortDate(strtotime($firstDay)).' to '.$lastDate."<p>";
		$standardMessageSubject = "Invoice Summary";
	}

	$lines[] = explodePairsLine("name|Total||count|$visitCount||total|".dollarAmount($grandTotal));
	$columns = explodePairsLine('name|Client||count|Item Count||tax|Tax||total|Total');
	$headerclasses = explodePairsLine('count|number||tax|dollar||total|dollar');
	ob_start();
	ob_implicit_flush(0);
	echo "<style>.dollar {text-align: right} .number {text-align: center}</style>";
//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) 
	
	tableFrom($columns, $lines, 'WIDTH=90% BORDER=1 BORDERWIDTH=1 BORDERCOLOR=gray',
						null, null, null, null, null, null, $headerclasses);
	$summarytable = ob_get_contents();
	ob_end_clean();
	$messageBody = $standardInvoiceMessage.$summarytable;
	
	echo $messageBody;
	//include "comm-composer.php";

	//echo "<hr>$invoicePaymentReference";
	exit;
} // END OF SUMMARY
else if(isset($ids) || ($_GET && $_GET['ids'] && $_GET['send'] == 1)) { // Automatic mode
	if($_GET) extract($_GET);
	// automatic emailing
	$clients = explode(',',$ids);
	$clientDetails = getClientDetails($clients, array('lname','fname', 'email', 'userid'));
	$invoiceCount = 0;
	$excludePriorIDs = explode(', ', "{$_REQUEST['excludePriors']}");
	if($templateid) 
		$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = $templateid");
	else 
		$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email'");
	if($template) {
		$standardInvoiceMessage = $template['body'];
		$standardMessageSubject = $template['subject'];
	}
//if(mattOnlyTEST()) {print_r($standardInvoiceMessage);exit;}	
	
	foreach($clients as $clientid) {
		$client = $clientDetails[$clientid];
		if(!$client['email']) {
			echo "No email address found for {$client['clientname']}.\n";
			continue;
		}
		$msgbody = preprocessMessage($standardInvoiceMessage, $client);
		
		/*
		$invoice = getBillingInvoice($clientid, $firstDay, $lookahead, $literal, $showOnlyCountableItems, $_REQUEST['packageptr']);
		$invoice['clientid'] = $clientid;
		*/
		require_once "billing-statement-display-class.php";
		$billingStatement = new BillingStatement($clientid);
		
		$excludePriorUnpaidBillables = in_array($clientid, $excludePriorIDs);
		$billingStatement->
			populateBillingInvoice($firstDay, $lookahead, 
						$literal, $showOnlyCountableItems, 
						$_REQUEST['packageptr'], ($excludePriorUnpaidBillables ? 1 : 0));
						
		
//if(mattOnlyTEST()) {unset($invoice['lineitems']);unset($invoice['priorunpaiditems']);print_r($invoice);exit;}	
		//$invoice = getPrepaidInvoice($clientid, $firstDay, $lookahead, $showOnlyCountableItems=true, null);
		//global $origbalancedue, $creditApplied, $tax, $credits;
		$amountDue = $billingStatement->calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
		
		$msgbody = mailMerge($msgbody, 
											array(
														'#AMOUNTDUE#' => $amountDue
														));
		

		// NEXT TWO LINES ARE REDUNDANT, I THINK
		$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>$billingStatement->calculateAmountDue()); // calculateAmountDue() uses gloabls from getBillingInvoice
		$payNowLink = payNowLink($client, $payNowInfo);
		
		// NEXT THREE LINES ARE REDUNDANT, I THINK
		if($_REQUEST['includePayNowLink']) {
			$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>calculateAmountDue());
		}
		
//	function getBillingStatementContents($literal=false, $showOnlyCountableItems=false, $includePayNowLink=false, $packageptr=null, $excludePriorUnpaid=false) 

	$billingDisplay = new BillingStatementDisplay($billingStatement);
/*	$invoiceContent = $billingDisplay->
				getBillingStatementContents($invoicePaymentReference['literal'], $showOnlyCountableItems=false, $includePayNowLink=$payNowInfo, 
				$_REQUEST['packageptr'], $excludePriorUnpaidBillables);*/
		$invoiceContent = $billingDisplay->
				getBillingStatementContents($literal, $showOnlyCountableItems=false, $includePayNowLink=$_REQUEST['includePayNowLink'], 
				$_REQUEST['packageptr'], $excludePriorUnpaidBillables);
		

		$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.$invoiceContent;

		$msgbody = processMessageAdHocSubstitutions($msgbody);
		//getInvoiceContents($invoiceid);
		
		//function getBillingInvoiceContents($clientid, $firstDay, $lookahead, $literal=false, $showOnlyCountableItems=false, $includePayNowLink=false) 

		//getBillingInvoiceContents($invoice, $firstDay, $lookahead, $literal, $showOnlyCountableItems, $payNowInfo, $_REQUEST['packageptr']);





		//if(notifyByEmail($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html'))
		//	echo "Failed to email invoice to {$client['clientname']} ({$client['email']}):\n$error\n";
		//else $invoiceCount++;
//if($db == 'yourdogsmiles') $TESTEMAILOVERRIDE = 'ted@leashtime.com';
		enqueueEmailNotification($client, $standardMessageSubject, $msgbody, null, $emailFromName, 'html', null, $messageTag); //$_SESSION["auth_login_id"]
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
//if(mattOnlyTEST()) print_r($_GET);	
	$message = getPaidInvoiceSubjectAndMessage($templateid);
	$preprocessedMessage = preprocessMessage($message['body'], getClient($client));

	require_once "billing-statement-display-class.php";
	$billingStatement = new BillingStatement($clientid);

	$excludePriorUnpaidBillables = in_array($clientid, $excludePriorIDs);
	$billingStatement->
		populateBillingInvoice($firstDay, $lookahead, 
					$literal, $showOnlyCountableItems, 
					$_REQUEST['packageptr'], ($excludePriorUnpaidBillables ? 1 : 0));
	$amountDue = $billingStatement->calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);

	/*$invoice = getBillingInvoice(
							$clientid, 
							$invoicePaymentReference['firstDay'], 
							$invoicePaymentReference['lookahead'], 
							$invoicePaymentReference['literal'], 
							$showOnlyCountableItems=true);
	
	$amountDue = calculateAmountDue();*/
	$preprocessedMessage = mailMerge($preprocessedMessage, 
										array(
													'#AMOUNTDUE#' => $amountDue
														));
	
	$msgbody = $preprocessedMessage;

	//$invoice['clientid'] = $clientid;
	//if($_REQUEST['includePayNowLink']) {
	//	$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>$amountDue); // calculateAmountDue() uses gloabls from getBillingInvoice
	//}
	$billingDisplay = new BillingStatementDisplay($billingStatement);
	$invoiceContent = $billingDisplay->
				getBillingStatementContents($invoicePaymentReference['literal'], $showOnlyCountableItems=false, $includePayNowLink=$payNowInfo, 
				$_REQUEST['packageptr'], $excludePriorUnpaidBillables);

	/*$invoiceContents = getBillingInvoiceContents(
											$invoice, 
											$invoicePaymentReference['firstDay'], 
											$invoicePaymentReference['lookahead'], 
											$invoicePaymentReference['literal'], 
											$showOnlyCountableItems
											//$payNowInfo
											);*/
											
	$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.$invoiceContent;

	$msgbody = processMessageAdHocSubstitutions($msgbody);


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

	//$properties = explode('|', $properties);
	$preprocessedMessage = preprocessMessage($msgbody, getClient($_POST['correspid']));
	//$invoice = getPrepaidInvoice($correspid, $firstDay, $lookahead, ($properties[5] ? true : false));
	//$invoice = getBillingInvoice($correspid, $firstDay, $lookahead, $literal, $showOnlyCountableItems, $_REQUEST['packageptr']);
	//global $origbalancedue, $creditApplied, $tax, $credits;
	//$amountDue = calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
	require_once "billing-statement-display-class.php";
	$billingStatement = new BillingStatement($correspid);
	$properties = explode('|',$properties);
	for($i=0; $i < count($properties)-1; $i+=2) $statementProps[$properties[$i]] = $properties[$i+1];
	$billingStatement->
		populateBillingInvoice($statementProps['firstDay'], $statementProps['lookahead'], 
					$statementProps['literal'], $statementProps['showOnlyCountableItems'], 
					$statementProps['packageptr'], $statementProps['excludePriors']);
	$amountDue = $billingStatement->calculateAmountDue();

	$preprocessedMessage = mailMerge($preprocessedMessage, 
										array(
													'#AMOUNTDUE#' => $amountDue
														));
	
	$msgbody = $preprocessedMessage;
	//$msgbody = plainTextToHtml($msgbody);
	$invoice['clientid'] = $correspid;
	if($_REQUEST['includePayNowLink'])
		$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>$amountDue);
	//$invoiceContents = getBillingInvoiceContents($invoice, $firstDay, $lookahead, $literal, $showOnlyCountableItems, $payNowInfo, $_REQUEST['packageptr']);
	$billingDisplay = new BillingStatementDisplay($billingStatement);
	$invoiceContent = $billingDisplay->
			getBillingStatementContents($statementProps['literal'], $showOnlyCountableItems=false, $payNowInfo, 
			$_REQUEST['packageptr'], $statementProps['excludePriors']);

//if(staffOnlyTEST()) {echo "[[{$_REQUEST['packageptr']}]]<p>";print_r($invoiceContents); exit;}
	$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.$invoiceContent;
	//getPrepaidInvoiceContents($invoice, $properties[1], $properties[3], $showOnlyCountableItems=true, $includePriorUnpaidBillables);

	$msgbody = processMessageAdHocSubstitutions($msgbody);

	$recipients = array("\"$corresname\" <$correspaddr>");
	if($_POST['clientemail2']) $recipients[] = $_POST['clientemail2'];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $error = "TEST ERROR"; }
	if($error || ($error = sendEmail($recipients, $subject, $msgbody, null, 'html', $emailFromName))) {
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
// example source: billing-statement-email.php?amount=1&ids=771&firstDay=2015-10-01&lookahead=30&literal=&packageptr=&excludePriors=&includePayNowLink=1&paymentptr=
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";
require_once "email-template-fns.php";
extract($_REQUEST);
$clientid = $_GET['ids'];
if(FALSE && $templateid) { // TBD
	/*if($template = blah blah) {
		$thisInvoiceMessage = $template['body'];
		$thisInvoiceSubject = $template['subject'];
	}*/
}
else {
	$thisInvoiceMessage = $standardInvoiceMessage;
	$thisInvoiceSubject = $standardMessageSubject;
}
$messageBody = $failedMessageBody ? $failedMessageBody 
								: preprocessMessage($thisInvoiceMessage, getClient($clientid));
								
//$invoice = getPrepaidInvoice($client, $firstDay, $lookahead, $showOnlyCountableItems=true, null);
//$invoice = getBillingInvoice($client, $firstDay, $lookahead, $literal, $showOnlyCountableItems, $_REQUEST['packageptr']);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($invoice); }
//$amountDue = calculateAmountDue();//$origbalancedue - $creditApplied + $tax - $credits + priorUnpaidItemTotal($invoice);
require_once "billing-statement-display-class.php";
$billingStatement = new BillingStatement($clientid);
$billingStatement->
	populateBillingInvoice($firstDay, $lookahead, 
				$literal, $showOnlyCountableItems, 
				$_REQUEST['packageptr'], $excludePriors);
$amountDue = $billingStatement->calculateAmountDue();

$messageBody = mailMerge($messageBody, 
									array(
												'#AMOUNTDUE#' => $amountDue
													));

$messageBody = htmlToPlainText($messageBody);
$messageSubject = $thisInvoiceSubject;
$properties = "firstDay|{$_REQUEST['firstDay']}|lookahead|{$_REQUEST['lookahead']}|literal|$literal|includePayNowLink|{$_REQUEST['includePayNowLink']}"
							."|excludePriors|$excludePriors|amountDue|$amountDue|packageptr|$packageptr";
$tags = $messageTag;

$client = $clientid; // for comm-composer

include "comm-composer.php";