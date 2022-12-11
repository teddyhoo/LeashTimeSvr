<?php // mmd-login.php
// params: user_name, user_pass, expected_role

require_once "common/init_session.php";
require_once "preference-fns.php";
require_once "login-fns.php";

$droot = substr($_SERVER["DOCUMENT_ROOT"], -1) == "/" ? $_SERVER["DOCUMENT_ROOT"] : $_SERVER["DOCUMENT_ROOT"]."/";
set_include_path(get_include_path().PATH_SEPARATOR.$droot);

if(!defined(DEBUG)) define("DEBUG",true);

$logChangeTable = $logChangeTable  ? $logChangeTable  : 'mmd-login';


$headers = apache_request_headers();
}
foreach($headers as $hdr=>$val)
    if(strtoupper($hdr) == 'CONTENT-TYPE') $contentType = strtoupper($val);

if(strpos("$contentType", 'JSON') !== FALSE) {
    $INPUT_ARRAY = json_decode(file_get_contents('php://input'), true);   // $INPUT_ARRAY is a variable defined in this script only
}
else {
	$INPUT_ARRAY = DEBUG ? $_GET : $_POST;

	$login_failed = '';
	// Failures: Unknown,Password,InactiveUser,RightsMissing,FoundNoBiz,BizInactive
	$failure = '';

	require_once('common/init_db_common.php');
	logChange(999, $logChangeTable, 'L', "TEST: [[{$_SERVER["CONTENT_TYPE"]}]] ".print_r($INPUT_ARRAY, 1));

	if($INPUT_ARRAY['json']) $INPUT_ARRAY = json_decode($INPUT_ARRAY['json'], 'ASSOC');
}

if(!$INPUT_ARRAY) {
	$failure = 'E'; // empty parameters
	require_once('common/init_db_common.php');
	//logChange(999, $logChangeTable, 'L', "EMPTY INPUT: CONTENT_TYPE = {$_SERVER["CONTENT_TYPE"]}");
}

/*$noCookie = !isset($_COOKIE[session_name()]);  // for MSIE, which does not immediately register the session cookie (see splash-block.php)
if(!$_POST['json'] && $noCookie) {
	$failure = 'C';
}*/

