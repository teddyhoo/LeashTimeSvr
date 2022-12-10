<? // comm-fns.php
require_once "request-fns.php";
require_once "email-fns.php";
/* 
inbound - true if from client/provider
correspid - client or provider id
correstable - tblclient or tblprovider
mgrname - defaults to current loginid (outbound only)
transcribed - phone or email (inbound or outbound logged messages)
correspaddr - phone number or email address
*/

function forwardedSubject($forwardid) {
	$forwardMsg = fetchFirstAssoc("SELECT * FROM tblmessage WHERE msgid = '$forwardid' LIMIT 1", 1);
	if(!$forwardMsg) return "Message [$forwardid] not found.";
	return "Fwd: {$forwardMsg['subject']}";
}

function forwardedContent($forwardid) {
	$forwardMsg = fetchFirstAssoc("SELECT * FROM tblmessage WHERE msgid = '$forwardid' LIMIT 1", 1);
	if(!$forwardMsg) return "Message [$forwardid] not found.";
	
	ob_start();
	ob_implicit_flush(0);

	$corresType = $forwardMsg['correstable'] == 'tblclient' ? 'Client' : (
								$forwardMsg['correstable'] == 'tblprovider' ? 'Sitter' : (
								$forwardMsg['correstable'] == 'tblclientrequest' ? 'Prospect' : $message['correstable']));

	//$client or $provider will be set, containing the recipient's id
	if($corresType == 'Client') {
		require_once "client-fns.php";
		$correspondent = getClient($forwardMsg['correspid']);
	}
	else if($corresType == 'Sitter') {
		require_once "provider-fns.php";
		$correspondent = getProvider($forwardMsg['correspid']);
	}
	else if($corresType == 'Prospect') {
		require_once "request-fns.php";
		$correspondent = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$forwardMsg['correspid']} LIMIT 1", 1);
	}
	require_once "provider-fns.php";
	$originator = $forwardMsg['originatorid'] ? getProvider($forwardMsg['originatorid']) : '';
	$corrName = $correspondent['fname'].' '.$correspondent['lname'];
	$transcribed = $message['transcribed'];
	$corrAddr = $forwardMsg['correspaddr'];
	$msgType = $transcribed == 'phone' ? 'Phone' : 
						(!$transcribed || $transcribed == 'email' ? 'Email' : 
						/* $transcribed == 'mail'*/ 'Mail');
?>
<hr><hr><b>Forwarding:</b><hr>
<table border=0 style='padding-left:0px;'>
<?
	// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
	if(!$forwardMsg['inbound']) {
		if($originator) labelRow("From Sitter:", '', "{$originator['fname']} {$originator['lname']} <{$originator['email']}>");
		else labelRow('From:', 'mgrname', $forwardMsg['mgrname']);
	}
	labelRow(($forwardMsg['inbound'] ? 'From' : 'To')." $corresType:", '', $corrName);
	$dateLabel = $transcribed ? 'Logged on:' : ($_REQUEST['queued'] ? 'Added to queue:' : 'Date:');

	labelRow($dateLabel, '', date('D F j, Y g:i a', strtotime($forwardMsg['datetime'])));
	//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
	if(strpos($forwardMsg['correspaddr'], '|')) {
			$adds = array();
			$parts = explode('|', $forwardMsg['correspaddr']);
			foreach($parts as $labelList) {
				$labelList = explode(':', $labelList);
				if(trim($labelList[1])) 
					labelRow("{$labelList[0]}:", '', $labelList[1]);

			}
	}
	else if($msgType == 'Mail') labelRow("Printed:", 'correspaddr', "This message was printed.");
	else labelRow("$msgType:", 'correspaddr', $forwardMsg['correspaddr']);
	labelRow('Subject:', 'subject', $forwardMsg['subject'], null, 'emailInput');
	//textDisplayRow('Message:', 'msgbody', $message['body']);
	labelRow('Message:', '', '<img src="art/spacer.gif" width=400 height=1>', '','','','','raw');
	?>
</table>
<div style='padding-left:10px;padding-top:10px;'>
<?	
		$displayText = $forwardMsg['body'];
		if(noTablesOrLineBreaks($displayText)) {
			$displayText = str_replace("\n\n", '<p>', $forwardMsg['body']);
			$displayText = str_replace("\n", '<br>', $displayText);
		}
		echo $displayText."</div>";;
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}


function noTablesOrLineBreaks($s) {
	static $patterns;
	if(!$patterns) $patterns = explode(',', '<table,<p,<br,<TABLE,<P,<BR');
	foreach($patterns as $pat)
		if(strpos($s, $pat) !== FALSE)
			return false;
	return true;
}

function getMsgFields() {
	static $fields;
	if(!$fields) 
		$fields = explode(',', "msgid,inbound,correspid,correstable,mgrname,subject,body,datetime,transcribed,correspaddr,originatorid,originatortable,tags");
	return $fields;
}

$msgFields = explode(',', "msgid,inbound,correspid,correstable,mgrname,subject,body,datetime,transcribed,correspaddr,originatorid,originatortable");

function getLeashTimeTwilioGateway() {
	// for Twilio on LeashTime's dime
	$settings = ensureInstallationSettings();
	$gateway = new TwilioGateway(
			$settings['twilioAccountID'], 
			"", //$settings['twilioGatewayAccountToken'], 
			$settings['twilioPhoneNumber']);
	$gateway->setAccountToken($settings['twilioGatewayAccountToken']); // value is raw.  constructor tries to decrypt it
	return $gateway;
}

function notifyByLeashTimeSMS($person, $body, $media=null) {
	// send one SMS to one person on LeashTime's dime
	require_once "sms-fns.php";
	require_once "encryption.php"; // used by TwilioGateway constructor
	if(!$_SESSION) chdir(dirname($_SERVER['SCRIPT_FILENAME'])); // for non-session use by encryption.php

	require_once "field-utils.php"; // for phone numbers
	if(!smsEnabled('fromLeashTimeAccount')) return;
	// find an SMS-enabled phone;
	$primary = findSMSPhoneNumberFor($person);
	if(!$primary) return "No text-enabled primary phone number found for {$person['fname']} {$person['lname']}.";
	$correspid = $person['clientid'] ? $person['clientid'] : (
								$person['providerid'] ? $person['providerid'] : $person['userid']);
	$correstable = $person['clientid'] ? 'tblclient' : (
								$person['providerid'] ? 'tblprovider' : 'tbluser');								
	if(!$body) {
		$errorExplanation = "Attempt to send empty message to {$person['fname']} {$person['lname']}.";
		//logError("SMS error: $correstable/$correspid ($primary) : $errorExplanation");
		return $errorExplanation;
	}
	require_once "twilio-gateway-class.php";
	$gateway = getLeashTimeTwilioGateway();
	
	global $biz;  // exists during cronjob execution only
	$bizptr = $_SESSION["bizptr"] ? $_SESSION["bizptr"] : $biz['bizid'];
	
	
	$smsObjOrError = $gateway->sendSMS($primary, $body, $fromNumber=null, $media=null, twilioStatusCallbackURL($bizptr));
	
	if(!is_string($smsObjOrError)) {
		$subject = messageSubjectFromSMSBody($gateway->gsmify($body));
		$msg = array('inbound'=>0,'mgrname'=>'System','datetime'=>date('Y-m-d H:i:s'),  'correstable'=>$correstable,
									'transcribed'=>'','body'=>$body,'subject'=>$subject, 'correspaddr'=>$primary, 'correspid'=>$correspid);
		$msgptr = saveOutboundMessage($msg);
		$metadata = newSmsMetaDataRecord($msgptr, $smsObjOrError, $gateway='Twilio');
//print_r($metadata);		
		$metadataID = insertTable('tblmessagemetadata', $metadata, 1);
		registerLatestSMS($metadata['tophone'], $correstable, $correspid, $bizptr);
		// check for error here
		markBadNumbersIfNecessary($metadata['status'], $metadataID, $metadata['tophone']);
		if($thresholdNotice = approachingThreshold()) {
			saveNewSystemNotificationRequest("Text Message Billing Notice", $thresholdNotice['body'], $extraFields = null);
		}
		// check for error here
	}
	else {
		logError("SMS error: $correstable/$correspid ($primary) : $smsObjOrError");
		if($gateway->errorIndicatesBadNumber($smsObjOrError))
			markBadNumbersIfNecessary('failed', array('correstable'=>$correstable, 'correspid'=>$correspid), $primary);
		$errorExplanation = $gateway->explainError($smsObjOrError);
		return $errorExplanation;
	}
}

