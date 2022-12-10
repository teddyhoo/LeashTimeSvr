<? // reports-overbooking.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "invoice-fns.php";
require_once "field-utils.php";

$locked = locked('o-#vr');
extract($_REQUEST);

if($_POST) {
	$start = $_REQUEST['starting'] ? date('Y-m-d', strtotime($_REQUEST['starting'])) : date('Y-m-d');
	$end = $_REQUEST['ending'] ? date('Y-m-d', strtotime($_REQUEST['ending'])) : $_REQUEST['ending'];
	$sql = "SELECT * FROM tblappointment a WHERE date >= '$start'";
	if($end) $sql .= " AND date <= '$end'";
	$sql .= " ORDER BY date, clientptr, starttime";
	$clients = fetchAssociationsIntoHierarchy($sql, array('clientptr', 'date'));


	$overlaps = array();
	//print_r($clients);
	
	foreach($clients as $clientptr => $days)
		foreach($days as $day => $appts) {
			if(count($appts) == 1) continue;
			else {
				foreach($appts as $i => $a) {
					$a['starttime'] = strtotime("{$a['date']} {$a['starttime']}");
					$a['endtime'] = $a['endtime'] < $a['starttime']
						? strtotime("+ 24 hours", strtotime("{$a['date']} {$a['endtime']}"))
						: strtotime("{$a['date']} {$a['endtime']}");
				}
				foreach($appts as $i => $a) {
					foreach($appts as $j => $b) {
						if($i == $j) continue;
						if(($strict && timeFrameOverlapStrict($a, $b))
								|| (!$strict && timeFrameOverlapLenient($a, $b))) {
							$overlaps[$clientptr][$day][$a['appointmentid']] = $a;
							$totalOverlaps++;
						}
					}
				}
			}
		}
	if(!$totalOverlaps) 
		$message = "No overbookings to report from ".shortDate(strtotime($start))
							.($end ? " to ".shortDate(strtotime($end)) : " onwards");
}

$pageTitle = "Overbooking Report";
$breadcrumbs = "<a href='reports.php'>Reports</a>";	
include "frame.html";
?>
<style>
.cat1 {text-align:center;color:lightblue;font-size:2em;font-weight:bold;}
.cat2 {text-align:center;background-color:lightblue;font-size:1.1em;font-weight:bold;border:solid black 1px;}
</style>
<span class='tiplooks fontSize1_1em'>Starting and or ending may be left blank.  Starting defaults to today&apos;s date.</span>
<p>
<form name='reportform' method='POST'>
<?
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
echo " ";
labeledCheckbox('strict', 'strict', $strict, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title='[Strict] means that 10am-11am and 11am-12am overlap');
echo " ";
echoButton('', 'Show', 'checkAndSubmit()');
?>
</form>

<?
if($message) echo "<span style='fontSize1_2em'>$message</span>";
else if($overlaps) {
	$serviceNames = getAllServiceNamesById();
	$sitterNames = getProviderNames();
	// columns: time service sitter
	$clientNames = fetchKeyValuePairs(
		"SELECT CONCAT_WS(' ', fname, lname), clientid 
			FROM tblclient
			WHERE clientid IN (".join(',', array_keys((array)$overlaps)).")
			ORDER BY lname, fname");
	$columns = explodePairsLine('timeofday|Time||service|Service||sitter|Sitter');
	$colcount = count($columns);
	foreach($clientNames as $clientname => $clientptr) {
		$days = $overlaps[$clientptr];
		$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='cat1'><td style='font-size:1.4em;' colspan=$colcount>$clientname</td></tr>");
		foreach($days as $day => $appts) {
			$rows[] = array('#CUSTOM_ROW#'=>"<tr class ='cat2'><td style='font-size:1.4em;' colspan=$colcount>".longerDayAndDate(strtotime($day))."</td></tr>");
			foreach($appts as $i => $a) {
				$a['service'] = $serviceNames[$a['servicecode']];
				$a['sitter'] = $sitterNames[$a['providerptr']];
				$rows[] = $a;
			}
		}
	}
	tableFrom($columns, $rows, $attributes='width=100%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('starting','Starting','ending','Ending');
function checkAndSubmit() {
	if(MM_validateForm('starting', '', 'isDate', 'ending', '', 'isDate'))
		document.reportform.submit();
}
<? dumpPopCalendarJS(); ?>
</script>
<img src='art/spacer.gif' height=300 width=1>
<?
include "frame-end.html";

