<?
// prov-schedule-list-section.php
// for inclusion in the Master View Daily Appointments tab
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";

// Determine access privs
//$locked = locked('o-');

$max_rows = 500;

extract($_REQUEST);
$_SESSION['showCanceledOnHomePage'] = $showCanceled ? 1 : 0;
$canceledFilter = isset($showCanceled) && $showCanceled ? '' : "AND canceled IS NULL";
$_SESSION['showSurchargesOnHomePage'] = $showSurcharges ? 1 : 0;
$appts = array();

$dbOneDay = dbDate($oneDay);
$found = getProviderAppointmentCountAndQuery($dbOneDay, $dbOneDay, 'date_ASC', ($provider ? $provider : -1), $offset, $max_rows, $canceledFilter);
$numFound = 0+substr($found, 0, strpos($found, '|'));
$query = substr($found, strpos($found, '|')+1);
//if(mattOnlyTEST()) echo $query;exit;
$appts = $numFound ? fetchAssociations($query) : array();

$originalServiceProviders = originalServiceProviders($appts);
$allPetClients = array();

foreach($appts as $key => $appt) {
	if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
		if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
			$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];
	if($appt['canceled']) $canceledCount++;
	
	if($_REQUEST['xml']) {
		require_once 'appointment-fns.php';
		$totalVisits++;
		if(!$appt['canceled']) $provrev += $appt['charge']+$appt['adjustment'];
		if($appt['completed']) $completed++;
		else if($appt['canceled']) $canceled++;
		else if(appointmentFuturity($appt) < 0) $unreported++;
	}	
	
	
}

$surcharges = $_SESSION['surchargesenabled'] && $_SESSION['showSurchargesOnHomePage']
	? fetchAssociations("SELECT * FROM tblsurcharge WHERE date = '$dbOneDay' AND providerptr = $provider $canceledFilter")
	: array();

$rows = array_merge($appts, $surcharges);

if($rows) {
	foreach($rows as $row) $clients[] = $row['clientptr'];
	$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', lname, fname) FROM tblclient WHERE clientid IN (".join(',', $clients).")");
	usort($rows, 'dateSort');
}

$searchResults = ($numFound ? $numFound : 'No')." visit".($numFound == 1 ? '' : 's')." found.  ";
if($canceledCount) $searchResults .= $canceledCount.($canceledCount == 1 ? ' is' : ' are')." canceled.  ";

if($_REQUEST['xml']) {
	$provrev = dollarAmount($provrev, $cents=true, $nullRepresentation='');
	echo "<resultxml><provrev><![CDATA[$provrev]]></provrev><visitcounts><![CDATA["
				.visitCountDisplay($totalVisits, $completed, $unreported, $omitRevenue=true)
				."]]></visitcounts><visits><![CDATA[";
}
$sectionHeader[] = getUpcomingTimeOffLabels($provider);
$sectionHeader[] = $searchResults;
echo join('<p>', $sectionHeader);
?>

<br>
<?
$provideractive = fetchRow0Col0("SELECT active FROM tblprovider WHERE providerid = $provider");

if($provideractive && ($times = timesOffThisDay($provider, $oneDay))) {
	$times = array_unique($times);
	if(in_array('', $times)) $times = 'all day';
	else $times = join(', ', $times);
	$onclickedit = "openConsoleWindow(\"timeoffcalendar\", \"timeoff-sitter-calendar.php?&editable=1&provid=$provider&month=$oneDay\",850,700)";
	$times .= " <img src='art/clock20.gif' width=10 height=10 title='Edit Time Off' onclick='$onclickedit'>";

	echo "<p style='text-align:center;color:red;font-weight:bold;'>SITTER TIME OFF: $times</p>";
}


$props = getUserPreferences($_SESSION['auth_user_id']);
$wagPrimaryNameMode = $props['provsched_client'];
if($props['provsched_start'] == 'starttime') $timeColumn = '||starttime|Start';
else $timeColumn = '||time|Time';
$phoneColumn = !$props['provsched_hidephone'] ? '||phone|Phone' : '';
$addressColumn = !$props['provsched_hideaddress'] ? '||address|Address' : '';
$maxServiceNameLength = 12;
$columnDataLine = "client|Client$phoneColumn$addressColumn||service|Service$timeColumn||charge| ||buttons| ";

if($appts || $surcharges || !$provider) { // show table even when surcharge only
	$enableDragging = FALSE && mattOnlyTEST();
	versaProviderScheduleTable($provider, $rows, array('date'), 'noSort', $updateList, 0, 0, 0, $columnDataLine);
	/* providerScheduleTable($rows, array('date'), 'noSort', $updateList);*/
}

if($_REQUEST['xml']) echo "]]></visits></resultxml>";


function dateSort($a, $b) {
	global $clients;
	$result = strcmp($a['starttime'], $b['starttime']);
	if(!$result) {
		$result = strcmp($clients[$a['clientptr']], $clients[$b['clientptr']]);
	}
	if(!$result) {
		$a = isset($a['appointmentid']) ? '1' : 2;
		$b = isset($b['appointmentid']) ? '1' : 2;
		$result = strcmp($a, $b);
	}
	return $result;
}
