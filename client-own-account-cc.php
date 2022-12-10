<? // client-own-account-cc.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

if($_SESSION) $_SESSION['client-own-account-cc-time'] = time();
if($_POST) {
	foreach($_POST as $k => $v) 
		if($k != 'password' && $k != 'password2')
			$params[] = "$k=$v";
	if($_POST['password']) {
		require_once "encryption.php";
		$nugget = "password={$_POST['password']}&password2={$_POST['password2']}";
		$params[] = "nugget=".nuggetize($nugget);
	}
	if($params) $params = "?".join('&', $params);
}
globalRedirect("client-own-account.php$params");