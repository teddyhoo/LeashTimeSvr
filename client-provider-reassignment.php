<? // client-provider-reassignment.php
// may be called from client-edit.php after defaultprovider has changed

// oldprovider, client

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";

// Determine access privs
$locked = locked('o-');

$clientid = $_REQUEST['client'];
$go = $_REQUEST['go'];
$oldprovider = $_REQUEST['oldprovider'] ? $_REQUEST['oldprovider'] : 0;

$apptCount = countCurrentIncompleteClientAppointmentsWithProvider($clientid, $oldprovider);
$packageTypes = getCountsForCurrentClientPackagesWithProvider($clientid, $oldprovider);
$packageCount = array_sum($packageTypes);
$packageTypes = join(' and ', array_keys($packageTypes));

$newProvider = fetchRow0Col0("SELECT defaultproviderptr FROM tblclient WHERE clientid = $clientid");
$newProvider = $newProvider ? $newProvider : 0;


if($apptCount + $packageCount == 0) redirectToDestination();

//echo "[$apptCount]   [$packageCount]";exit;
if($go==1) {
	if($apptCount) {
		$mods = withModificationFields(array('providerptr'=>$newProvider));
		$appts = fetchAssociations(
								tzAdjustedSql("SELECT appointmentid, date, timeofday, providerptr, servicecode, charge, pets FROM tblappointment 
														WHERE clientptr = $clientid AND providerptr = $oldprovider AND completed IS NULL
															AND (date > CURDATE() OR (date = CURDATE() AND starttime >= CURTIME()))"));
		$timesOff = getProviderTimeOff($newProvider, false);
		$eligibleIds = array();
		require_once "pet-fns.php";
		require_once "appointment-fns.php";
		$clientPets = getClientPetNames($clientid);
		foreach($appts as $appt) {
			$specificMods = array_merge($mods);

			if(TRUE || $appt['providerptr']) {

				if($newProvider && providerIsOff($newProvider, $appt['date'], $appt['timeofday'])) {
					$specificMods['providerptr'] = 0;
					$timeoffConflicts[] = $appt;
					$doNotRedirect = 1;
				}
				else if(detectVisitCollision($appt, $newProvider)) {
					$specificMods['providerptr'] = 0;
					$collisionConflicts[] = $appt;
					$doNotRedirect = 1;
				}
			}
			$eligibleIds[] = $appt['appointmentid'];
			$specificMods['rate'] = calculateServiceRate($specificMods['providerptr'], $appt['servicecode'], $appt['pets'], $clientPets, $appt['charge']);
/*if(mattOnlyTEST()) {
echo "<hr><hr>NEW: $newProvider OLD: $oldprovider<p>".print_r($specificMods,1).'<p>'
."appointmentid = {$appt['appointmentid']}"
."<p>APPT: ".print_r(fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = {$appt['appointmentid']}"),1);
$doNotRedirect = 1;
}*/
			updateTable('tblappointment', $specificMods, "appointmentid = {$appt['appointmentid']}", 1);
/*if(  mattOnlyTEST()) {
	echo "<hr>==&gt; ".print_r(fetchFirstAssoc("SELECT * FROM tblappointment WHERE appointmentid = {$appt['appointmentid']}"),1);
}*/
			//if(!providerIsOff($appt['prov'], $appt['date'], $appt['timeofday'], $timesOff)) {
			//}
		}
			
}
			
		if($eligibleIds) {
			//updateTable('tblappointment', $mods, "appointmentid IN (".join(',', $eligibleIds).")", 1);
			if($_SESSION['surchargesenabled']) {
				require_once "surcharge-fns.php";
				updateAppointmentAutoSurchargesWhere(tzAdjustedSql(
					"clientptr = $clientid AND providerptr = $oldprovider AND completed IS NULL
								AND (date > CURDATE() OR (date = CURDATE() AND starttime >= CURTIME()))"));
			}
		}
		// completion and charge are unchanged, so no discount action is necessary
    require_once "provider-memo-fns.php";
		if($oldprovider != $newProvider)
			makeClientVisitsReassignmentMemo($oldprovider, $clientid, $apptCount, $from=1);
		makeClientVisitsReassignmentMemo($newProvider, $clientid, $apptCount, $from=0);
							
	}
	if($packageCount) {
		$sql = "SELECT serviceid FROM `tblservice` 
						LEFT JOIN tblservicepackage ON packageid = packageptr
						WHERE tblservice.clientptr = $clientid AND providerptr = $oldprovider 
						AND recurring = 0 and tblservice.current AND enddate >= CURDATE()";
		$services = fetchCol0(tzAdjustedSql($sql));
		$sql = "SELECT serviceid FROM `tblservice` 
						LEFT JOIN tblrecurringpackage ON packageid = packageptr
						WHERE tblservice.clientptr = $clientid AND providerptr = $oldprovider 
						AND recurring = 1 and (cancellationdate IS NULL OR cancellationdate >= CURDATE())";
//echo "$sql:<p>".print_r($recurring	, 1);exit;
		$services = array_merge($services, fetchCol0(tzAdjustedSql($sql)));
		$services = join(',', $services);
		updateTable('tblservice', withModificationFields(array('providerptr'=>$newProvider)), "serviceid IN ($services)", 1);
		logChange($clientid, 'tblclient', 'm', "Provider: [$oldprovider] => [$newProvider]");
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { exit; }	
	if(!$doNotRedirect) redirectToDestination();
}

function redirectToDestination() {
	//header ("Location: client-list.php?$param");
	header ("Location: client-edit.php?id={$_REQUEST['client']}");
	exit();
}
$client = getOneClientsDetails($clientid);
$clientname = $client['clientname'];
$pageTitle = "$clientname's Default Sitter Changed";
$oldProviderName = getProvider($oldprovider);
$oldProviderName = $oldProviderName ? $oldProviderName['fname'].' '.$oldProviderName['lname'] : '<i>Unassigned</i>';
$newProviderName = getProvider($newProvider);
$newProviderName = $newProviderName ? $newProviderName['fname'].' '.$newProviderName['lname'] : '<i>Unassigned</i>';
include "frame.html";
// ***************************************************************************
if($doNotRedirect) {
	$serviceTypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	echo "<div class='fontSize1_1em'>";
	if($timeoffConflicts) {
		echo "The following visits could not be assigned to $newProviderName due to Time Off conflicts:";
		echo "<ul>";
		foreach($timeoffConflicts as $appt)
			echo "<li>".shortDate(strtotime($appt['date']))." {$appt['timeofday']} {$serviceTypes[$appt['servicecode']]}";
		echo "</ul>";
	}
	if($collisionConflicts) {
		echo "The following visits could not be assigned to $newProviderName due to Exclusive Service Type collisions:";
		echo "<ul>";
		foreach($collisionConflicts as $appt)
			echo "<li>".shortDate(strtotime($appt['date']))." {$appt['timeofday']} {$serviceTypes[$appt['servicecode']]}";
		echo "</ul>";
	}
	echo "<a href='client-edit.php?id=$clientid&tab=services'>Back to $clientname&apos;s schedule.</a></div>";
}

else echo <<<HTML
<div class='fontSize1_1em'>
You changed this client&apos;s default provider from $oldProviderName to $newProviderName.
<p>
$oldProviderName is currently scheduled for $apptCount visits in $clientname&apos;s $packageTypes schedules in future days.
<p>
Would you like to take all of $clientname&apos;s future appointments which are assigned to $oldProviderName and reassign them to $newProviderName?
<center>
<a href="client-provider-reassignment.php?go=1&client=$clientid&oldprovider=$oldprovider">
Yes, reassign appointments to $newProviderName.
</a>
<p>- or -
<p>
<a href="client-edit.php?id={$_REQUEST['client']}">No, leave the appointments as they are.</a>
</div>
HTML;
// ***************************************************************************
include "frame-end.html";
