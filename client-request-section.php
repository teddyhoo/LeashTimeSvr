<?
// client-request-section.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "request-fns.php";
require_once "gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');


extract($_REQUEST);

clientRequestSection($updateList, $unresolvedOnly, $offset);