function findSMSPhoneNumberFor($person) {
	if($person['clientid'] ||$person['providerid']) {
		// maybe we could test for array_key_exists('homephone', $person) before doing a db fetch...
		$candidateFields = array( 'homephone', 'cellphone', 'workphone');
		if($person['clientid']) $candidateFields[] = 'cellphone2';
		if(array_key_exists('homephone', $person)) 
			$numbers = $person;
		else {
			if($person['clientid'])
				$test = "FROM tblclient WHERE clientid = {$person['clientid']}";
			else if($person['providerid'])
				$test = "FROM tblprovider WHERE providerid = {$person['providerid']}";
			$numbers = fetchFirstAssoc("SELECT ".join(', ', $candidateFields)." $test");
		}
		$primary = primaryPhoneField($numbers, $candidateFields);
		$primary = $numbers[$primary];
		$primary = strpos($primary, '*T') === 0 ? strippedPhoneNumber($primary) : '';
	}
	else { // manager/dispatcher
		// find managerTextPhone for this userid
		$primary = fetchRow0Col0(
				"SELECT value 
					FROM tbluserpref 
					WHERE userptr = {$person['userid']} AND property = 'managerTextPhone' 
					LIMIT 1", 1);
		if(strpos("$primary", 'INVALID') === 0) $primary = null;
	}
	return $primary;
}

function saveInboundSMSMessage($descr, $originator) {
	require_once "sms-fns.php";
	require_once "twilio-gateway-class.php";
	// originator: ownertable/ownerptr/phone/datetime/petbizptr
//echo "ORIGINATOR: ".print_r($originator,1)."<hr>";
	$metadata = newInboundSmsMetaDataRecord($descr);
	$msg = array();
	$msg['inbound'] = 1;
	$msg['correspid'] = $originator['ownerptr'];
	$msg['correstable'] = $originator['ownertable'];
	$msg['correspaddr'] = $originator['phone'];
	$msg['body'] = $descr['Body'];
	$msg['mgrname'] = 'System';
	$msg['subject'] = messageSubjectFromSMSBody($msg['body']);
	$msg['datetime'] = date('Y-m-d H:i:s'); // $metadata['datesent']; <== may be the wrong timezone
	$msg['transcribed'] = '';
	$msgptr = insertTable('tblmessage', $msg, 1);
	$metadata['msgptr'] = $msgptr;
	$metadataID = insertTable('tblmessagemetadata', $metadata, 1);
//echo "INSERTING: ".print_r($metadata,1)."<hr>";
//if(mysql_error()) logError('sv inbound sms: '.mysql_error());
	return $msgptr;
}



function twilioStatusCallbackURL($bizptr) {
	return globalURL("twilio-status-callback.php?bizptr=$bizptr");
}

function registerLatestSMS($phone, $ownertable, $ownerptr, $bizptr) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	replaceTable('tbllatestsms', array('phone'=>$phone, 'petbizptr'=>$bizptr, 'ownertable'=>$ownertable, 'ownerptr'=>$ownerptr, 'datetime'=>date('Y-m-d H:i:s')), 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
}
	

function notifyByEmail($person, $subject, $body, $cc=null, $mgrname, $html=false) {
	// Notify person by email immediately
	if(!$person['email']) return "No email found for {$person['fname']} {$person['lname']}.";
	$error = sendEmail($person['email'], $subject, $body, $cc, $html);
	if(!$error) {
		$correspid = $person['clientid'] ? $person['clientid'] : $person['providerid']; // what about managers?
		$correstable = $person['clientid'] ? 'tblclient' : 'tblprovider';
		$mgrname = $mgrname ? $mgrname : 'System';
		$correspaddr = $person['email'];
		if($cc) $correspaddr .= ", $cc";
		$msg = array('inbound'=>0,'mgrname'=>$mgrname,'datetime'=>date('Y-m-d H:i:s'),  'correstable'=>$correstable,
									'transcribed'=>'','body'=>$body,'subject'=>$subject, 'correspaddr'=>$correspaddr, 'correspid'=>$correspid);
		saveOutboundMessage($msg);
	}
	else return $error;
}

function clearEmailQueueIfDisabled() {
	// if the mail queue has been disabled by LT Staff for longer than N minutes, clear the queue
	// return TRUE if this was called for
	// $mailQueueDisabled looks like: 2014-07-29 07:47:52 (America/Chicago)
	$mailQueueDisabled = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mailQueueDisabled' LIMIT 1");
	if(strlen((string)$mailQueueDisabled) < strlen("2015-07-02 11:42:51")) return;
	$mailQueueDisabled = substr($mailQueueDisabled, 0, strlen("2015-07-02 11:42:51"));
	if($mailQueueDisabled = strtotime($mailQueueDisabled)) { // strtotime returns FALSE if not a date or datetime
		$deleteAfterHours = 48;  // 48 hours 
		if(time() - $mailQueueDisabled > $deleteAfterHours * 60 * 60) {
			deleteTable('tblqueuedemail', "1=1");
			if($count = mysql_affected_rows()) logError("Email queue cleared ($count msgs deleted after at least $deleteAfterHours hours).");
			return true;
		}
	}
}

function enqueueEmailNotification($person, $subject, $body, $cc=null, $mgrname, $html=false, $originator=null, $tags=null, $attachments=null) {

	if(clearEmailQueueIfDisabled()) return;  // don't queue up email if queue has been disabled longer than the threshold period

global $TESTEMAILOVERRIDE;
if($TESTEMAILOVERRIDE) $person['email'] = $TESTEMAILOVERRIDE;
	
	if(!$person['email']) return array("No email found for {$person['fname']} {$person['lname']}.");
	$correspid = $person['clientid'] ? $person['clientid'] : ($person['providerid'] ? $person['providerid'] : $person['userid']);
	$correstable = $person['clientid'] ? 'tblclient' : ($person['providerid'] ? 'tblprovider' : 'tbluser');
	if($originator) {
		$originatorid = $originator['clientid'] ? $originator['clientid'] : ($originator['providerid'] ? $originator['providerid'] : $originator['userid']);
		$originatortable = $originator['clientid'] ? 'tblclient' : ($originator['providerid'] ? 'tblprovider' : 'tbluser');
	}
	$mgrname = $mgrname ? $mgrname : 'System';
	$newId = insertTable('tblqueuedemail', 
							array('recipients'=>$person['email'],
									'subject'=>$subject,
									'body'=>$body,
									'cc'=>$cc,
									'addedtime'=>date('Y-m-d H:i:s'),
									'html'=>($html ? 1 : 0),
									'tblmsgfields'=>queuedMessageDescription($mgrname, $correspid, $correstable, $originatorid, $originatortable, $tags, $attachments)), 1);
	return $newId ? $newId : array(mysql_errno());
}

function enqueueMassEmailNotification($toPersons, $subject, $body, $cc=null, $bccPersons=null, $mgrname, $html=false, $originator=null) {
	if($toPersons) foreach($toPersons as $person) {
		$correspid = $person['clientid'] ? $person['clientid'] : ($person['providerid'] ? $person['providerid'] : $person['userid']);
		$correstable = $person['clientid'] ? 'tblclient' : ($person['providerid'] ? 'tblprovider' : 'tbluser');
		$correspondents[] = $correstable."_$correspid";
		$label = firstNonEmpty(array($person['fullname'], $person['clientname'], $person['providername']), $person['shortname']);
		if(!$label && $person['fname']) $label = "{$person['fname']} {$person['lname']}";
		$recipients[] = $label ? "$label <{$person['email']}>" : $person['email'];
	}
	if($bccPersons) foreach($bccPersons as $person) {
		$correspid = $person['clientid'] ? $person['clientid'] : ($person['providerid'] ? $person['providerid'] : $person['userid']);
		$correstable = $person['clientid'] ? 'tblclient' : ($person['providerid'] ? 'tblprovider' : 'tbluser');
		$correspondents[] = $correstable."_$correspid";
		$label = firstNonEmpty(array($person['fullname'], $person['clientname'], $person['providername']), $person['shortname']);
		if(!$label && $person['fname']) $label = "{$person['fname']} {$person['lname']}";
		$bcc[] = $label ? "$label <{$person['email']}>" : $person['email'];
	}
	if($originator) {
		$originatorid = $originator['clientid'] ? $originator['clientid'] : ($originator['providerid'] ? $originator['providerid'] : $originator['userid']);
		$originatortable = $originator['clientid'] ? 'tblclient' : ($originator['providerid'] ? 'tblprovider' : 'tbluser');
	}
	$mgrname = $mgrname ? $mgrname : 'System';
	
	$recipients = $recipients ? join(',', $recipients) : ' ';
	$bcc = $bcc ? join(',', $bcc) : '';
	$newId = insertTable('tblqueuedemail', 
							array('recipients'=>$recipients,
									'subject'=>$subject,
									'body'=>$body,
									'cc'=>$cc,
									'bcc'=>$bcc,
									'addedtime'=>date('Y-m-d H:i:s'),
									'html'=>($html ? 1 : 0),
									'tblmsgfields'=>queuedMassMessageDescription($mgrname, $correspondents, $originatorid, $originatortable)), 1);
	return $newId ? $newId : array(mysql_errno());
}


function clearedLogJamTEST($mailQueueSendStarted) {
	/* NOTE
	*  This version does NOT actually clear the log jam or notify support.
	*  It merely sends an email to matt, for testing purposes
	*  deletion of mailQueueSendStarted is disabled, the message is altered
	*  and it ALWAYS returns false.
	*/
	if(!$mailQueueSendStarted) return;
	// if $mailQueueSendStarted is less than 10 minute old, do nothing
	if(time() - strtotime($mailQueueSendStarted) < 10 * 60) return false;
	// if messages have been sent in the last 5 minutes, do nothing
	$fiveMinutesAgo = date('Y-m-d H:i:s', strtotime("- 5 minutes"));
	$recentMsg = fetchRow0Col0("SELECT datetime FROM tblmessage WHERE datetime > '$fiveMinutesAgo' LIMIT 1", 1);
	if($recentMsg)  return false;
	// else clear logjam
	deleteTable('tblpreference', "property = 'mailQueueSendStarted'", 1);
	logError("Mail queue logjam cleared automatically because no messages sent since $fiveMinutesAgo.");
	// notify support logjam was cleared
	toggleSupportCronMailPrefs($switchback=false);
	global $db;
	//$msgbody = "$db mail queue logjam cleared automatically at ".date('Y-m-d H:i:s')." because no messages  had been sent since $fiveMinutesAgo.<p>Was jammed since $mailQueueSendStarted.";
	//if($error = sendEmail("support@leashtime.com", "Logjam cleared: $db", $msgbody, '', 'html', "LeashTime queue", $bcc, $extraHeaders, $attachments)) {
	$msgbody = "$db mail queue logjam DETECTED automatically at ".date('Y-m-d H:i:s')." because no messages  had been sent since $fiveMinutesAgo.<p>Was jammed since $mailQueueSendStarted.";
	$msgbody .= <<<MSG
<p><u>Reminders</u>

<ol><li>You can review all businesses with non-empty mail queues by clicking the <b>Q!</b> radio button in the white box on the dashboard.
<li>You can examine the business&apos;s email settings and email queue details without connecting to the business by clicking the 
red number in the Email Queue column of the dashboard listing for that business.
<li>To survey email settings for all businesses that use the same SMTP server as the business in question, click the SMTP host name for that business.
</ol>
<p><u>Explanation</u>
<p>When starting to handle the email queue for a business, LeashTime notes the time (<i>mailQueueSendStarted</i>), and it 
&quot;forgets&quot; that time (clears <i>mailQueueSendStarted</i>) when it finishes with the queue.  
<p>Before it starts handling the queue it checks for <i>mailQueueSendStarted</i> to see if the last cycle is still in progress.
It will NOT start processing the queue again if it believes the last cycle is in progress.

<ol><li>If the last start time (<i>mailQueueSendStarted</i>) is less than 10 minutes ago, it does nothing.
<li>If <i>mailQueueSendStarted</i> is older than 10 minutes but LeashTime has sent a message in the last five minutes, it does nothing.
<li>Otherwise, it assumes the queue is jammed (and that the last cycle&apos;s process is dead) so it clears the stored 
<i>mailQueueSendStarted</i> and tries processng the email queue afresh.
</ol>

Very few email preference misconfigurations lead to log jams (mail queue process death), but when a log jam occurs, it may point to 
a serious issue.

<p>The targeted smtp survey (reminder 3) feature was added and the logjam notification feature was changed to send email to support instead of matt on 11/2/2021.
MSG;
	if($error = sendEmail("support@leashtime.com", "Logjam DETECTED: $db", $msgbody, '', 'html', "LeashTime queue", $bcc, $extraHeaders, $attachments)) {
		
		if($error == 'BadCustomSMTPSettings')
			$error = "SMTP Server connection settings (host, port, username, or password) are  incorrect.<p>"
								."Please review your "
								.fauxLink('<b>Outgoing Email</b> preferences', 'if(window.opener) window.opener.location.href="preference-list.php?show=4";window.close();', 1, 'Go there now')
								." in ADMIN > Preferences";
	}
	
	toggleSupportCronMailPrefs($switchback=true);
	echo date('Y-m-d H:i:s')." $db mail queue logjam cleared automatically.\n";
	//return true;
} 

function toggleSupportCronMailPrefs($switchback=false) {  // NEEDS WORK -- NO SESSION
	global $installationSettings, $cachedMailPrefs, $scriptPrefs;
	if(!$switchback) {
		$cachedMailPrefs['emailFromAddress'] = $scriptPrefs['emailFromAddress'];
		$cachedMailPrefs['shortBizName'] = $scriptPrefs['shortBizName'];
		$cachedMailPrefs['bizName'] = $scriptPrefs['bizName'];
		$cachedMailPrefs['emailHost'] = $scriptPrefs['emailHost'];
		$cachedMailPrefs['emailUser'] = $scriptPrefs['emailUser'];
		$cachedMailPrefs['emailPassword'] = $scriptPrefs['emailPassword'];
		$cachedMailPrefs['smtpPort'] = $scriptPrefs['smtpPort'];
		$cachedMailPrefs['smtpSecureConnection'] = $scriptPrefs['smtpSecureConnection'];
		$cachedMailPrefs['emailBCC'] = $scriptPrefs['emailBCC'];
		$cachedMailPrefs['defaultReplyTo'] = $scriptPrefs['defaultReplyTo'];
		
		$scriptPrefs['emailFromAddress'] = 'support@leashtime.com';
		$scriptPrefs['shortBizName'] = 'LeashTime';
		$scriptPrefs['bizName'] = 'LeashTime';
		$scriptPrefs['emailHost'] = $installationSettings['smtphost'];
		$scriptPrefs['emailUser'] = $installationSettings['smtpuser'];
		$scriptPrefs['emailPassword'] = $installationSettings['smtppassword'];
		$scriptPrefs['smtpPort'] = $installationSettings['smtpPort'];
		$scriptPrefs['smtpSecureConnection'] = null;
		$scriptPrefs['emailBCC'] = null;
		$scriptPrefs['defaultReplyTo'] = null;
		//foreach($cachedMailPrefs as $k => $v) echo "BEFORE $k: $v<br>";
	}
	else {
		foreach($cachedMailPrefs as $k => $v) {
			$scriptPrefs[$k] = $v;
			//echo "AFTER $k: $v<br>";
		}
	}
}


	 

function sendAllQueuedEmail() {  // return message ids keyed by queued message ids
	global $scriptPrefs;
	$scriptPrefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference"); // in preference-fns.php
	$mailQueueDisabled = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mailQueueDisabled' LIMIT 1");
	if($mailQueueDisabled) {
		logError("Mail queue processing was disabled $mailQueueDisabled.");
		return;
	}
	$mailQueueSendStarted = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mailQueueSendStarted' LIMIT 1");
	if($mailQueueSendStarted) {
		clearedLogJamTEST($mailQueueSendStarted);  // change this to if(!clearedLogJam(...)) {logError(...); return;}
		logError("Mail queue processing started at $mailQueueSendStarted is still in progress.");
		return;
	}
	else insertTable('tblpreference', array('property'=>'mailQueueSendStarted', 'value'=>date('Y-m-d H:i:s')), 1);
	$messages = array();
	$sent = 0;
	$errorCount = 0;
	$errorQuota = 5; // stop after encountering this many errors
	foreach(fetchAssociations("SELECT * FROM tblqueuedemail ORDER BY emailid") as $i => $queuedmsg) {
		// enforce a quota for this business
		if($quotaReached = emailQuotaExceeded($i)) {
			logError("Queue halt after LT quota reached ($quotaReached). Msgs sent: $sent.  Errors: $errorCount");
			break;
		}
		if($errorCount >= $errorQuota) {
			logError("Queue halt after $errorQuota errors. Msgs sent: $sent.  Errors: $errorCount");
			break;
		}
		//$messages[$queuedmsg['emailid']] = sendQueuedEmail($queuedmsg);
		$sentMessageId = sendQueuedEmail($queuedmsg);
		if($sentMessageId && !is_array($sentMessageId)) {
			$messages[$queuedmsg['emailid']] = $sentMessageId;
			$sent += 1;
		}
		else if($sentMessageId) {
			$errorCount += 1;
			$error = $sentMessageId['error'];
			// if authentication error, log cessation as an error and drop out
			if(strpos($error, 'Connection timed out') !== FALSE) {
				logError("Queue halted after connection timed out. Msgs sent: $sent");
				break;
			}
			if(strpos($error, 'xpected response code 250 but got code ""') !== FALSE) {
				logError("Queue halted after [Expected response code 250 but got...]. Msgs sent: $sent");
				break;
			}
			if(strpos($error, 'authenticat') !== FALSE) {
				logError("Queue halt after authentication error. Msgs sent: $sent");
				break;
			}
		}
		else ($sentMessageId = null) ; // should not happen
	}
	deleteTable('tblpreference', "property = 'mailQueueSendStarted'", 1);
	return $messages;
}

function emailQuotaExceeded($queueIndex) {
	// return false or a description of the throttling condition
	// TBD: integrate with TOTAL email output (incl immediate email)
	// TBD: find way to integrate needs of immediate emails with the queue
	// TBD: explore sorting of queue based on a priority setting as well as datetime
	//  e.g., queued messages are sorted first based on a priority setting (ASC), then by datetime
	//       mass emails would (usually) be assigned less priority (positive value)
	//			 except in emergencies where they might be assigned a negative value
	global $scriptPrefs;
	// apply any quota rules here
	if($throttle = $scriptPrefs['mailqueuethrottle']) {
		if(is_numeric($throttle)) { // max number of messages to be sent per cycle
			$throttle = (int)$throttle;
			if($throttle > 0) {
				if($queueIndex >= $throttle)
					return $throttle;
			}
		}
	}
	return false;
}

function sendQueuedEmail($queuedmsg) {
	//global $installationSettings, $suppressErrorLoggingOnce; // from common/db_fns.php
	global $installationSettings, $suppressErrorLoggingOnce, $EMAIL_TRANSPORT_ERRORS; // $installationSettings is from common/db_fns.php
	if($EMAIL_TRANSPORT_ERRORS[$db]) return;  // a showstopper occurred earlier in this script (in SwiftMailer) and was logged.  Don't try again.
	
//logError("sendQueuedEmail: about to send {$queuedmsg['emailid']}");	
  $suppressErrorLoggingOnce = true; // errors for queued email are logged below
  
  // process tblmsgfields to find any special originator name
  
  
  foreach(array('recipients'=>'To', 'cc'=>'CC', 'bcc'=>'BCC') as $field => $label)
  	if($queuedmsg[$field]) $correspaddr[] = "$label:{$queuedmsg[$field]}";
  if($correspaddr) $correspaddr = join('|', $correspaddr);
  
	$msg = array('inbound'=>0,'datetime'=>date('Y-m-d H:i:s'),
								'transcribed'=>'','body'=>$queuedmsg['body'],'subject'=>$queuedmsg['subject'], 
								'correspaddr'=>$correspaddr);
	foreach(explode('||', $queuedmsg['tblmsgfields']) as $piece) {
		$pair = explode('|', $piece);
		$msg[$pair[0]] = $pair[1];
	}
	
	if($msg['originatorid']) {
		$idfield = $msg['originatortable'] == 'tblclient' ? 'clientid' : 'providerid';
		$senderLabel = fetchRow0Col0(
			"SELECT CONCAT_WS(' ', fname, lname) 
				FROM {$msg['originatortable']} 
				WHERE $idfield = {$msg['originatorid']} LIMIT 1");
	}
//if($db = 'dogslife') { echo print_r($msg,1)."\n".print_r(explode('||', $queuedmsg['tblmsgfields']),1)."\n"; exit; }  
	$attachments = attachmentsFromMsgFields($queuedmsg['tblmsgfields']);
	
	$error = sendEmail($queuedmsg['recipients'], $queuedmsg['subject'], $queuedmsg['body'], $queuedmsg['cc'], $queuedmsg['html'], $senderLabel, $queuedmsg['bcc'], $extraHeaders=null, $attachments);
	if(!$error) {
		cleanUpAttachments($queuedmsg);
		if($msg['correspondents']) $msgid = saveOutboundMessages($msg);
		else $msgid = saveOutboundMessage($msg);
		deleteTable('tblqueuedemail', "emailid={$queuedmsg['emailid']}", 1);
		if(strpos($queuedmsg['body'], 'confirmationid=') !== FALSE) {
			require_once "confirmation-fns.php";
			linkConfirmationToMessage($msgid, null, $queuedmsg['body']);
		}
		return $msgid;
	}
	else {
		logError("tblqueuedemail: {$queuedmsg['emailid']}: $error");
		logError("... [{$queuedmsg['recipients']}] [{$queuedmsg['cc']}]");

//global $scriptPrefs;logError("SCRIPT PREFS: ".print_r($scriptPrefs, 1));		
		if(strpos($error, 'authentication failure')) {
			//$host = mPrefSet('emailHost') ? mPrefSet('emailHost') : $installationSettings['smtphost'];
			//$username = mPrefSet('emailUser') ? mPrefSet('emailUser') : $installationSettings['smtpuser'];
			//$password = mPrefSet('emailPassword') ? mPrefSet('emailPassword') : $installationSettings['smtppassword'];
			//$port = mPrefSet('smtpPort') ? mPrefSet('smtpPort') : 25;
			//$auth = mPrefSet('smtpAuthentication') ? mPrefSet('smtpAuthentication') : true;
			//$ssl = mPrefSet('smtpSecureConnection', null);
			//logError("tblqueuedemail: {$queuedmsg['emailid']}: $host / $username / $password / $port / $auth / $ssl");
		}
		return array('error'=>$error);
	}
}

function cleanUpAttachments($queuedmsg) {
	// message has been sent.  clear out unnecessary attachment files
	// clear only paths that are not referenced by any other queued message
	$attachments = attachmentsFromMsgFields($queuedmsg['tblmsgfields']);
//logError('Diag: tblmsgfields ='.$queuedmsg['tblmsgfields']); 
//logError('Diag: attachments ='.print_r($attachments, 1)); 
	if(!$attachments) return;
	$others = fetchAssociations(
		"SELECT tblmsgfields 
			FROM tblqueuedemail 
			WHERE emailid <> {$queuedmsg['emailid']}
			AND tblmsgfields IS NOT NULL");
	foreach($others as $fields) {
		$otherattachments = attachmentsFromMsgFields($fields);
		if(!$otherattachments) continue;
		foreach($otherattachments as $att)
			$allFiles[] = $att['path'];
		$allFiles = array_unique($allFiles);
	}
//logError('Diag: allFiles ='.join(', ', $allFiles)); 
	foreach($attachments as $att) {
		// if any others refer to these attachments, continue
		if(in_array($att['path'], (array)$allFiles)) continue;
		else {
			unlink($att['path']);
//logError("Diag: unlink = {$att['path']} Success: ".(file_exists($att['path']) ? 'no' : 'yes'));
		}
	}
}

function attachmentsFromMsgFields($tblmsgfields) {
	if($tblmsgfields) {
		foreach(explode('||', $tblmsgfields) as $pair) {
			$pair = explode('|', $pair);
			if($pair[0] == 'attachments')
				$attachments = attachmentsFromXML($pair[1]);
		}
	}
	return $attachments;
}


function attachmentsFromXML($xml) {
	foreach(simplexml_load_string($xml)->children() as $attEl) {
		$attachment = array();
		foreach($attEl->children() as $field)
			$attachment[$field->getName()] = (string)$field;
		$attachments[] = $attachment;
	}
	return $attachments;
}

function queuedMassMessageDescription($mgrname, $correspondents, $originatorid, $originatortable) {
	return "mgrname|$mgrname||correspid|-1||correspondents|".join(',', $correspondents)
					.($originatorid ? "||originatorid|$originatorid||originatortable|$originatortable" : '');
}

function queuedMessageDescription($mgrname, $correspid, $correstable, $originatorid, $originatortable, $tags, $attachments=null) {
	return "mgrname|$mgrname||correspid|$correspid||correstable|$correstable"
					.($originatorid ? "||originatorid|$originatorid||originatortable|$originatortable" : '') // ||tags|$tags
					.($attachments ? "||attachments|".attachmentsXML($attachments) : '')
					.($tags ? "||tags|$tags" : '');
}

function attachmentsXML($attachments) {
	$xml = "<attachments>";
	foreach($attachments as $att) 
		$xml .= "<att><file><![CDATA[{$att['file']}]]></file><path><![CDATA[{$att['path']}]]></path></att>";
	return "$xml</attachments>";
}

function saveOutboundMessages($msg) {
//echo print_r($msg);	
	foreach(explode(',', $msg['correspondents']) as $correspondent) {
		$correspondent = explode('_', $correspondent);
		$msg['correspid'] = $correspondent[1] ? $correspondent[1] : null; // DO NOT FIX! -989;
		$msg['correstable'] = $correspondent[0];
		$ids[] = saveOutboundMessage($msg);
	}
	return $ids;
}

function saveOutboundMessage($msg) {
	$msgFields = getMsgFields();
	//$msg['correspid'] = $msg['correspid'] ? $msg['correspid'] : null; // DO NOT FIX! -989;
	
	$msg['inbound'] = 0;
	//$msg['mgrname'] = isset($msg['mgrname']) ? $msg['mgrname'] : $_SESSION["auth_login_id"];
	$msg['mgrname'] = isset($msg['mgrname']) ? $msg['mgrname'] : getUsersFromName();
	$msg['datetime'] = date('Y-m-d H:i:s');
	$msg['transcribed'] = '';
	$msg['body'] = $msg['msgbody'] ? $msg['msgbody'] : $msg['body'];
	foreach($msg as $field =>$val) 
		if(!in_array($field, $msgFields)) unset($msg[$field]);
	return insertTable('tblmessage', $msg, 1);
}

function logMessage($msg) {
	global $msgFields;
	$msg['mgrname'] = isset($msg['mgrname']) ? $msg['mgrname'] : $_SESSION["auth_login_id"];
	$msg['datetime'] = date('Y-m-d H:i:s');
	$msg['body'] = $msg['msgbody'];
	foreach($msg as $field =>$val) 
		if(!in_array($field, $msgFields)) unset($msg[$field]);
	insertTable('tblmessage', $msg, 1);
}

function logOutgoingMessage($msg) {
	global $msgFields;
	// keys: transcribed(mail,phone), correspid, correstable, subject, body
	$msg['mgrname'] = isset($msg['mgrname']) ? $msg['mgrname'] : $_SESSION["auth_login_id"];
	$msg['datetime'] = date('Y-m-d H:i:s');
	$msg['inbound'] = 0;
	foreach($msg as $field =>$val) 
		if(!in_array($field, $msgFields)) unset($msg[$field]);
	insertTable('tblmessage', $msg, 1);
}

function getMessage($id) {
	//if(mattOnlyTEST() && in_array('tblmessagearchive', fetchCol0("SHOW TABLES"))) echo "OK!";
	return fetchFirstAssoc("SELECT * FROM tblmessage WHERE msgid = $id LIMIT 1");
}

function getArchivedMessage($id, $raw=false) {
	if(!$_SESSION["hasmessagearchive"]) return;
	$body = $raw ? 'body' : 'UNCOMPRESS(body) as body';
	//if(mattOnlyTEST()) {print_r(join(', ', fetchCol0("DESC tblmessagearchive")));}
	return fetchFirstAssoc(
		"SELECT msgid, inbound, correspid, correstable, mgrname, subject, 
						$body, datetime, transcribed, tags, correspaddr, originatortable, originatorid, 
						hidefromcorresp FROM tblmessagearchive 
			WHERE msgid = $id LIMIT 1");
}


function getManagerName() {
	return (userRole() == 'o' || userRole() == 'd')
		? (trim($_SESSION["auth_username"]) ? trim($_SESSION["auth_username"]) : $_SESSION["auth_login_id"])
		: (userRole() == 'p' ? $_SESSION["shortname"] : $_SESSION["auth_login_id"]);
}

function getUsersFromName() {
	if(!$_SESSION["auth_user_id"]) return 'System';
	require_once "preference-fns.php";
	$from = getUserPreference($_SESSION["auth_user_id"], 'managerNickname');
	return $from ? $from : getManagerName();
}

function getSMSCommsFor($correspondent, $filter=null, $clientflg=true, $totalMsg=false) {
	if($correspondent != -1) {
		$correspondentId = $correspondent[$clientflg ? 'clientid' : ($correspondent['providerid'] ? 'providerid' : ($correspondent['userid'] ? 'userid' : ''))];
		$correspondentName = $correspondent['name'] ? $correspondent['name'] : join(' ',array($correspondent['fname'], $correspondent['lname']));
		$cTable = $clientflg ? 'tblclient' : ($correspondent['providerid'] ? 'tblprovider' : ($correspondent['userid'] ? 'tbluser' : ''));
		$comms = array();
		$filter = "((correspid = $correspondentId AND correstable = '$cTable') 
										OR (originatorid = $correspondentId AND originatortable = '$cTable'))".($filter ? "AND $filter" : '');
	}
	else $filter = $filter; // :)
//if(mattOnlyTEST()) {echo "SELECT * FROM tblmessage WHERE $filter";exit;} if(TRUE) { // if(FALSE) { //
if(TRUE) { //if(staffOnlyTEST() || dbTEST('tonkapetsitters')) { // improvement: don't collect all messages into memory; just the ones with metas
	$msgids = array();
	$messages = array();
screenLogPageTime("getSMSCommsFor about to fetch messages: ");
	$messages = fetchAssociations(
		 "SELECT m.*, msgptr, status, numsegments, price, priceunit, fromphone, tophone
			FROM tblmessage m
			LEFT JOIN tblmessagemetadata ON msgptr = msgid
			WHERE $filter AND msgptr IS NOT NULL
			ORDER BY datetime");
  /*while($msg = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$msgids[] = $msg['msgid'];
		$meta = fetchFirstAssoc("SELECT * FROM tblmessagemetadata WHERE msgptr = {$msg['msgid']} AND type = 'sms' LIMIT 1", 'msgptr', 1);
		if($meta) {
			$metas[] = $meta;
			$messages[] = $msg;
		}
	}*/
screenLogPageTime('getSMSCommsFor found '.count($messages).": messages and ".count($metas)." metas: ");
}
else {
	$messages = fetchAssociations($sql = "SELECT * FROM tblmessage WHERE $filter ORDER BY datetime");
screenLog("SQL: $sql");
screenLogPageTime('getSMSCommsFor found '.count($messages).": messages");
	// SELECT * FROM tblmessage WHERE datetime > '2018-04-16 17:07:17' AND inbound = 1 ORDER BY datetime
	$msgids = array();
	foreach($messages as $msg) $msgids[] = $msg['msgid'];
	if($msgids) {
		$metas = fetchAssociationsKeyedBy("SELECT * FROM tblmessagemetadata WHERE msgptr IN (".join(',', $msgids).") AND type = 'sms'", 'msgptr', 1);
		foreach($messages as $i => $msg) if(!$metas[$msg['msgid']]) unset($messages[$i]);
		$messages = array_merge($messages);
	}
screenLogPageTime('getSMSCommsFor found '.count($metas)." metas: ");
}
	foreach($messages as $msg)
		if($msg['originatorid'] && $msg['originatortable'])
			$originators["{$msg['originatortable']}-{$msg['originatorid']}"] = 
					array('originatortable'=>$msg['originatortable'], 'originatorid'=>$msg['originatorid']);
	foreach((array)$originators as $k => $v)
		$originators[$k] = 
			$v['originatortable'] == 'tblclient'
			 ? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$v['originatorid']}")
			 : fetchRow0Col0("SELECT ifnull(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = {$v['originatorid']}");

	require_once "field-utils.php";

	foreach($messages as $msg) {
		if($correspondent == -1) {
			if($msg['correstable'] == 'tbluser') $corr = getUserByID($msg['correspid']);
			else {
				$tblid = $msg['correstable'] == 'tblclient' ? 'clientid' : 'providerid';
				$corr = fetchFirstAssoc("SELECT fname, lname FROM {$msg['correstable']} WHERE $tblid = {$msg['correspid']} LIMIT 1", 1);
			}
			$sender = "{$corr['fname']} {$corr['lname']}";
		}
		else $sender = $msg['inbound'] 
				? $correspondentName 
				: ($msg['originatorid'] ? $originators["{$msg['originatortable']}-{$msg['originatorid']}"] : $msg['mgrname']);
		
		//transitional code
		$meta = $metas ? $metas[$msg['msgid']] : $msg;
		// end transitional code
		
		$comms[] = array('date'=>shortDateAndTime(strtotime($msg['datetime'])),
									'sortdate'=>$msg['datetime'],
									'displaydate'=>relativeDateTime($msg['datetime']), // see db_fns.php
									'body'=>$msg['body'],
									'sender' =>$sender,
									'listid'=>count($comms),
									'inbound' => $msg['inbound'],
									'correstable' => $msg['correstable'],
									'correspid' => $msg['correspid'],
									'status'=>$meta['status'],
									'numsegments'=>$meta['numsegments'],
									'price'=>$meta['price'],
									'priceunit'=>$meta['priceunit'],
									'fromphone'=>$meta['fromphone'],
									'tophone'=>$meta['tophone'],
									'msgid'=>$msg['msgid']);
		if($totalMsg) {
			$comms[count($comms)-1]['body'] = $msg['body'];
			$comms[count($comms)-1]['type'] = "message";
		}
	}
	return $comms;
}

function getSMSComm($msgOrMsgId, $metdadata=null) {
	if(is_array($msgOrMsgId)) $msg = $msgOrMsgId;
	else {
		$msg = getMessage($msgOrMsgId);
		if(!$msg) {
			$msg = getArchivedMessage($msgOrMsgId);
			$archived = 1;
		}
	}
	$msgid = $msg['msgid'] ? $msg['msgid'] : $msg['msgptr'];
	$metadata = $metdadata ? $metdadata : metaDataForMessage($msg['msgid']);

	return array(
		'date'=>shortDateAndTime(strtotime($msg['datetime'])),
		'sortdate'=>$msg['datetime'],
		'displaydate'=>relativeDateTime($msg['datetime']), // see db_fns.php
		'body'=>$msg['body'],
		'sender' =>$sender,
		//'listid'=>count($comms), // ?
		'inbound' => $msg['inbound'],
		'correstable' => $msg['correstable'],
		'correspid' => $msg['correspid'],
		'status'=>$metadata['status'],
		'numsegments'=>$metadata['numsegments'],
		'price'=>$metadata['price'],
		'priceunit'=>$metadata['priceunit'],
		'fromphone'=>$metadata['fromphone'],
		'tophone'=>$metadata['tophone'],
		'msgid'=>$msgid,
		'metadataid'=>$metadata['metadataid']);
}
	
function getCommsFor($correspondent, $filter, $clientflg=true, $totalMsg=false, $postFilterClause='', $inboundOnly=false, $excludeHidden=false) {
	global $requestTypes;
	// date subject sender
	// if client flg, collect requests according to filter and make them into comm rows
	// $correspondent may be a client, a sitter, a user, or...
	//  CLIENTS, SITTERS, CLIENTSANDSITTERS
	$correspondentNames = array();
	$corresTable = 
		$correspondent == 'CLIENTS' ? 'tblclient' : (
		$correspondent == 'SITTERS' ? 'tblprovider' : (
		$correspondent == 'CLIENTSANDSITTERS' ? 'CLIENTSANDSITTERS' : (
		$correspondent['clientid'] ? 'tblclient' : (
		$correspondent['providerid'] ? 'tblprovider' : 'tbluser'))));
	$idFields = array('tbluser' => 'userid', 'tblclient' => 'clientid', 'tblprovider'  => 'providerid');
	//$correspondentId = $correspondent[$clientflg ? 'clientid' : 'providerid'];
	
	if(is_array($correspondent)) {
		$correspondentId = $correspondent[$idFields[$corresTable]];
		$correspondentName = $correspondent['name'] ? $correspondent['name'] : join(' ',array($correspondent['fname'], $correspondent['lname']));
		$whereCondition = $corresTable == 'tblclient' ? "clientptr = $correspondentId" : "providerptr = $correspondentId";
	}
	else {
		$correspondentGroup = $correspondent;
		$whereCondition = 
			$corresTable == 'CLIENTSANDSITTERS' ? "(correstable IN ('tblclient', 'tblprovider'))" 
				: "correstable = '$corresTable'";
	}
	$comms = array();


	if(FALSE && $corresTable != 'tbluser') { // begin postcard search (OBSELETE)
		$pcfilter = $filter ? str_replace('datetime', 'created', "AND $filter") : '';
		if(in_array('tblpostcard', fetchCol0("SHOW TABLES"))) {
			$cards = fetchAssociations(
				"SELECT *, CONCAT_WS(' ', p.fname, p.lname) as provider, CONCAT_WS(' ', c.fname, c.lname) as client
					FROM tblpostcard
					LEFT JOIN tblprovider p ON providerid = providerptr
					LEFT JOIN tblclient c ON clientid = clientptr
					WHERE $whereCondition $pcfilter");
			foreach($cards as $card) {
				$sender = $clientflg ? $card['provider'] : "To: {$card['client']}";
				$subject = "Postcard";
				$title = 'View client postcard.';
				$comms[] = array('date'=>shortDateAndTime(strtotime($card['created'])),
											'sortdate'=>$card['created'],
											'subject'=>viewPostcardLink($subject, "client={$card['clientptr']}&open={$card['cardid']}", $title),
											'sortsubject'=>$subject,
											'sender' =>$sender,
											'listid'=>count($comms));
				if($totalMsg) {
					$comms[count($comms)-1]['body'] = "https://{$_SERVER["HTTP_HOST"]}/request-edit.php?id={$req['requestid']}&updateList=";
					$comms[count($comms)-1]['type'] = "request";
				}
			}

		}
	} // end postcard search (OBSELETE)

	if($corresTable != 'tbluser') { // begin request search
		$reqfilter = $filter ? "AND $filter" : '';
		if(!$clientflg) $timeOffReqClause = 
		 " OR (requesttype = 'TimeOff' $reqfilter AND extrafields LIKE '%\'providerid\'>$correspondentId<%')";

		$requestWhere = 
			$correspondentGroup == 'SITTERS' ? 'providerptr IS NOT NULL' : (
			$correspondentGroup ? '1=1' : $whereCondition);
		$sql = "SELECT * FROM tblclientrequest WHERE ($requestWhere $reqfilter ) $timeOffReqClause $postFilterClause";

		$sql = str_replace('datetime', 'received', $sql);
//if(mattOnlyTEST()) echo "[[[".print_r(fetchAssociations($sql), 1)."]]]<br>";			
		foreach(fetchAssociations($sql) as $req) {
			if($correspondentGroup) $correspondentName = null;
			$sender = join(' ',array($req['fname'], $req['lname']));
			if(!trim($sender)) $sender = $correspondentName;
			if(!$sender) { /* FOR groups */
				$requestorName = 
					$req['clientptr'] ? 
						fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$req['clientptr']} LIMIT 1", 1) : (
					$req['providerptr'] ? 
						fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$req['providerptr']} LIMIT 1", 1)
					: null);
				$senderType = $req['clientptr'] ? 'client' : ($req['providerptr'] ? 'provider' : '??');
				$correspondentName = $requestorName;
				$sender = $correspondentName;
				$senderId = $req['clientptr'] ? $req['clientptr'] : $req['providerptr'];
			}
			if($req['requesttype'] == 'Reminder') $subject = "Reminder: {$req['street1']}";
			else $subject = "Request: {$requestTypes[$req['requesttype']]}";
			$title = 'View this request.';
			if($clientflg && $req['providerptr']) {
				require_once "provider-fns.php";
				$pname = getProvider($req['providerptr']);
				$pname = providerShortName($pname);
				$title = "View this request. (Submitted by $pname)";
				$subject = "Request: {$requestTypes[$req['requesttype']]} (*)";
			}
			else if(!$clientflg) {
				require_once "client-fns.php";
				$client = getClient($req['clientptr']);
				$title = "View this request. (For client: {$client['fname']} {$client['lname']})";
				$subject = "Request: {$requestTypes[$req['requesttype']]} (*)";
			}
			$comms[] = array('date'=>shortDateAndTime(strtotime($req['received'])),
										'sortdate'=>$req['received'],
										'subject'=>viewRequestLink($subject, $req['requestid'], $title),
										'sortsubject'=>$subject,
										'sender' =>($correspondentName ? $correspondentName : $sender),
										'listid'=>count($comms),
										'requesttype'=>$req['requesttype'],
										'sendertype'=> $senderType,
										'senderid'=> $senderId,
										'item'=>array('table'=>'tblclientrequest', 'id'=>$req['requestid']));
			if($totalMsg) {
				$comms[count($comms)-1]['body'] = "https://{$_SERVER["HTTP_HOST"]}/request-edit.php?id={$req['requestid']}&updateList=";
				$comms[count($comms)-1]['type'] = "request";
			}
		}
	} // end request search
	
	
	// survey search
	require_once "survey-fns.php";
	$reviewSurveySubmissionsPermission = adequateRights('o-,#rs');
	if(surveysAreEnabled() && $reviewSurveySubmissionsPermission) {
		$submissions = findSubmissionsFrom($correspondent);
		foreach($submissions as $sub) $omitIds[] = $sub['submissionid'];
		$concerns = findSubmissionsConcerning($correspondent, $omitIds);
		foreach($concerns as $sub) 
			$submissions[] = $sub;
		if($submissions) {
			$surveyNames = getSurveyNamesById();
		}
		foreach($submissions as $sub) {
			$subject = "Survey submission: {$surveyNames[$sub['surveytemplateid']]}";
			$comms[] = array('date'=>shortDateAndTime(strtotime($sub['submitted'])),
										'sortdate'=>$sub['submitted'],
										'subject'=>viewSurveySubmissionLink($subject, $sub['submissionid'], 'View this survey submission'),
										'sortsubject'=>$subject,
										'sender' =>$correspondentName,
										'listid'=>count($comms),
										'requesttype'=>null, // ??
										'sendertype'=> $senderType,
										'senderid'=> $senderId,
										'item'=>array('table'=>'tblsurveysubmission', 'id'=>$sub['submissionid']));
		}
	}
	

	if(TRUE && !$correspondentGroup && $corresTable == 'tblclient') { // added 10/19/2020
	// TBD figure out how to allow $correspondentGroup serach for prospect emails
	// Search for Prospect requests BEFORE regular messages since $filter is modified before the regular search
	$prospectMsgs = fetchAssociations(
		"SELECT * 
		FROM tblmessage
		WHERE correstable = 'tblclientrequest'
		AND correspid IN
			(SELECT requestid FROM tblclientrequest 
				WHERE clientptr = $correspondentId
					AND requesttype = 'Prospect')
		AND $filter");
	}

	
	// create comm rows from tblmessage rows passing the filter
	if($correspondentGroup) $filter = $whereCondition.($filter ? "AND $filter" : '');
	else $filter = "((correspid = $correspondentId AND correstable = '$corresTable') 
									OR (originatorid = $correspondentId AND originatortable = '$corresTable'))".($filter ? "AND $filter" : '');
	if($inboundOnly) $filter .= " AND inbound = 1 ";
	if($excludeHidden) $filter .=" AND hidefromcorresp = 0";
//if(mattOnlyTEST()) {echo "SELECT * FROM tblmessage WHERE $filter";exit;}
	$messages = fetchAssociations($sql = "SELECT * FROM tblmessage WHERE $filter");
	
	if($prospectMsgs) foreach($prospectMsgs as $pm) $messages[] = $pm; // added 10/19/2020

	foreach($messages as $msg)
		if($msg['originatorid'] && $msg['originatortable'])
			$originators["{$msg['originatortable']}-{$msg['originatorid']}"] = 
					array('originatortable'=>$msg['originatortable'], 'originatorid'=>$msg['originatorid']);
	foreach((array)$originators as $k => $v)
		$originators[$k] = 
			$v['originatortable'] == 'tblclient'
			 ? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$v['originatorid']}")
			 : fetchRow0Col0("SELECT ifnull(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider WHERE providerid = {$v['originatorid']}");

	foreach($messages as $msg) {
		if($inboundOnly) {
			$correspondentName = $correspondentNames["{$msg['correstable']}{$msg['correspid']}"];
			if(!$correspondentName) {
				$correspondentNames["{$msg['correstable']}{$msg['correspid']}"] = fetchCorrespondent($msg);
				$correspondentName = $correspondentNames["{$msg['correstable']}{$msg['correspid']}"];
				$correspondentName = $correspondentName['name'];
			}
		}
		$senderType = $msg['correstable'] == 'tblclient' ? 'client' : ($msg['correstable'] == 'tblprovider' ? 'provider' : '??');
		$sender = $msg['inbound'] 
			? $correspondentName 
			: ($msg['originatorid'] ? $originators["{$msg['originatortable']}-{$msg['originatorid']}"] : $msg['mgrname']);
		$comms[] = array('date'=>shortDateAndTime(strtotime($msg['datetime'])),
									'sortdate'=>$msg['datetime'],
									'subject'=>viewMessageLink($msg['subject'], $msg['msgid']),
									'sortsubject'=>$msg['subject'],
									'sender' =>$sender,
									'senderid' =>$msg['correspid'],
									'listid'=>count($comms),
									'sendertype'=> $senderType,
									'item'=>array('table'=>'tblmessage', 'id'=>$msg['msgid']));
		if($totalMsg) {
			$comms[count($comms)-1]['body'] = $msg['body'];
			$comms[count($comms)-1]['type'] = "message";
		}
	}
	return $comms;
}

