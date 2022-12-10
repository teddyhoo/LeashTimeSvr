<?php

require_once "common/init_session.php";
require_once "preference-fns.php";
require_once "login-fns.php";

$droot = substr($_SERVER["DOCUMENT_ROOT"], -1) == "/" ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"]."/";
set_include_path(get_include_path().PATH_SEPARATOR.$droot);

define("DEBUG",true);

// Let's scrub a bit, shall we?
$loginid = "{$_POST['user_name']}";
$passwd = "{$_POST['user_pass']}";

$login_failed = '';
// Failures: Unknown,Password,InactiveUser,RightsMissing,FoundNoBiz,BizInactive
$failure = '';

$noCookie = !isset($_COOKIE[session_name()]);  // for MSIE, which does not immediately register the session cookie (see splash-block.php)
if(!$_POST['json'] && $noCookie) {
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
	$note = join(',', $note);
/*	doQuery("insert into tbllogin set LoginID = '$loginid'".
					 ", Success = ".($failure ? 0 : 1).
					 ", FailureCause = '$failure'".
					 ", RemoteAddress = '{$_SERVER["REMOTE_ADDR"]}'".
					 ", browser = '"
					 	.mysql_real_escape_string($_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : $_SESSION['jsuseragent'])
					 	."'"
					 	.($note ? ", note = '$note'" : '')
					 .updateStamp());*/
}

else if ( isset ( $_POST['user_name'] ) ) {
	$userName = addslashes($_POST['user_name']);
	$password = addslashes($_POST['user_pass']);
	$wasMobile = $_SESSION["mobiledevice"];
	foreach($_SESSION as $k=>$v) unset($_SESSION[$k]);  // in case someone else is already logged in
	if($wasMobile) $_SESSION["mobiledevice"] = $wasMobile;
	require_once('common/init_db_common.php');
	
	$user = login($userName, $password);
	if(!$user) {
		$user = fetchUserWithTempPassword($userName, $password);
		// if temp password login, redirect to set password page
		$_SESSION['passwordResetRequired'] = true;
	}

	if(!$user) {
		$badLogin = fetchFirstAssoc("select * from tbluser where LoginID = '".mysql_real_escape_string($userName)."'");
		if($badLogin && $badLogin['tempPassword']) // clear temporary password, if any
			doQuery("UPDATE tbluser set tempPassword = '' WHERE userid = '{$badLogin['userid']}'");
		if(!$badLogin) $failure = 'U'; // Unknown
		else if(!passwordsMatch($password, $badLogin['Password']))
			$failure = 'P'; // Password
		else if($badLogin['active'])
			$failure = 'I'; // InactiveUser
	}
	if($user) {
		$userBizId = $user['bizid'];
		if($userBizId && $_POST['bizid']) {
			$targetPetBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$_POST['bizid']}' LIMIT 1", 1);
			if($targetPetBiz) {
				list($db_global, $dbhost_global, $dbuser_global, $dbpass_global) = array($db, $dbhost, $dbuser, $dbpass);
				reconnectPetBizDB($targetPetBiz['db'], $targetPetBiz['dbhost'], $targetPetBiz['dbuser'], $targetPetBiz['dbpass']);
				if($db != 'petcentral')
				 $mustMatchBizId = fetchPreference('brandedBusinessLoginsOnly') ? $_POST['bizid'] : false;
				reconnectPetBizDB($db_global, $dbhost_global, $dbuser_global, $dbpass_global);
			}
			else "bizid: {$_POST['bizid']}";
		}
		$failure = loginUser($user, $_REQUEST['clienttime'], $allowInactive=false, $mustMatchBizId);  // lives in login-fns.php, for invocation elsewhere.  clienttime only set by mobile login form
		// clear temporary password, if any
		if($user['tempPassword']) doQuery("UPDATE tbluser set tempPassword = '' WHERE LoginID = '$userName'");
	}
	$_SESSION['jsuseragent'] = $_POST['jsuseragent'];
	if(usingMobileSitterApp()) $note = $note ? "$note. mobile" : 'mobile';
		
	
	
	/*doQuery("insert into tbllogin set LoginID = '$userName'".
					 ", Success = ".($failure ? 0 : 1).
					 ", FailureCause = '$failure'".
					 ", RemoteAddress = '{$_SERVER["REMOTE_ADDR"]}'".
					 ", browser = '"
					 	.mysql_real_escape_string($_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : $_SESSION['jsuseragent'])
					 	."'"
					 	.($note ? ", note = '$note'" : '')
					 .updateStamp());*/
}

