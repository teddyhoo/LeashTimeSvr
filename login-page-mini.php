<? // login-page-mini.php
require_once "common/init_session.php";

if(isIPad()) {
	$_SESSION["mobiledevice"] = 1;
	globalRedirect("login-page.php?mobile=1");
	exit;
}
	globalRedirect("login-page.php");

