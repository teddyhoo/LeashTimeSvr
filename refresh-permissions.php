<? // refresh-permissions.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";

locked(userRole().'-');

$_SESSION["rights"] = fetchRow0Col0("SELECT rights FROM tbluser WHERE userid = {$_SESSION['auth_user_id']} LIMIT 1", 1);