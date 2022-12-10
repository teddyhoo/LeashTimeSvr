<?
// daily-summary-sectionNEW.php
// for inclusion in the Master View Daily Appointments tab
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "appointment-fns.php";
require_once "preference-fns.php";

// Determine access privs
//$locked = locked('o-');

$max_rows = 500;

extract($_REQUEST);
$_SESSION['showCanceledOnHomePage'] = $showCanceled ? 1 : 0;
$canceledFilter = isset($showCanceled) && $showCanceled ? '' : "AND canceled IS NULL";

$dbOneDay = dbDate($oneDay);
$sql = "SELECT appointmentid, providerptr, canceled, charge+IFNULL(adjustment,0) as rev, completed, date, starttime, endtime
	FROM tblappointment 
	WHERE date = '$dbOneDay' $canceledFilter";
$appts = fetchAssociationsKeyedBy($sql, 'appointmentid');
if($appts) $discounts = fetchKeyValuePairs(
		"SELECT appointmentptr, amount 
			FROM relapptdiscount 
			WHERE appointmentptr IN (".join(',', array_keys($appts)).")");

foreach($appts as $appt) {
	$breakdown[$appt['providerptr']]['visits']++;
	$breakdown[$appt['providerptr']]['rev'] += $appt['rev'] - $discounts[$appt['appointmentid']];
	$visitRev += $appt['rev'] - $discounts[$appt['appointmentid']];
	if($appt['canceled']) {
		$canceled++;
		$canceledRev += $appt['rev'];
		$breakdown[$appt['providerptr']]['canceled']++;
	}
	else if($appt['completed']) $breakdown[$appt['providerptr']]['completed']++;
	else if(appointmentFuturity($appt) < 0) $breakdown[$appt['providerptr']]['unreported']++;
}
$surcharges = $_SESSION['surchargesenabled'] 
	? fetchKeyValuePairs("SELECT providerptr, charge FROM tblsurcharge WHERE date = '$dbOneDay'")
	: array();
	
echo ($totalRevenue = array_sum($surcharges)+$visitRev).'|'.count($appts).'|';

$dollarCol = "class='dollaramountcell'";
require_once "preference-fns.php";

?>
<table style='font-size:1.1em;width:100%;'><tr> <? // summary ?>
<? 
$omitRevenue = getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');
if($omitRevenue) 
	echo "<tr><td>Visits: ".count($appts)."</td><td>Canceled Visits ($canceled)</td>";
else { ?>
<td>
<?= "<table border=0><tr><td valign=top>Day's Revenue ".($showCanceled ? 'including' : 'excluding')." canceled visits:</td>"
		 ."<td $dollarCol  valign=top>".dollarAmount($totalRevenue)."</td></tr>
		 <tr><td>"
		 .fauxLink('Sitter Login Report', "document.location.href=\"reports-logins.php\"", 1)
		 ."</td></tr></table>";
?>
</td>

<td valign=top>
	<table border=0>
<?
echo "<tr><td>Visits (discounted) (".count($appts)."):</td><td $dollarCol>".dollarAmount($visitRev)."</td></tr>";
if($showCanceled) {
	$canceledRev = $canceledRev ? "(".dollarAmount($canceledRev).")" : dollarAmount($canceledRev, '0');
	echo "<tr><td>Canceled Visits ($canceled):</td><td $dollarCol>$canceledRev</td></tr>";
}
echo "<tr><td>Surcharges:</td><td $dollarCol>".dollarAmount(array_sum($surcharges))."</td></tr>";
echo "<tr><td>Discounts:</td><td $dollarCol>".dollarAmount(array_sum((array)$discounts))."</td></tr>";
?>
	</td>
	</tr>
	</table>
</td>
<? } // !suppressRevenueDisplay ?>
</tr>

<? if(mattOnlyTEST()) {
			echo "\n<tr><td colspan=2 style='text-align:center;'>";
			fauxLink('Expand All', 'toggleAllProviderSummarySections(this)');
			echo "</td></tr>\n";
	 }
?>
<tr><td colspan=2><hr></td></tr>

<tr>
<? // show all sitters in one column
$statusTest = dbTEST('valleypetsitting') ? '1=1' : "active=1";
$names = getProviderShortNames("WHERE $statusTest");
function cistrcmp($a,$b) {return strcmp(strtoupper($a),strtoupper($b));}
uasort($names, 'cistrcmp');
$allNames = array(0=> "<font color=red>Unassigned</font>");
foreach($names as $p=>$n) $allNames[$p] = $n;
foreach($allNames as $prov => $name) {
	//if(isProviderOffThisDay($prov, $dbOneDay)) 
	//	$name .= " <span style='text-align:center;color:red;font-weight:bold;'>SITTER TIME OFF</span>";
	if($times = timesOffThisDay($prov, $dbOneDay)) {
		$times = array_unique($times);
		if(in_array('', $times)) $times = 'all day';
		else $times = join(', ', $times);
		$name .= " <span style='text-align:center;color:red;font-weight:bold;'>SITTER TIME OFF: $times</span>";
		$breakdown[$prov]['timeoff'] = "SITTER TIME OFF: $times";
	}

	else if(!$breakdown[$prov]) {
//if(!$upgradeTEST) continue;
	}
	$showStyle = $breakdown[$prov] ? "style='display:{$_SESSION['tableRowDisplayMode']}'" : "style='display:none'";
	$showStyle = 
		($breakdown[$prov] || $surcharges[$prov] || getUserPreference($_SESSION['auth_user_id'], 'showZeroVisitCountSitters')) 
			? "style='display:{$_SESSION['tableRowDisplayMode']}'"
			: "style='display:none'";
	
	$plural = $breakdown[$prov]['visits'] == 1 ? '' : 's';
	$counts = $breakdown[$prov];
	$visits = "<span id='visitcounts_$prov'>"
			.visitCountDisplay($counts['visits'], $counts['completed'], $counts['unreported'], $omitRevenue)
			."</span>";
	if($prov && staffOnlyTEST()) {
		$visits .= " ".fauxLink('&#9776;', "apptTimeChart($prov, \"$dbOneDay\")", 1, 'View a chart (Staff Only for now)');
		echo "";
	}
	/*$visits = ($upgradeTEST || $breakdown[$prov]['visits']) 
		?  "<span id='visitcounts_$prov'>"
			.visitCountDisplay($counts['visits'], $counts['completed'], $counts['unreported'], $omitRevenue)
			."</span>"
		: '';*/
		
/*
$breakdown[$prov]['visits']." visit$plural)".($omitRevenue ? '' : ':') 
		: '';
if(staffOnlyTEST() || dbTEST('themonsterminders')) {
	if($breakdown[$prov]['completed']) $visits .= " <span class='completedtask'>Completed: {$breakdown[$prov]['completed']}</span>";
	if($breakdown[$prov]['unreported'])$visits .= " <span class='noncompletedtask'>Unreported: {$breakdown[$prov]['unreported']}</span>";
}
	if($visits) $visits .= 
*/
	
	$link = fauxLink($name, "toggleProviderSection($prov, \"$dbOneDay\")", 1);
	$dollarAmount = $omitRevenue ? '' : dollarAmount($breakdown[$prov]['rev']);
	$rows[] = "<tr class='provrow_$prov' $showStyle><td>$link $visits</td><td id='provrev_$prov' $dollarCol>$dollarAmount</td></tr>";
	ob_start();
	ob_implicit_flush(0);
	if(!$prov) providerCalendarSection('<font color=red>Schedule</font>', '0', $counts['visits']);
	else providerCalendarSection("Schedule", $prov, $counts['visits']);
	$providerDiv = ob_get_contents();
	ob_end_clean();
//if(mattOnlyTEST()) if($prov==157) print_r(htmlentities($providerDiv)."<hr>[".$showStyle."]");	
	$rows[] = "<tr class='provrow_$prov' $showStyle><td colspan=2>$providerDiv</td></tr>";
//if(mattOnlyTEST() && !$breakdown[$prov]) {echo htmlentities(print_r($rows, 1));exit;}
}
echo "<td colspan=2><table>";
foreach((array)$rows as $row) echo $row;
echo "</table></td>";
?>
</tr>
</table>

<? 
function providerCalendarSection($provName, $prov, $numVisits) {
	$numVisits = $numVisits ? $numVisits : '0';
	$starting = date('Y-m-d');
	$schedProv = $prov ? $prov : -1;
	echo "<div class='providersectiondiv' visits= '$numVisits' prov='$prov' id='providersectiondiv_$prov' style='padding:0px;display:none;width:100%;'>\n";
	echo "<span style='font-weight:bold;font-size:1.0em;'><a href='prov-schedule-list.php?provider=$schedProv&starting=$starting'>$provName</a></span>:\n";
	echoButton('', 'Reassign Visits', "reassignJobs($prov)");
	$includeMap = staffOnlyTEST() || dbTEST('doggiewalkerdotcom'); 
	if($prov) {
		echo " ";
		echoButton('', 'Print Visit Sheets', "printVisitSheets($prov)");

		if($_SESSION['preferences']['mobileSitterAppEnabled']) { echo " "; echoButton('', 'Map', "mapVisits($prov)"); }
		echo " ";
		echoButton('', 'Set Up Route', "setUpRoute($prov)");
		echo " <div id='revenue_prov_section_$prov' style='display:inline'></div>";
		
	}
	else {
		if($includeMap) echo " "; echoButton('', 'Map', "mapVisits(\"0\")"); 
		echo "<div id='revenue_prov_section_$prov' style='display:inline;'></div><img src='art/spacer.gif' width=20 height=1><br>";
	}
	echo "\n<p>\n\n";
	echo "<div id='prov_section_$prov' style='padding-top:0px;width:100%;'></div>\n<p>\n<div style='width:99%;background-color: #ccdcff;margin-bottom: 5px;'><img src='art/spacer.gif' width=1 height=10></div>\n";
	echo "</div>\n";
	echo "</div>\n";
}


?>
