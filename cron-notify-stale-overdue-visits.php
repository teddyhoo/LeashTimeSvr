#!/usr/bin/php
<?// cron-notify-stale-overdue-visits.php
// use crontab to send overdue visit notifications
set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
require_once "stale-appointment-fns.php";

set_time_limit(300);

$noSession = true;
$mein_host = "https://Leashtime.com";
$this_dir = "";

$delayed = true;

require_once "common/db_fns.php";
ensureInstallationSettingsWithThisDirectory('/var/www/prod'); // $SUBDIRECTORY will be non-null for corp scripts
require_once "common/init_db_common.php";

$databases = fetchCol0("SHOW DATABASES");

foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
	if(!in_array($biz['db'], $databases)) {
		//echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	$dbhost = $biz['dbhost'];
	$dbuser = $biz['dbuser'];
	$dbpass = $biz['dbpass'];
	$db = $biz['db'];
	$bizptr = $biz['bizid'];
	$lnk = mysql_connect($dbhost, $dbuser, $dbpass);
	if ($lnk < 1) {
		$errMessage="Not able to connect: invalid database username and/or password.";
	}
	$lnk1 = mysql_select_db($db);
	if(mysql_error()) echo mysql_error();
	setLocalTimeZone($biz['timeZone']);
	
	if($appointmentIds = findNewlyStaleVisits()) {  // checks to ensure that tblstaleappointment exists
		/* echo */generateStaleVisitsRequest($appointmentIds);
		markStaleVisits($appointmentIds);
	}
}

/*****  (0-6, 0 = Sunday),
Runs once a day at 4am.  On each business' preferred schedule day, weekly schedules are sent to designated providers.
On a given day, each provider receives either no schedule, a weekly schedule, or a daily schedule.


# m h  dom mon dow   command
3,8,13,18,23,28,33,38,43,48,53,58  /var/www/petbizdev/cron-notify-stale-overdue-visits.php                                                      w/petbizdev/mailcron.out
********/
