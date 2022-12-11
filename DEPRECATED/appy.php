<?
// appy.php
require_once "common/init_session.php";
// Determine access privs
if(!$_SESSION['auth_user_id']) {
	$file = 'login-page.php';
}
else 	$file = 'index.php';
//$url = "http://{$_SERVER["HTTP_HOST"]}/$file";

require $file;

/*
<head>
<meta http-equiv="refresh" content="0;URL=<?= $url ?>">
</head>
<?= strpos($url, 'login') ? 'You need to log in...' : 'You are logged in...' ?>

*/