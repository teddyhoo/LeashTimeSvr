<? // reports-tax-collected.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "projections.php";
require_once "service-fns.php";
require_once "credit-fns.php";
require_once "reports-archive-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,csv', $_REQUEST));

		
$pageTitle = "Tax Collected Report by Client";

$allowArchiving = staffOnlyTEST();

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	if($allowArchiving) $breadcrumbs .= " - <a href='reports-archive.php?type=tax-collected&label=Tax Collected'>Tax Collected Reports Archive</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<span>A Report for payments in the stated period.</span><p>
	The period specified period cannot extend past today.<p>
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
	echo "&nbsp;";
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
			openConsoleWindow('reportprinter', 'reports-tax-collected.php?print=1&start='+start+'&end='+end, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Tax-Collected-By-Client.csv ");
	dumpCSVRow('Tax Collected By Client');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Tax Collected Report by Client';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Tax Liability Report by Client</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}

function dateCmp($a, $b) { return strcmp($a['date'], $b['date']); }

function paymentSpeculation($clientptr, $availablecredit) {
	require_once "tax-fns.php";
	$alloc = array();
	$startingcredit = $availablecredit;
	$incomplete = fetchAssociations(
		"SELECT *, a.charge+IFNULL(adjustment, 0) as charge, a.clientptr
			FROM tblappointment a
			LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment' AND superseded = 0
			WHERE a.clientptr = $clientptr 
				AND canceled IS NULL
				AND completed IS NULL
				AND a.charge+IFNULL(adjustment, 0) > 0 
				AND (billableid IS NULL OR paid = 0)", 1); // allow for dedicated payments
	$surcharges = fetchAssociations(
		"SELECT *, s.charge as charge, s.clientptr
			FROM tblsurcharge s
			LEFT JOIN tblbillable ON itemptr = surchargeid AND itemtable = 'tblsurcharge' AND superseded = 0
			WHERE s.clientptr = $clientptr 
				AND canceled IS NULL
				AND completed IS NULL
				AND s.charge > 0 
				AND (billableid IS NULL OR paid = 0)", 1); // allow for dedicated payments
	foreach($surcharges as $surcharge) $incomplete[] = $surcharge;
	usort($incomplete, 'dateCmp');
//if($clientptr == 816)	echo "[$clientptr] incomplete: ".count($incomplete)." start $startingcredit - avail $availablecredit<br>";
	foreach($incomplete as $item) {
		if($availablecredit <= 0) break;
		$itemtax = $item['appointmentid']
			? figureTaxForAppointment($item, ($item['recurringpackage'] ? 'R' : 'N')) 
			: figureTaxForSurcharge($item);
		$item['originalcharge'] += $item['charge'];
		$item['charge'] += $itemtax;
//if($clientptr==815) echo "<p>TAX $clientptr: ".print_r($item, 1);		
		$applied = min($availablecredit, $item['charge']);
		$availablecredit -= $applied;
//if($clientptr==816) echo "<p>APPLIED: $applied<br>";		
		$alloc['items'] += 1;
//if($item['clientptr'] == 818) echo print_r($item, 1)."<hr>";
		$alloc['total'] += $applied;
		if($applied == $item['charge']) {
			$tax = $itemtax;
//if($clientptr==815) echo "<hr>CHARGE $clientptr: {$item['originalcharge']} tax: $tax";
		}
		else {
			$rate = $itemtax / $item['originalcharge'];
			$partialcharge = $applied / (1 + $rate);
			$tax = $partialcharge * $rate;
//if($clientptr==817) echo "<hr>RATE $clientptr: $rate partialcharge: $partialcharge tax: $tax";		
		}
		$alloc['tax'] += $tax;
		$alloc['revenue'] += $applied - $tax;
	}
//if($clientptr == 816)	echo "[$clientptr] start $startingcredit - avail $availablecredit<br>";
	$alloc['unknown'] = $startingcredit - ($alloc['tax'] + $alloc['revenue']);
	$alloc['total'] = $startingcredit;
//if($clientptr==815) echo "<hr>SPEC $clientptr: ".print_r($alloc, 1);		
	return $alloc;
}

if($start && $end) {
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	$payments = fetchAssociations(
		"SELECT clientptr, creditid 
			FROM tblcredit 
			WHERE issuedate >= '$pstart' AND issuedate <= '$pend' AND payment=1 AND voided IS NULL", 1);
	$byClient = array();
	foreach($payments as $payment) $byClient[$payment['clientptr']][] = $payment['creditid'];
	foreach($byClient as $clientptr => $paymentptrs) {
		foreach($paymentptrs as $paymentptr) {
			$payment = fetchFirstAssoc("SELECT * FROM tblcredit WHERE creditid = $paymentptr LIMIT 1", 1);
			$breakdown = paymentAllocation($paymentptr);
			$byClient[$clientptr]['unapplied'] += ($payment['amount'] - $breakdown['total']);
			$byClient[$clientptr]['total'] += $breakdown['total'];
			$byClient[$clientptr]['tax'] += $breakdown['tax'];
			$byClient[$clientptr]['revenue'] += $breakdown['revenue'];
			$byClient[$clientptr]['items'] += $breakdown['items'];			
			$byClient[$clientptr]['payments'] += 1;
		}
		$byClient[$clientptr]['taxrate'] = $byClient[$clientptr]['total']
			? $byClient[$clientptr]['tax'] / $byClient[$clientptr]['total']
			: 0;
		$byClient[-999]['revenue'] +=  $byClient[$clientptr]['revenue'];
		$byClient[-999]['tax'] +=  $byClient[$clientptr]['tax'];
	}
	// foreach client with unapplied payment, speculate what it *might* be spent on
	foreach($byClient as $clientptr => $summary) {
		$byClient[$clientptr]['actualtotal'] = $byClient[$clientptr]['total'];
		$byClient[$clientptr]['actualtax'] = $byClient[$clientptr]['tax'];
		$byClient[$clientptr]['actualrevenue'] = $byClient[$clientptr]['revenue'];
		$byClient[$clientptr]['actualitems'] = $byClient[$clientptr]['items'];
		$speculation = paymentSpeculation($clientptr, $summary['unapplied']);
		$byClient[$clientptr]['total'] += $speculation['total'];
		$byClient[$clientptr]['unknown'] += $speculation['unknown'];
		$byClient[$clientptr]['tax'] += $speculation['tax'];
		$byClient[$clientptr]['revenue'] += $speculation['revenue'];
		$byClient[$clientptr]['items'] += $speculation['items'];
		$byClient[-999]['revenue'] +=  $speculation['revenue'];
		$byClient[-999]['tax'] +=  $speculation['tax'];
		$byClient[$clientptr]['taxrate'] = $byClient[$clientptr]['total'] 
			? $byClient[$clientptr]['tax'] / $byClient[$clientptr]['total'] 
			: 0;
	}
//if(mattOnlyTEST()) echo "ITEMS: [{$byClient[818]['items']}] <hr>";

	//print_r($byClient);
	ob_start();
	ob_implicit_flush(0);
	if(!$byClient) echo "<p>No information to report.";
	else  taxesCollectedByClientTable($byClient);
	$body = ob_get_contents();
	ob_end_clean();
	
	echo $body;
	if($byClient && $allowArchiving) {
		$range = shortDate(strtotime($pstart)).'-'.shortDate(strtotime($pend));
		archiveReport('tax-collected', "Tax Collected $range", array('start'=>$pstart, 'end'=>$pend), $body);
	}
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