<? // cc-login.php - make manager login again to do CC stuff
// included by other scripts and posted by itself
// also opened in new window directlyby main window
// Param: backlink - page to return to after successful login -or-
//				successaction - js action to take on success
//        password - on $_POST only
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "cc-processing-fns.php";

locked('o-');
$failure = false;
if(!adequateRights('*cm') && !adequateRights('cc')) { // RIGHTS: *cc - credit card processing permission (absoutely required), *cm - credit card info management permission (absoutely required)
	$failure = "Insufficient Access Rights";
}
if($failure) {
	$windowTitle = 'Insufficient Access Rights';
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}

if($_REQUEST['successaction'] && (!is_array($expiration = expireCCAuthorization()))) {
	// If popup and already logged in, just close window
		echo "<script language='javascript'>".($_REQUEST['successaction'] ? $_REQUEST['successaction'] : '')."window.close();</script>";
		exit;
}

if($_POST && $_POST['password']) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		include "common/init_db_common.php";
		$success = ccLogin($_POST['password']);
		list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
		include "common/init_db_petbiz.php";

	if($success) {
//if($_SESSION['auth_user_id'] == 11891) {echo print_r($_POST, 1); exit;}	
		if($_POST['backlink']) header("Location: ".globalURL($_POST['backlink']));
		else echo "<script language='javascript'>".($_POST['successaction'] ? $_POST['successaction'] : '')."window.close();</script>";
		exit;
	}
	else {
		echo "<script language='javascript'>if(window.opener) window.opener.location.href='index.php'; window.close();</script>";
		exit;
	}
}

$windowTitle = 'Credit Card Management Login';
require "frame-bannerless.php";
if($ccTestMode) $windowTitle .= " <font color='red'>TEST MODE</font>";
echo "<h2>$windowTitle</h2>";
$backlink = isset($backlink) ? $backlink : $_REQUEST['backlink'];
$successaction = isset($successaction) ? $successaction : $_REQUEST['successaction'];
?>
<form method='POST' name='loginform' action='cc-login.php'>
Please enter your LeashTime password: <input type='password' name='password' id='password' size=20 autocomplete='off'> <input type='button' value='Login' onClick='login()'>
<input type='hidden' name='backlink' value='<?= $backlink ?>'>
<input type='hidden' name='successaction' value='<?= $successaction ?>'>
</form>

<script language='javascript'>
document.getElementById('password').focus();

function login() {
	var pw = document.getElementById('password').value.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
	if(!pw) {
		alert('You must supply your LeashTime password before you can manage Credit Card information.');
		return;
	}
	document.loginform.submit();
}
</script>
