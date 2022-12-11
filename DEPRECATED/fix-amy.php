<? // fix-amy.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($db != 'woofies') {echo "Login as a woofies manager.";exit;}

$clients = fetchAssociationsKeyedBy("SELECT * FROM `tblclient` WHERE defaultproviderptr = 30", 'clientid');

echo count($clients)." clients.<p>";

$rpacks = fetchAssociationsKeyedBy("SELECT * FROM `tblrecurringpackage` WHERE clientptr IN (".join(',', array_keys($clients)).")", 'packageid');
foreach($rpacks as $rpack) $rclients[$rpack['clientptr']]=1;

echo count($rpacks)." recurring package versions for ".count($rclients)." clients.<p>";


$services = fetchAssociationsKeyedBy("SELECT * FROM tblservice WHERE providerptr = 0 AND packageptr IN (".join(',', array_keys($rpacks)).")", 'serviceid');
foreach($services as $service) $rservices[$service['packageptr']]=1;

echo count($services)." unassigned services in ".count($rservices)." recurring packages.<p>";

$appts = fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE providerptr = 0 AND serviceptr IN (".join(',', array_keys($services)).")", 'appointmentid');

echo count($appts)." unassigned recurring appts between <p>";

$dates = array(date('Y-m-d',strtotime("+2 years")), date('Y-m-d',strtotime("-2 years")));
foreach($appts as $appt) {
	if(strcmp($dates[0], $appt['date']) > 0) $dates[0] = $appt['date'];
	if(strcmp($dates[1], $appt['date']) < 0) $dates[1] = $appt['date'];
}
echo "{$dates[0]} and {$dates[1]}.";
echo "<hr>";

$npacks = fetchAssociationsKeyedBy("SELECT * FROM `tblservicepackage` WHERE clientptr IN (".join(',', array_keys($clients)).")", 'packageid');
foreach($npacks as $npack) $nclients[$npack['clientptr']]=1;

echo count($npacks)." nonrecurring package versions for ".count($nclients)." clients.<p>";


$nservices = fetchAssociationsKeyedBy("SELECT * FROM tblservice WHERE providerptr = 0 AND packageptr IN (".join(',', array_keys($npacks)).")", 'serviceid');
foreach($nservices as $service) $nservicesn[$service['packageptr']]=1;

echo count($nservicesn)." unassigned services in ".count($nservicesn)." nonrecurring packages.<p>";

$nappts = fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE providerptr = 0 AND serviceptr IN (".join(',', array_keys($nservices)).")", 'appointmentid');

echo count($nappts)." unassigned nonrecurring appts between<p>";

$dates = array(date('Y-m-d',strtotime("+2 years")), date('Y-m-d',strtotime("-2 years")));
foreach($nappts as $appt) {
	if(strcmp($dates[0], $appt['date']) > 0) $dates[0] = $appt['date'];
	if(strcmp($dates[1], $appt['date']) < 0) $dates[1] = $appt['date'];
}
echo "{$dates[0]} and {$dates[1]}.";



