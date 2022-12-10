<?php // login-external.php


require_once "common/init_session.php";
require_once "preference-fns.php";
require_once "login-fns.php";

$errors = '';

if(!isset($_POST['user_name']) || !trim($_POST['user_name'])) $errors[] = 'User name not supplied.';
if(!isset($_POST['user_pass']) || !trim($_POST['user_pass'])) $errors[] = 'Password not supplied.';
if(!isset($_POST['bizid']) || !trim($_POST['bizid'])) $errors[] = 'bizid not supplied.';
if(!isset($_POST['failuredestination']) || !trim($_POST['failuredestination'])) $errors[] = 'failuredestination not supplied.';
if($errors) {
	echo '<ul><li>'.join('<li>', $errors).'</ul>';
	exit;
}

$downForMaintenance = false; //$_SERVER['REMOTE_ADDR'] == '68.225.89.173';
if($downForMaintenance) {
	header("Location: https://leashtime.com/login-page.php?logout=1&bizid={$_POST['bizid']}");
	exit;
}

// Let's scrub a bit, shall we?
$loginid = "{$_POST['user_name']}";
$passwd = "{$_POST['user_pass']}";

$login_failed = '';
// Failures: Unknown,Password,InactiveUser,RightsMissing,FoundNoBiz,BizInactive
$failure = '';

//$noCookie = !isset($_COOKIE[session_name()]);  // for MSIE, which does not immediately register the session cookie (see splash-block.php)
require_once('common/init_db_common.php');
if($noCookie) {
	$failure = 'C';
}

else if(suspiciousCredentials($loginid, $passwd)) {
	// NOTE: the standard login page limits passwd to 45 chars and loginid to 65 chars
	//  loginid MAY be up to 65 chars, but in practice no legit password has yet exceeded 45 chars in length (8/4/2020)
	require_once('common/init_db_common.php');
	$failure = 'H';
	if(suspiciousUserName($loginid)) {
		$suspiciousLoginId = 'suspicious:'.sqlScrubbedString($loginid);
		$note[] = 'log('.strlen($loginid).')';
	}
	if(suspiciousPassword($passwd)) $note[] = 'pass('.strlen($passwd).')';

/* else if(strlen($loginid) > 45 || strlen($passwd) > 45) 
	// NOTE: the standard login page limits passwd to 45 chars and loginid to 65 chars
	//  loginid MAY be up to 65 chars, but in practice no legit password has yet exceeded 45 chars in length (8/4/2020)
	require_once('common/init_db_common.php');
	$failure = 'H';
	if(strlen($loginid) > 45) $note[] = 'log('.strlen($loginid).')';
	if(strlen($passwd) > 45) $note[] = 'pass('.strlen($passwd).')';
*/
}

else if ( isset ( $_POST['user_name'] ) ) {
	foreach($_SESSION as $k=>$v) unset($_SESSION[$k]);  // in case someone else is already logged in
	
	$user = login($_POST['user_name'], $_POST['user_pass']);
	if(!$user) {
		$user = fetchUserWithTempPassword($_POST['user_name'], $_POST['user_pass']);
		// if temp password login, redirect to set password page
		$_SESSION['passwordResetRequired'] = true;
	}

	if(!$user) {
		$badLogin = fetchFirstAssoc("select * from tbluser where LoginID = '{$_POST['user_name']}'");
		if($badLogin && $badLogin['tempPassword']) // clear temporary password, if any
			doQuery("UPDATE tbluser set tempPassword = '' WHERE LoginID = '{$_POST['user_name']}'");
		if(!$badLogin) $failure = 'U'; // Unknown
		else if(!passwordsMatch($_POST['user_pass'], $badLogin['Password']))
			$failure = 'P'; // Password
		else if($badLogin['active'])
			$failure = 'I'; // InactiveUser
	}
	if($user) {
		$failure = loginUser($user);  // lives in login-fns.php, for invocation elsewhere
		// clear temporary password, if any
		if($user['tempPassword']) doQuery("UPDATE tbluser set tempPassword = '' WHERE LoginID = '{$_POST['user_name']}'");
	}
}

$browser = $_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : (
		$_SESSION['jsuseragent'] ? $_SESSION['jsuseragent'] : '--');
if(suspiciousUserAgent($browser)) $browser = "suspicious: ".sqlScrubbedString($browser);
$browser = mysql_real_escape_string($browser);

$failuredestination = trim("{$_POST['failuredestination']}");
if(suspiciousUserAgent($failuredestination)) $failuredestination = "suspicious: ".sqlScrubbedString($failuredestination);
$failuredestination = mysql_real_escape_string($failuredestination);

$bizid = trim("{$_POST['bizid']}");
if(suspiciousUserAgent($bizid)) $bizid = "suspicious: ".sqlScrubbedString($bizid);
$bizid = mysql_real_escape_string($bizid);


$loginRecord = 
	array('LoginID'=>($suspiciousLoginId ? $suspiciousLoginId : $_POST['user_name']), 
				'Success'=>($failure ? '0' : '1'), 
				'FailureCause'=>($failure ? $failure : '0'), 
				'RemoteAddress'=>$_SERVER["REMOTE_ADDR"],
				'extbizid'=>$bizid,
				'extloginpage'=>$failuredestination,
				'browser'=>$browser,
				'UserUpdatePtr'=>$_SESSION['auth_user_id'],
				'LastUpdateDate'=>date('Y-m-d H:i:s')
	);
if($note) $loginRecord['note'] = join(',', $note);

insertTable('tbllogin', $loginRecord, 1);
	
if($failure) session_unset();


if (!$failure) {
	$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
	header("Location: $mein_host$this_dir/index.php");

} 
else {
	$login_failed = $failure;
	if(session_id()) session_destroy();
	$dest = $_POST['failuredestination'];
	$dest .=  strpos($dest, '?') ? "&loginfailed=1" : "?loginfailed=1";
	header("Location: $dest");
}
exit;

?>
