<?
//incomplete-appts-section.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "surcharge-fns.php";

// Determine access privs
$locked = locked('o-');


extract($_REQUEST);

$maxRows = 1000;

function clientLink($client) {
	global $clients;
	//return "<a title='View this client.' href='client-view.php?id=$client'>{$clients[$client]['clientname']}</a>";
	return fauxLink($clients[$client]['clientname'], 
	         "openConsoleWindow(\"viewclient\", \"client-view.php?id=$client\",700,500)",1, 'View this client');
}

function serviceLink($appt, $updateList) {
	if($appt['surchargecode']) return surchargeLink($appt, $updateList);
	$petsTitle = $appt['pets'] 
	  ? htmlentities("Pets: {$appt['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	$targetPage = true || appointmentFuturity($appt) == -1 ? 'appointment-view.php' : 'appointment-edit.php';
	return fauxLink($_SESSION['servicenames'][$appt['servicecode']],
	       "openConsoleWindow(\"editappt\", \"$targetPage?updateList=$updateList&id={$appt['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})",
	       1,
	       $petsTitle);
}

function surchargeLink($surcharge, $updateList) {
	static $surchargeNames;
	if(!$surchargeNames) $surchargeNames = getSurchargeTypesById();
	$targetPage = 'surcharge-edit.php';
	return fauxLink('Surcharge: '.$surchargeNames[$surcharge['surchargecode']],
	       "openConsoleWindow(\"editappt\", \"$targetPage?updateList=$updateList&id={$surcharge['surchargeid']}\",530,450)",
	       1);
}

$multiSortMethod = $sort ? explode('_', $sort) : null;

function multiKeySort($a, $b) {
	global $multiSortMethod, $sort;
	//echo "SORT: [$sort] multiSortMethod: [".print_r($multiSortMethod,1).']';	echo "<p>a: [".print_r($a,1)."] <p>b: [".print_r($b,1).']';exit;

	if($multiSortMethod[0] == 'provider') $x = strcmp($a['providername'], $b['providername']);
	else if($multiSortMethod[0] == 'client') $x = strcmp($a['clientsortname'], $b['clientsortname']);
	else if($multiSortMethod[0] == 'date') $x = strcmp($a['start'], $b['start']);
	else if($multiSortMethod[0] == 'timeofday') $x = strcmp($a['starttime'], $b['starttime']);
	else if($multiSortMethod[0] == 'service') $x = strcmp($a['label'], $b['label']);
	else $x = strcmp($a['start'], $b['start']);
	if(count($multiSortMethod) == 2 && strtoupper($multiSortMethod[1]) == 'DESC')
		$x = 0 - $x;
	return $x;
}

/*if(isset($client) && $client) $appts = findIncompleteJobs($starting, $ending, null, $sort, $client, $maxRows);
else $appts = findIncompleteJobs($starting, $ending, null, $sort, null, $maxRows);
if(!$appts) {
	echo "<p>No Incomplete Visits found in specified date range.";
	exit;
}
*/

$result = null;
$resultCount = 0;
$clients = array();
$clientIds = array();
$providers = getProviderShortNames();
$rows = array();
for($i=1; $i<=2; $i++) {
	$newClientIds = array();
	$newResultCount = 0;
	$client = isset($client) && $client ? $client : null;
//echo "findIncompleteJobsResultSet($starting, $ending, null, $sort, $client, $maxRows)";
	if($i == 1)
		$result = findIncompleteJobsResultSet($starting, $ending, null, $sort, $client, $maxRows, $futurealso);
	else 
		$result = findIncompleteSurchargesResultSet($starting, $ending, null, $sort, $client, $maxRows, $futurealso);
//if($i != 1)	{echo "findIncompleteSurchargesResultSet($starting, $ending, null, $sort, $client, $maxRows)";exit;}
	if($result) 
		while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$resultCount++;
			$newResultCount++;
			$newClientIds[] = $row['clientptr'];
		}
	if($newResultCount) mysql_data_seek($result, 0);

	//$appts = array();
	//foreach($appts as $appt) $clientIds[] = $appt['clientptr'];
	//print_r($clientIds);
	$newClientIds = array_unique($newClientIds);
	if($newClientIds) {
		$newClientIds = array_diff($newClientIds, $clientIds);
		foreach(getClientDetails($newClientIds, array('phone')) as $newClientId => $c)
			$clients[$newClientId] = $c;
		$clientIds = $newClientIds;
	}
	$columns = explodePairsLine('cb|&nbsp;||client|Client||phone|Phone||date|Date||timeofday|Time of Day||service|Service||provider|Sitter');
	$sortableColsString = 'client|date|timeofday|service|provider';
	foreach(explode('|', $sortableColsString) as $col) $sortableCols[$col] = null;

	while($result && $appt = mysql_fetch_array($result, MYSQL_ASSOC)) {
		// return -1 if appointment is completely past, 0 if now is in appointment's timeframe, or 1 if appointment timeframe is totally in the future
		$id = $appt['appointmentid'] ? 'appt_'.$appt['appointmentid'] : 'sur_'.$appt['surchargeid'];
		$row = $appt;
		$row['start'] = strtotime("{$appt['date']} {$appt['starttime']}");
		$row['cb'] = "<input type='checkbox' id='$id' name='$id'>";
		$row['client'] = clientLink($appt['clientptr']);
		$row['phone'] = $clients[$appt['clientptr']]['phone'];
		$row['date'] = shortDate(strtotime($appt['date']));;
		$row['dbdate'] = $appt['date'];;
		$row['timeofday'] = $appt['timeofday'];;
		$row['service'] = serviceLink($appt, $updateList);
		$row['provider'] = $providers[$appt['providerptr']];
		if(!$row['provider']) $row['provider'] = '<i>Unassigned</i>';
		$rows[] = $row;
	}
}
usort($rows, 'multiKeySort');
if($now == null) $now = getLocalTime();//time();
foreach($rows as $i => $row) {
	$future = $row['start'] > $now;
	$rowClasses[] = $future ? 'futuretask' : 'noncompletedtask';
}

?>
<style>
.topRow {background:white;}
</style>
<?
echo "<hr>";
echoButton('','Mark Selected Visits Complete', 'markVisitsComplete()');
echo " ";
echoButton('','Send Reminders to Selected Sitters', 'sendReminders()');
echo " ";
fauxLink('Select All', 'selectAllIncomplete(1)');
echo " - ";
fauxLink('Deselect All', 'selectAllIncomplete(0)');
if($resultCount == $maxRows) echo " First $maxRows visits shown.";
echo "<p>";

tableFrom($columns, $rows, 'width=100%', 'noncompletedtask', null, 'topRow', null, $sortableCols, $rowClasses, null, 'sortIncompleteJobs'); //'noncompletedtask'
?>
