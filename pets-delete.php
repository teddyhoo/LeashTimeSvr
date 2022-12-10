<? // pets-delete.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "client-fns.php";

$locked = locked('o-');
if(!staffOnlyTEST()) {
	echo "Insufficient access rights.";
	exit;
}
extract($_REQUEST);

$doomed = fetchKeyValuePairs(
	"SELECT petid, 
			CONCAT('pet id: ',petid, ' [', CONCAT_WS(' ', IF(sex='m', 'male', IF(sex='f', 'female', '{unknown sex}')), breed, type), '] ',
			'(client #', clientid, ' ', fname, ' ', lname,')') 
		FROM tblpet
		LEFT JOIN tblclient ON clientid=ownerptr
		WHERE tblpet.active = 0 AND name = 'delete'");


if($_POST['go'] && $doomed) {
	include "frame.html";
	set_time_limit(300);
	foreach($doomed as $petptr => $name) 
	{
		wipePet($petptr);
		echo "<h2>WIPED $name</h2>";
	}
	echo "<img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}

$pageTitle = "Delete Pets";
include "frame.html";
// ***************************************************************************


if(!$doomed) {
	echo '<h2>There are no pets to be deleted.</h2>(Inactive pets with name = "DELETE")<p>';
	echo "<img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}
?>
<form method='POST' name='delform'><input type='hidden' name='go' value=1></form>
<h2>Pets to be deleted:</h2>
(Inactive pets with last name = "DELETE")<p>
<? echoButton('', 'Delete Pets', "document.delform.submit()"); ?>
<ul>
<? foreach($doomed as $name) echo "<li>$name\n"; ?>
</ul>

<?
echo "<img src='art/spacer.gif' height=300>";
include "frame-end.html";
