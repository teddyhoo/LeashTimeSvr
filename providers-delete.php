<? // providers-delete.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "provider-fns.php";

$locked = locked('o-');
if(!staffOnlyTEST()) {
	echo "Insufficient access rights.";
	exit;
}
extract($_REQUEST);

$doomed = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE active = 0 AND lname = 'delete'");


if($_POST['go'] && $doomed) {
	include "frame.html";
	foreach($doomed as $providerptr => $name) 
	{
		wipeProvider($providerptr);
		echo "<h2>WIPED sitter $name [$providerptr]</h2>";
	}
	echo "<p><img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}

$pageTitle = "Delete Sitters";
include "frame.html";
// ***************************************************************************


if(!$doomed) {
	echo '<h2>There are no sitters to be deleted.</h2>(Inactive sitters with last name = "DELETE")';
	echo "<p><img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}
?>
<form method='POST' name='delform'><input type='hidden' name='go' value=1></form>
<h2>Sitters to be deleted:</h2>
(Inactive sitters with last name = "DELETE")<p>
<? echoButton('', 'Delete Sitters', "document.delform.submit()"); ?>
<ul>
<? foreach($doomed as $name) echo "<li>$name\n"; ?>
</ul>

<?
echo "<p><img src='art/spacer.gif' height=300>";
include "frame-end.html";
