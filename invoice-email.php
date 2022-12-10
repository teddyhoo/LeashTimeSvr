<? // invoice-email.php
/* Four Modes:
Automatic - called by AJAX: 
	GET with ids: automatically email all invoices
	echos status.
Automatic - included by another script: 
	isset(ids): automatically email all invoices
	echos status.
Manual 
	GET with id - open a composer for one invoice
	POST - to close composer and send email
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "invoice-gui-fns.php";
require_once "email-template-fns.php"; //'#STANDARD - Invoice Email'
if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 

$emailFromName = $_SESSION['preferences']['invoiceEmailsFromCurrentManager'] ? getUsersFromName() : null;


$standardInvoiceMessage = "Hi #RECIPIENT#,<p>Here is your latest invoice.<p>Sincerely,<p>#BIZNAME#";
$standardMessageSubject = "Your Invoice";
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Invoice Email'");
if($template) {
	$standardInvoiceMessage = $template['body'];
	$standardMessageSubject = $template['subject'];
}
//$excludeStylesheets = false;
$bizName = $_SESSION['preferences']['bizName'] 
						? $_SESSION['preferences']['shortBizName']
						: $_SESSION['preferences']['bizName'];
$managerNickname = fetchRow0Col0(
	"SELECT value 
		FROM tbluserpref 
		WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
if(isset($ids) || ($_GET && $_GET['ids'])) { // Automatic mode
	if($_GET) extract($_GET);
	// automatic emailing
	$clients = fetchKeyValuePairs("SELECT invoiceid, clientptr FROM tblinvoice WHERE invoiceid IN ($ids)");
	$clientDetails = getClientDetails($clients, array('lname','fname', 'email'));
	$invoiceCount = 0;
	foreach($clients as $invoiceid => $clientid) {
		$client = $clientDetails[$clientid];
		if(!$client['email']) {
			echo "No email address found for {$client['clientname']}.\n";
			continue;
		}
		if(strpos($standardInvoiceMessage, '#PETS#') !== FALSE) {
			require_once "pet-fns.php";
			$petnames = getClientPetNames($client['clientid'], false, true);
		}
		if(strpos($standardInvoiceMessage, '#AMOUNTDUE#') !== FALSE) {
			$amountDue = fetchRow0Col0("SELECT origbalancedue FROM tblinvoice WHERE invoiceid = $invoiceid LIMIT 1");
		}
		
if(TRUE || mattOnlyTEST()) { // enabled for all 6/6/2018
		require_once "comm-composer-fns.php";
		$msgbody = mailMerge($standardInvoiceMessage, array('#AMOUNTDUE#' => $amountDue));
		$msgbody = preprocessMessage($msgbody, $client);
}
else {
	
	
		$msgbody = mailMerge($standardInvoiceMessage, 
													array('#RECIPIENT#' => $client['clientname'],
																'#FIRSTNAME#' => $client['fname'],
																'#LASTNAME#' => $client['lname'],
																'#BIZNAME#' => $bizName,
																'#BIZID#' => $_SESSION["bizptr"],
																'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
																'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
																'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
																'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
																'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
																'#PETS#' => $petnames,
																'#LOGO#' => templateLogoIMG(),
																'#AMOUNTDUE#' => $amountDue,
																'#CLIENTID#' => $client['clientid']
																));		
		$msgbody = plainTextToHtml($msgbody);						
}
																
		$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'		.getInvoiceContents($invoiceid);

		updateTable('tblinvoice', array('notification'=>'email', 'lastsent'=>date('Y-m-d')), "invoiceid = $invoiceid");
		
		//if(notifyByEmail($client, $standardMessageSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html'))
		//	echo "Failed to email invoice to {$client['clientname']} ({$client['email']}):\n$error\n";
		//else $invoiceCount++;
		enqueueEmailNotification($client, $standardMessageSubject, $msgbody, null, $emailFromName, 'html');	// $_SESSION["auth_login_id"]
		$invoiceCount++;
	}
	echo "$invoiceCount invoice".($invoiceCount ==  1 ? '' : 's')." emailed.";
	exit;
}

if($_POST) {  // Manual mode - send email
	//print_r($_POST);
	extract($_REQUEST);
	
	$properties = explode('|', $properties);
	$invoiceid = $properties[1];
	
	$msgbody = plainTextToHtml($msgbody);						

	$msgbody .=  "<hr style='page-break-after:always;'>".
'<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/style.css" type="text/css" /> 
<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/pet.css" type="text/css" /> 
<style> body {background-image: none;}</style>
'.
	getInvoiceContents($invoiceid);
	
	
	$outboundMessage = array_merge($_POST);
	$outboundMessage['msgbody'] = $msgbody;
	//print_r($outboundMessage);exit;
	updateTable('tblinvoice', array('notification'=>'email', 'lastsent'=>date('Y-m-d')), "invoiceid = $invoiceid");
	saveOutboundMessage($outboundMessage);
	$recipients = array("\"$corresname\" <$correspaddr>");
	if($error = sendEmail($recipients, $subject, $msgbody, null, 'html', $emailFromName)) {
		echo "Mail error:<p>$error";
		exit;
	}
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
	exit;
}

// Manual mode - open composer
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";
$client = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as clientname 
												FROM tblinvoice 
												LEFT JOIN tblclient ON clientid = clientptr
												WHERE invoiceid = {$_REQUEST['id']} LIMIT 1");

if(strpos($standardInvoiceMessage, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($client['clientid'], false, true);
}
if(strpos($standardInvoiceMessage, '#AMOUNTDUE#') !== FALSE) {
	$amountDue = fetchRow0Col0("SELECT origbalancedue FROM tblinvoice WHERE invoiceid = {$_GET['id']} LIMIT 1");
}

if(TRUE || mattOnlyTEST()) { // enabled for all 6/6/2018
		require_once "comm-composer-fns.php";
		$msgbody = mailMerge($standardInvoiceMessage, array('#AMOUNTDUE#' => $amountDue));
		$messageBody = preprocessMessage($msgbody, $client, $template=null, $noHTMLConversion=true);
}
else {
$msgbody = mailMerge($standardInvoiceMessage, 
											array('#RECIPIENT#' => $client['clientname'],
														'#FIRSTNAME#' => $client['fname'],
														'#LASTNAME#' => $client['lname'],
														'#BIZNAME#' => $bizName,
														'#BIZID#' => $_SESSION["bizptr"],
														'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
														'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
														'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
														'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
														'#MANAGER#' => ($manager ? $manager : $_SESSION["auth_username"]),
														'#PETS#' => $petnames,
														'#LOGO#' => templateLogoIMG(),
														'#AMOUNTDUE#' => $amountDue,
														'#CLIENTID#' => $client['clientid']
														));
$messageBody = htmlToPlainText($msgbody);
}
$client = $client['clientid'];
$messageSubject = $standardMessageSubject;
$properties = "id|{$_REQUEST['id']}";




include "comm-composer.php";