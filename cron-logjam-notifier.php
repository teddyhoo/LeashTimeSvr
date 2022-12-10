#!/usr/bin/php
<?// cron-logjam-notifier.php
// use crontab to schedule the job
set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
require_once "common/init_db_common.php";
require_once "comm-fns.php";
ensureInstallationSettingsWithThisDirectory('/var/www/prod');


// exit;


// cronjob only
//if($_SESSION) exit;
//echo date('n/j H:i')." Starting...\n";

$databases = fetchCol0("SHOW DATABASES");


foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysql_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		echo "Not able to connect: invalid database username and/or password.\n";
	}
	$lnk1 = mysql_select_db($db);
	if(mysql_error()) echo mysql_error();
	$tables = fetchCol0("SHOW TABLES");
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	
	// #########################################################################################################
	$mailQueueSendStarted = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'mailQueueSendStarted' LIMIT 1");
	if($mailQueueSendStarted) {
		$start = strtotime($mailQueueSendStarted);
		$timeSince = time() - $start;
		if($timeSince > 60 * 60 &&  $timeSince < (60 + 25) * 60) {
			$queueLength = fetchRow0Col0("SELECT count(*) FROM tblqueuedemail");
			$logjams[] = array($bizName, $db, $mailQueueSendStarted, $queueLength);
		}
	}
}

if($logjams) {
	foreach($logjams as $jam) $message .= "\n{$jam[0]} ({$jam[1]}) since {$jam[2]}.  {$jam[3]} messages.";
	$recipients = array('thule@aol.com', 'ted@leashtime.com', 'matt@leashtime.com');
	$error = sendEmail($recipients, "Email Logjam Alert", $message, $cc=null, $html=null, $senderLabel='', $bcc=null, $extraHeaders=null);
	if($error) echo $error;
}
		
