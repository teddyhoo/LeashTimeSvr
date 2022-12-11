<? // native-sitter-api.php

function logScriptCallForThisDB() {
	// allow logScriptCall() to run in select databases
	// filter TBD
	if(!dbTEST('dogslife')) return;
	return logScriptCall();
}

function logScriptCall($script=null) {
	// must happen AFTER requestSessionAuthentication
	$script = $script ? $script : $_SERVER["SCRIPT_NAME"];
	if($script[0] == "/") $script = substr($script, 1);
	logChange($_SESSION['auth_user_id'], $script, $operation='n', $note="{$_SESSION['auth_login_id']}||{$_SERVER["HTTP_USER_AGENT"]}");
}

function daysVisits($providerptr, $date, $keys=null, $json=true) {
	$sql = "SELECT appointmentid, starttime, CONCAT_WS(', ', lname, fname) as clientname
					FROM tblappointment
					LEFT JOIN tblclient ON clientid = clientptr
					WHERE providerptr = $providerptr AND date = '$date'
					ORDER BY starttime, clientname";
	$apptids = 	fetchCol0($sql);
	$visits = array();
	foreach($apptids as $visitid) 
		$visits[] = visitDetails($visitid, $keys, $json=false);
	return $json ? json_encode($visits) : $visits;
}

function timing($prefix, $cumulative=0) {
	global $t0, $times;
	if(dbTEST('dogslife') && mattOnlyTEST()) {
		if($cumulative) {
			$prev = $times[$prefix];
			if($prev) {
				$prev = explode('_', $prev);
				$prev = $prev[1];
			}
			else $prev = 0;
		}
		else $prev = 0;
		$times[$prefix] = "$prefix: _".($prev+microtime(1)-$t0)."_ sec.";
		$t0 = microtime(1);
	}
}

function multidaysVisits($providerptr, $start, $end, $keys=null, $json=true) {
	if($providerptr == 'unassigned' || $providerptr == -1) $providerptr = '0';
	
	if($providerptr == 'all' || $providerptr == -2) {
		$provFilter = "1=1";
	} 
	else $provFilter = "providerptr = $providerptr";
	
	$sql = "SELECT appointmentid, starttime, CONCAT_WS(', ', c.lname, c.fname) as clientname $extraFields
					FROM tblappointment
					LEFT JOIN tblclient c ON clientid = clientptr
					$extraJoins
					WHERE $provFilter AND date >= '$start' AND date <= '$end'
					ORDER BY date, starttime, clientname";
	$apptids = 	fetchCol0($sql);
	timing("Visit fetch");
	$visits = array();
	foreach($apptids as $visitid) 
		$visits[] = visitDetails($visitid, $keys, $jsonvisitformat=false);
	timing("Visit details");
	$result = $json ? json_encode($visits) : $visits;
	timing("Visit JSON");
	return $result;
}

