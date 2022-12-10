<? // reports-balances.php

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
extract(extractVars('start,end,print,clients,sort,csv,positiveonly,goldstaronly', $_REQUEST));

		
$pageTitle = "Account Balance Report";


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
labeledSelect('Clients:', 'clients', $clients, $options);
hiddenElement('csv','');
hiddenElement('postproof','1');
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
	if(staffOnlyTEST()) {
		echo "&nbsp;STAFF ONLY: ";
		labeledCheckbox('Gold Star only', 'goldstaronly', $goldstaronly, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
	}
	if(staffOnlyTEST() || dbTest('walkzchicago') || dbTest('prestigepetsitting')) {
		echo "&nbsp;";
		labeledCheckbox('positive account balances only ', 'positiveonly', $positiveonly, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true);
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
	function spawnPrinter() {
		var clients = document.getElementById('clients');
		clients = clients.options[clients.selectedIndex].value;
		openConsoleWindow('reportprinter', 'reports-balances.php?print=1&clients='+clients, 700,700);
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
	dumpCSVRow("Account Balance Report: ".str_replace('-', ' ', $specificType));
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
}
else {
	$windowTitle = 'Payments Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payments Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
}
if($_POST) {
	fetchBalances($clients, $sort);
	$filter = $clients == -1 ? '' : (
									$clients == 0 ? "AND tblbillable.clientptr IN (SELECT clientid FROM tblclient WHERE active = 1)" : (
									$clients == -2 ? "AND tblbillable.clientptr IN (SELECT clientid FROM tblclient WHERE active = 0)" : $clients));
	$uninvoiced  = getUninvoicedCharges($filter, date('Y-m-d'));
	$uninvoicedTotal  = array_sum($uninvoiced);
	foreach($allBalances as $id => $arr)
		$allBalances[$id]['uninvoiced'] = $uninvoiced[$id] ? $uninvoiced[$id] : 0;
	if(strpos($sort, 'uninvoiced') === 0) {
		uasort($allBalances, 'uninvoicedSort');
		if(strpos(strtoupper($sort), 'DESC')) $allBalances = array_reverse($allBalances);
	}
//echo ">>>";print_r($allPayments);	
	
	if($csv) {
		paymentsCSV($clients);
		exit;
	}
	else paymentsTable($clients, $sort);
}
else if(!$csv) echo "<div class='tiplooks fontSize1_1em' style='padding-top:20px;width=100%;'>Click one of the buttons above to generate a list.</div>";
if(!$print && !$csv){
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
	global $allBalances, $totalBalance, $clientSorts, $positiveonly, $goldstaronly;
	require_once "cc-processing-fns.php";
	$allBalances = array();
	$clientSorts = array();
//$options = array('-- All Clients --'=>-1, '-- Active Clients Only --'=>0, '-- Inactive Clients Only --'=>-2 );
	$clients = $clients ? $clients : 0;
	$options = array(0=>"tblclient.active = 1", -1=>"1=1", -2=>"tblclient.active = 0");
	$filter = $clients > 0 ? "clientid = $clients" : $options[$clients];
	$clientDetails = fetchAssociationsKeyedBy($sql = "SELECT clientid, lname, fname, CONCAT_WS(' ', lname, fname) as sortname, CONCAT_WS(' ', fname, lname) as client FROM tblclient WHERE $filter ORDER BY lname, fname", 'clientid');
	if($goldstaronly) $goldstarClientIds = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property LIKE 'flag_%' AND value LIKE '2|%'",1);
//print_r($goldstarClientIds);}
	foreach($clientDetails as $id=>$detail) {
//if(mattOnlyTEST()) echo "$id<br>";
		if($goldstaronly && !in_array($id, $goldstarClientIds)) continue;
		$recentPayment = fetchFirstAssoc(
			"SELECT amount as lastpayment, issuedate as lastpaymentdate 
				FROM tblcredit 
				WHERE clientptr = $id AND payment = 1
				ORDER BY issuedate DESC
				LIMIT 1", 1);
		if($recentPayment) {
			$detail['lastpayment'] = $recentPayment['lastpayment'];
			$detail['lastpaymentdate'] = $recentPayment['lastpaymentdate'];
		}
		$detail['amount'] = getAccountBalance($id, true, false);
		if($positiveonly && $detail['amount'] <= 0) continue;
		$totalBalance += $detail['amount'];
		if($source = getClearPrimaryPaySource($id))
			$detail['paymentSource'] = ($source['ccid'] ? 'CC' : 'ACH').($source['autopay'] ? ' (autopay)' : '');
		$allBalances[$id] = $detail;
	}

	if(!$sort) $sort = 'client_ASC';
	$sorts = $sort ? explode('_', $sort) : '';
	if($sorts[0] == 'amount') uasort($allBalances, 'balanceSort');
	if(strtoupper($sorts[1]) == 'DESC') $allBalances = array_reverse($allBalances);
	
	return $allPayments;
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
	global $totalBalance, $uninvoicedTotal;
	dumpCSVRow(array("Total Balance Due:",$totalBalance,"Total Uninvoiced Charges:",$uninvoicedTotal));
	$columns = explodePairsLine('client|Client||lname|Last||fname|First||amount|Account Balance||uninvoiced|Uninvoiced Charges');
	if(TRUE || mattOnlyTEST() || dbTEST('dogonfitness')) $columns['paymentSource'] = 'Credit Card/ACH';
	if(TRUE || mattOnlyTEST()) {
		$columns['lastpaymentdate'] = 'Last Paid';
		$columns['lastpayment'] = 'Last Payment';
	}
	dumpCSVRow($columns);
	foreach(paymentsRows() as $row) {
		$rowToShow = array($row['client'], $row['lname'], $row['fname'], $row['amount'], $row['uninvoiced']);
		if(TRUE || mattOnlyTEST() || dbTEST('dogonfitness')) {
			$paymentSource = getClearPrimaryPaySource($row['clientid']);
			//if($paymentSource)
			//	$rowToShow[] = ($paymentSource['ccid'] ? 'CC' : 'ACH').($paymentSource['autopay'] ? ' (autopay)' : '');
			$rowToShow[] = 
				!$paymentSource ? '' 
				: ($paymentSource['ccid'] ? 'CC' : 'ACH').($paymentSource['autopay'] ? ' (autopay)' : '');

		}
		if(TRUE || mattOnlyTEST()) {
			$rowToShow['lastpaymentdate'] = $row['lastpaymentdate'];
			$rowToShow['lastpayment'] = $row['lastpayment'];
		}
		
		dumpCSVRow($rowToShow);
	}
}

function paymentsTable($clients, $sort) {
	global $totalBalance, $uninvoicedTotal;
	$rows = paymentsRows();

	echo "<p><span style='font-size:1.1em;'><span style='font-weight:bold;'>Total Balance Due: </span>".dollarAmount($totalBalance);
	echo "&nbsp;&nbsp;<span style='font-weight:bold;'>Total Uninvoiced Charges: </span>".dollarAmount($uninvoicedTotal).'</span><p>';


	$columns = explodePairsLine('client|Client||amount|Account Balance||uninvoiced|Uninvoiced Charges');
	if(TRUE || mattOnlyTEST() || dbTEST('dogonfitness')) $columns['paymentSource'] = 'Credit Card/ACH';
	if(TRUE || mattOnlyTEST() ) {
		$columns['lastpaymentdate'] = 'Last Paid';
		$columns['lastpayment'] = 'Last Payment';
	}
	$colClasses = array('amount' => 'dollaramountcell', 'uninvoiced' => 'dollaramountcell', 'lastpayment' => 'dollaramountcell'); 
	$headerClass = array('amount' => 'dollaramountheader', 'uninvoiced' => 'dollaramountheader', 'lastpayment' => 'dollaramountheader'); //'dollaramountheader'
//lastpaymentdate
	$columnSorts = array('amount'=>null, 'client'=>null, 'uninvoiced'=>null);
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}
	foreach($rows as $i => $row) {
		$rows[$i]['amount'] = $row['amount'] < 0 ? dollarAmount(0-$row['amount']).'cr' : dollarAmount($row['amount']);
		$rows[$i]['uninvoiced'] = dollarAmount($row['uninvoiced']);
		$rows[$i]['lastpayment'] = $row['lastpayment']  ? dollarAmount($row['lastpayment']) : '';
		$rows[$i]['lastpaymentdate'] = $row['lastpaymentdate']  ? shortDate(strtotime($row['lastpaymentdate'])) : '';
	}
	$width = '75%'; //$_REQUEST['print'] ? '60%' : '45%';
	tableFrom($columns, $rows, "width='$width'", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}

function paymentsRows() {
	global $allBalances;
	if($allBalances) foreach($allBalances as $payment) {
		$row = $payment;
		$row['amount'] = $payment['amount'];
		$row['uninvoiced'] = $payment['uninvoiced'];
		//$row['lastpayment'] = $payment['lastpayment'];
		//$row['lastpaymentdate'] = $payment['lastpaymentdate'];
		$rows[] = $row;
	}
	return $rows;
}
?>
<script language='javascript'>
function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var clients = '<?= $clients ?>';
	document.location.href='reports-balances.php?sort='+sortKey+'_'+direction
		+'&clients='+clients;
}
</script>