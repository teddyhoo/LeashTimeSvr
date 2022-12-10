<? // common/db_fns_switch.php

if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') require_once "common/db_fns_pdo.php";
else require_once "common/db_fns_orig.php";