else if (!($INPUT_ARRAY['user_name'] && $INPUT_ARRAY['user_pass'] && $INPUT_ARRAY['expected_role'])) {
	$failure = 'Missing parameters'; // missing parameters
	foreach(array('user_name','user_pass','expected_role') as $p)
		if(!$INPUT_ARRAY[$p]) $missing[] = $p;
	require_once('common/init_db_common.php');
	logChange(999, $logChangeTable, 'L', "missing parameters: ".join(', ', $missing));
}
else if ( isset ( $INPUT_ARRAY['user_name'] ) ) {
	$userName = addslashes($INPUT_ARRAY['user_name']);
	$password = addslashes($INPUT_ARRAY['user_pass']);
	$wasMobile = $_SESSION["mobiledevice"];
	foreach($_SESSION as $k=>$v) unset($_SESSION[$k]);  // in case someone else is already logged in
	if($wasMobile) $_SESSION["mobiledevice"] = $wasMobile;
	require_once('common/init_db_common.php');
	
logChange(999, $logChangeTable, 'L', "[$userName] [$password] [{$INPUT_ARRAY['expected_role']}]");
	
	if(suspiciousCredentials($userName, $password)) {
		// NOTE: the standard login page limits passwd to 45 chars and loginid to 65 chars
		//  loginid MAY be up to 65 chars, but in practice no legit password has yet exceeded 45 chars in length (8/4/2020)
		require_once('common/init_db_common.php');
		$failure = 'H';
		if($suspiciousLoginId = suspiciousUserName($userName)) {
			$suspiciousLoginId = 'suspicious:'.sqlScrubbedString($userName);
			$note[] = 'log('.strlen($userName).')';
		}
		if(suspiciousPassword($password)) $note[] = 'pass('.strlen($password).')';
	}
	else {
		$user = login($userName, $password);
		if(!$user) {
			$user = fetchUserWithTempPassword($userName, $password);
			// if temp password login, redirect to set password page
			$_SESSION['passwordResetRequired'] = true;
		}
		if(!$user) {
			$badLogin = fetchFirstAssoc("select * from tbluser where LoginID = '".mysqli_real_escape_string($userName)."'");
			if($badLogin && $badLogin['tempPassword']) // clear temporary password, if any
				doQuery("UPDATE tbluser set tempPassword = '' WHERE userid = '{$badLogin['userid']}'");
			if(!$badLogin) $failure = 'U'; // Unknown
			else if(!passwordsMatch($password, $badLogin['password']))
				$failure = 'P'; // Password
			else if(!$badLogin['active'])
				$failure = 'I'; // InactiveUser 
	}
		}
		if($user) {
			$foundRole = $user['rights']; // test rights, if found
			if($foundRole) {
				if($INPUT_ARRAY['expected_role'] == 'm' && in_array($foundRole[0], array('o', 'd'))) $correctRole = true;
				else $correctRole = $INPUT_ARRAY['expected_role'] == $foundRole[0];
				if(!$correctRole) $failure = 'X'; // user does not have expected role
			}
			if(!$failure) $failure = loginUser($user, $_REQUEST['clienttime']);  // lives in login-fns.php, for invocation elsewhere.  clienttime only set by mobile login form
			// clear temporary password, if any
			if($user['tempPassword']) doQuery("UPDATE tbluser set tempPassword = '' WHERE LoginID = '$userName'");
		}
	
	}
	
	$_SESSION['jsuseragent'] = $INPUT_ARRAY['jsuseragent'];
	
	$browser = $_SESSION['jsuseragent'] ? $_SESSION['jsuseragent'] : $_SERVER["HTTP_USER_AGENT"];
	
	if(suspiciousUserAgent($browser)) $browser = "suspicious: ".sqlScrubbedString($browser);
	$browser = mysqli_real_escape_string($browser);
	$loginRecord = 
		array('LoginID'=>($suspiciousLoginId ? $suspiciousLoginId : $userName), 
					'Success'=>($failure ? '0' : '1'), 
					'FailureCause'=>($failure ? $failure : '0'), 
					'RemoteAddress'=>$_SERVER["REMOTE_ADDR"],
					'browser'=>$browser,
					'UserUpdatePtr'=>$_SESSION['auth_user_id'],
					'LastUpdateDate'=>date('Y-m-d H:i:s')
		);
		if(usingMobileSitterApp()) $note[] = 'mobile';
		if($note) $loginRecord['note'] = join(',', $note);
		insertTable('tbllogin', $loginRecord, 1);
	
/*	doQuery("insert into tbllogin set LoginID = '$userName'".
					 ", Success = ".($failure ? 0 : 1).
					 ", FailureCause = '$failure'".
					 ", RemoteAddress = '{$_SERVER["REMOTE_ADDR"]}'".
					 ", browser = '"
					 	.mysqli_real_escape_string($_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : $_SESSION['jsuseragent'])
					 	."'"
					 	.(usingMobileSitterApp() ? ", note = 'mobile'" : '')
					 .updateStamp());*/
					 
}
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

// possible results:
// {"status":"failed","message":"bad login"}
// {"status":"failed","message":"bad login","hint":"caps lock"}
// {"status":"failed","message":"account locked"}
// {"status":"ok"}
if($failure) { // FAILURE
	$message = loginFailureExplanation($failure);
	if(!$message) $message = $failure;
	$result = array('status'=>'failed', 'message'=>$message, 'failurecode'=>$failure);
	if($failure == 'P' && !$INPUT_ARRAY['user_pass'])
		$result['hint'] = 'no password supplied';
	else if(($failure == 'P') && ($INPUT_ARRAY['user_pass'] == strtoupper($INPUT_ARRAY['user_pass'])))
		$result['hint'] = 'caps lock';
}
else { // SUCCESS

	setLoginCookies();

	$result = array('status'=>'ok');
}
//$result['params'] = print_r($failure,1);	
header("Content-type: application/json");
header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
header('Access-Control-Allow-Methods: GET, OPTIONS'); // GET, PUT, POST, DELETE, OPTIONS'
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');	

echo json_encode($result);

exit;

?>
