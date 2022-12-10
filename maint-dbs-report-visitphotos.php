<? // maint-dbs-report-visitphotos.php

function processBusiness() {
	global $date, $biz, $db, $bizptr, $formerclients, $tables, $bizName, $totalCount, $csv;
	if($db != 'dogslife')
		if(!$biz['activebiz'] || $biz['test'] || in_array($bizptr, $formerclients)) return;
	$sql = "SELECT COUNT(value)
						FROM tblappointmentprop
						LEFT JOIN tblappointment ON appointmentid = appointmentptr
						WHERE date = '$date' AND property = 'visitphotocacheid'";
	if($n = fetchRow0Col0($sql)) {
		echo "$bizName ($db) stored $n visit photos<br>";
		$totalCount += $n;
		$csv[] = explode('|', "$date|$bizName|$db|$n");
	}
}

function postProcess() {
	global $totalCount, $csv, $date;
	if($totalCount) {
		echo "<hr>Total visit photos uploaded for $date: $totalCount<hr>";
		echo "date,biz,database,photos<br>";
		foreach($csv as $row) {
			dumpCSVRow($row);
			echo "<br>";
		}
	}
	else echo "None uploaded for any business.";
}

$date = $_REQUEST['date'] ? date('Y-m-d', strtotime($_REQUEST['date'])) : date('Y-m-d');
$fullday = date('l n/j/Y', strtotime($date));
echo "<h2>Visit photos uploaded on $fullday</h2>";
$yesterday = date('Y-m-d', strtotime("-1 day", strtotime($date)));
$tomorrow = date('Y-m-d', strtotime("+1 day", strtotime($date)));

echo "<a href='?date=$yesterday'>Back</a>  <a href='?date=$tomorrow'>Forward</a>  <hr>";
require_once "maint-dbs-report.inc.php";