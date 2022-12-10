<? // maint-report-dup-payments.php
// for a given day (default: today)
// show probable dup payments
// for each business
set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');

function processBusiness() {
	global $date, $biz, $db, $bizptr, $globalZips, $bizCount;
	$date = date('Y-m-d');
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	$dups = fetchKeyValuePairs(
		"SELECT clientptr, count(*), CONCAT_WS(' ', amount, clientptr) as fingerprint
			FROM tblcredit
			WHERE issuedate LIKE '$date %' AND payment = 1 AND (sourcereference LIKE 'CC%' OR sourcereference LIKE 'ACH%')
			GROUP BY fingerprint"); //  AND (sourcereference LIKE 'CC%' OR sourcereference LIKE 'ACH%')
	if(!$dups) return null;
	foreach($dups as $clientptr => $count)
		if($count < 2)
			unset($dups[$clientptr]);

	if(!$dups) {
		return;
	}
	echo "<p><b>{$biz['bizname']} ({$biz['db']})</b><br>";

	foreach($dups as $clientptr => $count) 
		echo fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientptr LIMIT 1", 1)
			." $count<br>";
}

require_once "maint-dbs-report.inc.php";
