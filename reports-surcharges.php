<? // reports-surcharges.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,client,sort,csv', $_REQUEST));

		
$pageTitle = "Surcharge Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('For the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	hiddenElement('csv', '');
?>
	</td></tr>
	<tr><td colspan=2>
<?
$options = array('All Clients'=>-1);
$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), clientid FROM tblclient ORDER BY lname, fname"));
labeledSelect('Clients: ', 'client', $client, $options);
?>
	</td></tr>
	
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
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
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			var start = escape(document.getElementById('start').value);
			var end = escape(document.getElementById('end').value);
			var client = document.getElementById('client');
			client = client.options[client.selectedIndex].value;
			openConsoleWindow('reportprinter', 'reports-surcharges.php?print=1&start='+start+'&end='+end+'&client='+client, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Surcharges.csv ");
	dumpCSVRow($pageTitle);
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = $pageTitle;
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>$pageTitle</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	$clients = fetchKeyValuePairs(
			"SELECT clientid, CONCAT_WS(' ', fname, lname), lname, fname
				FROM tblclient
				ORDER BY lname, fname");
	$clientids = array_keys($clients);
	
	$wages = fetchSurcharges($start, $end, $client);
//echo ">>>";print_r($allPayments);	
	
	if($csv) dumpCSV($start, $end, $wages);
	else dumpTable($start, $end, $wages);
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}
else if(!$csv){
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
}

function fetchSurcharges($start, $end, $clientid) {
	global $allSurcharges, $allSurchargeCounts;
	$rows = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$filter = $clientid && $clientid != -1 ? "AND clientptr = $clientid" : "";
	
	$sql = "SELECT tblsurcharge.*,
					CONCAT_WS(' ', tblclient.fname, tblclient.lname) as client, label as service
					FROM tblsurcharge 
					LEFT JOIN tblclient ON clientid = clientptr
					LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
					WHERE completed IS NOT NULL AND tblsurcharge.date >= '$start' AND tblsurcharge.date <= '$end' $filter";
	$result = doQuery($sql);
  while($surch = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$allSurcharges[$surch['clientptr']] += $surch['charge'];
		$allSurchargeCounts[$surch['clientptr']] += 1;
		$surch['time'] = strtotime("{$surch['date']} {$surch['starttime']}");
		$rows[] = $surch;
	}
	usort($rows, 'surchargeSort');	
	return $rows;
}

function surchargeSort($a, $b) {
	global $clientids;
	$aprovindex = array_search($a['clientptr'], $clientids);
	$bprovindex = array_search($b['clientptr'], $clientids);
	return  $aprovindex < $bprovindex ? -1 : (
					$aprovindex > $bprovindex ? 1 : (
					$a['time'] < $b['time'] ? -1 : (
					$a['time'] > $b['time'] ? 1 : 0)));
}				 
	

function dumpTable($start, $end, $rows) {
	global $allSurcharges;
//print_r($rows);

	$columns = explodePairsLine('date|Date||timeofday|Time||service|Surcharge||charge|Charge');
	$numCols = count($columns);
	$colClasses = array('charge' => 'dollaramountcell'); 
	$headerClass = array('charge' => 'dollaramountheader'); //'dollaramountheader'

	echo "<style>.topline {border-top:solid black 1px;}</style>";
	$client = -1;
	foreach((array)$rows as $i => $row) {
//echo print_r($rows[$i], 1)."<br>";		
		$rows[$i]['pay'] = dollarAmount($rows[$i]['charge']);
		$rows[$i]['date'] = shortDate(strtotime($rows[$i]['date']));
		if($rows[$i]['clientptr'] != $client) {
			$clientCount += 1;
			if($client != -1) {
				$rowClass = 'futuretask';
				summaryRows($client, $finalrows, $rowClasses);

			}
			$client = $rows[$i]['clientptr'];
			$rowClass = 'futuretaskEVEN';
			$finalrows[] = array('#CUSTOM_ROW#'=>"<tr class='topline'><td colspan=$numCols class='fontSize1_1em'><b>{$row['client']}</b></td></tr>");
			$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
		}
		$finalrows[] = $rows[$i];
		$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	}
	if($finalrows) {
		summaryRows($client, $finalrows, $rowClasses);
	}
	
	if(FALSE && $clientCount > 1) {
		$totalPayments = dollarAmount($totalPayments);
		$finalrows[] = array('service'=>'<b>All Sitters:</b>', 
													'charge'=>dollarAmount(array_sum($allSurcharges)));
	}
	if($finalrows)
		tableFrom($columns, $finalrows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);
	else echo "<p>No wages to report.";
}

function summaryRows($client, &$finalrows, &$rowClasses) {
	global $allSurcharges, $allSurchargeCounts;
	$rowClass = 'futuretask';
	$visitCount = $allSurchargeCounts[$client];
	$visitCount = !$visitCount ? 'No' : $visitCount;
	$visitCount = "($visitCount visit".($visitCount == 1 ? '' : 's').")";
	$finalrows[] = array('service'=>"<b>Total</b> $visitCount", 'charge'=>dollarAmount($allSurcharges[$client])); 
	$rowClasses[] = $rowClass;
}

function dumpCSV($start, $end, $rows) {
	$columns = explodePairsLine('client|Client||date|Date||timeofday|Time||service|Service||charge|Charge');
	dumpCSVRow($columns);
	foreach($rows as $row) {
		$finalrow['client'] = $row['client'];
		$finalrow['date'] = shortDate(strtotime($row['date']));
		$finalrow['timeofday'] = $row['timeofday'];
		$finalrow['service'] = $row['service'];
		$finalrow['charge'] = $row['charge'];
		dumpCSVRow($finalrow);
	}
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

if(!$csv && !$print) {
?>
<script language='javascript'>
</script>
<? }