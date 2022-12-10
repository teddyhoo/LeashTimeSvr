<? // reports-invoices.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "invoice-gui-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,sort,csv', $_REQUEST));

		
$pageTitle = "Invoices <font color=red></font>";


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
	<tr><td colspan=2>
	</td></tr>
	
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	//echoButton('', 'Print Report', "spawnPrinter()");
	//echo "&nbsp;";
	if($_SESSION['staffuser']) echoButton('', 'Download Spreadsheet', "genCSV()");
if(mattOnlyTEST() && $start && $end) {
	$numFound = fetchInvoices($start, $end, $sort, $countOnly=true)	;
	echo "<p>Found: $numFound</p>";
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
			openConsoleWindow('reportprinter', 'reports-payroll-projection.php?print=1&start='+start+'&end='+end+'&sort='+'<?= $sort ?>', 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: attachment; filename=Invoices.csv ");
	dumpCSVRow('Invoices Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Projected Payroll Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payroll Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	$rows = fetchInvoices($start, $end, $sort);
	if($csv) $rows = array_merge($rows, fetchCredits($start, $end, $sort));
//echo ">>>";print_r($allPayments);	
	
	if($csv) {
		//invoicesCSV($start, $end, $sort);
		$columns = explodePairsLine('date|Date||subject|Invoice||client|Client||amount|Orginal Amount||stilldue|Current Balance Due');
		dumpCSVRow($columns);
		foreach($rows as $row) {
			dumpCSVRow($row, array_keys($columns));
		}
		exit;
	}

	else {
	if($rows) {
		foreach($rows as $i => $row) {
			$rows[$i]['amount'] = dollarAmount($row['amount']);
			$rows[$i]['stilldue'] = dollarAmount($row['stilldue']);
		}
		$columns = explodePairsLine('date|Date||subject|Invoice||client|Client||amount|Orginal Amount||stilldue|Current Balance Due');
		$columnSorts = array('date'=>1,'subject'=>1,'client'=>1,'amount'=>1,'stilldue'=>1);
		$colClasses = array('amount'=>'dollaramountcell', 'stilldue'=>'dollaramountcell');
		$headerRowClass = array('amount'=>'dollaramountheader', 'stilldue'=>'dollaramountheader');
		tableFrom($columns, $rows, 'width=90%', $class=null, $headerClass=null, $headerRowClass, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	}
	else echo "No invoices found.";
		
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

function fetchCredits($start, $end, $sort) {
	$issueDateInRange = "issuedate >= '".date('Y-m-d 00:00:00', strtotime($start))."' AND issuedate <= '".date('Y-m-d 23:59:59', strtotime($end))."'"; //  AND note LIKE '%$pattern%'
	$credits = fetchAssociations($sql = "SELECT *, issuedate as date FROM tblcredit WHERE payment=0 AND $issueDateInRange ORDER BY issuedate DESC");
	$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
	foreach($credits as $credit) {
		$date = shortDate(strtotime($credit['date']));
		$paid = '';
		$subject = "CREDIT: ".$credit['reason'];
		$client = $clients[$credit['clientptr']];
		$amount = 0-$credit['amount'];
		$stillDue = '';
		$rows[] = array('subject'=>$subject, 'date'=>$date, 'client'=>$client, 'amount'=>$amount, 
										'stilldue'=>$stillDue, 'sortsubj'=>$request['street1']);
	}
	return (array)$rows;
}


function fetchInvoices($start, $end, $sort, $countOnly=null) {
	global $invoices, $sortKey;
	$inRange = "date >= '".date('Y-m-d 00:00:00', strtotime($start))."' AND date <= '".date('Y-m-d 23:59:59', strtotime($end))."'"; //  AND note LIKE '%$pattern%'
	if($countOnly) return fetchRow0Col0("SELECT COUNT(*) FROM tblinvoice WHERE $inRange");
	$invoices = fetchAssociations($sql = "SELECT * FROM tblinvoice WHERE $inRange ORDER BY date DESC");
	$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
	$rows = array();
	foreach($invoices as $invoice) {
		$date = shortDate(strtotime($invoice['date']));
		$paid = $invoice['paidinfull'] ? ' (PAID)' : '';
		$subject = fauxLink(invoiceIdDisplay($invoice['invoiceid']).$paid, "openConsoleWindow(\"viewinvoice\", \"invoice-view.php?id={$invoice['invoiceid']}\")", 1, "generated: {$invoice['date']} as of: {$invoice['asofdate']}");
		$client = $clients[$invoice['clientptr']];
		$amount = $invoice['origbalancedue'];
		$stillDue = $invoice['balancedue'];
		$rows[] = array('subject'=>$subject, 'date'=>$date, 'client'=>$client, 'amount'=>$amount, 
										'stilldue'=>$stillDue, 'sortsubj'=>$request['street1']);
	}
	
	$startCondition = $start ? "AND SUBSTR(datetime, 1, 10) >= '".date('Y-m-d', strtotime($start))."'" : '';
	$endCondition = $end ? "AND SUBSTR(datetime, 1, 10) <= '".date('Y-m-d', strtotime($end))."'" : '';
	$statements = fetchAssociations(
		"SELECT datetime, msgid, subject, correspaddr, correspid, ifnull(transcribed, 'email') as transcribed, 
						CONCAT_WS(' ', lname, ',', fname) as sortname
		 FROM tblmessage
		 LEFT JOIN tblclient on clientid = correspid
		 WHERE inbound = 0 AND correstable = 'tblclient'
				$startCondition $endCondition
				AND (subject like 'prepayment'
										OR subject like 'invoice'
										OR tags like 'prepayment'
										OR tags like 'billing') 
		 ORDER BY datetime DESC");  // should really be [ tags like '%$billingInvoiceTag% ]
	foreach($statements as $msg) {
		$date = shortDate(strtotime($msg['datetime']));
		$paid = '';
		$subject = fauxLink($msg['subject'], 
				"openConsoleWindow(\"invoiceview\", \"comm-view.php?id={$msg['msgid']}\", 800, 800);", 
				1, 'View this invoice statement');
		$client = $clients[$msg['correspid']];
		$amount = '';
		$stillDue = '';
		$rows[] = array('subject'=>$subject, 'date'=>$date, 'client'=>$client, 'amount'=>$amount, 
										'stilldue'=>$stillDue, 'sortsubj'=>$subject);
	}
	
	if(!$sort) $sort = 'client_ASC';
	if($sort) {
		$sortParts = explode('_', $sort);
		$sortKey = $sortParts[0];
		if($sortKey == 'received') $sortKey = 'sortdate';
		else if($sortKey == 'subject') $sortKey = 'sortsubj';
		usort($rows, 'rowSort');
		if($sortParts[1] == 'desc') $rows = array_reverse($rows);
	}
	return (array)$rows;
}

function rowSort($a, $b) {
	global $sortKey;
	return strcmp(strtoupper($a[$sortKey]), strtoupper($b[$sortKey]));
}
	

function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if(is_array($row)) {
		if($cols) {
			$nrow = array();
			if(is_string($cols)) $cols = explode(',', $cols);
			foreach($cols as $k) $nrow[] = strip_tags($row[$k]);
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

function payrollTable($start, $end, $sort) {
	global $allPayments, $totalPayments, $totalRevs;

	$columns = explodePairsLine('provider|Sitter||charge|Revenue||amount|Pay||net|Business Net'); //||note|Notes
	$colClasses = array('amount' => 'dollaramountcell', 'charge' => 'dollaramountcell', 'net' => 'dollaramountcell'); 
	$headerClass = array('amount' => 'dollaramountheader', 'charge' => 'dollaramountheader', 'net' => 'dollaramountheader'); //'dollaramountheader'

	//$columnSorts = array('amount'=>null, 'provider'=>null);
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}

	foreach($allPayments as $payment) {
		$totalNet += ($net = $payment['charge'] - $payment['amount']);
		$row = $payment;
		$row['charge'] = dollarAmount($payment['charge']);
		$row['net'] = dollarAmount($net);
		$row['amount'] = fauxLink(dollarAmount($payment['amount']), "payProjectionDetail({$payment['providerptr']})", 1);
		$rows[] = $row;
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=4 id='detail_{$payment['providerptr']}' class='selectedbackground'></td></tr>");
	}
	$totalPayments = dollarAmount($totalPayments);
	$totalRevs = dollarAmount($totalRevs);
	$rows[] = array('#CUSTOM_ROW#'=>"<tr>
		<td class='sortableListCell' style='font-weight:bold;text-align:right;'>Totals</td>
		<td class='dollaramountcell' style='font-weight:bold;'>".dollarAmount($totalRevs)."</td>
		<td class='dollaramountcell' style='font-weight:bold;'>".dollarAmount($totalPayments)."</td>
		<td class='dollaramountcell' style='font-weight:bold;'>".dollarAmount($totalNet)."</td>
		</tr>");
	
	if(!$allPayments) {
		echo "No sitters found.";
		return;
	}
	echo "<p><span class='tiplooksleft'>Click a pay amount once to view supporting details.  Click again to close the detail.</span><p>";
	tableFrom($columns, $rows, 'width=60%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function openRequest(id) {
	openConsoleWindow("viewrequest", "request-edit.php?id="+id+"&updateList=requests",610,600);
}

function payProjectionDetail(prov) {
	var td = document.getElementById('detail_'+prov);
	if(td.innerHTML) {
		$.fn.removeClass("selectedbackground");
		td.innerHTML = '';
	}
	else {
		$.fn.addClass("selectedbackground");
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("payroll-projection-detail.php?start=<?= date('Y-m-d', strtotime($start)) ?>&end=<?= date('Y-m-d', strtotime($end)) ?>&prov="+prov, fillInDetail, 'detail_'+prov);
		//ajaxGet("payroll-projection-detail.php?start=<?= date('Y-m-d', strtotime($start)) ?>&end=<?= date('Y-m-d', strtotime($end)) ?>&prov="+prov, 'detail_'+prov);
	}
}

function fillInDetail(divid, html) {
	document.getElementById(divid).innerHTML = html;
	$('.BlockContent-body').busy("hide");
}

function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	document.location.href='reports-invoices.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end; 
}
</script>