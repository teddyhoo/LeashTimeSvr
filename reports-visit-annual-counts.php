<? // reports-visit-annual-counts.php
/* Show completed visit counts for specified intervals.  Args:
- years, e.g., 2014,2015,2016
- breakdown: month, service, sitter, (client?)
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";
require_once "pet-fns.php";
require_once "petpick-grid-client.php";
require_once "time-framer-mouse.php";
require_once "client-sched-request-fns.php";
require_once "request-fns.php";


$locked = locked('o-');

extract($_REQUEST);
//extractVars('years,breakdown', $_REQUEST);

if(1 || $POST) {
	$servicenames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype ORDER BY label");
	$sitternames = fetchKeyValuePairs("SELECT providerid, CONCAT(fname, ' ', lname) FROM tblprovider ORDER BY lname, fname");
	$months = explode(',', '-,January,February,March,April,May,June,July,August,September,October,November,December');
	$years = explode(',', $years);
	sort($years);
	foreach($years as $year) $clauses[] =  "date LIKE '$year-%'";
	if(!$years) {$error = 'years must be specified'; exit;}
	$sql = "SELECT * FROM tblappointment WHERE completed IS NOT NULL AND (".join(' OR ', $clauses).")";
	$result = doQuery($sql);
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
  	$date = explode('-', $row['date']);
  	$year = $date[0];
		$month = $date[1];
  	$yearCounts[$year]['count'] += 1;
  	if($breakdown == 'month') $yearCounts[$year][(int)$month] += 1;
  	else if($breakdown == 'service') $yearCounts[$year][$servicenames[$row['servicecode']]] += 1;
  	else if($breakdown == 'sitter') $yearCounts[$year][$sitternames[$row['servicecode']]] += 1;
  }
  foreach($yearCounts as $year => $data) {
		$totalLabel = $csv ? 'Total' : '<b>Total</b>';
  	$rows[] = array('year'=>$year, 'breakdown'=>$totalLabel, 'count'=>$data['count']);
  	$keys = $breakdown == 'month' ? 	
  		array(1,2,3,4,5,6,7,8,9,10,11,12) : (
  		$breakdown == 'service' ? $servicenames :
  		$sitternames);
  	if($breakdown) foreach($keys as $key) {
  		if($key == 'count') continue;
  		if(!$data[$key] && $breakdown != 'month') continue;
  		$sublabel = $breakdown == 'month' ? $months[$key] : $key;
			$rows[] = array('year'=>$year, 'breakdown'=>$sublabel, 'count'=>$data[$key]);
		}
	}
  if($csv) {
		header("Content-Type: text/csv");
		header("Content-Disposition: inline; filename=Completed-Visit-Counts.csv ");
		dumpCSVRow("Visits Completed in ",join(', ', $years));
		dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
		$columns = explodePairsLine("year|Year||breakdown|".ucfirst($breakdown)."||count|Count");
		dumpCSVRow($columns);
		foreach($rows as $row) {
			dumpCSVRow($row);
		}
  }
  else {
  	$breakdown = $breakdown ? $breakdown : ' ';
		$columns = explodePairsLine("year|Year||breakdown|".ucfirst($breakdown)."||count|Count");
		foreach($rows as $i => $row) {
			$rows[$i]['count'] = number_format($row['count']);
		}
  	tableFrom($columns, $rows, $attributes='BORDER=1');
  }
}

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

