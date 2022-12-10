<? // bad-electronic-transactions-email.php
/* Four Modes:
Automatic - called by AJAX: 
	GET with ids: tblcreditcarderror ids
	echos status.
Automatic - included by another script: 
	isset(ids): automatically email all invoices
	echos status.
Manual 
	GET with id - open a composer for one invoice
	GET with fetchTemplate and id - open a composer for one invoice
	POST - to close composer and send email
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "cc-processing-fns.php";
require_once "comm-composer-fns.php";

//if($_GET['ids']) /** TEST **/ echo "<pre>".print_r(gatherDataForCreditCardErrors($_GET['ids']), 1)."</pre>";exit;
function badETransactionEmailTemplate() {	
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Failed Electronic Payment'");
	if(!$template)
		$template = array(
			'label'=>'#STANDARD - Failed Electronic Payment',
			'subject'=> "Charge to your #SOURCETYPE# failed",
			'targettype'=>'other', 'personalize'=>0,
			'salutation'=>'', 'farewell'=>sqlVal("''"), 'active'=>1,
			'body'=>standardBadETransactionEmailTemplateBody());			
	return $template;
}

function standardBadETransactionEmailTemplateBody() {
	return <<<BODY
#LOGO#
Dear #FIRSTNAME#,

On #DATE# at #TIME#, we tried to bill your #SOURCETYPE# (#SOURCEDESCRIPTION#), but the transaction failed for the following reason:

#REASON#

Please login and provide and update your electronic payment method or contact us to make other arrangements for payment.

Thank you for your assistance in this matter.

Kind regards,

#BIZNAME#
#BIZPHONE#
#BIZEMAIL#
BODY;
}

