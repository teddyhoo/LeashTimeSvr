<? //cron-fns.php
// 0,30 * * * * /var/www/prod/cron-daily-tasks.php >> /var/www/prod/cronout/dailycron.out

$dailyTasks = array(
		'send-master-schedule'=>'00:00',
		'check-holidays'=>'01:00',
		'billing-reminders'=>'01:10',
		'email-leashtime-mergelist'=>'01:30',
		'incomplete-schedules-notification'=>'02:30',
		// 'recurring-schedule-rollover'=>'02:00', // converted back to its own cron task 11/19/2015
		'send-provider-schedules'=>'04:00',
		'archive-messages'=>    '22:25',
		'prepayment-overdue-email'=>'05:30',
		'generate-billables'=>'06:30',
		//'cron-send-client-schedules'=>'06:00'
		);
		
		

/*
	CREATE TABLE IF NOT EXISTS `tblcrons` (
	  `bizptr` int(11) NOT NULL,
	  `task` varchar(100) NOT NULL,
	  `lastrun` date NOT NULL,
	  PRIMARY KEY  (`task`,`bizptr`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1;

	# (Use to post in the top of your crontab)
	# ------------- minute (0 - 59)
	# | ----------- hour (0 - 23)
	# | | --------- day of month (1 - 31)
	# | | | ------- month (1 - 12)
	# | | | | ----- day of week (0 - 6) (Sunday=0)
	# | | | | |
	# * * * * * command to be executed
	* * * * * /var/www/gsk/email-cron-test.php >> /home/jweirger/cron-out.txt

	[root@525552-web2 cronout]# crontab -l
	0,5,10,15,20,25,30,35,40,45,50,55 * * * * /var/www/prod/cron-queued-msgs-email.php >> /var/www/prod/cronout/mailcron.out
	# SUPERSEDED 3,8,13,18,23,28,33,38,43,48,53,58 * * * * /var/www/prod/cron-queued-msgs-email-zoho.php >> /var/www/prod/cronout/mailcron.out
	0 2 * * * /var/www/prod/cron-recurring-schedule-rollover.php >> /var/www/prod/cronout/rollovercron.out
	# SUPERSEDED 30 6 * * * /var/www/prod/cron-generate-billables.php >> /var/www/prod/cronout/generatebillablescron.out
	9,19,29,39,49,59 * * * * /var/www/prod/cron-confirmation-overdue-email.php >> /var/www/prod/cronout/mailcron.out
	# SUPERSEDED 0 1 * * * /var/www/prod/cron-check-holidays.php >> /var/www/prod/cronout/checkholidays.out
	# SUPERSEDED 10 1 * * * /var/www/prod/cron-generate-billing-reminders.php >> /var/www/prod/cronout/billingremiders.out
	# SUPERSEDED 0 4 * * * /var/www/prod/cron-send-provider-schedules.php >> /var/www/prod/cronout/sendschedcron.out
	# SUPERSEDED 30 5  * * * /var/www/prod/cron-prepayment-overdue-email.php >> /var/www/prod/cronout/mailcron.out
	# SUPERSEDED 0 6 * * * /var/www/prod/cron-send-client-schedules.php
	# SUPERSEDED 5 6 * * * /var/www/prod/cron-prepayment-overdue-email.php >> /var/www/prod/cronout/mailcron.out
	* * * * * /var/www/prod/cron-test.php >> /var/www/prod/cronout/test.out
	0,15,30,45 * * * * /var/www/prod/cron-logjam-notifier.php >> /var/www/prod/cronout/logjam.out
	0,30 * * * * /var/www/prod/cron-daily-tasks.php >> /var/www/prod/cronout/dailycron.out
	2,7,12,17,22,27,32,37,42,47,52,57 * * * * /var/www/html/hlptick/inc/mail/hesk_pop3.php
	3,8,13,18,23,28,33,38,43,48,53,58 * * * * /var/www/prod/cron-notify-stale-overdue-visits.php >> /var/www/prod/cronout/stale.out
*/
function timeToRun($bizptr, $jobKey, $jobTime) {
	global $dbhost, $db, $dbuser, $dbpass;
	if(strcmp($jobTime, date('H:i')) > 0) return false;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$lastRun = 
			fetchRow0Col0("SELECT lastrun FROM tblcrons WHERE bizptr = $bizptr AND task = '$jobKey'");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	return !($lastRun && strcmp($lastRun, date('Y-m-d')) >= 0);
}

