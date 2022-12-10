<? // rights-check.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";

//locked('o-');
$_SESSION["rights"] = fetchRow0Col0("SELECT rights FROM tbluser WHERE userid = {$_SESSION["auth_user_id"]} LIMIT 1");
echo $_SESSION["rights"];