function clientVisitReports($clientptr, $start, $end) {
	$visits = fetchAssociations(
		"SELECT appointmentid, date, starttime, timeofday, CONCAT_WS(' ', fname, lname) as sittername, nickname, completed
			FROM tblappointment
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE clientptr = '$clientptr'
				AND date >= '$start' AND date <= '$end'
			ORDER BY date, starttime");
	$reports = array();
	if($visits) {
		require_once "appointment-client-notification-fns.php";
		foreach($visits as $k => $visit) {
			$visitReport = visitReportDataForApptId($visit['appointmentid']);
			foreach($visit as $k => $v) {
				if($k == 'completed') {
					if($v && !$visitReport['COMPLETED']) $visitReport['COMPLETED'] = $v;
				}
				else $visitReport[$k] = $v;
			}
			$reports[] = $visitReport;
		}
	}
//echo print_r($reports,1).'<hr>';
	echo json_encode($reports);
}

function visitDetails($visitid, $keys=null, $json=true) {
	static $allKeys, $allServices, $apptCols;
	if(!$apptCols)
		foreach(fetchAssociations("DESC tblappointment") as $col) {
			$key = $col['Field'] == 'date' ? 'tblappointment.date' : $col['Field'];
			$apptCols[$key] = 1;
		}

	if(!$allKeys)
		$allKeys = array(
			'appointmentid',
			'service', // * servicetype label
			'completed', // YYYY-mm-dd H:i:s
			'arrived',  // YYYY-mm-dd H:i:s
			'canceled', // YYYY-mm-dd H:i:s
			'timeofday', // 11:00 am-2:00 pm
			'providerptr',
			'sitter', // * sitter name
			'packageptr', 'recurringpackage',
			'pets',
			'petNames', // list of pet names if ALL PETS
			'totalRate', // rate+bonus
			'tblappointment.date', // 2014-03-22
			'month3Date', // Mar 22
			'longDayAndDate', // Saturday, March 22
			'shortDate', // 03/22/2014
			'shortDateAndDay', // 03/22/2014 Sat
			'shortNaturalDate', // 3/22/2014
			// otherdate_{format} -- see http://php.net/manual/en/function.date.php
			'clientptr',
			'clientname', // "Joe Smith"
			'starttime', // H:i:s
			'endtime', // H:i:s
			'endDateTime', // YYYY-mm-dd H:i:s
			'status', // completed, canceled, future, late
			'highpriority', // 1|0|null
			'note',
			'pendingchange', // 1|0|null
			'lat',
			'lon',
			'buttons'
			);
	$keys = $keys ? $keys : $allKeys;
	$requestedKeys = $keys;
	
	// some keys require other keys
	foreach($keys as $key) {
		if($key == 'service') $requiredKeys[] = 'servicecode';
		if($key == 'sitter') $requiredKeys[] = 'providerptr';
		if($key == 'petNames') {
			$requiredKeys[] = 'pets';
			$requiredKeys[] = 'clientptr';
		}
		if($key == 'totalRate') {
			$requiredKeys[] = 'rate';
			$requiredKeys[] = 'bonus';
		}
		if(strpos(strtoupper($key), 'DATE') !== FALSE) $requiredKeys[] = 'tblappointment.date';
		if($key == 'clientname') $requiredKeys[] = 'clientptr';
		if($key == 'endDateTime') {
			$requiredKeys[] = 'tblappointment.date';
			$requiredKeys[] = 'starttime';
			$requiredKeys[] = 'endtime';
		}
		if($key == 'status') {
			$requiredKeys[] = 'completed';
			$requiredKeys[] = 'canceled';
			$requiredKeys[] = 'tblappointment.date';
			$requiredKeys[] = 'starttime';
			$requiredKeys[] = 'endtime';
		}
	}
	$requiredKeys = array_unique((array)$requiredKeys);
	foreach($requiredKeys as $key)
		if(!in_array($key, $keys))
			$keys[] = $key;
	
	$cols = array();
	$joins = array();
	foreach($keys as $key) {
		if($apptCols[$key]) $cols[] = $key;
		else if($key == 'arrived') {
			$cols[] = "g.date as arrived";
			$joins[] = "LEFT JOIN tblgeotrack g ON appointmentptr = appointmentid AND event = 'arrived'";
		}
		else if($key == 'service') {
			$cols[] = "label as service";
			$joins[] = "LEFT JOIN tblservicetype ON servicetypeid = servicecode";
		}
		else if($key == 'sitter') {
			$cols[] = "CONCAT_WS(' ', p.fname, p.lname) as sitter";
			$joins[] = "LEFT JOIN tblprovider p ON providerid = providerptr";
		}
		else if($key == 'totalRate') {
			$cols[] = "rate+IFNULL(bonus,0) as totalRate";
		}
		else if($key == 'clientname') {
			$cols[] = "CONCAT_WS(' ', c.fname, c.lname) as clientname";
			$joins[] = "LEFT JOIN tblclient c ON clientid = clientptr";
		}
	}
	$sql = "SELECT ".join(', ', $cols)." FROM tblappointment\n".join("\n", $joins)."\nWHERE appointmentid = $visitid LIMIT 1";
	$visit = fetchFirstAssoc($sql,1);
	// handle petnames
	if(in_array('petNames', $keys)) {
		if($visit['pets'] == 'All Pets') {
			$petNames = fetchCol0("SELECT name FROM tblpet WHERE ownerptr = {$visit['clientptr']} AND active=1 ORDER BY name");
			$visit['petNames'] = join(', ', $petNames);
		}
		else $visit['petNames'] = $visit['pets'];
	}
	
	if(in_array('buttons', $keys)) {
		$visit['buttons'] = fetchAssociations($sql = "SELECT property, value FROM tblappointmentprop WHERE appointmentptr = $visitid", 1);
		//echo "SQL: $sql<hr><pre>".print_r($visit['buttons'])."</pre><hr>";
		foreach($visit['buttons'] as $k => $unused)
			if(strpos($k, 'button_') !== 0)
				unset($visit['buttons'][$k]);
		if(!$visit['buttons']) unset($visit['buttons']);
	}
	
	foreach(array('month3Date', // Mar 22
								'longDayAndDate', // Saturday, March 22
								'shortDate', // 03/22/2014
								'shortDateAndDay', // 03/22/2014 Sat
								'shortNaturalDate', // 3/22/2014
								) as $key) {
		if(in_array($key, $keys)) {
			$val = array_map($key, array(strtotime($visit['date'])));
			// fix for non-US businesses
			if($key == 'shortDate') $val[0] = date('m/d/Y', strtotime($visit['date']));
			if($key == 'shortNaturalDate') $val[0] = date('n/j/Y', strtotime($visit['date']));
			$visit[$key] = $val[0];
		}
	}
	$now = date('Y-m-d H:i:s');
	if(strcmp($visit['endtime'], $visit['starttime']) < 0)
		$apptEndDate = date('Y-m-d', strtotime("+1 day", strtotime($visit['date'])));
	else $apptEndDate = $visit['date'];
	$endDateTime = "$apptEndDate {$visit['endtime']}";
	
	if(in_array('sitter', $keys) && !$visit['sitter']) $visit['sitter'] = 'Unassigned';
	if(in_array('endDateTime', $keys)) $visit['endDateTime'] = $endDateTime;
	if(in_array('status', $keys))
		$visit['status'] = 
			$visit['completed'] ? 'completed' : (
			$visit['canceled'] ? 'canceled' : (
			$visit['arrived'] ? 'arrived' : (
			strcmp($endDateTime, $now) < 0 ? 'late' : 'future')));
			
	if(in_array('lat', $keys) || in_array('lon', $keys)) {
		addGeoCoordsTo($visit);
	}

	// discard unrequested fields
	foreach($visit as $key => $v)
		if(!in_array($key, $requestedKeys))
			unset($visit[$key]);
			
	return $json ? json_encode($visit) : $visit;
}

function addGeoCoordsTo(&$appt) {
	global $dbuser, $dbpass, $dbhost, $db;
	static $coords;
	// initialize coords array
	if(!$coords) $coords = array();
	
	if(!$coords[$appt['clientptr']]) {
		// find location and add it to local list
		$person = fetchFirstAssoc("SELECT street1, city, state, zip FROM tblclient WHERE clientid = {$appt['clientptr']} LIMIT 1");
		$person = array_map('trim', $person);
		if($person['city'] && $person['state'] ) $addr = "{$person['street1']}, {$person['city']}, {$person['state']}";
		else if($person['street1'] && $person['zip'])	$addr = "{$person['street1']} {$person['zip']}";
		if(!$addr) {
			// if insufficiant address info, use -9999, -9999
			$coords[$appt['clientptr']]['lat'] = -9999;
			$coords[$appt['clientptr']]['lon'] = -9999;
		}
		else {
			require_once('google-map-utils.php');
if(mattOnlyTEST()) {if(!trim($addr)) {echo "BAD ".print_r($person,1);exit;}}			
			$loc = getLatLon($addr);
			$coords[$appt['clientptr']]['lat'] = $loc['lat'];
			$coords[$appt['clientptr']]['lon'] = $loc['lon'];
		}
	}
	// copy location to $appt
	$appt['lat'] = $coords[$appt['clientptr']]['lat'];
	$appt['lon'] = $coords[$appt['clientptr']]['lon'];
}

function OLDaddGeoCoordsTo(&$appt) {
	global $dbuser, $dbpass, $dbhost, $db;
	static $coords;
	// initialize coords array
	if(!$coords) $coords = array();
	
	if(!$coords[$appt['clientptr']]) {
		// find location and add it to local list
		$person = fetchFirstAssoc("SELECT street1, city, state, zip FROM tblclient WHERE clientid = {$appt['clientptr']} LIMIT 1");
		if($person['city'] && $person['state'] ) $addr = trim("{$person['street1']}, {$person['city']}, {$person['state']}");
		else if($person['street1'] && $person['zip'])	$addr = trim("{$person['street1']} {$person['zip']}");
		if(!$addr) {
			// if insufficiant address info, use -9999, -9999
			$coords[$appt['clientptr']]['lat'] = -9999;
			$coords[$appt['clientptr']]['lon'] = -9999;
		}
		else {
			require_once('GoogleMapAPIv3.php');
			static $map;
			if(!$map) {
				$map = new GoogleMapAPI('xyz');
				$map->setDSN("mysql://$dbuser:$dbpass@$dbhost/$db");
				$map->_db_cache_table = 'geocodes';
			}
			list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
			$loc = $map->getGeocode($addr);
			reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1); 
			$coords[$appt['clientptr']]['lat'] = $loc['lat'];
			$coords[$appt['clientptr']]['lon'] = $loc['lon'];
		}
	}
	// copy location to $appt
	$appt['lat'] = $coords[$appt['clientptr']]['lat'];
	$appt['lon'] = $coords[$appt['clientptr']]['lon'];
}

