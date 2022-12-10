<? // client-activate.php
// called from response.php when the redirecturl points here
// u - user to be activated

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('');

$biz = fetchFirstAssoc("SELECT tblpetbiz.* FROM tbluser LEFT JOIN tblpetbiz ON bizid = bizptr WHERE userid = {$_REQUEST['u']} LIMIT 1");
if(!$biz) $error = "ERROR: User not found ({$_REQUEST['u']})";
else {
	updateTable('tbluser', array('active'=>1), "userid = {$_REQUEST['u']}", 1);
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$client = fetchRow0Col0("SELECT clientid FROM tblclient WHERE userid = {$_REQUEST['u']} LIMIT 1");
	logChange($client, 'tblclient', 'a', 'client-activate.php');
}
foreach($_SESSION as $k=>$v) unset($_SESSION[$k]);  // in case someone else is already logged in
if($error) {
	echo $error;
}
else {
	$_SESSION['frame_message'] = "Welcome!  Your account has been activated!";
	header("Location: ".globalURL("login-page.php?bizid={$biz['bizid']}"));
}