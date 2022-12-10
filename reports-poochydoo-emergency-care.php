<? // reports-poochydoo-emergency-care.php
// https://leashtime.com/reports-visits.php?option=visits&client=1602&uncanceled=1

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-#vr');

$pageTitle = "Poochy Doos Clients Without Emergency Authorization";
$breadcrumbs = "<a href='reports.php'>Reports</a>";	

include "frame.html";

$sql = "SELECT *  FROM `tblclient` WHERE `active` = 1 AND `emergencycarepermission` = 0";
$activeNotChecked = fetchAssociations($sql);
$sql = "SELECT *  FROM `tblclient` WHERE `active` = 1 AND `emergencycarepermission` = 1";
$activeChecked = fetchAssociations($sql);
$sql = "SELECT *  FROM `tblclient` WHERE `active` = 0 AND `emergencycarepermission` = 0";
$inactiveNotChecked = fetchAssociations($sql);
$sql = "SELECT *  FROM `tblclient` WHERE `active` = 0 AND `emergencycarepermission` = 1";
$inactiveNotChecked = fetchAssociations($sql);

echo "<h3>Active Clients Without Emergency Authorization</h3>";
if(!$activeNotChecked) echo "None";
else {
	echo "<table>";
	foreach($activeNotChecked as $client) 
		echo "<tr><td><a href='client-edit.php?id={$client['clientid']}' title='Edit this client'>{$client['fname']} {$client['lname']} (@{$client['clientid']})</a>";
	echo "</table>";
}

echo "<h3>Inactive Clients Without Emergency Authorization</h3>";
if(!$inactiveNotChecked) echo "None";
else {
	echo "<table>";
	foreach($inactiveNotChecked as $client) 
		echo "<tr><td><a href='client-edit.php?id={$client['clientid']}' title='Edit this client'>{$client['fname']} {$client['lname']} (@{$client['clientid']})</a>";
	echo "</table>";
}


include "frame-end.html";

