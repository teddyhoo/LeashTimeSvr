<? // reports-logins.php

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
extract(extractVars('start,find_sitters,find_clients,find_managers,find_dispatchers,print,providers,sort,csv,failuresonly', $_REQUEST));

		
$pageTitle = "Login Report <font color=red></font>";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************

?>

	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	if(!$start) $start = shortDate();
	if(!($find_sitters || $find_clients || $find_managers || $find_dispatchers)) {
		$find_sitters = 1;
	}
	calendarSet('For the period starting:', 'start', $start, null, null, true);
	echo " ";;
	labeledCheckBox('sitters', 'find_sitters', $find_sitters, null, null, null, 1);
	echo " ";;
	labeledCheckBox('clients', 'find_clients', $find_clients, null, null, null, 1);
	echo " ";;
	labeledCheckBox('managers', 'find_managers', $find_managers, null, null, null, 1);
	echo " ";;
	labeledCheckBox('dispatchers', 'find_dispatchers', $find_dispatchers, null, null, null, 1);
	echo " ";;
	labeledCheckBox('Login Failures Only', 'failuresonly', $failuresonly, null, null, null, 1);
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
	//echoButton('', 'Print Report', "spawnPrinter()");
	//echo "&nbsp;";
	//if($_SESSION['staffuser'] || $db == 'db203pet') echoButton('', 'Download Spreadsheet', "genCSV()");
?>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start','Starting Date');
	function genReport() {
		if(MM_validateForm(
				'start', '', 'R',
				'start', '', 'isDate')) document.reportform.submit();
	}
	function genCSV() {
		if(MM_validateForm(
				'start', '', 'R',
				'start', '', 'isDate')) {
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
			openConsoleWindow('reportprinter', 'reports-logins.php?print=1&start='+start+'&end='+end+'&providers='+providers+'&sort='+'<?= $sort ?>', 700,700);
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
if($start) {
	$rows = fetchLogins($start, $sort);
//echo ">>>";print_r($allPayments);	
	
	if($csv) {
		payrollCSV($start, $end, $sort);
		exit;
	}

	else {
	if($rows) {
		foreach($rows as $i => $row) $rowClasses[] = $row['success'] == 'Ok' ? '' : 'pink';
		$columns = explodePairsLine('displaytime|Login time||person|Name||role|Role||success|Success');
		$columnSorts = array('displaytime'=>1,'person'=>1,'role'=>1,'success'=>1);
		tableFrom($columns, $rows, 'width=90%', $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
	}
	else echo "No logins found.";
		
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

function fetchLogins($start, $sort) {
	global $logins, $sortKey, $find_sitters, $find_clients, $find_managers, $find_dispatchers, $failuresonly;
	$clients = fetchAssociationsKeyedBy("SELECT userid, CONCAT_WS(' ', fname, lname) as name,
																							CONCAT_WS(', ', lname, fname) as sortname
																							FROM tblclient", 'userid');
	$providers = fetchAssociationsKeyedBy("SELECT userid, CONCAT_WS(' ', fname, lname) as name,
																							CONCAT_WS(', ', lname, fname) as sortname 
																							FROM tblprovider", 'userid');
	require "common/init_db_common.php";
	//$inRange = "LastUpdateDate >= '".date('Y-m-d 00:00:00', strtotime($start))."'"; //  AND note LIKE '%$pattern%'
	$inRange = "UNIX_TIMESTAMP(LastUpdateDate) >= '".strtotime($start)."'"; //  AND note LIKE '%$pattern%'
	$failuresonly = $failuresonly ? "success = 0 AND" : '';
	$logins = fetchAssociations($sql = 
															"SELECT tbllogin.*, UNIX_TIMESTAMP(LastUpdateDate) as utime, 
																  userid, bizptr, rights, CONCAT_WS(' ', fname, lname) as name
															FROM tbllogin
															LEFT JOIN tbluser ON tbluser.loginid = tbllogin.loginid
															WHERE $failuresonly $inRange AND bizptr = {$_SESSION['bizptr']} 
															ORDER BY LastUpdateDate DESC");
	//if(staffOnlyTEST()) echo "<hr>$start: $sql<hr>";

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "$sql<p>";print_r($logins);}															
	$failures = explodePairsLine("0|Ok||P|Bad password||U|Unknown user||I|Inactive User||R|RightsMissing||F|No Business found||B|Business inactive||M|Missing organization||O|Organization inactive||D|Logins disabled for this role");
	foreach($logins as $login) {
		$universalTime = $login['utime'];
		$time = shortDateAndTime($universalTime, 'mil');
		$displaytime = shortDateAndTime($universalTime);
		$success = $login['FailureCause'] ? $failures[$login['FailureCause']] : 'Ok';
		$role = $login['rights'][0];
		$name = $login['name'];
		if($clients[$login['userid']]) {
			if(!$find_clients) continue;
			$role = 'client';
			$person = $clients[$login['userid']]['name'];
			$sortname = $clients[$login['userid']]['sortname'];			
		}
		else if($providers[$login['userid']]) {
			if(!$find_sitters) continue;
			$role = 'sitter';
			$person = $providers[$login['userid']]['name'];
			$sortname = $providers[$login['userid']]['sortname'];
		}
		else if($role == 'o') {
			if(!$find_managers) continue;
			$role = 'manager';
			$person = $name;
		}
		else if($role == 'd') {
			if(!$find_dispatchers) continue;
			$role = 'dispatcher';
			$person = $name;
		}
		$rows[] = array('time'=>$time, 'displaytime'=>$displaytime, 'person'=>$person, 'success'=>$success, 'role'=>$role, 
										'sortdate'=>$login['LastUpdateDate'], 'sortname'=>$sortname);
	}
	if($sort) {
		$sortParts = explode('_', $sort);
		$sortKey = $sortParts[0];
		if($sortKey == 'time') $sortKey = 'time';
		else if($sortKey == 'name') $sortKey = 'sortname';
		usort($rows, 'rowSort');
		if($sortParts[1] == 'desc') $rows = array_reverse($rows);
	}
	return $rows;
}

function rowSort($a, $b) {
	global $sortKey;
	return strcmp(strtoupper($a[$sortKey]), strtoupper($b[$sortKey]));
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
	document.location.href='reports-logins.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end; //+'&providers='+providers
}
</script>