function commsTable($comms = array(), $sort) {
	$columns = explodePairsLine("date|Date||subject|Subject||sender|Sender");
	$columnSorts = array('date'=>'asc','subject'=>null,'sender'=>null);
	$sort_key = 'date';
	$sort_dir = '';
	if(isset($sort)) {
	  $sort_key = substr($sort, 0, strpos($sort, '_'));
	  $sort_dir = substr($sort, strpos($sort, '_')+1);
	}
	sortComms($comms, $sort_key, $sort_dir);
	$rowClasses = array();
	for($i=0;$i < count($comms);$i+=2) $rowClasses[$i] = 'futuretaskEVEN';
	tableFrom($columns, $comms, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses, null, 'sortMessages');
}

function inboundCommsTable($comms = array(), $sort) {
	$dog = '&#128021;';
	$paws = '&#128062;';
	$walker = '&#128694;';
	$profile = '&#128100;';
	$leftFinger = '&#9756;';
	$diamond = '&#9830;';
	$diamonds = '	&#10070;';
	$columns = explodePairsLine("date|Received||reminders|Reminders||sender|Sender||subject|Subject");
	$columnSorts = array('date'=>'asc','subject'=>null,'sender'=>null);
	$sort_key = 'date';
	$sort_dir = '';
	if(isset($sort)) {
	  $sort_key = substr($sort, 0, strpos($sort, '_'));
	  $sort_dir = substr($sort, strpos($sort, '_')+1);
	}
//print_r($comms);	
	sortComms($comms, $sort_key, $sort_dir);
	$clientSpan = "<span title='Client'>$paws</span>";
	$sitterSpan = "<span title='Sitter'>$profile</span>";
	foreach($comms as $i => $comm) {
		$time = strtotime($comm['date']);
//if(mattOnlyTEST() && !$comm['senderid']) {echo print_r($comm, 1).'<br>';}
		$typeIcon = $comm['sendertype'] == 'client' ? $clientSpan : $sitterSpan;
		$typeIcon = "<span class='fontSize1_2em'>$typeIcon</span>";
		$comms[$i]['sender'] = commTabLink($typeIcon.' '.$comms[$i]['sender'], $comm['sendertype'], $comm['senderid']);
		$comms[$i]['date'] = "<span class='titlehint' title='".date('D', $time).' '.shortDateAndTime($time)."'>".ago($comm['date'])."</span>";
		$reminder = "<span style='cursor:pointer;' title='Set a reminder' onclick='addReminder(\"{$comm['sendertype']}\", {$comm['senderid']})'>$diamond</span>";
		$remindersLink = remindersLink($diamonds, $comm['sendertype'], $comm['senderid']);
		$comms[$i]['reminders'] = "$reminder $remindersLink";
		$comms[$i]['reminders'] = "<span class='fontSize1_2em'>{$comms[$i]['reminders']}</span>";
		//$comms[$i]['senderType'] = 
		//	$comm['sendertype'] == ('client' ? $clientSpan : $sitterSpan)." $reminder";
		//$comms[$i]['subject'] = "<span class='titlehint' title='".commSample($comm['item'])."'>".$comm['subject']."</span>";
	}
	$rowClasses = array();
	for($i=0;$i < count($comms);$i+=2) $rowClasses[$i] = 'futuretaskEVEN';
	tableFrom($columns, $comms, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses, null, 'sortMessages');
}

