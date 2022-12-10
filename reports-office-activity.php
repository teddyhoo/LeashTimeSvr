<? // reports-office-activity.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "appointment-fns.php";
require_once "request-fns.php";
require_once "client-flag-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,staffid,sort,csv', $_REQUEST));

		
$pageTitle = "Office Activity Report";


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
$options = array('All Office Staff'=>-1);
$managers = getManagers(null, true);
foreach($managers as $userid => $user) $managers[$userid] = ($user['name'] ? $user['name'] : $user['loginid']);
//print_r($managers);
$options = array_merge($options, array_flip($managers));
labeledSelect('Staff:', 'staffid', $staffid, $options);
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
	header("Content-Disposition: inline; filename=Office Activity.csv ");
	dumpCSVRow('Payments Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Office Activity';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Office Activity Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}
if($start && $end) { //echo "start[$start] end[$end]";
	fetchLogEntries($start, $end, $staffid, $sort);
//echo ">>>";print_r($allPayments);	
	
	if($csv) paymentsCSV($start, $end, $sort);
	else logTable($start, $end, $sort);
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

function fetchLogEntries($start, $end, $staffid, $sort) {
	global $logEntries, $managers;
	$start = date('Y-m-d 00:00:00', strtotime($start));
	$end = date('Y-m-d 23:59:59', strtotime($end));
	$filter = $staffid && $staffid != -1 ? "AND user = $staffid" : "";
	if(!$sort) $sort = 'time_ASC';
	$sorts = $sort ? explode('_', $sort) : '';
	if($sorts[0] == 'time') $sorts = "{$sorts[0]} {$sorts[1]}";
	else if($sorts[0] == 'staffname') $sorts = null;
	if($sorts) $sorts = "ORDER BY $sorts";
	
	$sql = "SELECT *
					FROM tblchangelog 
					WHERE time >= '$start' AND time <= '$end' AND user != 0 
					$filter
					AND user IN (".join(',', array_keys($managers)).")
					AND itemtable NOT IN ('reassignment')
					$sorts";
	$result = doQuery($sql);
	$tables = 'tblclientrequest|Request||tblappointment|Visit||tblclient|Client||flags|Flags'
						.'||tblservicepackage|Non Recurring Schedule||tblrecurringpackage|Recurring Schedule'
						.'||tblmessage|Message||reassignment|Job Reassignment';
	$tables = explodePairsLine($tables);
	$services = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype", 1);
	$readOnlyVisits = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$bizFlags = getBizFlagList();
	$billingFlags = (array)getBillingFlagList();
  while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$row['staffname'] = $managers[$row['user']];
		$row['object'] = $tables[$row['itemtable']] ? $tables[$row['itemtable']] : $row['itemtable'];
		if(staffOnlyTEST()) {
			if($row['object'] == 'Visit') {
				$url = $readOnlyVisits ? "appointment-view.php?id={$row['itemptr']}" : "appointment-edit.php?id={$row['itemptr']}";
				$appt = getAppointment($row['itemptr'], 'withnames');
				$title = "{$appt['client']} {$appt['timeofday']} {$services[$appt['servicecode']]} - {$appt['provider']}";
				$row['itemptr'] = 
					fauxLink($row['itemptr'], "openConsoleWindow(\"appteditor\", \"$url\",600,600)", 1, "Visit: $title");
			}
			else if($row['object'] == 'Request') {
				$url = "request-edit.php?id={$row['itemptr']}";
				$req = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$row['itemptr']} LIMIT 1", 1);
				$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '{$req['clientptr']}' LIMIT 1", 1);
				//print_r($req);
				$note = null;
				if($req['requesttype'] == 'cancel') {
					$note = getAllExtraFieldAssociations($req);
					if($note = $note['visitdetails']['value']) { // html
						$note = str_replace('</td><td>', " / ", $note); // html
						$note = strip_tags($note);
					}
				}
				$title = "$clientname {$req['received']} {$req['requesttype']} - $note";
				$row['itemptr'] = 
					fauxLink($row['itemptr'], "openConsoleWindow(\"requestteditor\", \"$url\",600,600)", 1, "Request: $title");
			}
			else if($row['object'] == 'Client') {
				$url = "client-view.php?id={$row['itemptr']}";
				$client = fetchFirstAssoc("SELECT CONCAT_WS(' ', fname, lname) as name, active FROM tblclient WHERE clientid = '{$row['itemptr']}' LIMIT 1", 1);
				$clientname = $client['name'].($client['active'] ? '' : ' (inactive)');
				$title = "$clientname";
				$row['itemptr'] = 
					fauxLink($row['itemptr'], "openConsoleWindow(\"requestteditor\", \"$url\",600,600)", 1, "Request: $title");
			}
			else if($row['object'] == 'Flags') {
				$url = "client-view.php?id={$row['itemptr']}";
				$client = fetchFirstAssoc($sql = "SELECT CONCAT_WS(' ', fname, lname) as name, active FROM tblclient WHERE clientid = '{$row['itemptr']}' LIMIT 1", 1);
				$clientname = $client['name'].($client['active'] ? '' : ' (inactive)');
				$note = explode(':', $row['note']); //Client:1,5,15,22 Billing: 2.3
				$cflags = explode(',', trim(substr($note[1], 0, strpos($note[1], 'Billing'))));
				$bflagd = $cflagd = null;
				foreach($cflags as $flg) {
					$flg =  $bizFlags[$flg];
					$cflagd[] = "({$flg['flagid']}) {$flg['title']} [{$flg['src']} ]";
				}
				$cflagd = $cflagd ? join(', ', $cflagd) : 'none';
				$bflags = explode(',', trim($note[3]));
				foreach($bflags as $flg) {
					$flg =  $billingFlags[$flg];
					$bflagd[] = "({$flg['flagid']}) {$flg['title']} [{$flg['src']} ]";
				}
				$bflagd = $bflagd ? join(', ', $bflagd) : 'none';
				$title = "$clientname";
				$row['itemptr'] = 
					fauxLink($row['itemptr'], "openConsoleWindow(\"requestteditor\", \"$url\",600,600)", 1, "Changed flags on: $title");
				$row['note'] = "Client flags: $cflagd<br>Billing flags: $bflagd<br>{$row['note']}";
			}
		}
		$logEntries[] = $row;
	}
	if($logEntries) {
		$sorts = $sort ? explode('_', $sort) : '';
		if($sorts[0] == 'staffname') {
			if(strtoupper($sorts[1]) == 'DESC') usort($logEntries, 'cmpStaffnameR');
			else usort($logEntries, 'cmpStaffname');
		}
	}
	return $logEntries;
}

