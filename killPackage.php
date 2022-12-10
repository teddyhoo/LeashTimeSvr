<?
// killPackage.php
// recur
// id
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

extract($_REQUEST);

if(!isset($recur)) {echo "Supply 'recur'"; exit;}
if(!isset($id) && !isset($client)) {echo "Supply 'id' or 'client'"; exit;}

$ptable = $recur ? 'tblrecurringpackage' : 'tblservicepackage';


if($client && $recur) {
	$packageids = fetchCol0("SELECT * FROM tblrecurringpackage WHERE clientptr = $client");
	if($packageids) {
		$services = fetchCol0("SELECT serviceid FROM tblservice WHERE packageptr IN (".join(',', $packageids).")");
		$appts = fetchCol0("SELECT appointmentid FROM tblappointment WHERE packageptr IN (".join(',', $packageids).")");
		$clientid = $client;
		$client = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $client LIMIT 1");
		echo "Deleting the recurring package for client $client:<p>";
		$toDo = array(
							"DELETE FROM tblrecurringpackage WHERE packageid IN (".join(',', $packageids).")",
							"DELETE FROM tblservice WHERE packageptr IN (".join(',', $packageids).")",
							"DELETE FROM tblappointment WHERE packageptr IN (".join(',', $packageids).")");
	}
	else {
		echo "No recurring package to delete.";
		$noNeed = true;
	}
}
else {
	$package = fetchFirstAssoc("SELECT * FROM $ptable where packageid = $id");
	if(!$package) {echo ($recur ? 'recurring' : 'non-recurring')." package $id not found."; exit;}

	$services = fetchCol0("SELECT serviceid FROM tblservice WHERE packageptr = $id AND recurring = $recur");
	$appts = fetchCol0("SELECT appointmentid FROM tblappointment WHERE serviceptr IN (".join(',', $services).")");
	$client = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = {$package['clientptr']}");
	echo "Deleting ".($recur ? 'recurring' : ($package['onedaypackage'] ? 'one-day' : 'non-recurring'))." package for client $client:<p>";
	$toDo = array(
						"DELETE FROM $ptable WHERE packageid = $id",
						"DELETE FROM tblservice WHERE serviceid IN (".join(',', $services).")",
						"DELETE FROM tblappointment WHERE appointmentid IN (".join(',', $appts).")");
}
if(!$noNeed) {
	echo "Services: ".count($services)."<br>Appointments: ".count($appts);
	if(!isset($confirm)) echo "<p>Continue: <a href='killPackage.php?recur=$recur&id=$id&client=$clientid&confirm=1'>Yes</a>";
}
if($confirm) {
	foreach($toDo as $do) sayAndDo($do);
}

function sayAndDo($sql) {
	doQuery($sql);
	echo "<p>$sql<br>";
  printf("Records deleted: %d<p>\n", mysql_affected_rows());
}
/*
delete from tblappointment where clientptr = 611;
delete from tblservice where clientptr = 611;
delete from tblrecurringpackage where clientptr = 611;
delete from tblbillable where clientptr = 611;
*/