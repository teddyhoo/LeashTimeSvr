<? // reports-hourly-wage.php

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

		
$pageTitle = "Hourly Wage Report";


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
	header("Content-Disposition: inline; filename=Sitter-Wages.csv ");
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
	
	$wages = fetchWages($start, $end, $provider);
//echo ">>>";print_r($allPayments);	
	
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

function fetchWages($start, $end, $providerid) {
	global $allVisitTotals, $allowanceTotals, $allRates, $allTravel, $allHours, $allVisitCounts, $allSurchargeTotals, $allGratuityTotals;
	$rows = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$filter = $providerid && $providerid != -1 ? "AND providerptr = $providerid" : "";
	
	$sql = "SELECT tblappointment.*,
					CONCAT_WS(' ', tblclient.fname, tblclient.lname) as client, 
					hours, label as service
					FROM tblappointment 
					LEFT JOIN tblclient ON clientid = clientptr
					LEFT JOIN tblservicetype ON servicetypeid = servicecode
					WHERE completed IS NOT NULL AND date >= '$start' AND date <= '$end' $filter";
	$result = doQuery($sql);
  while($appt = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$prov = $appt['providerptr'];
		$hours = $appt['hours'] ? (strtotime($appt['hours']) - strtotime('00:00')) / 3600 : 0;
//if(mattOnlyTEST() && !$prov) {echo print_r($appt, 1)." hours: $hours".'<br>';}
		$appt['hours'] = $hours;
		$appt['pay'] = $hours * (float)$allRates[$prov];
		$appt['time'] = strtotime(trim("{$appt['date']} {$appt['starttime']}"));
		$allVisitTotals[$prov] += $appt['pay'];
		$allHours[$prov] += $hours;
		$allVisitCounts[$prov] += 1;
		$allowanceTotals[$prov] += $allTravel[$prov];
		$rows[] = $appt;
	}
	$sql = "SELECT amount as pay, issuedate as date, ifnull(tipnote, 'Gratuity') as service, providerptr,
					CONCAT_WS(' ', tblclient.fname, tblclient.lname) as client
					FROM tblgratuity
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE issuedate >= '$start' AND issuedate <= '$end 23:59:59' $filter";
	$result = doQuery($sql);
	while($gratuity = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$gratuity['time'] = strtotime($gratuity['date']);
		$prov = $gratuity['providerptr'];
		$allGratuityTotals[$prov] += $gratuity['pay'];
		$rows[] = $gratuity;
	}
	
	$sql = "SELECT tblsurcharge.*,
					CONCAT_WS(' ', tblclient.fname, tblclient.lname) as client, CONCAT('Surcharge: ', label) as service
					FROM tblsurcharge
					LEFT JOIN tblclient ON clientid = clientptr
					LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
					WHERE completed IS NOT NULL AND tblsurcharge.date >= '$start' AND tblsurcharge.date <= '$end' $filter";
	$result = doQuery($sql);
	while($surch = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$surch['time'] = strtotime(trim("{$surch['date']} {$surch['starttime']}"));
		$prov = $surch['providerptr'];
		$surch['pay'] = $surch['rate'] + $surch['bonus'];
		$allSurchargeTotals[$prov] += $surch['rate'];
		$rows[] = $surch;
	}
	usort($rows, 'wageSort');	
	return $rows;
}

function wageSort($a, $b) {
	global $provids;
	$aprovindex = array_search($a['providerptr'], $provids);
	$bprovindex = array_search($b['providerptr'], $provids);
//echo "A: [{$a['providerptr']}] $aprovindex  B: [{$b['providerptr']}] $bprovindex<br>";	
	return  $aprovindex < $bprovindex ? -1 : (
					$aprovindex > $bprovindex ? 1 : (
					$a['time'] < $b['time'] ? -1 : (
					$a['time'] > $b['time'] ? 1 : 0)));
}				 
	

