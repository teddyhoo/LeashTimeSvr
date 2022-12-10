<? // reports-sitter-activity.php

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
extract(extractVars('start,end,print,provider,sort,csv,clientDetail', $_REQUEST));

$clientDetail = $_POST ? $clientDetail : 1;
		
$pageTitle = "Sitter Activity Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('Visits completed in the period starting:', 'start', $start, null, null, true, 'end');
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
echo "&nbsp;";
//labeledCheckbox($label, $name, $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null) {
labeledCheckBox('Show client detail', 'clientDetail', $clientDetail);

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
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			var start = escape(document.getElementById('start').value);
			var end = escape(document.getElementById('end').value);
			var provider = document.getElementById('provider');
			provider = provider.options[provider.selectedIndex].value;
			openConsoleWindow('reportprinter', 'reports-hourly-wage.php?print=1&start='+start+'&end='+end+'&provider='+provider, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Sitter-Activity.csv ");
	dumpCSVRow($pageTitle);
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = $pageTitle;
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payments Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	$provs = fetchKeyValuePairs(
			"SELECT providerid, CONCAT_WS(' ', fname, lname), lname, fname
				FROM tblprovider
				ORDER BY lname, fname");
	$provs[0] = 'Unassigned';
	$provids = array_keys($provs);
	$allRates = fetchKeyValuePairs("SELECT providerptr, value  FROM tblproviderpref WHERE property = 'hourlyRate'", 'providerptr');
	$allTravel = fetchKeyValuePairs("SELECT providerptr, value  FROM tblproviderpref WHERE property = 'travelAllowance'", 'providerptr');
	
	$wages = fetchWages($start, $end, $provider, !$clientDetail);
//echo ">>>";print_r($wages);	
	
	if($csv) wagesCSV($start, $end, $wages);
	else wagesTable($start, $end, $wages);
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

function fetchWages($start, $end, $providerid, $noClientDetail=false) {
	global $allVisitTotals, $allVisitRevs, $allowanceTotals, $allRates, $allTravel, $allHours, $allVisitCounts, $allSurchargeTotals, $allGratuityTotals;
	$rows = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$filter = $providerid && $providerid != -1 ? "AND providerptr = $providerid" : "";
	$grouping = $noClientDetail ? 'providerptr' : "CONCAT_WS(' ', clientptr, providerptr)";
	$sql = "SELECT providerptr, count(*) as visits, 
					sum(rate+ifnull(bonus,0)) as pay, 
					sum(charge+ifnull(adjustment,0)) as revenue,
					sum(hours) as hours,
					$grouping as grouping,
					CONCAT_WS(' ', tblclient.fname, tblclient.lname) as client, 
					CONCAT_WS(',', tblclient.lname, tblclient.fname) as clientsort, 
					CONCAT_WS(' ', tblprovider.fname, tblprovider.lname) as provider, 
					CONCAT_WS(',', tblprovider.lname, tblprovider.fname) as providersort
					FROM tblappointment
					LEFT JOIN tblclient ON clientid = clientptr
					LEFT JOIN tblprovider ON providerid = providerptr
					LEFT JOIN tblservicetype ON servicetypeid = servicecode					
					WHERE completed IS NOT NULL AND date >= '$start' AND date <= '$end' $filter
					GROUP BY grouping
					ORDER BY providersort, clientsort";
	$result = doQuery($sql);
  while($grp = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$prov = $grp['providerptr'];
		$hours = $grp['hours'] ? (strtotime($grp['hours']) - strtotime('00:00')) / 3600 : 0;
//if(mattOnlyTEST() && !$prov) {echo print_r($appt, 1)." hours: $hours".'<br>';}
		$grp['hours'] = $hours;
		//$grp['pay'] = $_SESSION['preferences']['sittersPaidHourly'] ? $hours * (float)$allRates[$prov] : $grp['pay'];
		$allVisitTotals[$prov] += $grp['pay'];
		$allVisitRevs[$prov] += $grp['revenue'];
		$allHours[$prov] += $hours;
		$allVisitCounts[$prov] += $grp['visits'];
		$rows[] = $grp;
	}
	
	return $rows;
}

function wagesTable($start, $end, $rows) {
	global $allVisitTotals, $allVisitCounts, $allowanceTotals, $provs, $allRates, $allTravel, $allSurchargeTotals, $allGratuityTotals, $clientDetail;

	$columns = explodePairsLine('client|Client||visits|Visits||hours|Hours||pay|Pay||revenue|Revenue');
	$numCols = count($columns);
	$colClasses = array('pay' => 'dollaramountcell', 'revenue' => 'dollaramountcell'); 
	$headerClass = array('pay' => 'dollaramountheader', /*'pay' => 'dollaramountheader'*/);

	echo "<style>.topline {border-top:solid black 1px;}</style>";
	$prov = -1;
	foreach((array)$rows as $i => $row) {
//echo print_r($rows[$i], 1)."<br>";		
		$rows[$i]['pay'] = dollarAmount($rows[$i]['pay']);
		$rows[$i]['revenue'] = dollarAmount($rows[$i]['revenue']);
		if(!$clientDetail) $rows[$i]['client'] = $row['provider'];
		if($row['providerptr'] != $prov) {
			$provCount += 1;
			if($prov != -1) {
				$rowClass = 'futuretask';
				providerSummaryRows($prov, $finalrows, $rowClasses);

			}
			$prov = $row['providerptr'];
			//$rowClass = 'futuretaskEVEN';
			$providerLabel = $clientDetail ? "{$row['provider']} ($allVisitCounts[$prov] visits)" : '';
			$finalrows[] = array('#CUSTOM_ROW#'=>"<tr class='topline'><td colspan=$numCols class='fontSize1_1em'><b>{$providerLabel}</b></td></tr>");
			$rowClasses[] = $rowClass = $rowClass== 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';//PLACEHOLDER
			if($clientDetail) $rowClass = 'futuretask';
		}
		$finalrows[] = $rows[$i];
		$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	}
	
	$finalrows[] = array('#CUSTOM_ROW#'=>"<tr class='topline'><td colspan=$numCols class='fontSize1_1em'><b>TOTALS</b></td></tr>");
	$rowClasses[] = $rowClass = $rowClass== 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';//PLACEHOLDER
	finalSummaryRows($finalrows, $rowClasses);
	if($finalrows)
		tableFrom($columns, $finalrows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);
	else echo "<p>No wages to report.";
}

function providerSummaryRows($prov, &$finalrows, &$rowClasses) {
	global $allVisitCounts, $allVisitRevs, $allHours, $allVisitTotals, $allowanceTotals, $allSurchargeTotals, $allGratuityTotals, $clientDetail;
	$rowClass = 'futuretask';
	$visitCount = $allVisitCounts[$prov];
	$visitCount = !$visitCount ? 'No' : $visitCount;
	$visitCount = "($visitCount visit".($visitCount == 1 ? '' : 's').")";
	if($allHours[$prov]) {
		$finalrows[] = array('hours'=>"<b>Hours Worked $visitCount</b>", 'pay'=>sprintf('%.2f', $allHours[$prov])); 
		$rowClasses[] = $rowClass;
	}
	if($clientDetail) {
		$finalrows[] = 
			array('hours'=>'<b>Total</b>', 
					'pay'=>dollarAmount($allVisitTotals[$prov]+$allowanceTotals[$prov]+$allSurchargeTotals[$prov]+$allGratuityTotals[$prov]),
					'revenue'=>dollarAmount($allVisitRevs[$prov])); 
		$rowClasses[] = $rowClass;
	}
}

function finalSummaryRows(&$finalrows, &$rowClasses) {
	global $allVisitCounts, $allVisitRevs, $allHours, $allVisitTotals, $allowanceTotals, $allSurchargeTotals, $allGratuityTotals, $clientDetail;
	$rowClass = 'futuretask';
	$visitCount = array_sum($allVisitCounts);
	$visitCount = !$visitCount ? 'No' : $visitCount;
	$visitCount = "($visitCount visit".($visitCount == 1 ? '' : 's').")";
	$totalHours = array_sum($allHours);
	if($totalHours) {
		$finalrows[] = array('hours'=>"<b>Hours Worked $visitCount</b>", 'pay'=>sprintf('%.2f', $totalHours)); 
		$rowClasses[] = $rowClass;
	}
	if($clientDetail) {
		$finalrows[] = 
			array('hours'=>'<b>Total</b>', 
					'pay'=>dollarAmount(array_sum($allVisitTotals)+$allowanceTotals[$prov]+$allSurchargeTotals[$prov]+$allGratuityTotals[$prov]),
					'revenue'=>dollarAmount(array_sum($allVisitRevs))); 
		$rowClasses[] = $rowClass;
	}
}


function wagesCSV($start, $end, $rows) {
	global $allVisitTotals, $allVisitRevs, $allowanceTotals, $provs, $allRates, $allTravel, $clientDetail;
	$columns = explodePairsLine('provider|Sitter||client|Client||visits|Visits||hours|Hours||pay|Pay||revenue|Revenue');
	if(!$clientDetail) unset($columns['client']);
	dumpCSVRow($columns);
	foreach($rows as $row) {
		$finalrow['provider'] = $row['provider'];
		if($clientDetail) $finalrow['client'] = $row['client'];
		$finalrow['visits'] = $row['visits'];
		$finalrow['hours'] = $row['hours'];
		$finalrow['pay'] = $row['pay'];
		$finalrow['revenue'] = $row['revenue'];
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