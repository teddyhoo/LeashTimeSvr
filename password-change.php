<? //password-change.php

$goHome = false;

$minPasswordLength = 7;

$optional = isset($optional) ? $optional : false;

$frameEndURL = $frameEndURL ? $frameEndURL : "frame-end.html";

if($_REQUEST['message']) {
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	include "frame-client.html";
	echo "<font color='green'><h2>{$_REQUEST['message']}</h2></font>";
	echo "<img src='art/spacer.gif' height=300>";
	include $frameEndURL;
	exit;
}

$password = $password ? $password : $_REQUEST['password']; //since client-own-account-cc does a redirect, we cannot restrict to $_POST
$password2 = $password2 ? $password2 : $_REQUEST['password2'];
if(!$password2 && $_REQUEST['nugget']) { // nugget provides security in the GET case
	require_once "encryption.php";
	$nugget = denuggetize($nugget); // "password=NEWPASSWORD&password2=NEWPASSWORD";
	if($parts = explode('&', $nugget)) {
		foreach($parts as $pair) {
			$pair = explode('=', $pair);
			if($pair[0] == 'password') $password = $pair[1];
			if($pair[0] == 'password2') $password2 = $pair[1];
		}
	}
}

if($password2) {  // 
	require_once "login-fns.php";
	$moveOn = true;
	if(!$optional && $_REQUEST['token'] != $_SESSION['passwordchangetoken'])
		$message = 'This request can be honored only once.';
	else {
		unset($_SESSION['passwordchangetoken']);
		extract($_REQUEST);
		$oldPasswordFailed = false;
		list($db_pchange, $dbhost_pchange, $dbuser_pchange, $dbpass_pchange) = array($db, $dbhost, $dbuser, $dbpass);
		if(isset($_REQUEST['oldpassword'])) {
			include "common/init_db_common.php";
			$oldPasswordFailed = 
				!passwordsMatch($_REQUEST['oldpassword'], 
											fetchRow0Col0("SELECT password FROM tbluser WHERE userid = {$_SESSION['auth_user_id']} LIMIT 1"));
		}
		if(!$oldPasswordFailed && $password && $password == $password2) {
			include "common/init_db_common.php";
			if(suspiciousPassword($password))
				$message = "This new password is not valid.  Please choose another.";
			else {

				$encPassword = encryptPassword($password);
				doQuery("UPDATE tbluser set password = '$encPassword' WHERE userid = {$_SESSION["auth_user_id"]}");
				$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
				unset($_SESSION['passwordResetRequired']);

				//globalRedirect('password-change.php?message='.urlencode('Your password has been changed.<p>'.
				//	"Return to <a href='index.php'>Home</a>"));
				echo "<font color='green'><h2>Your password has been changed.<p></h2></font><span class='fontSize1_5em'>Return to <a href='index.php'>Home</a></span>";
				if(userRole() != 'z') include $frameEndURL;
				/*if(function_exists('reconnectPetBizDB')) */reconnectPetBizDB($db_pchange, $dbhost_pchange, $dbuser_pchange, $dbpass_pchange, 1);
				$roles = array('d'=>'dispatcher', 'o'=>'manager', 'c'=>'tblclient', 'p'=>'tblprovider');
				$roleid = $_SESSION["clientid"] || $_SESSION["providerid"] || $_SESSION['auth_user_id'];
				if(userRole() != 'z') logChange($roleid, $roles[userRole()], 'm', "Password changed: ({$_SESSION['auth_user_id']}) {$_SESSION['auth_login_id']}");

				exit;
			}
			
		}
		else if($oldPasswordFailed) {
			$message = "That is not your current password.";
			$moveOn = false;
		}

		
	}
}
if($optional) 	$action = 'password-change-page.php';

else if(!$_SESSION || !$_SESSION['passwordResetRequired']) {
	// add code here to start the session and dump the frame
	$action = 'password-change.php';
}
else if(userRole() == 'z') {
	$action = 'maint-password-change.php';
}
else {
	$action = 'index.php';
}
// frame start has already been output

