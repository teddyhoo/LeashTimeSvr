<? //itinerary.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require  "google2018upgrade.php";

if(!$useGoogle2018Version) require "googlev3/itinerary.php";
else require "itinerary2018.php";