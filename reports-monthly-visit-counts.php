<? // reports-monthly-visit-counts.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "projections.php";
require_once "service-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,csv', $_REQUEST));

		
$pageTitle = "Monthly Schedule Visits by Service Type";
require_once "frame-bannerless.php";
echo "<h2>$pageTitle</h2>";
echo "From ".shortDate(strtotime($start))." to ".shortDate(strtotime($end))."<p>";

$start = date('Y-m-d', strtotime($start));
$end = date('Y-m-d', strtotime($end));

$appts = fetchAssociations("SELECT label, servicecode, date, packageptr
FROM tblappointment
LEFT JOIN tblservicetype ON servicetypeid = servicecode
WHERE recurringpackage = 1 AND date >= '$start' AND date <= '$end'
ORDER BY date", 1);
foreach($appts as $appt) $monthlyptrs[$appt['packageptr']] = 1;

foreach($monthlyptrs as $pptr => $n)
	if(!fetchRow0Col0("SELECT monthly FROM tblrecurringpackage WHERE packageid = $pptr LIMIT 1", 1))
		unset($monthlyptrs[$pptr]);

$services = array();
foreach($appts as $appt) {
	if($monthlyptrs[$appt['packageptr']]) {
		$services[$appt['label']] += 1;
		$monthYear = date('M Y', strtotime($appt['date']));
		$months[$monthYear] = 1;
		$byMonth[$appt['label']][$monthYear] += 1;
	}
}

ksort($services);

$columns = explodePairsLine('service|Service||count|Visits');
foreach($months as $month => $n) $columns[$month] = $month;
foreach($services as $service => $count) {
	$row = array('service'=>$service, 'count'=>$count);
	foreach($months as $month => $n) {
		$mcount = $byMonth[$service][$month];
		$row[$month] = $mcount ? $mcount : '';
	}
	$rows[] = $row;
}
//foreach($services as $label => $count)
//	echo "$label: $count visits<br>";
	
tableFrom($columns, $rows, $attributes="WIDTH=98% BORDER=1 BORDERCOLOR=gray BGCOLOR=white");