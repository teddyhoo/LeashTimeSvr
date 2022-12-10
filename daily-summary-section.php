<?
// daily-summary-section.php
// for inclusion in the Master View Daily Appointments tab
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";

// Determine access privs
//$locked = locked('o-');

$max_rows = 500;

extract($_REQUEST);
$_SESSION['showCanceledOnHomePage'] = $showCanceled ? 1 : 0;
$canceledFilter = isset($showCanceled) && $showCanceled ? '' : "AND canceled IS NULL";

$dbOneDay = dbDate($oneDay);
$sql = "SELECT appointmentid, providerptr, canceled, charge+IFNULL(adjustment,0) as rev 
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
}
$surcharges = $_SESSION['surchargesenabled'] 
	? fetchCol0("SELECT charge FROM tblsurcharge WHERE date = '$dbOneDay'")
	: array();
	
echo ($totalRevenue = array_sum($surcharges)+$visitRev).'|'.count($appts).'|';

$dollarCol = "class='dollaramountcell'";
require_once "preference-fns.php";

?>
<table style='font-size:1.1em'><tr> <? // summary ?>
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

<tr><td colspan=2><hr></td></tr>

<tr>
<? // show all sitters in two columns
$names = getProviderShortNames();
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
	}

	else if(!$breakdown[$prov]) continue;
	$linkProv = $prov ? $prov : -1;
	$plural = $breakdown[$prov]['visits'] == 1 ? '' : 's';;
	$visits = $breakdown[$prov]['visits'] ? "(".$breakdown[$prov]['visits']." visit$plural)".($omitRevenue ? '' : ':') : '';
	$link = fauxLink($name, "document.location.href=\"prov-schedule-list.php?provider=$linkProv&starting=$dbOneDay\"", 1);
	$dollarAmount = $omitRevenue ? '' : dollarAmount($breakdown[$prov]['rev']);
	$rows[] = "<tr><td>$link $visits</td><td $dollarCol>$dollarAmount</td></tr>";
}
$chunks = count($rows) < 10 ? array($rows) : array_chunk($rows, (int)(count($rows) / 2 + count($rows) % 2));
foreach($chunks as $rows)
	if($rows) echo "<td><table>".join("\n", $rows)."</table></td>";
?>
</tr>
</table>

<? /*

echo "<table><tr><td>Day's Revenue ".($showCanceled ? 'including' : 'excluding')." canceled visits:</td>"
		 ."<td $dollarCol>".dollarAmount($totalRevenue)."</td></tr>";
echo "<tr><td>Visits (discounted) (".count($appts)."):</td><td $dollarCol>".dollarAmount($visitRev)."</td></tr>";
if($showCanceled) {
	$canceledRev = $canceledRev ? "(".dollarAmount($canceledRev).")" : dollarAmount($canceledRev, '0');
	echo "<tr><td>Canceled Visits (".count($canceled)."):</td><td $dollarCol>$canceledRev</td></tr>";
}
echo "<tr><td>Surcharges:</td><td $dollarCol>".dollarAmount(array_sum($surcharges))."</td></tr>";
echo "<tr><td>Discounts:</td><td $dollarCol>".dollarAmount(array_sum((array)$discounts))."</td></tr>";
echo "<tr><td colspan=2><hr></td></tr>";
$names = getProviderShortNames();
function cistrcmp($a,$b) {return strcmp(strtoupper($a),strtoupper($b));}
uasort($names, 'cistrcmp');
$allNames = array(0=> "<font color=red>Unassigned</font>");
foreach($names as $p=>$n) $allNames[$p] = $n;
foreach($allNames as $prov => $name) {
	if(isProviderOffThisDay($prov, $dbOneDay)) 
		$name .= " <span style='text-align:center;color:red;font-weight:bold;'>SITTER TIME OFF</span>";
	else if(!$breakdown[$prov]) continue;
	$linkProv = $prov ? $prov : -1;
	$visits = $breakdown[$prov]['visits'] ? "(".$breakdown[$prov]['visits']." visits):" : '';
	$link = fauxLink($name, "document.location.href=\"prov-schedule-list.php?provider=$linkProv&starting=$dbOneDay\"", 1);
	echo "<tr><td>$link $visits</td><td $dollarCol>".dollarAmount($breakdown[$prov]['rev'])."</td></tr>";
}
echo "</table>";
*/
?>
