<? // homepage-client.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
$_SESSION["preferences"] = fetchPreferences();

// We set responsiveClient the same way at login time, but this
// allows the option of adding a user as a tester and saving the tester
// the trouble of logging in again.
$_SESSION["responsiveClient"] = 
	$clientUIVersion = $_SESSION['preferences']['clientUIVersion']
	 && $_SESSION['preferences']['clientUIVersion'] != 'Version 1'
	 && !$_GET['dev']
	 && ($_SESSION['preferences']['version2TestClients'] == 'PUBLIC'
	 			|| in_array($_SESSION["clientid"], 
											explode(',', $_SESSION['preferences']['version2TestClients'])));

			

if($_SESSION["creditCardIsRequired"]
	// if CC required, go to account page
		) {
	include "client-own-account-cc.php";
	exit;
}
if($db == 'leashtimecustomers') {
	include "client-own-account.php";
	exit;
}
if(fetchFirstAssoc("SELECT appointmentid FROM tblappointment WHERE clientptr = {$_SESSION["clientid"]} LIMIT 1") ||
	fetchRow0Col0("SELECT requestptr FROM tblclientprofilerequest WHERE clientptr = {$_SESSION["clientid"]}")) {
	// a profile request was submitted
	if($_SESSION["responsiveClient"])
		include "client-own-schedule-fullcalendar.php";
	else if(dbTEST('dogslife') && in_array($_SESSION['auth_login_id'], array_map('strtolower', array('lecharles1','ekrum'))))
		include "client-own-schedule-list-ajax.php";
	else include "client-own-schedule-list.php";
}
else {
	include "client-own-edit.php";
}
