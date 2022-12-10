<?  //maint-biz-description-INCLUDE.php

// ASSUMPTION: DB is leashtimecustomers, $biz array is set, $id = $bizid
// SETS globals: $ltclientid, $goldstar,$ltclient, $ltClientDescription, $bizPrefs, $activated, $bizDescription, $managers, $totalDescription
// CALLED in maint-edit-biz.php, leashtime-customer-details.php
$ltclientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE garagegatecode = {$biz['bizid']} LIMIT 1");
if($ltclientid) $goldstar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '2|%'");
if($ltclientid) {
	require_once "client-flag-fns.php";
	$ltclient = fetchFirstAssoc("SELECT *, CONCAT('@',clientid) as tag FROM tblclient WHERE clientid = $ltclientid LIMIT 1");
	foreach(explode(',', 'tag,fname,lname,email,homephone,cellphone,workphone,city,state') as $f) 
		$ltClientDescription[$f] = "<b>$f: </b>{$ltclient[$f]}";
	$ltClientDescription = '<b><u>LT Client:</u></b>'
													.clientFlagPanel($ltclientid, $officeOnly=false, $noEdit=true, $contentsOnly=false, $onClick=null, $includeBillingFlags=true)
													.'<p>'.join('<br>', $ltClientDescription);
}
else $ltClientDescription = '<b><u>LT Client:</u></b><p>No LeashTime Client record associated with this business.';

reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
$bizPrefs = fetchKeyValuePairs("SELECT * FROM tblpreference");
$biz['phone'] = $bizPrefs['bizPhone'];
$biz['bizEmail'] = $bizPrefs['bizEmail'];


foreach(explode(',', 'bizName,shortBizName,bizPhone,bizEmail,bizAddress,bizHomePage,timeZone,emailHost') as $f) $bizPrefDescription[$f] = "<b>$f: </b>".addslashes($bizPrefs[$f]);

$ccKeys = explode(',', 'ccGateway,x_login,x_tran_key');
foreach($ccKeys as $k) {
	if($k == 'ccGateway') $bizPrefDescription[] = "<b>$k</b>: ".($bizPrefs['ccGateway'] ?  : 'Not set.');
	else if($bizPrefs[$k]) $bizPrefDescription[] = "<b>$k</b>: is set.";
	else $bizPrefDescription[] = "<b>$k</b>: is NOT set.";
}

$bizPrefDescription = '<b><u>Prefs:</u></b><p>'.join('<br>', $bizPrefDescription);

$bizMetrics['activeclients'] = fetchRow0Col0("SELECT COUNT(*) FROM tblclient WHERE active = 1");
$bizMetrics['inactiveclients'] = fetchRow0Col0("SELECT COUNT(*) FROM tblclient WHERE active = 0");
$bizMetrics['activesitters'] = fetchRow0Col0("SELECT COUNT(*) FROM tblprovider WHERE active = 1");
$bizMetrics['inactivesitters'] = fetchRow0Col0("SELECT COUNT(*) FROM tblprovider WHERE active = 0");
$bizMetrics['totalvisits'] = fetchRow0Col0("SELECT COUNT(*) FROM tblappointment");
$bizMetrics['futurevisits'] = fetchRow0Col0("SELECT COUNT(*) FROM tblappointment WHERE date >= '".date('Y-m-d')."'");
$bizMetrics['todaysvisits'] = fetchRow0Col0("SELECT COUNT(*) FROM tblappointment WHERE date = '".date('Y-m-d')."'");
$metricsDescription = '<b><u>Metrics:</u></b><p>'
													."<b>Sitters</b> active: {$bizMetrics['activesitters']} inactive: {$bizMetrics['inactivesitters']}<br>"
													."<b>Clients</b> active: {$bizMetrics['activeclients']} inactive: {$bizMetrics['inactiveclients']}<br>"
													."<b>Visits</b> today: {$bizMetrics['todaysvisits']} future: {$bizMetrics['futurevisits']} total: {$bizMetrics['totalvisits']}<br>";

//5=29.95,10=69.95,20=129.95,30=169.95,50=199.95,999=249.95
if($biz['rates']) {
	require_once "gui-fns.php";
	$rates = explodePairsLine(str_replace(',', '||', str_replace('=', '|', $biz['rates'])));
	$ratesDesc = "<hr><b><u>Metrics:</u></b><ul>";
	foreach($rates as $count => $rate)
		$ratesDesc .= "<li>up to $count sitters: ".number_format($rate, 2);
	$ratesDesc .= "</ul>";
}
include "common/init_db_common.php";
//$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$id'");
$activated = $biz['activated']; // ? date('Y-m-d', $biz['activated']) : '--'; 
$notificationButton = echoButton('', 'Issue a System Notification', "openConsoleWindow(\"systemnotificationeditor\", \"request-system-adhoc-system-notification.php?bizid=$id\", 450,450)", null, null, 'noEcho');
$notificationButton = str_replace("\r", ' ', str_replace("\n", ' ', $notificationButton));
$bizDescription = "$notificationButton<br><b><u>Biz table:</u></b><p>DB: {$biz['db']}<br>Biz ID: {$biz['bizid']}<br>State: {$biz['state']}<br>Country: {$biz['country']}<br>Time Zone: {$biz['timeZone']}<br>Activated: $activated<p>";
$managers = fetchAssociations("SELECT * FROM tbluser WHERE bizptr = $id AND rights LIKE 'o-%' AND ltstaffuserid = 0");
foreach($managers as $man) {
	$class = $man['isowner'] ? 'boldfont' : '';
	$ownerLabel = $man['isowner'] ? 'Owner: ' : '';
	$mans[] = addslashes("<span class='$class'>$ownerLabel{$man['fname']} {$man['lname']} - {$man['email']}</span>");
	$bizDescription .= join('<br>', $mans);
}
if($biz['lockout']) $lockout = "<h2 style='color:red'>Locked out since ".date('m/d/Y', strtotime($biz['lockout'])).'</h2>';
$bizid = $id;
require_once "maint-biz-burden.php";  // sets $storageBurden
$storageBurden = str_replace("\n", '', str_replace("\r", '', $storageBurden));
$totalDescription = "<h2>{$biz['bizname']}</h2>$lockout$bizDescription<hr>$bizPrefDescription<hr>$metricsDescription$ratesDesc<hr>$ltClientDescription<hr>$storageBurden";
//reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
require "common/init_db_common.php";
