<? // email-swiftmailer-fns.php

require_once "Swift-4.0.6/lib/swift_required.php";
//require_once "log.php";
require_once "log-new.php";

function allowedAddresses($adds) {
	if(!$adds) return array();
	$_ALLOWED_ADDRESSES = array();
	if(!$_ALLOWED_ADDRESSES) return $adds;
	foreach($adds as $add => $name) {
		if(!in_array($add, $_ALLOWED_ADDRESSES)) {
			unset($adds[$add]);
			$adds['test@leashtime.com'] = $name;
		}
	}
	return (array)$adds;
}

function escapePassword
function escapeArg($str) {
	$str = preg_replace('/\"/','\"', $str);
	$str = preg_replace('/!/', '\!', $str);
	$str = preg_replace('/;/', '\;', $str);
	return  $str;
}

function getEmailAddress($emailInfo) {
	$pattern = '/([a-zA-Z0-9.!#$%&’*+=?^_`{|}~-]+)\@([a-zA-Z0-9-]+)(?:\.[a-zA-Z0-9-]+)(?:\.[a-zA-Z0-9-]+)?/';
	$matches = array();
	preg_match_all($pattern, $emailInfo,$matches);
	$matchcount = count($matches[0]);
	$multiemail = "";

	if ($matchcount == 1)
		return $matches[0][0];
	else if ($matchcount > 1) {
		foreach ($matches[0] as $email) {
			$multiemail = $multiemail . " " . $email;
		}
		return trim($multimail);
	}
}

function sendEmailViaPython($mainInfo) {
	$mainInfo['from'] = '"'.escapeArg($mainInfo['from']) . '"';
	$mainInfo['replyto'] = '"' . escapeArg($mainInfo['replyto']) . '"';
	$mainInfo['debug'] = $mainInfo['recipient'];
	$emailList = getEmailAddress($mainInfo['recipient']);

	$mainInfo['recipient'] = $emailList;

	//'"'.escapeArg($mainInfo['recipient']) . '"';
	$mainInfo['subject'] = '"'.escapeArg($mainInfo['subject']) . '"';
	$mainInfo['body'] = '"'. escapeArg($mainInfo['body'] ).'"';

	$request = 'python3 mail-relay.py ' . 
		$mainInfo['from'] . ' ' . 
		$mainInfo['replyto']. ' ' . 
		$mainInfo['recipient'] . ' ' . 
		$mainInfo['host'] . ' ' . 
		$mainInfo['username'] . ' ' . 
		$mainInfo['password'] . ' ' . 
		$mainInfo['subject'] . ' ' . 
		$mainInfo['body'] . ' ' . 
		$mainInfo['html'];
	/*if ($mainInfo['cc']) 
		$request .= $request . ' ' . $mainInfo['cc'];
	if ($mainInfo['bcc'])
		$request .= $request . ' ' . $mainInfo['bcc']	;
	*/
	$mainInfo['Request'] = $request;
	serverlog($mainInfo, "sendEmailViaSwiftMailerSMTPServer", "email-swiftmailer-fns");

	$output = shell_exec($request);
	$ok = "OK";
	if (strcmp(trim($output), $ok) === 0) {
		echo "OK";
	} else  {
		$ouput = 'THROWN EXCEPTION';
		throw new Exception(trim($output));
	}
}

