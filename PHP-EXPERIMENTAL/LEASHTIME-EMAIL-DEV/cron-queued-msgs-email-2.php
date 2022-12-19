#!/usr/bin/php
<?// cron-queued-msgs-email.php
// use crontab to schedule the job
ini_set('memory_limit','256M'); // doubled to accommodate fat master schedules
set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');

require_once "preference-fns.php";
include 'comm-fns-2.php';

require_once "common/init_db_common-2.php";
ensureInstallationSettingsWithThisDirectory('/var/www/prod');
$databases = fetchCol0("SHOW DATABASES");
$lastHost = null;
//fwrite($strrm, "\n".date('Y-m-d H:i:s')."\n");

//echo globalUrl('crunmbs')."\n";
foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
	if(!in_array($biz['db'], $databases)) {
		continue;
	}
	$globalbizptr = $biz['bizid'];
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=1);
	setLocalTimeZone(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeZone' LIMIT 1"));

	if(TRUE) { // functionality ported to cron-fns.php
		require_once "cron-fns-2.php";
		runEmailRelatedJobs();
	}
	else {
		
		// TEMPORARY -- UNTIL WE SWAP IN THE NEW MAIL ENGINE
		if($hhost = isSMTPHostExperimental()) {
			continue;
		}

		require_once "provider-memo-fns-2.php";
		require_once "provider-fns.php";
		require_once "appointment-fns-2.php";
		require_once "response-token-fns-2.php";
		require_once "invoice-gui-fns-2.php";
		require_once "reminder-2-fns.php";
		require_once "appointment-client-notification-fns-2.php";
		require_once "comm-fns-2.php";
		require_once "email-fns.php";
		require_once "preference-fns.php";

		$NO_SESSION['i18n'] = getI18NProperties($biz['country']);	
		echo ("NO SESSION: " . $NO_SESSION['i18n'] . "\n");
					
		$messageConfirmations = enqueueProviderMemos();  // queuedemail['emailid']=>confirmations
		//preProcessInvoicePreviewEmails();
		//emailWaitingVisitReports(); // generates and queues up waiting visit reports
		//sendReminders($onceADay=1);  // enforces once per day and chooses the time to send

		$messages = sendAllQueuedEmail(); // queuedemail['emailid']=>messageid

		//reminderCleanup();
		//postProcessInvoicePreviewEmails();

		if($messageConfirmations) foreach($messageConfirmations as $queuemsgptr => $confirmationids) {
			echo "Confirmation ID: $confirmationids";
			
			// confirmations have queuedemail['emailid'] for messageptrs

			/******TED
			updateTable('tblconfirmation', array('msgptr'=>$messages[$queuemsgptr]), "confid IN (".join(',', $confirmationids).")");
			******/
		}
	} // else
	
  //file_put_contents('/home/jweirger/cron-result.txt', "Result [$now]:\n[$result]");
  
} // foreach business

/*

# (Use to post in the top of your crontab)
# ------------- minute (0 - 59)
# | ----------- hour (0 - 23)
# | | --------- day of month (1 - 31)
# | | | ------- month (1 - 12)
# | | | | ----- day of week (0 - 6) (Sunday=0)
# | | | | |
# * * * * * command to be executed
* * * * * /var/www/gsk/email-cron-test.php >> /home/jweirger/cron-out.txt
0,5,10,15,20,25,30,35,40,45,50,55 * * * * /var/www/prod/cron-queued-msgs-email.php >> /var/www/prod/cronout/mailcron.out

*/
