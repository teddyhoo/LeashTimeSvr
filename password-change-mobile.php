<? //password-change-mobile.php

$goHome = false;

$optional = isset($optional) ? $optional : false;

if($_REQUEST['message']) {
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	include "mobile-frame.html";
	echo "<font color='green'><h2>{$_REQUEST['message']}</h2></font>";
	exit;
}

if(isset($_REQUEST['password2'])) {  // since client-own-account-cc does a redirect, we cannot restrict to $_POST
	require_once "login-fns.php";
	$moveOn = true;
	if(!$optional && $_REQUEST['token'] != $_SESSION['passwordchangetoken'])
		$message = 'This request can be honored only once.';
	else {
		unset($_SESSION['passwordchangetoken']);
		extract($_REQUEST);
		$oldPasswordFailed = false;
		if(isset($_REQUEST['oldpassword'])) {
			include "common/init_db_common.php";
			$oldPasswordFailed = 
				!passwordsMatch($_REQUEST['oldpassword'], 
											fetchRow0Col0("SELECT password FROM tbluser WHERE userid = {$_SESSION['auth_user_id']} LIMIT 1"));
		}
		if(!$oldPasswordFailed && $password && $password == $password2) {
			include "common/init_db_common.php";
			$encPassword = encryptPassword($password);
			doQuery("UPDATE tbluser set password = '$encPassword' WHERE userid = {$_SESSION["auth_user_id"]}");
			$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
			unset($_SESSION['passwordResetRequired']);
			
			//globalRedirect('password-change.php?message='.urlencode('Your password has been changed.<p>'.
			//	"Return to <a href='index.php'>Home</a>"));
			echo "<font color='green'><h2>Your password has been changed.<p></h2></font>Return to <a href='index.php'>Home</a>";

			exit;
			
		}
		else if($oldPasswordFailed) {
			$message = "That is not your current password.";
			$moveOn = false;
		}

		
	}
}
if($optional) 	$action = 'password-change-page-mobile.php';

else if(!$_SESSION || !$_SESSION['passwordResetRequired']) {
	// add code here to start the session and dump the frame
	$action = 'password-change.php';
}
else {
	$action = 'index.php';
}
// frame start has already been output

if($message) {
	$color = $goHome ? 'green' : 'red';
	echo "<font color='$color'><h2>$message</h2></font>";
	if($moveOn) {
		exit;
	}
}

require_once "response-token-fns.php";
$_SESSION['passwordchangetoken'] = randomToken();


?>
<form name='changepassword' method='POST' action='<?= $action ?>'>
<? hiddenElement('token', $_SESSION['passwordchangetoken']); 

if($optional) echo "<h2>Password Change</h2>";
else echo "<span style='color:red;'><h2>Password change required</h2></span>";
?>

<table>
<? 
$step = 0;

if($optional) {
?>	
<tr><td colspan=2><h3>Step <?= $step = $step + 1; ?>: Please enter your current password.</h3></td></tr>
<tr><td>Current Password:</td><td><input type = 'password' name='oldpassword' autocomplete='off'></td></tr>
<?
}
?>
<tr><td colspan=2><h3>Step <?= $step = $step + 1; ?>: Please enter a new password.</h3></td></tr>

<tr><td>Password:</td><td><input type = 'password' name='password' autocomplete='off'></td></tr>
<tr><td colspan=2><h3>Step <?= $step = $step + 1; ?>:  Retype the password to ensure accuracy.</h3></td></tr>
<tr><td>Retype Password:</td><td><input type = 'password' name='password2' autocomplete='off'></td></tr>
<tr><td colspan=2><h3>Step <?= $step = $step + 1; ?>:  Set Password.</h3></td></tr>
<tr><td><? echoButton('', 'Set Password', 'checkAndSubmit()'); ?></td>
<? 
function localURL($url) {
	$dir = basename(dirname($_SERVER['SCRIPT_NAME']));
	$dir = $dir ? '../' : '';
	return "$dir$url";
}

if(!$optional) { 
			$logoutURL = localURL('login-page.php?logout=1');
?>
		<td>&nbsp;</td><td>... or <? echoButton('', 'Logout', "document.location.href=\"$logoutURL\""); ?></td>
<? } 
	 else { ?>
		<td>&nbsp;</td><td> <? echoButton('', 'Cancel', 'document.location.href="index.php"'); ?></td>
		 
<? }
?>		
</tr>
</table>

<script language='javascript' src="<?= localURL('check-form.js'); ?>"></script>
<script language='javascript'>
setPrettynames('oldpassword','Current Password','password','Password','password2','Retyped Password');
function checkAndSubmit() {
	if(MM_validateForm(
		<? if($optional) echo "		'oldpassword', '', 'R',
"; ?>
		'password', '', 'R',
		'password2', '', 'R',
		'password', 'password2', 'EQ'
		)) {
		document.changepassword.submit();
	}
}
</script>



<?
// ***************************************************************************

exit;
