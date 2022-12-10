<? // reports-prospect-latency.php
// copied from reports-all-payments-internal.php
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

if(!$start) $start =  date('1/1/Y');
$pstart = date('Y-m-d', strtotime($start));
if(!$end) $end = date('m/d/Y');
$pend = date('Y-m-d', strtotime($end));
		
$pageTitle = "Prospect Latency Report";

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	echo "Latency is defined as the time between receipt of a prospect request, and the client's first (uncanceled) visit.";

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
calendarSet('For <i>earliest</i> prospect requests received period starting:', 'start', $start, null, null, true, 'end');
echo "&nbsp;";
calendarSet('and ending:', 'end', $end);
echo "&nbsp;";
//labeledCheckbox('Show Errors Only', 'showErrorsOnly', $showErrorsOnly, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
hiddenElement('csv','');
?>
	</td></tr>
	
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	//echoButton('', 'Print Report', "spawnPrinter()");
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
		
		/*
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var clients = '<?= $_REQUEST['clients'] ?>';
	document.location.href='reports-all-payments.php?sort='+sortKey+'_'+direction
		+'&clients='+clients
		+'&start='+start
		+'&end='+end;
		
		*/
		
		
		
		var clients = document.getElementById('clients');
		clients = clients.options[clients.selectedIndex].value;
		openConsoleWindow('reportprinter', 'reports-all-payments.php?print=1&clients='+clients, 700,700);
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
	header("Content-Disposition: attachment; filename=Prospect-latency.csv ");
	dumpCSVRow("Period: $start - $end");
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
}






if($start) $dateRange[] = "received >= '$pstart'";
if($end) $dateRange[] = "received <= '$pend'";
if($dateRange) $dateRange = "AND (".join(' AND ', $dateRange).")";


