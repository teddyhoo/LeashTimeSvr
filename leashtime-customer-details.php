<? // leashtime-customer-details.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";



locked('o-');

if($db != 'leashtimecustomers') {
	echo "Available only in LeashTime Customers";
	exit;
}

$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = {$_GET['id']} LIMIT 1", 1);



if(!($garagegatecode = $client['garagegatecode'])) 
	$totalDescription = "This client does not have its business ID (Garage/Gate Code) set.";
else {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$id = $garagegatecode;
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $id LIMIT 1", 1);
	if(!$biz) $totalDescription = "<h2>No business found with business ID ($id).</h2>";
	else {
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
		// ASSUMPTION: DB is leashtimecustomers, $biz array is set
		// SETS globals: $ltclientid, $goldstar,$ltclient, $ltClientDescription, $bizPrefs, $activated, $bizDescription, $managers, $totalDescription
		// maint-biz-description-INCLUDE.php CALLED in maint-edit-biz.php, leashtime-customer-details.php
		require_once "maint-biz-description-INCLUDE.php";
	}
}


$extraHeadContent = "<script language='javascript' src='common.js'></script>";
require "frame-bannerless.php";
require_once "gui-fns.php";
if($garagegatecode) 
	echoButton('', 'Issue a System Notification', 
					"openConsoleWindow(\"systemnotification\", \"comm-notice-composer.php?client={$_GET['id']}\", 700, 600)");
echo $totalDescription;
