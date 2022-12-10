<? // client-home-safe.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "response-token-fns.php";

// parameter:
// requestid -- a billing reminder request
// OR...
// packageptr -- a nonrecurring packageid

reconnectPetBizDB($_SESSION["db"], $_SESSION["dbhost"], $_SESSION["dbuser"], $_SESSION["dbpass"]);
$tz = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'timeZone' LIMIT 1", 1);
date_default_timezone_set($tz ? $tz : 'America/New_York');

if($_GET['requestid']) {
	$request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$_GET['requestid']} LIMIT 1", 1);
	$note = $request['officenotes'] ? $request['officenotes'] ."\n" : '';
	$note .= "HOME SAFE: ".shortDateAndDay(time()).' '.date('g:i a');
	$mods = array('officenotes'=>$note, 'resolved'=>1);
	if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'homeSafeDoNotResolveRequest' LIMIT 1"))
		unset($mods['resolved']);
	updateTable('tblclientrequest', $mods, "requestid = {$_GET['requestid']}", 1);
}

require_once "frame-bannerless.php";
echo "<h2>Welcome Back!</h2>
<p style='fontSize1_3em'>Thanks for letting us know!</p>
<img src='art/lightning-smile-small.png'>";

if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'homeSafeNotifySitters' LIMIT 1")) {
	// find all sitters for schedule
	require_once "service-fns.php";
	require_once "request-fns.php";
	if($_GET['requestid']) {
		$hidden = getHiddenExtraFields($request);
		$packageptr = $hidden['packageptr'];
		$clientptr = $request['clientptr'];
	}
	else if($_GET['packageptr']) {
		$packageptr = $_GET['packageptr'];
		$package = getPackage($packageptr, $R_or_N_orNull='N');
		$clientptr = $package['clientptr'];
	}
	$clientLabel = clientLabelForSitters($clientptr);
	$appts = fetchAllAppointmentsForNRPackage($packageptr, $clientptr);
	$provids = array();
	foreach($appts as $appt) if($appt['providerptr']) 
		$provids[] = $appt['providerptr'];
	$provids = array_unique($provids);
	if($provids) {
//echo "[".join(', ', $provids)."]";exit;
		require_once "client-fns.php";
//echo "clientLabelForSitters($clientptr) = [".clientLabelForSitters($clientptr)."]";exit;
		foreach($provids as $provptr) {
			notifySitterClientHomeSafe($provptr, $clientLabel, $clientptr);
		}
	}
}

function notifySitterClientHomeSafe($provptr, $clientLabel, $clientptr) {
		require_once "provider-memo-fns.php";
	// if provider does not accept notifications of this type, return
	// we really want to call makeProviderMemo without the override
	makeProviderMemo($provptr, "homesafe|HOME SAFE reported : $clientLabel", $clientptr, $preprocess=1, $acceptAlways=TRUE);
}

if(TRUE) {
	require_once "comm-fns.php";
	require_once "event-email-fns.php";
	//$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientptr LIMIT 1", 1);
	//notifyStaff('h', "HOME SAFE reported : $clientLabel", "$clientName (@clientptr)<p>reported Home Safe at ".shortDateAndTime(time()));
	$clientLabel = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$request['clientptr']}", 1);
	notifyStaff('h', "HOME SAFE reported : $clientLabel", 
								"$clientLabel (@{$request['clientptr']})<p>reported Home Safe at ".shortDateAndTime(time())
								."<p style='color:gray;font-size:0.7em;'>(msg ver: 2)</p>"
							);
}



session_unset();
session_destroy();
