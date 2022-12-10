<? // prov-schedule-email.php
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
require_once "prov-notification-fns.php";
require_once "email-template-fns.php"; 
if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 


$standardInvoiceMessage = "Hi #RECIPIENT#,<p>Here is your schedule.<p>Sincerely,<p>#BIZNAME#<p>#SCHEDULE#";
$standardMessageSubject = "Your Schedule";
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Sitter Schedule Email'");
if($template) {
	$standardInvoiceMessage = $template['body'];
	$standardMessageSubject = $template['subject'];
}
//$excludeStylesheets = false;
$bizName = $_SESSION['preferences']['bizName'] 
						? $_SESSION['preferences']['shortBizName']
						: $_SESSION['preferences']['bizName'];
if(isset($ids) || ($_GET && $_GET['ids'])) { // Automatic mode NOT USED!
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
		
		if(strpos($msgbody, '#PETS#') !== FALSE) {
			require_once "pet-fns.php";
			$petnames = getClientPetNames($client['clientid'], false, true);
		}
		$msgbody = mailMerge($messageBody, 
													array('#RECIPIENT#' => $client['clientname'],
																'#FIRSTNAME#' => $client['fname'],
																'#LASTNAME#' => $client['lname'],
																'#BIZNAME#' => $bizName,
																'#PETS#' => $petnames
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
		$invoiceCount++;
	}
	echo "$invoiceCount invoice".($invoiceCount ==  1 ? '' : 's')." emailed.";
	exit;
}

if($_POST) {  // Manual mode - send email
	//print_r($_POST);
	extract($_REQUEST);
	$provider = getProvider($_REQUEST['correspid']);
	$properties = explodePairsLine($properties); // starting, ending
	
	$msgbody = plainTextToHtml($msgbody);						

	$msgbody .=  "<hr style='page-break-after:always;'>";
//'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
//<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> '.
	ob_start();
	ob_implicit_flush(0);
	providerScheduleTableForEmail(($provider['providerid'] ? $provider['providerid'] : -1), $properties['starting'], $week=null, $properties['ending']);
	$schedule = ob_get_contents();
	ob_end_clean();
	$msgbody = str_replace('#SCHEDULE#', $schedule, $msgbody);
	
	$recipients = array("\"$corresname\" <$correspaddr>");
	if($error = sendEmail($recipients, $subject, $msgbody, null, 'html')) {
		echo "Mail error:<p>$error";
		exit;
	}
	$outboundMessage = array_merge($_POST);
	$outboundMessage['msgbody'] = $msgbody;
	//print_r($outboundMessage);exit;
	updateTable('tblinvoice', array('notification'=>'email', 'lastsent'=>date('Y-m-d')), "invoiceid = $invoiceid");
	saveOutboundMessage($outboundMessage);
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
	exit;
}

// Manual mode - open composer
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";
$providerDetails = fetchFirstAssoc("SELECT *, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as provname 
												FROM tblprovider 
												WHERE providerid = {$_GET['prov']} LIMIT 1");

$msgbody = mailMerge($standardInvoiceMessage, 
											array('#RECIPIENT#' => $providerDetails['provname'],
														'#FIRSTNAME#' => $providerDetails['fname'],
														'#LASTNAME#' => $providerDetails['lname'],
														'#BIZNAME#' => $bizName
														));
$provider = $_GET['prov'];
$messageBody = htmlToPlainText($msgbody);
$messageSubject = $standardMessageSubject;
$properties = "starting|{$_GET['starting']}||ending|{$_GET['ending']}";




include "comm-composer.php";