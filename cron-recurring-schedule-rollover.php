#!/usr/bin/php
<?// cron-recurring-schedule-rollover.php
// use crontab to schedule the job
set_time_limit(0); // prob unnecessary

set_include_path('/var/www/prod:');
require_once "preference-fns.php";
include 'service-fns.php';

function markTaskComplete($bizptr, $jobKey) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	replaceTable('tblcrons', array('bizptr'=>$bizptr, 'task'=>$jobKey, 'lastrun'=>date('Y-m-d')));
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
}



$noSession = true;
require_once "common/init_db_common.php";
$databases = fetchCol0("SHOW DATABASES");
$starttime = microtime(1);
foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
//if($biz['db'] == 'dogslife') continue; // for testing cron-daily-tasks.php	
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	$lnk = mysql_connect($biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	if ($lnk < 1) {
		$errMessage="Not able to connect: invalid database username and/or password.";
	}
	$lnk1 = mysql_select_db($biz['db']);
	if(mysql_error()) echo mysql_error();
	$CRON_DiscountsAreEnabled = in_array('tbldiscount', fetchCol0("SHOW TABLES"));
	$NO_SESSION['i18n'] = getI18NProperties($biz['country']);				
	setLocalTimeZone($biz['timeZone']);
	rolloverRecurringSchedules();
	markTaskComplete($biz['bizid'], 'recurring-schedule-rollover');
}

require "common/init_db_common.php";
setLocalTimeZone('America/New_York');

logChange(-999, 'rolloverruntime', 'c', (microtime(1) - $starttime));


