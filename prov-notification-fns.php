<? //prov-notification-fns.php

require_once "comm-fns.php";
require_once "provider-fns.php";
require_once "preference-fns.php";
require_once "response-token-fns.php";
require_once "prov-schedule-fns.php";
require_once "appointment-fns.php";
require_once "key-fns.php";


// WARNING: this function is defined in init_session
if(!function_exists('userRole')) {
	function userRole() {
		return '';
	}
}

function sendWeeklyOrDailyProviderSchedules($starting, $delayed=false) {
	// if today is the right day of week, send weekly schedules to providers registered to receive them
	// send daily schedules to providers registered to receive them and who have not been sent weekly schedules
	global $prefs; // for getRequestScopePreferences() in preference-fns, used by getProviderPreference below
	$prefs = null;  // must clear prefs in case they were set for a prior business in the cron job
	getRequestScopePreferences();
	logChange(999, 'providerschedules', 'c', 'Started to queue up provider schedules.');

	$weekdayToSendWeeklyNotifications = 
		fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'scheduleDay'");
	$weekdayToSendWeeklyNotifications = $weekdayToSendWeeklyNotifications == date('l');
	$numsent = 0;
	//$noEmptyProviderScheduleNotification = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'noEmptyProviderScheduleNotification'");
	foreach(getProviders("active=1") as $person) {
		// !isset means value is actually null (not zero, not empty string)
		$sendDailyVisits = isset($person['dailyvisitsemail']) ? $person['dailyvisitsemail'] : $_SESSION['preferences']['scheduleDaily'];
		$sendWeeklyVisits = isset($person['weeklyvisitsemail']) ? $person['weeklyvisitsemail'] : $_SESSION['preferences']['scheduleDay'];

		if(!$person['active'] || (!$sendDailyVisits && !$sendWeeklyVisits))
			continue;
			
		if($weekdayToSendWeeklyNotifications && $person['weeklyvisitsemail'])
			$numsent += sendProviderSchedule($person, $starting, true, $delayed);
		else if($person['dailyvisitsemail'])
			$numsent += sendProviderSchedule($person, $starting, false, $delayed);
	}
	$unassignedemail = fetchKeyValuePairs(
			"SELECT property, value FROM tblpreference 
			 WHERE property IN ('unassignedemail', 'unassigneddailyvisitsemail','unassignedweeklyvisitsemail')");
	if($unassignedemail) {  
		if($weekdayToSendWeeklyNotifications && $unassignedemail['unassignedweeklyvisitsemail'])
			$numsent += sendUnassignedSchedule($unassignedemail['unassignedemail'], $starting, true, $delayed);
		else if($unassignedemail['unassigneddailyvisitsemail'])
			$numsent += sendUnassignedSchedule($unassignedemail['unassignedemail'], $starting, false, $delayed);
	}
	logChange(999, 'providerschedules', 'c', "Queued up provider schedules: [$numsent].");
}
	
/*function sendProviderSchedules($starting, $week, $delayed=false) {
	foreach(getProviders() as $person) {
		if($person['active'] &&
			 ((!$week && $person['dailyvisitsemail']) ||
			  ($week && $person['weeklyvisitsemail'])))
			sendProviderSchedule($person, $starting, $week, $delayed);
	}
}	*/
	
function sendUnassignedSchedule($emailaddress, $starting, $week, $delayed=false) {
	$person = array('providerid'=> -1,  'email' => $emailaddress);
	$providerid = $person['providerid'];
	$span = shortDate(strtotime($starting));
	if($week) $span = "the week starting $span";
	$week = $week ? 1 : 0;
	ob_start();
	ob_implicit_flush(0);
	providerScheduleTableForEmail($providerid, $starting, (isset($week)? $week : ''));
	$schedule = ob_get_contents();
	ob_end_clean();
	
	$suppressAllCanceled = fetchPreference('noAllCanceledProviderScheduleNotification');  // catches ALL CANCELED and EMPTY schedules		
	$suppressEmptySchedules = $suppressAllCanceled || fetchPreference('noEmptyProviderScheduleNotification');  // catches only EMPTY schedules
	if($suppressEmptySchedules && getVisitCount($provider, $starting, $week, $canceledAlso=!$suppressAllCanceled) == 0)
		return 0;
		
	/*if(strpos($schedule, 'viewclient') === FALSE) { // no appointments
		$suppress = getProviderPreference($provider, 'noEmptyProviderScheduleNotification', false);
		if($suppress) return 0;
	}
	*/
	
	$body = 'The following visits are Unassigned<p>'.$schedule;
	if($delayed) enqueueEmailNotification($person, "Unassigned visits for $span", $body, null, null, true);
	else notifyByEmail($person, "Unassigned visits for $span", $body, null, null, true);
	return 1;
}

function sendProviderSchedule($person, $starting, $week, $delayed=false) {
echo "START: 	sendProviderSchedule\n";
	$provider = $person['providerid'];
	$span = date('l', strtotime($starting)).', '.shortDate(strtotime($starting));
	if($week) $span = "the week starting $span";
	$week = $week ? 1 : 0;
	ob_start();
	ob_implicit_flush(0);
	providerScheduleTableForEmail($provider, $starting, (isset($week)? $week : ''));

	$schedule = ob_get_contents();
	
	
	ob_end_clean();
//echo "========================\n$schedule\n^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^\n";	


	$suppressAllCanceled = 
		$provider ? getProviderPreference($provider, 'noAllCanceledProviderScheduleNotification', false)  // catches ALL CANCELED and EMPTY schedules	
			: fetchPreference('noAllCanceledProviderScheduleNotification');
	
	
	$suppressEmptySchedules = 
		$suppressAllCanceled || 
		($provider ? getProviderPreference($provider, 'noEmptyProviderScheduleNotification', false)
			: fetchPreference('noEmptyProviderScheduleNotification'));
	if($suppressEmptySchedules && getVisitCount($provider, $starting, $week, $canceledAlso=!$suppressAllCanceled) == 0)
		return 0;
		
	/*if(strpos($schedule, 'viewclient') === FALSE) { // no appointments
		$suppress = getProviderPreference($provider, 'noEmptyProviderScheduleNotification', false);
		if($suppress) return 0;
	}
	*/
	$body = "Dear ".providerShortName($person).',<p>'.$schedule;
	if($delayed) enqueueEmailNotification($person, "Your schedule for $span", $body, null, null, true);
	else notifyByEmail($person, "Your schedule for $span", $body, null, null, true);
	return 1;
}

function getVisitCount($provider, $starting, $week, $canceledAlso=false) {
	$ending = $week ? date('Y-m-d', strtotime("+6 days", strtotime($starting))) : $starting;
	$provider = $provider ? $provider : '0';
	$notCanceledClause = $canceledAlso ? "" : "AND canceled IS NULL";
	$count = fetchRow0Col0(
		"SELECT count(0) 
			FROM tblappointment 
			WHERE date BETWEEN '$starting' AND '$ending' 
			$notCanceledClause
			AND providerptr = $provider");
	//logError("provider [$provider] visits for [$starting-$ending] [".($canceledAlso ? 'count canceled' : 'no canceled')."] : $count") ;
	return $count;
}

function providerScheduleTableForEmail($provider, $starting, $week, $ending=null) {
	$ending = $ending ? $ending  : ($week ? date('Y-m-d', strtotime("+6 days", strtotime($starting))) : $starting);
	$found = getProviderAppointmentCountAndQuery(dbDate($starting), dbDate($ending), null, $provider, 0, 999);
	$numFound = 0+substr($found, 0, strpos($found, '|'));
	$query = substr($found, strpos($found, '|')+1);
	$appts = $numFound ? fetchAssociations($query) : array();
	
	$originalServiceProviders = originalServiceProviders($appts);

	foreach($appts as $key => $appt) if($provider == -1 && $appt['canceled']) unset($appts[$key]);
	$numFound = count($appts);
	$numCanceled = 0;
	$numUncanceled = 0;
	
	foreach($appts as $key => $appt) {
		if($appt['canceled']) {
			if($provider == -1) continue; // do not count unassigned canceled visits
			$numCanceled++;
		}
		else $numUncanceled++;
		
		if(!($appts[$key]['origprovider'] = appointmentUnassignedFrom($appt)))
			if($appt['providerptr'] != $originalServiceProviders[$appt['serviceptr']]['providerptr'])
				$appts[$key]['origprovider'] = $originalServiceProviders[$appt['serviceptr']]['providername'];
	}
	
	if($provider != -1) {
		$allTimeOff = getProviderTimeOffInRange($provider, array(dbDate($starting), dbDate($ending)));
		foreach($allTimeOff as $to) {
			$to['starttime'] = date('H:i:s', strtotime(substr($to['timeofday'], 0, strpos($to['timeofday'], '-'))));
			$appts[] = $to;
		}
		usort($appts, 'dateTimesInOrder');
	}

	

	$daysAhead = 14;
	$clientsMissingKeys = clientKeysMissingForDaysAhead($daysAhead, $provider);
	if($clientsMissingKeys) {
		$clientDetails = getClientDetails($clientsMissingKeys);
		foreach($clientDetails as $id => $client) $names[] = $client['clientname'];
		$preface = "<span class='sortableListCell' style='color:darkgreen;font-weight:bold;'>You will need keys to the following clients' houses
		       for visits over the next $daysAhead days:<p align=center>".
		      join(', ', $names)."</p></span><p>";
	}
	
	echo "<a href='".globalURL('app.php')."' target='_blank'>Go to LeashTime</a>";
	
	echo <<<HTML
<style>
 .completedtask {
	background: lightgreen;
}

 .completedtaskEVEN {
	background: #CDFECD;
}

 .noncompletedtask {
	background: #FEFF99;
}

 .noncompletedtaskEVEN {
	background: #FEFFB5;
}

 .canceledtask {
	background: #FFC0CB;
}

 .canceledtaskEVEN {
	background: #FF93A5;
}

 .futuretask {
	background: white;
}

 .futuretaskEVEN {
	background: #EEE5FF;/* #FFE3E3; rose*/ /* #F6E5F7 lilac*/ /* #EEEEFF VERY light blue */
}
 .daycalendardaterow { /* daycalendar td which displays date */
	background:lightblue;
	text-align:center;
	border: solid black 1px;
	font-weight:bold;
}
 .highprioritytask {
	border: solid red 2px;
}


</style>
HTML;
	echo $preface;
	echo "<h2>".($provider != -1 ? 'Your' : 'Unassigned')." visits for "
		.($starting == $ending ? '' : (
			$week ? "the week starting " : "the period starting "));
	echo longestDayAndDate(strtotime($starting))."</h2>";
	if($numCanceled + $numUncanceled == 0) $visitCount = 'No visits.';
	else {
		if($numCanceled) $visitCount .= "$numCanceled <font color=red>CANCELED</font> visit".($numCanceled == 1 ? '. ' : 's. ');
		if($numUncanceled) $visitCount .= "$numUncanceled active visit".($numUncanceled == 1 ? '. ' : 's. ');
	}
	echo "$visitCount<p>";
	global $wagPrimaryNameMode;
	$wagPrimaryNameMode = fetchPreference('provuisched_client');
	$wagPrimaryNameMode = $wagPrimaryNameMode ? $wagPrimaryNameMode : 'fullname/pets';	
	if($numFound) providerScheduleTable($appts, array('buttons','date'), 'noSort', null, 'noLinks', $week, 'providerView');
}

function dateTimesInOrder($a, $b) {
	return strcmp("{$a['date']} {$a['starttime']}", "{$b['date']} {$b['starttime']}");
}
