<?
//check-loginid.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "system-login-fns.php";
locked('+o-,+z-');
extract(extractVars('userid,loginid', $_REQUEST));
$user = findSystemLoginWithLoginId($loginid, true);
//print_r($user);
if(!$user) echo "The username [$loginid] is available.";
else if($user['userid'] == $userid) echo "This user owns the username [$loginid].";
else echo "The username [$loginid] is in use by another user.";

