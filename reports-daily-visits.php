<? // reports-daily-visits.php
/*
$included - if(true) then this page was included (by reports-multi-day-visits.php)
$newPage - if(true -- when included) then start a new page when printing
$showstarts, $showfinishes if(true -- when included) show only STARTS and/or FINISHES
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";

$locked = locked('o-');
//http://leashtime.com/reports-daily-visits.php?date=2011-05-01&nocanceled=0&includeaddress=1&includepets=1

if(!$included) extract(extractVars('date,nocanceled,includeaddress,includepets,sortbytime,showstarts,showfinishes', $_REQUEST));
if(!$_POST) {
	if(!isset($_REQUEST['nocanceled'])) $nocanceled = 1;
	if(!isset($_REQUEST['includeaddress'])) $includeaddress = 0;
	if(!isset($_REQUEST['includepets'])) $includepets = 1;
}

$date = date('Y-m-d', strtotime($date));

$providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider");

$nocanceled = $nocanceled ? "AND canceled IS NULL" : '';

$startFinish = array();
if($showstarts) $startFinish[] = "note LIKE '%[START]%'";
if($showfinishes) $startFinish[] = "note LIKE '%[FINISH]%'";
$startFinish = $startFinish ? "AND (".join(' OR ', $startFinish).")" : '';

$orderCriteria = $sortbytime ? "starttime, clientsort, provsort" : "provsort, starttime, clientsort";

$appts = fetchAssociations($sql = "SELECT appointmentid, CONCAT_WS(' ', c.fname, c.lname) as client,
				CONCAT_WS(', ', c.lname, c.fname) as clientsort,  
				IFNULL(nickname, CONCAT_WS(' ', p.fname, p.lname)) as provider, 
				IFNULL(nickname, CONCAT_WS(', ', p.lname, p.fname)) as provsort, 
				timeofday, servicecode, providerptr, clientptr, pets, starttime, note, serviceptr, canceled, date
				FROM tblappointment
				LEFT JOIN tblclient c ON clientid = clientptr
				LEFT JOIN tblprovider p ON providerid = providerptr
				WHERE date = '$date' $nocanceled $startFinish
				ORDER BY $orderCriteria", 1);
				
//foreach($appts as $appt) $oi[$appt['date']][] = "{$appt['starttime']} {$appt['client']} {$appt['provider']} ";
//print_r($oi);
	
	
	
$originalServiceProviders = originalServiceProviders($appts);
//echo "::: ".print_r($appts, 1);
foreach($appts as $appt) $clientids[$appt['clientptr']] = 1;
$clientids = array_keys((array)$clientids);
$clients = getClientDetails($clientids, array('address', 'zip', 'phone', 'email', 'nokeyrequired', 'activepets'));
$columnDataLine = 'client|Client||timeofday|Time Window||provider|Sitter||pets|Pets||service|Service||phone|Phone||address|Address';
$columns = explodePairsLine($columnDataLine);
if(!$sortbytime) unset($columns['provider']);
if(!$includeaddress) unset($columns['address']);
if(!$includepets) unset($columns['pets']);
$numCols = count($columns);
$lastProvider = -1;
$rows = array();
$rowClasses = array();
foreach($appts as $key => $appt) {
	if(!($appt['origprovider'] = appointmentUnassignedFrom($appt)))
		if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
			$appt['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];

	if(!$sortbytime && $appt['providerptr'] != $lastProvider) {
		$pLabel = !trim($appt['provider']) ? 'Unassigned Visits' : "{$appt['provider']}'s Visits";
		$lastProvider = $appt['providerptr'];
		if($lastProvider) {
			$timeOff = getProviderTimeOff($lastProvider);
			if(isProviderOffThisDayWithRows($lastProvider, $date, $timeOff))
				$pLabel .= " <font color='red'>{$appt['provider']} is off today.</font>";
			
			$keyClients =  array();
			foreach(getProviderKeys($lastProvider) as $key) $keyClients[] = $key['clientptr'];
		}
		//echo "($pLabel)";
		$rows[] = array('#CUSTOM_ROW#' =>"<tr><td colspan=$numCols><hr></td></tr>");
		$rowClasses[] = '';
		$rows[] = array('#CUSTOM_ROW#' =>"<tr><td style='font-size:1.2em;font-weight:bold' colspan=$numCols>$pLabel</td></tr>");
		$rowClasses[] = '';
	}
	
	else if($sortbytime) { // must redo this logic when there is time
		if(!$keyRings[$appt['providerptr']]) 
			foreach(getProviderKeys($appt['providerptr']) as $key) $keyRings[$appt['providerptr']][] = $key['clientptr'];
		$keyClients = $keyRings[$appt['providerptr']] ? $keyRings[$appt['providerptr']] : array();
	}
	
	
	
	
	$clientptr = $appt['clientptr'];
	$client = $clients[$clientptr];
	if($includepets) {
		if($appt['pets'] == 'All Pets' && $client['pets']) 
			$appt['pets'] = join(', ', $client['pets']);
		else if(!$appt['pets'] && !$client['pets']) $appt['pets'] = '<i>No Pets Found</i>';
		else if(!$appt['pets']) $appt['pets'] = '<i>No Pets</i>';
	}
	$appt['phone'] = $client['phone'];
	if($includeaddress) $appt['address'] = $client['address'];
	if(!in_array($clientptr, (array)$keyClients) && !$client['nokeyrequired'])
		$appt['client'] .= " ".noKeyIcon($clientid);
	$appt['service'] = $_SESSION['servicenames'][$appt['servicecode']];
	if($appt['canceled']) {
		$appt['service'] = "<span style='font-weight:bold;'>CANCELED</span> {$appt['service']}";
		$appt['client'] .= " <span style='font-weight:bold;'>CANCELED</span>";
		$rowClasses[] = 'crossedout';
	}
	else $rowClasses[] = '';
	$rows[] = $appt;
	$row2 = '';
	if($appt['origprovider']) $row2 .= "(Reassigned from ".$appt['origprovider'].") "; 
	if($appt['note']) $row2 .= "Note: ".$appt['note'];
	if($row2) {
		$rows[] = array('#CUSTOM_ROW#' =>"<tr><td style='color:red;' colspan=$numCols>$row2</td></tr>");
		$rowClasses[] = '';
	}
	$rows[] = array('#CUSTOM_ROW#' =>"<tr><td colspan=$numCols><hr></td></tr>");
	$rowClasses[] = '';
}
//print_r($rows);
?>
<style>
body { font-size: 10pt; }
table td { font-size: 10pt; }
.leftbar { padding-left: 5px; }
.crossedout {text-decoration: line-through;}
h2 {text-align:center;}
@media print
{
.newpage {page-break-before:always;}
}
</style>
<?
$h2style = !$_REQUEST['nobreaks'] && $newPage && !$startFinish ? "class='newpage'" : "";
echo "<h2 $h2style>Visits for ".longDayAndDate(strtotime($date))."</h2>";
?>
<form method='POST'>
<?
if(!$included) {
	labeledCheckbox('Hide Canceled Visits:', 'nocanceled', $nocanceled, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null);
	echo "<img src='art/spacer.gif' width=30 height=1>";
	labeledCheckbox('Show Address:', 'includeaddress', $includeaddress, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null);
	echo "<img src='art/spacer.gif' width=30 height=1>";
	labeledCheckbox('Show Pets:', 'includepets', $includepets, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null);
	echo "<img src='art/spacer.gif' width=30 height=1>";
	echo "<input type='SUBMIT' value='Show Visits'> <a href='javascript:window.print()'>Print this page</a></form>";
}
$columnDataLine = 'client|Client||timeofday|Time Window||pets|Pets||service|Service||phone|Phone||address|Address';
$colClasses = array('timeofday'=>'leftbar', 'pets'=>'leftbar',  'service'=>'leftbar',  'phone'=>'leftbar',  'address'=>'leftbar'); 

if($rows) tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, $sortableCols, $rowClasses, $colClasses);
else {
	$startFinish = array();
	if($showstarts) $startFinish[] = "STARTs";
	if($showfinishes) $startFinish[] = "FINISHes";
	$objects = $startFinish ? join(' or ', $startFinish) : 'visits';
	echo "<div style='text-align:center;'>No $objects</div>";
}