function sendEmailViaSwiftMailerSMTPServer(
					$toRecipients, 
					$subject, 
					$body, 
					$cc = null, 
					$html=false, 
					$senderLabel='', 
					$bcc=null, 
					$extraHeaders=null, 
					$attachments=null) 
{ 
		// e.g., $senderLabel= 'Beth from Biz Name'
	global $installationSettings, $suppressErrorLoggingOnce; // from common/db_fns.php
	
	$from = firstNonEmpty(array(mPrefSet('emailFromAddress'), 'notice@leashtime.com')) ;
	
	

	if(mPrefNoInstallation('emailFromAddress') // Sender was specified
				&& !mPrefNoInstallation('emailHost') // host was NOT specified
				&& (strpos(strtolower($from), '@leashtime.com') === FALSE)) // Sender is NOT a LeashTime address
		
		$from = 'notice@leashtime.com';
	
	$senderLabel = firstNonEmpty(array($senderLabel, mPrefSet('shortBizName'), mPrefSet('bizName'), "Notices from LeashTime"));


	$host = mPrefSet('emailHost') ? mPrefSet('emailHost') : $installationSettings['smtphost'];
	$username = mPrefSet('emailUser') ? mPrefSet('emailUser') : $installationSettings['smtpuser'];


	debugLog("FROM", $from, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("sender label", $senderLabel, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("host", $host, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("username", $username, "sendEmailViaSwiftMailerSMTPServer");

	$password = mPrefSet('emailPassword') ? mPrefSet('emailPassword') : $installationSettings['smtppassword'];
	debugLog("password", $password, "sendEmailViaSwiftMailerSMTPServer");
	$port = mPrefSet('smtpPort') ? mPrefSet('smtpPort') : 25;

	$ssl = mPrefSet('smtpSecureConnection', null);
	if($ssl == 'no') $ssl = null;

	$to = 
	$toRecipients && is_array($toRecipients) 
		? $toRecipients : 
		(trim((string)$toRecipients) ? explode(',', trim($toRecipients)) : array());

	

	$cc = $cc && is_array($cc) ? join(', ', $cc) : $cc;

	debugLog("to", 						$to, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("FROM", 				$from, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("sender label", 	$senderLabel, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("host", 					$host, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("port", 					$port, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("username", 		$username, "sendEmailViaSwiftMailerSMTPServer");
	debugLog("cc", 						$cc, "sendEmailViaSwiftMailerSMTPServer");

	if($bcc) $bcc = is_array($bcc) ? $bcc : explode(',', $bcc);
	$globalBcc = mPrefSet('emailBCC') ? mPrefSet('emailBCC') : ''; //"notice@leashtime.com";
	if($globalBcc) {
		foreach(explode(',', $globalBcc) as $addr) $bcc[] = $addr;
	}
	if($bcc) $bcc = join(', ', $bcc);

	debugLog("bcc" , 					$bcc, "sendEmailViaSwiftMailerSMTPServer");

	$replyTo = $extraHeaders['Reply-to'] ? $extraHeaders['Reply-to'] : mPrefSet('defaultReplyTo');

	debugLog("reply-to" , 			$replyTo, "sendEmailViaSwiftMailerSMTPServer");

	$message = Swift_Message::newInstance()
		->setSubject($subject)
		->setFrom(array($from=>$senderLabel))
		->setBody($body, ($html ? 'text/html' : 'text/plain'));

	debugLog("subject", 			$subject, "sendEmailViaSwiftMailerSMTPServer");

	if($_SERVER['REMOTE_ADDR'] == 'X68.225.89.173') { 
		$offsetFromET = 3 * 60;
		$message->setDate(time() - $offsetFromET * 60);
	}	

	foreach(addressList($to) as $parts) {
		if(strpos($parts[0], 'X_OUT_') === 0) 
			$failedAddresses[] = $parts[0];
		else 
			$toAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
	}
	try {
		$message->setTo(allowedAddresses($toAssoc));
		foreach(addressList($cc) as $parts) {
			if(strpos($parts[0], 'X_OUT_') === 0) 
				$failedAddresses[] = $parts[0];
			else 
				$ccAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
		}
		if($ccAssoc) 
			$message->setCc(allowedAddresses($ccAssoc));
		foreach(addressList($bcc) as $parts) {
			if(strpos($parts[0], 'X_OUT_') === 0) 
				$failedAddresses[] = $parts[0];
			else 
				$bccAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
		}
		if($bccAssoc)
			$message->setBcc(allowedAddresses($bccAssoc));
		foreach(addressList($replyTo) as $parts) {
			if(strpos($parts[0], 'X_OUT_') === 0) 
				$failedAddresses[] = $parts[0];
			else 
				$replyToAssoc[$parts[0]] = ($parts[1] ? $parts[1] : null);
		}
		if($replyToAssoc) 
			debugLog("Set Reply To", 				$replyToAssoc, 'sendEmailViaPython');
			$message->setReplyTo(allowedAddresses($replyToAssoc));
	}
	catch (Exception $e)
	{
		debugLog("EXCEPTION" ,"ALLOWED TO ADDRESSES", "sendEmailViaSwiftMailerSMTPServer");
		$suppressErrorLoggingOnce = false;
		logError('Send failed - Bad Address: '.$e->getMessage());
		return "Send failed - Bad Address: ".$e->getMessage();
	}
	unset($extraHeaders['Reply-to']);
	if(count($extraHeaders)) 
		foreach($extraHeaders as $key => $val)
			$message->getHeaders()->addTextHeader($key, $val);

	if($attachments) {
		foreach($attachments as $att) {
			if(is_array($att)) {
				$filename = $att['filename'];
				$imageToken = $att['imagetoken'];
				$imageSrc = $att['imagepath'];
				$att = $att['path'];
			}
			else 
				
			$filename = $att;
			if($filename)
				$message->attach(Swift_Attachment::fromPath($att)
					->setFilename($filename));
			else if($imageToken && $imageSrc) {
				$cid = $message->embed(Swift_Image::fromPath($imageSrc));
				$message->setBody(str_replace($imageToken, $cid, $message->getBody()));
			}
		}
	}

	$mail_variables['senderLabel'] = $senderLabel;
	$mail_variables['subject'] = $message->getSubject();
	$mail_variables['body'] = $message->getBody();
	foreach($message->getFrom() as $value) {
		$mail_variables['from'] .= $value;
	}
	//$mail_variables['recipient']  = preg_replace('/"/', '\"', $mail_variables['recipient']);
	$messageHeaders = $message->getHeaders();
	$reptoarray = split(':', $messageHeaders->get('Reply-to'));
	$mail_variables['replyto'] = trim($reptoarray[1]);
	$sendto = split(':', $messageHeaders->get('To'));
	$mail_variables['recipient'] = trim($sendto[1]);
	$mail_variables['cc'] = $message->getCc();
	$mail_variables['date'] = $message->getDate();
	if ($html) {
		$mail_variables['html'] = 'html';
	} else {
		$mail_variables['html'] = 'text';
	}
	$mail_variables['host'] = $host;
	$mail_variables['username'] = $username;
	$mail_variables['password'] = $password;
	$mail_variables['port'] = $port;
	$mail_variables['bcc'] = $bcc;
	$mail_variables['tostring'] = $message->tostring();
	$mail_variables['headers'] = $message->getHeaders();
	try {
		debugLog("Try", "transmit email", "sendEmailViaSwiftMailerSMTPServer");
		sendEmailViaPython($mail_variables); 
	} catch (Exception $e) {
		return "Failed sending mail: ".$e->getMessage();
	}


	/*
	$transport = Swift_SmtpTransport::newInstance($host, $port)
		->setUsername($username)
		->setPassword($password);
	if($ssl) $transport->setEncryption($ssl); // ssl or tls
	$mailer = Swift_Mailer::newInstance($transport);
	try {
		$numSent = $mailer->send($message, $failedAddresses);
	}
	catch (Swift_TransportException $e)
	{
		$suppressErrorLoggingOnce = false;
		logError('Mail Transport fail: '.$e->getMessage());
		return "Failed to connect: ".$e->getMessage().(mattOnlyTEST() ? "<br>Matt diagnositic: [$host/$port/$username/$password/security: $ssl]]" : "");
	}
	catch (Exception $e)
	{
		$suppressErrorLoggingOnce = false;
		logError('Mail Sending fail: '.$e->getMessage());
		return "Failed sending mail: ".$e->getMessage();
	}
	if($failedAddresses) {
		$error = "Could not send email to: ".join(', ', $failedAddresses);
		if(!$suppressErrorLoggingOnce) logError($error);		
		$suppressErrorLoggingOnce = false;
		if($_SESSION) return $error;
	}
	*/
	debugLog("RETURNING WITH FALSE VALUE", "FALSE", "sendEmailViaSwiftMailerSMTPServer");
	return False;

}