function fetchCorrespondent($msg) {
	if($msg['correstable'] == 'tbluser') $corr = getUserByID($msg['correspid']);
	else {
		$tblid = $msg['correstable'] == 'tblclient' ? 'clientid' : 'providerid';
		$corr = fetchFirstAssoc("SELECT fname, lname FROM {$msg['correstable']} WHERE $tblid = {$msg['correspid']} LIMIT 1", 1);
	}
	$corr['name'] = "{$corr['fname']} {$corr['lname']}";
	return $corr;
}


function commTabLink($sender, $type, $id) {
	if($type == 'client') $title = "Go to this client's Communication tab";
	else if($type == 'provider') $title = "Go to this sitter's Communication tab";
	return fauxLink($sender, "goToCommTab(\"$type\", $id)", $noEcho=true, $title);
}

function remindersLink($label, $type, $id) {
	require_once "reminder-fns.php";
	$person = array("{$type}ptr"=>$id);
	$count = count(getReminders($person));
	$count = $count ? "$count" : "0";
	$title = "Go to this $type's Reminders page.  Currently has $count reminders set.";
//	else if($type == 'provider') $title = "Go to this sitter's Reminders page";
	return "<span style='cursor:pointer;' title=\"$title\" onclick='goToReminders(\"$type\", $id)'>$label ($count)</span>";
}

