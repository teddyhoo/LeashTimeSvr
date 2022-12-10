<? // stale-appointment-fns.php
// for handling visits that have not been reported complete within a certain time
// prefs: reportStaleVisits (default: FALSE), visitsStaleAfterMinutes (default: 15), staleVisitsLimitDays (default: 1)
// staleVisitsLimitDays - if visit is stale, but staleVisitsLimitDays (min: 1) old or older, ignore it

require_once "preference-fns.php";

function setUpStaleVisits() {
	doQuery(
"CREATE TABLE IF NOT EXISTS `tblstaleappointment` (
  `appointmentptr` int(11) NOT NULL,
  `notificationdate` datetime NOT NULL,
  PRIMARY KEY (`appointmentptr`)
 ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");
}

function findNewlyStaleVisits() {
	$prefs = fetchKeyValuePairs("SELECT * FROM tblpreference WHERE property IN ('reportStaleVisits', 'visitsStaleAfterMinutes', 'staleVisitsLimitDays', 'ignoreVisitsMarkedArrived')");
	if(!$prefs['reportStaleVisits']) return;
	if(!in_array('tblstaleappointment', fetchCol0("SHOW TABLES"))) {
		logError("Tried to findNewlyStaleVisits but tblstaleappointment does not exist.");
		return;
	}
	if(!$prefs['staleVisitsLimitDays']) {
		$prefs['staleVisitsLimitDays'] = 1;
		setPreference('staleVisitsLimitDays', $prefs['staleVisitsLimitDays']);
	}
	//echo "{$prefs['staleVisitsLimitDays']}<hr>".strtotime("-{$prefs['staleVisitsLimitDays']} DAYS")."<hr>";
	if(!$prefs['visitsStaleAfterMinutes']) {
		$prefs['visitsStaleAfterMinutes'] = 15;
		setPreference('visitsStaleAfterMinutes', $prefs['visitsStaleAfterMinutes']);
	}
	//$staleTime = date('Y-m-d H:i:s', strtotime("-{$prefs['visitsStaleAfterMinutes']} MINUTES"));
	$now = date('Y-m-d H:i:s');
	
	//$dtF = new DateTime("@0");
	//$dtT = new DateTime("@".($prefs['visitsStaleAfterMinutes']*60));
	//$staleTime = $dtF->diff($dtT)->format('%a %H:%i');
	$rowEndTime = "if(endtime<starttime,
													CONCAT_WS(' ', DATE_ADD(date, INTERVAL 1 DAY), endtime),
													CONCAT_WS(' ', date, endtime))";

	if($prefs['reportStaleVisits'] == 2) { // send only to selected providers
		$selectedProviders = fetchCol0("SELECT providerptr FROM tblproviderpref WHERE property = 'reportStaleVisits' AND value = 1");
		if($selectedProviders) $selectedProviders = " AND providerptr IN (".join(',', $selectedProviders).")";
		else { // if no selected providers return empty array
			//$selectedProviders = "";
			return array();
		}
	}

	$staleTime = gmdate('z H:i:s', $prefs['visitsStaleAfterMinutes']*60);
	$sql = "SELECT appointmentid 
						FROM tblappointment 
						LEFT JOIN tblstaleappointment ON appointmentptr = appointmentid
						WHERE completed IS NULL AND canceled IS NULL AND appointmentptr IS NULL
							AND DATE > '".date('Y-m-d', strtotime("-{$prefs['staleVisitsLimitDays']} DAYS"))."'
							AND ADDTIME($rowEndTime, '$staleTime') <= '$now'
							$selectedProviders
						ORDER BY date, starttime, providerptr";
	
	$ids = fetchCol0($sql);
	
	if($ids && $prefs['ignoreVisitsMarkedArrived']) {
		$arrivals = fetchCol0(
			"SELECT appointmentptr 
				FROM tblgeotrack 
				WHERE appointmentptr IN (".join(',', $ids).") AND event = 'arrived'");
		$ids = array_diff($ids, $arrivals);
	}
	
	if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableOverdueArrivalNotifications' LIMIT 1", 1)) {
		$ids = array_unique(array_merge($ids, findLateArrivalVisitIds($prefs, $selectedProviders)));
		if($ids) $ids = fetchCol0(
			"SELECT appointmentid FROM tblappointment
				WHERE appointmentid IN (".join(',', $ids).") 
				ORDER BY date, starttime", 1);
	}
	
	
	return $ids;
}

function findLateArrivalVisitIds($prefs, $selectedProviders) {
	// for servicetypes designated as being start time-sensitive
	// find visits that are not yet marked arrived
	// e.g., "overdueStarting_72"=>"72"
	$servicePreferencePrefix = 'overdueStarting_';
	$serviceCodes = fetchKeyValuePairs(
		"SELECT * FROM tblpreference 
			WHERE property LIKE '{$servicePreferencePrefix}%'");
	if(!$serviceCodes) return array();

	$serviceCodes = $serviceCodes ? join(',', $serviceCodes)  : null;
	$now = date('Y-m-d H:i:s');
	$staleTime = gmdate('z H:i:s', $prefs['visitsStaleAfterMinutes']*60);
	$sql = "SELECT appointmentid 
						FROM tblappointment 
						LEFT JOIN tblstaleappointment ON appointmentptr = appointmentid
						WHERE completed IS NULL AND canceled IS NULL AND appointmentptr IS NULL
							AND servicecode IN ($serviceCodes)
							$selectedProviders
							AND DATE > '".date('Y-m-d', strtotime("-{$prefs['staleVisitsLimitDays']} DAYS"))."'
							AND ADDTIME(CONCAT_WS(' ', date, starttime), '$staleTime') <= '$now'
						ORDER BY date, starttime, providerptr";
	$ids = fetchCol0($sql);
//echo "<pre>$sql<hr>".print_r($ids, 1);
	if($ids) {
		$arrivals = fetchCol0(
			"SELECT appointmentptr 
				FROM tblgeotrack 
				WHERE appointmentptr IN (".join(',', $ids).") AND event = 'arrived'");
		$ids = array_diff($ids, $arrivals);
	}
	return $ids;
}

function markStaleVisits($appointmentIds) {
	$now = date('Y-m-d H:i:s');
	foreach($appointmentIds as $apptid)
		insertTable('tblstaleappointment', array('appointmentptr'=>$apptid, 'notificationdate'=>$now));
}


function generateStaleVisitsRequest($appointmentIds) {
	require_once "appointment-fns.php";
	require_once "request-fns.php";
	$appts = fetchAssociationsKeyedBy(
		"SELECT tblappointment.*,
				CONCAT_WS(' ', c.fname, c.lname) as client,
				CONCAT_WS(', ', c.street1, c.city) as clientaddress,
				CONCAT_WS(' ', p.fname, p.lname) as provider,
				CONCAT_WS(' ', p.street1, p.city) as provideraddress,
				p.homephone as homephone,
				p.cellphone as cellphone,
				p.workphone as workphone,
				p.email as email,
				s.label as service
			FROM tblappointment
				LEFT JOIN tblclient c ON clientid = clientptr
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblservicetype s ON servicetypeid = servicecode
			WHERE appointmentid IN (".join(',', $appointmentIds).")
				ORDER BY date, timeofday", 'appointmentid');

	if($appts && mattOnlyTEST()) $arrivals = fetchKeyValuePairs(
		"SELECT appointmentptr, date 
			FROM tblgeotrack 
			WHERE appointmentptr IN (".join(',', $appointmentIds).") AND event = 'arrived'");
	
	$asTable = true;
	$cols = array('Time', 'Service', 'Client', 'Sitter', 'Sitter Contact');
	$showAlternate = dbTEST('dogslife');
	if($showAlternate) {
		$cols[] = 'Fallback Sitter';
	}
	$numcols = count($cols);

if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableOverdueArrivalNotifications' LIMIT 1", 1)) {
	$now = date('Y-m-d H:i:s');
	foreach($appts as $appt) {
		$dayOfEndTime = 
			$appt['endtime'] < $appt['starttime'] 
				? date('Y-m-d', strtotime("+1 day", strtotime($appt['date'])))
				: $appt['date'];
		$endTime = "$dayOfEndTime {$appt['endtime']}";
		if($now < $endTime) $lateArrivals[] = $appt;
		else $lateCompletions[] = $appt;
	}
		
	if($lateArrivals) {
		$message = "The following visits which should be marked arrived by now have not been:<p>";
		$message .= "<table border=1 bordercolor=black><tr><th>".join('</th><th>', $cols)."</th></tr>";
		foreach($lateArrivals as $appt) {
			$message .= overdueVisitTableRow($appt, $arrivals, $date, $showAlternate, $numcols);
			$date = $appt['date'];
		}
		$message .= "</table>";
	}
	$date = null;
	if($lateCompletions) {
		if($message) $message .= "<p>";
		$message .= "The following visits which should be complete by now have not been marked complete:<p>";
		$message .= "<table border=1 bordercolor=black><tr><th>".join('</th><th>', $cols)."</th></tr>";
		foreach($lateCompletions as $appt) {
			$message .= overdueVisitTableRow($appt, $arrivals, $date, $showAlternate, $numcols);
			$date = $appt['date'];
		}
		$message .= "</table>";
	}
} // mattOnlyTEST()	
else {
	if($asTable) $message = "<table border=1 bordercolor=black><tr><th>".join('</th><th>', $cols)."</th></tr>";;
	foreach($appts as $appt) {
		if($asTable) {
			$message .= overdueVisitTableRow($appt, $arrivals, $date, $showAlternate, $numcols);
			$date = $appt['date'];
		}
	}
	if($asTable) $message .= "</table>";
	
	$message = "The following visits which should be complete by now have not been marked complete:<p>$message";
}  
	$plural = count($appts) ? 's' : '';
  saveNewSystemNotificationRequest(count($appts)." Overdue Visit$plural", $message, 
  																	$extraFields = array('odappts'=>join(',', $appointmentIds)));
  
	return $message;
}

function overdueVisitTableRow($appt, $arrivals, $date, $showAlternate, $numcols) {
	if($date != $appt['date'])
		$message .= "<tr><td bgcolor='lightblue' valign='TOP' colspan=$numcols>".longerDayAndDate(strtotime($appt['date']))."</td></tr>";
	$date = $appt['date'];
	if(!$appt['providerptr']) $appt['provider'] = '<b>Unassigned</b>';
	$arrivalNote = $arrivals ? $arrivals[$appt['appointmentid']] : '';
	$arrivalNote = $arrivalNote ? "<br><span style='color:gray;font-style:italic;'>arrived: ".date('g:i a', strtotime($arrivalNote))."</span>" : '';
	$message .= "<tr><td valign='TOP'>{$appt['timeofday']}$arrivalNote</td><td valign='TOP'>{$appt['service']}</td>"
				."<td valign='TOP'>{$appt['client']}<br>({$appt['pets']})<br>{$appt['clientaddress']}</td>"
				."<td valign='TOP'>{$appt['provider']}</td>";

	$contacts = contactsArray($appt, array('cellphone'=>'(c)', 'homephone'=>'(h)', 'workphone'=>'(w)', 'email'=>''));
	$contacts = $contacts ? $contacts : array('&nbsp;');
	$message .= "<td valign='TOP'>".join('<br>', $contacts)."</td>";
	if($showAlternate) {
		require_once "provider-fns.php";
		$altSitter = findAlternateSitter($appt['clientptr'], $ignoreSitter=$appt['providerptr'], $appt['date']);
		if(is_string($altSitter)) $message .= "<td valign='TOP'>$altSitter</td>";
		else {
			$contacts = array("{$altSitter['fname']} {$altSitter['lname']}");
			$contacts = array_merge($contacts, contactsArray($altSitter, array('cellphone'=>'(c)', 'homephone'=>'(h)', 'workphone'=>'(w)', 'email'=>'')));
			$contacts = $contacts ? $contacts : array('&nbsp;');
			$message .= "<td valign='TOP'>".join('<br>', $contacts)."</td>";
		}
	}
	$message .= "</tr>";
	return $message;
}

function contactsArray($numbers, $labels) {
	$contacts = array();
	$primaryLine = '';
	foreach($labels as $k=>$label) {
		if(!$numbers[$k]) continue;
		if($k == 'email') $contacts[] = "{$numbers[$k]}";
		else {
			$stripped = strippedPhoneNumber($numbers[$k]);
			if(!$stripped) continue;  // primary (*) and text-enabled (T) may be the only info here
			$textEnabled = strpos("{$numbers[$k]}", 'T');
			$textEnabled = $textEnabled === 0 | $textEnabled == 1 ? '(T)' : '';
			if(strpos("{$numbers[$k]}", '*') === 0) {
				$primaryLine = "*$label$textEnabled $stripped";
			}
			else if($numbers[$k]) $contacts[] = "$label$textEnabled $stripped";
		}
	}
	if($primaryLine) {
		$contacts = array_reverse($contacts);
		$contacts[] = $primaryLine;
		$contacts = array_reverse($contacts);
	}
	return $contacts;
}

function generateStaleVisitsSMSBody($appointmentIds) {
	$appts = getStaleVisitsForIds($appointmentIds);
	$body = count($appointmentIds).strtoupper(" Overdue Visits\n");
	foreach($appts as $appt) {
		$body .= "\n";
		$body .= staleVisitsSMSBodyFor($appt);
		$body .=  "\n";
	}
	return $body;
}

function generateSeparateStaleVisitsSMSBodies($appointmentIds) {
	$appts = getStaleVisitsForIds($appointmentIds);
	foreach($appts as $appt) {
		$bodies[] = "Late:\n".staleVisitsSMSBodyFor($appt);
	}
	return $bodies;
}

function generateSeparateStaleVisitsSitterSMSPackets($appointmentIds) {
	$appts = getStaleVisitsForIds($appointmentIds);
	foreach($appts as $appt) {
		if($appt['providerptr'])
			$packets[] = 
				array('body' => "Late:\n".staleVisitsSitterSMSBodyFor($appt),
							'providerptr' => $appt['providerptr']);
	}
	return $packets;
}

function getStaleVisitsForIds($appointmentIds) {
	require_once "appointment-fns.php";
	require_once "request-fns.php";
	require_once "field-utils.php";
	$appts = fetchAssociationsKeyedBy(
		"SELECT tblappointment.*,
				CONCAT_WS(' ', c.fname, c.lname) as client,
				CONCAT_WS(', ', c.street1, c.city) as clientaddress,
				CONCAT_WS(' ', p.fname, p.lname) as provider,
				CONCAT_WS(' ', p.street1, p.city) as provideraddress,
				p.homephone as homephone,
				p.cellphone as cellphone,
				p.workphone as workphone,
				p.email as email,
				s.label as service
			FROM tblappointment
				LEFT JOIN tblclient c ON clientid = clientptr
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblservicetype s ON servicetypeid = servicecode
			WHERE appointmentid IN (".join(',', $appointmentIds).")
				ORDER BY date, timeofday", 'appointmentid');

	if($appts && mattOnlyTEST()) $arrivals = fetchKeyValuePairs(
		"SELECT appointmentptr, date 
			FROM tblgeotrack 
			WHERE appointmentptr IN (".join(',', $appointmentIds).") AND event = 'arrived'");
			
	return $appts;
}	

function staleVisitsSMSBodyFor(&$augmentedAppt) { // arg has clientaddress, provider
	require_once "appointment-fns.php";
	$appt = $augmentedAppt;
	$date = $appt['date'] == date('Y-m-d') ? '' : shortNaturalDate($appt['date']);
	$briefTOD = briefTimeOfDay($appt);
	if($date) $date = substr($date, 0, strrpos($date, '/')).' ';
	$body =  "$date{$briefTOD} {$appt['client']}\n";
	if($pets = $appt['pets']) {
		if($pets == 'All Pets') {
			require_once "pet-fns.php";
			$pets = getClientPetNames($appt['clientptr'], $inactiveAlso=false, $englishList=false);
			if(!$pets) $pets = $appt['pets'];  // no client pets, so reset
			$pets = truncatedLabel($pets, 20);
		}
		$body .= "$pets\n";
	}
	$serviceType = fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appt['servicecode']} LIMIT 1", 1);
	$serviceType = truncatedLabel($serviceType, 20);
	$body .= "$serviceType\n";
	$noAddress = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'overdueVisitManagerSMSExcludeAddress' LIMIT 1", 1);
	if(!$noAddress && $appt['clientaddress']) $body .=  "{$appt['clientaddress']}\n";
	$assigned = $appt['provider'] ? $appt['provider'] : 'Unassigned';
	$body .=  "ASSIGNED: $assigned\n";

	$contacts = contactsArray($appt, array('cellphone'=>'(c)', 'homephone'=>'(h)', 'workphone'=>'(w)'));
	if($contacts) $body .= join("\n", $contacts)."\n";
	$showAlternate = dbTEST('dogslife');
	if($showAlternate) {
		require_once "provider-fns.php";
		$altSitter = findAlternateSitter($appt['clientptr'], $ignoreSitter=$appt['providerptr'], $appt['date']);
		if(is_string($altSitter)) $body .= "NO FALLBACK: $altSitter";
		else if($altSitter) {
			$firstLine = "FALLBACK: {$altSitter['fname']} {$altSitter['lname']}\n";
			$contacts = contactsArray($altSitter, array('cellphone'=>'(c)', 'homephone'=>'(h)', 'workphone'=>'(w)'));
			if($contacts) $body .= $firstLine.join("\n", $contacts)."\n";
		}
	}
	return $body;
}

function staleVisitsSitterSMSBodyFor(&$augmentedAppt) { // arg has clientaddress, provider
	require_once "appointment-fns.php";
	$appt = $augmentedAppt;
	$date = $appt['date'] == date('Y-m-d') ? '' : shortNaturalDate($appt['date']);
	$briefTOD = briefTimeOfDay($appt);
	if($date) $date = substr($date, 0, strrpos($date, '/')).' ';
	$body =  "$date{$briefTOD} ".clientNameForSitterSMS($augmentedAppt)."\n";
	if($appt['clientaddress'] && !getPreference('provuisched_hideaddress') && !getPreference('overdueVisitSitterSMSExcludeAddress'))
		$body .=  "{$appt['clientaddress']}\n";
	$body .=  fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = {$appt['servicecode']} LIMIT 1", 1);
	return $body;
}
function clientNameForSitterSMS(&$appt) {
	$names = fetchFirstAssoc("SELECT fname, lname FROM tblclient WHERE clientid = '{$appt['clientptr']}' LIMIT 1", 1);
	require_once "preference-fns.php";
	$nameStyle = fetchPreference('provuisched_client');
	$nameStyle = $nameStyle ? $nameStyle : 'fullname';
	if($nameStyle || $nameStyle == 'fullname') 
		return "{$names['fname']} {$names['lname']}";
	else {
		$pets = $appt['pets'];
		if($pets == 'All Pets' || !$pets) {
			require_once "pet-fns.php";
			$pets = getClientPetNames($appt['clientptr'], $inactiveAlso=false, $englishList=false);
		}
		$pets = $pets ? $pets : 'no pets';
		return 
			$nameStyle == 'name/pets' ? "{$names['lname']}\n($pets)" : (
			$nameStyle == 'pets/name' ? "$pets\n({$names['lname']})" : (
			$nameStyle == 'fullname/pets' ? "{$names['fname']} {$names['lname']}\n($pets)" : '??'));
	}
}


/*

When enabled, this feature generates "Overdue Visits" system notifications.  Like other system notifications, 
these requests are accessible from the Home page and optionally emailed to staff.  This feature is managed primarily in
ADMIN > Preferences > General Business.

RULES:
1. Preference "Report overdue visits to managers" turns on reporting.
2. An overdue visit is considered "stale" if a certain number of minutes elapsed since the visit's end time and the 
visit is incomplete (not completed or canceled).  Preference "Minutes after visit end time a visit is considered overdue"
determines this grace period.
3. A stale visit is reported only once.  If it has been reported and the visit is marked incomplete later, it will not 
be reported again.
4. If a visit is marked complete before it can be marked stale and is later marked incomplete, it will be reported stale 
like any other overdue visit.
5. The preference "Days to consider overdue visits (default: '1' = 'today only')" determines whether to report only 
		visits scheduled today (1),
		visits scheduled back to yesterday (2),
		or to look back even further (3, 4, 5...)
6. The cron job searching for stale visits runs every five minutes, starting at 3 minutes pas the hour.

*/





