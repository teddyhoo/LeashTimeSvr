<?
//login-creds-application.php

/* Rules:
1. No one is logged in.
2. For Client use.
3. Client's data is checked
4. If not found, fail.
5. If client already has a login, fail and email current login id and directions to request temp password
6. Else email client a confirmation link
7. Client login is activated when link is clicked


Inputs: (R = required, * = optional, @ = one required among all @'s
[R] lname
[R] fname
[R] email
[R] b - business ID
*/

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "comm-fns.php";

// Verify login information here
$lockChecked = true;

foreach($_SESSION as $k=>$v) unset($_SESSION[$k]);  // in case someone else is already logged in
extract(extractVars('lname,fname,email,loginid,password,b', $_REQUEST));

if(!$b) {
	echo "Context not specified.";
	exit;
}

$error = null;

$b = intValueOrZero($b);

$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE activebiz=1 AND bizid = $b LIMIT 1");
if(!$biz) $fatalError =  "Bad context supplied: $b.  Please contact LeashTime technical support.";
if($biz && !in_array($biz['db'], fetchCol0("SHOW DATABASES")))
	$fatalError =  "Bad context supplied: $b.  Please contact LeashTime technical support.";

if($_POST && $b) {
	if(!$error) {
		$clients = localClientSearch($_POST, $biz);
		if(is_string($clients)) $error = $clients;
	}
	if(!$error && !$clients) $error = "No registered user found with this information.";
	else if(count($clients) > 1) $error ="Could not set up login.  Please contact your pet sitting company.";
	else {
		$client = $clients[0];
		if($client['userid']) {
			$error = "You are already registered as a user.<p>Your username has been emailed to you and should arrive shortly.<p>"
							."If you have forgotten your password, please use the <a href='temp-password-assignment.php'>forgotten password</a>"
							." here or on the login page to get a new password.<p>"
							."<a href='login-page.php?b=$b'>Login Page</a>.<p><img src='art/spacer.gif' height=200 width=1>";
			if(sendOutLoginIdEmail($client)) $error = "Failed to send out user's username.";
			$alreadyRegistered = true;
		}
		if(!$error) {
			// Lookup loginid
			$user = findSystemLoginWithLoginId($loginid, true);
//echo "$loginid: ";print_r($user);exit;			
			// user is null, a string(insufficient rights error), or an array -- a user
			// new loginid and new user
			if(is_string($user)) {
				$error = "Sorry, but that won't work.  Please try another username.";
				$loginIdInUse = true;
			}
			else if(!$user) { // new loginid
				$data = array('loginid'=>$loginid, 'bizptr'=>$b, 'password'=>$password, 'active'=>0,'rights'=>basicRightsForRole('client'));
				$newuser = addSystemLogin($data, 'clientOrProviderOnly');
				if(is_string($newuser)) $error = $newuser;
				else {
					reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
					updateTable('tblclient', array('userid'=>$newuser['userid']), "clientid={$client['clientid']}", 1);
					$client['userid'] = ($userid = $newuser['userid']);
					$alreadyRegistered = true;
					$error = sendOutConfirmationEmail($client, $userid);
					if(!$error) 
						$error = "A message has been emailed to you and should arrive shortly.<p>"
										."It contains a web link which will confirm your identity and activate your account. "
										."If you do not receive the note, please contact your dogwalking company.<p>"
										."<a href='login-page.php?b=$b'>Login Page</a>.<p><img src='art/spacer.gif' height=200 width=1>";
				}
			}
			// old user and unchanged loginid or new, unused loginid
			else if($client['userid'] != $user['userid']) {
				$error = "Sorry, but [$loginid] is already in use.  Please try another username.";
				$userid = $user['userid'];
				$loginIdInUse = true;
			}
			else if($client['userid'] == $user['userid']) {  // covered above
				$error = "X";  
				$loginIdInUse = true;
			}
		}
	}
}

$pageTitle = 'Create Your System Login';
$_SESSION["uidirectory"] = "bizfiles/biz_$b/clientui/";
if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$b/";
$suppressMenu = 1;
include "frame-client.html";
if($alreadyRegistered) {
	echo $error;
	require "frame-end.html";
	exit;
}
if($fatalError) {
	echo $fatalError;
	require "frame-end.html";
	exit;
}
if($error) echo "<font color='red'>$error</font>";
?>
<table width='100%'>
<tr><td valign=top width='50%'>
<table width='100%'>
<form name='userlogineditor' method='POST'>
<?
$allowAutoCompleteForScript = true;

