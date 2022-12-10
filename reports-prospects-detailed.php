<? // reports-prospects-detailed.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "client-flag-fns.php";
require_once "js-gui-fns.php";


// Determine access privs
$locked = locked('o-');

extract(extractVars('csv,start,end,weekly', $_REQUEST));

$pageTitle = "Prospect Performance";

if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	$fullScreenMode = true;
	include "frame.html";
	// ***************************************************************************
?>
	<span>Prospects listed by Prospect Request date.<ul>
	<li><b>First Contact:</b> date prospect request received.
	<li><b>Client Setup:</b> date client created from prospect request.
	<li><b>Visit data and revenue data:</b> includes only Completed visits (of any type).
	<li><b>Revenue:</b> amount owed (but not necessarily collected) for completed visits.
	<li><b>Suspense:</b> days between receipt of Prospect Request and first visit.
	</ul>
	</span><p>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	calendarSet('For the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	
	//labeledCheckbox('Summarize Weekly', 'weekly', $weekly, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
	hiddenElement('csv', '');
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
	//echoButton('', 'Download Spreadsheet', "genCSV()");
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
				'start', 'NOT', 'isFutureDate',
				'end', 'NOT', 'isFutureDate')) document.reportform.submit();
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
				'start', 'NOT', 'isFutureDate',
				'end', 'NOT', 'isFutureDate')) {
			var start = escape(document.getElementById('start').value);
			var end = escape(document.getElementById('end').value);
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-tax-liability.php?print=1&start='+start+'&end='+end, 700,700);
		}
	}
	
	function editClient(id) {
		var url = 'client-edit.php?tab=services&id='+id;
		if(true) openConsoleWindow('clientedit', url, 800, 600);
		else document.location.href=url;
	}
	
	function editRequest(id) {
		var url = 'request-edit.php?tab=services&id='+id;
		if(true) openConsoleWindow('requestedit', url, 600, 600);
		else document.location.href=url;
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Prospects by Date.csv ");
	dumpCSVRow('Prospects by Date');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
else {
	$windowTitle = 'Prospects by Date';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Prospects by Date</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";
}

function clientLink($client) {
	if($client['clientptr']) return fauxLink($client['client'], "editClient({$client['clientptr']})", 1, 'Edit this client');
	else return $client['client'];
}

function addVisitAndRevenueStats(&$client) {
	if(!$client['clientptr']) return;
  if(!($result = doQuery(
		"SELECT date, charge, adjustment 
			FROM tblappointment 
			WHERE clientptr = {$client['clientptr']}
				AND completed IS NOT NULL
			ORDER BY date", 1))) return null;
  while($row = leashtime_next_assoc($result, MYSQL_ASSOC)) {
		if(!$client['firstdate']) $client['firstdate'] = $row['date'];
		$client['lastdate'] = $row['date'];
		$client['visitcount'] += 1;
		$client['revenue'] += $row['charge'] + $row['adjustment'];
	}
}

if($start && $end) {
	$achs = $achs ? $achs : array(0);
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));


	// Date / Time	Action	Amount	Type	Client	Account	Transaction	User
	$sort = $byclient ? 'sortname ASC' : 'issuedate ASC';
	$sql =
		"SELECT r.received as date,
				requestid,
				CONCAT_WS(' ', r.fname, r.lname) as requestname, 
				CONCAT_WS(' ', c.fname, c.lname) as clientname, 
				CONCAT_WS(' ', c.fname, c.lname) as sortname,
				clientptr,
				setupdate,
				if(deactivationdate IS NOT NULL, deactivationdate, 'active') as status
			FROM tblclientrequest r
			LEFT JOIN tblclient c ON clientid = clientptr
			WHERE requesttype = 'Prospect' 
				AND received >= '$start 00:00:00' AND received <= '$end 23:59:59'
				AND (setupdate IS NULL || DATE(received) <= DATE(setupdate))
			ORDER BY received ASC";
	$prospects = fetchAssociations($sql, 1);
	foreach($prospects as $i => $prospect) {
		addVisitAndRevenueStats($prospects[$i]);
	}
	/*$pclients = array();
	foreach($prospects as $p) {
		$pdate = substr($p['received'], 0, strpos($p['received'], ' '));
		if($date && $date != $pdate) {
			$stats[$date]['prospects'] = $pcount;
			$pcount = 0;
		}
		$date = $pdate;
		$pcount += 1;
		if($p['clientptr']) {
			$pclients[$p['clientptr']] = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$p['clientptr']} LIMIT 1", 1);
		}
	}
	if(!$stats[$date]['prospects']) $stats[$date]['prospects'] = $pcount;
	*/
	
	/*
	// find client creation date, trial flag
	foreach($pclients as $clientid => $client)
		foreach(getClientFlags($clientid) as $flag)
			if($flag['flagid'] == 1) { // prospect flag ID is "1"
				$trialStart = strtotime($flag['note']);
				if($trialStart)
				$trialstarts[date('Y-m-d', $trialStart)] += 1; // speculative -- only works when matt has supplied a valid date
			}
	*/
//echo $sql;exit;
//print_r($stats);
	$columns = explodePairsLine(
		'date|First Contact||client|Client||setupdate|Client Setup||status|Status||'
		.'visitcount|Visits||revenue|Revenue||timetofirst|Suspense||'
		.'firstdate|First Visit||lastdate|Last Visit'
		);

	if(!$csv){
		foreach($prospects as $i => $prospect) {
			$prospects[$i]['date'] = 
				fauxLink(shortDate(strtotime($prospect['date'])), "editRequest({$prospect['requestid']})", 1, 'Edit Prospect request.');
			$prospects[$i]['setupdate'] = $prospect['setupdate'] ? shortDate(strtotime($prospect['setupdate'])) : '--';
			$prospects[$i]['timetofirst'] = $prospect['firstdate']
				? round((strtotime($prospect['firstdate']) - strtotime($prospect['date'])) / (60 * 68 * 24))
				: '--';
			$prospects[$i]['client'] = $prospect['clientptr'] ? trim($prospect['clientname']) : trim($prospect['requestname']);
			$prospects[$i]['client'] = clientLink($prospects[$i]);
			$prospects[$i]['status'] = $prospect['status'] == 'active' ? 'active' : "<font color=red>Deactivated:</font><br>".shortDate(strtotime($prospect['status']));
			$prospects[$i]['revenue'] = $prospect['revenue'] ? dollarAmount($prospect['revenue']) : '--';
			$prospects[$i]['firstdate'] = $prospect['firstdate'] ? shortDate(strtotime($prospect['firstdate'])) : '--';
			$prospects[$i]['lastdate'] = $prospect['lastdate'] ? shortDate(strtotime($prospect['lastdate'])) : '--';
			$rowClasses[] = $rowClass = $rowClass == 'clientrow futuretask' ? 'clientrow futuretaskEVEN' : 'clientrow futuretask';
		}
		echo "<p>".count($prospects)." prospects received.";
		$colClasses = array('revenue'=>'dollaramountcell', 'status'=>'sortableListCell center', 'timetofirst'=>'sortableListCell center', 'visitcount'=>'sortableListCell center');
		$headerClass = array('revenue'=>'dollaramountheader', 'status'=>' sortableListHeader center');
		tableFrom($columns, $prospects, $attributes='border=0', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses, $sortClickAction=null);
	}
	else if($csv) {
		dumpCSVRow(array_keys($columns));
		foreach($days as $date)
			dumpCSVRow($date, array_keys($columns));
	}

}
if(!$print & !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}
else if(!$csv) {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
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

