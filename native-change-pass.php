<? // native-change-pass.php
// allow password change when temp password has been supplied
require_once "common/init_session.php";
require_once "native-sitter-api.php";

extract(extractVars('loginid,password,newpassword', $_REQUEST));

$userOrFailure = requestSessionAuthentication($loginid, $password);

$mysqlUserId = mysql_real_escape_string($loginid);
$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE loginid = '$mysqlUserId' LIMIT 1");
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
require_once "login-fns.php";


$mods = array('temppassword'=>'');

if($user && encryptPassword($password) == encryptPassword($user['temppassword'])) { // <==  WTF?? // test ($userOrFailure == 'T') won't work if user password==temppassword
	 // proceed with password change
	$mods['password'] = encryptPassword($newpassword);
}
else if(!$userOrFailure) {
	$errors = array('error'=>'Unknown error.');
}
else if(!is_string($userOrFailure)) {
	$errors = array('error'=>'Permanent password was supplied rather than temp password.');
}
else if(is_string($userOrFailure)) {
	$errors = array('error'=>$userOrFailure);
}
//require "init_db_common.php";
//if($user)
	updateTable('tbluser', $mods, "userid = {$user['userid']}", 1);

if(!$errors && mysql_error()) $errors = array('error'=>$userOrFailure);
if(mysql_error()) $errors['sqlerror'] = mysql_error();

if(!$errors) echo "OK";
else {
	if($biz) {
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		$prefs = fetchKeyValuePairs("SELECT property, value FROM tblpreference");
		foreach(explode(',', 'bizName,bizEmail,bizHomePage,bizPhone') as $k)
			if($prefs[$k]) $errors[$k] = $prefs[$k];
	}
	echo json_encode($errors);
}