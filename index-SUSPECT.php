<?
// index.php CURRENT VERSION 10/26/2013
require_once "common/init_session.php";
// Determine access privs
if(!$_SESSION['auth_user_id'] 
	&& strpos(strtolower($installationSettings['baseURL']), 'leashtime.com')
	&& !strpos(strtolower($installationSettings['baseURL']), '/rc')
	&& !strpos(strtolower($installationSettings['baseURL']), '/dev')) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($installationSettings);exit;}	
	header("Location: http://{$_SERVER["HTTP_HOST"]}/info");
	exit;
}

$locked = locked();

/* Session rights: x- where x one of (s,o,p,c) support, owner, provider, customer */
$role = $_SESSION['rights'][0];

function popArg($url) {
	$parts = explode('?',$url);
	foreach(explode('&', $parts[1]) as $part) {
		if(strpos($part, 'pop=') !== FALSE) return substr($part,4);
	}
}

$popurl = $_REQUEST['pop'] ? $_REQUEST['pop'] : '';

$processPop = false;

// if support
if($role == 's') {
}

// if owner
else if($role == 'o') {
  include "homepage_owner.php";
}

// if provider
else if($role == 'p') {

//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "DEV: [{$_SESSION["mobiledevice"]}] PREF: [{$_SESSION["mobileVersionPreferred"]}]";exit; }
	
  $url = "prov-own-schedule-list.php";
	/*if($_SESSION['preferences']['mobileSitterAppEnabled']
		&& ($_SESSION["mobileVersionOverride"] || 
					($_SESSION["mobiledevice"]  // set at login time 
						&& ($_SESSION['mobileVersionPreferred']
								) // || $_SESSION['webUIOnMobileDisabled'] 
					)
				)
		)*/
		if(usingMobileSitterApp()) // in init_session.php
			$url = "home-prov-mobile.php";
if(FALSE && mattOnlyTEST()) {
	$obuffer = "test/ipadtest.html";
	ob_start();
	ob_implicit_flush(0);
}
  include $url;
if($obuffer) {
	file_put_contents($obuffer, ob_get_contents());
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	echo $obuffer;
}
  $processPop = $popurl;

}

// if customer
else if($role == 'c') {
  include "homepage-client.php";
}

else if($role == 'z') {
  include "homepage-maint.php";
}

else if($role == 'x') {
  header("Location: ".globalUrl("corp/index.php"));
  exit;
}

else if($role == 'd' && $_SESSION['bizptr']) {
  include "homepage_owner.php";
}

else if($role == 'd') {
  header("Location: ".globalUrl("corp/homepage-disp.php"));
  exit;
}

if($processPop) {
	$popName = popArg($popurl);
	echo "<script language='javascript'>openConsoleWindow('$popName', '$popurl',750,700);</script>";
}


?>

