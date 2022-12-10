<? //password-change-page-mobile.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
//require_once "password-change-page.php";
require_once "gui-fns.php";

// Determine access privs
$locked = locked();

extract($_REQUEST);

//if(userRole() == 'c') include "frame-client.html";
//else include "frame.html";
$pageIsPrivate = 0;  // the page itself calls for the password to be entered
$noExtension = 1;
include "mobile-frame.php";
?>
<style>
table { width: 100%; }
td {font-size:0.7em;}
</style>
<?
// ***************************************************************************

$optional = true;
include "password-change-mobile.php";