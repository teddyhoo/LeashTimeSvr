#!/usr/bin/php
<?// cron-check-holidays.php
// use crontab to check for recurring appointments on upcoming holidays

set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
require_once "common/init_db_common.php";
require_once "holidays-future.php";

$delayed = true;

$databases = fetchCol0("SHOW DATABASES");

foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
if($biz['db'] == 'dogslife') continue; // for testing cron-daily-tasks.php	
	if(!in_array($biz['db'], $databases)) {
		echo date('m/d/Y H:i:s')." (time zone uncertain) DB: {$biz['db']} not found.\n";
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
	if(in_array('tblsurcharge', $tables)) {
		$NO_SESSION['i18n'] = getI18NProperties($biz['country']);				
		issueSystemHolidayNotice($exactLookahead=true);
	}
}



/*****  (0-6, 0 = Sunday),
Runs once a day at 4am.  On each business' preferred schedule day, weekly schedules are sent to designated providers.
On a given day, each provider receives either no schedule, a weekly schedule, or a daily schedule.


# m h  dom mon dow   command
0 4 * * * /var/www/petbizdev/cron-send-provider-schedules.php >> /var/www/petbizdev/sendschedcron.out                                                           w/petbizdev/mailcron.out
********/
