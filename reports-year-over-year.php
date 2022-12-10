<? // reports-year-over-year.php
/* report on one week, year over year.
gather a starting date and compare the seven days from there to the same date range in the previous year.
compare: uncanceled vists, revenues, pay to sitters
offer: breakdowns by type, by sitter
offer charts: visits and revenue
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "appointment-fns.php";
require_once "service-fns.php";
require_once "year-over-year-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('#vr');

$showPackageCount = 0 && mattOnlyTEST();

$globalDays = 7;


function statsTableFor($yearKey, $start, $days, $lastYear) {
	global $stats, $clientZIPs;
	global $globalDays, $globalDays0;
	$start = date('Y-m-d', strtotime($start));
	if($lastYear)
		$start = ((int)substr($start, 0, 4) - 1).substr($start, 4);
	$days = $days ? $days : ($lastYear ? $globalDays0 : $globalDays);
	$days = $days - 1; // for display
	$end = date('Y-m-d', strtotime("+ $days DAYS", strtotime($start)));
	$start = longDayAndDate(strtotime($start)).', '.date('Y', strtotime($start));
	$end = longDayAndDate(strtotime($end)).', '.date('Y', strtotime($end));
	echo "<table style='width:90%'>";
	echo "<tr><td class='fontSize1_1em' colspan=3 align='center'>$start - $end</td></tr>";
	echo "<tr><td>&nbsp;</td><td class='dolla'>Rev</td><td class='dolla'>Pay</td></tr>";
	$countDisplay = $stats[$yearKey]['visits'] ? $stats[$yearKey]['visits'] : 'none';
	echo "<tr><td>Visits: ($countDisplay)</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['visitscharge'], $cents=false)
					."</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['rate'], $cents=false)
					."</td></tr>";
	$countDisplay = $stats[$yearKey]['surcharges'] ? $stats[$yearKey]['surcharges'] : 'none';
	echo "<tr><td>Surcharges: ($countDisplay)</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['surchargescharge'], $cents=false)
					."</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['surchargesrate'], $cents=false)
					."</td></tr>";
	$countDisplay = $stats[$yearKey]['misccharges'] ? $stats[$yearKey]['misccharges'] : 'none';
	echo "<tr><td>Misc charges: ($countDisplay)</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['misccargescharge'], $cents=false)
					."</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['miscchargesrate'], $cents=false)
					."</td></tr>";
					
	if($days >= 28) {
		$countDisplay = $stats[$yearKey]['monthlycharges'] ? $stats[$yearKey]['monthlycharges'] : 'none';
		echo "<tr><td>Monthly Contracts: ($countDisplay)</td><td class='dolla'>"
						.dollarAmount($stats[$yearKey]['monthlychargescharge'], $cents=false)
						."</td><td class='dolla'>"
						."--"
						."</td></tr>";
	}
	echo "<tr><td style='font-weight:bold;'>Total:</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['charge'], $cents=false)
					."</td><td class='dolla'>"
					.dollarAmount($stats[$yearKey]['rate'], $cents=false)
					."</td></tr>";
					
	$showHide = "<span style='font-weight:normal;font-size: 0.9em;'> (show / hide)</span>";
					
	echo "<tr><td colspan=3 align='center'>&nbsp;</td></tr>";
	echo "<tr class='shrinkBanner'><td colspan=3  align='center' onclick='$(\".sitterrow\").toggle()'>Sitter Activity$showHide</td></tr>";
	$sitterNames = getProviderNames("ORDER BY lname, fname");
	$totalVisits = array_sum((array)$stats[$yearKey]['sitters']);
	foreach($sitterNames as $provid => $nm) {
		if($countDisplay = $stats[$yearKey]['sitters'][$provid]) {
			$percentDisplay = percentDisplay($countDisplay, $totalVisits);
			echo "<tr class='sitterrow'><td>$nm ($countDisplay) $percentDisplay</td><td class='dolla'>"
							.dollarAmount($stats[$yearKey]['sitterrev'][$provid], $cents=false)
							."</td><td class='dolla'>"
							.dollarAmount($stats[$yearKey]['sitterpay'][$provid], $cents=false)
							."</td></tr>";
		}
	}
	if($countDisplay = $stats[$yearKey]['sitters'][0]) {
		$percentDisplay = percentDisplay($countDisplay, $totalVisits);
		echo "<tr class='sitterrow'><td style='font-weight:bold;'><i>Unassigned</i> ($countDisplay) $percentDisplay</td><td class='dolla'>"
						.dollarAmount($stats[$yearKey]['sitterrev'][0], $cents=false)
						."</td><td class='dolla'>"
						.dollarAmount($stats[$yearKey]['sitterpay'][0], $cents=false)
						."</td></tr>";
	}
	
	echo "<tr><td colspan=3 align='center'>&nbsp;</td></tr>";
	echo "<tr class='shrinkBanner'><td colspan=3  align='center' onclick='$(\".servicerow\").toggle()'>Services$showHide</td></tr>";
	$serviceNames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype ORDER BY label");
	$totalVisits = array_sum((array)$stats[$yearKey]['services']);
	foreach($serviceNames as $servicecode => $nm) {
		if($countDisplay = $stats[$yearKey]['services'][$servicecode]) {
			$percentDisplay = percentDisplay($countDisplay, $totalVisits);
			echo "<tr class='servicerow'><td>$nm ($countDisplay) $percentDisplay</td><td class='dolla'>"
							.dollarAmount($stats[$yearKey]['servicescharge'][$servicecode], $cents=false)
							."</td><td class='dolla'>"
							.dollarAmount($stats[$yearKey]['servicesrate'][$servicecode], $cents=false)
							."</td></tr>";
		}
	}

	echo "<tr><td colspan=3 align='center'>&nbsp;</td></tr>";
	echo "<tr class='shrinkBanner'><td colspan=3  align='center' onclick='$(\".ziprow\").toggle()'>ZIP Codes$showHide</td></tr>";

	$zips = array_keys((array)$stats[$yearKey]['zips']);
	sort($zips);
//echo "ZIPS: ".print_r($stats['zips'],1);
/*	$stats[$yearKey]['zips'][$clientZIPs['clientptr']] += 1;
	$stats[$yearKey]['zipsrev'][$clientZIPs['clientptr']] += $charge;
	$stats[$yearKey]['zipspay'][$clientZIPs['clientptr']] += $rate;
*/

	$totalVisits = array_sum((array)$stats[$yearKey]['zips']);
	foreach((array)$zips as $zip) {
		$countDisplay = $stats[$yearKey]['zips'][$zip] ? $stats[$yearKey]['zips'][$zip] : 'none';
		$percentDisplay = percentDisplay($countDisplay, $totalVisits);
		
		$zipLabel = $zip ? $zip : 'no ZIP';
		echo "<tr class='ziprow'><td>$zipLabel ($countDisplay) $percentDisplay</td><td class='dolla'>"
						.dollarAmount($stats[$yearKey]['zipsrev'][$zip], $cents=false)
						."</td><td class='dolla'>"
						.dollarAmount($stats[$yearKey]['zipspay'][$zip], $cents=false)
						."</td></tr>";
	}
	
