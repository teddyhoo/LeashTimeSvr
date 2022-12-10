<? //password-change-page.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
//require_once "password-change-page.php";
require_once "gui-fns.php";

// Determine access privs
$locked = locked();

extract($_REQUEST);

$noSearchBox = $_SESSION['passwordResetRequired'];

$frameEndURL = "frame-end.html";

if($_SESSION["responsiveClient"]) {
	$extraHeadContent = "<style>td {font-size:1.1em;} .tiplooks {font-size:14pt;}</style>";
	include "frame-client-responsive.html";
	$frameEndURL = "frame-client-responsive-end.html";
}
else if(userRole() == 'c') include "frame-client.html";
else if(userRole() == 'z') ;
else include "frame.html";
// ***************************************************************************

$optional = true;
include "password-change.php";