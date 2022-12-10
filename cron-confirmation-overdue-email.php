#!/usr/bin/php
<?// cron-confirmation-overdue-email.php
// use crontab to schedule the job
set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');

require_once "confirmation-fns.php";
include 'comm-fns.php';

$noSession = true;
require_once "common/init_db_common.php";
$databases = fetchCol0("SHOW DATABASES");

$lastHost = null;
foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
if($biz['db'] == 'dogslife') continue; // for testing cron-daily-tasks.php	
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	/*if($lastHost != $biz['dbhost']) {
		mysql_close();
		$lnk = mysql_connect($biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if ($lnk < 1) {
			$errMessage="Not able to connect: invalid database username and/or password.";
		}
	}
	$lastHost = $biz['dbhost'];
	mysql_select_db($biz['db']);
	*/
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);

	//echo "Selected {$biz['db']}...\n";
	if(mysql_error()) echo mysql_error();
	$NO_SESSION['i18n'] = getI18NProperties($biz['country']);				
	sendOverdueConfirmationsEmails();
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
