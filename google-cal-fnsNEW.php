<? // google-cal-fns.php

//set_include_path('/var/www/prod:/usr/share/php:/usr/share/pear:');
/*set_include_path(get_include_path().':/var/www/prod/ZendGdata-1.11.6/library:');

require_once 'Zend/Loader.php';
Zend_Loader::loadClass('Zend_Gdata');
Zend_Loader::loadClass('Zend_Gdata_AuthSub');
Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
Zend_Loader::loadClass('Zend_Gdata_Calendar');


$user = 'mmlinden@gmail.com';
$pass = 'sylvain2';
$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

$client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);

//outputCalendarList($client);
$gdataCal = getCalFromEmail($client, 'mmlinden@gmail.com'));
*/


function providerAcceptsGoogleCalendarEvents($providerOrId) {
	if(!getPreference('allowSittersToUseGoogleCalendar')) return false;
	if($providerOrId == -1) $userid = $_SESSION['auth_user_id'];
	else if(!is_array($providerOrId))
		$userid = fetchRow0Col0("SELECT userid FROM tblprovider WHERE active AND providerid = $providerOrId");
	else $userid = $providerOrId['active'] ? $providerOrId['userid'] : null;
	if(!$userid) return false;
	if($providerOrId != -1) {
		$allowed = getPreference('googleCalendarEnabledSitters');
		if(!in_array($userid, explode(',', $allowed))) return false;
	}
	return getGoogleCreds($userid);
}
	
