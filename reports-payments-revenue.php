<? // reports-payments-revenue.php

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

$pageTitle = "Year End Report";
$year = $_POST['year'];
$payrollYears = 
	array(
		fetchRow0Col0("SELECT paymentdate FROM tblproviderpayment ORDER BY paymentdate ASC LIMIT 1"),
		fetchRow0Col0("SELECT paymentdate FROM tblproviderpayment ORDER BY paymentdate DESC LIMIT 1")
		);
$paymentYears = 
	array(
		fetchRow0Col0("SELECT issuedate FROM tblcredit WHERE payment = 1 AND voided IS NULL ORDER BY issuedate ASC LIMIT 1"),
		fetchRow0Col0("SELECT issuedate FROM tblcredit WHERE payment = 1 AND voided IS NULL ORDER BY issuedate DESC LIMIT 1")
		);
		
$range = array(
		(strcmp($payrollYears[0], $paymentYears[0]) == -1 ? $payrollYears[0] : $paymentYears[0]),
		(strcmp($payrollYears[1], $paymentYears[1]) == 1 ? $payrollYears[1] : $paymentYears[1]));
$range = array(substr($range[0], 0, 4), substr($range[1], 0, 4));

if($_POST['year']) {
	$sitterPay = fetchKeyValuePairs(
			"SELECT providerptr, SUM(amount) FROM tblproviderpayment WHERE paymentdate LIKE '$year-%' GROUP BY providerptr", 1);
	$sql = "SELECT providerptr, amount, paymentdate FROM tblproviderpayment WHERE paymentdate LIKE '$year-%'";
	foreach(fetchAssociations($sql, 1) as $pmt)
		$sitterPayMonths[$pmt['providerptr']][substr($pmt['paymentdate'],5,2)] += $pmt['amount'];

	$sitterPaymengCounts = fetchKeyValuePairs(
			"SELECT providerptr, COUNT(*) FROM tblproviderpayment WHERE paymentdate LIKE '$year-%' GROUP BY providerptr", 1);

	$sitters = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(',', lname, fname) FROM tblprovider ORDER BY lname, fname");

	foreach($sitters as $provid => $nm) {
		if(!$sitterPay[$provid]) continue;
		$rows[$provid] = array('name'=>$nm."<br><span style='font-size:0.9em'>({$sitterPaymengCounts[$provid]} payments)</span>", 'pay'=>dollarAmount($sitterPay[$provid]));
		$totalPay += $sitterPay[$provid];
	}
	
	$paymentSum = fetchRow0Col0("SELECT SUM(amount) FROM tblcredit WHERE payment = 1 AND voided IS NULL AND issuedate LIKE '$year-%'");
	$paymentCount = fetchRow0Col0("SELECT COUNT(*) FROM tblcredit WHERE payment = 1 AND voided IS NULL AND issuedate LIKE '$year-%'");

	$gratuitySum = fetchRow0Col0("SELECT SUM(amount) FROM tblgratuity WHERE issuedate LIKE '$year-%'");
	$gratuityCount = fetchRow0Col0("SELECT COUNT(*) FROM tblgratuity WHERE issuedate LIKE '$year-%'");
}	
	

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a></a>";	
	include "frame.html";
	// ***************************************************************************
?>
<style>
.dateRow { font-weight: bold; }
.selectedbackground { background: lightyellow; }
.narrowdollaramountcell {
  font-size: 1.0em; // 1.05em;
  padding-bottom: 4px; 
  padding-right: 4px; 
  border-collapse: collapse;
	text-align: right;
	vertical-align: top;
}

</style>

	<form name='reportform' method='POST'>
<?
	for($i = $range[0]; $i <= $range[1]; $i++) $years[$i] = $i;
//print_r($years);
	labeledSelect("Show payments for year:", 'year', $value=$year, $options=$years, $labelClass=null, $inputClass=null, $onChange=null, $noEcho=false);
	echoButton('x', 'Show', $onClick='document.reportform.submit()', $class='', $downClass='', $noEcho=false, $title=null);
	//calendarSet('Show payments to sitters dated from:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	hiddenElement('csv', '');
?>
<?
	/*echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()"); */
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
//echo ">>>";print_r($allPayments);	
	
	if($csv) {
		payrollCSV($start, $end, $sort, $paymentsonly);
		exit;
	}
}
if(!$print && !$csv){
	if($rows) {
		$months = explode('|', 'X|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec');
		$columns = explodePairsLine("name|Sitter||pay|$year Pay");
		$colClasses['pay'] = 'narrowdollaramountcell';
		for($m=1; $m<=12; $m++) {
			$columns[sprintf('%02d', $m)] = $months[$m];
			$colClasses[sprintf('%02d', $m)] = 'narrowdollaramountcell';
		}
		echo "<p>";
		if(1 || mattOnlyTEST()) {
			foreach($rows as $provid => $row) {
				for($m=1; $m<=12; $m++) {
					$rows[$provid][sprintf('%02d', $m)] = $sitterPayMonths[$provid][sprintf('%02d', $m)];
				}
			}
		}
		tableFrom($columns, $rows, 'width=40% border=1 bordercolor=black', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
		echo "<p>";
		echo "<p><span class='fontSize1_1em'><b>Total $year sitter pay: </b>".dollarAmount($totalPay);	
		echo "<p><span class='fontSize1_1em'><b>Total $year client payments (".number_format($paymentCount, 0)."): </b>".dollarAmount($paymentSum);	
		
		if(mattOnlyTEST())
		echo "<p><span class='fontSize1_1em'><b>Total $year client gratuities (".number_format($gratuityCount, 0)."): </b>".dollarAmount($gratuitySum);	
	}
	
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
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

</script>