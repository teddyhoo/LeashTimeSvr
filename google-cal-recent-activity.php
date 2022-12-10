<? // google-cal-recent-activity.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "google-cal-fns.php";
require_once "field-utils.php";

locked('o-');

$s = recentCalendarActivity($_REQUEST['prov'], $entries=10);
if(!$s) $s = "No calendar activity on record.";

$name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$_REQUEST['prov']} LIMIT 1");

echo "<h2>Recent calendar activity for $name</h2>
<span style='font-size:1.2em'>$s</span>";