echo "<tr><td colspan=2><font color='red'>All fields are required.</font></td><tr>";
hiddenElement('b', $b);
hiddenElement('logineditor', 1);
inputRow('First Name:', 'fname', $fname);
inputRow('Last Name:', 'lname', $lname);
inputRow('Email:', 'email', $email, '', 'emailInput');
inputRow('Username:', 'loginid', $user['loginid']);
echo "<tr><td colspan=2>";
if($loginIdInUse) 
  if($names = suggestedLogins($userid, $lname, $fname, '', $email)) {
		$names = array_merge(array('-- Suggested Usernames --' => ''),array_combine($names, $names));
    selectElement('', 'suggestions', null, $names, $onChange='takeSuggestion(this)');
    echo " ";
	}
echo "</td></tr>";
echo "<tr style='display:hidden'><td id='availabilitymessage' colspan=2></td></tr>";

passwordRow('Password:', 'password', '', '', '', '', '', 'comparePasswords(this)');
passwordRow('Retype Password:', 'password2', '', '', '', '', '', 'comparePasswords(this)');
echo "<tr><td WIDTH=150>&nbsp;</td><td id='passwordnote'></td></tr>";	
echo "<tr><td colspan=2 align=center>";
echoButton('', "Create New Login", 'createLogin()');
echo "</td></tr>";
?>
</form>
</table>
Return to <a href='login-page.php?b=<?= $b ?>'>Login Page</a>.<p>
</td>
<td style='vertical-align:center;color:green'>
Username length must be at least 6 characters and no more than 45 characters.
<p>
Password length must be at least 7 characters and no more than 45 characters.
</td></tr>
</table>
<?
require "frame-end.html";

?>
<script language='javascript'>
function createLogin() {
	// if userid, then password1,password2 not required
	// if temporary password, then password1,password2 not required
		setPrettynames('fname','First Name','lname','Last Name', 'email', 'Email', 
										'loginid', 'Username', 'password', 'Password', 'password2', 'Retyped Password');
		if(!MM_validateForm(
			'fname', '', 'R',
			'lname', '', 'R',
			'email', '', 'R',
			'loginid', '', 'R',
			'loginid', '6', 'MINLEN',
			'loginid', '45', 'MAXLEN',
			'password', '', 'R',
			'password2', '', 'R',
			'password', '7', 'MINLEN',
			'password', '45', 'MAXLEN',
			'password1', 'password2', 'eq')
			)
			return;
	document.userlogineditor.submit();
}

function comparePasswords(el) {
	var p1 = document.getElementById('password').value;
	var p2 = document.getElementById('password2').value;
	if(el.id == 'password' && (p1.length < 6 || p1.length > 45))
		document.getElementById('passwordnote').innerHTML = '<font color=red>Minumum password length is 7 characters.</font>';
	else if(!p1 || !p2) document.getElementById('passwordnote').innerHTML = '';
	else if(p1 == p2) document.getElementById('passwordnote').innerHTML = '<font color=green>Passwords match!</font>';
	else if(p1 != p2) document.getElementById('passwordnote').innerHTML = '<font color=red>Passwords do not match!</font>';
}

</script>


<? dumpLoginEditorScripts(); 

function localClientSearch($client, &$biz) {
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$localClients = fetchAssociations($sql = "SELECT clientid, userid, fname, lname, email
															FROM tblclient
															WHERE email IS NOT NULL AND email = '{$client['email']}'
																AND fname = '{$client['fname']}'
																AND lname = '{$client['lname']}'");
//echo $sql;																
																
	return $localClients;
}

function sendOutConfirmationEmail($client, $userid) {
	global $biz;
	require_once "response-token-fns.php";
	require "common/init_db_common.php";
	$url = generateResponseURL($biz['bizid'], $client, "client-activate.php?u=$userid", $systemlogin=null, $expires=null, $appendToken=false);
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	return notifyByEmail($client, 'Account Activation', "Please click this link to <a href='$url'>activate your account</a>.", $cc=null, '', $html=1);
}

function sendOutLoginIdEmail($client) {
	global $biz;
	require "common/init_db_common.php";
	$user = findSystemLogin($client['userid']);
	if(is_string($user)) $msg =  "Your username could not be found.";
	else $msg =  "Your username is {$user['loginid']}";
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	return notifyByEmail($client, 'The information you requested', $msg, $cc=null, $html=null);
}

?>