<? // reports-payroll2.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,providers,sort,csv', $_REQUEST));

		
$pageTitle = "Payroll Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a> - <a href='reports-payroll-projection.php'>Projected Payroll</a>";	
	include "frame.html";
	// ***************************************************************************
?>
<style>
.dateRow { font-weight: bold; }
.selectedbackground { background: lightyellow; }
</style>

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
$options = array('All Sitters'=>-1);
$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), providerid FROM tblprovider ORDER BY lname, fname"));
labeledSelect('Providers:', 'providers', $providers, $options);
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
			var providers = document.getElementById('providers');
			providers = providers.options[providers.selectedIndex].value;
			openConsoleWindow('reportprinter', 'reports-payroll.php?print=1&start='+start+'&end='+end+'&providers='+providers+'&sort='+'<?= $sort ?>', 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: attachment; filename=Payroll-Report.csv ");
	dumpCSVRow('Payroll  Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Payroll Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payroll Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) {
	fetchPayments($start, $end, $providers, $sort);
//echo ">>>";print_r($allPayments);	
	
	if($csv) {
		payrollCSV($start, $end, $sort);
		exit;
	}

	else payrollTable($start, $end, $sort);
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

function fetchPayments($start, $end, $providers, $sort) {
	global $allPayments, $totalPayments, $providerSorts;
	$allPayments = array();
	$providerSorts = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$filter = $providers && $providers != -1 ? "AND providerptr = $providers" : "";
	if(!$sort) $sort = 'paymentdate_ASC';
	$sorts = $sort ? explode('_', $sort) : '';
	if($sorts[0] == 'paymentdate') $sorts = "{$sorts[0]} {$sorts[1]}, sortname, provider ASC";
	else if($sorts[0] == 'amount') $sorts = "{$sorts[0]} {$sorts[1]}, sortname";
	else if($sorts[0] == 'paymenttype') $sorts = "{$sorts[0]} {$sorts[1]}, paymentdate, sortname";
	else if($sorts[0] == 'provider') $sorts = "sortname {$sorts[1]}, paymentdate";
	if($sorts) $sorts = "ORDER BY $sorts";
	
	$sql = "SELECT tblproviderpayment.*,
					CONCAT_WS(' ', fname, lname) as provider, CONCAT_WS(' ', lname, fname) as sortname
					FROM tblproviderpayment 
					LEFT JOIN tblprovider ON providerid = providerptr
					WHERE paymentdate >= '$start' AND paymentdate <= '$end' $filter
					$sorts";
	$result = doQuery($sql);
  while($payment = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$totalPayments += ($amt = $payment['amount']);
		$provider = $payment['provider'];
		$clientSorts[$payment['provider']] = $payment['sortname'];
		$allPayments[$payment['paymentid']] = $payment;
	}
	return $allPayments;
}

function payrollCSV($start, $end, $sort) {
	require "pay-fns.php";
	global $allPayments, $totalPayments, $clientNames, $services, $surchargeTypes, $hours;
	$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname, CONCAT('[',clientid,']')) FROM tblclient");
	$surchargeTypes = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$hours = fetchKeyValuePairs("SELECT servicetypeid, hours FROM tblservicetype WHERE hours IS NOT NULL");
	$providers = getProviderNames();
	$columns = explodePairsLine('date|Date||provider|Sitter||amount|Amount||transactionId|Client / Transaction ID||tod_ending|Ending or Time of Day||service|Service||servicecode|Service Code||hours|Hours||note|Notes');
	dumpCSVRow($columns);
	//$paymentTypes = explodePairsLine('regular|Regular||adhoc|Special');
	foreach($allPayments as $payment) {
		//$row = $payment;
		$row['date'] = shortDate(strtotime($payment['paymentdate']));
		$row['provider'] = $providers[$payment['providerptr']];
		$row['amount'] = $payment['amount'];
		$row['transactionid'] = $payment['transactionid'];
		$row['tod_ending'] = $payment['enddate'] ? shortDate(strtotime($payment['enddate'])) : '';;
		$row['service'] = '~~PAYMENT~~';
		$row['servicecode'] = '';
		$row['note'] = $payment['note'];
		dumpCSVRow($row);
		paymentDetailCSV($payment['paymentid'], $row['provider']);
	}
}

function paymentDetailCSV($paymentid, $providername) {
	global $clientNames, $services, $surchargeTypes, $hours;
	$payables = getPaymentPayables($paymentid);
	foreach($payables as $payable) {
		$type = $payable['itemtable'];
		$itemptr = $payable['itemptr'];
		$row = array();
		if($type == 'tblappointment') {
			$item = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $itemptr LIMIT 1");
			$row['date'] = shortDate(strtotime($item['date']));
			$row['provider'] = $providername;
			$row['amount'] = $item['rate']+$item['bonus'];
			$row['transactionId'] = $clientNames[$item['clientptr']];
			$row['tod_ending'] = $item['timeofday'];
			$row['service'] = $services[$item['servicecode']];
			$row['servicecode'] = $item['servicecode'];
			$row['hours'] = $hours[$item['servicecode']] == '00:00' ? '' : $hours[$item['servicecode']];
		}
		else if($type == 'tblsurcharge') {
			$item = fetchFirstAssoc("SELECT * FROM tblsurcharge WHERE surchargeid = $itemptr LIMIT 1");
			$row['date'] = shortDate(strtotime($item['date']));
			$row['provider'] = $providername;
			$row['amount'] = $item['rate']+$item['bonus'];
			$row['transactionId'] = $clientNames[$item['clientptr']];
			$row['tod_ending'] = $item['timeofday'];
			$row['service'] = 'Surcharge: '.$surchargeTypes[$item['surchargecode']];
			$row['servicecode'] = $item['surchargecode'];
		}
		else if($type == 'tblothercomp') {
			$item = fetchFirstAssoc("SELECT * FROM tblothercomp WHERE compid = $itemptr LIMIT 1");
			$row['date'] = shortDate(strtotime($item['date']));
			$row['provider'] = $providername;
			$row['amount'] = $item['amount'];
			$row['transactionId'] = '';
			$row['tod_ending'] = '';
			$row['service'] = 'Other comp: '.$item['comptype'];
			$row['servicecode'] = '';
		}
		else if($type == 'tblgratuity') {
			$item = fetchFirstAssoc("SELECT * FROM tblgratuity WHERE gratuityid = $itemptr LIMIT 1");
			$row['date'] = shortDate(strtotime($item['issuedate']));
			$row['provider'] = $providername;
			$row['amount'] = $item['amount'];
			$row['transactionId'] = $clientNames[$item['clientptr']];
			$row['tod_ending'] = '';
			$row['service'] = '';
			$row['servicecode'] = '';
		}
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

function payrollTable($start, $end, $sort) {
	global $allPayments, $totalPayments;

	$columns = explodePairsLine('paymentdate|Date||provider|Sitter||amount|Amount||paymenttype|Type||note|Notes');
	$colClasses = array('amount' => 'dollaramountcell'); 
	$headerClass = array('amount' => 'dollaramountheader'); //'dollaramountheader'

	$columnSorts = array('paymentdate'=>null,'amount'=>null, 'paymenttype'=>null, 'provider'=>null);
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}

	$paymentTypes = explodePairsLine('regular|Regular||adhoc|Special');
	foreach($allPayments as $payment) {
		$row = $payment;
		$row['amount'] = fauxLink(dollarAmount($payment['amount']), "paymentDetail({$payment['paymentid']})", 1);
		$row['paymenttype'] = $paymentTypes[$payment['paymenttype']];
		$row['paymentdate'] = shortDate(strtotime($payment['paymentdate']));
		$rows[] = $row;
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=5 id='detail_{$payment['paymentid']}' class='selectedbackground'></td></tr>");
	}
	$totalPayments = dollarAmount($totalPayments);
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td class='sortableListCell' style='font-weight:bold;text-align:right;'>Total Payments</td><td class='dollaramountcell'>$totalPayments</td></td></tr>");
	
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}
?>
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function paymentDetail(paymentid) {
	var td = document.getElementById('detail_'+paymentid);
	if(td.innerHTML) {
		$.fn.removeClass("selectedbackground");
		td.innerHTML = '';
	}
	else {
		$.fn.addClass("selectedbackground");
		ajaxGet("payments-detail.php?paymentid="+paymentid, 'detail_'+paymentid);
	}
}

function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var providers = document.getElementById('providers');
	providers = providers.options[providers.selectedIndex].value;
	document.location.href='reports-payroll.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end+'&providers='+providers;
}
</script>