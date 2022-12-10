<?
// maint-link-menu.php
// Find whether the current user is linked to any other LeashTime users
// (presumably in other dbs)
// offer a menu

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "maint-link-fns.php";

// opens in an iframe colorbox

echo getMenuColorboxContent();