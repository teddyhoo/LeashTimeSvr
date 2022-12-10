<? // staff-visit-details.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "pet-fns.php";
require_once "contact-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";

locked('o-');
if(!staffOnlyTEST()) { echo "LeashTime Staff Only"; exit; }
extract($_GET);

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require_once "common/init_db_common.php";
$names = fetchKeyValuePairs(
	"SELECT userid, CONCAT_WS(' ', fname, lname) 
		FROM tbluser 
		WHERE bizptr = {$_SESSION['bizptr']} 
			AND (rights LIKE 'o-%' OR rights LIKE 'd-%' OR rights LIKE 'p-%')", 1);
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
foreach($names as $userid => $name)
	if(!$name) $names[$userid] = fetchRow0Col0(
		"SELECT ifnull(nickname, CONCAT_WS(' ', fname, lname)) 
		FROM tblprovider
		WHERE userid = $userid
		LIMIT 1", 1);

$client = getOneClientsDetails($id);
$appts = fetchAssociationsKeyedBy(
"SELECT tblappointment.*, label as service, CONCAT_WS(' ', fname, lname) as sitter
	FROM tblappointment
		LEFT JOIN tblservicetype on servicetypeid = servicecode
		LEFT JOIN tblprovider on providerid = providerptr
	WHERE clientptr = $id "
.($starting ? "AND date >= '$starting'" : '')
.($ending ? "AND date <= '$ending'" : '')
." ORDER BY date, starttime", 'appointmentid', 1);

if($appts) $history = fetchAssociationsGroupedBy(
$sql = "SELECT itemptr, time, note, operation, user FROM tblchangelog 
	WHERE itemtable = 'tblappointment' AND itemptr IN (".join(',', array_keys($appts)).")
	ORDER BY `time`", 'itemptr', 1);

//print_r($sql);
//$changeCols = explode(',', 'time,itemtable,note,operation,user,itemptr');

$columns = explodePairsLine('pdate|Date||timeofday|Time Window||sitter|Sitter||service|Service||charge|Charge||pet|Pets');
foreach($appts as $apptid => $appt) {
	$appt['pdate'] = shortDateAndDay(strtotime($appt['date']));
	$rows[] = $appt;
	$rowClasses[] = ($rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask') ;
	
	$changes = array();
	$newlog = array(array('itemptr'=>$apptid, 'time'=>$appts[$apptid]['created'], 
									'note'=>'Created', 'operation'=>'c', 'user'=>$appts[$apptid]['createdby']));

	foreach((array)$history[$apptid] as $line) $newlog[] = $line;

	foreach($newlog as $change) {
		$change['user'] = $names[$change['user']];
		unset($change['itemtable']);
		$changes[] = '<tr><td>'.join('<td>', $change).'</td></tr>';
	}
	$rows[] = array('#CUSTOM_ROW#'=>"<tr class='$rowClass'><td colspan=6><table width=100% border=1 bordercolor=black>".join("\n", $changes)."</table>");
	$rowClasses[] = null;
}

$windowTitle = "Visit Change History for {$client['clientname']}";
$extraBodyStyle = 'padding:10px;';
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";
echo "From $starting to $ending<br>";
tableFrom($columns, $rows, $attributes='width=100%', $class=null, $headerClass='sortableListHeader', 
						$headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null);
