<? // passthru.php

require_once "common/init_db_common.php";

$target = $_REQUEST['target'];

insertTable('tblclickcount',
	array('time'=>date('Y-m-d H:i:s'),
		'target'=>$target,
		'ipaddress'=>$_SERVER["REMOTE_ADDR"],
		'agent'=>mysqli_real_escape_string($_SERVER["HTTP_USER_AGENT"])),
	1);

header("Location: $target");
