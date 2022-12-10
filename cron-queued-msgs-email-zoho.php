#!/usr/bin/php
<?// cron-queued-msgs-email-zoho.php
// use crontab to schedule the job
// 3,8,13,18,23,28,33,38,43,48,53,58 * * * * /var/www/prod/cron-queued-msgs-email.php >> /var/www/prod/cronout/mailcron.out

set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');

require_once "preference-fns.php";
include 'comm-fns.php';

$noSession = true;
require_once "common/init_db_common.php";
ensureInstallationSettingsWithThisDirectory('/var/www/prod');
$databases = fetchCol0("SHOW DATABASES");

$lastHost = null;
echo "\n".date('Y-m-d H:i:s')."\n";
//echo globalUrl('crunmbs')."\n";
foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	if($lastHost != $biz['dbhost']) {
		mysql_close();
		$lnk = mysql_connect($biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if ($lnk < 1) {
			$errMessage="Not able to connect: invalid database username and/or password.";
		}
	}
	$lastHost = $biz['dbhost'];
	mysql_select_db($biz['db']);
	if(mysql_error()) echo mysql_error();
// TEMPORARY -- UNTIL WE SWAP IN THE NEW MAIL ENGINE
if($hhost = !isSMTPHostExperimental()) {
	echo "DB: {$biz['db']} skipped.\n";
	continue;
}
$host = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'emailHost' LIMIT 1");
echo "DB: {$biz['db']} [$host]\n";
	
	require_once "provider-memo-fns.php";
	require_once "provider-fns.php";
	require_once "appointment-fns.php";
	require_once "response-token-fns.php";
	$NO_SESSION['i18n'] = getI18NProperties($biz['country']);				
	$messageConfirmations = enqueueProviderMemos();  // queuedemail['emailid']=>confirmations
	$messages = sendAllQueuedEmail(); // queuedemail['emailid']=>messageid
	if($messageConfirmations) foreach($messageConfirmations as $queuemsgptr => $confirmationids) {
		// confirmations have queuedemail['emailid'] for messageptrs
		updateTable('tblconfirmation', array('msgptr'=>$messages[$queuemsgptr]), "confid IN (".join(',', $confirmationids).")");
	}
  //file_put_contents('/home/jweirger/cron-result.txt', "Result [$now]:\n[$result]");S
}

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

*/