$browser = $_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : (
		$_SESSION['jsuseragent'] ? $_SESSION['jsuseragent'] : '--');
if(suspiciousUserAgent($browser)) $browser = "suspicious: ".sqlScrubbedString($browser);
//$browser = mysql_real_escape_string($browser);


//if($suspiciousLoginId) $suspiciousLoginId = mysql_real_escape_string($suspiciousLoginId);

$loginRecord =
	array('LoginID'=>($suspiciousLoginId ? $suspiciousLoginId : $_POST['user_name']), 
				'Success'=>($failure ? '0' : '1'), 
				'FailureCause'=>($failure ? $failure : '0'), 
				'RemoteAddress'=>$_SERVER["REMOTE_ADDR"],
				//'extbizid'=>$bizid,
				//'extloginpage'=>$failuredestination,
				'browser'=>$browser,
				'UserUpdatePtr'=>$_SESSION['auth_user_id'],
				'LastUpdateDate'=>date('Y-m-d H:i:s')
	);
if($note) $loginRecord['note'] = $note;
insertTable('tbllogin', $loginRecord, 1);




if($failure) {
	if($failure == 'R') { // rights mismatch
		require_once "email-fns.php";
		$scriptPrefs = array(); // not necessary
		//$installationSettings is already set
		sendEmail('support@leashtime.com', 
							"Login failure needs attention", 
							"User login attempt with ID [$userName] failed due to missing/corrupt/incorrect permissions.  Matt needs to see this and fix it ASAP.",
							$cc=null, $html=null, $senderLabel='', $bcc=null, $extraHeaders=null);
	}
	session_unset();
}

if($_POST['json']) {
	// possible results:
	// {"status":"failed","message":"bad login"}
	// {"status":"failed","message":"bad login","hint":"caps lock"}
	// {"status":"failed","message":"account locked"}
	// {"status":"ok"}
	if($failure) { // FAILURE
		if($failure == 'L') $message = "account locked"; 
		else $message .= "bad login";
		$result = array('status'=>'failed', 'message'=>$message);
		if(($failure == 'P') && ($_POST['user_pass'] == strtoupper($_POST['user_pass'])))
			$result['hint'] = 'caps lock';
	}
	else { // SUCCESS
	
		setLoginCookies();
	
		$result = array('status'=>'ok');
	}
//$result['params'] = print_r($failure,1);	
	header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
	header('Access-Control-Allow-Methods: POST, OPTIONS'); // GET, PUT, POST, DELETE, OPTIONS'
	header('Access-Control-Max-Age: 1000');
	header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');	
	
	echo json_encode($result);
}
else {
	if (!$failure) { // SUCCESS
		$pop = '';
		setLoginCookies();
		if($_POST['redirect']) {
			$dest = $_POST['redirect'];
			if(strpos($dest, 'login-page.php') !== FALSE 
				 || preg_match('/login$/', $dest) 
				 || preg_match('/login\?bizid=.*$/', $dest))
				 $dest = globalURL("index.php");
			header("Location: $dest");
			exit;
		}
		
		
		$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
		header("Location: $mein_host$this_dir/index.php");

	} else { // FAILURE
			$login_failed = $failure;
			if(session_id()) session_destroy();

			include "login-page.php";
	}
}
exit;

?>
