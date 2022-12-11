<? // event-email-fns.php
// functions to support notifyication of staff when certain events occur
//require_once "common/init_session.php";
//require_once "common/init_db_petbiz.php";
//require_once "gui-fns.php";



function getEventTypeMenu() {
	//global $testProspectRequestNotifications;
 	//$testProspectRequestNotifications = dbTEST('dogslife,doggiewalkerdotcom');
 
	$eventTypeMenu = //explodePairsLine('r|Client Requests||c|Confirmations and Declines||o|Overdue Confirmations||p|Overdue Prepayments');
		array( 'i'=>'Prospect Requests',
			'r'=>'Client Requests',
			'c'=>'Confirmations and Declines',
			'o'=>'Overdue Confirmations',
			'p'=>'Overdue Prepayments',
			'e'=>'Sitter-Sitter Emails',
			's'=>'Schedule Changes',
			't'=>'Sitter-Scheduled Time Off',
			'k'=>'Sitter-Client Emails'
		);
if($_SESSION['preferences']['enableOverdueArrivalEventType']) $eventTypeMenu['v'] = 'Overdue Visits';
if(staffOnlyTEST()) $eventTypeMenu['h'] = 'Home Safe Replies';

if(staffOnlyTEST()) $eventTypeMenu['q'] = 'Surveys Received';




//if(dbTEST('dogslife,tonkatest,sarahrichpetsitting')) $eventTypeMenu['v'] = 'Overdue Visits';		
asort($eventTypeMenu);
		
/*	if($testProspectRequestNotifications) {
		$newMenu = array('i'=>'Prospect Requests');
		foreach($eventTypeMenu as $k => $v) $newMenu[$k] = $v;
		$eventTypeMenu = $newMenu;
	}
*/
	return $eventTypeMenu;
}

$eventTypeMenu = getEventTypeMenu();

function staffToNotify($eventType) {
	if(!in_array('relstaffnotification', fetchCol0("SHOW TABLES"))) return array();
	$all = fetchAssociations($sql = "SELECT * FROM relstaffnotification WHERE eventtypes LIKE '%$eventType%'"); //daysofweek, timeofday
	
	$time = strtotime(date('H:i:s'));
	$day = date('w');
	$dows = explode(',', 'Su,M,Tu,W,Th,F,Sa');
	foreach($all as $i => $entry) {
		$dash = strpos($entry['timeofday'], '-');
		$start = strtotime(substr($entry['timeofday'], 0, $dash));
		$end = strtotime(substr($entry['timeofday'], $dash+1));
		$entryDays = $entry['daysofweek'];
		if(
				($start > $time || $end < $time)
				|| ($entryDays == 'Weekdays' && ($day == 0 || $day == 6))
				|| ($entryDays == 'Weekends' && ($day != 0 && $day != 6))
				|| (!in_array($entryDays, array('Every Day','Weekdays','Weekends')) 
								&& !in_array($dows[$day], explode(',', $entryDays)))
		) unset($all[$i]);
	}
 }	
	return array_merge($all);
}

/*
Event Types:

r- Client Requests
c- Confirmations and Declines
o- Overdue Confirmations
p- Overdue Prepayments
v-  Overdue Visits
i- Prospect Requests
s- Schedule Changes
k- Sitter-Client Emails
t- Sitter-Scheduled Time Off
e- Sitter-Sitter Emails
h- Home Safe notifications

Keep this in sync with $eventTypeMenu
*/

function notifyStaff($eventType, $subject, $msgBody) {
	$staff = staffPersonsToNotify($eventType);
	$html = strpos($msgBody, '<') !== FALSE;
	foreach($staff as $person)
		enqueueEmailNotification($person, $subject, $msgBody, $cc=null, $mgrname=null, $html);
	return $staff;
}

function notifyStaffBySMS($eventType, $msgBody) {
	$staff = staffPersonsToNotify($eventType);
	foreach($staff as $person)
		notifyByLeashTimeSMS($person, $msgBody, $media=null); // comm-fns.php
	return $staff;
}

function staffPersonsToNotify($eventType) {
	$providersByUserId = fetchAssociationsKeyedBy("SELECT providerid, userid FROM tblprovider WHERE active = 1", 'userid');
	$entries = staffToNotify($eventType);
	// filter out inactive staff
	foreach($entries as $entry) $userids[] = $entry['userptr'];
	$userStatus = checkUserStatus($userids);
	$staff = array();
	foreach($entries as $entry) {
		if($userStatus[$entry['userptr']] == 0) continue;
		if(isset($providersByUserId[$entry['userptr']])) {
			$staff[$entry['email']] = $providersByUserId[$entry['userptr']];
		}
		else $staff[$entry['email']]['userid'] = $entry['userptr'];
		$staff[$entry['email']]['email'] = $entry['email'];
	}
	return $staff;
}
