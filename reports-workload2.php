<?  // reports-workload.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "preference-fns.php";
require_once "prov-schedule-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');

$max_rows = 100;
extract($_REQUEST);

if($thisweek && !$starting && !$ending) {
	$starting = shortDate();
	$ending = shortDate(strtotime("+ 6 days"));
}

else if(!$starting && !$ending) {
	$starting = shortDate();
	$ending = shortDate();
}

$activeProviderSelections = array_merge(array('--Select a Sitter--' => '-2', '--All Sitters--' => 0, '--Unassigned--' => -1), getActiveProviderSelections());

$appts = array();
if($provider == -1) $providerName = 'Unassigned';
else if($provider)
	$providerName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provider");
if($provider != -2) {
	$timesOfDayRaw = getPreference('appointmentCalendarColumns');
	if(!$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
	$timesOfDayRaw = explode(',', $timesOfDayRaw);
	for($i=0;$i < count($timesOfDayRaw)-1; $i+=2) $timesOfDay[$timesOfDayRaw[$i+1]] = $timesOfDayRaw[$i]; //07:00:00 => Morning
	$reverseTimes = array_reverse(array_keys($timesOfDay));
	// drop earliest frame
	array_pop($reverseTimes);
	foreach($reverseTimes as $i => $start)
		$todFrameSQL .= 
										"if(starttime >= '$start', '{$timesOfDay[$start]}', ";
	$todFrameSQL .= "'".current($timesOfDay)."'";
	for($i=1; $i < count($timesOfDay); $i++) $todFrameSQL .= ")";
	$todFrameSQL .= " as TODFrame";

	
	
	if($provider) $providerFilter = " AND providerptr=".($provider == -1 ? '0' : $provider);
	$found = fetchAssociationsIntoHierarchy(
		$sql = "SELECT date, providerptr, $todFrameSQL, COUNT(*) as visits FROM tblappointment 
						WHERE canceled IS NULL AND date >= '".dbDate($starting)."' AND date <= '".dbDate($ending)
						."' $providerFilter GROUP BY date,providerptr, TODFrame ORDER BY date ASC", array('date','providerptr','TODFrame'), 1);
	foreach($found as $date=>$sitterCounts) {
		foreach($sitterCounts as $providerptr=>$TODFrame) {
			foreach($TODFrame as $label=>$row) {
				$count = $row[0]['visits'];
				$dayTotals[$date][0] += $count;
				$dayTotals[$date][$label] += $count;
			}
		}
	}
	
//echo "$sql<br>\n".print_r($dayTotals, 1);	exit;
	
	
	
	$allDaysTotal = $dayTotals ? array_sum($dayTotals) : 0;
	$sortedSitters = array(0=>'Unassigned');
	foreach(fetchKeyValuePairs(
			"SELECT providerid, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname
				FROM tblprovider
				ORDER BY sortname") as $k=>$v) $sortedSitters[$k]=$v;
}

if($dayDetail) {
	echo "<div style='background:palegreen;'><span class='fontSize1_2em'>Visits for: ".shortDate(strtotime($date))." ".date('D', strtotime($date)).'</span><p>';	if($found) {
		$columns = explodePairsLine("sitter|Sitter||visits|Visits");
	//echo 	dbDate($starting).' - '.dbDate($ending);exit;
		reset($found);
		$data = current($found);
		foreach($sortedSitters as $providerptr => $name) {
			if(!$data[$providerptr]) continue;
			$row = array('sitter'=>$name, 'visits'=>$data[$providerptr][0]['visits']);
			$rows[] = $row;
			//echo print_r($appt,1)."<p>";
		}
		tableFrom($columns, $rows, "style=margin-left:1px;width:200px", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
		exit;
	}
}


$searchResults = ($allDaysTotal ? $allDaysTotal : 'No')." visit".($allDaysTotal == 1 ? '' : 's')." found.  ";


$pageTitle = "Visit Workload".($provider ? " for $providerName" : '');;

$columns = explodePairsLine("timeofday| ||client| ||service| ||provider| ||arrivedtime| ||completedtime| "); // ||completedname| 
$collabels = explodePairsLine("timeofday|Time||client|Client||service|Service||provider|Sitter||arrivedtime|Arrived||completedtime|Completed"); // ||completedname|Marked Complete By
if($provider) {
	unset($columns['provider']);
	unset($collabels['provider']);
}


if($csv) {
	$collabels['accuracyArrived'] = 'Arrival accuracy';
	$collabels['accuracyCompleted'] = 'Completion accuracy';
	$columns['accuracyArrived'] = '';
	$columns['accuracyCompleted'] = '';
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

	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Sitter-performance.csv ");
	dumpCSVRow("Sitter Performance".($providerName ? " for $providerName" : ''));
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $starting - $ending");
	dumpCSVRow("Accuracy (reported by GPS device) is expressed in meters.  Higher values indicate greater possible errors.");
	dumpCSVRow("Accuracy depends on signal strength and the signal source integrity (LAN, Cell towers, Satellites).");
	dumpCSVRow("");

	if($appts) {
		dumpCSVRow($collabels);
//if(mattOnlyTEST()) echo "<pre>".print_r($appts,1)."</pre>";		
		
		foreach($appts as $appt) {
			$appt['arrivedtime'] = $appt['arrived'] ? date('h:i a', strtotime($appt['arrived'])) : '--';
			if(!$appt['accuracyArrived']) $appt['accuracyArrived'] = '--';
			if(!$appt['accuracyCompleted']) $appt['accuracyCompleted'] = '--';
			if($appt['completed'] && strcmp(substr($appt['completed'], 0, 10), substr($appt['date'], 0, 10)) != 0)
				$dateDisplay = ' ('.shortestDate(strtotime($appt['completed'])).')';
			else $dateDisplay = '';
			$appt['completedtime'] = $appt['completed'] ? date('h:i a', strtotime($appt['completed'])).$dateDisplay : '--';
			if(!$appt['completedmobile']) $appt['completedtime'] = "Not reported by mobile - {$appt['completedtime']}";
			dumpCSVRow($appt, array_keys($columns));
		}
	}
	else echo "No visits found.";
	exit;
}

$breadcrumbs = "<a href='reports.php'>Reports</a>";
if($provider) $breadcrumbs .= " - <a href='provider-edit.php?id=$provider'>$providerName</a>";

include "frame.html";
// ***************************************************************************
//print_r($appts);
?>
<form name='workloadform'>
<p>
<? 
selectElement('Sitter:', "provider", $provider, $activeProviderSelections);
calendarSet('Starting:', 'starting', $starting, null, null, true, 'ending');
calendarSet('ending:', 'ending', $ending);
echo " ";
echoButton('', 'Show', 'checkAndSubmit()');
echo " ";
$url = $_SERVER['REQUEST_URI'];
if(strpos($url, '?')) $url .= '&';
else $url .= '?';
//echoButton('', 'Download Spreadsheeet', "document.location.href=\"{$url}csv=1\"");
?>
</form>
<?
echo "<table><tr><td style='padding-right:5px;'>$searchResults</td></tr></table><table><tr><td valign=top>";

if($found) {	
	$columns = explodePairsLine("day|Day||visits|Visits");
	foreach($timesOfDay as $frame) $columns[$frame] = $frame;
//echo 	dbDate($starting).' - '.dbDate($ending);exit;
	for($date = dbDate($starting); strtotime($date) <= strtotime($ending); $date = date('Y-m-d', strtotime("+1 day", strtotime($date)))) {
		/* if($lastDate != $appt['date']) {
			$rowClasses[count($data)] = 'daycalendardaterow';
			$rows[] = array('#CUSTOM_ROW#'=> 
				"<tr><td class='daycalendardaterow' colspan=".count($columns).">".longDayAndDate(strtotime($appt['date']))."</td></tr>\n");
			$rows[] = array('#CUSTOM_ROW#'=> 
				"<tr><th>".join('</th><th>', $collabels)."</th></tr>\n");
		}
		$lastDate = $appt['date'];*/
		$row = array('day'=>shortDate(strtotime($date))." ".date('D', strtotime($date)), 'visits'=>$dayTotals[$date][0]);
		foreach((array)$dayTotals[$date] as $k => $v) $row[$k] = $v;
		if(!$provider && $dayTotals[$date]) {
			$action = "$.ajax({url:\"reports-workload.php?starting=$date&ending=$date&dayDetail=1\", 
							success: function(data) {document.getElementById(\"detail\").innerHTML = data;}});";
			$row['visits'] = fauxLink($row['visits'], $action, 1, 'View details');
		}
		$rows[] = $row;
		//echo print_r($appt,1)."<p>";
	}
	tableFrom($columns, $rows, "style=margin-left:1px;", $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
}
?>
</td><td style='vertical-align:top;' id='detail'></td></tr></table>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('provider','Provider','starting','Starting Date','ending', 'Ending Date');

function checkAndSubmit() {
  if(MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var provider = document.workloadform.provider.value;
		var starting = document.workloadform.starting.value;
		var ending = document.workloadform.ending.value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
    document.location.href='reports-workload.php?provider='+provider+starting+ending;
	}
}

<?
dumpPopCalendarJS();

?>

</script>
<img src='art/spacer.gif' width=1 height=160>
<?
include "frame-end.html";
?>
