<? // email-swiftmailer-fns.php

require_once "Swift-4.0.6/lib/swift_required.php";

//======== TEST ===========

/*$installationSettings = array(
	'smtphost'=>'smtp.gmail.com',
	'smtpPort'=>465,
	'smtpuser'=>'mmlinden@gmail.com',
	'smtppassword'=>'sylvain2',
	'smtpSecureConnection'=>'tls',
	'emailFromAddress'=>'mmlinden@gmail.com');
$installationSettings = array(
	'smtphost'=>'smtp.zoho.com',
	'smtpPort'=>465,
	'smtpuser'=>'zohoTestLT',
	'smtppassword'=>'devnull2',
	'smtpSecureConnection'=>'tls',
	'emailFromAddress'=>'zohoTestLT@zoho.com');
$installationSettings = array(
	'smtphost'=>'smtp.mail.yahoo.com',
	'smtpPort'=>465,
	'smtpuser'=>'mmlinden',
	'smtppassword'=>'sylvan',
	'smtpSecureConnection'=>'tls',
	'emailFromAddress'=>'mmlinden@yahoo.com');
$installationSettings = array(
	'smtphost'=>'smtp.1and1.com', // WORKS WITH PATCH FROM http://swiftmailer.lighthouseapp.com/projects/21527-swift-mailer/tickets/67-add-starttls-support
	'smtpPort'=>587,
	'smtpuser'=>'notice@leashtime.com',
	'smtppassword'=>'not11ce',
	'smtpSecureConnection'=>'starttls',
	'emailFromAddress'=>'notice@leashtime.com');
$installationSettings = array(
	'smtphost'=>'smtp.live.com', // WORKS WITH PATCH FROM http://swiftmailer.lighthouseapp.com/projects/21527-swift-mailer/tickets/67-add-starttls-support
	'smtpPort'=>587,
	'smtpuser'=>'devnullhandy@hotmail.com',
	'smtppassword'=>'devnull2',
	'smtpSecureConnection'=>'starttls',
	'emailFromAddress'=>'devnullhandy@hotmail.com');
$installationSettings = array(
	'smtphost'=>'smtp.aol.com', 
	'smtpPort'=>587,
	'smtpuser'=>'devnullhandy@aol.com',
	'smtppassword'=>'devnull2',
	'smtpSecureConnection'=>'starttls',
	'emailFromAddress'=>'devnullhandy@aol.com');
	*/
/*require_once "email-fns.php";
$installationSettings = array(
	'smtphost'=>'smtp.east.cox.net',
	'smtpPort'=>587,
	'smtpuser'=>'mlindenfelser',
	'smtppassword'=>'sylvain2',
	'smtpSecureConnection'=>'starttls',
	'emailFromAddress'=>'mlindenfelser@cox.net');	
$toRecipients = '"Matt Linden" <thule@aol.com>,matt@leashtime.com';
$extraHeaders['Reply-to'] = '"Matt Linden" <thule@aol.com>';
$subject = "Test ".date('Y-md H:i');
$body = "This is a <b>test</b>.  Note the <i>From</i> line";
$html = 1;
echo "$subject<hr>";
echo sendEmailViaSwiftMailerSMTPServer($toRecipients, $subject, $body, $cc = null, $html, $senderLabel='', $bcc=null, $extraHeaders);

function logError($err) { }
*/
//======== TEST ===========

function allowedAddresses($adds) {
	// make any necessary changes to recipient email addresses.  Ensure an array is returned.
	if(!$adds) return array();
	
	/* ******* TESTING ********* */
	// In this episode, we will replace any non-allowed addresses with 'test@leashtime.com'
	$_ALLOWED_ADDRESSES = array();
	//$_ALLOWED_ADDRESSES = explode(',', 'jody@leashtime.com,matt@leashtime.com,ted@leashtime.com,thule@aol.com');
	if(!$_ALLOWED_ADDRESSES) return $adds;
	foreach($adds as $add => $name) {
		if(!in_array($add, $_ALLOWED_ADDRESSES)) {
			unset($adds[$add]);
			$adds['test@leashtime.com'] = $name;
		}
	}
	/* ******* TESTING ********* */
	
	return (array)$adds;
}


function sendEmailViaSwiftMailerSMTPServer($toRecipients, $subject, $body, $cc = null, $html=false, $senderLabel='', $bcc=null, $extraHeaders=null, $attachments=null) { // e.g., $senderLabel= 'Beth from Biz Name'
	global $installationSettings, $suppressErrorLoggingOnce; // from common/db_fns.php
//global $db;if($db == 'dogslife') logError(date('Y-m-d H:i')." SWIFTMAILER [$subject]");

	$from = firstNonEmpty(array(mPrefSet('emailFromAddress'), 'notice@leashtime.com')) ;
	$senderLabel = firstNonEmpty(array($senderLabel, mPrefSet('shortBizName'), mPrefSet('bizName'), "Notices from LeashTime"));
	
	$host = mPrefSet('emailHost') ? mPrefSet('emailHost') : $installationSettings['smtphost'];
	$username = mPrefSet('emailUser') ? mPrefSet('emailUser') : $installationSettings['smtpuser'];
	$password = mPrefSet('emailPassword') ? mPrefSet('emailPassword') : $installationSettings['smtppassword'];
	$port = mPrefSet('smtpPort') ? mPrefSet('smtpPort') : 25;
	//$auth = mPrefSet('smtpAuthentication') ? mPrefSet('smtpAuthentication') : true;
	$ssl = mPrefSet('smtpSecureConnection', null);
	if($ssl == 'no') $ssl = null;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "$host, $username, $port, $auth, $ssl"; exit;}

