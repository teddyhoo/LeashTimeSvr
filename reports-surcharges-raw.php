<? // reports-surcharges-raw.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "item-note-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,provider,sort,csv', $_REQUEST));

$clientDetail = $_POST ? $clientDetail : 1;
		
$pageTitle = "Surcharges Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('Surcharges scheduled in the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	hiddenElement('csv', '');
?>
	</td></tr>
	<tr><td colspan=2>
<?
$options = array('All Sitters'=>-1);
$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), providerid FROM tblprovider ORDER BY lname, fname"));
labeledSelect('Sitters: ', 'provider', $provider, $options);

?>
	</td></tr>
	
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	//echoButton('', 'Print Report', "spawnPrinter()");
	//echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()");
?>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start','Starting Date','end','Ending Date');
	function genReport() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) document.reportform.submit();
	}
	function genCSV() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			document.getElementById('csv').value=1;
		  document.reportform.submit();
			document.getElementById('csv').value=0;
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Surcharges-Scheduled.csv ");
	dumpCSVRow($pageTitle);
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
if($start && $end) {
	$provs = fetchKeyValuePairs(
			"SELECT providerid, CONCAT_WS(' ', fname, lname), lname, fname
				FROM tblprovider
				ORDER BY lname, fname");
	$provs[0] = 'Unassigned';
	$provids = array_keys($provs);
//echo ">>>";print_r($wages);	
	$surcharges = fetchVisits($start, $end, $provider);
	if($csv) surchargesCSV($start, $end, $surcharges);
	else surchargesTable($start, $end, $surcharges);
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}

function fetchVisits($start, $end, $providerid) {
	$rows = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$filter = $providerid && $providerid != -1 ? "AND providerptr = $providerid" : "";
	$sql = "SELECT s.*,
					CONCAT_WS(' ', s.date, starttime) as datetime, 
					CONCAT_WS(' ', tblclient.fname, tblclient.lname) as client, 
					CONCAT_WS(',', tblclient.lname, tblclient.fname) as clientsort, 
					CONCAT_WS(' ', tblprovider.fname, tblprovider.lname) as provider, 
					CONCAT_WS(',', tblprovider.lname, tblprovider.fname) as providersort,
					IF(completed, 'completed', IF(canceled, 'CANCELED', 'INCOMPLETE')) AS status,
					label
					FROM tblsurcharge s
					LEFT JOIN tblclient ON clientid = clientptr
					LEFT JOIN tblprovider ON providerid = providerptr
					LEFT JOIN tblsurchargetype t ON surchargetypeid = surchargecode					
					WHERE s.date >= '$start' AND s.date <= '$end' $filter
					ORDER BY s.date, starttime";
	$result = doQuery($sql);
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		//$prov = $grp['providerptr'];
		$row['date'] = shortDateAndTime(strtotime($row['date']));
		$rows[] = $row;
	}
	
	return $rows;
}

function surchargesTable($start, $end, $rows) {
	$columns = explodePairsLine('datetime|Date||client|Client||label|Service||status|Status||charge|Charge||provider|Sitter||rate|Rate');
	$numCols = count($columns);
	$colClasses = array('charge' => 'dollaramountcell', 'adjustment' => 'dollaramountcell', 'rate' => 'dollaramountcell', 'bonus' => 'dollaramountcell'); 
	//$headerClass = array('pay' => 'dollaramountheader', /*'pay' => 'dollaramountheader'*/);

	echo "<style>.topline {border-top:solid black 1px;}</style>";
	$prov = -1;
	foreach((array)$rows as $i => $row) {
//echo print_r($rows[$i], 1)."<br>";	
		foreach(explode(',', 'charge,adjustment,rate,bonus') as $fld)
			$rows[$i][$fld] = dollarAmount($rows[$i][$fld]);
		$dispRow = array();
		foreach($columns as $c => $unused)
			$dispRow[$c] = $rows[$i][$c];
		$finalrows[] = $dispRow;
		$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	}
	//$finalrows[] = array('#CUSTOM_ROW#'=>"<tr class='topline'><td colspan=$numCols class='fontSize1_1em'><b>TOTALS</b></td></tr>");
	if($finalrows)
		tableFrom($columns, $finalrows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);
	else echo "<p>No surcharges to report.";
}

function surchargesCSV($start, $end, $rows) {
	$columns = explodePairsLine('datetime|Date||client|Client||label|Service||status|Status||charge|Charge||provider|Sitter||rate|Rate');
	dumpCSVRow($columns);
	foreach($rows as $row) {
		dumpCSVRow($row, array_keys($columns));
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

if(!$csv && !$print) {
?>
<script language='javascript'>
</script>
<? }
