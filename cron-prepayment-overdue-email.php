#!/usr/bin/php
<?// cron-prepayment-overdue-email.php
// use crontab to schedule the job
set_time_limit(30 * 60);
$lookahead = 2;

set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');


include 'comm-fns.php';

$noSession = true;
require_once "common/init_db_common.php";
require_once "preference-fns.php";
$databases = fetchCol0("SHOW DATABASES");

$lastHost = null;
foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
if($biz['db'] == 'dogslife') continue; // for testing cron-daily-tasks.php	
	if(!in_array($biz['db'], $databases)) {
		echo "DB: {$biz['db']} not found.\n";
		continue;
	}
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
	echo "\n(prepayment) Selected {$biz['db']}...";
	/*if($lastHost != $biz['dbhost']) {
		mysqli_close();
		$lnk = mysqli_connect($biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if ($lnk < 1) {
			echo "Not able to connect: invalid database username and/or password.\n";
		}
	}
	$lastHost = $biz['dbhost'];
	if(!mysqli_select_db($biz['db'])) echo "Failed to select {$biz['db']}\n";
	echo "\n(prepayment) Selected {$biz['db']}...";
	if(mysqli_error()) echo mysqli_error();
	*/
	$NO_SESSION['i18n'] = getI18NProperties($biz['country']);
	$lastRunDate = fetchPreference('lastOverduePrepaymentCronRunDate');
	$runDate = date('Y-m-d');
	if($lastRunDate != $runDate) {
		sendOverduePrepaymentEmails();
		setPreference('lastOverduePrepaymentCronRunDate', $runDate);
	}
	else echo "\nRan earlier today [{$biz['db']}].  Skipped.";
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
0 6 * * * /var/www/petbizdev/cron-prepayment-overdue-email.php >> /var/www/petbiz/cronout/mailcron.out

*/

function sendOverduePrepaymentEmails() {
	global $scriptPrefs;
	if(!in_array('relstaffnotification', fetchCol0("SHOW TABLES"))) {echo "skipped."; return;}
	$scriptPrefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
	require_once "event-email-fns.php";
	if(!staffToNotify('p')) return;
	$message = getPrepaymentMessage();
echo "\nMESSAGE:\n$message\n........................";
echo "SERVER: [".print_r($_SERVER, 1).']<p>';
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

echo "\nPREPAYMENTS (lookahead: $lookahead days):\n".print_r($prepayments, 1)."\n........................";

	foreach($prepayments as $clientptr => $client) {
echo "{$client['clientname']} credit: ".getUnusedClientCreditTotal($clientptr)."\n";
		if(getUnusedClientCreditTotal($clientptr) > $client['prepayment']) continue;
		$email = emailLink($client);
		$phone = primaryPhoneNumber(getOneClientsDetails($clientptr, array('email', 'phone')));
		$phone = $phone ? $phone : 'No phone number found.';
		$line = correspondentLink($client).' - '.dollarAmount($client['prepayment']);
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

function emailLink($corresp) {
	if(!$corresp['email']) return "No email address found.";
	return "<a href='mailto:{$corresp['email']}'>{$corresp['email']}</a>";
}

function correspondentLink($corresp) {
	global $mein_host;
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	$urlBase = "$mein_host$this_dir";
	$url = "client-edit.php?id={$corresp['clientptr']}&tab=services";
	return "<a href='".globalURL($url)."'>{$corresp['clientname']}</a>";
}
	
