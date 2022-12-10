<? // visit-map.php
/*
Show a map for a given visit showing:
client address
sitter coordinates for that day
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require  "google2018upgrade.php";

if(!$useGoogle2018Version) require "googlev3/visit-map.php";
else require "visit-map2018.php";