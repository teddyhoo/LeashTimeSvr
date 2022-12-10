<? // reports-balances-internal.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "projections.php";
require_once "service-fns.php";
require_once "invoice-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,clients,sort,csv', $_REQUEST));

if(!$start) $start = '1/1/2010';
$pstart = date('Y-m-d', strtotime($start));
if(!$end) $end = date('m/d/Y');
$pend = date('Y-m-d', strtotime($end));
		
$pageTitle = "Account Balance INTERNAL Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
?>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<? /*
	calendarSet('For the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	*/
?>
	</td></tr>
	<tr><td colspan=2>
<?
$options = array('-- All Clients --'=>-1, '-- Active Clients Only --'=>0, '-- Inactive Clients Only --'=>-2 );
$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), clientid FROM tblclient ORDER BY lname, fname"));
//labeledSelect('Clients:', 'clients', $clients, $options);
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
	function spawnPrinter() {
		var clients = document.getElementById('clients');
		clients = clients.options[clients.selectedIndex].value;
		openConsoleWindow('reportprinter', 'reports-balances-internal.php?print=1&clients='+clients, 700,700);
	}
	function genCSV() {
		document.getElementById('csv').value=1;
		document.reportform.submit();
		document.getElementById('csv').value=0;
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	$specificType = $clients == -1 ? 'All-Clients' : (
									$clients == 0 ? 'Active-Clients' : (
									$clients == -2 ? 'Inactive-Clients' : 'Client'-$clients));

	header("Content-Disposition: attachment; filename=Account-Balance-Report-$specificType.csv ");
	dumpCSVRow("Account Balance INTERNAL Report: ".str_replace('-', ' ', $specificType));
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
}
else {
	$windowTitle = 'Account Balances INTERNAL Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payments Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".date('m/d/Y H:i')."</p>";
}

