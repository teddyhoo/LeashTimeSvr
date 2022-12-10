<? // common/db_fns_switch.php
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') echo "DBO_TEST = $DBO_TEST<p>";

if($_SERVER['DBO_TEST'] && ($_SERVER['REMOTE_ADDR'] == '173.79.14.173' || $_SERVER['REMOTE_ADDR'] == ' 98.169.8.83' ) ) require_once "common/db_fns_pdo.php";
else require_once "common/db_fns_orig.php";