function cmpStaffname($a, $b) {
	$r = strcmp($a['staffname'], $b['staffname']);
	return $r ? $r : strcmp($a['time'], $b['time']);
}

function cmpStaffnameR($a, $b) {
	$r = strcmp($a['staffname'], $b['staffname']);
	return $r ? 0-$r : strcmp($a['time'], $b['time']);
}

function logTable($start, $end, $sort) {
	global $logEntries;

	$columns = explodePairsLine('time|Time||object|Object||itemptr|ID||operation|Op||staffname|Staff||user|Staff ID||note|Note');
	$columnSorts = array('time'=>null,'staffname'=>null);
	if($sort) {
		$sort = explode('_', $sort);
		$columnSorts[$sort[0]] = $sort[1];
	}

	$rowClass = 'futuretaskEVEN';
	foreach($logEntries as $row) {
		$rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';

		$row['time'] = shortDateAndTime(strtotime($row['time']));
		$rows[] = $row;
		$rowClasses[] = $rowClass;
	}
	echo count($logEntries)." entries found.";
	tableFrom($columns, $rows, 'width=100% border=1', $class='fontSize1_2em', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
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
	var staffid = '<?= $staffid ?>';
	document.location.href='reports-office-activity.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end+'&staffid='+staffid;
}
</script>
<? }