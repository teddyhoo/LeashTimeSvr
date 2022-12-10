<? // appointment-clear.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "preference-fns.php";

if(!mattOnlyTEST()) {echo "matt only.";exit;}
locked('ea');
extract($_REQUEST);

if(!isset($id)) $error = "Appointment ID not specified.";

$appointmentProps = fetchKeyValuePairs("SELECT property, value FROM tblappointmentprop WHERE appointmentptr = '$id'");

$photoCacheId = $appointmentProps['visitphotocacheid'];
if(!$delete) {
	print_r($appointmentProps);
	$deadPhoto = "photo ID: [$photoCacheId] ";
	require_once "remote-file-storage-fns.php";
	if($entry = getCachedFileEntry($photoCacheId))
		$deadPhoto .= " entry [".print_r($entry, 1)."] ";
	echo "<br>$deadPhoto";	
	exit;
}
if($photoCacheId) {
	$deadPhoto = "photo ID: [$photoCacheId] ";
	require_once "remote-file-storage-fns.php";
	if($entry = getCachedFileEntry($photoCacheId)) {
		$deadPhoto .= " entry [".print_r($entry, 1)."] ";
		$deadPhoto .= deleteFileFromCache($entry['localpath']);
	}
	if($deadPhoto) {
		deleteTable('tblremotefile', "remotefileid = $photoCacheId", 1);
		$photoResult .= "Deleted remote file: $photoCacheId";
	}
	else $photoResult = "Could NOT delete remote file: $photoCacheId";
	
}
print_r($appointmentProps);
deleteTable('tblappointmentprop', "appointmentptr = $id", 1);
echo "<br>Deleted ".mysql_affected_rows();
deleteTable('tblgeotrack', "appointmentptr = $id", 1);
echo " appt props and ".mysql_affected_rows()." tracks.";
echo " $photoResult.";
updateTable('tblappointment', array('completed'=>null), "appointmentid = $id", 1);
echo "<br>un-completed  ".mysql_affected_rows()." visit.";
