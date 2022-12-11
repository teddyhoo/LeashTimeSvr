<? // inactive-pet-visits.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$locked = locked('o-');
extract(extractVars('droppetid,fromappt', $_REQUEST));

if($droppetid) {
	$petname = fetchRow0Col0("SELECT name FROM tblpet WHERE petid = $droppetid LIMIT 1");
	if(!$petname) $errors[] = "No pet found to drop.";
	$appt = fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = $fromappt LIMIT 1");
	if(!$appt) $errors[] = "No visit found.";
	if(!$errors) {
		$pets = explode(', ', $appt['pets']);
		if(!in_array($petname, $pets))
			$errors[] = "$petname was not listed on the ".shortDate(strtotime($appt['date']))." visit.";
		else {
			$newPets = array();
			foreach($pets as $p) if($p != $petname) $newPets[] = $petname;
			$newPets = join(', ', $newPets);
			updateTable('tblappointment', array('pets'=>$newPets), "appointmentid = $fromappt", 1);
			$message = "$petname dropped from ".shortDate(strtotime($appt['date']))." visit.";
		}
	}
	if($errors) $error = join('<br>', $errors);
}

$petResults = doQuery(
	"SELECT p.*, CONCAT_WS(' ', fname, lname) as client
	 FROM tblpet p
	 LEFT JOIN tblclient on clientid = ownerptr
	 WHERE p.active = 0 AND tblclient.active = 1", 1);
$today = date('Y-m-d');
while($pet = mysqli_fetch_array($petResults, MYSQL_ASSOC)) {
	$client = $pet['client'];
	$petname = $pet['name'];
	$futureVisits = fetchAssociations(
		"SELECT a.*, label as service
			FROM tblappointment a
			LEFT JOIN tblservicetype ON servicetypeid = servicecode
			WHERE clientptr = {$pet['ownerptr']} AND date > '$today' 
				AND pets LIKE '%$petname%'
				ORDER BY date", 1);
	foreach($futureVisits as $appt) {
		if($appt['recurringpackage']) {
			$recurring[$pet['ownerptr']][] = $petname;
			if(!$firstRecurring[$pet['ownerptr']]) $firstRecurring[$pet['ownerptr']] = shortDate(strtotime($appt['date']));
		}
		else {
			$redX = "<img style='cursor:pointer;display:inline' onclick=\"dropPet({$appt['appointmentid']}, {$pet['petid']})\" title='Remove $petname from visit.' src='art/delete.gif' width=15 height=15> ";
			$editLink = fauxLink($appt['service'], "editVisit({$appt['appointmentid']})", 1, 1);
			$row = array('pet'=>$redX.$petname, 'client'=>$client, 'date'=>shortDate(strtotime($appt['date'])), 'service'=>$editLink, 'pets'=>$appt['pets']);
			$rows[] = $row;
		}
	}
}
foreach((array)$recurring as $clientid => $petnames) {
	$owner = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientid LIMIT 1", 1);
	$pets = join(', ', array_unique($petnames));
	require_once "service-fns.php";
	$scheduleLink = getCurrentClientPackages($clientid, 'tblrecurringpackage');
	$scheduleLink = $scheduleLink[0]['packageid'];
	$scheduleLink = fauxLink($owner, "document.location.href=\"service-repeating.php?packageid=$scheduleLink\"", 1, "Go edit this recurring schedule.");
	
	$recurringrows[] = array('owner'=>$scheduleLink, 'pets'=>$pets, 'nextvisit'=>$firstRecurring[$clientid]);
}
include "frame.html";
if($message) echo "<span class='tiplooks'>$message</span>";
if($error) echo "<span class='warning'>$error</span>";
echo "<h2>Future Short Term Schedule Visits to Inactive Pets</h2>";
if($rows) echo "<p class='tiplooks'>Click <img src='art/delete.gif'  width=15 height=15> to remove a pet from visit.  Click the service to edit the visit.</p>"; 
quickTable($rows);
echo "<h2>Recurring Schedules with Future Visits to Inactive Pets</h2>";
if($recurringrows) echo "<p class='tiplooks'>Click a client to edit the client's recurring schedule.</p>"; 
quickTable($recurringrows);
?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function editVisit(id) {
	openConsoleWindow('editappt', 'appointment-edit.php?id='+id,530,550);
}
function dropPet(apptid, petid) {
	if(confirm('Drop this pet from the visit?'))
		document.location.href="inactive-pet-visits.php?droppetid="+petid+"&fromappt="+apptid;		
}
</script>
<?
include "frame-end.html";
