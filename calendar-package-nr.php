<? // calendar-package-nr.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "service-fns.php";
require_once "client-services-fns.php";
require_once "appointment-calendar-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-');
extract(extractVars('packageid,notifytime,notifynewschedule,notifyschedule,primaryProvider', $_REQUEST));

$client = fetchFirstAssoc(
	"SELECT *, CONCAT_WS(' ', fname, lname) as clientname
	FROM tblservicepackage LEFT JOIN tblclient ON clientid = clientptr 
	WHERE packageid = '$packageid'");
if(!$client) $error = 'Package not found.';
else {
	$appts = getPackageAppointments($packageid, $client['clientid']);
}

$windowTitle = "Short Term Package for {$client['clientname']}";
$extraBodyStyle = 'background:white;';
require "frame-bannerless.php";

// ***************************************************************************
if($error) {
	echo "<font color='red'></font>";
	exit;
}
echo "<h2>$windowTitle</h2>";
if($notifytime) {
	$notify = $notifynewschedule ? "notifynewschedule=$notifynewschedule" : "notifyschedule=$notifyschedule";
	$_SESSION['clientEditNotifyToken'] = time();
	$notify = "&notifytime={$_SESSION['clientEditNotifyToken']}&$notify";
	echoButton('', 'Done', "window.opener.location.href=\"client-edit.php?id={$client['clientid']}&tab=services$notify\";window.close();");
}
dumpCalendarLooks(100, 'lightblue');
require_once "surcharge-fns.php";
$surcharges = $_SESSION['surchargesenabled'] ? getPackageSurcharges($packageid, $client['clientid']) : array();
$packageDetails = getPackage($packageid);
$packageDetails['providerptr'] = $primaryProvider;
appointmentTable($appts, $packageDetails, $editable=false, $allowSurchargeEdit=true, $showStats=true, $includeApptLinks=false, $surcharges);
include "js-refresh.php";

?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='appointment-calendar-fns.js'></script>
