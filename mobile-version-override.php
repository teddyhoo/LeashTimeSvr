<? // mobile-version-override.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

locked('p-');
$_SESSION["mobileVersionOverride"] = isset($_GET['on']) ? $_GET['on'] : 1;
echo "mobileVersionOverride = ".(isset($_GET['on']) && $_GET['on'] ? 'on' : 'off');