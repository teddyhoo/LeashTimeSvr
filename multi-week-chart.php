<? // multi-week-chart.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";


// Two modes:
// MODE 1 justweek, date, startdate, weeks - print the weeknumber for package
// MODE 2 weeks, startdate, starton, numweeks - printa week chart

// MODE 1
if($_GET['justweek']) {
	$package = array('weeks'=>$_GET['weeks'], 'startdate'=>$_GET['startdate']);
	echo json_encode(
		array(
			'weeknumber'=>weekNumber($_GET['date'], $package)/*,
			'date checked'=>$_GET['date'],
			'package'=>$package*/
		));
	exit;
}

$package = array('weeks'=>$_GET['weeks'], 'startdate'=>$_GET['startdate']);
$startOn = $_GET['starton'] ? $_GET['starton'] : date('Y-m-d');
$numweeks = $_GET['numweeks'] ? $_GET['numweeks'] : 12;
$weeks = weekChart($package, $startOn, $numweeks, $returnAsArray=true);
?>
<style>
table td {padding:5px;font: 1.3em Arial;text-align:left;}
.bigger {font: 1.3em Arial;}
</style>
<?
$chartTitle = $_GET['title'] ? $_GET['title'] : 'Week Chart';

$packStart = shortDate(strtotime($package['startdate']));
$msg = "This schedule started on $packStart.  That was in the first Week 1.";
$result = "<p class='bigger'>$chartTitle</p><p class='bigger'>($msg)<p><table>";
foreach($weeks as $dates=>$week)
	$result .= "<tr><td>$dates</td><td>Week $week</td></tr>";
$result .= "</tr></table>";
echo $result;
