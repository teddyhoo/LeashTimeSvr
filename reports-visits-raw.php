<? // reports-visits-raw.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "item-note-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('start,end,print,provider,client,servicetype,status,scheduletype,sort,csv,includeaddressesinspreadsheet,zerocharge,zerorate', $_REQUEST));

$clientDetail = $_POST ? $clientDetail : 1;

$clientSummaryTEST = (1 && staffOnlyTEST()) || dbTEST('pawlosophy');
$averageSitterPayTEST = (staffOnlyTEST()) || dbTEST('bluedogpetcarema');
		
$pageTitle = "Visits Detailed Analysis";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	$extraHeadContent = "<style>.CANCELED {background:pink;}</style>";
	include "frame.html";
//if(mattOnlyTEST()) print_r($_POST);	
	// ***************************************************************************
?>
	<form name='reportform' method='POST'>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	if(mattOnlyTEST()) echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	echoButton('', 'Download Spreadsheet', "genCSV()");
	labeledCheckbox("  Include client addresses", 'includeaddressesinspreadsheet', $value=$includeaddressesinspreadsheet, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title="Include addresses in downloaded spreadsheet");
	echo "<p>";
?>
	
	<table>
	<tr><td colspan=2>
<?
	calendarSet('Visits scheduled in the period starting:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('and ending:', 'end', $end);
	hiddenElement('csv', '');
	hiddenElement('client', $client);
?>
	</td></tr>
	<tr><td colspan=2>
<?
$options = array('All Sitters'=>-1);
if(TRUE || mattOnlyTEST()) $options = array_merge($options, array('All Active Sitters'=>-2, 'All Inactive Sitters'=>-3));
$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), providerid FROM tblprovider ORDER BY lname, fname"));
labeledSelect('Sitters: ', 'provider', $provider, $options);

if(TRUE || staffOnlyTEST() || dbTEST('queeniespets')) { // published 8/7/2018
$options = array('All Clients'=>-1);
$options = array_merge($options, fetchKeyValuePairs("SELECT CONCAT_WS(' ', fname, lname), clientid FROM tblclient ORDER BY lname, fname"));
echo " ";
labeledSelect('Clients: ', 'client', $client, $options);

$options = array('Any Status'=>-1, 'Completed'=>'completed', 'Incomplete'=>'incomplete', 'Canceled'=>'canceled');
echo " ";
labeledSelect('Status: ', 'status', $status, $options);

echo "<p>";
$options = array('All Service Types'=>-1);
$options = array_merge($options, fetchKeyValuePairs("SELECT label, servicetypeid FROM tblservicetype ORDER BY label"));
$checkedTypes = $servicetype && is_array($servicetype) ? $servicetype : ($servicetype ? array($servicetype) : array());

$deployFancyServiceTypeChooser = true;

if($deployFancyServiceTypeChooser) {
	// no-op -- move after Schedule Type
}
else if(dbTEST('dogsofberkeley')) {
	echo "Service Types: <select multiple=1 size=3 id='servicetype' name='servicetype[]' class='standardInput'>\n";
//print_r($checkedTypes);	
	foreach($options as $optLabel => $optValue) {
		$checked = in_array($optValue, $checkedTypes) ? 'SELECTED' : '';
		echo "\n\t<option value='$optValue' $checked>$optLabel</option>\n";
	}
	echo "</select>\n";
} 
else labeledSelect('Service Types: ', 'servicetype', $servicetype, $options);

$options = array('All Schedule Types'=>-1, 'Short Term'=>'short term', 'Ongoing'=>'ongoing', 'Fixed Price'=>'fixed price');
echo " ";
labeledSelect('Schedule Type: ', 'scheduletype', $scheduletype, $options);

if($deployFancyServiceTypeChooser) {
	$allTypes = fetchKeyValuePairs("SELECT servicetypeid, label  FROM tblservicetype ORDER BY label");
	$serviceTypeLabel = 
		!$checkedTypes ? '--none--' : (
		is_array($checkedTypes) && count($checkedTypes) == 1 ? $allTypes[current($servicetype)] : 
		count($servicetype).' selected');
			
	echo " <span  onclick='toggleServiceTypes()'>Service Types: </span><span id='serviceTypesChosenLabel' onclick='toggleServiceTypes()' style='border:solid lightgray 1px; padding:2px; cursor: pointer;'>Service Types: $serviceTypeLabel</span>";
	servicesTable($checkedTypes);
}
}

