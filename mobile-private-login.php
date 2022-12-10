<? // mobile-private-login.php?pw="+pw+"&goal

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "login-fns.php";

locked('p-');

if($_GET['reset']) { // AJAX -- reset timeout iff unlocked and
	if($_SESSION['mobile_private_zone_timeout'] 
			&& $_SESSION['mobile_private_zone_timeout'] > time()) {
		$_SESSION['mobile_private_zone_timeout'] = time() + $_SESSION['mobile_private_zone_timeout_interval'];
		echo $_SESSION['mobile_private_zone_timeout'] - $_SESSION['mobile_time_offset'];
	}
	exit;
}

// pw and goal

$url = $_GET['goal'];
if($argstart = strpos($url, '?')) {
	$args = explode('&', substr($url, $argstart+1));
	$url = substr($url, 0, $argstart+1);
	foreach($args as $i => $arg) if(strpos($arg, 'pagemessage=') === 0) unset($args[$i]);
	$url .= join('&', $args);
}
$user = login($_SESSION["auth_login_id"], $_GET['pw']);
//echo $_SESSION["auth_login_id"] ." / ". $_GET['pw']." - ".$user;exit;
if($user) {
	$_SESSION['mobile_private_zone_timeout'] = time() + $_SESSION['mobile_private_zone_timeout_interval'];
	//$url .= (strpos($url, '?') ? '&' : '?').'pagemessage='.urlencode($_SESSION['mobile_private_zone_timeout'].' > '.time());
}
else {
	$url .= (strpos($url, '?') ? '&' : '?').'pagemessage='.urlencode('You must first enter your password');
}

//echo "[{$_SESSION['mobile_private_zone_timeout']}]";exit;

globalRedirect($url);