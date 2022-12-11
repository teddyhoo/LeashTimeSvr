<? // set-birthmarks.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";

$services = fetchAssociations("SELECT serviceid, timeofday, servicecode FROM tblservice");

$servicemarks = array();
foreach($services as $service) $servicemarks[$service['serviceid']] = getServiceSignature($service);


$result = doQuery("SELECT appointmentid, birthmark, serviceptr FROM tblappointment", $showErrors=1);
$n=0;
while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	if(!$servicemarks[$row['serviceptr']]) echo "Bad servicemark for service: [{$row['serviceptr']}] appointment: [{$row['appointmentid']}]<br>";
	else if(!$row['birthmark']) {
		doQuery("UPDATE tblappointment SET birthmark='{$servicemarks[$row['serviceptr']]}' 
			WHERE (birthmark IS NULL OR birthmark = '') AND appointmentid = {$row['appointmentid']}", 1);
		$n++;
	}
}			

echo "$n birthmarks set.<p>";

echo fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE birthmark is NULL OR birthmark = ''")." appointments have NULL birthmarks.";