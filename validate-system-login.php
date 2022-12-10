<? // validate-system-login.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "system-login-fns.php";
$locked = locked('o-');

extract(extractVars('roleid,role,loginid', $_REQUEST));

$results = validateSystemLogin($roleid, $role, $loginid);

echo "<ul>\n";
foreach((array)$results['errors'] as $error)
	echo "<li style='color:red'>$error\n";
foreach((array)$results['notes'] as $note)
	echo "<li style='color:black'>$note\n";
echo "</ul>";	
