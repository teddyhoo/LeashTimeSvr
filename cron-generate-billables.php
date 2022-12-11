#!/usr/bin/php
<?// cron-generate-billables.php
// use crontab to schedule the job
set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
require_once "invoice-fns.php";
require_once "preference-fns.php";

$noSession = true;
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
	
	$preferences = fetchPreferences();
	
	// Note: for dev testing purposes ensure that database schema is up to date

	$result = mysqli_query("SHOW COLUMNS FROM tblservicepackage");
	$ran = false;
	if (!$result) {
			echo 'Could not run query: ' . mysqli_error();
			exit;
	}
	if (mysqli_num_rows($result) > 0) {
			while ($row = mysqli_fetch_assoc($result)) {
					if($row['Field'] == 'prepaid') {
						$NO_SESSION['i18n'] = getI18NProperties($biz['country']);				
						billingCron($force=false);
						$ran = true;
					}
			}
	}
	if(!$ran) echo "Did not run billingCron for $db.\n";
	
}

/*****  (0-6, 0 = Sunday),
Runs once a day at 5:15am.  


# m h  dom mon dow   command
15 5 * * * /var/www/petbizdev/cron-generate-billables.php >> /var/www/petbizdev/generatebillablescron.out                                                           w/petbizdev/mailcron.out
********/
