<?
// response.php
// Look up and consume a token.  Then redirect to the appropriate URL.


$token = $_GET['token'];
if(!$token) {
	echo "Bad request: no token";
	exit;
}

require_once "response-token-fns.php";
require_once "preference-fns.php";
require_once "common/init_session.php";
require_once "common/init_db_common.php";

if(userRole()) {
	$settings = ensureInstallationSettings();
	session_unset();
  session_destroy();
	session_name($settings['sessionName']);
  session_start();
}

include "common/init_db_common.php";
$tokenRow = findTokenRow($token);
if(!$tokenRow) {
	echo "Sorry, this link can be used just once.";
	logChange(999, 'tblresponsetoken', 'x', "[$token] not found");
	
	exit;
}
$tokenbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$tokenRow['bizptr']} LIMIT 1", 1);

if($tokenRow['useonce'] || ($tokenRow['expires'] && time() > strtotime($tokenRow['expires']))) //!in_array($tokenbiz['db'], array('dogslife', 'wisconsinpetcare'))) {
	consumeTokenRow($token);



if($tokenRow['expires'] && (time() > strtotime($tokenRow['expires']))) {
	echo "Sorry, this link has expired.";
	logChange(999, 'tblresponsetoken', 'x', "[$token] expired");
	exit;
}

// Good token.  Set up to redirect


if($tokenRow['loginuserid'] == SYSTEM_USER) {
	$biz = fetchFirstAssoc("SELECT * from tblpetbiz WHERE bizid = {$tokenRow['bizptr']} LIMIT 1");
	$_SESSION["dbhost"] = $biz["dbhost"];
	$_SESSION["db"] = $biz["db"];
	$_SESSION["dbuser"] = $biz["dbuser"];
	$_SESSION["dbpass"] = $biz["dbpass"];
	$_SESSION["bizname"] = $biz["bizname"];
	$_SESSION["bizptr"] = $biz["bizptr"];
	$_SESSION["bizfiledirectory"] = "bizfiles/biz_{$biz["bizptr"]}/";
	$_SESSION["preferences"] = fetchPreferences();
	$_SESSION["rights"] = "o-";
	$_SESSION["auth_user_id"] = SYSTEM_USER;
	$_SESSION["burnafterreading"] = true;
	$user = array('db'=>$biz["db"], 'dbhost'=>$biz["dbhost"], 'dbuser'=>$biz["dbuser"], 'dbpass'=>$biz["dbpass"]);
}
else {
	$user = fetchFirstAssoc(
		"SELECT tbluser.*, dbhost, dbname, dbuser, dbpass, db, activebiz
			FROM tbluser 
			LEFT JOIN tblpetbiz ON bizid = bizptr
			WHERE bizptr = {$tokenRow['bizptr']} AND userid = {$tokenRow['loginuserid']} 
			LIMIT 1");
	require_once "login-fns.php";
	if($failure = loginUser($user)){
		echo "Sorry, we could not log you in. [$failure]";
		exit;
	}
}
list($db, $dbhost, $dbuser, $dbpass) = array($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass']);

include "common/init_db_petbiz.php";

header("Location: {$tokenRow['url']}");
