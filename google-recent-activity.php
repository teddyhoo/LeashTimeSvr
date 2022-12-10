<? // google-recent-activity.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "google-cal-fns.php";
require_once "field-utils.php";

locked('o-');

echo recentCalendarActivity($_REQUEST['prov'], $entries=3);
