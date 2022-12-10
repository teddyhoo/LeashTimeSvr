<? // maint-password-change.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');
require "frame-maintenance.php";

if($_SESSION['passwordResetRequired']) {
	require "password-change.php";
	require "frame-maintenance.php";
}
else require "password-change-page.php";