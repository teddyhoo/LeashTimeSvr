<? // reports-discounts-client.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "discount-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,sort,csv', $_REQUEST));

		
$pageTitle = "Discount Report by Client";

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<span>Generate Report on discounts for all clients in the stated period.</span><p>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('For the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	hiddenElement('csv','');
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
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-discounts-client.php?print=1&sort=<?= $sort ?>&start='+start+'&end='+end, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Discount-Report-by-Client.csv");
	dumpCSVRow("Discount Report by Client");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Discount Report by Client';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Discount Report by Client</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	if($csv) {
		discountDataCSV($start, $end);
		exit;
	}
	else discountDataTable($start, $end);
}
if(!$print){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
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
function discountDataCSV($start, $end) {
	$rows = discountDataRows($start, $end);
	if(!$rows) echo "No Discounts found.";
	else {
		foreach($rows as $row) $total += $row['amount'];
		echo "Total discounts for period: ".dollarAmount($total, $cents=true, $nullRepresentation='', $nbsp=' ')."\n";
		$columns = explodePairsLine('client|Client||categories|Discount Types||amount|Total');
		dumpCSVRow($columns);
		foreach($rows as $row) 
			dumpCSVRow($row);
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

function discountDataTable($start, $end) {
	$rows = discountDataRows($start, $end);
	if(!$rows) echo "No Discounts found.";
	else {
		foreach($rows as $i => $row) {
			$total += $row['amount'];
			$rows[$i]['amount'] = dollarAmount($rows[$i]['amount']);
		}
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=3 class='dollaramountcell'><span style='font-weight: bold;'>Total: </span>"
										.dollarAmount($total)."</td></tr>");
		$columns = explodePairsLine('name|Client||categories|Discount Types||amount|Total');
		$columnSorts = array('name'=>'asc','amount'=>null);
		$colClasses = array('amount'=>'dollaramountcell');
		$headerClasses = array('amount'=>'dollaramountheader_right', 'name'=>'sortableListHeader', 'categories'=>'sortableListHeader');

		tableFrom($columns, $rows, 'WIDTH=75%', null, null, null, null, $columnSorts, $rowClasses=null, $colClasses, 'sortTable');
	}
}
	
function discountDataRows($start, $end) {
	$discounts = discountsByClient($start, $end);
	$rows = array();
	if($discounts) {
		foreach(getDiscounts() as $cat)
			$discountCats[$cat['discountid']] = $cat['label'];
		$clients = getClientDetails(array_keys($discounts), $additionalFields=array('sortname'), $sorted=true);
		if(isset($sort)) {
			$sort_key = substr($sort, 0, strpos($sort, '_'));
			$sort_dir = strtoupper(substr($sort, strpos($sort, '_')+1));
			if($sort_key == 'name') uasort($discounts, 'compareNames');
			else if($sort_key == 'amount') uasort($discounts, 'compareAmounts');
			if($sort_dir == 'DESC') $discounts = array_reverse($discounts, $preserve_keys=true);
		}
		foreach($discounts as $clientid => $summary) {
			$cats = array();
			foreach(array_unique($summary['cats']) as $cat) $cats[] = $discountCats[$cat];
			$rows[] = array('name'=>$clients[$clientid]['clientname'], 'categories'=>join(', ', $cats), 'amount'=>$summary['amount']);
			$total += $summary['amount'];
		}
		return $rows;
	}
}

function compareNames($a, $b) {
	return strcmp($a['sortname'], $b['sortname']);
}
	
function compareAmounts($a, $b) {
	return $a['amount'] > $b['amount'] ? -1
				  : ($a['amount'] < $b['amount'] ? 1
				  : compareNames($a, $b));
}
	
function discountsByClient($start, $end) {
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$result = doQuery("SELECT relapptdiscount.clientptr, discountptr, amount, appointmentptr
					FROM relapptdiscount
					LEFT JOIN tblappointment ON appointmentptr = appointmentid
					WHERE date >= '$start' AND date <= '$end'");
  if($result) while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
    $discounts[$row['clientptr']]['amount'] += $row['amount'];
    $discounts[$row['clientptr']]['clientptr'] = $row['clientptr'];
    $discounts[$row['clientptr']]['cats'][] = $row['discountptr'];
	}
	return $discounts;
}
?>
<script language='javascript'>
var start = '<?= date('Y-m-d', strtotime($start)); ?>';
var end = '<?= date('Y-m-d', strtotime($end)); ?>';
function sortTable(sortKey, direction) {
	document.location.href="reports-discounts-client.php?sort="+sortKey+"_"+direction+"&start="+start+"&end="+end;
}
</script>