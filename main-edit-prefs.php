<? // maint-edit-prefs.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
require_once "system-login-fns.php";


// Verify login information here
locked('z-');
extract(extractVars('id,property,value,action', $_REQUEST));

if($id && $action == 'save') {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	reconnectPetBizDB($BIZ['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
	