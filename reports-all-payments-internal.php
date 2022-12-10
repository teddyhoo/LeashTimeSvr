<? // reports-all-payments-internal.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,clients,sort,csv,showErrorsOnly', $_REQUEST));

if(!$start) $start =  date('m/1/Y');
$pstart = date('Y-m-d', strtotime($start));
if(!$end) $end = date('m/d/Y');
$pend = date('Y-m-d', strtotime($end));
		
$pageTitle = "Payment INTERNAL Report";


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
echo "&nbsp;";
//labeledCheckbox('Show Errors Only', 'showErrorsOnly', $showErrorsOnly, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
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

	header("Content-Disposition: attachment; filename=AllPayments-Report-$specificType.csv ");
	dumpCSVRow("Credit Card Payment INTERNAL Report: ".str_replace('-', ' ', $specificType));
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
}
else {
	$windowTitle = 'All Payment INTERNAL Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payments Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".date('m/d/Y H:i')."</p>";
}

	$filter = $clients == -1 ? '' : (
									$clients == 0 ? "AND clientptr IN (SELECT clientid FROM tblclient WHERE active = 1)" : (
									$clients == -2 ? "AND clientptr IN (SELECT clientid FROM tblclient WHERE active = 0)" : $clients));
									
	$recent = $_REQUEST['recent'];
	$addedstarting = $_REQUEST['addedstarting'];
	if($recent) $sql =
		"SELECT * 
			FROM tblcredit
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE 1=1
			ORDER BY creditid DESC 
			LIMIT $recent";
	else if($addedstarting) $sql =
		"SELECT * 
			FROM tblcredit
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE 1=1
			AND created >= '$addedstarting'
			ORDER BY creditid DESC 
			LIMIT $recent";
	else $sql = 
		"SELECT * 
			FROM tblcredit
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE 1=1
			AND issuedate >= '$pstart' AND issuedate <= '$pend'
			ORDER BY clientptr, issuedate";
	
	
	$allpayments = fetchAssociations($sql);
	foreach($allpayments as $credit) $clients[$credit['clientptr']] = 1;
//echo $sql;	
//print_r($clients);	
	if(isset($clients[0])) {
		$includeUnknown = '<font color=red>Client Unknown</font>';
		unset($clients[0]);
	}
	//echo "BANG!".print_r($clients, 1);
	if($clients) $clients = getClientDetails(array_keys((array)$clients), null, ($recent ? '' : 'sorted'));
	if($includeUnknown) $clients = array_merge(array(0 => array('clientname'=>$includeUnknown) ), (array)$clients);
//echo ">>>";print_r($allBalances);	
	
	if($csv) {
		paymentsCSV($clients);
		exit;
	}
	else paymentsTable($clients, $sort);
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

function nameSort($a, $b) {
	return strcmp($a['clientname'], $b['clientname']);
}

function uninvoicedSort($a, $b) {
	if($a['uninvoiced'] == $b['uninvoiced'])  return strcmp($a['sortname'], $b['sortname']);
	return $a['uninvoiced'] < $b['uninvoiced'] ? -1 : 1;
}

function paymentsCSV($clients) {
	global $allpayments, $uninvoicedTotal, $allBalances, $pstart, $pend, $showErrorsOnly;

	$columns = explodePairsLine('date|Date||clientname|Client||type|Type||amount|Amount||sourcereference|Source||externalreference|Reference');
	dumpCSVRow($columns);
	foreach($allpayments as $credit) {
		$credit['clientname'] = $clients[$credit['clientptr']]['clientname'];
		$credit['type'] = $credit['payment'] ? 'payment' : 'credit';
		$credit['date'] = $credit['issuedate'] ? date('m/d/Y', strtotime($credit['issuedate'])) : '';
		dumpCSVRow($credit, array_keys($columns));
	}
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


function paymentsTable($clients, $sort) {
	global $allpayments, $uninvoicedTotal, $allBalances, $pstart, $pend, $showErrorsOnly;
	$rows = $allBalances;

	$columns = explodePairsLine('date|Date||clientname|Client||type|Type||amount|Amount||sourcereference|Source||externalreference|Reference'
															);
	//$colClasses = array('amount' => 'dollaramountcell', 'uninvoiced' => 'dollaramountcell', 'credit' => 'dollaramountcell', 'charge' => 'dollaramountcell'); 
	//$headerClass = array('amount' => 'dollaramountheader', 'uninvoiced' => 'dollaramountheader'); //'dollaramountheader'

	//$columnSorts = array('amount'=>null, 'client'=>null);
	//if($sort) {
	//	$sort = explode('_', $sort);
	//	$columnSorts[$sort[0]] = $sort[1];
	//}
	foreach((array)$clients as $clientptr => $client) {
		foreach($allpayments as $credit) {
			if($credit['clientptr'] != $clientptr) continue;
			$row = $credit;
			$row['clientname'] = $client['clientname'];
			$row['date'] = date('m/d/Y', strtotime($credit['issuedate']));
			if($credit['payment']) {
				$row['type'] = 'payment';
				$editor = 'payment-edit.php';
			}
			else {
				$row['type'] = 'credit';
				$editor = 'credit-edit.php';
			}
			$row['amount'] = dollarAmount($credit['amount']);
		$row['amount'] = fauxLink($row['amount'], "openConsoleWindow(\"editcredit\", \"$editor?id={$credit['creditid']}\")",
																1, safeValue($credit['reason']));
			$rows[] = $row;
		}
	}
	$width = '100%'; //$_REQUEST['print'] ? '60%' : '45%';
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