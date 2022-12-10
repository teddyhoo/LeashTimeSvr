<? // reports-daily-activity.php 
require_once "common/init_session.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";

$locked = locked('o-');
if(!$_SESSION['staffuser']) $errors[] = "You must be logged in as Staff to view this report.";
if($_SESSION['db'] != 'leashtimecustomers') $errors[] = "This report is available only in the the context of the LT Customers db.";
extract(extractVars('action,newbizdb,bizdb,date,enddate,showtest,detail,csv', $_REQUEST));

if($date) $date = date('Y-m-d', strtotime($date));

//$bizdb = $newbizdb;

require_once "common/init_db_petbiz.php";
$clientsByPetBizId = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient");
$liveBizIds = fetchCol0("SELECT garagegatecode 
														FROM tblclient 
														WHERE clientid IN (SELECT clientptr 
																								FROM tblclientpref 
																								WHERE property LIKE 'flag_%' AND value like '2|%')");
$lastInvoiceDates = fetchKeyValuePairs("SELECT garagegatecode, date
																				FROM tblinvoice
																				LEFT JOIN tblclient ON clientid = clientptr
																				ORDER BY date");
require_once "common/init_db_common.php";
$dbs = fetchKeyValuePairs("SELECT db, db FROM tblpetbiz ORDER BY db");
$dbs = array_merge(array('All Active Businesses'=>''), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
$columns = explodePairsLine('date|Date||visits|Visits');
if($detail && $date) {  // AJAX call
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$detail' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$rows = getProviderVisitCountForMonth($date);
//print_r($rows);	
	foreach($rows as $row) $total += $row['visits'];
	$rows = array_merge(array(array('name'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
	if($rows) {
		$columns = explodePairsLine('name|Sitter||visits|Visits');
		$monthYear = date('F Y', strtotime($date));
		echo "<p style='font-size:1.2em;padding-left:5px;'>Here are the sitters who made visits in $monthYear. Visit counts <u>exclude</u> canceled visits.</p>";
		tableFrom($columns, $rows, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses);
	}
	exit;
}
else if($bizdb && $date) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$bizdb' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$rows = getVisitCountForPeriod($date, $enddate);
	foreach($rows as $i => $row) {
		$rows[$i]['date'] = date('m/d/Y', strtotime($row['date']));
		$total += $row['visits'];
	}
	$rows = array_merge(array(array('date'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
}
else if($date) {
	$databases = fetchCol0("SHOW databases");
	$hideTest = $showtest ? '' : 'AND test = 0'; 
	$bizzes = fetchAssociations($sql = "SELECT * FROM tblpetbiz WHERE activebiz = 1 $hideTest");
//screenLog($sql);	
	$total = '0';
	$rows = array();
	$bizTotals = array();

	$startdate = $date;
	$enddate = $enddate ? date('Y-m-d', strtotime($enddate)) : null;
	foreach($bizzes as $biz) {
		if(!in_array($biz['db'], $databases)) continue;
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
		if(!in_array('tblappointment', fetchCol0("SHOW TABLES"))) continue;
		$bizviz = getVisitCountForPeriod($startdate, $enddate);
		foreach($bizviz as $bdate => $row)
			$rows[$bdate]['visits'] += $row['visits'];
		if($enddate) $andBeforeEnd .= " AND date <= '".date('Y-m-d', strtotime($enddate))."'";
		$sql = "SELECT count(*) FROM tblappointment WHERE  canceled IS NULL AND date >= '$startdate' $andBeforeEnd LIMIT 1";
		$bizTotals[$biz['bizname']] = array('name'=>"{$biz['bizname']} ({$biz['db']})", 'visits'=>fetchRow0Col0($sql));
		$numBizzes++;
		$numVisits += $bizTotals[$biz['bizname']]['visits'];
		if($bizTotals[$biz['bizname']]['visits'] == 0) $inactiveBizzes++;
	}
//echo "<hr>$db $date - $enddate<p>";print_r($rows);echo "<hr>";
	foreach((array)$rows as $i => $row) {
		$rows[$i]['date'] = date('m/d/Y', strtotime($i));
		$total += $row['visits'];
	}
	$rows = array_merge(array(array('date'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
}

function getVisitCountForPeriod($startdate, $enddate) {
	$datespec = "date >= '$startdate'";
	if($enddate) $datespec .= " AND date <= '".date('Y-m-d', strtotime($enddate))."'";
	$sql = "SELECT date, count(date) FROM tblappointment WHERE  canceled IS NULL AND $datespec GROUP BY date ORDER BY date";
	$vals = fetchKeyValuePairs($sql);
//print_r($sql);
	if(!$enndate) {
		$dates = array_keys($vals);
		$lastDate = $dates[count($dates)-1];
	}
	else $lastDate = $enddate;
//echo "S[$startdate] E[$lastDate]";	
	for($date = $startdate; strcmp($date, $lastDate) <= 0;  $date = date('Y-m-d', strtotime('+1 day', strtotime($date))))
		$rows[$date] =  array('date'=>$date, 'visits'=>$vals[$date]);
	return (array)$rows;
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

if($csv) {
	$dbn = $bizdb ? $bizdb : 'All';
	header("Content-Type: text/csv");
	header("Content-Disposition: attachment; filename=DailyActivity-$dbn.csv ");
	dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
	$columns = explodePairsLine('name|Business||visits|Visits');
	dumpCSVRow($columns);
//echo count($rows)." rows\n";	
	ksort($bizTotals);
	foreach($bizTotals as $row) { // array('name'=>.., 'visits'->...)
		dumpCSVRow($row, array_keys($columns));
	}
	exit;
}

$pageTitle = 'Daily Activity - Uncanceled Visits Each Day';
$breadcrumbs = "<a href='reports.php'>Reports</a>";	
if($bizdb) {
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$bizName = $bizName ? $bizName : "[$db}]";
	$pageTitle = "<h2>Daily Activity - Uncanceled Visits Each Day</h2>";
	$breadcrumbs .=  " - <a href='reports-daily-activity.php?&date=$date&enddate=$enddate'>Back to All Businesses</a><p>";
}
else if($date) $pageTitle =  "<h2>Daily Activity - Uncanceled Visits Each Day</h2>";
// ******************************************************
include 'frame.html';

if($errors) {
	echo "<ul><li>".join('<li>', $errors)."</ul>";
	require_once "frame-end.html";
	exit;
}
if($bizName) echo "<h2>$bizName</h2>";

?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
$mdyDate = $date ? date('m/d/Y', strtotime($_REQUEST['date'])) : date('m/d/Y');
selectElement('Business:', 'bizdb', $bizdb, $dbs);
hiddenElement('bizdb', $bizdb);
//echo " Date: <input id='date' name='date' value='$mdyDate'>";
calendarSet('Start', 'date', $mdyDate);
calendarSet('end', 'enddate', ($enddate ? $enddate : $mdyDate));

if($msg) echo "<p style='color:darkgreen'>$msg</p>";
if($error) echo "<p style='color:red'>$error</p>";
echoButton('', 'Show', 'show(0)');
echo " ";
echoButton('', 'CSV', 'show(1)');
if($bizdb) hiddenElement('showtest', $showtest);
else labeledCheckbox(' show test databases', 'showtest', $showtest);
if($rows) {
	echo "<p style='font-size:1.2em;padding-left:5px;'>Visit counts <u>exclude</u> canceled visits.</p>";
	echo "<table><tr><td valign=top>";
	tableFrom($columns, $rows, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses);
	if($bizTotals) {
		echo "<td valign=top>";
		ksort($bizTotals);
		$inactiveBizzes = $inactiveBizzes ? "($inactiveBizzes inactive)" : '';
		$columns = explodePairsLine('name|Business Visits During Period||visits|Visits');
		$bizTotals = array_merge(array(array('name'=>"<b>Total: $numBizzes Businesses $inactiveBizzes</b>", 'visits'=>"$numVisits")), $bizTotals);
		tableFrom($columns, $bizTotals, 'border=1 bordercolor=darkgrey style="margin-left:5px;background:palegreen "');
	}
	echo "</tr></table>";
}
?>
<p><img src='art/spacer.gif' height=250 width=1>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

function show(csv) {
	if(!MM_validateForm('date', '', 'R', 'date', '', 'isDate')) return;
	var bizdb = document.getElementById("bizdb").value;
	var showtest = document.getElementById("showtest").checked ? 1 : 0;
	var date = escape(date = document.getElementById("date").value);
	var enddate = escape(enddate = document.getElementById("enddate").value);
	document.location.href="reports-daily-activity.php?date="+date+"&enddate="+enddate+"&bizdb="+bizdb+"&showtest="+showtest+"&csv="+csv;
}

<? dumpPopCalendarJS(); ?>
</script>

<?
require_once "frame-end.html";
