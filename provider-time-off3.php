<?
// provider-time-off3.php
// for population of the provider editor time off tab
require_once "provider-fns.php";
require_once "js-gui-fns.php";
require_once "common/init_session.php";
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if(userRole() == 'p') $locked = locked('p-');
else $locked = locked('o-');

extract($_REQUEST);

if(userRole() == 'p') {
	$id = $_SESSION["providerid"];
	$showpasttimeoff = true;
}

$timeOffRows = getProviderTimeOff($id, $showpasttimeoff);
$oldTimeOffRows = array();
$today = date('Y-m-d');
foreach($timeOffRows as $r => $row)
	if(strcmp($row['date'], $today) == -1) {
		$oldTimeOffRows[] = $row;
		unset($timeOffRows[$r]);
	}
	
$timeOffRows = array_values($timeOffRows);

usort($timeOffRows, 'dateSort');
usort($timeOffRows, 'dateSort');
function dateSort($a, $b) {return strcmp($a['date'], $b['date']); }
?>
<style>
.oldTO {background:lightgray;}
.timeofftable td {padding-right:10px;}
</style>
<?
if(TRUE || mattOnlyTEST()) {
	fauxLink('Show Old Time Off', 'toggleOldTimeOff(this)');
	echo " <span id='toggleTimePleaseWait' style='display:none;'>Please wait...</span>";
	echo "<p>";
}
echo "<table class='timeofftable fontSize1_2em' style='background:white;' border=1 bordercolor=black>\n";
foreach($oldTimeOffRows as $row) {
	$tod = $row['timeofday'] ? $row['timeofday'] : 'All Day';
	echo "<tr class='oldTO' style='display:none;'><td>".dateLink($row['date'], $id, false, $row['timeoffid']); // ($noEdit= userRole() == 'p')
	echo "</td><td>$tod</td>";
	echo "</td><td>{$row['note']}</td>";
	echo "</tr>\n";
}
foreach($timeOffRows as $row) {
	$tod = $row['timeofday'] ? $row['timeofday'] : 'All Day';
	echo "<tr><td>".dateLink($row['date'], $id, false, $row['timeoffid']);
	echo "</td><td>$tod</td>";
	echo "</td><td>{$row['note']}</td>";
	echo "</tr>\n";
}
echo "</table>\n";

function dateLink($date, $id, $noEdit=false, $timeoffid='') {
	$month = date('Y-m-01', strtotime($date));
	$date = shortDate(strtotime($date)).date(" (l)", strtotime($date));
	if(userRole() != 'p' || ($_SESSION['preferences']['offerTimeOffProviderUI'] && !$noEdit))
		$date = fauxLink($date, "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?provid=$id&amp;editable=1&amp;month=$month&amp;open=$timeoffid\",850,700)", 1, 'Click to edit.');
	return $date;
}
?>
