<? // master-schedule.php
// starting
// ending
set_time_limit(3 * 60);
require_once "prov-schedule-fns.php";
require_once "master-schedule-email-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

//require_once "frame-bannerless.php";

// Determine access privs
if(userRole() == 'd') $locked = locked('d-');
else $locked = locked('o-');

$starting = $starting ? $starting : $_REQUEST['starting'];
$ending = $ending ? $ending : $_REQUEST['ending'];

$starting = dbDate($starting);
$ending = dbDate($ending);

$windowTitle = 'Master Schedule';
require "frame-bannerless.php";	
echo "<style>table {background:white;}</style>";
echo getMasterScheduleToEmail($starting, $ending);
