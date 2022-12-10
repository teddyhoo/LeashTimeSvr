<? // master-schedule-email.php
// starting
// days
require_once "prov-schedule-fns.php";
require_once "master-schedule-email-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

//require_once "frame-bannerless.php";

// Determine access privs
$locked = locked('o-');
if($_REQUEST['email']) emailMasterSchedule();
else if(!$_REQUEST['email']) {
	$t = microtime(1);
	$starting = $starting ? $starting : $_REQUEST['starting'];
	$days = $days ? $days  : ($_REQUEST['days'] ? $_REQUEST['days'] : 7);

	$starting = $starting ? dbDate($starting) : date('Y-m-d');
	$plusdays = $days - 1;
	$ending = date('Y-m-d', strtotime("+ $plusdays days", strtotime($starting)));
	echo getMasterScheduleToEmail($starting, $ending);
	echo microtime(1) - $t;
}