if($message) {
	$color = $goHome ? 'green' : 'red';
	echo "<font color='$color'><h2>$message</h2></font>";
	echo "<img src='art/spacer.gif' height=300>";
	if($moveOn) {
		include $frameEndURL;
		exit;
	}
}

require_once "response-token-fns.php";
$_SESSION['passwordchangetoken'] = randomToken();


?>
<form name='changepassword' method='POST' action='<?= $action ?>'>
<? 

hiddenElement('token', $_SESSION['passwordchangetoken']); 
hiddenElement('verboten', $_SESSION["auth_login_id"]); 

if($optional) echo "<h2>Password Change</h2>";
else echo "<span style='color:red;'><h2>Password change required</h2></span>";

function mattPrint($s) { if(mattOnlyTEST()) echo $s; }
?>
<table border=0><tr><td>
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

<tr><td>Password:</td><td><input type = 'password' id='password' name='password' autocomplete='off'></td></tr>
<tr><td colspan=2><h3>Step <?= $step = $step + 1; ?>:  Retype the password to ensure accuracy.</h3></td></tr>
<tr><td>Retype Password:</td><td><input type = 'password' name='password2' autocomplete='off'></td></tr>
<tr><td colspan=2><h3>Step <?= $step = $step + 1; ?>:  Click below to Set Password.</h3></td></tr>
<tr><td><? echoButton('', 'Set Password', 'checkAndSubmit()', $class='BigButton', $downClass='BigButtonDown', $noEcho=false, $title='Save this as your new permanent password.'); ?></td>
<? 
function localURL($url) {
	$dir = basename(dirname($_SERVER['SCRIPT_NAME']));
	$dir = $dir ? '../' : '';
	return "$dir$url";
}

if(!$optional) { 
			$logoutURL = localURL('login-page.php?logout=1');
?>
		<td align=right>... or <? echoButton('', 'Logout', "document.location.href=\"$logoutURL\""); ?></td>
<? } 
	 else { ?>
		<td align=right> <? echoButton('', 'Cancel', 'document.location.href="index.php"'); ?></td>
		 
<? }
?>		
</tr>
</table>
</td><td valign=top>
</td>
</tr>
</table>
<? if(mattOnlyTEST()) { ?>
<div class='tiplooks fontSize1_2em' style='text-align:left;'>
	<b>Password Rules</b>
	<ul>
	<li>You can <b>not</b> use <b><?= $_SESSION["auth_login_id"] ?></b> as your password.
	<li>You can <b>not</b> use <b>pass</b> as your password.
	<li>You can <b>not</b> use <b>password</b> as your password.
	<li>New password must be at least <?= $minPasswordLength ?> characters long.
	</ul>
</div>
<? } ?>

<script language='javascript' src="<?= localURL('check-form.js'); ?>"></script>
<script language='javascript'>
setPrettynames('oldpassword','Current Password','password','Password','password2','Retyped Password');
function checkAndSubmit() {
	var verboten = [document.getElementById('verboten').value, 'pass', 'password'];
	var pwd = document.getElementById('password').value;
	var verbotenmessage = '';
	for(var i=0; i < verboten.length; i++)
		if(pwd == verboten[i])
			verbotenmessage = 'Please choose a different password.';
	var tooshortmessage = '';
	var minPwdLength = <?= $minPasswordLength ?>;
	if(pwd.length < minPwdLength) tooshortmessage = 'Please supply a password at least '+minPwdLength+' characters long.';
	
	if(MM_validateForm(
		<? if($optional) echo "		'oldpassword', '', 'R',
"; ?>
		'password', '', 'R',
		'password2', '', 'R',
		'password', 'password2', 'EQ'
		, verbotenmessage, '', 'MESSAGE'
		, tooshortmessage, '', 'MESSAGE'
		)) {
		document.changepassword.submit();
	}
}
</script>



<?
// ***************************************************************************
if(userRole() != 'z') include $frameEndURL;
exit;
