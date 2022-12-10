<? // reports-daily-revenue.php 
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";

$locked = locked('o-');
extract(extractVars('action,newbizdb,bizdb,date,enddate,showtest,detail,csv', $_REQUEST));


$date = $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
$enddate = $enddate ? date('Y-m-d', strtotime($enddate)) : date('Y-m-d');

//$bizdb = $newbizdb;

$result = doQuery(
	"SELECT a.*, CONCAT_WS(' ', fname, lname) as sitter, label as service
		FROM tblappointment a
		LEFT JOIN tblprovider ON providerid = providerptr
		LEFT JOIN tblservicetype ON servicecode = servicetypeid
		WHERE completed IS NOT NULL AND date >= '$date' AND date <= '$enddate'
		ORDER BY date", 1);
		
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$days[$row['date']]['count'] += 1;
	$rowRevenue = $row['charge'] + $row['adjustment'];
	$rowPay = $row['rate'] + $row['bonus'];
	$days[$row['date']]['revenue'] += $rowRevenue;
	$days[$row['date']]['rev_'.$row['service']] += $rowRevenue;
	$days[$row['date']]['rev_'.$row['sitter']] += $rowRevenue;
	$days[$row['date']]['pay'] += $rowPay;
	$days[$row['date']][$row['service']] += 1;
	$sitterName = $row['sitter'] ? $row['sitter'] : 'Unassigned';
	$sitters[$sitterName]['visits'] += 1;
	$sitters[$sitterName]['pay'] += $rowPay;
	$sitters[$sitterName]['revenue'] += $rowRevenue;
	$services[$row['service']]['visits'] += 1;
	$services[$row['service']]['pay'] += $rowPay;
	$services[$row['service']]['revenue'] += $rowRevenue;
 }

foreach((array)$days as $thisDate => $day) {
	$row = array();
	$row['date'] = shortDate(strtotime($thisDate));
	$row['visits'] = $day['count'];
	$row['revenue'] = $day['revenue'];
	$row['pay'] = $day['pay'];
	$totalVisits += $day['count'];
	$totalRevenue += $day['revenue'];
	$totalPay += $day['pay'];
	foreach($day as $k=>$v) if(strpos($k, 'rev_') !== FALSE) $row['sitters'] += 1;
	$overview[] = $row;
}
if(!$csv) {
	$row = array();
	$row['date'] = '<b>Total</b>';
	$row['visits'] = $totalVisits;
	$row['revenue'] = $totalRevenue;
	$row['pay'] = $totalPay;
	$overview = array_reverse($overview);
	$overview[] = $row;
	$overview = array_reverse($overview);
	
	$row = array();
	$row['visits'] = $totalVisits;
	$row['revenue'] = $totalRevenue;
	$row['pay'] = $totalPay;
	ksort($sitters);
	$sitters = array_reverse($sitters);
	$sitters["<b>".count($sitters)." Sitters</b>"] = $row;
	$sitters = array_reverse($sitters);
	
	$row = array();
	$row['visits'] = $totalVisits;
	$row['revenue'] = $totalRevenue;
	$row['pay'] = $totalPay;
	ksort($services);
	$services = array_reverse($services);
	$services["<b>".count($services)." Services</b>"] = $row;
	$services = array_reverse($services);
}

$columns = explodePairsLine('date|Date||visits|Visits||revenue|Revenue||sitters|Sitters');
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

$pageTitle = 'Daily Activity - Completed Visits Each Day';
$breadcrumbs = "<a href='reports.php'>Reports</a>";	
// ******************************************************
include 'frame.html';
?>
<style>
.dollaramount {
  font-size: 1.05em; 
	text-align: right;
}
</style>
<?
if($errors) {
	echo "<ul><li>".join('<li>', $errors)."</ul>";
	require_once "frame-end.html";
	exit;
}

?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
$mdyDate = $date ? shortDate(strtotime($date)) : date('m/d/Y');
//echo " Date: <input id='date' name='date' value='$mdyDate'>";
calendarSet('Start', 'date', $mdyDate);
calendarSet('end', 'enddate', ($enddate ? $enddate : $mdyDate));

if($msg) echo "<p style='color:darkgreen'>$msg</p>";
if($error) echo "<p style='color:red'>$error</p>";
echoButton('', 'Show', 'show(0)');
echo " ";
//echoButton('', 'CSV', 'show(1)');
if(!$csv && $overview) {
	//echo "<p><a href='#service-sitter'>Visits by Sitter and Service Type</a><p>";
	echo "<p style='font-weight:bold'>Visits by Date</p>";
	$columns = explodePairsLine('date|Date||visits|Visits||revenue|Revenue||pay|Pay||sitters|Sitters');
	foreach($overview as $i => $row) {
		$overview[$i]['revenue'] = number_format($row['revenue'], 2);
		$overview[$i]['pay'] = number_format($row['pay'], 2);
	}
	echo "<p>";
	$colClasses = array('revenue'=>'dollaramount', 'pay'=>'dollaramount');
	tableFrom($columns, $overview, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses, $colClasses);
	
	echo "<a name='service-sitter'></a><p><table width=100%><tr><td valign=top>";
	$columns = explodePairsLine('sitter|Sitter||visits|Visits||revenue|Revenue||pay|Pay');
	foreach($sitters as $sitter => $row) {
		$sitters[$sitter]['sitter'] = $sitter;
		$sitters[$sitter]['revenue'] = number_format($row['revenue'], 2);
		$sitters[$sitter]['pay'] = number_format($row['pay'], 2);
	}
	$colClasses = array('revenue'=>'dollaramount', 'pay'=>'dollaramount');
	tableFrom($columns, $sitters, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses, $colClasses);
	echo "</td><td valign=top>";
	$columns = explodePairsLine('service|Service||visits|Visits||revenue|Revenue||pay|Pay');
	foreach($services as $service => $row) {
		$services[$service]['service'] = $service;
		$services[$service]['revenue'] = number_format($row['revenue'], 2);
		$services[$service]['pay'] = number_format($row['pay'], 2);
	}
	//print_r($services);
	$colClasses = array('revenue'=>'dollaramount', 'pay'=>'dollaramount');
	tableFrom($columns, $services, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses, $colClasses);
	echo "</td></tr></table>";

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
	var date = escape(date = document.getElementById("date").value);
	var enddate = escape(enddate = document.getElementById("enddate").value);
	document.location.href="reports-daily-revenue.php?date="+date+"&enddate="+enddate+"&csv="+csv;
}

<? dumpPopCalendarJS(); ?>
</script>

<?
require_once "frame-end.html";
