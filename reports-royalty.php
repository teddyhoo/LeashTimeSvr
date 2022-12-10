<? // reports-royalty.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "projections.php";
require_once "service-fns.php";

$failure = false;
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType', $_REQUEST));
if($print) $auxiliaryWindow = true; // prevent login from appearing here if session times out

		
$pageTitle = "Royalty Report";

$royalty = $_SESSION['preferences']['franchiseRoyaltyRate'] / 100.0;

// ###### SUMMARY
$date = date('Y-m-d', strtotime('yesterday'));
$dataYesterday = fetchOneDayRevenues($date);
$dataThisMonth = fetchOneMonthRevenues($date);
$dateLastYear = shortDate(strtotime("- 1 year", strtotime($date)));
$dataThisDayLastYear = fetchOneDayRevenues($dateLastYear);
$lastDay = shortDate(strtotime("- 1 year", strtotime($dataThisMonth['end'])));
$dataThisMonthLastYear = fetchOneMonthRevenues($dateLastYear, $lastDay);
$noData = '';

ob_start();
ob_implicit_flush(0);
?>
<table width=80% align=center>
<tr>
<? 
$n = $dataYesterday['revenue'];
$b = $dataThisDayLastYear['revenue'];
?>
<td>Total Revenue Yesterday: <?= dollarAmount($n) ?> <?= $b ? "(".percentage($n / $b - 1).')' : $noData  ?></td>
<? 
$n = $dataYesterday['visits'];
$b = $dataThisDayLastYear['visits'];
?>
		<td>Total Visits Yesterday: <?= $n ?> <?= $b ? "(".percentage($n / $b - 1).')' : $noData  ?></td>
</tr>

<tr>
<? 
$n = $dataThisMonth['revenue'];
$b = $dataThisMonthLastYear['revenue'];
?>
<td>Total Revenue This Month to Date: <?= dollarAmount($n) ?> <?= $b ? "(".percentage($n / $b - 1).')' : $noData  ?></td>
<? 
$n = $dataThisMonth['visits'];
$b = $dataThisMonthLastYear['visits'];
?>
		<td>Total Visits This Month to Date: <?= $n ?> <?= $b ? "(".percentage($n / $b - 1).')' : $noData  ?></td>
</tr>

<tr>
<? 
$n = $dataYesterday['revenue']*$royalty;
$b = $dataThisDayLastYear['revenue']*$royalty;
?>
<td>Net Royalty Yesterday: <?= dollarAmount($n) ?> <?= $b ? "(".percentage($n / $b - 1).')' : $noData  ?></td>
<? 
$n = $dataThisMonth['revenue']*$royalty;
$b = $dataThisMonthLastYear['revenue']*$royalty;
?>
		<td>Net Royalty This Month to Date: <?= dollarAmount($n) ?> <?= $b ? "(".percentage($n / $b - 1).')' : $noData  ?></td>
</tr>
</table>
<?
$summary = ob_get_contents();
ob_end_clean();

// ###### END SUMMARY

if(!$print) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	echo "$summary<p>";
	// ***************************************************************************
?>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('For the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
?>
	</td><tr>
	</table><p>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	//echoButton('', 'Export to Excel', "alert(\"Coming soon...\")");
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
				'start', 'NOT', 'isFutureDate',
				'end', 'NOT', 'isFutureDate',
				'start','end','datesInOrder')) document.reportform.submit();
	}
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm(
				'start', 'end', 'inseparable',
				'start', 'NOT', 'isFutureDate',
				'end', 'NOT', 'isFutureDate')) {
			var start = '';
			var end = '';
			if(document.getElementById('start').value) {
				start = escape(document.getElementById('start').value);
				end = escape(document.getElementById('end').value);
			}
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-royalty.php?print=1&start='+start+'&end='+end+'&reportType='+reportType, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else {
	$windowTitle = 'Royalty Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Royalty Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "$summary<p>";
	if($start) echo "<p style='text-align:center'>Period: $start - $end</p>";
	else echo "No date rage specified.";
}
if($start && $end) {
	if(!$print) {
		echo "<hr>";
		echo "<center>Report Generated ".shortDateAndTime('now', 'mil')."</center>";
		if($start) echo "For the date range: ".shortDate(strtotime($start))." to ".shortDate(strtotime($end)).':<p>';
	}
	
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$periodDataThisYear = fetchRevenuesInRange($start, $end);
	$startLastYear = shortDate(strtotime("- 1 year", strtotime($start)));
	$endLastYear = shortDate(strtotime("- 1 year", strtotime($periodDataThisYear['end'])));
	$periodDataLastYear = fetchRevenuesInRange($startLastYear, $endLastYear);
	
	$n = $adjustedRevenue = $periodDataThisYear['revenue']; // * (1 - $royalty);
	$b = $adjustedRevenueLastYear = $periodDataLastYear['revenue']; // * (1 - $royalty);
	
	$tar = dollarAmount($adjustedRevenue);

if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { $tar = fauxLink($tar, "drillDown(\"$start\", \"$end\")", true); }
	echo "<table width=50%>";
	echo "<tr><th>Total Adjusted Revenue</th><th>Royalty Rate</th><th>Royalty Due</th>";
	echo "<tr>";
	echo "<td>$tar".($b ? "(".percentage($n / $b - 1).')' : $noData)."</td>";
	echo "<td>".($royalty * 100)." %</td>";
	echo "<td>".dollarAmount($royalty * $periodDataThisYear['revenue'])."</td>";
	echo "</tr>";
	echo "</table>";
}
if(!$print){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function drillDown(start, end) {
	document.location.href='reports-royalty-drilldown.php?start='+start+'&end='+end;
	//openConsoleWindow('drilldown', 'reports-royalty-drilldown.php?start='+start+'&end='+end,800,800);
}
</script>
<?
// ***************************************************************************
	include "frame-end.html";
}
else {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
}

function sortnameSort($a, $b) {	
	global $providerSorts;
 	return strcmp($providerSorts[$a], $providerSorts[$b]); 
}

function percentage($num) {
	return sprintf("%d", $num)." %";
}
