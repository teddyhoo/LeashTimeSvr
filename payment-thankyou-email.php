<? // payment-thankyou-email.php
/* Five Modes:
Manual 
	GET with id and no send - open a composer for a payment
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

$emailFromName = $_SESSION['preferences']['invoiceEmailsFromCurrentManager'] ? getUsersFromName() : null;

if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 

if($_POST) {  // Manual mode - send email
	//print_r($_POST);
	$auxiliaryWindow = true; // prevent login from appearing here if session times out
	extract($_REQUEST);

	$properties = explode('|', $properties);
	$preprocessedMessage = preprocessMessage($msgbody, getClient($_POST['correspid']));

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
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";
extract($_REQUEST);
$client = $_GET['ids'];
$templateLabel = '#STANDARD - Manual Payment Thank You';
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$templateLabel' LIMIT 1", 1);
if(!$template) {
	$template =
			array('label'=>$templateLabel, 'targettype'=>'other', 'personalize'=>'1',  
						'subject'=>'Thank you for your payment', 
						'extratokens'=>'#TOTALAMOUNT#,#GRATUITY#',
						'body'=>
							"Dear #LASTNAME#,\n\nThank you for your payment of #TOTALAMOUNT#"
							."#IFGRATUITY# and for your thoughtful gratuity of #GRATUITY##ENDIFGRATUITY#"
							.".\n\nSincerely,\n\n#BIZNAME#",
						'salutation'=>'', 'farewell'=>sqlVal("''"), 'active'=>1);

	$id = insertTable('tblemailtemplate', $template, 1);
}




$messageBody = $failedMessageBody ? $failedMessageBody 
								: preprocessMessage($template['body'], getClient($client));
								
$gratuityStart = strpos($messageBody, '#IFGRATUITY#');
if($gratuityStart !== FALSE) $gratuityEnd = strpos($messageBody, '#ENDIFGRATUITY#');
if($gratuityEnd)  $gratuityLength = $gratuityEnd + strlen('#ENDIFGRATUITY#') - $gratuityStart;
if($gratuityLength) {
	if($gratuity)
		$sectionReplacement = substr($messageBody, 
														$gratuityStart+strlen('#IFGRATUITY#'), 
														$gratuityEnd-($gratuityStart+strlen('#IFGRATUITY#')));
	else $sectionReplacement = '';
	$messageBody = str_replace(substr($messageBody, $gratuityStart, $gratuityLength),
															$sectionReplacement,
															$messageBody);
}
								
$messageBody = mailMerge($messageBody, 
									array(
												'#TOTALAMOUNT#' => dollarAmount($totalAmount),
												'#GRATUITY#' => dollarAmount($gratuity)
													));

$messageBody = htmlToPlainText($messageBody);
$messageSubject = $template['subject'];
$properties = "totalAmount|{$_REQUEST['totalAmount']}|gratuity|{$_REQUEST['gratuity']}";


include "comm-composer.php";