if(staffOnlyTEST() || dbTEST('themonsterminders')) {
	
	echo "<tr><td colspan=3 align='center'>&nbsp;</td></tr>";
	echo "<tr class='shrinkBanner'><td colspan=3  align='center' onclick='$(\".schedulerow\").toggle()'>Schedule Types$showHide</td></tr>";
	echo "<tr><td colspan=3 align='center' class='schedulerow'>* Includes canceled visits.<p>";
	echo "<u>Clients with Recurring Schedules Visits</u><p>";
	echo "<table width='100%'>";
	foreach($stats[$yearKey]['recurring'] as $month => $clients) {
		if($month == 'total') continue;
		$label = date('F Y', strtotime($month));
		echo "<tr><td>$label</td><td>".count($clients)."</td></tr>";
	}
	echo "<tr><td>Total</td><td>".count($stats[$yearKey]['recurring']['total'])."</td></tr>";
	
	echo "</table>";
	
	echo "<u>Clients with Nonrecurring Schedule Visits</u><p>";
	
	global $showPackageCount;
	
	echo "<table width='100%'>";
	foreach($stats[$yearKey]['nonrecurring'] as $month => $clients) {
		if($month == 'total') continue;
if($showPackageCount) $packs = "(".count($stats[$yearKey]['nonrecurringpacks'][$month]).")";
		$label = date('F Y', strtotime($month));
		echo "<tr><td>$label</td><td>".count($clients)." $packs</td></tr>";
	}
if($showPackageCount) $packs = "(".count($stats[$yearKey]['nonrecurringpacks']['total']).")";
	echo "<tr><td>Total</td><td>".count($stats[$yearKey]['nonrecurring']['total'])." $packs</td></tr>";
	
	echo "</table>";
	echo "</td></tr>";	
	
}	
	echo "</table>";
}