if(staffOnlyTEST() || dbTEST('savinggrace')) {
//zerocharge,zerorate
echo "<p>";
labeledCheckbox("Only ZERO Charge Visits", 'zerocharge', $zerocharge, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title="Show only visits with a total charge less than $1");
echo " ";
labeledCheckbox("Only ZERO Pay Visits", 'zerorate', $zerorate, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title="Show only visits with a total pay rate less than $1");
//zerocharge,zerorate
}
//if(mattOnlyTEST()) labeledCheckbox("Show Surcharges", 'showsurcharges', $showsurcharges, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title="Show surcharges in the period.");
// Including surcharges would be tricky.  Because of the possibility of really large query return sets,
// this report does NOT generate an array from the query result.  Instead, it passes the query result
// tableFrom() and tableFrom() iterates through result itself.
// Because of this, it would become necessary to merge rows from one table (tblappointment) with rows 
// drawn from another table (tblsurcharge) IN THE DATABASE QUERY ITSELF.  I imagine this would be
// possible, but I do not kow how to do it at the present time, especially in a way that imposes a
// sort on the entire return set.  Perhaps this could be accomplished with two separate queries that 
// created, populated, read, and destroyed emporary table, perhaps?  Seems like a lotta work.
?>
	</td></tr>
	
	</table>
	</form>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start','Starting Date','end','Ending Date');
	fillServiceTypesChosenLabel();
	
	function fillServiceTypesChosenLabel() {
		var labels = [];
		var els = document.getElementsByName('servicetype[]');
		for(var i=0; i<els.length;i++) {
			if(els[i].checked) labels[labels.length] = els[i].getAttribute('label');
		}
		
		var chosen = labels.join(', ');
		var maxChars = 75;
		if(chosen.length > maxChars) chosen = chosen.substr(0, maxChars)+'...';
		var serviceTypesLabel = labels.length == 0 ? '-- Click to Choose -- ' : (
			labels.length == 1 ? chosen :
			'('+labels.length+') '+chosen
			);

		document.getElementById('serviceTypesChosenLabel').innerHTML = serviceTypesLabel;
	}

	function toggleServiceTypes() {
		var el = document.getElementById('serviceTypesTable');
		var disp = el.style.display;
		el.style.display = disp == 'none' ? 'block' : 'none';
	}
	
	function genReport() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) document.reportform.submit();
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
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			var start, end, provider;
			start = document.getElementById('start').value;
			end = document.getElementById('end').value;
			provider = document.getElementById('provider').value;
			openConsoleWindow('reportprinter', 
				'reports-visits-raw.php?print=1&start='+start+
				'&end='+end+
				'&provider='+provider, 700,700);
		}
	}
	
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
header("Cache-Control: no-store, no-cache");
header("Pragma:");
	$contentType = mattOnlyTEST() ?  : " application/vnd.ms-excel " ;" text/csv ";
	header("Content-Type: $contentType");
	$disposition = 0 && mattOnlyTEST() ?  : " attachment; filename=Visits-Scheduled.csv " ;" inline; filename=Visits-Scheduled.csv ";
	header("Content-Disposition: $disposition ");
	dumpCSVRow($pageTitle);
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
}
if($start && $end) {
	$provs = fetchKeyValuePairs(
			"SELECT providerid, CONCAT_WS(' ', fname, lname), lname, fname
				FROM tblprovider
				ORDER BY lname, fname");
	$provs[0] = 'Unassigned';
	$provids = array_keys($provs); // why?
//echo ">>>";print_r($wages);
	$visits = fetchVisits($start, $end, $provider, $client, $servicetype, $status, $scheduletype);
	if($csv) visitsCSV($start, $end, $visits);
	else {
		visitsTable($start, $end, $visits);
		echo "<table><tr><td style='vertical-align:top'>";
		if(TRUE) { // staffOnlyTEST() || dbTEST('queeniespets')
			$visits = fetchVisits($start, $end, $provider, $client, $servicetype, $status, $scheduletype);
			sitterSummaryTable($visits);
		}
		echo "</td>";
		if($clientSummaryTEST) { // staffOnlyTEST() || dbTEST('pawlosophy')
			echo "<td style='vertical-align:top;padding-left:30px;'>";
//echo "<hr>";		
			$visits = fetchVisits($start, $end, $provider, $client, $servicetype, $status, $scheduletype);
			clientSummaryTable($visits);
			echo "</td>";
		}
		if($averageSitterPayTEST) {
			echo "</tr><tr><td style='vertical-align:top;padding-left:30px;'>";
//echo "<hr>";		
			$visits = fetchVisits($start, $end, $provider, $client, $servicetype, $status, $scheduletype);
			echo sitterAveragesTable($visits, $csv=false);
			echo "</td>";
		}
		echo "</tr></table>";
	}
}
if(!$print && !$csv){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}

function servicesTable($checkedTypes) {
	$activeTypes = 
		fetchKeyValuePairs("SELECT label, servicetypeid FROM tblservicetype WHERE active=1 ORDER BY menuorder, label");
	$inactiveTypes =
		fetchKeyValuePairs("SELECT label, servicetypeid FROM tblservicetype WHERE active=0 ORDER BY menuorder, label");
	$activeRows = array_chunk($activeTypes, 3, $preserve_keys=true);
	$inactiveRows = array_chunk($inactiveTypes, 3, $preserve_keys=true);
	echo "<table id='serviceTypesTable' style='display:none;background:LemonChiffon;'>";
		echo "<tr><td class='fontSize1_2em'>".fauxLink('Hide This Section','toggleServiceTypes()', 1, 2)."</td></tr>";
	foreach($activeRows as $row) {
		echo "<tr>";
		foreach($row as $label => $typeid) {
			$checked = in_array($typeid, $checkedTypes) ? 'CHECKED' : '';
			$safeLabel = safeValue($label);
			$label = "<label for='stype_$typeid'> $label";
			echo "<td><input label = '$safeLabel' id='stype_$typeid' type='checkbox' name='servicetype[]' value='$typeid' class='servicetype' onchange='fillServiceTypesChosenLabel()' $checked> $label</td>";
		}
		echo "</tr>";
	}
	echo "<tr><td colspan=3 style='font-weight:bold;'>Inactive Service Types</td></tr>";
	foreach($inactiveRows as $row) {
		echo "<tr>";
		foreach($row as $label => $typeid) {
			$checked = in_array($typeid, $checkedTypes) ? 'CHECKED' : '';
			$safeLabel = safeValue($label);
			$label = "<label for='stype_$typeid'>$label";
			echo "<td style='font-style:italic;'><input label = '$safeLabel' id='stype_$typeid' type='checkbox' name='servicetype[]' value='$typeid' class='servicetype' onchange='fillServiceTypesChosenLabel()' $checked> $label</td>";
		}
		echo "</tr>";
	}
	echo "</table>";
}

function fetchVisits($start, $end, $providerid, $clientid=null, $servicetypeid=null, $status=null, $scheduletype=null) {

	global $csv, $zerocharge, $zerorate;
	$rows = array();
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	if(!$providerid || $providerid == -1) ; //no-op$filter[] = "providerptr = $providerid";
	else if($providerid == -2) $filter[] = "tblprovider.active = 1";
	else if($providerid == -3) $filter[] = "tblprovider.active = 0";
	else $filter[] = "providerptr = $providerid";
	if($clientid && $clientid != -1) $filter[] = "tblappointment.clientptr = $clientid";
	if($servicetypeid && $servicetypeid != -1) {
		if(is_array($servicetypeid)) 
			$filter[] = "tblappointment.servicecode IN (".join(',', $servicetypeid). ")";
		else $filter[] = "tblappointment.servicecode = $servicetypeid";
	}
	if($status && $status != -1) $filter[] = 
		$status == 'completed' ? "tblappointment.completed IS NOT NULL" : (
		$status == 'canceled' ? "tblappointment.canceled IS NOT NULL" : (
		$status == 'incomplete' ? "(tblappointment.completed IS NULL AND tblappointment.canceled IS NULL)" :
		""));
	if($scheduletype && $scheduletype != -1) $filter[] = 
		$scheduletype == 'fixed price' ? "recurringpackage AND monthly = 1" : (
		$scheduletype == 'ongoing' ? "recurringpackage AND monthly != 1" :
		"NOT recurringpackage");

	if($zerocharge) $filter[] = "tblappointment.charge+IFNULL(adjustment, 0) < 1";
	if($zerorate) $filter[] = "tblappointment.rate+IFNULL(bonus, 0) < 1";
	
	$filter = $filter ? "AND ".join(' AND ', $filter) : '';
	if(!$csv) {
		$formattedFields = explodePairsLine('charge|charge||adjustment|adjustment||rate|rate||bonus|bonus');
		foreach($formattedFields as $field) 
			$formattedFields[$field] = "IF($field IS NULL, $field, CONCAT_WS(' ', '".getCurrencyMark()."', FORMAT($field, 2))) as formatted$field";
		$formattedFields = join(', ', $formattedFields).', ';
		}
	// disambiguate charge...
	//$formattedFields['charge'] = "IF(tblappointment.charge IS NULL, tblappointment.charge, CONCAT_WS(' ', '".getCurrencyMark()."', FORMAT(tblappointment.charge, 2))) as formatted$field";
	
	if(1 && $csv && mattOnlyTEST()) {
		$billingFields = ", billableid, b.charge as bcharge, b.tax, b.paid";
		$billingJOIN = "LEFT JOIN tblbillable b ON itemtable = 'tblappointment' AND itemptr = appointmentid AND superseded = 0";
	}
	if($csv) $csvInclusions = ", surchargenote, tblappointment.cancellationreason";
	$sql = "SELECT DISTINCT tblappointment.*, $formattedFields
					IF(recurringpackage, 
						IF(monthly = 1,'fixed price', 'ongoing'),
						'short term') as recurring,
					CONCAT_WS(' ', tblappointment.date, starttime) as datetime, 
					CONCAT_WS(' ', tblclient.fname, tblclient.lname) as client, 
					CONCAT_WS(',', tblclient.lname, tblclient.fname) as clientsort,
					tblclient.zip as zip,
					IF(providerptr=0,'Unassigned', CONCAT_WS(' ', tblprovider.fname, tblprovider.lname)) as provider,
					tblprovider.nickname,
					IF(providerptr=0,'0Unassigned', CONCAT_WS(',', tblprovider.lname, tblprovider.fname)) as providersort,
					IF(completed IS NOT NULL, 'completed', IF(canceled IS NOT NULL, 'CANCELED', 'INCOMPLETE')) AS status,hours,
					IF(hours IS NULL, hours,  FORMAT(TIME_TO_SEC(CONCAT(hours, ':00')) / 3600, 3)) as formattedhours,
					tblappointment.modified as apptmodified,
					tblappointment.created as apptcreated,
					completed as completiontime,
					canceled as cancellationtime,
					IFNULL(arrivaltrack.date, '--') as arrived,
					/* COMMENTED OUT TO AVOID VISI DUPLICATION IFNULL(completiontrack.date, '--') as completed,*/
					label
					$billingFields
					$csvInclusions
					FROM tblappointment
					$billingJOIN
					LEFT JOIN tblclient ON clientid = tblappointment.clientptr
					LEFT JOIN tblprovider ON providerid = providerptr
					LEFT JOIN tblservicetype ON servicetypeid = servicecode					
					LEFT JOIN tblrecurringpackage ON packageid = packageptr					
					LEFT JOIN tblgeotrack arrivaltrack ON arrivaltrack.appointmentptr = appointmentid AND arrivaltrack.event = 'arrived'				
					/* COMMENTED OUT TO AVOID VISI DUPLICATION LEFT JOIN tblgeotrack completiontrack ON completiontrack.appointmentptr = appointmentid AND completiontrack.event = 'completed'*/
					WHERE tblappointment.date >= '$start' AND tblappointment.date <= '$end' $filter
					ORDER BY date, starttime";
//if(mattOnlyTEST()) {echo $sql;exit;}					
//if(mattOnlyTEST()) {$rows = fetchAssociations($sql); foreach($rows as $i=>$r) if($r['clientptr'] != 2626) unset($rows[$i]);print_r($rows);exit;}					
	$result = doQuery($sql);
	return $result;
}

function addTrackedCompletionTime(&$appt) { // introduced because two LEFT JOINS on tblgeotrack in fetchVisits led to duplicates
	$completed = fetchRow0Col0(
		"SELECT date 
			FROM tblgeotrack 
			WHERE appointmentptr = {$appt['appointmentid']} AND event = 'completed' LIMIT 1", 1);

	$appt['completed'] = $completed ? $completed : '--';
}

function sitterAveragesTable($rows, $csv=false) {
	// return a string
	$visitCounts = array(); // provid=>array(total=>count,type1=>count, type2=>count...)
	$totalSitterVisitPay = array(); // provid=>array(total=>sum,type1=>sum, type2=>sum...)
	while($row = fetchResultAssoc($rows)) {
		if(!$row['completed']) continue;
		$visitCounts['allsitters'][$row['servicecode']] += 1;
		$visitCounts['allsitters']['total'] += 1;
		$visitCounts[$row['providerptr']][$row['servicecode']] += 1;
		$visitCounts[$row['providerptr']]['total'] += 1;
		
		$visitPay = $row['rate'] + $row['bonus'];
		$totalSitterVisitPay[$row['providerptr']]['total'] += $visitPay;
		$totalSitterVisitPay[$row['providerptr']][$row['servicecode']] += $visitPay;
		$totalSitterVisitPay['allsitters']['total'] += $visitPay;
		$totalSitterVisitPay['allsitters'][$row['servicecode']] += $visitPay;
	}
	$sitterNames = fetchKeyValuePairs(
		"SELECT providerid, CONCAT_WS(' ', fname, lname) as name
			FROM tblprovider
			ORDER BY lname, fname", 1);
	$serviceNames = fetchKeyValuePairs(
		"SELECT servicetypeid, label
			FROM tblservicetype
			ORDER BY label", 1);
	$data = array();
	if(TRUE) {
		$data[] = array('name'=>bolded('All Sitters', $csv));
		$meanPay = safeMean($totalSitterVisitPay['allsitters']['total'], $visitCounts['allsitters']['total']);
		if(!$csv) $meanPay = dollarAmount($meanPay);
		$data[] = array('name'=>bolded('All Services', $csv), 'count'=>$visitCounts['allsitters']['total'], 'meanPay'=>$meanPay);
		foreach($serviceNames as $servicecode=>$label) {
			if($visitCounts['allsitters'][$servicecode]) {
				$meanPay = safeMean($totalSitterVisitPay['allsitters'][$servicecode], $visitCounts['allsitters'][$servicecode]);
				$data[] = array('name'=>$label, 'count'=>$visitCounts['allsitters'][$servicecode], 'meanPay'=>$meanPay);
			}
		}
		foreach($sitterNames as $provid => $name) {
			if(!$visitCounts[$provid]['total']) continue;
			$data[] = array('name'=>bolded($name, $csv));
			$meanPay = safeMean($totalSitterVisitPay[$provid]['total'], $visitCounts[$provid]['total']);
			if(!$csv) $meanPay = dollarAmount($meanPay);
			$data[] = array('name'=>bolded('All Services', $csv), 'count'=>$visitCounts[$provid]['total'], 'meanPay'=>$meanPay);
			foreach($serviceNames as $servicecode=>$label) {
				if($visitCounts[$provid][$servicecode]) {
					$meanPay = safeMean($totalSitterVisitPay[$provid][$servicecode], $visitCounts[$provid][$servicecode]);
					if(!$csv) $meanPay = dollarAmount($meanPay);
					$data[] = array('name'=>$label, 'count'=>$visitCounts[$provid][$servicecode], 'meanPay'=>$meanPay);
				}
			}
		}
	}
	$columns = explodePairsLine('name|Sitter/Service||count|Visit Count||meanPay|Mean Pay');
	ob_start();
	ob_implicit_flush(0);
	echo "<h3><a name='SITTERAVERAGES'></a>Average Pay by Sitter/Service</a></h3>";
	tableFrom($columns, $data, $attributes='BORDER=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=array('meanPay'=>'dollaramountcell'), $sortClickAction=null);
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function safeMean($total, $count, $formatted=true) {
	if(!$count) return '--';
	$dividend = $total / $count;
	return $formatted ? number_format($dividend, 2) : $dividend;
}

function bolded($str, $csv) { return $csv ? $str :  "<b>$str</b>";}


function sitterSummaryTable($rows) {
	echo "<h3><a name='SITTERSUMMARIES'></a>Summary by Sitter</a></h3>";
	$statusCounts = array('completed'=>null, 'INCOMPLETE'=>null, 'CANCELED'=>null);
	$statusRevs = array();
	$statusRates = array();
	$sitterCounts = array();
	$sitterRevs = array();
	$sitterRates = array();
	$sitterNominalHours = array();
	while($row = fetchResultAssoc($rows)) {
		$count += 1;
		$statusCounts[$row['status']] += 1;
		$statusRevs[$row['status']] += $row['charge']+$row['adjustment'];
		$statusRates[$row['status']] += $row['rate']+$row['bonus'];
		
		if(!$sitterCounts[$row['provider']]) {
			foreach($statusCounts as $k =>$zzz) {
				$sitterCounts[$row['provider']][$k] = null;
				$sitterRevs[$row['provider']][$k] = null;
				$sitterRates[$row['provider']][$k] = null;
				$sitterVisitTime[$row['provider']][$k] = null;
				$sitterNominalHours[$row['provider']][$k] = null;
			}
		}
		$sitterCounts[$row['provider']][$row['status']] += 1;
		$sitterRevs[$row['provider']][$row['status']] += $row['charge']+$row['adjustment'];
		$sitterRates[$row['provider']][$row['status']] += $row['rate']+$row['bonus'];
		
		$visitlength = 0;
		if($row['arrived'] && $row['arrived'] != '--' && $row['completed'] && $row['completed'] != '--')
			$visitlength = (strtotime($row['completed'])-strtotime($row['arrived'])) / 3600;
		$sitterVisitTime[$row['provider']][$row['status']] += $visitlength;
		$sitterNominalHours[$row['provider']][$row['status']] += $row['formattedhours'];
	}
	foreach(array_keys($statusCounts) as $k=>$v) if(!$statusCounts[$k]) unset($statusCounts[$k]);
	
	echo "Total Visits: ".number_format($count)." Sitters: ".count($sitterCounts)."<table border=1>";
	echo "<tr><td class='sortablelistcell' style='font-weight:bold;'>All Sitters</td>";
	foreach($statusCounts as $k=>$v) if($v) echo "<th class='sortablelistcell $k'>$k</th>";
	echo"</tr>";
	
	echo "<tr><td class='sortablelistcell'>Visits</td>";
	foreach($statusCounts as $k=>$v) if($v) echo "<td class='sortablelistcell $k'>".number_format($v)."</td>";
	echo"</tr>";
	
	echo "<tr><td class='sortablelistcell'>Revenue</td>";
	foreach($statusRevs as $k=>$v) if($v) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
	echo"</tr>";
	
	echo "<tr><td class='sortablelistcell'>Pay</td>";
	foreach($statusRates as $k=>$v) if($v) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
	echo"</tr>";
	
	foreach($sitterCounts as $prov=>$zzz) {
		echo "<tr><td>&nbsp;</td></tr>";
		echo "<tr><td style='font-weight:bold;'>$prov</td>";
		foreach($statusCounts as $k=>$v) if($v) echo "<td class='sortablelistcell $k'>$k</td>";
		echo "</tr>";
		echo "<tr><td class='sortablelistcell'>Visits</td>";
		foreach($sitterCounts[$prov] as $k=>$v) if($statusCounts[$k]) echo "<td class='sortablelistcell $k'>".number_format($v)."</td>";
		echo"</tr>";
		echo "<tr><td class='sortablelistcell'>Nominal Hours</td>";
		foreach($sitterNominalHours[$prov] as $k=>$v) if($statusCounts[$k]) echo "<th class='sortablelistcell $k'>".number_format($v,3)."</td>";
		echo"</tr>";
		echo "<tr><td class='sortablelistcell'>Visit Time</td>";
		foreach($sitterVisitTime[$prov] as $k=>$v) if($statusCounts[$k]) echo "<td class='sortablelistcell $k'>".number_format($v,3)."</td>";
		echo"</tr>";
		echo "<tr><td>Revenue</td>";
		foreach($sitterRevs[$prov] as $k=>$v) if($statusCounts[$k]) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
		echo"</tr>";
		echo "<tr><td>Pay</td>";
		foreach($sitterRates[$prov] as $k=>$v) if($statusCounts[$k]) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
		echo"</tr>";
	}
		echo"</table>";
}

function clientSummaryTable($rows) {
	echo "<h3><a name='CLIENTSUMMARIES'></a>Summary by Client (Staff Only)</a></h3>";
	$statusCounts = array('completed'=>null, 'INCOMPLETE'=>null, 'CANCELED'=>null);
	$statusRevs = array();
	$statusRates = array();
	$clientCounts = array();
	$clientRevs = array();
	$clientRates = array();
	$sitterNominalHours = array();
	while($row = fetchResultAssoc($rows)) {
		$count += 1;
		$statusCounts[$row['status']] += 1;
		$statusRevs[$row['status']] += $row['charge']+$row['adjustment'];
		$statusRates[$row['status']] += $row['rate']+$row['bonus'];
		
		if(!$clientCounts[$row['client']]) {
			foreach($statusCounts as $k =>$zzz) {
				$clientCounts[$row['client']][$k] = null;
				$clientRevs[$row['client']][$k] = null;
				$clientRates[$row['client']][$k] = null;
				$clientVisitTime[$row['client']][$k] = null;
				$clientNominalHours[$row['client']][$k] = null;
			}
		}
		$clientCounts[$row['client']][$row['status']] += 1;
		$clientRevs[$row['client']][$row['status']] += $row['charge']+$row['adjustment'];
		$clientRates[$row['client']][$row['status']] += $row['rate']+$row['bonus'];
		
		$visitlength = 0;
		if($row['arrived'] && $row['arrived'] != '--' && $row['completed'] && $row['completed'] != '--')
			$visitlength = (strtotime($row['completed'])-strtotime($row['arrived'])) / 3600;
		$clientVisitTime[$row['client']][$row['status']] += $visitlength;
		$clientNominalHours[$row['client']][$row['status']] += $row['formattedhours'];
	}
	foreach(array_keys($statusCounts) as $k=>$v) if(!$statusCounts[$k]) unset($statusCounts[$k]);
	
	echo "Total Visits: ".number_format($count)." Clients: ".count($clientCounts)."<table border=1>";
	echo "<tr><td class='sortablelistcell' style='font-weight:bold;'>All Clients</td>";
	foreach($statusCounts as $k=>$v) if($v) echo "<th class='sortablelistcell $k'>$k</th>";
	echo"</tr>";
	
	echo "<tr><td class='sortablelistcell'>Visits</td>";
	foreach($statusCounts as $k=>$v) if($v) echo "<td class='sortablelistcell $k'>".number_format($v)."</td>";
	echo"</tr>";
	
	echo "<tr><td class='sortablelistcell'>Revenue</td>";
	foreach($statusRevs as $k=>$v) if($v) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
	echo"</tr>";
	
	echo "<tr><td class='sortablelistcell'>Pay</td>";
	foreach($statusRates as $k=>$v) if($v) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
	echo"</tr>";
	
	foreach($clientCounts as $client=>$zzz) {
		echo "<tr><td>&nbsp;</td></tr>";
		echo "<tr><td style='font-weight:bold;'>$client</td>";
		foreach($statusCounts as $k=>$v) if($v) echo "<td class='sortablelistcell $k'>$k</td>";
		echo "</tr>";
		echo "<tr><td class='sortablelistcell'>Visits</td>";
		foreach($clientCounts[$client] as $k=>$v) if($statusCounts[$k]) echo "<td class='sortablelistcell $k'>".number_format($v)."</td>";
		echo"</tr>";
		echo "<tr><td class='sortablelistcell'>Nominal Hours</td>";
		foreach($clientNominalHours[$client] as $k=>$v) if($statusCounts[$k]) echo "<th class='sortablelistcell $k'>".number_format($v,3)."</td>";
		echo"</tr>";
		echo "<tr><td class='sortablelistcell'>Visit Time</td>";
		foreach($clientVisitTime[$client] as $k=>$v) if($statusCounts[$k]) echo "<td class='sortablelistcell $k'>".number_format($v,3)."</td>";
		echo"</tr>";
		echo "<tr><td>Revenue</td>";
		foreach($clientRevs[$client] as $k=>$v) if($statusCounts[$k]) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
		echo"</tr>";
		echo "<tr><td>Pay</td>";
		foreach($clientRates[$client] as $k=>$v) if($statusCounts[$k]) echo "<td class='dollaramountcell $k'>".dollarAmount($v)."</td>";
		echo"</tr>";
	}
		echo"</table>";
}

function visitsTable($start, $end, $rows) {
	global $allVisitTotals, $allVisitCounts, $allowanceTotals, $provs, $allRates, $allTravel, $allSurchargeTotals, $allGratuityTotals, $clientDetail;

	$columns = explodePairsLine('datetime|Date||client|Client||label|Service||status|Status||recurring|Schedule Type||formattedcharge|Charge||formattedadjustment|Adj.||provider|Sitter||formattedrate|Rate||formattedbonus|Bonus||hours|Hours');
	$numCols = count($columns);
	$colClasses = array('charge' => 'dollaramountcell', 'adjustment' => 'dollaramountcell', 'rate' => 'dollaramountcell', 'bonus' => 'dollaramountcell'); 
	//$headerClass = array('pay' => 'dollaramountheader', /*'pay' => 'dollaramountheader'*/);

	echo "<style>.topline {border-top:solid black 1px;}</style>";
	$prov = -1;
	/*foreach((array)$rows as $i => $row) {
//echo print_r($rows[$i], 1)."<br>";	
		foreach(explode(',', 'charge,adjustment,rate,bonus') as $fld)
			$rows[$i][$fld] = dollarAmount($rows[$i][$fld]);
		*if(!$clientDetail) $rows[$i]['client'] = $row['provider'];
		if($row['providerptr'] != $prov) {
			$provCount += 1;
			if($prov != -1) {
				$rowClass = 'futuretask';
				providerSummaryRows($prov, $finalrows, $rowClasses);

			}
			$prov = $row['providerptr'];
			//$rowClass = 'futuretaskEVEN';
			$providerLabel = $clientDetail ? "{$row['provider']} ($allVisitCounts[$prov] visits)" : '';
			$finalrows[] = array('#CUSTOM_ROW#'=>"<tr class='topline'><td colspan=$numCols class='fontSize1_1em'><b>{$providerLabel}</b></td></tr>");
			$rowClasses[] = $rowClass = $rowClass== 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';//PLACEHOLDER
			if($clientDetail) $rowClass = 'futuretask';
		}*
		$dispRow = array();
		foreach($columns as $c => $unused)
			$dispRow[$c] = $rows[$i][$c];
		$finalrows[] = $dispRow;
		$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	}*/
	//$finalrows[] = array('#CUSTOM_ROW#'=>"<tr class='topline'><td colspan=$numCols class='fontSize1_1em'><b>TOTALS</b></td></tr>");
	if($rows) {
		echo leashtime_num_rows($rows)." visits found.";
		echo " - ".fauxLink("Jump to Sitter Summaries", "document.location.href=\"#SITTERSUMMARIES\"", 1, 'Jump past the visit list to the sitter summaries.');
		global $clientSummaryTEST, $averageSitterPayTEST;
		if($clientSummaryTEST) echo " - ".fauxLink("Jump to Client Summaries", "document.location.href=\"#CLIENTSUMMARIES\"", 1, 'Jump past the visit list to the client summaries.');
		if($averageSitterPayTEST) echo " - ".fauxLink("Jump to Average Pay by Sitter/Service", "document.location.href=\"#SITTERAVERAGES\"", 1, 'Jump past the visit list to the average sitter pay table.');
		tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);
	}
	else echo "<p>No visits to report.";
}

function visitsCSV($start, $end, $rows) {
	global $includeaddressesinspreadsheet;
	$columns = 'datetime|Date||client|Client||label|Service||timeofday|Time of Day||status|Status||recurring|Schedule Type||charge|Charge||adjustment|Adj.||provider|Sitter||nickname|Nickname||rate|Rate||bonus|Bonus||hours|Hours||formattedhours|Num Hours||zip|Postal Code||arrived|Arrived||completed|Completed||visitlength|Visit Length||apptcreated|Created||apptmodified|Last Modified';
	if(TRUE) $columns .= '||statusTimeStamp|Final Status Stamp';
	if(TRUE) $columns .= '||surchargenote|Adj Note||cancellationreason|Cancel Note';
	if($includeaddressesinspreadsheet) {
		$columns .= '||street|Street||city|City||state|State';
		$addresses = array();
	}
	if(mattOnlyTEST()) $columns .= '||bcharge|Billable||tax|Tax||paid|Paid||paidOn|Paid On';
	
	if(TRUE) $columns .= '||clientptr|Client ID';
	if(TRUE) $columns .= '||defaultProviderName|Primary Sitter';
	$defaultProviders = array();
	$columns = explodePairsLine($columns);
	dumpCSVRow($columns);
	//foreach($rows as $row) {
	while($row = mysql_fetch_array($rows, MYSQL_ASSOC)) {
		if(!$defaultProviders[$row['clientptr']]) {
			$providerName = fetchRow0Col0(
				"SELECT CONCAT_WS(' ', p.fname, p.lname)
					FROM tblclient
					LEFT JOIN tblprovider p ON providerid = defaultproviderptr
					WHERE clientid = {$row['clientptr']}
						AND defaultproviderptr IS NOT NULL", 1);
			$defaultProviders[$row[$clientptr]] = $providerName ? $providerName : '-';
		}
		$row['defaultProviderName'] = $defaultProviders[$row[$clientptr]];
		addTrackedCompletionTime($row);
		$row['statusTimeStamp'] = 
			$row['completiontime'] ? $row['completiontime'] : (
			$row['cancellationtime'] ? $row['cancellationtime'] : '--');
			
//if(mattOnlyTEST()) print_r($row);		// && $row['canceled']

		if($includeaddressesinspreadsheet) {
			if(!$addresses[$row['clientptr']])
				$addresses[$row['clientptr']] = 
					fetchFirstAssoc(
						"SELECT CONCAT_WS(' ', street1, street2) as street, city, state, zip 
							FROM tblclient 
							WHERE clientid = {$row['clientptr']}", 1);
			foreach($addresses[$row['clientptr']] as $k => $v)
				$row[$k] = $v;		
		}
		$arrived = $row['arrived'] == '--' ? null : $row['arrived'];
		$row['arrived'] = !$arrived ? '--'  : date('h:i a', strtotime($row['arrived']));
		$completed = $row['completed'] == '--' ? null : $row['completed'];
		$row['completed'] = !$completed ? '--' : (
			date('d', strtotime($completed)) != date('d', strtotime($row['date'])) 
				? date('m/d/Y h:i a', strtotime($completed))
				: date('h:i a', strtotime($completed)));
		if($arrived && $completed) {
			//$d2 = new DateTime();
			//$d2->add(new DateInterval('PT'.(strtotime($completed)-strtotime($arrived)).'S'));
			//$row['visitlength'] = $d2->format('H:i');
			//$row['visitlength'] = date('H:i', strtotime($completed)-strtotime($arrived));
			$row['visitlength'] = gmdate('H:i', strtotime($completed)-strtotime($arrived));

		}
		else $row['visitlength'] = '--';
		
		if($row['billableid'])
			$row['paidOn'] = fetchRow0Col0(
				"SELECT issuedate
					FROM relbillablepayment
					LEFT JOIN tblcredit ON creditid = paymentptr
					WHERE billableptr = {$row['billableid']}
					ORDER BY issuedate DESC", 1);
					
//if(mattOnlyTEST()) echo print_r($row,1)."\n<br>\n"; else
		dumpCSVRow($row, array_keys($columns));
	}
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

if(!$csv && !$print) {
?>
<script language='javascript'>
</script>
<? }
