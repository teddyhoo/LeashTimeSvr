<? // google-cal-prov.php
// Edit email prefs for logged in provider
// params: id - clientid

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Determine access privs
$locked = locked('p-');

if(mattOnlyTEST()) require "google-cal-prov-api-V3.php";
else require "google-cal-prov-ZEND.php";