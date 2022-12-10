<? // reports-cancellations.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Cancellations";
$breadcrumbs = "<a href='reports.php' title=''>Reports</a> - ";

if($_POST) {
	$starting = date('Y-m-d', strtotime($_POST['starting']));
	$ending = date('Y-m-d', strtotime($_POST['ending']));	
	$canceledVisits = fetchAssociations(
		"SELECT date, canceled
				FROM tblappointment
				WHERE date >= '$starting' AND date <= '$ending'", 1);
	foreach($canceledVisits as $visit) {
		$numbers[$visit['date']]['allvisits'] += 1;
		if($visit['canceled']) $numbers[$visit['date']]['canceledvisits'] += 1;
	}
	$startingTime = date("Y-m-d 00:00:00", strtotime($starting));
	$endingTime = date("Y-m-d 00:00:00", strtotime($ending));
	$cancellationsByDate = fetchCol0(
		"SELECT canceled 
			FROM tblappointment
			WHERE canceled >= '$startingTime' AND canceled <= '$endingTime'", 1);
	foreach($cancellationsByDate as $date)
		$numbers[date('Y-m-d', strtotime($date))]['cancellations'] += 1;
	ksort($numbers);
}
include 'frame.html';
?>
<form name='cancellationhistory' method='POST'>
<p style='font-weight:bold;font-size:1.2em;'>
<?

echoButton('', 'Show', 'checkAndSubmit()');
echo " ";
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
?>
</form>
<?
foreach((array)$numbers as $date=>$stats) {
	$rows[] = array(
		'date'=>shortDate(strtotime($date)).' '.date('D', strtotime($date)),
		'cancellations'=>$stats['cancellations'],
		'canceledvisits'=>$stats['canceledvisits'],
		'allvisits'=>$stats['allvisits'],
		'percent'=>number_format((0+$stats['canceledvisits'])/(0+$stats['allvisits'])*100, 2).'%'
		);
	$rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask';
	$rowClasses[] = $rowClass;
}
if($_POST && !$rows) echo "<p>No data for this period.";
else if($rows) {
	$colClasses = explodePairsLine('cancellations|rightAlignedTD||canceledvisits|rightAlignedTD||allvisits|rightAlignedTD||percent|rightAlignedTD');
	$headerClasses = explodePairsLine('cancellations|rightAlignedTD||canceledvisits|rightAlignedTD||allvisits|rightAlignedTD||percent|rightAlignedTD');
	$headers = explodePairsLine('date|Date||cancellations|Cancellations<br>Processed||canceledvisits|Canceled<br>Visits||allvisits|All<br>Visits||percent|Percent<br>Canceled');
	tableFrom($headers, $rows, 'WIDTH=55%% ',null,$headerClasses,null,null,$sortableCols,$rowClasses, $colClasses);
}

?>
<img src='art/spacer.gif' height=300>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
setPrettynames('Starting Date','ending', 'Ending Date');	

function checkAndSubmit() {
  if(MM_validateForm(
		  'starting', '', 'R',
		  'ending', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate',
		  'starting', 'ending', 'datesInOrder')) {
    document.cancellationhistory.submit();
	}
}
<? dumpPopCalendarJS();
?>
</script>
<?
require_once "frame-end.html";
