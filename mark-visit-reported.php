<? // mark-visit-reported.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

locked('o-');

require_once "preference-fns.php";
$reported = setAppointmentProperty($_REQUEST['id'], 'reportIsPublic', date('Y-m-d H:i:s'));

echo "OK";
