<? // provider-map.php
/*
Show a map for a given sitter showing:
sitter address (optional)
client addresses on a give day (info: address, appt times)
sitter coordinates for that day
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require  "google2018upgrade.php";

if(!$useGoogle2018Version) require "googlev3/provider-map.php";
else require "provider-map2018.php";

