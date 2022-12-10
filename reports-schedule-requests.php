<? // reports-schedule-requests.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "client-flag-fns.php";
require_once "js-gui-fns.php";


// Determine access privs
$locked = locked('o-');

extract(extractVars('csv,start,end', $_REQUEST));

$pageTitle = "LeashTime Schedules Report by Date";

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

	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	//echoButton('', 'Print Report', "spawnPrinter()");
	//echo "&nbsp;";
	//echoButton('', 'Download Spreadsheet', "genCSV()");
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
				'end', 'NOT', 'isFutureDate')) document.reportform.submit();
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
				'start', 'NOT', 'isFutureDate',
				'end', 'NOT', 'isFutureDate')) {
			var start = escape(document.getElementById('start').value);
			var end = escape(document.getElementById('end').value);
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-tax-liability.php?print=1&start='+start+'&end='+end, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
/*else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Prospects by Date.csv ");
	dumpCSVRow('Prospects by Date');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Prospects by Date';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Prospects by Date</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
*/

if($start && $end) {
	$achs = $achs ? $achs : array(0);
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));


	// Date / Time	Action	Amount	Type	Client	Account	Transaction	User
	$sort = $byclient ? 'sortname ASC' : 'issuedate ASC';
	$sql =
		"SELECT r.*
			FROM tblclientrequest r
			WHERE requesttype = 'Schedule' 
				AND received >= '$start 00:00:00' AND received <= '$end 23:59:59'
			ORDER BY received ASC";
	$schedulerequests = fetchAssociations($sql, 1);
}

if(!$csv){
	echo "<p>One schedule per line. Format: clientid*schedule.<p>Schedule format: <ul><li>Asterisk (*) separates days. Consecutive asterixes indicate empty days.<li>Pipe (|) separates visits.<li>Visit: servicetype#time#pets</ul></p>";
	foreach($schedulerequests as $r) {
		$note = explode("\n", $r['note']);
		$schedule = str_replace('<>', '*', $note[1]);
		echo "{$r['clientptr']}*$schedule<br>";
	}
}
else if($csv) {
	dumpCSVRow(array_keys($columns));
	foreach($days as $date)
		dumpCSVRow($date, array_keys($columns));
}
if(!$print & !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}
else if(!$csv) {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
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