function commSample($item, $length=null) { // array('tblmessage'=>..., 'id'=>...)
	$length = $length ? $length : 100;
	if($item['table'] == 'tblmessage') {
		$str = fetchRow0Col0("SELECT SUBSTRING(body, 1, $length) FROM tblmessage WHERE msgid = {$item['id']} LIMIT 1", 1);
		return truncatedLabel($str, $length);
	}
}

function viewMessageLink($subject, $msgid) {
	$subject = trim($subject) ? trim($subject) : '<i>No subject</i>';
	return fauxLink($subject, "openConsoleWindow(\"msgviewer\", \"comm-view.php?id=$msgid\",600,500)", 1, 'View this message');
}

function viewSurveySubmissionLink($subject, $msgid, $title) {
	$url = 'survey-submission-view.php';
	return fauxLink($subject, "openConsoleWindow(\"viewsurveysubmission\", \"$url?id=$msgid\",610,500)", 1, $title);
}

function viewRequestLink($subject, $msgid, $title) {
	$url = userRole() == 'c' ? 'request-review.php' : 'request-edit.php';
	return fauxLink($subject, "openConsoleWindow(\"viewrequest\", \"$url?id=$msgid\",610,500)", 1, $title);
}

function viewPostcardLink($subject, $msgid, $title) {
	return fauxLink($subject, "document.location.href=\"postcard-viewer.php?$msgid\"", 1, $title);
}

