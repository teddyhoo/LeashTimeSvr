<?
//appointment-request-cancel-ajaxSAVED.php -- superseded to support multiday cancellations
// called by: request-edit.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "request-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "appointment-fns.php";
require_once "gui-fns.php";
include "client-schedule-fns.php";
require_once "provider-memo-fns.php";

// Verify login information here
locked('o-');
extract($_REQUEST);

$source = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $request");
$scope = explode('_', $source['scope']);
if($scope[0] == 'sole') $where = "appointmentid = {$scope[1]}";
else if($scope[0] == 'day') 
	$where = "clientptr = {$source['clientptr']} AND date = '{$scope[1]}'";
$date = date('Y-m-d H:i:s');

	// UPDATE tblappointment
$additionalMods = array("pendingchange"=>null);
if($source['requesttype'] != 'uncancel') 
	$additionalMods['cancellationreason'] = "Client request: [$request]";
//print_r($mods);echo "<p>[$where]<p>";
$ids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE $where");

cancelAppointments($ids, ($source['requesttype'] == 'uncancel' ? 0 : 1), $additionalMods, $generateMemo=false, $initiator='honored request');

$dateTime = 'Honored '.shortDateAndTime('now', 'mil')."\n";
updateTable('tblclientrequest', array('resolved'=>1, 'resolution'=>'honored', 
	'officenotes'=>sqlVal("CONCAT_WS('\\n','$dateTime', officenotes)")), "requestid = $request", 1);

$appts = fetchAssociations("SELECT * FROM tblappointment WHERE $where");
$date = $appts[0]['date'];
$providers = array();
foreach($appts as $appt) $providers[] = $appt['providerptr'];
foreach(array_unique($providers) as $provptr)
	makeClientVisitsStatusChangeMemo($provptr, $source['clientptr'], count($appts), ($source['requesttype'] == 'uncancel' ? 0 : 1), $date);
echo "<tr><td id='cancelappts' colspan=2 style='border: solid black 1px;'>";

clientScheduleTable($appts);