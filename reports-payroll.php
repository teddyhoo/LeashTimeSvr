<? // reports-payroll.php

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
extract(extractVars('start,end,print,providers,sort,csv,paymentsonly', $_REQUEST));
$includeReportedHours = staffOnlyTEST() || dbTEST('furrygodmother');
		
$pageTitle = "Payroll Report";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a> - <a href='reports-payroll-projection.php'>Projected Payroll</a>";	
	include "frame.html";
	// ***************************************************************************
?>
<style>
.dateRow { font-weight: bold; }
.selectedbackground { background: LemonChiffon; }
</style>

	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('Show payments to sitters dated from:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('through:', 'end', $end);
	
if(TRUE) {
	require_once "field-utils.php";
	foreach(explode(',', 'Month-2,Last Month,This Month') as $intervalLabel) {
		list($bStart, $bEnd, $bMonth) = dateIntervalFromLabel($intervalLabel);
		echo " ";
		echoButton('', $bMonth, "setIntervalAndGenerate(\"$bStart\", \"$bEnd\")");
	}
}	
	hiddenElement('csv', '');
	hiddenElement('paymentsonly', '');
?>
	</td></tr>
	<tr><td colspan=2>
<?
$options = array('All Sitters'=>-1);
//$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), providerid FROM tblprovider ORDER BY lname, fname"));
$options = array_merge($options, getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=true));
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

	function setIntervalAndGenerate(start, end) {
		document.getElementById('start').value = start;
		document.getElementById('end').value = end;
		genReport();
	}
	
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
<? if(mattOnlyTEST() || dbTEST('pawsitivelypooches')) { ?>
			var paymentsonly = confirm('Click OK to omit payment subitems and include only payments.');
			document.getElementById('paymentsonly').value = paymentsonly ? 1 : 0;
<? } ?>
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
		payrollCSV($start, $end, $sort, $paymentsonly);
		exit;
	}

	else {
		payrollTable($start, $end, $sort);
		if(TRUE || mattOnlyTEST()) {
			echo "<hr>";
			echo "<table width='90%'><tr>";
			echo "<td style='vertical-align: top'>";
			echo "<h3>Sitter Totals</h3>";
			sitterTotalsTable();
			echo "</td>";
			echo "<td style='vertical-align: top'>";
			echo "<h3>Monthly Totals</h3>";
			monthlyTotalsTable();
			echo "</td>";
			echo "</tr></table>";
		}
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
		$payment['provider'] = $payment['provider'] ? $payment['provider'] : 'Unassigned';
		$payment['sortname'] = $payment['sortname'] ? $payment['sortname'] : '-99';
		$clientSorts[$payment['provider']] = $payment['sortname'];
		$allPayments[$payment['paymentid']] = $payment;
	}
	return $allPayments;
}

function payrollCSV($start, $end, $sort, $paymentsonly=false) {
	global $includeReportedHours;
	require "pay-fns.php";
	global $allPayments, $totalPayments, $clientNames, $services, $surchargeTypes, $hours;
	$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname, CONCAT('[',clientid,']')) FROM tblclient");
	$surchargeTypes = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$hours = fetchKeyValuePairs("SELECT servicetypeid, hours FROM tblservicetype WHERE hours IS NOT NULL");
	$providers = getProviderNames();
	$columns = explodePairsLine('date|Date||provider|Sitter||amount|Amount||transactionid|Client / Transaction ID||tod_ending|Ending or Time of Day||service|Service||servicecode|Service Code||hours|Hours||note|Notes');
	if($includeReportedHours) {
		unset($columns['note']);
		$columns['reportedhours'] = 'Reported Hours';
		$columns['note'] = 'Notes';
	}
	if($paymentsonly) $columns = explodePairsLine('date|Date||provider|Sitter||amount|Amount||paymenttype|Type||tod_ending|Ending||transactionid|Check #||note|Notes');

	dumpCSVRow($columns);
	//$paymentTypes = explodePairsLine('regular|Regular||adhoc|Special');
	$paymentTypes = explodePairsLine('regular|Regular||adhoc|Special');
	foreach($allPayments as $payment) {
		//$row = $payment;
		$row['date'] = shortDate(strtotime($payment['paymentdate']));
		$row['provider'] = $providers[$payment['providerptr']];
		$row['amount'] = $payment['amount'];
		$row['transactionid'] = $payment['transactionid'];
		$row['tod_ending'] = $payment['enddate'] ? shortDate(strtotime($payment['enddate'])) : '';;
		$row['service'] = '~~PAYMENT~~';
		$row['paymenttype'] = $paymentTypes[$payment['paymenttype']];

		$row['servicecode'] = '';
		$row['note'] = $payment['note'];
		dumpCSVRow($row, $columns);
		if(!$paymentsonly) paymentDetailCSV($payment['paymentid'], $row['provider']);
	}
}

