<? // reports-gratuity.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,csv', $_REQUEST));

		
$pageTitle = "Gratuities Report";


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
	</td><tr>
<?
	radioButtonRow('Organized:', 'reportType', ($reportType ? $reportType : 'monthly'), array('by month'=>'monthly', 'by sitter'=>'provider'), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
?>
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV(1)");
if(staffOnlyTEST() || dbTEST('tonkapetsitters')) {	
	echo "&nbsp;";
	echoButton('', 'Download Detailed Spreadsheet', "genCSV(\"detailed\")");
}
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
	function genCSV(detailed) {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			document.getElementById('csv').value=detailed;
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
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-gratuity.php?print=1&start='+start+'&end='+end+'&reportType='+reportType, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv == 'detailed') {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Detailed-Client-Revenue-Report.csv ");
	dumpCSVRow('Detailed Gratuities Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Revenue-Report.csv ");
	dumpCSVRow('Gratuities Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Gratuities Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Gratuities Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	fetchGratuities($start, $end, ($csv == 'detailed'), ($reportType == 'monthly'));
//echo ">>>";print_r($allGratuities);	
	
	if($reportType == 'monthly') gratuitiesByMonthTable($start, $end, $csv);
	else if($reportType == 'provider') gratuitiesByProviderTable($start, $end, $csv);
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}
else if($print){
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
}

function fetchGratuities($start, $end, $detailed=false, $monthly=false) {

	global $allGratuities, $totalGrats, $providerSorts;
	$allGratuities = array();
	$providerSorts = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$orderBy = $monthly ? "issuedate, sortname" : "sortname, issuedate";
	$sql = "SELECT tblgratuity.*, CONCAT_WS(' ', p.fname, p.lname) as provider, 
						CONCAT_WS(', ', p.lname, p.fname) as sortname,
						CONCAT_WS(' ', c.fname, c.lname) as clientname
					FROM tblgratuity 
					LEFT JOIN tblprovider p ON providerid = providerptr
					LEFT JOIN tblclient c ON clientid = clientptr
					WHERE issuedate >= '$start' AND issuedate <= '$end 23:59:59'
					ORDER BY $orderBy";
	$result = doQuery($sql);
  while($grat = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$totalGrats += ($amt = $grat['amount']);
		$provider = $grat['provider'];
		$providerSorts[$grat['provider']] = $grat['sortname'];
		if($detailed) $allGratuities[] = $grat;
		else $allGratuities[substr($grat['issuedate'], 0, 7)][$provider] += $amt;
	}
	
	return $allGratuities;
}

function sortnameSort($a, $b) {	
	global $providerSorts;
 	return strcmp($providerSorts[$a], $providerSorts[$b]); 
}

function OLDgratuitiesByProviderTable($start, $end, $csv=false) {
	global $allGratuities, $totalGrats;
	$temp = $allGratuities;
	$allGratuities = array();
	foreach($temp as $monthYear => $grats) {
		foreach($grats as $secondaryKey => $amount) {
			$allGratuities[$secondaryKey][$monthYear] += $amount;
		}
	}
	uksort($allGratuities, 'sortnameSort');

	$columns = explodePairsLine('category| ||gratuities|Gratuities');
	$colClasses = array('gratuities' => 'dollaramountcell'); 
	$headerClass = array('gratuities' => 'dollaramountheader'); //'dollaramountheader'
	$row = array('gratuities' => dollarAmount($totalGrats));
	$row['category'] = 'All Sitters, Date Range: '.shortDate(strtotime($start)).' to '.shortDate(strtotime($end));
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	foreach($allGratuities as $secondaryKey => $grats) {
		$row = array('category' => $secondaryKey);
		$row['gratuities'] = dollarAmount(array_sum($grats));
		$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$rows[] = $row;
		foreach($grats as $monthYear => $amount) {
			$row = array('category' => date('F Y', strtotime("$monthYear-01")));
			$row['gratuities'] = dollarAmount($amount);
			$rows[] = $row;
		}
	}
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
}

function gratuitiesByProviderTable($start, $end, $csv=false) {
	if($csv == 'detailed') return gratuityDetailsByProviderCSV($start, $end);
	else if($csv) return gratuitiesByProviderCSV($start, $end);
	$rows = gratuitiesByProviderData($start, $end, $csv);
	$columns = explodePairsLine('category| ||gratuities|Gratuities');
	$colClasses = array('gratuities' => 'dollaramountcell'); 
	$headerClass = array('gratuities' => 'dollaramountheader'); //'dollaramountheader'
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
}

function gratuitiesByProviderCSV($start, $end) {
	$rows = gratuitiesByProviderData($start, $end);
	$columns = explodePairsLine('category| ||gratuities|Gratuities');
	dumpCSVRow($columns);
	foreach($rows as $i => $row) {
		unset($row['#ROW_EXTRAS#']);
		dumpCSVRow($row);
	}
}

function gratuityDetailsByProviderCSV($start, $end) {
	global $allGratuities;
	$columns = explodePairsLine('issuedate|Date||provider|Sitter||clientname|Client||amount|Gratuity');
	dumpCSVRow($columns);
	foreach($allGratuities as $i => $row) {
		$row['issuedate'] = shortDate(strtotime($row['issuedate']));
		unset($row['#ROW_EXTRAS#']);
		dumpCSVRow($row, array_keys($columns));
	}
}

function gratuitiesByProviderData($start, $end) {
	global $allGratuities, $totalGrats;
	$temp = $allGratuities;
	$allGratuities = array();
	foreach($temp as $monthYear => $grats) {
		foreach($grats as $secondaryKey => $amount) {
			$allGratuities[$secondaryKey][$monthYear] += $amount;
		}
	}
	uksort($allGratuities, 'sortnameSort');

	$row = array('category' => 'All Sitters, Date Range: '.shortDate(strtotime($start)).' to '.shortDate(strtotime($end)));
	$row['gratuities'] = $totalGrats;
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	foreach($allGratuities as $secondaryKey => $grats) {
		$row = array('category' => $secondaryKey);
		$row['gratuities'] = array_sum($grats);
		$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$rows[] = $row;
		foreach($grats as $monthYear => $amount) {
			$row = array('category' => date('F Y', strtotime("$monthYear-01")));
			$row['gratuities'] = $amount;
			$rows[] = $row;
		}
	}
	return $rows;
}


function gratuitiesByMonthTable($start, $end, $csv=false) {
	if($csv == 'detailed') return gratuityDetailsByProviderCSV($start, $end);
	else if($csv) return gratuitiesByMonthCSV($start, $end);
	$rows = gratuitiesByMonthData($start, $end);
	$columns = explodePairsLine('category| ||gratuities|Gratuities');
	$colClasses = array('gratuities' => 'dollaramountcell'); 
	$headerClass = array('gratuities' => 'dollaramountheader'); //'dollaramountheader'
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);
}

function gratuitiesByMonthCSV($start, $end) {
	$rows = gratuitiesByMonthData($start, $end);
	$columns = explodePairsLine('category| ||gratuities|Gratuities');
	dumpCSVRow($columns);
	foreach($rows as $i => $row) {
		unset($row['#ROW_EXTRAS#']);
		dumpCSVRow($row);
	}
}
function gratuitiesByMonthData($start, $end) {
	global $allGratuities, $totalGrats;
	$row = array('category' => 'All Sitters, Date Range: '.shortDate(strtotime($start)).' to '.shortDate(strtotime($end)));
	$row['gratuities'] = $totalGrats;
	$row['#ROW_EXTRAS#'] = "style='background:lightgreen;'";
	$rows[] = $row;
	foreach($allGratuities as $monthYear => $grats) {
		uksort($grats, 'sortnameSort');
		$row = array('category' => date('F Y', strtotime("$monthYear-01")));
		$row['gratuities'] = array_sum($grats);
		$row['#ROW_EXTRAS#'] = "style='background:lightblue;'";
		$rows[] = $row;
		foreach($grats as $secondaryKey => $amount) {
			$row = array('category' => $secondaryKey);
			$row['gratuities'] = $amount;
			$rows[] = $row;
		}
	}
	return $rows;
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