if($_POST) {
	fetchBalances($clients, $sort);
	$filter = $clients == -1 ? '' : (
									$clients == 0 ? "AND clientptr IN (SELECT clientid FROM tblclient WHERE active = 1)" : (
									$clients == -2 ? "AND clientptr IN (SELECT clientid FROM tblclient WHERE active = 0)" : $clients));
	$credits = fetchKeyValuePairs(
		"SELECT clientptr, sum(amount) 
			FROM tblcredit WHERE issuedate >= '$pstart' AND issuedate <= '$pend' $filter
			GROUP BY clientptr", 'clientptr');
	$creditsTotal = array_sum($credits);
	$charges = fetchKeyValuePairs(
		"SELECT clientptr, sum(charge+ifnull(adjustment,0)) 
			FROM tblappointment 
			WHERE canceled IS NULL AND date >= '$pstart' AND date <= '$pend' $filter
			GROUP BY clientptr", 'clientptr');
	$surcharges = fetchKeyValuePairs(
		"SELECT clientptr, sum(charge) 
			FROM tblsurcharge 
			WHERE canceled IS NULL AND date >= '$pstart' AND date <= '$pend' $filter
			GROUP BY clientptr", 'clientptr');
	foreach($surcharges as $clientptr => $surcharge) $charges[$clientptr] += $surcharge;
	foreach($charges as $clientptr => $charge) $allBalances[$clientptr]['charge'] = $charge;
	$chargesTotal = array_sum($charges);
	foreach($credits as $clientptr => $credit) $allBalances[$clientptr]['credit'] = $credit;
		
	//$uninvoiced  = getUninvoicedCharges($filter, date('Y-m-d'));
	//$uninvoicedTotal  = array_sum($uninvoiced);
	foreach($allBalances as $id => $arr)
		$allBalances[$id]['uninvoiced'] = $uninvoiced[$id] ? $uninvoiced[$id] : 0;
	if(strpos($sort, 'uninvoiced') === 0) {
		uasort($allBalances, 'uninvoicedSort');
		if(strpos(strtoupper($sort), 'DESC')) $allBalances = array_reverse($allBalances);
	}
//echo ">>>";print_r($allBalances);	
	
	if($csv) paymentsCSV($clients);
	else accountsTable($clients, $sort);
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

function fetchBalances($clients, $sort) {
	global $allBalances, $totalBalance, $clientSorts;
	$allBalances = array();
	$clientSorts = array();

	$filter = $clients > 0 
						? "clientid = $clients" 
						: ($clients == -1 
							?	"tblclient.active = 1"
							: ($clients == -2 ? "tblclient.active = 0" : "1=1"));
	$clientDetails = fetchAssociationsKeyedBy("SELECT clientid, lname, fname, CONCAT_WS(' ', lname, fname) as sortname, CONCAT_WS(' ', fname, lname) as client FROM tblclient WHERE $filter ORDER BY lname, fname", 'clientid');
	
	foreach($clientDetails as $id=>$detail) {
		$detail['amount'] = getAccountBalance($id, true, false);
		
		$unpaidbillables = fetchRow0Col0(
			"SELECT COUNT(*) 
				FROM tblbillable
				WHERE superseded = 0
					AND clientptr = $id
					AND paid < charge", 1);

		if($unpaidbillables > 1) $detail['fname'] .= " <image src='art/flag-dollar-red.jpg' title='$unpaidbillables unpaid invoices.'>";
		
		
		$totalBalance += $detail['amount'];
		$allBalances[$id] = $detail;
	}

	if(!$sort) $sort = 'client_ASC';
	$sorts = $sort ? explode('_', $sort) : '';
	if($sorts[0] == 'amount') uasort($allBalances, 'balanceSort');
	if(strtoupper($sorts[1]) == 'DESC') $allBalances = array_reverse($allBalances);
	
	return $allBalances;
}

function balanceSort($a, $b) {
	if($a['amount'] == $b['amount']) return strcmp($a['sortname'], $b['sortname']);
	return $a['amount'] < $b['amount'] ? -1 : 1;
}

function uninvoicedSort($a, $b) {
	if($a['uninvoiced'] == $b['uninvoiced'])  return strcmp($a['sortname'], $b['sortname']);
	return $a['uninvoiced'] < $b['uninvoiced'] ? -1 : 1;
}

function paymentsCSV($clients) {
	global $totalBalance, $uninvoicedTotal, $allBalances, $chargesTotal, $creditsTotal;

	dumpCSVRow(array(str_replace('&nbsp;', ' ', "Total Balance Due: ".dollarAmount($totalBalance)." Total Credits: ".dollarAmount($creditsTotal)." Total Charges: ".dollarAmount($chargesTotal))));
	$columns = explodePairsLine('client|Client||amount|Account Balance||credit|Total Credits||charge|Total Charges');
	dumpCSVRow($columns);
	foreach($allBalances as $row) 
		dumpCSVRow(array($row['client'], $row['amount'], $row['credit'], $row['charge']));
}

function accountsTable($clients, $sort) {
	global $totalBalance, $uninvoicedTotal, $allBalances, $pstart, $pend;
	$rows = $allBalances;
	echo "<p><span style='font-size:1.1em;'><span style='font-weight:bold;'>Total Balance Due: </span>".dollarAmount($totalBalance);
	echo "&nbsp;&nbsp;<span style='font-weight:bold;'>Total Credits: </span>".dollarAmount($creditsTotal).'</span><p>';
	echo "&nbsp;&nbsp;<span style='font-weight:bold;'>Total Charges: </span>".dollarAmount($chargesTotal).'</span><p>';


	$columns = explodePairsLine('client|Client||amount|Account Balance||credit|Total Credits||charge|Total Charges');
	$colClasses = array('amount' => 'dollaramountcell', 'uninvoiced' => 'dollaramountcell', 'credit' => 'dollaramountcell', 'charge' => 'dollaramountcell'); 
	$headerClass = array('amount' => 'dollaramountheader', 'uninvoiced' => 'dollaramountheader'); //'dollaramountheader'

	$columnSorts = array('amount'=>null, 'client'=>null);
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}
	foreach($rows as $i => $row) {
		$rows[$i]['amount'] = $row['amount'] < 0 ? dollarAmount(0-$row['amount']).'cr' : dollarAmount($row['amount']);
		$rows[$i]['amount'] = fauxLink($rows[$i]['amount'], 
					"openConsoleWindow(\"paymenthistory\", \"payment-history.php?go=1&id=$i&firstDay=$pstart&lastDay=$pend\",900,700)", 1);
		$rows[$i]['uninvoiced'] = dollarAmount($row['uninvoiced']);
		$rows[$i]['credit'] = dollarAmount($row['credit']);
		$rows[$i]['charge'] = dollarAmount($row['charge']);
	}
	$width = '60%'; //$_REQUEST['print'] ? '60%' : '45%';
	tableFrom($columns, $rows, "width='$width'", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}

?>
<script language='javascript' src='common.js'></script>

<script language='javascript'>
function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var clients = '<?= $clients ?>';
	document.location.href='reports-balances-internal.php?sort='+sortKey+'_'+direction
		+'&clients='+clients;
}
</script>