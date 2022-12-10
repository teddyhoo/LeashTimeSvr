<? // reports-refunds.php

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

		
$pageTitle = "Refunds Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a> - <a href='reports-payments.php'>Payments</a>";	
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
			openConsoleWindow('reportprinter', 'reports-refunds.php?print=1&start='+start+'&end='+end+'&reportType='+reportType, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Refunds.csv ");
	dumpCSVRow('Refunds Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Refunds Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Refunds Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	fetchRefunds($start, $end, $clients, $sort);
//echo ">>>";print_r($allRefunds);	
	
	if($csv) refundsCSV($start, $end, $sort);
	else refundsTable($start, $end, $sort);
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

/*
  `refundid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clientptr` int(10) unsigned NOT NULL,
  `amount` float(6,2) NOT NULL DEFAULT '0.00',
  `reason` varchar(45) DEFAULT NULL,
  `paymentptr` int(10) unsigned DEFAULT NULL,
  `issuedate` date NOT NULL DEFAULT '0000-00-00',
  `externalreference` varchar(60) DEFAULT NULL COMMENT 'Check #, transaction number, etc',
  `sourcereference` varchar(60) DEFAULT NULL COMMENT 'Credit card #, bank acct #, etc',
*/

function fetchRefunds($start, $end, $clients, $sort) {
	global $allRefunds, $totalRefunds, $clientSorts, $totalCredits, $totalGratuities;
	$allRefunds = array();
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
	
	$sql = "SELECT tblrefund.*, CONCAT_WS(' ', fname, lname) as client, CONCAT_WS(' ', lname, fname) as sortname
					FROM tblrefund 
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE issuedate >= '$start' AND issuedate <= '$end' $filter
					$sorts";
	$result = doQuery($sql);
  while($refund = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$amt = $refund['amount'];
		if($refund['payment']) $totalRefunds += $amt;
		$client = $refund['client'];
		$clientSorts[$refund['client']] = $refund['sortname'];
		$allRefunds[$refund['refundid']] = $refund;
		$totalRefunds += $refund['amount'];
		if($refund['paymentptr'])	$allPayments[$refund['paymentptr']] = null;
		//else echo "Refund {$refund['refundid']} has no paymentptr.<br>";
	}
	if($allRefunds && $allPayments) {
		$sql = "SELECT creditid, amount as paymentamount, issuedate as paymentdate, externalreference as paymenttransactionid
						FROM tblcredit
						WHERE creditid IN (".join(',', array_keys($allPayments)).")";
		$allPayments = fetchAssociationsKeyedBy($sql, 'creditid');
		foreach($allRefunds as $i => $refund)
			if($payment = $allPayments[$refund['paymentptr']])
//echo "XX Refund ".print_r($refund, 1)."<br>";		
				foreach($payment as $k=>$v) {
					$allRefunds[$i][$k] = $v;
					if($k == 'paymenttransactionid' && $v) {
						$prefix = strpos($allRefunds[$i][$k], 'CC:') !== false ? 'CC:' : (
											strpos($allRefunds[$i][$k], 'ACH:') !== false ? 'ACH:' : '');
						$allRefunds[$i][$k] = trim(substr($allRefunds[$i][$k], strlen($prefix)));
					}
				}
	}
	return $allRefunds;
}

function refundsTable($start, $end, $sort) {
	global $allRefunds, $totalRefunds;

	$columns = explodePairsLine('client|Client||issuedate|Date||amount|Amount||paymentamount|Payment||paymentdate|Payment Date||paymenttransactionid|Paym. Trans. ID'); // ||sourcereference|Payment Method||reason|Notes
	$colClasses = array('amount' => 'dollaramountcell', 'paymentamount' => 'dollaramountcell'); 
	$headerClass = array('amount' => 'dollaramountheader', 'paymentamount' => 'dollaramountheader'); //'dollaramountheader'

	$columnSorts = array('issuedate'=>null, 'client'=>null); // 'amount'=>null, 'sourcereference'=>null,
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}

	$rowClass = 'futuretaskEVEN';
	foreach($allRefunds as $refund) {
		$rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';

		$row = $refund;
		$row['amount'] = fauxLink(dollarAmount($refund['amount']), "showRefund({$refund['refundid']})", 'noecho', 'View refund.');
		$row['paymentamount'] = fauxLink(dollarAmount($refund['paymentamount']), "showPayment({$refund['refundid']})", 'noecho', 'View refund.');
		;
		$row['issuedate'] = shortDate(strtotime($refund['issuedate']));
		$row['paymentdate'] = $row['paymentdate'] ? shortDate(strtotime($refund['paymentdate'])) : '';
		
		$rows[] = $row;
		$rowClasses[] = $rowClass;
	}
	$totalRefunds = dollarAmount($totalRefunds);
	$totalCredits = dollarAmount($totalCredits);
	$totalGratuities = dollarAmount($totalGratuities);
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td class='sortableListCell' style='font-weight:bold;text-align:right;'>Total Refunds</td><td class='dollaramountcell'>$totalRefunds</td></tr>");
	
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
}

function refundsCSV($start, $end, $sort) {
	global $allRefunds, $totalRefunds;
	$columns = 'client|Client||issuedate|Date||amount|Amount||paymentamount|Payment||paymentdate|Payment Date||paymenttransactionid|Paym. Trans. ID'; // ||sourcereference|Payment Method||reason|Notes
	$columns .= "||reason|Refund Reason";
	$columns = explodePairsLine($columns);
	dumpCSVRow($columns);
	foreach($allRefunds as $refund) {
		$row = array('issuedate' => shortDate(strtotime($refund['issuedate'])), 'paymentdate' => shortDate(strtotime($refund['paymentdate'])));
		$row['client'] = $refund['client'];
		$row['amount'] = $refund['amount'];
		$row['paymentamount'] = $refund['paymentamount'];
		//$row['sourcereference'] = $payment['sourcereference'];
		$row['paymenttransactionid'] = $refund['paymenttransactionid'];
		$row['reason'] = $refund['reason'];
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
<script language='javascript' src='common.js'></script>

<script language='javascript'>
function showRefund(id) {
	openConsoleWindow('refundeditor', 'refund-edit.php?id='+id,580,300)
}

function showPayment(id) {
	openConsoleWindow('refundeditor', 'payment-edit.php?id='+id,580,300)
}

function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var clients = '<?= $clients ?>';
	document.location.href='reports-refunds.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end+'&clients='+clients;
}
</script>
<? }