function sortComms(&$comms, $sort, $dir) {
	if($sort == 'date') $fn = 'cmpCommDates'.($dir == 'desc' ? 'R' : '');
	else if($sort == 'subject') $fn = 'cmpCommSubjects'.($dir == 'desc' ? 'R' : '');
	else if($sort == 'sender') $fn = 'cmpCommSenders'.($dir == 'desc' ? 'R' : '');
	usort($comms, $fn);
}

function cmpCommDatesR($a, $b) {return 0 - cmpCommDates($a, $b);}
function cmpCommDates($a, $b) {
	return $a['sortdate'] < $b['sortdate'] ? -1 :
					($a['sortdate'] > $b['sortdate'] ? 1 : 0);
}

function cmpCommSubjectsR($a, $b) {return 0 - cmpCommSubjects($a, $b);}
function cmpCommSubjects($a, $b) {
	return $a['sortsubject'] < $b['sortsubject'] ? -1 :
					($a['sortsubject'] > $b['sortsubject'] ? 1 : 0);
}

function cmpCommSendersR($a, $b) {return 0 - cmpCommSenders($a, $b);}
function cmpCommSenders($a, $b) {
	return $a['sender'] < $b['sender'] ? -1 :
					($a['sender'] > $b['sender'] ? 1 : cmpCommDates($a, $b));
}

