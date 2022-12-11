<? // routing-api.php

function endRequestSession() {
	session_unset();
	session_destroy();
}

function apiLogin($userName, $password, $userRole='I') {
	require_once "login-fns.php";
	require('common/init_db_common.php');
	require('common/init_session.php');
	require('preference-fns.php');

	if(!trim("$userName") || !trim("$password")) {
		logError("NATIVE APP bad login data: username["
							.trim("$userName")."] password "
							.(!trim("$password") ? 'not ' : '')."supplied.");
		return array('failure'=>"U");
	}
	
	if(trim("$userName") == 'madtest') {
		logError("madtest password: [".trim("$password")."]");
	}
	

	$userName = addslashes($userName);
	$password = addslashes($password);
	
//if(trim("$userName") == 'dlifebri') logError("DLIFEBRI: ".print_r($_REQUEST, 1));

	foreach($_SESSION as $k=>$v) unset($_SESSION[$k]);  // in case someone else is already logged in
	
	$user = login($userName, $password);
	if(!$user) 
	if(!$user && !$failure && ($password == 'QVX992DISABLED')) // TEST OVERRIDE PASSWORD xyzz
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
		else if($badLogin['active'])
			$failure = 'I'; // InactiveUser
	}

	if($user) {
		// confirm enableNativeSitterAppAccess (set in Optional Features)
		$failure = loginUser($user, $_REQUEST['clienttime']);  // lives in login-fns.php, for invocation elsewhere.  clienttime only set by mobile login form
		// clear temporary password, if any
		if($user['tempPassword']) doQuery("UPDATE tbluser set tempPassword = '' WHERE LoginID = '$userName'");
		$userRole = strtoupper($userRole);
		//echo "{$user["rights"]}: $userRole<p>";
		if(strpos(strtoupper($user["rights"]), "{$userRole}") === FALSE) $failure = 'X';
		if(!$failure) {
			// $_SESSION["preferences"] is set in loginUser
			//reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass'], 1); 
			
			//$enableNativeSitterAppAccess = fetchPreference('enableNativeSitterAppAccess');
			//if(!$enableNativeSitterAppAccess) $failure =  'S';
		}
	}


	require('common/init_db_common.php'); // <== do not know why this is necessary

	if($_REQUEST['firstLogin'] || $_REQUEST['FirstLogin'] || $failure) // record login ONLY if the user just logged in
		doQuery("insert into tbllogin set LoginID = '$userName'".
					 ", Success = ".($failure ? 0 : 1).
					 ", FailureCause = '$failure'".
					 ", RemoteAddress = '{$_SERVER["REMOTE_ADDR"]}'".
					 ", browser = '"
					 	.mysqli_real_escape_string($_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : $_SESSION['jsuseragent'])
					 	."'"
					 	.(usingMobileSitterApp() ? ", note = 'mobile'" : '')
					 .updateStamp());

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
		return array('failure'=>$failure);
	}
	else {
		$_SESSION["auth_user_id"] = $user["userid"];
		$_SESSION["auth_login_id"] = $user["loginid"];
		ensureInstallationSettings();
		setLocalTimeZone();
	}
	
	// connect to the biz database and register first call today
	$now = date('Y-m-d H:i:s');
	reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass'], 'force');
	$first = getUserPreference($user["userid"], 'firstCallToday', $decrypted=false, $skipDefault=true);
	if(!$first || (date('Y-m-d', strtotime($first)) != date('Y-m-d', strtotime($now))))
		setUserPreference($user['userid'], 'firstCallToday', $now);
	require('common/init_db_common.php');

	return $user;
}

function connectToBusiness($userName, $password) {
	$userOrFailure = apiLogin($userName, $password, $userRole='I');

	if($failure = $userOrFailure['failure']) {
		echo json_encode($userOrFailure);
		exit;
	}

	$user = $userOrFailure;

	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
}

function getSitterTimeOff($userName, $password, $start, $end, $bySitter=true) {
	connectToBusiness($userName, $password);
	
	require_once "provider-fns.php";
	
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	for($day = strtotime($start); $day <= strtotime($end); $day = strtotime("+1 day", $day)) {
		$date = date('Y-m-d', $day);
		$provids = providersOffThisDay($date);
		foreach($provids as $provid) {
			$timesOff = timesOffThisDay($provid, $date, $completeRecords=false);
			foreach($timesOff as $i => $to) if(!$to) $timesOff[$i] = 'allday';
			if($bySitter)
				$timeoff[$provid][$date] = $timesOff;
			else
				$timeoff[$date][$provid] = $timesOff;
		}
	}
	return $timeoff;
}



