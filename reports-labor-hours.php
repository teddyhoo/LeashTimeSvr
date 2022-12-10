<? // reports-labor-hours.php // started 2/23/2021 based on reports-labor-hours.php

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

		
$pageTitle = "Labor Hours Report";


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
if(mattOnlyTEST()) {
	require_once "field-utils.php";
	foreach(explode(',', 'Month-2,Last Month,This Month') as $intervalLabel) {
		list($bStart, $bEnd, $bMonth) = dateIntervalFromLabel($intervalLabel);
		echo " ";
		echoButton('', $bMonth, "setIntervalAndGenerate(\"$bStart\", \"$bEnd\")");
	}
}	
	hiddenElement('csv', '');
?>
	</td></tr>
	<tr><td colspan=2>
<?
//$options = array('All Sitters'=>-1);
//$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), providerid FROM tblprovider ORDER BY lname, fname"));
$options = getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=true);
$options = array_reverse($options);
$options['All Sitters'] = -1;
$options = array_reverse($options);

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
	
	function setIntervalAndGenerate(start, end) {
		document.getElementById('start').value = start;
		document.getElementById('end').value = end;
		genReport();
	}
	
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
			openConsoleWindow('reportprinter', 'reports-labor-hours.php?print=1&start='+start+'&end='+end+'&provider='+provider, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Labor-Hours.csv ");
	dumpCSVRow($pageTitle);
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = $pageTitle;
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Labor Hours Report</h2>";
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
	
	$wages = fetchHours($start, $end, $provider);
//echo ">>>";print_r($allPayments);	
	
	if($csv) hoursCSV($start, $end, $wages);
	else hoursTable($start, $end, $wages);
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

function fetchHours($start, $end, $providerid) {
	global $allHours, $allVisitCounts, $allReportedHours;
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
		
		$appt['time'] = strtotime(trim("{$appt['date']} {$appt['starttime']}"));
		$allHours[$prov] += $hours;
		if(($appt['reportedhours'] = reportedVisitDurationInHours($appt['appointmentid'])) > 0) {
			$appt['reportedhoursandminutes'] = date('H:i', strtotime('today midnight')+($appt['reportedhours'] * 3600));
			$allReportedHours[$prov] += $appt['reportedhours'];
			$appt['reportedhours'] = number_format($appt['reportedhours'], 2);
			
		}
		else $appt['reportedhours'] = '--';
		$allVisitCounts[$prov] += 1;
		$rows[] = $appt;
	}
	usort($rows, 'wageSort');	
	return $rows;
}

function reportedVisitDurationInHours($apptid) {
	$tracks = fetchKeyValuePairs(
		"SELECT event, date 
			FROM tblgeotrack 
			WHERE appointmentptr = $apptid
				AND event IN ('completed', 'arrived')");
	if(!$arrived = $tracks['arrived']) return -1;
	if(!($completed = $tracks['completed'])) {
		$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $apptid LIMIT 1");
		$completed = $tracks['completed'] ? $tracks['completed'] : $appt['completed'];
	}
	return (strtotime($completed) - strtotime($arrived)) / 3600;
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
	

function hoursTable($start, $end, $rows) {
	global $provs, $allHours, $allReportedHours, $allVisitCounts;
	$columns = explodePairsLine('date|Date||timeofday|Time||client|Client||service|Service||hours|Nom. Hours||reportedhours|Rep. Hours');
	$numCols = count($columns);
	$colClasses = array('pay' => 'dollaramountcell', 'travelallowance' => 'dollaramountcell'); 
	$headerClass = array('pay' => 'dollaramountheader', 'travelallowance' => 'dollaramountheader'); //'dollaramountheader'

	echo "<style>.topline {border-top:solid black 1px;}</style>";
	$prov = -1;
	foreach((array)$rows as $i => $row) {
//echo print_r($rows[$i], 1)."<br>";		
		$rows[$i]['date'] = shortDate(strtotime($rows[$i]['date']));
		if(is_numeric($row['reportedhours'])) {
			$style = 'style="text-decoration-line:underline;text-decoration-style:dotted;"';
			$rows[$i]['reportedhours'] = "<span  $style title='{$row['reportedhoursandminutes']}'>".number_format($row['reportedhours'], 2)."</span>";
		}
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
			$summary = "<span class='fontSize1_2em'><b>{$provs[$prov]}</b></span> Visit count: {$allVisitCounts[$prov]} Total Nominal hours: {$allHours[$prov]} Total Reported Hours: ".number_format($allReportedHours[$prov], 2);
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
	global $allVisitCounts, $allHours, $allReportedHours;
	$rowClass = 'futuretask';
	$visitCount = $allVisitCounts[$prov];
	$visitCount = !$visitCount ? 'No' : $visitCount;
	$visitCount = "($visitCount visit".($visitCount == 1 ? '' : 's').")";
	$rowClasses[] = $rowClass;
	$finalrows[] = 
		array('service'=>"<b>Total Hours $visitCount</b>", 
					'hours'=>"<b>{$allHours[$prov]}</b>", 'reportedhours'=>"<b>".number_format($allReportedHours[$prov], 2)."</b>"); 
	$rowClasses[] = $rowClass;
}

function hoursCSV($start, $end, $rows) {
	global $provs;
	$columns = explodePairsLine('provider|Sitter||date|Date||timeofday|Time||client|Client||service|Service||hours|Nominal Hours||reportedhours|Reported Hours');
	dumpCSVRow($columns);
	foreach($rows as $row) {
		$finalrow['provider'] = $provs[$row['providerptr']];
		$finalrow['date'] = shortDate(strtotime($row['date']));
		$finalrow['timeofday'] = $row['timeofday'];
		$finalrow['client'] = $row['client'];
		$finalrow['service'] = $row['service'];
		$finalrow['hours'] = $row['hours'];
		$finalrow['reportedhours'] = $row['reportedhours'];
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