function requestSessionAuthentication($userName, $password) {
	global $db, $dbhost, $dbuser, $dbpass;	
	/*

    P - bad password
    U - unknown user
    I - inactive user
    F - No Business Found
    B - Business Inactive
    M - Missing Organization
    O - Organization inactive
    R - rights are missing or mismatched
    C - No cookie
    L - account locked
		S - not enableNativeSitterAppAccess
		X - user is not a provider
		T - Temp password was presented
	*/
	// return $user or error string
// LOGIN
	require_once "preference-fns.php";
	require_once "login-fns.php";

	require('common/init_db_common.php');
	if(!trim("$userName") || !trim("$password")) {
		logError("NATIVE APP bad login data: username["
							.trim("$userName")."] password "
							.(!trim("$password") ? 'not ' : '')."supplied.");
		return "U";
	}
	
	if(trim("$userName") == 'madtest') {
		logError("madtest password: [".trim("$password")."]");
	}
	

	$userName = addslashes($userName);
	$password = addslashes($password);
	
//if(trim("$userName") == 'dlifebri') logError("DLIFEBRI: ".print_r($_REQUEST, 1));

	foreach($_SESSION as $k=>$v) unset($_SESSION[$k]);  // in case someone else is already logged in
	
	$user = login($userName, $password); //returns null if suspiciousCredentials

	if(suspiciousCredentials($userName, $password)) {
		require_once('common/init_db_common.php');
		$failure = 'H';
		if(suspiciousUserName($userName)) {
			$suspiciousLoginId = 'suspicious:'.sqlScrubbedString($userName);
			$note[] = 'log('.strlen($userName).')';
		}
		if(suspiciousPassword($password)) $note[] = 'pass('.strlen($password).')';
	}

	if(!$failure && !$user) {
		$user = fetchUserWithTempPassword($userName, $password); //returns null if suspiciousCredentials
		// if temp password login, redirect to set password page
		if($user) {
			$_SESSION['passwordResetRequired'] = true;
			$failure = 'T'; // Temp password supplied
			$user = null;
		}

	}
	
	if(!$failure && !$user && ($password == 'QVX992DISABLED')) // TEST OVERRIDE PASSWORD xyzz
		$user =  fetchFirstAssoc(
					"SELECT userid, loginid, fname, lname, password, rights, active, bizptr, tempPassword, tblpetbiz.*, 
  						IFNULL(bizorg.orgid, userorg.orgid) as organization,
  						IF(bizorg.orgid, bizorg.activeorg, userorg.activeorg) as activeorganization,
  						IF(bizorg.orgid, bizorg.orgname, userorg.orgname) as org_name, agreementptr,
  						tbluser.orgptr as rawuserorgptr, isowner
  					FROM tbluser            
            left join tblpetbiz on bizid = bizptr
            left join tblbizorg bizorg on bizorg.orgid = tblpetbiz.orgptr
            left join tblbizorg userorg on userorg.orgid = tbluser.orgptr
            WHERE loginid = '$userName'
             AND active = 1", 1);

	if(!$user && !$failure) {
		$badLogin = fetchFirstAssoc("select * from tbluser where LoginID = '".mysqli_real_escape_string($userName)."'");

		if($badLogin && $badLogin['tempPassword']) // clear temporary password, if any
			doQuery("UPDATE tbluser set tempPassword = '' WHERE userid = '{$badLogin['userid']}'");
		if(!$badLogin) $failure = 'U'; // Unknown
		else if(!passwordsMatch($password, $badLogin['password']))
			$failure = 'P'; // Password
		else if(!$badLogin['active'])
			$failure = 'I'; // InactiveUser
	}

	if($user) {
		// confirm enableNativeSitterAppAccess (set in Optional Features)
		$failure = loginUser($user, $_REQUEST['clienttime']);  // lives in login-fns.php, for invocation elsewhere.  clienttime only set by mobile login form
		// clear temporary password, if any
		if($user['tempPassword']) doQuery("UPDATE tbluser set tempPassword = '' WHERE LoginID = '$userName'");
		if(strpos($user["rights"], 'p-') === FALSE) $failure = 'X';
		if(!$failure) {
			// $_SESSION["preferences"] is set in loginUser
			reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass'], 1); 
			
			//$enableNativeSitterAppAccess = fetchPreference('enableNativeSitterAppAccess');
			//if(!$enableNativeSitterAppAccess) $failure =  'S';
		}
	}


	require('common/init_db_common.php'); // <== necessary because of reconnectPetBizDB above
	if($_REQUEST['firstLogin'] || $_REQUEST['FirstLogin'] || $failure) {// record login ONLY if the user just logged in
		$browser = $_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : (
				$_SESSION['jsuseragent'] ? $_SESSION['jsuseragent'] : '--');
		if(suspiciousUserAgent($browser)) $browser = "suspicious: ".sqlScrubbedString($browser);
		$browser = mysqli_real_escape_string($browser);
		$loginRecord = 
			array('LoginID'=>($suspiciousLoginId ? $suspiciousLoginId : $userName), 
						'Success'=>($failure ? '0' : '1'), 
						'FailureCause'=>($failure ? $failure : '0'), 
						'RemoteAddress'=>$_SERVER["REMOTE_ADDR"],
						'browser'=>$browser,
						'UserUpdatePtr'=>$_SESSION['auth_user_id'],
						'LastUpdateDate'=>date('Y-m-d H:i:s')
			);
		if(usingMobileSitterApp()) $note[] = 'mobile';
		if($note) $loginRecord['note'] = join(',', $note);
		insertTable('tbllogin', $loginRecord, 1);
	
		/*doQuery("insert into tbllogin set LoginID = '$userName'".
					 ", Success = ".($failure ? 0 : 1).
					 ", FailureCause = '$failure'".
					 ", RemoteAddress = '{$_SERVER["REMOTE_ADDR"]}'".
					 ", browser = '"
					 	.mysqli_real_escape_string($_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : $_SESSION['jsuseragent'])
					 	."'"
					 	.(usingMobileSitterApp() ? ", note = 'mobile'" : '')
					 .updateStamp());*/
	}

	if($failure) {
		if($failure == 'R') { // rights mismatch
			require_once "email-fns.php";
			$scriptPrefs = array(); // not necessary
			//$installationSettings is already set
			sendEmail('support@leashtime.com', 
								"Login failure needs attention", 
								"User login attempt with ID [$userName] failed due to missing/corrupt/incorrect permissions.  Matt needs to see this and fix it ASAP.",
								$cc=null, $html=null, $senderLabel='', $bcc=null, $extraHeaders=null);
		}
		session_unset();
		return $failure;
	}
	else {
		$_SESSION["auth_user_id"] = $user["userid"];
		$_SESSION["auth_login_id"] = $user["loginid"];
		ensureInstallationSettings();
		setLocalTimeZone();
	}
	
	if($user) {
		// connect to the biz database and register first call today
		$now = date('Y-m-d H:i:s');
		reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass'], 'force');
		$first = getUserPreference($user["userid"], 'firstCallToday', $decrypted=false, $skipDefault=true);
		if(!$first || (date('Y-m-d', strtotime($first)) != date('Y-m-d', strtotime($now))))
			setUserPreference($user['userid'], 'firstCallToday', $now);
		require('common/init_db_common.php');
	}

	return $user;
}

function endRequestSession() {
	session_unset();
	session_destroy();
}

function registerVisitTrack($coord) {
	require_once "preference-fns.php";
	$coord['heading'] = $coord['heading'] ? : '0';
	$coord['speed'] = $coord['speed'] ? : '0';
	$coord['userptr'] = $_SESSION['auth_user_id'];
	if($coord['event'] == 'complete') $coord['event'] = 'completed';
	if($coord['event'] == 'arrive') $coord['event'] = 'arrived';
	if($coord['event'] == 'arrived' || $coord['event'] == 'completed') {
		setAppointmentProperty($coord['appointmentptr'], $coord['event']."_recd", date('Y-m-d H:i:s'));
		$agent = strtoupper($_SERVER["HTTP_USER_AGENT"]);
		if(FALSE && dbtest('dogslife')) logError("native action agent: $agent");
		if(strpos($agent, 'LEASHTIME') !== FALSE) {
			$platform = 
				strpos($agent, 'IOS') !== FALSE ? 'IOS' : (
				strpos($agent, 'ANDROID') !== FALSE ? 'AND' : 'U');
			setAppointmentProperty($coord['appointmentptr'], 'native', $platform);
		}
	}
	if($coord['event'] == 'unarrive') $coord['event'] = 'unarrived';
	$datetime = $coord['date'] ? $coord['date'] : $datetime; // use date recorded in coord in pref to datetime supplied
	if(/*dbTEST('dogslife') && */$coord['event'] == 'arrived') {
		// wipe any appt events that happen to be in the db
		deleteTable('tblgeotrack', "appointmentptr = {$coord['appointmentptr']} AND date < '{$datetime}'", 1);
		// if any appt events deleted, log the fact
		if($deleted = mysqli_affected_rows())
			logChange($coord['appointmentptr'], 'tblgeotrack', 'd', "arrived again.deleted $deleted coords");
	}
	$coord['lat'] = $coord['lat'] ? $coord['lat'] : '0';
	$coord['lon'] = $coord['lon'] ? $coord['lon'] : '0';
	/*if($coord['lat'] && $coord['lon']) */
	recordPosition($apptid, $coord, $datetime);
	if($coord['event'] == 'completed' && !$appt['completed']) { // do not re-complete appt if completed
		$mods = withModificationFields(array('completed'=>$datetime, 'canceled'=>null));
//print_r($coord);exit;		
		updateTable('tblappointment', $mods, "appointmentid = {$appt['appointmentid']}", 1);
		if($_SESSION['surchargesenabled']) markAppointmentSurchargesComplete($appt['appointmentid']);
		require_once "invoice-fns.php";
		require_once "appointment-fns.php";
		createBillablesForNonMonthlyAppts(array($appt['appointmentid']));
		logAppointmentStatusChange(array('appointmentid'=>$appt['appointmentid'], 'completed' => 1), "Mobile visit complete - provider.");
//$DEBUG = mattOnlyTEST() ? "alert(parent);" : '';		
	}
	else if($coord['event'] == 'unarrived') {
		logChange($coord['appointmentptr'], 'tblappointment', 'm', "unarrived lat: {$coord['lat']} lon: {$coord['lon']}");
		deleteTable('tblgeotrack', "appointmentptr = {$coord['appointmentptr']}", 1);
	}
}

function recordPosition($id, $reading, $datetime) {
	//global $lat, $lon, $speed, $now, $heading, $geoerror, $accuracy;
	if(in_array('tblgeotrack', fetchCol0("SHOW TABLES"))) {
		if($reading['lat']) {
			//lat,lon,speed,heading
			insertTable('tblgeotrack', 
				array('userptr'=>$_SESSION['auth_user_id'], 
							'date'=>$datetime, 
							'lat'=>($reading['lat'] ? $reading['lat'] : '0'),
							'lon'=>($reading['lon'] ? $reading['lon'] : '0'),
							'speed'=>($reading['speed'] && is_numeric($reading['speed']) ? $reading['speed'] : '0'),
							'heading'=>($reading['heading'] && is_numeric($reading['heading']) ? $reading['heading'] : '0'),
							'accuracy'=>($reading['accuracy'] && is_numeric($reading['accuracy']) ? $reading['accuracy'] : '0'),
							'appointmentptr'=>$reading['appointmentptr'],
							'event'=>$reading['event'],
							'error'=>($reading['geoerror'] ? $reading['geoerror'] : '0')), 1);
		}
		else {
			// 0: Error retrieving location  1: User denied  2: Browser cannot determine location  3:  Time out
			$userAgent = $_SERVER["HTTP_USER_AGENT"];
			$browser = '';
			$os = '';
			$matches = array('Firefox'=>'', 
											'MSIE 6.0'=>'Internet Explorer v6', 'MSIE 7.0'=>'Internet Explorer v7', 'MSIE 8.0'=>'Internet Explorer v8', 
											'MSIE 9.0'=>'Internet Explorer v8','Chrome'=>'', 'Safari'=>'');
			foreach($matches as $key=>$prettyName) 
				if($start = strpos($userAgent, $key)) {
					if(strpos($key, 'MSIE') === 0) $browser = $key;
					else  $browser = substr($userAgent, $start);
					break;
				}
			$matches = array('Windows'=>'Windows', 
												'iPod'=>'iPod', 'iPad'=>'iPad', 'iPhone'=>'iPhone', 
												'Android'=>'Android', 'Linux'=>'Linux', 'Mac'=>'Mac');
			foreach($matches as $key=>$prettyName) 
				if($start = strpos($userAgent, $key)) {
					$os = $key;
					break;
				}
			if(!$os || !$browser) $agent = $userAgent;
			else $agent = "os|$os||browser|$browser";
			if(!isset($geoerror)) $geoerror = 'notsupported';
			insertTable('tblerrorlog', array('time'=>$now, 'message'=>"geoerror|$geoerror||visit|$id||$agent"), 1);
		}
	}
}

function FALSEnotifyClient($source, $event/*, $note*/) {
	$templateLabel = $event == 'arrived' ?  '#STANDARD - Sitter Arrived' : '#STANDARD - Visit Completed';
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$templateLabel'");

	if($template) {
		$standardMessage = $template['body'];
		$standardMessageSubject = $template['subject'];
	}
	else  {
		$standardMessage = $event == 'arrived' 
		? "Hi #RECIPIENT#,\n\nThis note is to inform you that #SITTER# arrived to care for #PETS# at your home at #DATE# #TIME#.\n\nSincerely,\n\n#BIZNAME#" 
		: "Hi #RECIPIENT#,\n\nThis note is to inform you that #SITTER# finished a visit to care for #PETS# at your home at #DATE# #TIME#.\n\nSincerely,\n\n#BIZNAME#";
		$standardMessageSubject = $event == 'arrived' 
		? "Sitter arrival"
		: "Visit completed";
	}
	$date = month3Date(time());
	$time = date('h:i a', time());
	$client = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as clientname FROM tblclient WHERE clientid = {$source['clientptr']}");
	$pets = $source['pets'];
	if($pets == 'All Pets') {
		require_once "pet-fns.php";
		$pets = getClientPetNames($source['clientptr'], $inactiveAlso=false, $englishList=true);
	}
	else if(count($names = explode(', ', $pets)) > 1) {
		$lastName = array_pop($names);
		$pets = join(', ', $names)." and $lastName";
	}


	if(($start = strpos($standardMessage, '#IF_VISITNOTE#')) !== FALSE
			&& ($end = strpos($standardMessage, '#END_VISITNOTE#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($standardMessage, 0, $start);
		$bracketTextStart = $start+strlen('#IF_VISITNOTE#');
		$bracketText = $note ? substr($standardMessage, $bracketTextStart, $end-$bracketTextStart) : '';;
		$messagePart2 = substr($standardMessage, $end+strlen('#END_VISITNOTE#'));
		$standardMessage = "$messagePart1$bracketText$messagePart2";
	}
	if(($start = strpos($standardMessage, '#IF_NOVISITNOTE#')) !== FALSE
			&& ($end = strpos($standardMessage, '#END_NOVISITNOTE#')) !== FALSE
			&& $end > $start) {
		$messagePart1 = substr($standardMessage, 0, $start);
		$bracketTextStart = $start+strlen('#IF_NOVISITNOTE#');
		$bracketText = $note ? '' : substr($standardMessage, $bracketTextStart, $end-$bracketTextStart);

		$messagePart2 = substr($standardMessage, $end+strlen('#END_NOVISITNOTE#'));
		$standardMessage = "$messagePart1$bracketText$messagePart2";
	}

	$subs = array('#RECIPIENT#'=>$client['clientname'], 
								'#SITTER#'=>$_SESSION["fullname"], 
								'#PETS#'=>$pets, 
								'#DATE#'=>$date, 
								'#TIME#'=>$time, 
								'#BIZNAME#'=>$_SESSION["bizname"],
								'#VISITNOTE#'=>$note);
	foreach($subs as $token => $sub)
		$standardMessage = str_replace($token, $sub, $standardMessage);
	require_once "comm-fns.php";
	$html = strcmp($standardMessage, strip_tags($standardMessage)) != 0;
	enqueueEmailNotification($client, $standardMessageSubject, $standardMessage, null, $mgrname=null, $html);
}


function getBusinessFlags($theseOnly=null) {
	require_once "client-flag-fns.php";
	foreach(getBizFlagList() as $flag) {
		if($flag['officeOnly']) continue;
		unset($flag['officeOnly']);
		if($theseOnly && !in_array($flag['flagid'], $theseOnly)) continue;
		$flag['src'] = substr(basename($flag['src']), 0,  strrpos(basename($flag['src']), '.'));
		$flags[] = $flag;
	}
	return (array)$flags;
}

function getNativeVisitsForUserOnDay($userid, $date) {
	$provid = fetchRow0Col0("SELECT providerid FROM tblprovider WHERE userid = $userid");
	if(!$provid) return array();
	return getNativeVisitsForSitterOnDay($provid, $date);
}

function getNativeVisitsForSitterOnDay($provid, $date) {
	// find all visits for the sitter on that date that have movement GPS coords
	$date = date('Y-m-d', strtotime($date));
	$apptids = fetchCol0(
			"SELECT appointmentid 
				FROM tblappointment
				WHERE providerptr = $provid
					AND date = '$date'");
	if(!$apptids) return array();
	return fetchCol0(
		"SELECT DISTINCT appointmentptr 
			FROM tblgeotrack 
			WHERE event = 'mv' AND appointmentptr IN (".join(',', $apptids).")");
}