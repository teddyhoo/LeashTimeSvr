<? // reports-reminders.php

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

		
$pageTitle = "Delivered Reminders <font color=red></font>";


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
<?
//$options = array('All Sitters'=>-1);
//$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), providerid FROM tblprovider ORDER BY lname, fname"));
//labeledSelect('Providers:', 'providers', $providers, $options);
?>
	</td></tr>
	
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	//echoButton('', 'Print Report', "spawnPrinter()");
	//echo "&nbsp;";
	//if($_SESSION['staffuser'] || $db == 'db203pet') echoButton('', 'Download Spreadsheet', "genCSV()");
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
			openConsoleWindow('reportprinter', 'reports-payroll-projection.php?print=1&start='+start+'&end='+end+'&providers='+providers+'&sort='+'<?= $sort ?>', 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: attachment; filename=Payroll-Report.csv ");
	dumpCSVRow('Projected Payroll Report');
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
	$rows = fetchReminders($start, $end, $providers, $sort);
//echo ">>>";print_r($allPayments);	
	
	if($csv) {
		payrollCSV($start, $end, $sort);
		exit;
	}

	else {
	if($rows) {
		$columns = explodePairsLine('received|Date||subject|Subject||person|Person||resolution|Resolution');
		$columnSorts = array('received'=>1,'subject'=>1,'person'=>1,'resolution'=>1);
		tableFrom($columns, $rows, 'width=90%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	}
	else echo "No past reminders found.";
		
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

function fetchReminders($start, $end, $providers, $sort) {
	global $requests, $sortKey;
	$inRange = "AND received >= '".date('Y-m-d 00:00:00', strtotime($start))."' AND received <= '".date('Y-m-d 23:59:59', strtotime($end))."'"; //  AND note LIKE '%$pattern%'
	$requests = fetchAssociations($sql = "SELECT * FROM tblclientrequest WHERE requesttype = 'Reminder' $inRange ORDER BY received DESC");
	$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
	$providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider");
	foreach($requests as $request) {
		$received = shortDate(strtotime($request['received']));
		$resolution = $request['resolution'] ? $request['resolution'] : 'unresolved';
		$subject = fauxLink($request['street1'], "openRequest(\"{$request['requestid']}\")", 1);
		$persons = getPersons($request);
		$person = $request['clientptr'] ? $clients[$request['clientptr']] : (
							count($persons) > 1 || count($persons['client']) > 1 || count($persons['provider']) > 1
							? 'Various'
							: ($persons['client'] ? array('client', $persons['client'][0]) :
								($persons['provider'] ? array('provider', $persons['provider'][0]) : null)));
		if(is_array($person)) $person  = $person[0] == 'client' ? $clients[$person[1]] : $providers[$person[1]];
		$rows[] = array('subject'=>$subject, 'received'=>$received, 'resolution'=>$resolution, 'person'=>$person, 
										'sortdate'=>$request['received'], 'sortsubj'=>$request['street1']);
	}
	if($sort) {
		$sortParts = explode('_', $sort);
		$sortKey = $sortParts[0];
		if($sortKey == 'received') $sortKey = 'sortdate';
		else if($sortKey == 'subject') $sortKey = 'sortsubj';
		usort($rows, 'rowSort');
		if($sortParts[1] == 'desc') $rows = array_reverse($rows);
	}
	return $rows;
}

function rowSort($a, $b) {
	global $sortKey;
	return strcmp(strtoupper($a[$sortKey]), strtoupper($b[$sortKey]));
}
	

function getPersons($request) {
	$note = $request['note'];
	
	if(($start = strpos($note, '<persons>')) === FALSE) return;
	$end = strpos($note, '</persons>')+strlen('</persons>');
	$persons = (array)simplexml_load_string(substr($note, $start, $end-$start));
	foreach($persons as $k => $v) if(!is_array($v)) $persons[$k] = array($v);
	return $persons;
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
if(!$_SESSION['staffuser']) unset($columns['hours']);
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
		payProjectionDetailCSV($payment['paymentid'], $row['provider']);
	}
}

function payProjectionDetailCSV($paymentid, $providername) {
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
			$hoursVal = $hours[$item['servicecode']] == '00:00' ? '' : $hours[$item['servicecode']];
if($_SESSION['staffuser']) $row['hours'] = $hoursVal;
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
	//var providers = document.getElementById('providers');
	//providers = providers.options[providers.selectedIndex].value;
	document.location.href='reports-reminders.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end; //+'&providers='+providers
}
</script>