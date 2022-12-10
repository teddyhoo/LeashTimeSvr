<? // manager-alert-fns.php

function emailProblemReportWidget($noEcho=0) {
	if(!$report = emailProblemReport()) return;
	return fauxLink("ALERT...", 
		"$.fn.colorbox({html:\"$report\", width:\"600\", height:\"500\", scrolling: true, opacity: \"0.3\"});"
		, $noEcho, $title='Click to see important warnings.', $id=null, 
			$class='fauxlink fontSize1_4em warning boldfont', $style=null);
}

function emailProblemReport() {
	$problems = emailProblems();
	if(!$problems['error'] && !$problems['disabled']) {
		return null;
	}
	if($problems['disabled']) {
		$disabletime = strtotime($problems['disabled']);
		$disabletime = longDayAndDate($disabletime).' at '.date('h:i a', $disabletime);
		if(!$problems['disabledbecause']) $because = " because the email settings were causing repeated failures";
		else $because = " because {$problems['disabledbecause']}";
		$status[] = "Your email queue was disabled by LeashTime staff on $disabletime $because.";
	}
	if($ql = $problems['queuelength']) {
		$firstqueueitem = strtotime($problems['oldestqueue']);
		$firstqueueitem = longDayAndDate($firstqueueitem).' at '.date('h:i a', $firstqueueitem);
		$status[] = "$ql message".($ql > 1 ? "s are" : " is")." have been waiting to be sent since $firstqueueitem.";
	}
	
	if($hintcodes = $problems['errorhints']) {
		require_once "gui-fns.php";
		$explanations = array(
			"authentication"=>"The supplied username or password may be wrong.",
			"gmail"=>"...or your Gmail account settings may need to be changed.",
			"unresponsive"=>"The SMTP server is not responding.",
			"quota"=>"You have exceeded your SMTP server's quota for sent messages.",
			"badaddress"=>"A message in you outbound queue has an invalid recipient address."
			);
		$hints = array();
		foreach($hintcodes as $hint) $hints[] = $explanations[$hint];
		$hints = "<ul><li>".join('<li>', $hints)."</ul>";
		$status[] = "The error messages received suggest $hints";
	}

	if($settings = $problems['settings']) {
		if($settings['host']) {
			if(!($settings['sender'] && $settings['port'] && $settings['security'] && $settings['login'] && $settings['passwordset']))
				$status[] = "Your Outgoing email settings look wrong.  If you are using your own SMTP server, you need to supply valid entries for:<ul>"
					."<li>Sender Email Address<li>SMTP Host<li>SMTP Port<li>Email User Name<li>Email Password</ul>";
		}
		else {
			if(($settings['sender'] || $settings['port'] || $settings['security'] || $settings['login'] || $settings['passwordset']))
				$status[] = "Your Outgoing email settings look wrong.  If you are using your LeashTime's SMTP server, you need to leave the following entries BLANK:<ul>"
					."<li>Sender Email Address<li>SMTP Host<li>SMTP Port<li>Email User Name<li>Email Password</ul>";
		}
	}
	$status[] = "Please contact support@leashtime.com to help you get your LeashTime email working again.";
	if(mattOnlyTEST()) {
		$status[] = "<hr>".str_replace("\n", "<br>", str_replace("\n\n", "<p>", addslashes(print_r($problems, 1))));
	}
	return "<div class=\\\"fontSize1_2em\\\"><p class=\\\"fontSize1_2em warning\\\">LeashTime Cannot Send Your Email<p>"
		.str_replace("'", "&apos;", join('<p>', $status))."</div>";
}

function emailProblems() {
	global $authenticationError, $lastSentTime, $stats, $db;
	// this function errors if the db has no emailqueue, so check to make sure we are not in the "petcentral" db
	if($db == 'petcentral') return;
	$stats = getEmailQueueSummary(true);
	if($skipIfEmpty)
	if(!$stats) {
		if($skipIfEmpty) return null;
		else return null;
	}
	//setLocalTimeZone($TZ = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeZone' LIMIT 1")) ;
	//$report[] = "Local time is ".date('H:i - m/d/Y')." ($TZ)";
	if($stats['queuedisabled']) $report['disabled'] = shortDateAndTime(strtotime(substr($stats['queuedisabled'], 0, 19)));
	$report['queuelength'] = $stats['queuelength'] ? $stats['queuelength'] : 0;
	if($stats['queuelength']) $report['oldestqueue'] = shortDateAndTime(strtotime($stats['oldestqueuedmessagedate']));
	if($queuestarted = $stats['queuestarted']) {
		$inProgressMinutes = (time() - strtotime($queuestarted)) / 60;
		$report['inprogress'] = "Queue in progress since {$stats['queuestarted']} (".number_format($inProgressMinutes)." minutes)";
		$sentSinceStart = fetchRow0Col0("SELECT COUNT(*) FROM tblmessage WHERE inbound = 0 AND transcribed IS NULL AND datetime >= '{$stats['queuestarted']}'");
		$report['messagesSent'] = $sentSinceStart ? $sentSinceStart : '0';
	}
	//if($stats['lastmessagesent']) {
	//	$lastSentTime = strtotime($stats['lastmessagesent']['datetime']);
	//	$report[] = "The last message send date is ".shortDateAndTime($lastSentTime);
	//}
	//else $report[] = "No emails have ever been sent";
	$isset= $stats['emailPassword'] ? 1 : 0;
	$report['settings'] = array(
		'sender'=>$stats['emailFromAddress'],
		'host'=>$stats['emailHost'],
		'port' =>$stats['smtpPort'],
		'security'=>$stats['smtpSecureConnection'],
		'login'=>$stats['emailUser'],
		'passwordset'=>$isset);
	if($inProgressMinutes > 10 && $lastSentTime && (time() - $lastSentTime)/60 > 10) {
		$report['stalled'] = true;
		$report['error'] = true;
	}
	else {
		$since = date('Y-m-d H:i:s', strtotime("-10 MINUTES"));
		$sql = "SELECT time, message from tblerrorlog WHERE `time` > '$since' AND message LIKE '%mail%' ORDER BY `time` DESC";
		$recentErrors = fetchAssociations($sql);
		foreach($recentErrors as $error) {
			if(strpos($error['message'], 'authentic') !== FALSE) {
				$hints['authentication'] = 1;
				if(strpos(strtolower($stats['emailHost']), 'gmail') !== FALSE)
					$hints['gmail'] = 1;
				$authenticationError =  $error['message'];
			}
			if(strpos($error['message'], 'Expected response code 250 but got code ""') !== FALSE) $hints['unresponsive'] = 1;
			if(strpos($error['message'], 'quota') !== FALSE) $hints['quota'] = 1;
			if(strpos($error['message'], 'Bad Address') !== FALSE) $hints['badaddress'] = 1;
		}
		if($hints) $report['errorhints'] = array_keys($hints);
		if($recentErrors) {
			$report['error'] = true;
			foreach($recentErrors as $err) {
				$report['recenterrors'][] = "[{$err['time']}] {$err['message']}";
			}
		}
	}
	return $report;
}

function getEmailQueueSummary($skipIfEmpty=false) {
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
