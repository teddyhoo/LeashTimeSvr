<? // cleansweep.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "js-gui-fns.php";

// Determine access privs
$locked = locked('o-');
if(!$_SESSION["staffuser"]) {
	echo "For LeashTimje Staff use only.";
	exit;
}


foreach(fetchCol0("SHOW TABLES") as $t) echo "$t<br>";
if($_POST) {
}

$tables = 
'relapptdiscount';
