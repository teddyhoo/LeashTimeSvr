<? // reports-ccpayments-internal.php

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
		
$pageTitle = "Credit Card Payment INTERNAL Report";


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
labeledCheckbox('Show Errors Only', 'showErrorsOnly', $showErrorsOnly, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
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

	header("Content-Disposition: attachment; filename=CreditCardPayment-Report-$specificType.csv ");
	dumpCSVRow("Credit Card Payment INTERNAL Report: ".str_replace('-', ' ', $specificType));
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
}
else {
	$windowTitle = 'Credit Card Payment INTERNAL Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Payments Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".date('m/d/Y H:i')."</p>";
}

	$filter = $clients == -1 ? '' : (
									$clients == 0 ? "AND clientptr IN (SELECT clientid FROM tblclient WHERE active = 1)" : (
									$clients == -2 ? "AND clientptr IN (SELECT clientid FROM tblclient WHERE active = 0)" : $clients));
	$ccpayments = fetchAssociations($sql = 
		"SELECT itemptr as ccptr, note, time, operation, last4, company, clientptr, CONCAT_WS(' ', company, last4) as card
			FROM tblchangelog
			LEFT JOIN tblcreditcard ON ccid = itemptr
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE itemtable = 'ccpayment' 
			AND operation IN ('p' , 'f')
			AND tblchangelog.time >= '$pstart 00:00:00' AND tblchangelog.time <= '$pend 23:59:59'
			ORDER BY clientptr, time");
	$achpayments = fetchAssociations($sql = 
		"SELECT itemptr as ccptr, note, time, operation, last4, clientptr, CONCAT_WS(' ', 'ACH', last4) as card
			FROM tblchangelog
			LEFT JOIN tblecheckacct ON acctid = itemptr
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE itemtable = 'achpayment' 
			AND operation IN ('p' , 'f')
			AND tblchangelog.time >= '$pstart 00:00:00' AND tblchangelog.time <= '$pend 23:59:59'
			ORDER BY clientptr, time");
	$ccpayments = array_merge($ccpayments, $achpayments);
	usort($ccpayments, 'cmpTime');
//echo $sql;	
	foreach($ccpayments as $i => $ccpayment) {
		$errors = null;
		$approved = false;
		$transid = false;
		$credit = null;
		if(!$ccpayment['last4']) $errors[] = "card info is missing: card id #ccptr";
		if(!($clientptr = $ccpayment['clientptr'])) {
			$errors[] = "Bad cc payment: ".print_r($ccpayment, 1).'<p>';
			$ccpayments[$i]['clientptr'] = 0;
		}
		
		$note = explode('|', $ccpayment['note']);
		//Array ( [0] => Error-Transaction was Rejected by Gateway [1] => Amount: [2] => Trans: [3] => Gate:Solveras [4] => ErrorID: ) 
		
		if(count($note) < 3) $errors[] = "bad note: [{$ccpayment['note']}]";
		if(count($note) > 0) {
			$ccpayment = $note[0];
			$approved = strpos($ccpayment, 'Approved-') !== FALSE;
			if(!$approved) {
				$errors[] = $ccpayment;
//echo "Approved [$approved]";exit;		
//if($errors) {echo print_r($note, 1).'<p>';	exit;	}
			}
			else $ccpayments[$i]['ccpayment'] = substr($ccpayment, strlen('Approved-'));
		}
		//echo print_r($ccpayment, 1).'<p>';
		if($approved && count($note) > 1) {
			$transid = $note[1];
			$ccpayments[$i]['transid'] = $transid;
			$credit = fetchAssociationsKeyedBy(
				"SELECT * FROM tblcredit 
					WHERE externalreference IN ('CC: $transid', 'ACH: $transid')", 'creditid');
			if(count($credit) > 1) $ccpayments[$i]['multpayments'][] = array_keys($credit);
			else if(count($credit) == 1) {
				$ccpayments[$i]['credit'] = current($credit);
				if(!$clientptr) $ccpayments[$i]['clientptr'] = 
					$ccpayments[$i]['credit']['clientptr']
					? $ccpayments[$i]['credit']['clientptr']
					: 0;
			}
		}
		$clients[$ccpayments[$i]['clientptr']] = 0;
		if(count($transid) > 3) {
			$gateway = $note[3];
			if(strpos($gateway, 'Gate:') === FALSE) $errors[] = 'no gateway';
			else $ccpayments[$i]['gateway'] = substr($gateway, strlen('Gate:'));
		}
		if($errors) {
			$ccpayments[$i]['errors'] = $errors;
		}
//if($errors) echo print_r($ccpayments[$i], 1).'<p>';	
	}
//print_r($clients);	
	if(isset($clients[0])) {
		$includeUnknown = '<font color=red>Client Unknown</font>';
		unset($clients[0]);
	}
	//echo "BANG!".print_r($clients, 1);
	if($clients) $clients = getClientDetails(array_keys((array)$clients), null, 'sorted');
	if($includeUnknown) $clients = array_merge(array(0 => array('clientname'=>$includeUnknown) ), (array)$clients);
//echo ">>>";print_r($allBalances);	
	
	if($csv) paymentsCSV($clients);
	else paymentsTable($clients, $sort);
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

function cmpTime($a, $b) {
	return strcmp($a['time'], $b['time']);
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



function nameSort($a, $b) {
	return strcmp($a['clientname'], $b['clientname']);
}

function uninvoicedSort($a, $b) {
	if($a['uninvoiced'] == $b['uninvoiced'])  return strcmp($a['sortname'], $b['sortname']);
	return $a['uninvoiced'] < $b['uninvoiced'] ? -1 : 1;
}

function paymentsCSV($clients) {
	global $ccpayments, $pstart, $pend, $showErrorsOnly;

	$columns = explodePairsLine('clientname|Client||time|Date||transid|Transaction||card|Card||ccpayment|CC Amount'
															.'||creditptr|Payment||errormsgs|Errors');
	dumpCSVRow($columns);
	foreach((array)$clients as $clientptr => $client) {
		foreach($ccpayments as $ccpayment) {
			if($ccpayment['clientptr'] != $clientptr) continue;
			$row = $ccpayment;
			$row['clientname'] = $client['clientname'];
			if($ccpayment['multpayments']) $row['creditptr'] = '<font color=red>'.join(', ', $ccpayment['multpayments']).'</font>';
			if(!($credit = $ccpayment['credit']) && $ccpayment['operation'] == 'p') $row['creditptr'] = '<font color=red>No payment found.</font>';
//else $row['creditptr'] = print_r($ccpayment, 1);
//print_r($credit);exit;
			$errors = $ccpayment['errors'];
			if($credit) {
				$row['creditptr'] = $credit['creditid'];
				if($ccpayment['ccpayment'] && $credit['amount'] != $ccpayment['ccpayment']) $errors[] = "CC amount does not match LT payment: {$credit['amount']}";
				if($ccpayment['clientptr'] && $credit['clientptr'] != $ccpayment['clientptr']) 
					$errors[] = "CC client ({$ccpayment['clientptr']}) does not match LT client {$credit['clientptr']}".print_r($credit, 1);
				//else $errors[] = "LT client: {$credit['clientptr']}"; 
			}
			if($errors) $row['errormsgs'] = join(', ', $errors);
			if($showErrorsOnly && !$errors && $ccpayment['credit']) continue;
			dumpCSVRow($row, array_keys($columns));
		}
	}
}

function paymentsTable($clients, $sort) {
	global $ccpayments, $pstart, $pend, $showErrorsOnly;
	$rows = $allBalances;

	$columns = explodePairsLine('clientname|Client||time|Date||transid|Transaction||card|Card||ccpayment|CC Amount'
															.'||creditptr|Payment||errormsgs|Errors');
	//$colClasses = array('amount' => 'dollaramountcell', 'uninvoiced' => 'dollaramountcell', 'credit' => 'dollaramountcell', 'charge' => 'dollaramountcell'); 
	//$headerClass = array('amount' => 'dollaramountheader', 'uninvoiced' => 'dollaramountheader'); //'dollaramountheader'

	//$columnSorts = array('amount'=>null, 'client'=>null);
	//if($sort) {
	//	$sort = explode('_', $sort);
	//	$columnSorts[$sort[0]] = $sort[1];
	//}
	foreach((array)$clients as $clientptr => $client) {
		foreach($ccpayments as $ccpayment) {
			if($ccpayment['clientptr'] != $clientptr) continue;
			$row = $ccpayment;
			$row['clientname'] = $client['clientname'];
			if($ccpayment['multpayments']) $row['creditptr'] = '<font color=red>'.join(', ', $ccpayment['multpayments']).'</font>';
			if(!($credit = $ccpayment['credit']) && $ccpayment['operation'] == 'p') $row['creditptr'] = '<font color=red>No payment found.</font>';
//else $row['creditptr'] = print_r($ccpayment, 1);
//print_r($credit);exit;
			$errors = $ccpayment['errors'];
			if($credit) {
				$row['creditptr'] = $credit['creditid'];
				if($ccpayment['ccpayment'] && $credit['amount'] != $ccpayment['ccpayment']) $errors[] = "CC amount does not match LT payment: {$credit['amount']}";
				if($ccpayment['clientptr'] && $credit['clientptr'] != $ccpayment['clientptr']) 
					$errors[] = "CC client ({$ccpayment['clientptr']}) does not match LT client {$credit['clientptr']}".print_r($credit, 1);
				//else $errors[] = "LT client: {$credit['clientptr']}"; 
			}
			if($errors) $row['errormsgs'] = '<font color=red>- '.join('<br>- ', $errors).'</ul></font>';
			if($showErrorsOnly && !$errors && $ccpayment['credit']) continue;
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