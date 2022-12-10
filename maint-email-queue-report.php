<? // maint-email-queue-report.php

// 3/13/2021 - updated GMail-specific message.

require_once "common/init_session.php";
require_once "common/init_db_common.php";

$locked = locked('z-');
$bizid = $_GET['bizId'];

if(!$bizid) {echo "No biz supplied."; exit; }
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid' LIMIT 1");
if(!$biz) {echo "Biz [$bizid] not supplied."; exit; }
$mgr = fetchFirstAssoc(
	"SELECT * 
		FROM tbluser 
		WHERE bizptr = $bizid AND rights LIKE 'o-%' AND active = 1 AND ltstaffuserid != 1 
		ORDER BY isowner DESC, userid");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);

function getEmailQueueInfo($skipIfEmpty=false) {
	global $queuelength;
	if(!($queuelength = fetchRow0Col0("SELECT count(*) FROM tblqueuedemail")) && $skipIfEmpty) return;
	$summary = array(
		'queuelength'=>$queuelength,
		'queuestarted'=>fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mailQueueSendStarted' LIMIT 1"),
		'queuedisabled'=>fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mailQueueDisabled' LIMIT 1"),
		'oldestqueuedmessagedate'=>fetchRow0Col0("SELECT addedtime FROM tblqueuedemail ORDER BY addedtime LIMIT 1"),
		'lastmessagesent'=>fetchFirstAssoc("SELECT * FROM tblmessage WHERE inbound = 0 AND transcribed IS NULL ORDER BY datetime DESC LIMIT 1"));
	foreach(explode(',', 'emailFromAddress,emailHost,smtpPort,smtpSecureConnection,emailUser,emailPassword') as $setting) $summary[$setting] = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = '$setting' LIMIT 1");
	return $summary;
}

function emailQueueAnalysis($skipIfEmpty=true) {
	global $authenticationError, $serverUnresponsiveError, $lastSentTime, $stats;
	$stats = getEmailQueueInfo(true);
	if($skipIfEmpty)
	if(!$stats) {
		if($skipIfEmpty) return null;
		else return "Queue is empty";
	}
	setLocalTimeZone($TZ = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeZone' LIMIT 1")) ;
	$report[] = "Local time is ".date('H:i - m/d/Y')." ($TZ)";
	if($stats['queuedisabled']) $report[] = "QUEUE DISABLED SINCE: ".shortDateAndTime(strtotime(substr($stats['queuedisabled'], 0, 19)));
	$report[] = ($stats['queuelength'] ? $stats['queuelength'] : 'No').' messages in queue';
	if($stats['queuelength']) $report[] = "the oldest was added ".shortDateAndTime(strtotime($stats['oldestqueuedmessagedate']));
	if($queuestarted = $stats['queuestarted']) {
		
		$inProgressMinutes = (time() - strtotime($queuestarted)) / 60;
		$report[] = "Queue in progress since {$stats['queuestarted']} (".number_format($inProgressMinutes)." minutes)";
		$sentSinceStart = fetchRow0Col0("SELECT COUNT(*) FROM tblmessage WHERE inbound = 0 AND transcribed IS NULL AND datetime >= '{$stats['queuestarted']}'");
		$report[] = ($sentSinceStart ? $sentSinceStart : 'No').' messages sent since then.';
	}
	if($stats['lastmessagesent']) {
		$lastSentTime = strtotime($stats['lastmessagesent']['datetime']);
		$report[] = "The last message send date is ".shortDateAndTime($lastSentTime);
	}
	else $report[] = "No emails have ever been sent";
	$isset= $stats['emailPassword'] ? 'yes' : 'no';
	$report[] = "Settings: Sender: ({$stats['emailFromAddress']}) Host: (<font color=blue>{$stats['emailHost']}</font>) Port: ({$stats['smtpPort']}) Secure: ({$stats['smtpSecureConnection']}), Login: ({$stats['emailUser']}) Password set: ($isset)";
	if($inProgressMinutes > 10 && $lastSentTime && (time() - $lastSentTime)/60 > 10) {
		$report[] = "The queue is probably STALLED";
		$stalled = true;
	}
	else {
		$since = date('Y-m-d H:i:s', strtotime("-10 MINUTES"));
		$sql = "SELECT time, message from tblerrorlog WHERE `time` > '$since' AND message LIKE '%mail%' ORDER BY `time` DESC";
		$recentErrors = fetchAssociations($sql);
		foreach($recentErrors as $error) {
		
			if(strpos(strtolower($error['message']), 'authentic') !== FALSE) {
				$hints['authentication'] = 1;
				$authenticationError =  $error['message'];
			}
			if(strpos($error['message'], 'Expected response code 250 but got code ""') !== FALSE) {
				$hints['unresponsive'] = 1;
				$serverUnresponsiveError =  $error['message'];
			}
			if(strpos($error['message'], 'quota') !== FALSE) $hints['quota'] = 1;
			if(strpos($error['message'], 'Bad Address') !== FALSE) $hints['Bad Address'] = 1;
		}
		if($hints) $report[] = "Error hints: ".join(', ', array_keys($hints));
		if($recentErrors) $report[] = "Last Errors:"
			."<br>[{$recentErrors[1]['time']}] <font color=red>{$recentErrors[1]['message']}</font>"
			."<br>[{$recentErrors[0]['time']}] <font color=red>{$recentErrors[0]['message']}</font>"
			."</font>";
	}
	return array('report'=>$report, 'stalled'=>$stalled);
}
$analysis = emailQueueAnalysis();
if(!$analysis['stalled']) unset($analysis['stalled']);
$bizzy = "($bizid) <span style='font-size:2em;'>{$biz['bizname']}</span> ($db)";
echo "Email queue analysis for $bizzy:<p>";
if($analysis) echo join('<br>', $analysis['report']);
else echo "Nothing to report.";
// show officenotes

reconnectPetBizDB('leashtimecustomers', $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
$officeNotes = fetchRow0Col0("SELECT officeNotes FROM tblclient WHERE garagegatecode = $bizid LIMIT 1", 1);
echo str_replace("\n", "<br>", str_replace("\n\n", "<p>", "<hr>OFFICE NOTES:\n$officeNotes"));
$dayAndTime = longestDayAndDateAndTime($lastSentTime);
if($authenticationError) {
	$gmailNote = strpos(strtolower($stats['emailHost']), 'gmail') === false ? ''
	: "<p>If the test fails, you may need to log in to GMail in another window and follow these steps:"
		."<p>1. Click on the gearwheel (right side) and choose Settings."
		."<br>2. Click the \"See all settings\" button near the top."
		."<br>3. Click the \"Accounts and Import\" or \"Accounts\" tab link."
		."<br>4. In \"Change account Settings\" group, click \"Other Google Account settings\"."
		."<br>5. Click \"Security\" in the left sidebar."
		."<br>6. In the \"Signing in to Google\" section, turn OFF 2-Step Verification."
		."<br>7. In \"Less secure app access\" section, turn the setting ON."	
		."<p>After that, use \"Test Outgoing Email\" link in LeashTime to see if your email is working again.";
	
	
	
	if(is_array($authenticationError)) $authenticationError = print_r($authenticationError, 1);
	else $authenticationError = substr($authenticationError, trim(strrpos($authenticationError, "connect:")+strlen("connect:")));
	// construct message
	$msg = <<<MSG
Your LeashTime email is stuck - authentication

Hi {$mgr['fname']},

It looks like your LeashTime email is not going out.  $queuelength messages are currently queued up waiting to be sent.

The reason for this is an error that first appeared some time after $dayAndTime:

$authenticationError

This usually happens when you change the password on your email account but forget to update the password in LeashTime.  If you think this may have happened, please:

1. Go to ADMIN > Preferences
2. Open the Outgoing Email section
3. Update your email password (this should be the password you supply when you login to read your email).
4. Click the Test Outgoing Email link and follow the instructions.$gmailNote

If this is not what happened, or if your email still will not go out, please reply to this email so that we can help you get it working again.  If you reply, please do NOT tell us (or anyone) your email password.

Thanks,

matt
LeashTime
MSG;
	echo "<hr><span style='font-size:0.8em;'>".str_replace("\n", "<br>", str_replace("\n\n", "<p>", $msg))."<span>";
}

else if($serverUnresponsiveError) {
	$secureserverNote = strpos(strtolower($stats['emailHost']), 'secureserver') === false ? ''
	: <<<SECURE
	
Sometimes GoDaddy&apos;s email server (at secureserver.net) reports this error when the problem is really that you changed the password on your email account but forget to update the password in LeashTime.  If you think this may have happened, please:

1. Go to ADMIN > Preferences
2. Open the Outgoing Email section
3. Update your email password (this should be the password you supply when you login to read your email).
4. Click the Test Outgoing Email link and follow the instructions.$gmailNote

If this is not what happened, or if your email still will not go out, please reply to this email so that we can help you get it working again.  If you reply, please do NOT tell us (or anyone) your email password.
SECURE;

	$msg = <<<MSG
Your LeashTime email is stuck - mail server unresponsive

Hi {$mgr['fname']},

It looks like your LeashTime email is not going out.  $queuelength messages are currently queued up waiting to be sent.

The reason for this is an error that first appeared some time after $dayAndTime:

$serverUnresponsiveError
$secureserverNote

Please contact us at support@leashtime.com if you have questions.

Thanks,

matt
LeashTime
MSG;
	echo "<hr><span style='font-size:0.8em;'>".str_replace("\n", "<br>", str_replace("\n\n", "<p>", $msg))."<span>";
}