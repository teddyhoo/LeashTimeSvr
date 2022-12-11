#!/usr/bin/php
<?// cron-send-provider-schedules.php
// use crontab to schedule the job
set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
require_once "prov-notification-fns.php";
require_once "preference-fns.php";
set_time_limit(300);
$noSession = true;
$mein_host = "https://Leashtime.com";
$this_dir = "";

$delayed = true;

require_once "common/init_db_common.php";
$databases = fetchCol0("SHOW DATABASES");

foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
if($biz['db'] == 'dogslife') continue; // for testing cron-daily-tasks.php	
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		$errMessage="Not able to connect: invalid database username and/or password.";
	}
	$lnk1 = mysqli_select_db($db);
	if(mysqli_error()) echo mysqli_error();
	
	$NO_SESSION['i18n'] = getI18NProperties($biz['country']);				
	sendWeeklyOrDailyProviderSchedules(date('Y-m-d'), $delayed);
}

/*****  (0-6, 0 = Sunday),
Runs once a day at 4am.  On each business' preferred schedule day, weekly schedules are sent to designated providers.
On a given day, each provider receives either no schedule, a weekly schedule, or a daily schedule.


# m h  dom mon dow   command
0 4 * * * /var/www/petbizdev/cron-send-provider-schedules.php >> /var/www/petbizdev/sendschedcron.out                                                           w/petbizdev/mailcron.out
********/
