<?  // recent-visit-notes.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "gui-fns.php";

// Verify login information here
locked('o');

$show = $_REQUEST['show'];
$filter = !$show ? '1=1' : ($show ==  1 ? "completed IS NOT NULL" : "canceled IS NOT NULL");
$order = !$show ? 'date DESC' : ($show == 1 ? 'completed DESC' : 'canceled DESC');
$sql = "SELECT *
	FROM `tblappointment`
	WHERE $filter
	AND note IS NOT NULL
	AND note NOT LIKE '%Marked complete by manager%'
	ORDER BY $order
	LIMIT 0 , 1000";

$appts = fetchAssociations($sql);
$pageTitle = 'Most Recent visits with Notes';
require_once "frame.html";
if(!$appts) {
	echo "No Appointments found.";
	exit;
}
$options = array('All'=>'', 'Completed Visits Only'=>1, 'Canceled Visits Only'=>2);
radioButtonRow('Show:', 'show', $value=$show, $options, 'document.location.href="recent-visit-notes.php?show="+this.value');
echo '<p>';
$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");
$providerNames = fetchKeyValuePairs("SELECT providerid, ifnull(nickname, CONCAT_WS(' ', fname, lname)) FROM tblprovider");

$columns = explodePairsLine('completed|Completed||visittime|Visit||client|Client||provider|Provider||service|Service');
$numCols = count($columns);
$rows = array();
foreach($appts as $i => $appt) {
	$appt['completed'] = $appt['canceled'] ? ruddy(shortDateAndTime(strtotime($appt['canceled']), 'mil')) : shortDateAndTime(strtotime($appt['completed']), 'mil');
	$appt['visittime'] = shortDateAndTime(strtotime("{$appt['date']} {$appt['starttime']}"), 'mil');
	$appt['provider'] = $providerNames[$appt['providerptr']];
	$appt['client'] = $clientNames[$appt['clientptr']];
	$appt['service'] = serviceLink($appt);
	$rows[] = $appt;
	$rowClasses[] = $rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	$note = truncatedLabel($appt['note'], 120);
	$rows[] = array('#CUSTOM_ROW#'=> "\n<tr class='$rowClass'><td colspan=$numCols style='font-style:italic;font-size:1.1em;color:green;'>$note</td></tr>");
	$rowClasses[] = $rowClass;
}

tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, null, $rowClasses);

// =====================================================
function serviceLink($appt) {
	$petsTitle = $appt['pets'] 
	  ? htmlentities("Pets: {$appt['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	$targetPage = true || appointmentFuturity($appt) == -1 ? 'appointment-view.php' : 'appointment-edit.php';
	return fauxLink($_SESSION['servicenames'][$appt['servicecode']],
	       "openConsoleWindow(\"editappt\", \"$targetPage?id={$appt['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})",
	       1,
	       $petsTitle).($appt['canceled'] ? " ".ruddy('canceled', 'font-size:.9em;') : '');
}

function ruddy($str, $extraStyle='') {
	return "<font style='color:red;$extraStyle'>$str</font>";
}

?>
<script language='javascript' src='common.js'></script>
<?
require_once "frame-end.html";
