<?
//homepage_provider.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "request-fns.php";

// Determine access privs
//$locked = locked('o-');
extract($_REQUEST);
$shrink = isset($shrink) ? explode(',', $shrink) : array();

$pageTitle = "Home";

include "frame.html";
// ***************************************************************************
include "frame-end.html";
?>
