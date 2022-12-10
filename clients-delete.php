<? // clients-delete.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "client-fns.php";

$locked = locked('o-');
if(!staffOnlyTEST()) {
	echo "Insufficient access rights.";
	exit;
}
extract($_REQUEST);

$doomed = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient WHERE active = 0 AND lname = 'delete'");


if($_POST['go'] && $doomed) {
	include "frame.html";
	set_time_limit(1200);
	foreach($doomed as $clientptr => $name) 
	{
		wipeClient($clientptr);
		echo "<h2>WIPED client $name [$clientptr]</h2>";
	}
	echo "<img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}

$pageTitle = "Delete Clients";
include "frame.html";
// ***************************************************************************


if(!$doomed) {
	echo '<h2>There are no clients to be deleted.</h2>(Inactive clients with last name = "DELETE")';
	echo "<p><img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}
?>
<form method='POST' name='delform'><input type='hidden' name='go' value=1></form>
<h2>Clients to be deleted:</h2>
(Inactive clients with last name = "DELETE")<p>
<? echoButton('', 'Delete Clients', "document.delform.submit()"); ?>
<ul>
<? foreach($doomed as $name) echo "<li>$name\n"; ?>
</ul>

<?
echo "<p><img src='art/spacer.gif' height=300>";
include "frame-end.html";
