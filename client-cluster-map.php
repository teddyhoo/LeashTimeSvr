<? // client-cluster-map.php
/*
Show a map for a given client showing:
client address
addresses of other clients within a specified radius
prospective clients as green pins
actual clients as blue pins
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require  "google2018upgrade.php";

if(!$useGoogle2018Version) require "googlev3/client-cluster-map.php";
else require "client-cluster-map2018.php";