function combinedYearTable($headerSingular, $year1, $year2, $mainKey, $revKey, $payKey, $names=null) {
	global $stats, $clientZIPs;
	global $globalDays;
	
//print_r(array_keys($stats));echo "$year1][$mainKey]<hr>";
	$allKeys = array_unique(array_merge(array_keys((array)$stats['lastyear'][$mainKey]), array_keys((array)$stats['thisyear'][$mainKey])));
	$combinedTable = array();
	foreach($allKeys as $key) {
		$row = array();
		$row['key'] = $names ? $names[$key] : ($key ? $key : '--');
		$years = array('lastyear', 'thisyear');

		foreach($years as $year) {
			$totalVisits = array_sum((array)$stats[$year][$mainKey]);
			$countDisplay = $stats[$year][$mainKey][$key] ? $stats[$year][$mainKey][$key] : '0';
			$percentDisplay = percentDisplay($countDisplay, $totalVisits);
			$row["count_$year"] = "($countDisplay) $percentDisplay";
		}
		foreach($years as $year) {
			$row["rev_$year"] = dollarAmount($stats[$year][$revKey][$key], $cents=false);
		}
		foreach($years as $year) {
			$row["pay_$year"] = dollarAmount($stats[$year][$payKey][$key], $cents=false);
		}
		$combinedTable[] = $row;
	}
//print_r($combinedTable);
	$mainCols = explode(',', "$headerSingular,Count,Revenue,Pay");
	echo "<table border=1 bordercolor=lightgrey width=90%><tr>";
	foreach($mainCols as $i=>$label) {
		echo "<th $span>$label</th>";
		if(!$span) $span = 'class="count" colspan=2';
	}
	echo "</tr><tr><th></th>";
	$years = array($year1, $year2);
	$colKeys =  array('Count'=>$mainKey, 'Revenue'=>$revKey, 'Pay'=>$payKey);
	foreach($mainCols as $i=>$label) {
		$sectionKey =  $colKeys[$label];
		if($i) foreach($years as $year) {
			$start = date('Y-m-d', strtotime($_REQUEST['start']));
			$yearLabel = $year == $year1 ? 'lastyear' : 'thisyear';
			//$coltype = strpos(
			
			$buttonStyle = " class='fa fa-bar-chart fa-1x' style='display:inline;color:blue;cursor:pointer;'"; //"style='width:5px height:5px;'";
			//$chartButton = "<div $buttonStyle title='Chart' onclick='lightBoxChart(\"col|$sectionKey|$globalDays|$start|$yearLabel|$label\", \"$year $headerSingular $label\")'></div>";

			
			//$chartButton = echoButton('', 'X', 
			//	"lightBoxChart(\"col|$sectionKey|$globalDays|$start|$yearLabel|$label\", \"$year $headerSingular $label\")", 1, 2, 3);
			echo "<th $class>$year $chartButton</th>";
		}
		if(!$class) $class = 'class="count"';
		else $class = 'class="dolla"';
	}
	echo "</tr>";
		
	foreach($combinedTable as $row) {
		echo "<tr>";
		$class = null;
		$i = 0;
		foreach($row as $x => $cell) {
			if($i == 1) $class = 'class="count"';
			if($i == 3) $class = 'class="dolla"';
			$i++;
			echo "<td $class>$cell</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}

function combinedYearCSV($headerSingular, $year1, $year2, $mainKey, $revKey, $payKey, $names=null) {
	global $stats, $clientZIPs;
	global $globalDays;
	
//print_r(array_keys($stats));echo "$year1][$mainKey]<hr>";
	$allKeys = array_unique(array_merge(array_keys((array)$stats['lastyear'][$mainKey]), array_keys((array)$stats['thisyear'][$mainKey])));
	$combinedTable = array();
	foreach($allKeys as $key) {
		$row = array();
		$row['key'] = $names ? $names[$key] : ($key ? $key : '--');
		$years = array('lastyear', 'thisyear');

		foreach($years as $year) {
			$totalVisits = array_sum((array)$stats[$year][$mainKey]);
			$countDisplay = $stats[$year][$mainKey][$key] ? $stats[$year][$mainKey][$key] : '0';
			$percentDisplay = percentDisplay($countDisplay, $totalVisits);
			$row["count_$year"] = "$countDisplay";
			$row["count_{$year}_percent"] = "$percentDisplay";
		}
		foreach($years as $year) {
			$row["{$key}rev_$year"] = round($stats[$year][$revKey][$key]);
		}
		foreach($years as $year) {
			$row["{$key}pay_$year"] = round($stats[$year][$payKey][$key]);
		}
		$combinedTable[] = $row;
	}
//print_r($combinedTable);
	$mainCols = explode(',', "$headerSingular,Count {$year1},Percent {$year1},Count {$year2},Percent {$year2},Revenue {$year1},Revenue {$year2},Pay {$year1},Pay {$year2}");
	dumpCSVRow($mainCols);
		
	foreach($combinedTable as $row) {
		dumpCSVRow($row);
	}
}

function percentDisplay($countDisplay, $totalVisits) {
	if($totalVisits) {
		$percent = round($countDisplay/$totalVisits*100);
		if($percent == 0) $percent = number_format($countDisplay/$totalVisits*100, 1);
	}
	else $percent = 0;
	return $percent.'%';
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

extract(extractVars('interval,start,globalDays,print,reportType,csv', $_REQUEST));
//if($start) $end = shortDate(strtotime("+ $days days", strtotime($start)));
		
$pageTitle = "Year Over Year Report";

if($interval) { // 2016Q3 or 2016-11 or 2016
	$year = substr($interval, 0, 4);
	if($interval[0] == 'Y') {
		$year = substr($interval, 1);
		$start = "$year-01-01";
		$globalDays = date('L', strtotime($start)) ? 366 : 365; // L = leapYear? 1 : 0;
		
		$priorYear = $year - 1;
		$globalDays0 = date('L', strtotime("$priorYear-01-01")) ? 366 : 365; // L = leapYear? 1 : 0;
	}
	else if(strpos($interval, 'Q')) { // quarter
		$qstarts = array(1=>"01-01", 2=>"04-01", 3=>"07-01", 4=>"10-01");
		$quarter = substr($interval, 5);
		
		$start = "$year-".$qstarts[$quarter];
		$nextStart = $quarter == 4 ? 1 : $quarter+1;
		$nextStart = ($nextStart == 1 ? $year+1 : $year).'-'.$qstarts[$nextStart];
		$firstDateTime = new DateTime();
		$firstDateTime->setTimestamp(strtotime($start));
		$nextDateTime = new DateTime();
		$nextDateTime->setTimestamp(strtotime($nextStart));
		try {$globalDays = date_diff($nextDateTime, $firstDateTime)->days;}
		catch (Exception $e) {echo "ERROR: ".$e->getMessage();}
		
		$priorYear = $year - 1;
		$pstart = "$priorYear-".$qstarts[$quarter];
		$nextStart = $quarter == 4 ? 1 : $quarter+1;
		$nextStart = ($nextStart == 1 ? $priorYear+1 : $priorYear).'-'.$qstarts[$nextStart];
		$firstDateTime = new DateTime();
		$firstDateTime->setTimestamp(strtotime($pstart));
		$nextDateTime = new DateTime();
		$nextDateTime->setTimestamp(strtotime($nextStart));
		try {$globalDays0 = date_diff($nextDateTime, $firstDateTime)->days;}
		catch (Exception $e) {echo "ERROR: ".$e->getMessage();}
	}
	else if(strpos($interval, 'H')) { // half year
		$hstarts = array(1=>"01-01", 2=>"07-01");
		$half = substr($interval, 5);
		$start = "$year-".$hstarts[$half];
		$nextStart = $half == 2 ? 1 : $half+1;
		$nextStart = ($nextStart == 1 ? $year+1 : $year).'-'.$hstarts[$nextStart];
		$firstDateTime = new DateTime();
		$firstDateTime->setTimestamp(strtotime($start));
		$nextDateTime = new DateTime();
		$nextDateTime->setTimestamp(strtotime($nextStart));
		try {$globalDays = date_diff($nextDateTime, $firstDateTime)->days;}
		catch (Exception $e) {echo "ERROR: ".$e->getMessage();}

		$priorYear = $year - 1;
		$pstart = "$priorYear-".$hstarts[$half];
		$nextStart = $half == 2 ? 1 : $half+1;
		$nextStart = ($nextStart == 1 ? $priorYear+1 : $priorYear).'-'.$hstarts[$nextStart];
		$firstDateTime = new DateTime();
		$firstDateTime->setTimestamp(strtotime($pstart));
		$nextDateTime = new DateTime();
		$nextDateTime->setTimestamp(strtotime($nextStart));
		try {$globalDays0 = date_diff($nextDateTime, $firstDateTime)->days;}
		catch (Exception $e) {echo "ERROR: ".$e->getMessage();}
	}
	else { // month
		list($year, $month) = explode('-', $interval);
		$start = date('Y-m-01', strtotime("$month 1, $year"));
		$globalDays = date('t', strtotime($start));
//print_r($_REQUEST); echo "BANG! $year month: [$month] [$start] [$interval] [$month/1/$year] globalDays: ".print_r($globalDays,1);	
	}
}






if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";
	$extraHeadContent = 
				"<style>.dolla {text-align:right;} .count {text-align:center;}</style>"
				.'<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';	
;
	include "frame.html";
	// ***************************************************************************
?>
	<span>Compare uncanceled visits and revenues to the prior year.  Revenues are for charge items indicated, were not necessarily collected, and do not include taxes.</span><p>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?

	fauxLink('Choose a Time Frame', "$(\".choicetable\").toggle()", null, "Choose a month or Financial Quarter", null, 
							$class='fauxlink fontSize1_1em');
	echo " or... ";

	$options = array(1=>1, 7=>7, 28=>28, 29=>29, 30=>30, 31=>31	, 90=>90);
	if(!staffOnlyTEST()) unset($options[90]);
	$globalDaysInput = selectElement('', 'globalDays', $value=$globalDays, $options, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=true, $optExtras=null, $title=null);
	calendarSet("For the $globalDaysInput days starting:", 'start', $start, null, null, true, 'end');
	hiddenElement('csv', '');
	hiddenElement('interval', '');
	echo "<img src='art/spacer.gif' width=0 height=1>";
	$yearLabel = $start ? date('Y', strtotime($start)) : date('Y');
	//echoButton('', "$yearLabel Visits Chart", "lightBoxChart(\"yoy-visits-ytd-month&baseYear=$yearLabel\", \"Visits\")");
	//echo "<img src='art/spacer.gif' width=20 height=1>";
	//echoButton('', "$yearLabel Revenue Chart", "lightBoxChart(\"yoy-rev-ytd-month&baseYear=$yearLabel\", \"Revenue\")");
	print_r(presetsArray(presets()));

?>
	</td><tr>
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
				'start', '', 'isDate')) document.reportform.submit();
	}
	
	function genIntervalReport(interval) {
		document.getElementById('interval').value=interval;
		document.reportform.submit();
	}
	
	function genCSV() {
		if(MM_validateForm(
				'start', '', 'R',
				'start', '', 'isDate')) {
			document.getElementById('csv').value=1;
			<? if($interval) echo "document.getElementById('interval').value='$interval';\n"; ?>
		  document.reportform.submit();
			document.getElementById('csv').value=0;
			<? if($interval) echo "document.getElementById('interval').value='';\n"; ?>
		}
	}
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			var start = escape(document.getElementById('start').value);
			var end = escape(document.getElementById('end').value);
			var reportType = null;
			var types = document.getElementsByName('reportType');
			for(var i=0; i < types.length; i++)
				if(types[i].checked) reportType = types[i].value;
			openConsoleWindow('reportprinter', 'reports-revenue-zip.php?print=1&start='+start+'&end='+end+'&reportType='+reportType, 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Year-over-year.csv ");
	dumpCSVRow('Year Over Year Report');
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start $globalDays days");
}
else {
	$windowTitle = 'Year Over Year Report';
	require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Year Over Year Report</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
	echo "<p style='text-align:center'>Period: $start - $end</p>";


}


		

if($start /*&& $end */) {
//if(mattOnlyTEST()) echo "[[[$start]]]";
	$yearKey = 'thisyear';
//if(mattOnlyTEST()) print_r($_REQUEST); echo "BANG! $year quarter: [$quarter] [$start]=> [$nextStart] globalDays: ".print_r($globalDays,1)."  globalDays0: ".print_r($globalDays0,1);	
	$globalDays0 = $globalDays0 ? $globalDays0 : $globalDays;
	compileStats('lastyear', $start, $globalDays0, $lastYear=true);
	compileStats('thisyear', $start, $globalDays, $lastYear=false);
//print_r($stats);
	if($csv) {
		$year2 = date('Y', strtotime($start));
		$year1 = (int)$year2 - 1;
		$sitterNames = getProviderNames("ORDER BY lname, fname");
		$sitterNames[0] = 'Unassigned';
		echo "\n\n";
		combinedYearCSV('Sitters', $year1, $year2, 'sitters', 'sitterrev', 'sitterpay', $names=$sitterNames);
		echo "\n\n";
		$serviceNames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype ORDER BY label");
		combinedYearCSV('Services', $year1, $year2, 'services', 'servicescharge', 'servicesrate', $names=$serviceNames);
		echo "\n\n";
		combinedYearCSV('ZIP Code', $year1, $year2, 'zips', 'zipsrev', 'zipspay', $names=null);
		exit;
	}
	if(!$csv) {
		echo "<table valign=top style='width:100%'><tr>";
		echo "<td style='background:#cccccc;vertical-align:top;'>";
		statsTableFor('lastyear', $start, $days, $lastYear=true);
		echo "</td>";
		echo "<td valign=top>";
		statsTableFor('thisyear', $start, $days, $lastYear=false);
		echo "</td>";
		echo "</tr></table>";
		
		$year2 = date('Y', strtotime($start));
		$year1 = (int)$year2 - 1;
		echo "<p>";
		echoButton('', 'Download Spreadsheet', "genCSV()");
		$sitterNames = getProviderNames("ORDER BY lname, fname");
		$sitterNames[0] = '<i>Unassigned</i>';
		combinedYearTable('Sitters', $year1, $year2, 'sitters', 'sitterrev', 'sitterpay', $names=$sitterNames);
		echo "<p>";
		$serviceNames = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype ORDER BY label");
		combinedYearTable('Services', $year1, $year2, 'services', 'servicescharge', 'servicesrate', $names=$serviceNames);
		echo "<p>";
		combinedYearTable('ZIP Code', $year1, $year2, 'zips', 'zipsrev', 'zipspay', $names=null);
	}
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
?>
<script language='javascript'>
	function lightBoxChart(chart, title) {
		var url = 'https://leashtime.com/chartist-box.php?title='+title+'&width=580&height=350&chart='+chart;
		$.fn.colorbox({href:url, width:700, height:500, scrolling: true, opacity: "0.3", iframe: "true"});
	}
	
	
	$(".sitterrow").toggle();$(".servicerow").toggle();$(".ziprow").toggle();$(".schedulerow").toggle();
</script>
<?

// ***************************************************************************
	include "frame-end.html";
}
else if($print) {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}	
	</script>
<?
}