function getSigBizLogoImage() {
	if($_SESSION && isset($_SESSION["bizfiledirectory"]))
		$headerBizLogo = $_SESSION["bizfiledirectory"];
	else $headerBizLogo = "bizfiles/biz_{$_SESSION['bizptr']}/";
	if($headerBizLogo) {
		$headerBizLogo = getHeaderBizLogo($headerBizLogo);
		if($headerBizLogo) {
			$imgSrc = globalURL($headerBizLogo);
			$headerBizLogo = "<img src='$imgSrc'>";
		}
	}
	return $headerBizLogo;
}

function processRawSig($sig, $nobrs=false) {
	$headerBizLogo = strpos($sig, '#LOGO#') !== FALSE ? getSigBizLogoImage() : '';

	$preferences = $_SESSION['preferences'];
	
	if($preferences['bizAddressJSON']) // NEW bizAddress handling
		foreach(json_decode($preferences['bizAddressJSON']) as $k => $v)
			$addressParts["#".strtoupper($k)."#"] = $v;
	else {
		$frags = explode(' | ', $preferences['bizAddress']);
		foreach(array('#STREET1#','#STREET2#','#CITY#','#STATE#','#ZIP#') as $i => $token) 
			$addressParts[$token] = $frags[$i];
	}

	$sig = mailMerge($sig, 
					array(
								'#LOGO#' => $headerBizLogo,
								'#PHONE#' => $preferences['bizPhone'],
								'#FAX#' => $preferences['bizFax'],
								'#EMAIL#' => $preferences['bizEmail'],
								'#HOMEPAGE#' => $preferences['bizHomePage'],
								'#BIZNAME#' => $preferences['bizName'],
								'#STREET1#' => $addressParts['#STREET1#'],
								'#STREET2#' => $addressParts['#STREET2#'],
								'#CITY#' => $addressParts['#CITY#'],
								'#STATE#' => $addressParts['#STATE#'],
								'#ZIP#' => $addressParts['#ZIP#'],
								'#NAME#'=>$_SESSION["auth_username"]
								));
	return $nobrs ? $sig :  str_replace("\n", '<br>', str_replace("\n\n", '<p>', str_replace("\r", '', $sig)));
}

