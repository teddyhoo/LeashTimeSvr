<? // reports-revenue.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "projections.php";
require_once "service-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,csv', $_REQUEST));

		
$pageTitle = "Revenue Report by Service Type";

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<span>Generate Revenue and Commission Report for all uncanceled appointments.  Revenues do not include taxes or refunds.</span><p>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('For the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	hiddenElement('csv', '');
	echo "&nbsp;";
	
	if(TRUE) {
		$firstDayThisMonthInt = strtotime(date('Y-m-01'));
		echo " ";
		$startM = shortDate(strtotime(date("Y-m-01", strtotime("-1 month", $firstDayThisMonthInt))));
		$endM = shortDate(strtotime(date("Y-m-t", strtotime("-1 month", $firstDayThisMonthInt))));
		$monthLabel = date('M', strtotime($startM));
		fauxLink($monthLabel, "setRange(\"$startM\", \"$endM\")");

		echo " - ";
		$startM = shortDate(strtotime(date("Y-m-01")));
		$endM = shortDate(strtotime(date("Y-m-t")));
		$monthLabel = date('M', strtotime($startM));
		fauxLink($monthLabel, "setRange(\"$startM\", \"$endM\")");
			
		echo " - ";
		$startM = shortDate(strtotime(date("Y-m-01", strtotime("+1 month", $firstDayThisMonthInt))));
		$endM = shortDate(strtotime(date("Y-m-t", strtotime("+1 month", $firstDayThisMonthInt))));
		$monthLabel = date('M', strtotime($startM));
		fauxLink($monthLabel, "setRange(\"$startM\", \"$endM\")");

		
	}
?>
	</td><tr>
<?
	radioButtonRow('Organized:', 'reportType', ($reportType ? $reportType : 'monthly'), array('by month'=>'monthly', 'by service type'=>'service'), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
?>
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()");
	
	if(mattOnlyTEST()) echo " ".fauxLink('Hide Detail Lines', 'toggleDetailLines(this)', 1, 'Show only summary lines.');
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
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-revenue.php?print=1&start='+start+'&end='+end+'&reportType='+reportType, 700,700);
		}
	}
	
	function setRange(start, end) {
		document.getElementById('start').value = start;
		document.getElementById('end').value = end;
		genReport();
	}

<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Revenue-by-Service.csv ");
	$primarySort = $reportType == 'monthly' ? 'Monthly' : 'Service Type';
	dumpCSVRow("Revenue Report by Service Type with $primarySort Subtotals");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Revenue Report by Service Type';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Revenue Report by Service Type</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
$includeRevenueFromSurcharges  = true;
if($start && $end) {
	revenuesAndCommissions($start, $end);
//if(mattOnlyTEST()) print_r($actualRevenues);	
if(TRUE) refunds($start, $end, $clients=null, $withReason=null);
	dropProjectionApptTable();
	dropProjectionSurchTable();
	
	if($reportType == 'monthly') commissionsAndRevenuesByMonthTable($start, $end, "Service Types", true, null, 'sortSubCategories', $csv);
	else if($reportType == 'service') commissionsAndRevenuesByServiceTable($start, $end, $csv);
	else if($reportType == 'zip') commissionsAndRevenuesByZIPTable($start, $end);
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	echo "<script language='javascript'>
	function toggleDetailLines(el) {
		if(el.innerHTML == 'Hide Detail Lines') {
			el.innerHTML = 'Show Detail Lines';
			el.title = 'Show all details.';
		}
		else {
			el.innerHTML = 'Hide Detail Lines';
			el.title = 'Show only summary lines.';
		}
		$('.reportdetail').toggle();
	}
	</script>";
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