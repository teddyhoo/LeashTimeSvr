<? // temp-get-prefs.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";

$_SESSION["preferences"] = fetchPreferences();