function getProcessedSig($nobrs=false) {
	return $_SESSION['preferences']['composerSignature'] ? processRawSig($_SESSION['preferences']['composerSignature'], $nobrs) : '';
}

// OUTBOUND EMAIL GOVERNOR CODE
/*
Rules: Allow a set of rules to be implemented for a client business. 

    1. Each rule will be a "stop if..." rule. 
    2. The rule set will be tested at the start of LeashTime's sendEmail function, so that 
    	it will count all emails sent by the business, and not just those sent by the cron job 
    	or by the logged in manager in the current session (if any).
    3. The first rule that returns true will abort rule testing and prevent that email from being sent.  
    	It will also set a global (script-level) variable $EMAILSTOPPED to the current value of time().  
    	It will also log the failure and include the rule which fired.
    4. The function sendQueuedEmail, which sends an individual email from the queue and removes 
    	the message from the queue upon success, will return an error message without trying to send 
    	if $EMAILSTOPPED is not null
    	
"Save your breath" or "No doorpounding" measure:

    When sending an email fails because of a transport error, set a global (script scope) array association:
    $EMAIL_TRANSPORT_ERRORS[$db] = $e; // to help eliminate "door pounding" (in email-swiftmailer-fns.php catch (Swift_TransportException $e))
    In LeashTime's sendQueuedEmail function, if($EMAIL_TRANSPORT_ERRORS[$db]) then it will return an error message without trying to send if $EMAIL_TRANSPORT_ERRORS[$db] is not null
    	
Rule format:
	One rule per line.
	Threshold rule:
	limit|limitunit|timeinterval|timeintervalunit
	e.g.,
0|delay|3|second
20|address|1|minute
100|address|1|hour
400|address|1|day

	timeintervalunits would be second, minute, hour, day, calendarday
	limitunit would be address or message
*/

function stopIf($rules) { // return the rule, if any, that is to prevent email from being sent
	global $EMAILSTOPPED;
	if(!$rules) return false;
	// convert each rule into array('count'=>x, 'seconds'=>window)
	if(is_string($rules)) $rules = interpretRulesString($rules);
//echo print_r($rules,1).'<hr>';							
	foreach($rules as $rule) {
		if($rule['limitunits'] == 'delay') {
			sleep($rule['windowseconds']);
			continue;
		}
		$WHAT = $rule['limitunits'] == 'message'
				? "count(*)"
				: "correspaddr";
		$WHEN = $rule['windowseconds'] 
				? "datetime >= (NOW() - INTERVAL {$rule['windowseconds']} SECOND)"
				: "UNIX_TIMESTAMP(`datetime`) >= {$rule['onorafter']}";
		$sql = "SELECT $WHAT
						FROM tblmessage 
						WHERE (inbound = 0 OR inbound IS NULL)
							AND transcribed IS NULL
							AND $WHEN";
//echo fetchRow0Col0("SELECT NOW() - INTERVAL {$rule['windowseconds']} SECOND")."<br>$sql<br><pre>".print_r(fetchCol0($sql), 1)."</pre><hr>";							
		if($rule['limitunits'] == 'address')
			foreach(fetchCol0($sql) as $correspaddr)
				$targets += countAddresses($correspaddr);
		else if($rule['limitunits'] == 'message') 
			$targets = fetchRow0Col0($sql, 1);
		if($targets >= $rule['limit']) {
			$EMAILSTOPPED = time();
			return $rule;
		}
	}
}
	
												
function countAddresses($correspaddr) {
	// WARNING: THIS DOES NOT CURRENTLY COUNT THE possible altEmail CC
	static $lastDb, $emailBCC;
	if($_SESSION['preferences'] && $_SESSION['preferences']['emailBCC'])
		$emailBCC = $_SESSION['preferences']['emailBCC'];
	else if(!$_SESSION['preferences']) {
		global $db;
		if($db != $lastDb) {
			$emailBCC = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'emailBCC' LIMIT 1", 1);
			$lastDb = $db;
		}
	}
	foreach(explode('|', $correspaddr) as $header) // To:, BCC: (sitters)
		$count += count(explode(',', $header));
	if($emailBCC) $count += 1;
	return $count;
}
				
function interpretRulesString($str) {
	// 20|address|1|minute
	// 20|message|1|minute

	if(!$str) return false;
	if(!is_string($str)) return "Rules must be in string form.";;
	if(!trim($str)) return false;
	$rules = explode("\n", trim($str));
	$seconds = array('second'=>1, 'minute'=>60, 'hour'=>3600, 'day'=>24*3600);
	foreach($rules as $i=>$rule) {
		$rule = explode('|', trim($rule));
		$arr['limit'] = $rule[0];
		$arr['limitunits'] = $rule[1];
		if($rule[3] == 'calendarday') 
			$arr['onorafter'] = "'".date('Y-m-d')."' - ".($rule[2]-1)." DAYS";
		else $arr['windowseconds'] = $rule[2]*$seconds[$rule[3]];
		$arr['desc'] = $rules[$i];
		$rules[$i] = $arr;
	}
	return $rules;
}