//if($db == 'dogslife') logError(date('Y-m-d H:i')." SWIFTMAILER [$subject] BEFORE: ".print_r($toRecipients, 1));	
	$to = $toRecipients && is_array($toRecipients) ? $toRecipients : (
				trim((string)$toRecipients) ? explode(',', trim($toRecipients)) : array());
//if($db == 'dogslife') logError(date('Y-m-d H:i')." SWIFTMAILER [$subject] AFTER: ".print_r($to, 1));	
	$cc = $cc && is_array($cc) ? join(', ', $cc) : $cc;
	if($bcc) $bcc = is_array($bcc) ? $bcc : explode(',', $bcc);
	$globalBcc = mPrefSet('emailBCC') ? mPrefSet('emailBCC') : ''; //"notice@leashtime.com";
	if($globalBcc) {
		foreach(explode(',', $globalBcc) as $addr) $bcc[] = $addr;
	}
	if($bcc) $bcc = join(', ', $bcc);
	$replyTo = $extraHeaders['Reply-to'] ? $extraHeaders['Reply-to'] : mPrefSet('defaultReplyTo');
	
	//===================================================
	
	//Create the message
	$message = Swift_Message::newInstance()

		//Give the message a subject
		->setSubject($subject)

		//Set the From address with an associative array
		->setFrom(array($from=>$senderLabel))

		//Give it a body
		->setBody($body, ($html ? 'text/html' : 'text/plain'))


		//And optionally an alternative body
		//->addPart('<q>Here is the message itself</q>', 'text/html')

		//Optionally add any attachments
		//->attach(Swift_Attachment::fromPath('my-document.pdf'))
		;
		
if($_SERVER['REMOTE_ADDR'] == 'X68.225.89.173') { 
	$offsetFromET = 3 * 60;
	$message->setDate(time() - $offsetFromET * 60);
	//echo date('Y-m-d H:i', time() - $offsetFromET * 60);	exit;
}		

		
	//Set the To addresses with an associative array
	foreach(addressList($to) as $parts) {
	// Exclude blocked-off email addresses
		if(strpos($parts[0], 'X_OUT_') === 0) $failedAddresses[] = $parts[0];
		else $toAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
	}
//if($db == 'dogslife') logError(date('Y-m-d H:i')." SWIFTMAILER [$subject] setTo: ".print_r(($toAssoc ? $toAssoc : array()), 1));	
	$message->setTo(allowedAddresses($toAssoc));
	
	//Set the Cc addresses with an associative array
	foreach(addressList($cc) as $parts) {
	// Exclude blocked-off email addresses
		if(strpos($parts[0], 'X_OUT_') === 0) $failedAddresses[] = $parts[0];
		else $ccAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
	}
	if($ccAssoc) $message->setCc(allowedAddresses($ccAssoc));
	
	//Set the Bcc addresses with an associative array
	foreach(addressList($bcc) as $parts) {
	// Exclude blocked-off email addresses
		if(strpos($parts[0], 'X_OUT_') === 0) $failedAddresses[] = $parts[0];
		else $bccAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
	}
	if($bccAssoc) $message->setBcc(allowedAddresses($bccAssoc));
	
	//Set the replyTo addresses with an associative array
	foreach(addressList($replyTo) as $parts) {
	// Exclude blocked-off email addresses
		if(strpos($parts[0], 'X_OUT_') === 0) $failedAddresses[] = $parts[0];
		else $replyToAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
	}
	if($replyToAssoc) $message->setReplyTo(allowedAddresses($replyToAssoc));

	unset($extraHeaders['Reply-to']);
	if(count($extraHeaders)) 
		foreach($extraHeaders as $key => $val)
			$message->getHeaders()->addTextHeader($key, $val);
	
	if($attachments) {
		foreach($attachments as $att) {
			if(is_array($att)) {
				$filename = $att['filename'];
				$att = $att['path'];
			}
			else $filename = $att;
			$message->attach(Swift_Attachment::fromPath($att)
					->setFilename($filename));
		}
	}
	
	//Create the Transport
	$transport = Swift_SmtpTransport::newInstance($host, $port)
		->setUsername($username)
		->setPassword($password);
	if($ssl) $transport->setEncryption($ssl); // ssl or tls
	
	//Create the Mailer using your created Transport
	$mailer = Swift_Mailer::newInstance($transport);


	//Send the message
	try {
}
		$numSent = $mailer->send($message, $failedAddresses);
	}
	catch (Swift_TransportException $e)
	{
		$suppressErrorLoggingOnce = false;
		logError('Mail Transport fail: '.$e->getMessage());
		return "Failed to connect: ".$e->getMessage();
	}
	catch (Exception $e)
	{
		$suppressErrorLoggingOnce = false;
		logError('Mail Sending fail: '.$e->getMessage());
//if(dbTEST('leashtimecustomers')) logError('Mail Sending user: '.exec("id -u -n"));  // does nor work
//if(dbTEST('leashtimecustomers')) logError('Mail Sending user: '.exec("ps -F"));  // does nor work
		return "Failed sending mail: ".$e->getMessage();
	}
	
	/*
	You can alternatively use batchSend() to send the message

	$result = $mailer->batchSend($message);
	*/
	if($failedAddresses) {
		$error = "Could not send email to: ".join(', ', $failedAddresses);
		if(!$suppressErrorLoggingOnce) logError($error);		
		$suppressErrorLoggingOnce = false;
		//return $error;  -- DO NOT KEEP message in the queue.  if masked address and unmasked address, the unmasked address will get spammed
	}
	//===================================================
	
	return null;
}