function paymentDetailCSV($paymentid, $providername) {
	global $clientNames, $services, $surchargeTypes, $hours, $includeReportedHours;
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
			if($includeReportedHours) {$row['reportedhours'] = reportedVisitDurationInHours($item['appointmentid']);}
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

function reportedVisitDurationInHours($apptid) {
	$tracks = fetchKeyValuePairs(
		"SELECT event, date 
			FROM tblgeotrack 
			WHERE appointmentptr = $apptid
				AND event IN ('completed', 'arrived')");
	if(!$arrived = $tracks['arrived']) return 0;
	if(!($completed = $tracks['completed'])) {
		$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $apptid LIMIT 1");
		$completed = $tracks['completed'] ? $tracks['completed'] : $appt['completed'];
	}
	return (strtotime($completed) - strtotime($arrived)) / 3600;
}



function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if($cols) {
		foreach($cols as $k=>$v) $newRow[$k] = $row[$k];
		$row = $newRow;
	}
	if(is_array($row)) echo join(',', array_map('csv',$row))."\n";
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}

function sitterTotalsTable() {
	global $allPayments, $totalPayments;
	foreach($allPayments as $payment) {
		$totals[$payment['provider']] += $payment['amount'];
		$providerSorts[$payment['provider']] = $payment['sortname'];
	}
	asort($providerSorts);
	foreach($providerSorts as $provider => $sortname)
		$rows[] = array('provider'=>$provider, 'total' => dollarAmount($totals[$provider]));
	$colClasses = array('total' => 'dollaramountcell'); 
	$headerClass = array('total' => 'dollaramountheader dollaramountcell'); //'dollaramountheader'
	$columns = explodePairsLine("provider|Sitter||total|Total");
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}

function monthlyTotalsTable() {
	global $allPayments, $totalPayments;
	foreach($allPayments as $payment) {
		$dateLabel = date('M Y', strtotime($payment['paymentdate']));
		$totals[$dateLabel] += $payment['amount'];
		$dateSorts[$dateLabel] = $payment['paymentdate'];
	}
	asort($dateSorts);
	foreach($dateSorts as $dateLabel => $date)
		$rows[] = array('month'=>$dateLabel, 'total' => dollarAmount($totals[$dateLabel]));
	$columns = explodePairsLine("month|Month||total|Total");
	$colClasses = array('total' => 'dollaramountcell'); 
	$headerClass = array('total' => 'dollaramountheader dollaramountcell'); //'dollaramountheader'
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}

function payrollTable($start, $end, $sort) {
	global $allPayments, $totalPayments;

	$columns = explodePairsLine('paymentdate|Date||provider|Sitter||amount|Amount||paymenttype|Type||transactionid|Check # / Trans ID||note|Notes');
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
		$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=5 id='detail_{$payment['paymentid']}'></td></tr>");
	}
	$totalPayments = dollarAmount($totalPayments);
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td>&nbsp;</td><td class='sortableListCell' style='font-weight:bold;text-align:right;'>Total Payments</td><td class='dollaramountcell'>$totalPayments</td></td></tr>");
	
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function paymentDetail(paymentid) {
	var sectionID = 'detail_'+paymentid;
	var td = document.getElementById(sectionID);
	if(td.innerHTML) {
		$('#'+sectionID).removeClass("selectedbackground");
		td.innerHTML = '';
	}
	else {
		$('#'+sectionID).addClass("selectedbackground");
		<? if(TRUE || mattOnlyTEST()) { ?>
		ajaxGetAndCallWith("payments-detail.php?paymentid="+paymentid, fillInDetail, 'detail_'+paymentid);
		<? } else { ?>
		ajaxGet("payments-detail.php?paymentid="+paymentid, 'detail_'+paymentid);
		<? } ?>
	}
}

function fillInDetail(destinationId, content) {
	var paymentId = destinationId.split('_');
	paymentId = paymentId[1];
	document.getElementById(destinationId).innerHTML = "<a class='fauxlink' onClick='paymentDetail("+paymentId
		+")'><center><img src='art/up-black.gif' title='Hide detail' width=40 height=20></a></center><p>";
	document.getElementById(destinationId).innerHTML += content;
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