$firstContacts = fetchKeyValuePairs($sql = 
	"SELECT clientptr, received 
		FROM tblclientrequest 
		WHERE requesttype = 'Prospect' AND clientptr IS NOT NULL $dateRange
		ORDER BY received DESC", 1);
//echo "$sql<p>".print_r($firstContacts, 1);
$names = fetchKeyValuePairs(
	"SELECT clientid, CONCAT(fname, ' ', lname) as nm 
		FROM tblclient
		WHERE clientid IN (".join(',', array_keys($firstContacts)).")
		ORDER BY lname, fname", 1);
		
$minLatency = 9999999999;		
$maxLatency = 0;		
		
foreach($names as $clientid => $nm) {
	$firstvisit = 
		fetchRow0Col0(
		"SELECT date FROM tblappointment
			WHERE clientptr = $clientid
				AND canceled IS NULL
			ORDER BY date
			LIMIT 1", 1);
	if(!$firstvisit) {
		$noVisits += 1;
		$noVisitClients[$clientid] = array('Clients Without Visits'=>$nm, 'contact'=>shortDate(strtotime($firstContacts[$clientid])));
		continue;
	}
	$data[$clientid]['name'] = $nm;
	$data[$clientid]['contact'] = $firstContacts[$clientid];
	$data[$clientid]['firstvisit'] = $firstvisit;
	if($firstvisit) {
		$visit = new DateTime($firstvisit);
		$contact = new DateTime($data[$clientid]['contact']);
		$interval = $visit->diff($contact);
		$latency = $interval->days+1;
		$data[$clientid]['latency'] = $latency;
		$totalLatency += $latency;
		$latencyCounts[$latency] += 1;
		$minLatency = min($minLatency, $latency);
		$maxLatency = max($maxLatency, $latency);
	}
}
function cmplatency($a, $b) {
	$a = (int)$a['latency'];
	$b = (int)$b['latency'];
	return $a > $b ? -1 : ($a < $b ? 1 : 0);
}

if($data) {
	usort($data, 'cmplatency');
	asort($latencyCounts);
	//print_r($latencyCounts);
	$fruitfulCount = count($data);
	$mode = array_pop(array_keys($latencyCounts));
	$modeCount = array_pop($latencyCounts);
	$modePercentage = number_format($modeCount / $fruitfulCount * 100, 2);
	foreach($data as $i => $row) {
		$data[$i]['contact'] = shortDate(strtotime($row['contact']));
		$data[$i]['firstvisit'] = shortDate(strtotime($row['firstvisit']));
	}
		
}













	if($csv) {
		latencyCSV($clients);
		exit;
	}
	else {
		echo "<p><table style='width:60%'><tr><td style='vertical-align:top'>";
		echo "Initial Prospect requests total: ".count($firstContacts)."<p>";
		echo "Prospects without visits: $noVisits<p>";
		echo "Prospects with visits: $fruitfulCount<p>";
		echo "</td><td style='vertical-align:top'>";
		echo "Mean Latency: ".($fruitfulCount ? (int)($totalLatency / $fruitfulCount)." days<p>" : "N/A<p>");
		for($i=0; $i < $fruitfulCount / 2; $i++) $middle = next($data);
		if($fruitfulCount) {
			$median = "{$middle['latency']} days";
			$mode = "$mode  days ($modeCount clients) $modePercentage%";
			$range = "$minLatency -  $maxLatency";
		}
		else
			$median = $mode = $range = "N/A";
		echo "Median Latency: $median<p>";
		echo "Mode Latency: $mode<p>";
		echo "Latency range (in days): $range<p>";
		echo "</td></tr></table>";
		if($data) quickTable($data, $extra="border=1", $style=null, $repeatHeaders=0);
		else echo "<span class='fontSize1_2em bold'>No prospects found in this period.</span>";
		if($noVisitClients) {
			echo "<p>";
			quickTable($noVisitClients, $extra="border=1", $style=null, $repeatHeaders=0);
		}

	}
if(!$print){
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

function typeSort($a, $b) {
	return strcmp($a['type'], $b['type']);
}

function latencyCSV($clients) {
	global $data;

	$columns = explodePairsLine('name|Client||contact|Contact Date||firstvisit|First Visit||latency|Latency');
	dumpCSVRow($columns);
	foreach($data as $row) {
		dumpCSVRow($row, array_keys($columns));
	}
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


function paymentsTable($clients, $sort) {
	global $allpayments, $uninvoicedTotal, $allBalances, $pstart, $pend, $showErrorsOnly;
	$rows = $allBalances;

	$columns = explodePairsLine('date|Date||clientname|Client||type|Type||amount|Amount||sourcereference|Source||externalreference|Reference||reason|Note'
															);
	//$colClasses = array('amount' => 'dollaramountcell', 'uninvoiced' => 'dollaramountcell', 'credit' => 'dollaramountcell', 'charge' => 'dollaramountcell'); 
	//$headerClass = array('amount' => 'dollaramountheader', 'uninvoiced' => 'dollaramountheader'); //'dollaramountheader'

	//$columnSorts = array('amount'=>null, 'client'=>null);
	//if($sort) {
	//	$sort = explode('_', $sort);
	//	$columnSorts[$sort[0]] = $sort[1];
	//}
//	foreach((array)$clients as $clientptr => $client) {
		foreach($allpayments as $credit) {
//			if($credit['clientptr'] != $clientptr) continue;
			$client = $clients[$credit['clientptr']];
			$row = $credit;
			$row['clientname'] = $client['clientname'];
			$row['date'] = date('m/d/Y', strtotime($credit['issuedate']));
			if($credit['payment']) {
				$row['type'] = 'payment';
				$editor = 'payment-edit.php';
			}
			else {
				$row['type'] = 'credit';
				$editor = 'credit-edit.php';
			}
			$row['amount'] = dollarAmount($credit['amount']);
		$row['amount'] = fauxLink($row['amount'], "openConsoleWindow(\"editcredit\", \"$editor?id={$credit['creditid']}\", 700,300)",
																1, safeValue($credit['reason']));
			$rows[] = $row;
		}
	//}
	$width = '100%'; //$_REQUEST['print'] ? '60%' : '45%';
	$columnSorts = array('date'=>'Date', 'clientname'=>'Client', 'type'=>'Type');
	tableFrom($columns, $rows, "width='$width'", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');
}

?>
<script language='javascript' src='common.js'></script>

<script language='javascript'>
function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var clients = '<?= $_REQUEST['clients'] ?>';
	document.location.href='reports-all-payments.php?sort='+sortKey+'_'+direction
		+'&clients='+clients
		+'&start='+start
		+'&end='+end;
}
</script>