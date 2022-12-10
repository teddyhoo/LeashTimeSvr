<?
// init_session.php

bootUpSession();
//$_SESSION['ccenabled'] = $_SESSION['auth_login_id'] == 'dlife';
/***************************
unless session_set_cookie_params(seconds) is called, cookie remains until browser is closed
session.gc_maxlifetime	default: 1440 seconds - session data may be gc'd after this time


****************************/
function bootUpSession() {
	global $max_idle_minutes, $max_idle_seconds, $installationSettings, $mein_host, $returnURL, $googleMapAPIKey;
	setNewIncludePath();
	$max_idle_minutes = 8 * 60;
	$max_idle_seconds = $max_idle_minutes * 60;
	//session_set_cookie_params($max_idle_seconds);
	ini_set("session.gc_maxlifetime", $max_idle_seconds); 
	ini_set("display_errors", 0); 
	//session_cache_limiter('private, must-revalidate');	// allow 'back' button to work on forms
	//session_cache_expire(8 * 60);

	include_once 'db_fns.php'; // ??
	include_once 'common/db_fns.php'; // ??
	
	if(mattOnlyTEST() || staffOnlyTEST()) ini_set("display_errors", 1);
	
	$installationSettings = ensureInstallationSettings();

	$mein_host = "https://{$_SERVER['HTTP_HOST']}";
	$returnURL = $mein_host.$_SERVER["REQUEST_URI"];

	$googleMapAPIKey = $installationSettings['googleMapAPIKey'];
	$googleMapGeocodingAPIKey = $installationSettings['googleMapGeocodingAPIKey'];
	//$googleMapAPIKey = strpos(dirname($_SERVER["SCRIPT_NAME"]), 'dev') 
	//	? "ABQIAAAAK5DZh3ZV8WE3KqE3qwLoOBSUAjaM2zuof8Oz9LrAYHmr2SWuuxSwynhCZn9m11Oerv8w4LsV-VFtwA" // iwmr.info
	//	: "ABQIAAAAK5DZh3ZV8WE3KqE3qwLoOBRTd7GOr-Pj_JdPg_LHg_41MAgVahQ0k8jOTF9nSngAVbLuLRvC8HT0ew"; // leashtime.com

	if(!isset($_SESSION)) {
		//if(mattOnlyTEST()) echo "BANG!";		
		$settings = ensureInstallationSettings();
		$sessName = $settings['sessionName'] ? $settings['sessionName'] : 'leashtime2';
		//$sessName = "pet";
		session_name($sessName);
		// necessary? session_set_cookie_params(60*60*8);
		session_start();
	}
}

function requestIsJSON() {
	$headers = apache_request_headers();
	foreach($headers as $hdr=>$val)
		if(strtoupper($hdr) == 'CONTENT-TYPE')
			return strpos(strtoupper($val), 'JSON') !== FALSE;
}

function getJSONRequestInput() {
	// assumes requestIsJSON
	return json_decode(file_get_contents('php://input'), true);
}

function setNewIncludePath() {
	$frags = explode('/', $_SERVER['SCRIPT_FILENAME']);
	for($i=0; $i < count($frags); $i++) {
		$parts[] = $frags[$i];
		if($i && $frags[$i-1] == 'www') break;
	}
	$topDir = join('/', $parts);
	
	$pathsepr = strpos( $_ENV[ "OS" ], "Win" ) ? ';' : ':';
	$frags = explode($pathsepr, get_include_path());
	$parts = array($frags[0], $topDir);
	for($i=1; $i < count($frags); $i++)
		$parts[] = $frags[$i];
	set_include_path(join($pathsepr, $parts));
}
	
/*******
Locking rules:
Owner can see everything EXCEPT:
- when required role is x or z
- when owner lacks a required * right
Corporate can see all x
Overseer can see all z

Dispatcher can see everything EXCEPT
- rights that are designated as dispatcher-limited rights which are not granted to the logged in dispatcher
*******/

function locked($requiredRights=null, $noForward=false, $exitIfLocked=true) {
	global $max_idle_seconds, $lockChecked, $auxiliaryWindow;
  $loggedIn = isset($_SESSION["auth_user_id"]);
  $locked = true;
  if($loggedIn) {
    if(isset($_SESSION["last_activity"]) &&
        (time() - $_SESSION["last_activity"] > $max_idle_seconds)) {
			$loggedIn = false;
			$sessionTimeout = true;
		}
    else $_SESSION["last_activity"] = time();
  }
  if(!$loggedIn || !adequateRights($requiredRights)) {
		$_SESSION['rights'] = '';
    if(isset($auxiliaryWindow) && $auxiliaryWindow) {
			echo "<center>Your LeashTime session has ended.<p>Please login again.<p><a href=# onClick='window.close();'>Close Window</a>";
		}
    else if(!$noForward) {
			if(!adequateRights($requiredRights)) {
				$message = "<font color=red>Insufficent access rights.  Please <a href='login-page.php'>Click Here</a> to login again.</font><p>";
			}
			include "login-page.php";
		}
    if(session_id()) session_destroy();
		if($exitIfLocked) exit;
  }
  else $locked =  false;
  $lockChecked = true;
  return $locked;
}