function markTaskComplete($bizptr, $jobKey) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	replaceTable('tblcrons', array('bizptr'=>$bizptr, 'task'=>$jobKey, 'lastrun'=>date('Y-m-d')));
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
}

function runCrons() {
	// This is for DAILY jobs.  See $dailyTasks above
	//return; // DO NOT RUN CRON JOBS
	
	global $dailyTasks, $db, $bizptr, $tables, $NO_SESSION, $biz;
	set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
	require_once "common/init_db_common-2.php";
	$delayed = true;

	$databases = fetchCol0("SHOW DATABASES");
	
	//$TEST = "AND country IN ('AU', 'UK')";
	$TEST = "AND db NOT IN ('DoggiewalkerTest')";
	echo "\n======================================\nDAILY CRON RUN: ".date('Y-m-d H:i')." Eastern Time\n";
	foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1 $TEST") as $biz) {
		if(!in_array($biz['db'], $databases)) {
			echo "DB: {$biz['db']} not found.\n";
			continue;
		}
		if(!$biz['activebiz']) {
			echo "Inactive biz: {$biz['bizname']} ({$biz['db']}) skipped.\n";
			continue;
		}
		$lockedOut = $biz['lockout'] && strcmp($biz['lockout'], date('Y-m-d')) < 1;
		$bizptr = $biz['bizid'];
		setLocalTimeZone($biz['timeZone']);
		$NO_SESSION['i18n'] = getI18NProperties($biz['country']);		
		
		/*foreach($dailyTasks as $jobName => $jobTime) {
			if(!timeToRun($bizptr, $jobName, $jobTime)) continue;
			echo "JOB Run [{$biz['db']}] $jobName Local time: ".date('Y-m-d H:i')." ({$biz['timeZone']})\n";
			if($db != $biz['db']) {
				reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
				if(mysql_error()) echo mysql_error();
				$tables = fetchCol0("SHOW TABLES");
			}
			if($jobName == 'check-holidays') checkHolidays();
			else if($jobName == 'generate-billables') cronGenerateBillables();
			else if($jobName == 'prepayment-overdue-email') cronPrepaymentOverdueEmail();
			else if($jobName == 'recurring-schedule-rollover') cronRecurringScheduleRollover();
			else if($jobName == 'send-provider-schedules') cronSendProviderSchedules();
			else if($jobName == 'billing-reminders') cronGenerateBillingReminders();
			else if(!$lockedOut && $jobName == 'send-master-schedule') cronSendMasterSchedule();
			else if($jobName == 'archive-messages') archiveCronTask();
			else if($jobName == 'email-leashtime-mergelist') emailLeashTimeMergeListCron();
			else if($jobName == 'incomplete-schedules-notification') incompleteSchedulesCron();
			markTaskComplete($bizptr, $jobName);
			echo "JOB Complete [{$biz['db']}] $jobName Local time: ".date('Y-m-d H:i')." ({$biz['timeZone']})\n";
		}*/
	}
	
}
function incompleteSchedulesCron() {
	// if incompleteScheduleNotificationInterval, then the job should NOT be run daily
	require_once "preference-fns.php";
	if(fetchPreference('enableIncompleteScheduleNotifications')|| fetchPreference('clientUIVersion')) {
		require_once "client-sched-request-fns.php";
		generateIncompleteScheduleRequestSystemNotification(); // tests incompleteScheduleNotificationInterval
	}
}
function archiveCronTask() {
	global $scriptPrefs, $db;
	$scriptPrefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
	if(!$scriptPrefs['enableMessageArchiveCron']) return;
	require_once "archive-fns.php";
	$days = $scriptPrefs['preferences']['archiveMessageDaysOld'] 
					? $scriptPrefs['preferences']['archiveMessageDaysOld'] 
					: 450;
	$days = "-$days days";
	echo "$db ";
	$t0 = microtime(1);
	$results = archiveMessagesBefore($days, $maxCount=null, "DELETE ORIGINALS");
	echo "messages archived: {$results['added']}"
				.($results['errors'] ? "ERRORS: ".$results['errors'] : '')
			." last datetime: {$results['lastdate']} Backup time: ".(microtime(1)-$t0)." secs\n";
}
function emailLeashTimeMergeListCron() { // not used
	global $db;
	if($db != 'leashtimecustomers') return;
	require_once "mailchimp-fns.php";
	emailLeashTimeMergeList();
}
function cronGenerateBillingReminders() {
	global $tables, $delayed, $histories;
	require_once "provider-fns.php";
	require_once "client-fns.php";
	require_once "request-fns.php";
	require_once "gui-fns.php";
	require_once "confirmation-fns.php";
	require_once "billing-reminder-fns.php";
	require_once "service-fns.php";
	$delayed = true;
	$histories = array();
	$n= generateBillingReminderRequests();
	$n = $n ? $n : 'No';
	echo "$db: $n Billing Reminders Created.<br>";
}
function cronSendProviderSchedules() {
	global $tables, $delayed;
	require_once "prov-notification-fns.php";
	require_once "preference-fns.php";
	$mein_host = "https://Leashtime.com";
	$this_dir = "";
	$delayed = true;
	ob_start(); // consume output
	ob_implicit_flush(0);
	sendWeeklyOrDailyProviderSchedules(date('Y-m-d'), $delayed);
	$output = ob_get_contents();
	// count schedules
	$numSent = substr_count ($output, "START: 	sendProviderSchedule");
	ob_end_clean();
	echo "Sent $numSent schedules\n";
}
function cronRecurringScheduleRollover() {
	global $tables, $CRON_DiscountsAreEnabled;
	require_once "preference-fns.php";
	require_once 'service-fns.php';
	set_time_limit(15 * 60);
	$CRON_DiscountsAreEnabled = in_array('tbldiscount', $tables);
	rolloverRecurringSchedules();
}
function cronPrepaymentOverdueEmail() {
	require_once 'comm-fns.php';
	sendOverduePrepaymentEmails();
}
function cronSendMasterSchedule() {
	require_once "master-schedule-email-fns.php";
	require_once "prov-schedule-fns.php";
	if(!function_exists('userRole')) { // needed by prov-schedule-fns.php
		function userRole() {
			return 'o';
		}
		function adequateRights($needed) {
			return true;
		}
	}
	emailMasterSchedule();
}
function cronGenerateBillables() {
	global $tables, $preferences;
	require_once "invoice-fns.php";
	require_once "preference-fns.php";
	$preferences = fetchPreferences();
	// Note: for dev testing purposes ensure that database schema is up to date

	$result = mysql_query("SHOW COLUMNS FROM tblservicepackage");
	$ran = false;
	if (!$result) {
			echo 'Could not run query: ' . mysql_error();
			exit;
	}
	if (mysql_num_rows($result) > 0) {
			while ($row = mysql_fetch_assoc($result)) {
					if($row['Field'] == 'prepaid') {
						billingCron($force=false);
						$ran = true;
					}
			}
	}
	if(!$ran) echo "Did not run billingCron for $db.\n";
}
function checkHolidays() {
	global $tables;
	require_once "holidays-future.php";
	if(in_array('tblsurcharge', $tables))
		issueSystemHolidayNotice($exactLookahead=true);
}
function sendOverduePrepaymentEmails() {
	global $scriptPrefs;
	if(!in_array('relstaffnotification', fetchCol0("SHOW TABLES"))) {echo "skipped."; return;}
	$scriptPrefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
	require_once "event-email-fns.php";
	if(!staffToNotify('p')) return;
	$message = getPrepaymentMessage();
	//echo "\nMESSAGE:\n$message\n........................";
	//echo "SERVER: [".print_r($_SERVER, 1).']<p>';
	if($message) {
		require_once "event-email-fns.php";
		require_once "comm-fns.php";
		return notifyStaff('p', "Overdue Prepayments Report", $message);
	}
}
function getPrepaymentMessage() {
	global $lookahead;
	require_once "field-utils.php";
	require_once "gui-fns.php";
	require_once "prepayment-fns.php";
	require_once "preference-fns.php";
	$scriptPrefs = fetchPreferences();
	$prepayments = findPrepayments(date('Y-m-d'), $lookahead);

	//echo "\nPREPAYMENTS (lookahead: $lookahead days):\n".print_r($prepayments, 1)."\n........................";

	foreach($prepayments as $clientptr => $client) {
	//echo "{$client['clientname']} credit: ".getUnusedClientCreditTotal($clientptr)."\n";
		if(getUnusedClientCreditTotal($clientptr) > $client['prepayment']) continue;
		
		if(!$client['email']) $email =  "No email address found.";
		else $email =  "<a href='mailto:{$client['email']}'>{$client['email']}</a>";
		$phone = primaryPhoneNumber(getOneClientsDetails($clientptr, array('email', 'phone')));
		$phone = $phone ? $phone : 'No phone number found.';

		$url = "client-edit.php?id={$client['clientptr']}&tab=services";
		$correspondentLink = "<a href='".globalURL($url)."'>{$client['clientname']}</a>";

		$line = $correspondentLink.' - '.dollarAmount($client['prepayment']);
		$line .= " Email: $email Phone: $phone";
		$clients[] = $line;
	}
	
	$message = '';
	if($clients) {
		$message = "The following clients owe prepayments for services starting in the next $lookahead day".($lookahead == 1 ? '' : 's').':<p>';
		$message .= join("<br>\n", $clients);
	}
		
	return $message;
}
function runEmailRelatedJobs() {
	// context: a business, as set up by cron=queued-msgs-email.php
	global $biz, $globalbizptr, $NO_SESSION;
	$globalbizptr = $biz['bizid'];

	if(!function_exists('userRole')) { // needed by prov-schedule-fns.php
		function userRole() {
			return 'o';
		}
		function adequateRights($needed) {
			return true;
		}
	}	
	// TEMPORARY -- UNTIL WE SWAP IN THE NEW MAIL ENGINE <== Hah!
	if($hhost = isSMTPHostExperimental()) {
		echo "DB: {$biz['db']} skipped  [$hhost].\n";
		continue;
	}
	echo "DB: {$biz['db']}\n";
	
	require_once "provider-memo-fns.php";
	require_once "provider-fns.php";
	require_once "appointment-fns.php";
	require_once "response-token-fns.php";
	require_once "invoice-gui-fns.php";
	require_once "reminder-fns.php";
	
	$NO_SESSION['i18n'] = getI18NProperties($biz['country']);				
	$messageConfirmations = enqueueProviderMemos();  // queuedemail['emailid']=>confirmations
	preProcessInvoicePreviewEmails();
	
	require_once "appointment-client-notification-fns.php";
	require_once "comm-fns.php";
	require_once "email-fns.php";
	require_once "preference-fns.php";
	emailWaitingVisitReports(); // generates and queues up waiting visit reports
	sendReminders($onceADay=1);  // enforces once per day and chooses the time to send
	if(fetchPreference('enableIncompleteScheduleNotifications')) {
		require_once "client-sched-request-fns.php";
		generateIncompleteScheduleRequestSystemNotification();  // tests incompleteScheduleNotificationCheckMinutes
	}
	$messages = sendAllQueuedEmail(); // queuedemail['emailid']=>messageid
	reminderCleanup();
	postProcessInvoicePreviewEmails();
	if($messageConfirmations) foreach($messageConfirmations as $queuemsgptr => $confirmationids) {
		// confirmations have queuedemail['emailid'] for messageptrs
		updateTable('tblconfirmation', array('msgptr'=>$messages[$queuemsgptr]), "confid IN (".join(',', $confirmationids).")");
	}
 	//file_put_contents('/home/jweirger/cron-result.txt', "Result [$now]:\n[$result]");S
  
 	require_once "survey-fns.php";
 	generateSubmissionDigestNotification();  // no-op if submissionNotificationPolicy is NOT "digest"
}