function getSitters($userName, $password, $activeOnly=1) {
	connectToBusiness($userName, $password);
	
	require_once('GoogleMapAPIv3.php');
	$activeOnly = $activeOnly ? "WHERE active = 1" : "";
	$sitters = fetchAssociations(
		"SELECT providerid as sitterid, IFNULL(nickname, CONCAT_WS(' ', s.fname, s.lname)) as sitter, active,
				street1, city, state, zip
		 FROM tblprovider s $activeOnly", 1);
	if(!$sitters) return null;
	foreach($sitters as $providerptr => $provider) {
		$addr = googleAddress($provider);
		$latLon = getLatLon($addr);
		foreach((array)$latLon as $k => $v)
			$sitters[$providerptr][$k] = $v;
	}
	return $sitters;
}

function getSitterVisits($userName, $password, $start, $end, $includeUnassigned=0, $maskClientNames=true) {
	connectToBusiness($userName, $password);
	
	require_once('GoogleMapAPIv3.php');
	
	$start = date('Y-m-d', strtotime($start));
	$end = date('Y-m-d', strtotime($end));
	
	$sitterclause = $includeUnassigned ? '' : "AND providerptr != 0";
	$visits = fetchAssociations(
		"SELECT appointmentid, timeofday, servicecode, label as service, IFNULL(hours, '00:00') as hours, 
						if(completed IS NOT NULL, 'completed', 
							if(canceled IS NOT NULL, 'canceled', 'incomplete')) as status, completed,
						clientptr as clientid, CONCAT_WS(' ', c.fname, c.lname) as client,
						providerptr as sitterid, IFNULL(nickname, CONCAT_WS(' ', s.fname, s.lname)) as sitter
		 FROM tblappointment
		 LEFT JOIN tblprovider s ON providerid = providerptr
		 LEFT JOIN tblclient c ON clientid = clientptr
		 LEFT JOIN tblservicetype ON servicetypeid = servicecode
		 WHERE date >= '$start' AND date <= '$end' $sitterclause AND canceled IS NULL", 1);
	if(!$visits) return null;
	
	foreach($visits as $i => $v) {
		$clients[] = $v['clientid'];
		$visits[$i]['arrived'] = 
			fetchRow0Col0(
				"SELECT date 
					FROM tblgeotrack 
					WHERE appointmentptr = {$v['appointmentid']}
						AND event = 'arrived'");
		if(!$visits[$i]['arrived']) unset($visits[$i]['arrived']);
	}
	
	$clients = fetchAssociationsKeyedBy(
			"SELECT clientid, street1, city, state, zip FROM tblclient WHERE clientid IN (".join(',', array_unique($clients)).")", 
			'clientid', 1);
	foreach($clients as $clientid => $client) {
		$addr = googleAddress($client);
		$latLon = getLatLon($addr);
		if($maskClientNames && $latLon) $clients[$clientid]['client'] = "@$clientid";
		foreach((array)$latLon as $k => $v)
			$clients[$clientid][$k] = $v;
	}
	
	
	foreach($visits as $vid => $visit) {
		$addInfo = $clients[$visit['clientid']];
		if($addInfo) foreach($addInfo as $k => $v)
			$visits[$vid][$k] = $v;
//echo "<br>$vid: ".print_r($visit, 1);
	}
	return $visits;
}



function googleAddress($person) {
	if($person['city'] && $person['state'] ) $addr = trim("{$person['street1']}, {$person['city']}, {$person['state']}");
	else 	$addr = trim("{$person['street1']} {$person['zip']}");
	return $addr;
}

function getLatLon($toAddress) {
	global $dbhost, $db, $dbuser, $dbpass;
	//list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	static $map;
	global $googleMapAPIKey, $dbuser, $dbpass, $dbhost, $db;
	if(!$map) {
    $map = new GoogleMapAPI('xyz');
    $map->setDSN("mysql://$dbuser:$dbpass@$dbhost/$db");
    $map->_db_cache_table = 'geocodes';
	}
	$coords = $map->getGeocode($toAddress);
	//reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1); 
	return $coords;
}

