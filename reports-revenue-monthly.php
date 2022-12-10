<? // reports-revenue-monthly.php
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

		
$pageTitle = "Revenue from Monthly Contracts";

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<span>Generate Revenue Report for all monthly contracts.  Revenues do not include taxes.</span><p>
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

	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()");
	echo "&nbsp;";
	echoButton('', 'Visit Counts by Service Type', "visitCounts()");  // published 4/4/20017
?>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript' src='ajax_fns.js'></script>
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
			openConsoleWindow('reportprinter', 'reports-revenue-monthly.php?print=1&start='+start+'&end='+end, 700,700);
		}
	}
	
	function visitCounts() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			var start = escape(document.getElementById('start').value);
			var end = escape(document.getElementById('end').value);
			openConsoleWindow('visitcounts', 'reports-monthly-visit-counts.php?start='+start+'&end='+end, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Monthly-Revenue-Report.csv ");
	dumpCSVRow('Revenue Report from Monthly Contracts');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Revenue from Monthly Contracts';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Revenue from Monthly Contracts</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	$includeRevenueFromSurcharges  = true;
	monthlyRevenuesAndCommissionsByClient($start, $end);
	
	
//	global $refunds, $revenues, $commissions, $actualRevenues, $totalRevenue, $totalCommission, $totalActualRevenue, $clientVisits;
//print_r($actualRevenues);
	
	
	// refundsForClients deducts refunds from totalActualRevenue and totalRevenue.
	// add it back in and note the refunds later
	$refundTotal = refundsForClients($start, $end);
	$totalActualRevenue += $refundTotal;
	$totalRevenue += $refundTotal;

	dropProjectionApptTable();
	dropProjectionSurchTable();
	$totalMonthlyRevenue += array_sum($revenues);
	if($totalMonthlyRevenue && !$csv) echo "<p>Total Revenue: ".dollarAmount($totalMonthlyRevenue);
	else if(!$totalMonthlyRevenue) {
		if($csv) echo "No Revenue found.";
		else echo "<p>No Revenue found.";
	}

	if($totalMonthlyRevenue) {
		if($csv) commissionsAndRevenuesByClientCSVRows($start, $end, $showCommissions=false, $showClientDetails=true, $omitServiceCounts=true);
		else  {
			if($refundTotal) echo "<p class='tiplooks'>The total does not reflect the ".dollarAmount($refundTotal)." in refunds during the period.</p>";
			commissionsAndRevenuesByClientTable($start, $end, false);
		}
	}
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}
else if($print) {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
}