function gatherDataForCreditCardErrors($ids) {
	$ids = is_array($ids) ? $ids : explode(',', $ids);
	foreach($ids as $ccerrorid) {
		$found = fetchAssociations($sql = "SELECT * FROM tblchangelog 
		 WHERE 
			itemtable IN ('ccpayment', 'achpayment')
			AND note LIKE '%ErrorID:$ccerrorid%'");
			// add in time clause if start and end are available
		 	//AND SUBSTRING(time, 1, 10) >= '$dbstart' AND SUBSTRING(time, 1, 10) <= '$dbend'
		// allow for substring matches
		//echo "$sql<br>".print_r($found, 1).'<hr>';
		foreach($found as $changelogEntry) {
			$details = parseChangeLogPaymentNote($changelogEntry['note']);
			if($details['errorid'] == $ccerrorid) {
				$ccerror = fetchFirstAssoc("SELECT * FROM tblcreditcarderror WHERE errid = $ccerrorid LIMIT 1");
				$details['clientptr'] = $ccerror['clientptr'];
				$details['ccptr'] = $ccerror['ccptr'];
				$details['datetime'] = $ccerror['time'];
				$details['date'] = shortDate(strtotime($ccerror['time']));
				$details['time'] = date('g:i a', strtotime($ccerror['time']));
				if($ccerror['sourcetable'] == 'tblcreditcard') {
					$details['sourcetype'] = 'Credit Card';
					//$details['ccid'] = $error['ccptr'];
				}
				else {
					$details['sourcetype'] = 'Bank account';
					//$data['acctid'] = $error['ccptr'];
				}
				$source = getPaymentSourceFromChangeLog($changelogEntry);
				foreach(clearSource($source) as $k => $v)
					$details[$k] = $v;
				$details['sourcedescription'] = clearSourceDescription($source);
				$allDetails[$ccerrorid] = $details;
				break;
			}
		}
	}
	return $allDetails;
}



require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "email-template-fns.php";
if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 

$emailFromName = $_SESSION['preferences']['invoiceEmailsFromCurrentManager'] ? getUsersFromName() : null;
if($_REQUEST['templateid'] && $_REQUEST['templateid'] != 'default') $template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = {$_REQUEST['templateid']} LIMIT 1");
else $template = badETransactionEmailTemplate();

if($template) {
	$standardMessage = $template['body'];
	$standardSubject = $template['subject'];
}
//echo "<ERROR>error  {$_REQUEST['templateid']}</ERROR>";exit;
//$excludeStylesheets = false;
$bizName = $_SESSION['preferences']['shortBizName'] 
						? $_SESSION['preferences']['shortBizName']
						: $_SESSION['preferences']['bizName'];
$managerNickname = fetchRow0Col0(
	"SELECT value 
		FROM tbluserpref 
		WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
if(isset($ids) || ($_GET && $_GET['ids'])) { // Automatic mode
	if($_GET) extract($_GET);
	// automatic emailing
	//$clients = fetchKeyValuePairs("SELECT invoiceid, clientptr FROM tblinvoice WHERE invoiceid IN ($ids)");
	$clientDetails = getClientDetails($clients, array('lname','fname', 'email'));

	foreach($clients as $invoiceid => $clientid) {
		$client = $clientDetails[$clientid];
		if(!$client['email']) {
			echo "No email address found for {$client['clientname']}.\n";
			continue;
		}
		if(strpos($standardMessage, '#PETS#') !== FALSE) {
			require_once "pet-fns.php";
			$petnames = getClientPetNames($client['clientid'], false, true);
		}
		
		$msgbody = mailMerge($standardMessage, 
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
																
		updateTable('tblinvoice', array('notification'=>'email', 'lastsent'=>date('Y-m-d')), "invoiceid = $invoiceid");
		
		//if(notifyByEmail($client, $standardSubject, $msgbody, null, $_SESSION["auth_login_id"], 'html'))
		//	echo "Failed to email invoice to {$client['clientname']} ({$client['email']}):\n$error\n";
		//else $invoiceCount++;
		enqueueEmailNotification($client, $standardSubject, $msgbody, null, $emailFromName, 'html');	// $_SESSION["auth_login_id"]
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

// Manual mode - open composer or populate template
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";

$errorData = gatherDataForCreditCardErrors($_GET['id']);
$errorData = current($errorData);
$source = clearSource($errorData);
$client = getOneClientsDetails($errorData['clientptr'], $additionalFields=array('fullname'));

if(strpos($standardMessage, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($client['clientid'], false, true);
}

//echo "BANG!$standardMessage<hr>";
$messageBodyTokens = "#BIZNAME#, #BIZEMAIL#, #BIZPHONE#, #BIZHOMEPAGE#, #BIZLOGINPAGE#, #MANAGER#, "
											."#RECIPIENT#, #FIRSTNAME#, #LASTNAME#, #LOGO#, #LOGINID#, #TEMPPASSWORD#, #CREDITCARD#, #PETS#"
											."<p>The following can be used in the Subject field as well as the message Body:"
											."<br>#DATE#, #TIME#, #SOURCETYPE#<sup>1</sup>, #SOURCEDESCRIPTION#<sup>2</sup>, #AMOUNT#<sup>3</sup>, #REASON#"
											."<br><sup>1</sup> \"Credit Card\" or \"Bank Account\"<br><sup>2</sup> A \"masked\" description of the credit card or bank account<br><sup>3</sup> Not available in all cases<br>";

$msgbody = preprocessMessage($standardMessage, $client);
$msgbody = mailMerge($msgbody, 
											array('#DATE#' => $errorData['date'],
														'#TIME#' => $errorData['time'],
														'#SOURCETYPE#' => $errorData['sourcetype'],
														'#SOURCEDESCRIPTION#' => clearSourceDescription($source),
														'#AMOUNT#' => $errorData['amount'],
														'#REASON#' => $errorData['reason']));
$client = $client['clientid'];
$messageBody = htmlToPlainText($msgbody);
$messageSubject = $standardSubject;
$messageSubject = mailMerge($messageSubject, 
											array('#DATE#' => $errorData['date'],
														'#TIME#' => $errorData['time'],
														'#SOURCETYPE#' => $errorData['sourcetype'],
														'#SOURCEDESCRIPTION#' => clearSourceDescription($source),
														'#AMOUNT#' => $errorData['amount'],
														'#REASON#' => $errorData['reason']));
$properties = "id|{$_REQUEST['id']}";

if($_REQUEST['templateid']) {
	echo "<root><subject><![CDATA[$messageSubject]]></subject><body><![CDATA[$messageBody]]></body></root>";
	exit;
}

$specialTemplates = array('Standard Template'=>'default');

include "comm-composer.php";

?>
<script language='javascript'>
function templateChosen() {
	if(!document.getElementById('template')) return;
	var templateid = document.getElementById('template').value;
	if(templateid == 0) return;
	
	var url = 'bad-electronic-transactions-email.php?templateid='+templateid+'&id=<?= $_REQUEST['id'] ?>';
	ajaxGetAndCallWith(url, updateMessage, null);
}
</script>