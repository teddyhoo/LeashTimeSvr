<?
// prov-schedule-cal-section.php
// for inclusion in the Master View Daily Appointments tab
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "day-calendar-fns.php";

// Determine access privs
//$locked = locked('o-');

$max_rows = 500;

extract($_REQUEST);


$appts = array();

$found = getProviderAppointmentCountAndQuery(dbDate($oneDay), dbDate($oneDay), 'date_ASC', $provider, $offset, $max_rows, "AND canceled IS NULL");
$numFound = 0+substr($found, 0, strpos($found, '|'));
$query = substr($found, strpos($found, '|')+1);
//echo $query;exit;
$appts = $numFound ? fetchAssociations($query) : array();
foreach($appts as $key => $appt) {
	if($appt['canceled']) $canceledCount++;
}

$searchResults = ($numFound ? $numFound : 'No')." appointment".($numFound == 1 ? '' : 's')." found.  ";
if($canceledCount) $searchResults .= $canceledCount.($canceledCount == 1 ? ' is' : ' are')." canceled.  ";

echo "$searchResults";
?>

<br>
<?
if($appts) providerCalendarTable($appts, 'suppressDateRows');