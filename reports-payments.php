<? // reports-payments.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "item-note-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,clients,sort,csv', $_REQUEST));

		
$pageTitle = "Payments Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";
	$breadcrumbs .= " - <a href='reports-refunds.php'>Refunds</a>";
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
	<tr><td colspan=2>
<?
$options = array('All Clients'=>-1);
$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), clientid FROM tblclient ORDER BY lname, fname"));
labeledSelect('Clients:', 'clients', $clients, $options);
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
			openConsoleWindow('reportprinter', 'reports-payments.php?print=1&start='+start+'&end='+end+'&reportType='+reportType, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Payments.csv ");
	dumpCSVRow('Payments Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Payments Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payments Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	fetchPayments($start, $end, $clients, $sort);
//echo ">>>";print_r($allPayments);	
	
	if($csv) paymentsCSV($start, $end, $sort);
	else paymentsTable($start, $end, $sort);
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
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

function fetchPayments($start, $end, $clients, $sort) {
	global $allPayments, $totalPayments, $clientSorts, $totalCredits, $totalGratuities;
	$allPayments = array();
	$clientSorts = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d 23:59:59', strtotime($end));
	$filter = $clients && $clients != -1 ? "AND clientptr = $clients" : "";
	if(!$sort) $sort = 'issuedate_ASC';
	$sorts = $sort ? explode('_', $sort) : '';
	if($sorts[0] == 'issuedate') $sorts = "{$sorts[0]} {$sorts[1]}, sortname";
	else if($sorts[0] == 'amount') $sorts = "{$sorts[0]} {$sorts[1]}";
	else if($sorts[0] == 'sourcereference') $sorts = "{$sorts[0]} {$sorts[1]}, issuedate, sortname";
	else if($sorts[0] == 'client') $sorts = "sortname {$sorts[1]}, issuedate";
	if($sorts) $sorts = "ORDER BY $sorts";
	
	$sql = "SELECT tblcredit.*, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(' ', lname, fname) as sortname
					FROM tblcredit 
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE issuedate >= '$start' AND issuedate <= '$end' $filter
								AND (payment = 1 OR reason NOT LIKE '%(v:%')
					$sorts";
	$result = doQuery($sql);
  while($payment = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$amt = $payment['amount'];
		if($payment['payment']) $totalPayments += $amt;
		else {
			$totalCredits += $amt;
			$payment['reason'] = "[CREDIT] {$payment['reason']}";
		}
		$client = $payment['client'];
		$clientSorts[$payment['client']] = $payment['sortname'];
		$allPayments[$payment['creditid']] = $payment;
	}
	if($allPayments) {
		$sql = "SELECT paymentptr, sum(amount) as total
						FROM tblgratuity
						WHERE paymentptr IN (".join(',', array_keys($allPayments)).")
						GROUP BY paymentptr";
		$result = doQuery($sql);
		while($gratuity = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$allPayments[$gratuity['paymentptr']]['gratuity'] = $gratuity['total'];
			$totalGratuities += $gratuity['total'];
		}
	}
	return $allPayments;
}

function paymentsTable($start, $end, $sort) {
	global $allPayments, $totalPayments, $totalCredits, $totalGratuities;

	$columns = explodePairsLine('issuedate|Date||client|Client||amount|Amount||gratuity|Gratuity||externalreference|Trans. ID||sourcereference|Payment Method||reason|Notes');
	$colClasses = array('amount' => 'dollaramountcell', 'gratuity' => 'dollaramountcell'); 
	$headerClass = array('amount' => 'dollaramountheader', 'gratuity' => 'dollaramountheader'); //'dollaramountheader'

	$columnSorts = array('issuedate'=>null,'amount'=>null, 'sourcereference'=>null, 'client'=>null);
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}

	$rowClass = 'futuretaskEVEN';
	foreach($allPayments as $payment) {
		$rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';

		$row = $payment;
		$row['amount'] = dollarAmount($payment['amount']);
		if(mattOnlyTEST()) {
			$creditType = $payment['payment'] ? 'payment' : 'credit';
			$row['amount'] = 
				fauxLink($row['amount'], 
					"openConsoleWindow(\"paymenteditor\", \"$creditType-edit.php?id={$payment['creditid']}\", 600,500)", 1, 'Edit payment');
		}
		$row['issuedate'] = shortDate(strtotime($payment['issuedate']));
		if(staffOnlyTEST()) 
		$row['issuedate'] = "<span title ='{$row['issuedate']} ".date('h:i:s a', strtotime($payment['issuedate']))."'>{$row['issuedate']}</span>";
		if($payment['voided']) {
			$voidReason = getItemNote('tblcredit', $payment['creditid']);
			$voidReason = $voidReason ? truncatedLabel($voidReason['note'], 25) : '';
			$voidedDate = shortDate(strtotime($payment['voided']));
			$row['reason'] = "<font color=red>VOID ($voidedDate): \${$payment['voidedamount']} ".$voidReason.'</font>';
		}
		//else $row['reason'] = ($credit['payment'] ? 'PAYMENT' : 'CREDIT').($credit['reason'] ? ': '.$credit['reason'] : '');
		
		
		
		$rows[] = $row;
		$rowClasses[] = $rowClass;
	}
	$totalPayments = dollarAmount($totalPayments);
	$totalCredits = dollarAmount($totalCredits);
	$totalGratuities = dollarAmount($totalGratuities);
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td class='sortableListCell' style='font-weight:bold;text-align:right;'>Total Payments</td><td class='dollaramountcell'>$totalPayments</td><td class='dollaramountcell'>$totalGratuities</td></tr>");
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td class='sortableListCell' style='font-weight:bold;text-align:right;'>Total Credits</td><td class='dollaramountcell'>$totalCredits</td></td></tr>");
	
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
}

function paymentsCSV($start, $end, $sort) {
	global $allPayments, $totalPayments;
	$columns = explodePairsLine('issuedate|Date||client|Client||amount|Amount||gratuity|Gratuity||externalreference|Trans. ID||sourcereference|Payment Method||reason|Notes');
	dumpCSVRow($columns);
	foreach($allPayments as $payment) {
		$row = array('issuedate' => shortDate(strtotime($payment['issuedate'])));
		$row['client'] = $payment['client'];
		$row['amount'] = $payment['amount'];
		$row['gratuity'] = $payment['gratuity'];
		$row['sourcereference'] = $payment['sourcereference'];
		$row['externalreference'] = $payment['externalreference'];
		if($payment['voided']) {
			$voidReason = getItemNote('tblcredit', $payment['creditid']);
			$voidReason = $voidReason ? truncatedLabel($voidReason['note'], 25) : '';
			$voidedDate = shortDate(strtotime($payment['voided']));
			$row['reason'] = "VOID ($voidedDate): \${$payment['voidedamount']} ".$voidReason;
		}
		else $row['reason'] = $payment['reason'];
		$rows[] = $row;
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

if(!$csv && !$print) {
?>
<script language='javascript'>
function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var clients = '<?= $clients ?>';
	document.location.href='reports-payments.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end+'&clients='+clients;
}
</script>
<? }