function wagesTable($start, $end, $rows) {
	global $allVisitTotals, $allowanceTotals, $provs, $allRates, $allTravel, $allSurchargeTotals, $allGratuityTotals, $allHours, $allVisitCounts;

	$columns = explodePairsLine('date|Date||timeofday|Time||client|Client||service|Service||pay|Pay');
	$numCols = count($columns);
	$colClasses = array('pay' => 'dollaramountcell', 'travelallowance' => 'dollaramountcell'); 
	$headerClass = array('pay' => 'dollaramountheader', 'travelallowance' => 'dollaramountheader'); //'dollaramountheader'

	echo "<style>.topline {border-top:solid black 1px;}</style>";
	$prov = -1;
	foreach((array)$rows as $i => $row) {
//echo print_r($rows[$i], 1)."<br>";		
		$rows[$i]['pay'] = dollarAmount($rows[$i]['pay']);
		$rows[$i]['date'] = shortDate(strtotime($rows[$i]['date']));
		if($rows[$i]['providerptr'] != $prov) {
			$provCount += 1;
			if($prov != -1) {
				$rowClass = 'futuretask';
				summaryRows($prov, $finalrows, $rowClasses);

			}
			$prov = $rows[$i]['providerptr'];
			$rate = $allRates[$prov] ? dollarAmount($allRates[$prov]).' / hour' : '<i>not specified</i>';
			$allow = $allTravel[$prov] ? dollarAmount($allTravel[$prov]).' / visit' : '<i>not specified</i>';
			$rowClass = 'futuretaskEVEN';
			$summary = "<span class='fontSize1_2em'><b>{$provs[$prov]}</b></span> Visit count: {$allVisitCounts[$prov]} Total hours: {$allHours[$prov]} Rate: $rate  Travel Allowance: $allow ";
			$finalrows[] = array('#CUSTOM_ROW#'=>"<tr class='topline'><td colspan=$numCols class='fontSize1_1em'>$summary</td></tr>");
			$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
		}
		$finalrows[] = $rows[$i];
		$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	}
	if($finalrows) {
		summaryRows($prov, $finalrows, $rowClasses);
	}
	
	if(FALSE && $provCount > 1) {
		$totalPayments = dollarAmount($totalPayments);
		$finalrows[] = array('service'=>'<b>All Sitters:</b>', 
													'pay'=>dollarAmount(array_sum($allVisitTotals)+array_sum($allSurchargeTotals)+array_sum($allGratuityTotals)), 
													'travelallowance'=>dollarAmount(array_sum($allowanceTotals)));
	}
	if($finalrows)
		tableFrom($columns, $finalrows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);
	else echo "<p>No wages to report.";
}

function summaryRows($prov, &$finalrows, &$rowClasses) {
	global $allVisitCounts, $allHours, $allVisitTotals, $allowanceTotals, $allSurchargeTotals, $allGratuityTotals;
	$rowClass = 'futuretask';
	$visitCount = $allVisitCounts[$prov];
	$visitCount = !$visitCount ? 'No' : $visitCount;
	$visitCount = "($visitCount visit".($visitCount == 1 ? '' : 's').")";
	$finalrows[] = array('service'=>"<b>Hours Worked $visitCount</b>", 'pay'=>sprintf('%.2f', $allHours[$prov])); 
	$rowClasses[] = $rowClass;
	$finalrows[] = array('service'=>'<b>Visit Pay Subtotal</b>', 'pay'=>dollarAmount($allVisitTotals[$prov])); 
	$rowClasses[] = $rowClass;
	$finalrows[] = array('service'=>'<b>Surcharge Subtotal</b>', 'pay'=>dollarAmount($allSurchargeTotals[$prov])); 
	$rowClasses[] = $rowClass;
	$finalrows[] = array('service'=>'<b>Gratuity Subtotal</b>', 'pay'=>dollarAmount($allGratuityTotals[$prov])); 
	$rowClasses[] = $rowClass;
	$finalrows[] = array('service'=>'<b>Mileage Reimbursement</b>', 'pay'=>dollarAmount($allowanceTotals[$prov])); 
	$rowClasses[] = $rowClass;
	$finalrows[] = 
		array('service'=>'<b>Total</b>', 
					'pay'=>dollarAmount($allVisitTotals[$prov]+$allowanceTotals[$prov]+$allSurchargeTotals[$prov]+$allGratuityTotals[$prov])); 
	$rowClasses[] = $rowClass;
}

function wagesCSV($start, $end, $rows) {
	global $allVisitTotals, $allowanceTotals, $provs, $allRates, $allTravel;
	$columns = explodePairsLine('provider|Sitter||date|Date||timeofday|Time||client|Client||service|Service||hours|Hours||pay|Pay');
	dumpCSVRow($columns);
	foreach($rows as $row) {
		$finalrow['provider'] = $provs[$row['providerptr']];
		$finalrow['date'] = shortDate(strtotime($row['date']));
		$finalrow['timeofday'] = $row['timeofday'];
		$finalrow['client'] = $row['client'];
		$finalrow['service'] = $row['service'];
		$finalrow['hours'] = $row['hours'];
		$finalrow['pay'] = $row['pay'];
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