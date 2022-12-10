<? // ajax-set-unassigned-email.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";

locked('o-');
if(isset($_GET['email'])) {
	setPreference('unassignedemail', $_GET['email']);
	setPreference('unassigneddailyvisitsemail', $_GET['daily']);
	setPreference('unassignedweeklyvisitsemail', $_GET['weekly']);
}
echo 'ok';