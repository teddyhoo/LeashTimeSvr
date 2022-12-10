<? // appt-time-chart.php
set_time_limit(20);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";

locked('o-,d-');

?>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" />

<?

$provid = $_REQUEST['id'];
$date = $_REQUEST['date'];
$date = date('Y-m-d', strtotime($date));

$prettyDate = longestDayAndDate(strtotime($date));

$rangeColor = 'palegreen';
$actualColor = '#21F721';
$completionOnlyColor = '#08C008';


$provids = $provid ? array($provid ) :
	fetchCol0("SELECT DISTINCT providerptr FROM tblappointment WHERE canceled IS NULL AND date = '$date' AND providerptr != 0", 1);
$providers = fetchAssociationsKeyedBy(
	"SELECT CONCAT(lname, ',', fname) as sortname, CONCAT(fname, ' ', lname) as sitter, providerid
		FROM tblprovider 
		WHERE providerid IN (".join(',',$provids).")
		ORDER BY sortname", 'providerid', 1);

foreach($provids as $provid) {
	echo "<span class='charttitle'>{$providers[$provid]['sitter']}'s visits for $prettyDate</span>";
	echo " <div style='background: $rangeColor;display:inline;padding-left:5px; padding-right:5px;'>Schedule Window</div>";
	echo " <div style='background: $actualColor;display:inline;padding-left:5px; padding-right:5px;'>Completed Visit Actual Times</div><p>";
	echo "<div class='TipLooks fontSize1_2em'>Hover for details.</div>";
	timeChart(fetchAssociations("SELECT * FROM tblappointment WHERE canceled IS NULL AND providerptr = $provid AND date = '$date' ORDER BY starttime"));
	echo "<p>";
}

function timeChart($appts) {
	if(!$appts) {
		echo "There are no visits to display.";
		exit;
	}
	$totalrange = array('start'=>0, 'end'=>0);
	foreach($appts as $i =>$appt) {
		$range = timesForApptStartAndEnd($appt); // array('start'=>time, 'end'=>time)
		$appts[$i]['range'] = $range;
		$appts[$i]['actual'] = actualVisitTimes($appt);
		$totalrange['start'] = $totalrange['start'] ? min($range['start'], $totalrange['start']) : $range['start'];
		$totalrange['end'] = max($range['end'], $totalrange['end']);
	}
	usort($appts, 'cmpActuals');
	//echo "<hr>start: [".shortDateAndTime($totalrange['start'])."] end: [".shortDateAndTime($totalrange['end'])."]";
	$start = strtotime(date('Y-m-d H:00:00', $totalrange['start']));
	$end = strtotime(date('Y-m-d H:00:00', strtotime("+0 hour", $totalrange['end'])));
	$barFontSize = '1.2em';
	$minterval = 5;
	if(($end - $start)/60/30 > 14) $minterval = 10;
	//echo "[[[".(($end - $start)/60/30)."]]]";
	//echo "<hr>rangestart: [".shortDateAndTime($start)."] end: [".shortDateAndTime($end);
	global $rangeColor, $actualColor, $completionOnlyColor;
	echo "<style>
	table {background:white;}
	td {font-family:courier, 'courier new', monospace;font-size:$barFontSize;}
	.plain {font-family:arial, helvetica, sans-serif;font-size:1.2em;}
	.blank {color:#EBFFEB;}
	.completiononly {color:$completionOnlyColor;cursor:pointer;}
	.actual {color:$actualColor;cursor:pointer;}
	.range {color:$rangeColor;cursor:pointer;}	
	.charttitle {font-size: 1.2em;font-weight:bold;}
	</style>";
	echo "<table>";
	drawTimeHeaders($start, $end, $minterval);
	foreach($appts as $appt) drawTimeLine($appt, $start, $end, $minterval);
	echo "</table>";
}

function cmpActuals($a, $b) {
	$aa = $a['actual'] ? $a['actual']['start'] : $a['range']['start'];
	$bb = $b['actual'] ? $b['actual']['start'] : $b['range']['start'];
	return 
		$a['actual'] && !$b['actual'] ? -1 : (
		$b['actual'] && !$a['actual'] ? 1 : (
		$aa < $bb ? -1 : ($aa > $bb ? 1 : 0)));
}

function actualVisitTimes($appt) {
	$arrived = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = {$appt['appointmentid']} AND event = 'arrived' LIMIT 1", 1);
	$completed = fetchRow0Col0("SELECT date FROM tblgeotrack WHERE appointmentptr = {$appt['appointmentid']} AND event = 'completed' LIMIT 1", 1);
	$completed = $completed ? $completed : $appt['completed'];
	if($arrived && $completed)
		return array('start'=>strtotime($arrived), 'end'=>strtotime($completed));
	else if($completed) return array('completed'=>strtotime($completed));
}

function drawTimeHeaders($starttime, $endtime, $minterval=5) {
	$time = time();
	if($time <= $endtime) {
		echo "<tr><td>&nbsp;</td><td>";
		for($i = $starttime; $i <= $endtime; $i += $minterval * 60) {
			if($i >= $time && !$done) {
				echo "<span style='cursor:pointer' title='The time is ".date('g:i a')."'>&#9830;</span>"; // black diamond
				$done = true;
			}
			echo "&nbsp;";
		}
		echo "</td><tr>";
	}
	echo "<tr><td>&nbsp;</td><td>";
	for($i = $starttime; $i < $endtime; $i += $minterval*6 * 60) {
		echo date("H:i ", $i);
	}
	echo "</td></tr>";
}
function drawTimeLine($appt, $starttime, $endtime, $minterval=5) {
	echo "<tr><td class='plain'>"
			.fetchRow0Col0("SELECT CONCAT(fname, ' ', lname) FROM tblclient WHERE clientid = {$appt['clientptr']} LIMIT 1", 1)
			."</td><td>";
	$range = $appt['range'];
	$actual = $appt['actual'];
	if($actual['start']) {
		$arr = date('g:i a', $actual['start']);
		$com = date('g:i a', $actual['end']);
		$dur = round(($actual['end'] - $actual['start']) / 60).' minutes.';
		$dur = hoursAndMinutes($actual['end'] - $actual['start']);
		$dur = trim(($dur['hours'] ? "{$dur['hours']} hours " : '')
						.($dur['minutes'] ? "{$dur['minutes']} minutes " : ''));
	}
	else if($actual['completed'])
		$com = date('g:i a', $actual['end']);
		
	$rStart = date('g:i a', $range['start']);
	$rEnd = date('g:i a', $range['end']);
	$completionSymbol = 'C';
	$actualSymbol = '*';
	$rangeSymbol = 'X';
	$blankSymbol = '_';
	$symbolDisplay = "&#9608;"; // full block
	$blankDisplay = "&#9618;"; // medium shade block
	$blankDisplay = "&#9619;"; // medium shade block
	$mintervalMinutes = $minterval * 60;
	for($i = $starttime; $i < $endtime; $i += $mintervalMinutes) {
		$lastSymbol = $symbol;
		if($actual && $actual['start'] && $i >= $actual['start'] && $i <= $actual['end']) $symbol = $actualSymbol;
		else if($actual && $actual['completed'] && $i <= $actual['completed'] && $i + $mintervalMinutes >= $actual['completed']) $symbol = $completionSymbol;
		else if($i >= $range['start'] && $i <= $range['end']) $symbol = $rangeSymbol;
		else $symbol = $blankSymbol;
		if($symbol != $lastSymbol) {
			if($symbol) echo "</span>";
			$onclick = "onClick='editVisit({$appt['appointmentid']})'";
			echo 
				$symbol == $blankSymbol ? "<span class='blank'>" : (
				$symbol == $actualSymbol ? "<span class='actual' title='Arrived: $arr Completed: $com.  $dur.' $onclick>" : (
				$symbol == $completionSymbol ? "<span class='completiononly' title='Completed: $com.' $onclick>" :
				"<span class='range' title='Schedule Window: $rStart to: $rEnd' $onclick>"));
		}
		echo $symbol == $blankSymbol ? $blankDisplay : $symbolDisplay;
	}
	echo "</span>";	
	echo "</td></tr>";
}

$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

if($roDispatcher) {
	$editScript = "client-request-appointment.php?id=";
}
else $editScript = "appointment-edit.php?id=";

?>
<script src='common.js'></script>
<script>
function editVisit(visitId) {
	var url = '<?= $editScript ?>'+visitId;
	var dims = '<?= $_SESSION['dims']['appointment-edit'] ?>';
	openConsoleWindow("editappt", url, dims);
}
</script>