<? // reports-visits-year-over-year.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-gui-fns.php";

extract($_REQUEST);

$locked = locked('o-');

$startYear = $startYear ? $startYear : date('Y')-1;
$endYear = $endYear ? $endYear : date('Y');

function getVisitCountsByMonth($year) {
	$sql = "SELECT $year as year, MONTH(date) as month, SUM(1) as total
		FROM tblappointment
		WHERE canceled IS NULL
			AND date >= '{$year}-01-01'
			AND date <= '{$year}-12-31'
		GROUP BY month";
	$rows = fetchAssociations($sql);
	foreach($rows as $i => $row) {
		$day1 = "{$row['month']}/1/$year";
		$daysInMonth = date('t', strtotime($day1));
		$rows[$i]['month'] = date('F', strtotime($day1));
		$rows[$i]['number of days'] = $daysInMonth;
		$rows[$i]['visits per day'] = $row['total'] / $daysInMonth;
	}
	return $rows;
}

function dumpCSVRow($row) {
	if(!$row) echo "\n";
	if(is_array($row)) echo join(',', array_map('csv',$row))."\n";
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}

echo "$startYear visits\n";
$rows = getVisitCountsByMonth($startYear);
dumpCSVRow(array_keys($rows[0]));
foreach($rows as $row) dumpCSVRow($row);
		
echo "\n$endYear visits\n";
$rows = getVisitCountsByMonth($endYear);
dumpCSVRow(array_keys($rows[0]));
foreach($rows as $row) dumpCSVRow($row);
