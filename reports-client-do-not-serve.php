<? // reports-client-do-not-serve.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "provider-fns.php";

$locked = locked('o-');


$providers = providerNamesWhoWillNotServeClient($_GET['clientptr']);
$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '{$_GET['clientptr']}' LIMIT 1", 1);

require "frame-bannerless.php";
echo "<h2>Sitters Who Will Not Serve $clientName</h2>";

if(!$providers) echo "No sitters are restricted from serving $clientName.";
else echo "Click a sitter name to edit that sitter.<p>";

foreach($providers as $provid => $nm) {
	fauxLink($nm, "parent.location.href=\"provider-edit.php?id=$provid&tab=employment\"", 0, 'Edit this sitter.');
	echo "<br>";
}