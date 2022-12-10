<? // maint-dbs-report-visit-reports-today.php
require_once "common/init_session.php";

if(userRole() != 'z') {
	echo "<h2>You must be logged in to the dashboard.</h2>";
	exit;
}
$date = $_REQUEST['date'] ? date('Y-m-d', strtotime($_REQUEST['date'])) : date('Y-m-d');


function processBusiness() {
	global $totalVisitReports, $bizzesWithAny, $bizzesWithMoreThan5, $today, $tables, $biz, $bizName, $bizptr, $goldstars, $date;
	if(!in_array('tblmessage', $tables) || $biz['test'] || !$biz['activebiz'] || !in_array($bizptr, $goldstars)) return;
	require_once "preference-fns.php";
	if($ids = fetchCol0(
		"SELECT appointmentid, value
			FROM tblappointment
			LEFT JOIN tblappointmentprop ON appointmentptr = appointmentid AND property = 'visitreportreceived'
			WHERE date = '$date'
			AND value IS NOT NULL", 1)) {
		$totalVisitReports += count($ids);
		if(count($ids) > 0) $bizzesWithAny += 1;
		if(count($ids) > 5) $bizzesWithMoreThan5 += 1;
		echo "<b>$bizName</b> ".count($ids)."\tvisit reports.<br>";
		echo join("", (array)$lines);
	}
}

function postProcess() {
	global $totalVisitReports, $date, $bizzesWithAny, $bizzesWithMoreThan5;
	echo "<a name='stats'><h2>Stats</h2></a>";
	echo "<b>Total Visit Report Count for $date</b>: $totalVisitReports";
	echo "<p>Businesses with reports on $date</b>: $bizzesWithAny";
	echo "<p>Businesses with more than 5 reports on $date</b>: $bizzesWithMoreThan5";
}
?>
<h2>Visit Reports for <?= longDayAndDate(strtotime($date)) ?></h2>

<?
$today = date('Y-m-d');
require_once "maint-dbs-report.inc.php";
?>
