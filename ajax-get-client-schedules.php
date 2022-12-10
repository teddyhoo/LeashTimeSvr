<? // ajax-get-client-schedules.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
//require_once "provider-fns.php";
//require_once "client-fns.php";
require_once "service-fns.php";

$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#ec');
$readOnlyVisits = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

$locked = locked(userRole() == 'd' ? 'd-' : 'o-');

echo "<root><recurring><![CDATA[";
str_replace("\n", "", dumpRecurringSchedule(null, $_GET['id']));
echo "]]></recurring>\n<nonrecurring><![CDATA[";
str_replace("\n", "", dumpNonRecurringSchedules2($_GET['id']));
echo "]]></nonrecurring></root>";
