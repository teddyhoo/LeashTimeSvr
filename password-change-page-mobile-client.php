<? //password-change-page-mobile-client.php

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

$customStyles = "
h2 {font-size:2.5em;} 
td {font-size:1.5em;} 
.standardInput {font-size:1.5em;}
.emailInput {font-size:1.5em;}
/*input:radio {font-size:1.5em;} */
label {font-size:1.5em;} 
.mobileLabel {font-size:2.0em;} 
.mobileInput {font-size:2.0em;}
textarea {font-size:1.2em;}
input.Button {font-size:20px;}
input.ButtonDown {font-size:2.0em;}
";

include "mobile-frame-client.php";
?>
<style>
table { width: 100%; }
td {font-size:0.7em;}
</style>
<?
// ***************************************************************************

$optional = true;
include "password-change-mobile.php";
include "mobile-frame-client-end.php";