function updateProviderCalendarsForDates($providerIdString, $start, $end, $unassigned=null) {
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));

	/*
	$providerIdString  $prov
	0									 0          All
	-1								 0					Unassigned
	N									 N					One provider
	N,N,..						 Array			Many providers
	*/

	$prov = $providerIdString == -1 ? "0" : $providerIdString;

	$problemsClearing = clearAllGoogleItems($start, $end, $providerIdString);

	$sendUnassigned = $unassigned || $providerIdString == -1;
	if($sendUnassigned)
		$sendUnassigned = getManagerIdsForUnassignedGoogleItems();

	$filter = $providerIdString ? "AND providerptr IN ($prov)" : '';
	
	$appts = fetchAssociations("SELECT tblappointment.*, tblprovider.userid, CONCAT_WS(' ', c.lname, c.fname) as clientname 
															FROM tblappointment 
															LEFT JOIN tblclient c ON clientid = clientptr
															LEFT JOIN tblprovider ON providerid = providerptr
															WHERE date >= '$start' AND date <= '$end' $filter");
	if(!$appts) {
		if($problemsClearing) $msg[] = "Problems clearing some calendar items."; 
		$msg[] = "Calendar".($prov ? '' : 's')." updated.";
		return $msg;
	}

	foreach($appts as $appt) {
		if($appt['userid']) $byProvider[$appt['userid']][] = $appt;
		else if(!$appt['providerptr']) $byProvider[0][] = $appt; // Unassigned, to manager
		else $byProvider['NODESTINATION'.$appt['providerptr']][] = $appt; // nowhere to send
	}

	$allowed = getPreference('googleCalendarEnabledSitters');
	$sent = $unsendable = $unready = $ready = 0;
	foreach($byProvider as $userptr => $items) {
		$providerFailed = false;
		if(((int)$userptr != $userptr) 
				|| (!$userptr && !$sendUnassigned) 
				|| ($userptr && !in_array($userptr, explode(',', $allowed)))
				|| ($userptr && !getGoogleCreds($userptr))) {
	//echo "SEND: [$userptr][$sendUnassigned][$allowed]\n\n";
			$providerFailed = 'NODESTINATION';
		}
		else if(!$userptr && $sendUnassigned) {
	//echo "[U$sendUnassigned](".print_r($items, 1).") ";
			foreach($sendUnassigned as $mrguserptr) {
				if(is_string(pushItemsToUserCalendar($items, $mrguserptr, $role='O'))) {
					$badmgrptrs[] = $userptr;
					$unsendable += count($items);
					$unready += 1;
				}
				else {
					$ready += 1;
					$anyUnassignedSent = 1;
				}
			}
			if($anyUnassignedSent) $sent += count($items);
		}
		else {
	//echo "[$userptr] ";
			if(is_string(pushItemsToUserCalendar($items, $userptr, $role='P'))) $providerFailed = true;
			else {
				$sent += count($items);
				$ready += 1;
			}
		}
		if($providerFailed) {
			if($providerFailed === true) $badproviderptrs[] = $userptr;
			$unsendable += count($items);
			$unready += 1;
		}
	}
	if($badproviderptrs) {
		$badproviders = 
			join(', ', fetchCol0(
				"SELECT CONCAT_WS(' ', fname, lname) 
					FROM tblprovider 
					WHERE userid IN (".join(',', $badproviderptrs).")"));
			}
	if($badmgrptrs) $badmanagers = getManagerNames($badmgrptrs);

	$unsendable = !$unsendable ? null : ($unsendable == 1 ? "1 visit" : ((string)$unsendable)." visits");
	$unready = $unready == 1 ? "1 calendar" : ((string)$unready)." calendars";
	$sent = $sent == 1 ? "1 visit" : ((string)$sent)." visits";
	$ready = $ready == 1 ? "1 calendar" : ((string)$ready)." calendars";
	if($unsendable) $msg[] = "$unsendable could not be sent to $unready";
	if($badproviders) $msg[] = "These sitters have invalid Google credentials: $badproviders";
	if($badmanagers) $msg[] = "These managers have invalid Google credentials: $badmanagers";
	$msg[] = "$sent were sent to $ready $ALLSENT";
global $transactionTimes;
if(true) 	$msg[] = "Total Google transaction time: ".sprintf("%.2f", array_sum($transactionTimes));
	return $msg;
}

function getManagerNames($userids) {
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
		include "common/init_db_common.php";
		$users = fetchCol0(
			"SELECT CONCAT_WS(' ', fname, lname) 
				FROM tbluser 
				WHERE bizptr = {$_SESSION['bizptr']} AND rights LIKE 'o-%' AND userid IN (".join(',', $userids).")");
		list($db, $dbhost, $dbuser, $dbpass) = array($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
		include "common/init_db_petbiz.php";
		return $users;
}

	
function getGoogleCreds($userptr) {
	if(!($googleCreds = getUserPreference($userptr, 'googlecreds', 1))) return;
	$googleCreds = explode('#*SEPR*#', $googleCreds);
	return array('username'=>$googleCreds[0], 'password'=>$googleCreds[1]);
}

function saveGoogleCreds($userptr, $username, $password) {
	setUserPreference($userptr, 'googlecreds', "$username#*SEPR*#$password", 1);
}

function dropGoogleCreds($userptr) {
	setUserPreference($userptr, 'googlecreds', null);
	//deleteTable('tblusergooglevisit', "userptr = $userptr", 1);
}

function pushItemsToUserCalendar($items, $userptr, $role=null) {
	$googleCreds = getGoogleCreds($userptr);
	$role = $role ? $role : 'U';
	$visitptrs = array(0);
	foreach($items as $item) $visitptrs[] = $item['appointmentid'];
	$removables = fetchKeyValuePairs(
		"SELECT visitptr, googleurl 
		 FROM tblusergooglevisit 
		 WHERE userptr = $userptr
		   AND visitptr IN (".join(',', $visitptrs).")", 1);
	
	$urls = pushItemsToUserCalendarWithCreds($items, $googleCreds, $removables);
	if(is_string($urls)) return $urls;
	foreach($urls as $visitptr => $url)
		replaceTable('tblusergooglevisit', array('userptr'=>$userptr, 'visitptr'=>$visitptr, 'role'=>$role, 'googleurl'=>$url), 1);
	logChange($userptr, 'tblusergooglevisit', 'g', count($items).' visits');
}


function testUser($googleCreds) {
	require_once 'Zend/Loader.php';
	require_once 'preference-fns.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Calendar');

	$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

	try {
		$client = Zend_Gdata_ClientLogin::getHttpClient($googleCreds['username'],$googleCreds['password'],$service);
  	$gdataCal = new Zend_Gdata_Calendar($client);
		
	}
	catch (Exception $e) {
		return $error = $e->getMessage();
	}
}
	
function clearAllGoogleItems($start, $end, $provIds=null) {
	// CALL FROM PROV SCHED (PROV OR -1)
	// CALL FROM HOMEPAGE (PROV is null)
	$userids = array();
	if($provIds == -1) $userids = getManagerIdsForUnassignedGoogleItems();
	else if($provIds) {
		if(is_array($provIds)) $provIds = join(',', $provIds);
		$sql = "SELECT providerid, userid FROM tblprovider WHERE providerid IN ($provIds)";
		$userids = fetchKeyValuePairs($sql);
	}
	$userids = $userids ? "AND userptr IN (".join(',', $userids).")" : '';
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	$lists = fetchAssociationsGroupedBy(
		"SELECT tblusergooglevisit.*, date
			FROM tblusergooglevisit
			LEFT JOIN tblappointment ON appointmentid = visitptr
			WHERE date >= '$start' AND date <= '$end' $userids", 'userptr');
	for($day = $start; $day <= $end; $day = date('Y-m-d', strtotime("+1 day", strtotime($day))))
		$negDates[] = 0 - date('Ymd', strtotime($day));
	if($negDates) {
		$cancelItems = fetchAssociationsGroupedBy(
			"SELECT * FROM tblusergooglevisit WHERE visitptr IN (".join(',', $negDates).") $userids", 'userptr');
		foreach($cancelItems  as $userptr => $items)
			foreach($items as $item)
				$lists[$userptr][] = $item;
	}
//print_r($negDates );
	foreach($lists as $userptr => $items) {
		$googleCreds = getGoogleCreds($userptr);
		if($googleCreds && is_string(clearGoogleItemsForUser($items, $googleCreds))) {
				$baduserptrs[] = $userptr;
		}
		$deletions = 0;
		foreach($items as $item) {
			deleteTable('tblusergooglevisit', "userptr = $userptr AND visitptr = {$item['visitptr']}", 1);
			if($item['visitptr'] > 0) // do not include "cancel" notices in the count
				$deletions += mysql_affected_rows();
		}
		if($items) logChange($userptr, 'tblusergooglevisit', 'd', "$deletions visits");
	}
	return $baduserptrs;
}

function recentCalendarActivity($prov, $entries=3) {
	$userptr = is_array($prov) ? $prov['userid'] : 0;
	if(!$userptr) {
		$prov = is_array($prov) ? $prov['providerid'] : $prov;
		$userptr = fetchRow0Col0("SELECT userid FROM tblprovider WHERE providerid = $prov LIMIT 1");
	}
	if(!$userptr) return null;
	$rows = fetchAssociations(
		"SELECT * 
			FROM tblchangelog 
			WHERE itemptr = $userptr 
			  AND itemtable = 'tblusergooglevisit'
			  ORDER BY `time` DESC
			  LIMIT $entries");
	foreach($rows as $row) {
		$dateFrame = dateFrame($row['time']);  // today, last, next, this, full  field-utils.php
		$time = strtotime($row['time']);
		$line = $dateFrame == 'today' ? '' : (
						$dateFrame == 'last' ? date('l', $time) : (
						$dateFrame == 'this' ? 	shortNaturalDate($time, 'noyear') : shortNaturalDate($time)));
		if($line) $line .= ' ';
		$line .= date('g:i a ', $time).changeOperationLabel($row).' '.$row['note'];
		$lines[] = $line;
	}
	return join('<br>', $lines);
}
						
function clearGoogleItemsForUser($googleitems, $googleCreds) {
	require_once 'Zend/Loader.php';
	require_once 'preference-fns.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Calendar');

	$user = $googleCreds['username']; //'mmlinden@gmail.com';
	$pass = $googleCreds['password']; //'sylvain2';

	$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

	try {
		$client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);
	}
	catch (Exception $e) { return $e->getMessage(); }

	$gdataCal = getCalFromEmail($client, 'UNUSEDEMAIL');
	foreach($googleitems as $googleitem) {
		try {
global $transactionTimes;
$start = microtime(1);
			$event = $gdataCal->getCalendarListEntry($googleitem['googleurl']);
			$event->delete();
$transactionTimes[] = microtime(1) - $start; 
		}
		catch (Exception $e) { return $e->getMessage(); }
	}
}

function getManagerIdsForUnassignedGoogleItems() {
	$targets = fetchCol0("SELECT userptr FROM tbluserpref WHERE property = 'pushUnassignedToGoogleCalendar' AND value = 1");
	return fetchCol0(
		"SELECT userptr 
			FROM tbluserpref 
			WHERE property = 'googlecreds' AND value IS NOT NULL AND value != ''
						AND userptr IN (".join(',', $targets).")");
}

function pushItemsToUserCalendarWithCreds($items, $googleCreds, $removables=null) {
	set_time_limit(300);
	require_once 'Zend/Loader.php';
	require_once 'preference-fns.php';
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_AuthSub');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Gdata_Calendar');

	$user = $googleCreds['username']; //'mmlinden@gmail.com';
	$pass = $googleCreds['password']; //'sylvain2';

	$service = Zend_Gdata_Calendar::AUTH_SERVICE_NAME; // predefined service name for calendar

	try {
		$client = Zend_Gdata_ClientLogin::getHttpClient($user,$pass,$service);
	}
	catch (Exception $e) { return $e->getMessage(); }

	//outputCalendarList($client);
	$gdataCal = getCalFromEmail($client, 'UNUSEDEMAIL');
	$providers = fetchAssociationsKeyedBy("SELECT * FROM tblprovider", 'providerid', 1);
	foreach($items as $item) if($item['clientptr']) $clientptrs[] = $item['clientptr'];
	$clientNames = array();
	if($clientptrs) $clientNames = fetchKeyValuePairs(
		"SELECT clientid, CONCAT_WS(' ', fname, lname) 
			FROM tblclient 
			WHERE clientid IN (".join(',', $clientptrs).")");
	
	
	foreach((array)$items as $i => $item) {
		$item['clientname'] = $clientNames[$item['clientptr']];
		if($item['canceled']) {
			$canceled[0-date('Ymd', strtotime($item['date']))][] = $item;
			unset($items[$i]);
		}
	}
	
	foreach($items as $item) {
		// COVERED BY clearAllGoogleItems
		//if($removables[$item['appointmentid']])
		//	deleteAppointmentEvent($gdataCal, $removables[$item['appointmentid']]);
		$provider = $providers[$item['providerptr']];
		$urls[$item['appointmentid']] = 
			addAppointmentEvent($gdataCal, googleCalEventForAppt($item, $providers[$item['providerptr']]));
	}
	foreach((array)$canceled as $negdate => $canceledItems) {
		$description = array();
		foreach($canceledItems as $item) {
			$shortName = $item['nickname'] ? $item['nickname'] : (
									 $item['lname'] ? "{$item['fname']} {$item['lname']}" : 
									 "no sitter assigned");
			$description[] = "{$item['clientname']} - {$item['timeofday']} ($shortName)";
		}
		$description = "CANCELED:\n".join("\n", $description);;
		$date = (string)(0-$negdate);
		$date = substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2);
		$urls[$item['appointmentid']] = 
			addLabelEvent ($gdataCal, $date, $title='CANCELED', $description);
	}	
	return $urls;
}

function getGoogleVisitEventForUser($visitptr, $userid) {
	return fetchFirstAssoc("SELECT * FROM tblusergooglevisit WHERE userptr = $userid AND visitptr = $visitptr");
}

function deleteAppointmentEventForVisit($gdataCal, $visitId, $userid) {
	$event = getGoogleVisitEventForUser($visitptr, $userid);
	if(!$event) return;
	$eventEditUrl = $event['googleurl'];
	deleteAppointmentEvent($gdataCal, $eventEditUrl);
}
	
function deleteAppointmentEvent($gdataCal, $eventEditUrl) {
	//print_r($gdataCal->getCalendarListEntry($eventEditUrl));
	//$gdataCal->delete($eventEditUrl);
	try {
global $transactionTimes;
$start = microtime(1);
		$event = $gdataCal->getCalendarListEntry($eventEditUrl);
		$event->delete();
$transactionTimes[] = microtime(1) - $start; 
	}
	catch (Exception $e) {}
	//if($event) $event->delete();
}

function googleCalEventForAppt($item, $provider=null) {
	if($item['appointmentid']) $event['uid'] = $item['appointmentid'];
	if($item['servicecode']) {
		require_once "service-fns.php";
		if(!$serviceTypes) 
			$serviceTypes = getAllServiceNamesById($refresh=0, $noInactiveLabel=true, $setGlobalVar=false);
			
		$pets = $item['pets'];
		if($pets == 'All Pets') {
			$pets = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = '{$item['clientptr']}' ORDER BY name");
			$pets = $pets ? join(',', $pets) : 'All Pets';
		}
		if($item['canceled']) $status = 'CANCELED: ';
		$event['title'] = "$status{$serviceTypes[$item['servicecode']]} ($pets)";
	}
	if($item['clientname']) $event['note'][] = $item['clientname'] ;
	if($item['note']) $event['note'][] = $item['note'] ;
	if($event['note']) $event['note'] = join("\n", $event['note']);;
	$date = $item['date'];
	$event['startDate'] = $date;
	$event['startTime'] = $item['starttime'];
	if(strcmp($item['endtime'], $item['starttime']) < 0)
		$date = date("+1 day", strtotime($date)); // <== Bad line! But what format should date have?
	$event['endDate'] = $date;
	$event['endTime'] = $item['endtime'];
	if($item['providerptr']) {
		if(!$provider) {
			require_once "provider_fns.php";
			$provider = getProvider($item['providerptr']);
		}
		$event['attendeeName'] = "{$provider['fname']} {$provider['lname']}";
		$event['attendeeEmail'] = $provider['email'];
	}
	return $event;
}

function addLabelEvent ($gdataCal, $date, $title, $description)
/*$title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08')*/
{
	global $tzOffset;
	$tzOffset = $tzOffset ? $tzOffset : -5;
	
	//$tzOffset = sprintf("%02d", $tzOffset);
	// what is wrong with sprintf?!!!
	if(abs($tzOffset) < 10) $tzOffset = ($tzOffset < 0 ? '-' : '').'0'.abs($tzOffset);
	
  $newEvent = $gdataCal->newEventEntry();
  
  
  $newEvent->title = $gdataCal->newTitle($title);
  $newEvent->where = array($gdataCal->newWhere(''));
  $newEvent->content = $gdataCal->newContent($description);
  
  $when = $gdataCal->newWhen();
  $when->startTime = $date;
  $newEvent->when = array($when);

  // Upload the event to the calendar server
  // A copy of the event as it is recorded on the server is returned
global $transactionTimes;
$start = microtime(1);
  $createdEvent = $gdataCal->insertEvent($newEvent);
$transactionTimes[] = microtime(1) - $start; 
  return $createdEvent->id->text;
}

function addAppointmentEvent ($gdataCal, $event)
/*$title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08')*/
{
	global $tzOffset;
	$tzOffset = $tzOffset ? $tzOffset : -5;
	
	//$tzOffset = sprintf("%02d", $tzOffset);
	// what is wrong with sprintf?!!!
	if(abs($tzOffset) < 10) $tzOffset = ($tzOffset < 0 ? '-' : '').'0'.abs($tzOffset);
	
  $newEvent = $gdataCal->newEventEntry();
  
  
  $newEvent->title = $gdataCal->newTitle($event['title']);
  $newEvent->where = array($gdataCal->newWhere(''));
  $newEvent->content = $gdataCal->newContent($event['note']);
  
  $when = $gdataCal->newWhen();
  $when->startTime = "{$event['startDate']}T{$event['startTime']}.000";//{$tzOffset}:00";
  $when->endTime = "{$event['endDate']}T{$event['endTime']}.000";//{$tzOffset}:00";
//echo  $when->startTime .'<p>'.$when->endTime;
  $newEvent->when = array($when);

  // Upload the event to the calendar server
  // A copy of the event as it is recorded on the server is returned
global $transactionTimes;
$start = microtime(1);
  $createdEvent = $gdataCal->insertEvent($newEvent);
$transactionTimes[] = microtime(1) - $start; 
  return $createdEvent->id->text;
}

function createEvent ($gdataCal, $title = 'Tennis with Beth',
    $desc='Meet for a quick lesson', $where = 'On the courts',
    $startDate = '2008-01-20', $startTime = '10:00',
    $endDate = '2008-01-20', $endTime = '11:00', $tzOffset = '-08')
{
  $newEvent = $gdataCal->newEventEntry();
  
  $newEvent->title = $gdataCal->newTitle($title);
  $newEvent->where = array($gdataCal->newWhere($where));
  $newEvent->content = $gdataCal->newContent("$desc");
  
  $when = $gdataCal->newWhen();
  $when->startTime = "{$startDate}T{$startTime}.000{$tzOffset}:00";
  $when->endTime = "{$endDate}T{$endTime}.000{$tzOffset}:00";
  $newEvent->when = array($when);
  //$newEvent->setWho = $who? $who : array();

  // Upload the event to the calendar server
  // A copy of the event as it is recorded on the server is returned
  $createdEvent = $gdataCal->insertEvent($newEvent);
  return $createdEvent->id->text;
}


function getCalFromEmail($client, $email) 
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  
  return $gdataCal;
  
  /*$calFeed = $gdataCal->getCalendarListFeed();
  foreach ($calFeed as $calendar)
    if($calendar->title->text == $email)
    	return $calendar;*/
}	

function outputCalendarList($client) 
{
  $gdataCal = new Zend_Gdata_Calendar($client);
  $calFeed = $gdataCal->getCalendarListFeed();
  echo '<h1>' . $calFeed->title->text . '</h1>';
  echo '<ul>';
  foreach ($calFeed as $calendar) {
    echo '<li>' . $calendar->title->text . '</li>';
    echo '<li>' . print_r($calendar, 1);
  }
  echo '</ul>';
} 