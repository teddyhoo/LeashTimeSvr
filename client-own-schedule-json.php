<?
// client-own-schedule-json.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "client-schedule-fns.php";
require_once "pet-fns.php";
require_once "preference-fns.php";

// Determine access privs
$locked = locked('c-');

$max_rows = 100;

extract($_REQUEST);

$client = $_SESSION["clientid"];

$starting = shortDate();
$ending = shortDate(strtotime("+45 days"));


$json['client'] = $_SESSION["clientname"];
$json['clientid'] = $client;

if(!$_SESSION['clientLoginNoticeDelivered']) {
	$_SESSION['clientLoginNoticeDelivered'] = 1;
	$userNotice = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'clientLoginNotice'");
	if($userNotice) {
		$userNotice = json_decode($userNotice, 'assoc');
		$message = "<span class='fontSize1_5em'>".$userNotice['message']."</span>";
		$template = "<h2>{$userNotice['title']}</h2>$message";
	}
	$json['user_notice_title'] = $userNotice['title'];
	$json['user_notice_message'] = $message;
}	

//include "client-schedule-cal.php";

extract($_REQUEST);

if($_SESSION['clientid']) $client = $_SESSION['clientid'];  // discourage snooping

$max_rows = $limit = -1 ? 9999 : 100;

$appts = array();

$_SESSION['clientScheduleDateRange'] = dbDate($starting).'|'.dbDate($ending);

$found = getClientAppointmentCountAndQuery(dbDate($starting), dbDate($ending), 'date_ASC', $client, $offset, $max_rows, 'includearrivaltimes');
$numFound = 0+substr($found, 0, strpos($found, '|'));

$json['starting'] = shortDate(strtotime($starting));
$json['ending'] = shortDate(strtotime($ending));
$json['visitcount'] = $numFound;


$query = substr($found, strpos($found, '|')+1);

//$appts = $numFound ? fetchAssociations($query) : array();
$appts = $numFound ? fetchAssociationsKeyedBy($query, 'appointmentid') : array();
$appts = array_values($appts);

$originalServiceProviders = originalServiceProviders($appts);

foreach($appts as $key => $appt) {
	if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
		if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
			$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];
	if($appt['canceled']) $canceledCount++;
}

$json['canceledvisitcount'] = $canceledCount;

	foreach(getClientPets($clientid, 'photo,active,petid,name,type') as $pet)
		if($pet && $pet['active'] && $pet['photo']) {
			unset($pet['active']);
			$pet['photo'] = "pet-photo.php?id={$pet['petid']}&version=display";
			$pets[] = $pet;
		}
		
$json['petNamesEnglish'] = getPetNamesForClients(array($client), $inactiveAlso=false, $englishList=true);
$json['petNamesEnglish'] = $json['petNamesEnglish'][$client];
		
$json['pets'] = $pets;

$json['business']['preferences']['offerSimpleMultiDayChangeCancelRequestForm'] = 
	$_SESSION['preferences']['offerSimpleMultiDayChangeCancelRequestForm'];
$json['business']['preferences']['bizName'] = $_SESSION['preferences']['bizName'];
$json['business']['preferences']['shortBizName'] = $_SESSION['preferences']['bizName'];
$json['business']['preferences']['sitterNameDisplay'] = $_SESSION['preferences']['clientProviderNameDisplayMode'];

$providers = getProviderShortNames();
$serviceNames = fetchKeyValuePairs("SELECT servicetypeid, label  FROM tblservicetype");

$timesOfDayRaw = getPreference('appointmentCalendarColumns');
if(!$timesOfDayRaw) $timesOfDayRaw = 'Morning,07:00:00,Midday,11:00:00,Afternoon,15:00:00,Evening,19:00:00';
$timesOfDayRaw = explode(',',$timesOfDayRaw);
$timesOfDay = array();
for($i=0;$i < count($timesOfDayRaw)-1; $i+=2) $timesOfDay[$timesOfDayRaw[$i+1]] = $timesOfDayRaw[$i];
$timeStarts = array_keys($timesOfDay);

foreach($appts as $r => $appt) {
//print_r($timesOfDay);	echo "<p>";
	$appt['starttime'] = $appt['starttime'].':00';  // added this line because of https://leashtime.com/support/admin/admin_ticket.php?track=PTN-SPX-RETR&Refresh=63389
	$tod = null;
	for($i=0;$i < count($timeStarts); $i++) {
		if($i+1 == count($timeStarts)) 
			$tod = $timesOfDay[$timeStarts[$i]];
		else if($i==0 && $appt['starttime'] < $timeStarts[$i]) 
			$tod = $timesOfDay[$timeStarts[$i]];
		else if($appt['starttime'] < $timeStarts[$i+1])
			$tod = $timesOfDay[$timeStarts[$i]];
		if($tod) {
			$appts[$r]['TODColumn'] = $tod;
			break;
		}
	}
	$appts[$r]['primarysittername'] = isset($providers[$appt['providerptr']]) ? $providers[$appt['providerptr']] : 'Unassigned';;
	$appts[$r]['service'] = $serviceNames[$appt['servicecode']];
	
	$appts[$r]['dateAndDay'] = shortDateAndDay(strtotime($appt['date']));
	$appts[$r]['shortDate'] = shortDate(strtotime($appt['date']));
	$appts[$r]['status'] =
		$appt['arrived'] ? 'arrived' : (
		$appt['canceled'] ? 'canceled' : (
		$appt['completed'] ? 'completed' : 'incomplete'));
	$futurityLabels = explodePairsLine('-1|past||0|current||1|future');
	// futurity: -1 if appointment is completely past, 0 if now is in appointment's timeframe, or 1 if appointment timeframe is totally in the future
	$appts[$r]['futurity'] = $futurityLabels[appointmentFuturity($appt)];
	$appts[$r]['totalcharge'] = $appt['charge']+$appt['adjustment'];
	if($billable = fetchFirstAssoc("SELECT * FROM tblbillable WHERE itemtable = 'tblappointment' AND itemptr = {$appt['appointmentid']} AND superseded IS NULL", 1))
		$appts[$r]['tax'] = $billable['tax'];
	if($discount = fetchRow0Col0("SELECT amount FROM relapptdiscount WHERE appointmentptr = {$appt['appointmentid']} LIMIT 1", 1))
		$appts[$r]['discount'] = $discount;
}

$json['visits'] = $appts;

if(!$_REQUEST['debug']) header("Content-type: application/json");

echo json_encode($json);
