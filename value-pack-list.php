<? // value-pack-list.php
$scriptstarttime = microtime(1);
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "value-pack-fns.php";
require_once "gui-fns.php";
$locked = locked('o-');

if(!$_SESSION['preferences']['enableValuePacks']) {
	echo "Value Packs feature not enabled";
}
else {
	setupValuePackTable();

	valuePacksTable($_GET['id']);
}