<? // reports-schedule-requests-received.php
// spreadsheet only report, launched from reports-revenue-client.php
// ignores requests that cover date ranges previously supplied
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
$locked = locked('o-');

$start = $_REQUEST['start'];
if(!$start) $errors[] = "no start date supplied.";
else $start = date('Y-m-d', strtotime($start));

$end = $_REQUEST['end'];
if($end) $end = date('Y-m-d', strtotime($end));
else $end = date('Y-m-d');

$sql = "SELECT clientptr, received, note, CONCAT(c.lname, ', ', c.fname) as name
FROM tblclientrequest
LEFT JOIN tblclient c ON clientid = clientptr
WHERE requesttype = 'Schedule' "
.($start ? " AND received >= '$start 00:00:00'" : "")
.($end ? " AND received <= '$end 23:59:59'" : "")
//." AND resolution = 'honored'"
." ORDER BY name, received";

$result = doQuery($sql, 1);

while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$clientptr = $row['clientptr'];
	$lines = explode("\n", $row['note']);
	$dateRange = substr($lines[0], 0, strrpos($lines[0], '|')); // "04/18/2020|04/26/2020|241.20" grab start and end
	$numVisits = count(explode('<>', $lines[1]))	;
	if(!$requests[$clientptr][$dateRange])
		$requests[$clientptr][$dateRange] = array('name'=>$row['name'], 'received'=>$row['received'], 'range'=>str_replace('|','-', $dateRange), 'visits'=>$numVisits);
}

if($requests) {
	//ksort($requests);
	function dumpCSVRow($row, $cols=null) {
		if(!$row) echo "\n";
		if(is_array($row)) {
			if($cols) {
				$nrow = array();
				if(is_string($cols)) $cols = explode(',', $cols);
				foreach($cols as $k) $nrow[] = $row[$k];
				$row = $nrow;
			}
			echo join(',', array_map('csv',$row))."\n";
		}
		else echo csv($row)."\n";
	}
	
	function csv($val) {
	  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
	  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
	  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
		return "\"$val\"";
	}
	
	$starting = shortDate(strtotime($start));
	$ending = shortDate(strtotime($end));
	//print_r($requests);
	require_once "gui-fns.php";
	$cols = explodePairsLine("Client|name||Received|received||Visit Range|range||# visits|visits");
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client Scedule Requests.csv ");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $starting - $ending");
	dumpCSVRow("");
	dumpCSVRow(array_keys($cols));
	foreach($requests as $clientptr => $cluster) {
		$visitsTotal = 0;
		foreach($cluster as $request) {
			$name = $request['name'];
			dumpCSVRow($request, $cols);
			$visitsTotal += $request['visits'];
		}
		dumpCSVRow(array('name'=>$name, 'received'=>'TOTAL', 'range'=>count($cluster), 'visits'=>$visitsTotal), $cols);
	}
}