function adequateRights($needed) {
	// all rights in needed must be owned by logged in user
	// UNLESS a role contains a +.  In this case, at least one + right must be found.
	// owner user (o-) trumps all, unless
	// *special* (absolutely required) rights are needed
	// @ before a permission indicates that at least one of the "@" permissions must be present TEST
	$userRole = userRole();
	$check = $needed ? $needed : '';
	if(strpos($check, '+') !== FALSE) {  // test: adequateRights('+o-,+p-,#cl');
		$parts = explode(',', $check);
		foreach($parts as $pi => $part) {
			if(strpos($check, '+') == 0 && strpos($part, '-') == 2) {
				$allowedRoles[] = $part[1];
				unset($parts[$pi]);
			}
		}
		$check = join(',', $parts);
	}
	else if(strpos($check, '-') == 1) {
		$allowedRoles = array(substr($check, 0, 1));
		$check = substr($check, 2);
	}
	if(count($allowedRoles) == 1 && $allowedRoles[0] == 'z' && $userRole != 'z')
		return false;
	if($allowedRoles && $userRole != 'o' && $userRole != 'd' && !in_array($userRole, $allowedRoles))
		return false;
	if(strpos($check, ',') === 0) $check = substr($check, 1);
	$check = $check ? explode(",",$check) : array();
	$atLeastOne = array();
	foreach($check as $checkOne) 
		if($checkOne[0] == '@') $atLeastOne[] = substr($checkOne, 1);
	$userRights = $_SESSION['rights'] ? $_SESSION['rights'] : '';
	if($userRights) {
		$isOwner = false;
		if($userRole == 'o')
			$isOwner = (strpos($needed, 'z-') === FALSE)  /* Owner rights do not extend to z- user pages */
									&&  (strpos($needed, 'x-') === FALSE); /* Owner rights do not extend to x- user pages */
		if($userRole == 'd')
			$isOwner = (strpos($needed, 'z-') === FALSE)  /* Owner rights do not extend to z- user pages */
									&&  (strpos($needed, 'x-') === FALSE) /* Owner rights do not extend to x- user pages */
									&& (strpos($needed, '#') === FALSE);  /* dispatcher is not considered automatically an owner if dispatcher-specific permissions are required */
		if($isOwner 																	// owners rule!
				&& (strpos($needed, '*') === FALSE))    	// ... unless the rights are *special*
		  return true;  
		$userRights = substr($userRights, 0, 2).','.substr($userRights, 2);
	}
	$userRights = explode(",",$userRights);
//echo "RIGHTS: [".print_r($userRights,1)."]";exit;
// +right = one of the + rights is necessary
// owner trumps all, unless the right is *special*
// # rights refer to rights needed only by dispatchers
	$onePlusFound = false;
	foreach($check as $right) {
		$isSpecial = strpos($right, '*') !== FALSE;
		// The following line allowed sitters access to #ex and other dispatcher rights even if they were not assigned
		// I commented out this line and added #cl to every sitter's rights (and modified system-login-fns.php
		// to ensure sitters continued to have access to client lists.
		//if($right[0] == '#' && $userRole != 'd') continue;
		if($right[0] == '@') continue; // checked below

//if(mattOnlyTEST()) {echo "check: ".print_r($check,1).": $right ($userRole)<p>";}	 //$_SERVER['REMOTE_ADDR'] == '68.225.89.173'
			
		$genericOwnerAllowed = !$isSpecial && $isOwner;
		if(strpos($right[0], '+') !== FALSE) {
			if(in_array(substr($right,1), $userRights) || $genericOwnerAllowed)
				$onePlusFound = true;
		}
	  else if(!(in_array($right, $userRights) || $genericOwnerAllowed))
	  	return false;
	}
	if(strpos($needed, '+')) return $onePlusFound;
//if(mattOnlyTEST()) echo print_r($atLeastOne,1).'<br>'.$userRights.'<hr>';
	foreach($atLeastOne as $checkOne) if(in_array($checkOne, $userRights)) $atLeastOneFound = true;
	return !$atLeastOne ? true : $atLeastOneFound;
}

function userRole() {
	return isset($_SESSION['rights']) && $_SESSION['rights'] ? $_SESSION['rights'][0] : null;
}

function extractVars($keys, $source) {
	// return an array to pass to extract containing only those values
	// from $source named in $keys
	$result = array();
	if(is_string($keys)) $keys = explode(',',$keys);
	foreach($keys as $key)
		if(isset($source[$key]))
			$result[$key] = $source[$key];
	return $result;
}

