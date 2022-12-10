<? // confirmation.php
// called from response.php when the redirecturl points here
// token - token associated with confirmation
// logout - end session when non-null

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "confirmation-fns.php";
require_once "gui-fns.php";

$filter = $_GET['token'] ? "token = '{$_GET['token']}'" : "confid = '{$_GET['confid']}'";
$confirmation = fetchFirstAssoc(
		"SELECT respondentptr, respondenttable 
		 FROM tblconfirmation
		 WHERE $filter LIMIT 1");

$locked = locked('');

$badCreds = false;
if(($role = userRole()) != 'o') {
//echo "role: [$role] respondentptr: [{$confirmation['respondentptr']}]  clientid: [{$_SESSION["clientid"]}]<p>";
	if(!($confirmation['respondenttable'] == 'tblclient' && $role == 'c' && $confirmation['respondentptr'] == $_SESSION["clientid"])
			&& !($confirmation['respondenttable'] == 'tblprovider' && $role == 'p' && $confirmation['respondentptr'] == $_SESSION["providerid"]) 
		)
			$badCreds = true;
}

$frame = $role == 'c' ? 'frame-client.html' : 'frame.html';
$logout = $_GET[$logout];

if(!$badCreds) {
	if($_GET['token']) confirmWithToken($_REQUEST['token']);
	else confirm($_REQUEST['confid']);
}

if($logout) {
	session_unset();
	session_destroy();
}
include $frame;

if($badCreds) echo "Insufficient rights to execute this confirmation";
else {
	echo "Thanks for confirming!<p>";
	if(!$logout) {
		echo fauxLink('Go to Home Page', 'document.location.href="index.php";');
		echo "<p>";
		echo fauxLink('Log Out', 'document.location.href="login-page.php?logout=1";');
	}
}
include "frame-end.html";
exit;
