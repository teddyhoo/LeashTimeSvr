<? // client-provider-map.php
/*
Show a map for a given client showing:
client address
addresses of sitters within a specified radius
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require  "google2018upgrade.php";

if(!$useGoogle2018Version) require "googlev3/client-provider-map.php";
else require "client-provider-map2018.php";