function usingMobileSitterApp() {
	return $_SESSION["providerid"]
		&& $_SESSION['preferences']['mobileSitterAppEnabled']
		&& ($_SESSION["mobileVersionOverride"] || // || mattOnlyTEST()
					($_SESSION["mobiledevice"]  // set at login time 
						&& ($_SESSION['mobileVersionPreferred'])));
}

function usingMobileClientApp() {
	return FALSE;
	if(array_key_exists('mobile', $_REQUEST)) $_SESSION['mobileclientoff'] = !$_REQUEST['mobile'];
	return ((mattOnlyTEST() && $_SESSION["auth_login_id"] == 'bilbodl')
						 ||$_SESSION["auth_login_id"] == 'ekrum') && !$_SESSION['mobileclientoff'] ; // false;//
	return $_SESSION["clientid"]
		&& $_SESSION['preferences']['mobileClientAppEnabled']
		&& ($_SESSION["mobileVersionOverride"] || 
					($_SESSION["mobiledevice"]  // set at login time 
						&& ($_SESSION['mobileVersionPreferred'])));
}

function getMobileUserAgentTokensString() { // removed iPod
	return 'Alcatel,iPhone,SIE-,BlackBerry,Android,IEMobile,Obigo,Windows CE,Windows Phone,LG/,LG-,CLDC,Nokia,SymbianOS,PalmSource'
						.',Pre/,Palm webOS,SEC-SGH,SAMSUNG-SGH';
}

function getTabletUserAgentTokensString() {
	// groups separated by ||
	// each group separated by &
	// if group has > 1 elements, all elements must be present
	// any element with | is to be considered an OR 
  //return strtolower('iPad||Android&!mobile||Tablet PC';  -- !mobile is not a good test
	//return 'iPad||Android&GT-||Android&SGH-T849|Android&SM-T217S Build/JDQ39||Android&SHW-M180S||Tablet PC';
	return 'iPad||Android||Tablet PC';
}

/*function agentIsATablet($agent) {
	foreach(explode('||', getTabletUserAgentTokensString()) as $group) {
		$group = explode('&', $group);
		foreach($group as $required)
			if(strpos($agent, $required) === FALSE) break;
		if(strpos($agent, array_pop($group)) !== FALSE) return $group;
	}
}*/

function agentIsATablet($agent=null) {
	if(!$agent) $agent = $_SERVER["HTTP_USER_AGENT"];
	foreach(explode('||', getTabletUserAgentTokensString()) as $group) {
		$group = explode('&', $group);
		$success = true;
		foreach($group as $required) {
			if(strpos($required,'!') === 0) {
				$required = substr($required, 1);
				$desiredOutcome = FALSE;
			}
			else $desiredOutcome = TRUE;
			if($desiredOutcome != (strpos($agent, $required) !== FALSE)) {
				$success = false;
				break;
			}
		}
		if($success) return $group;
	}
}

function isIPad() { return strpos($_SERVER["HTTP_USER_AGENT"], 'iPad') !== FALSE; }
function isIPod() { return strpos($_SERVER["HTTP_USER_AGENT"], 'iPod') !== FALSE; }
function isIPhone() { return strpos($_SERVER["HTTP_USER_AGENT"], 'iPhone') !== FALSE; }
function isMobileTelephone() {
	if(isIPod()) return false; // iPod user agent can contain "iPhone"
	$agent = $_SERVER["HTTP_USER_AGENT"];
	$tokens = getMobileUserAgentTokensString(); // ini_session.php
	$tokens = explode(',',$tokens);
	$uppercaseAgent = strtoupper($agent);
	foreach($tokens as $token) if(strpos($uppercaseAgent, strtoupper($token)) !== FALSE) return true;
}
function isMobileUserAgent() {
	// See: http://en.wikipedia.org/wiki/List_of_user_agents_for_mobile_phones
	if(isMobileTelephone()) return true;
	// if all else fails, consider device mobile if the login page declared the device an iPad Mini
	return $_SESSION['mobiledevice'] || isIPod();
}

function isIOSDevice() {
	return isIPad() || isIPod() || isIPhone();
}

function killSessionAndExit() {
	session_unset();
  session_destroy();
	exit;
}

function internetExplorerVersion() {
	$ua = $_SERVER["HTTP_USER_AGENT"];
	if($start = strpos($ua, 'MSIE ')) {
		return floatval(substr($ua, $start+strlen('MSIE ')));
	}
	else if(($start = strpos($ua, 'Trident/')) && ($start = strpos($ua, 'rv:', $start))) {
		return floatval(substr($ua, $start+strlen('rv:')));
	}
}
	
function intValueOrZero($val) {
	if("".(int)"$val" != $val) $val = 0; // against injection attacks 8/26/2020
	return $val;
}

?>
