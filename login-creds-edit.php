<?
//login-creds-edit.php

/* Rules:
1. Only an Owner may use this page.
2. An owner may only edit logins in the same petbiz

Inputs: (R = required, * = optional, @ = one required among all @'s
[R] roleid - clientid or providerid
[@] userid - for existing logins
[@] role - for new logins
[*] lname
[*] fname
[*] nickname
[*] email
[*] target - element in window opener to update
[*] nextURL
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";

// Verify login information here
locked('o-');
extract($_REQUEST);
$myBizptr = $_SESSION["bizptr"];
if($userid) {
  $user = findSystemLogin($userid, $includeTemppassword=true);
  if(is_string($user)) {
		$error = $user;
		$user = array();
	}
}
else $user = array('bizptr' => $myBizptr, 'active'=>1);


if($error) {
  echo $error;
  exit;
}

if($role == 'client') {
	$table = 'tblclient';
	$keyfield = 'clientid';
}
else if($role == 'provider') {
	$table = 'tblprovider';
	$keyfield = 'providerid';
}

$person = fetchFirstAssoc("SELECT * FROM $table WHERE $keyfield = $roleid LIMIT 1", 1);

$error = null;
if($_POST && ($action == 'register')) {
	// Lookup loginid
  $user = findSystemLoginWithLoginId($loginid, true);
  // user is null, a string(insufficient rights error), or an array -- a user
  if(is_string($user)) {
		$error = $user;
		$user = array('bizptr' => $myBizptr, 'active'=>1, 'temppassword'=>$temppassword);
	}
	if($role == 'client') {
		$table = 'tblclient';
		$keyfield = 'clientid';
	}
	else if($role == 'provider') {
		$table = 'tblprovider';
		$keyfield = 'providerid';
	}
  // new loginid and new user
  if(!$error && !$user && !$userid) { // new loginid and new user
		$data = array_merge($_POST);
		$data['bizptr'] = $_SESSION['bizptr'];
		$newuser = addSystemLogin($data, 'clientOrProviderOnly');
		if(is_string($newuser)) $error = $newuser;
		else {
			updateTable($table, array('userid'=>$newuser['userid']), "$keyfield={$_POST['roleid']}", 1);
			$userid = $newuser['userid'];
			logChange($_POST['roleid'], $table, 'm', "Login creds created: ($userid) {$newuser['loginid']}");
		}
	}
	// old user and unchanged loginid or new, unused loginid
  else if(!$error && (!$user || ($userid && is_array($user) && $user['userid'] == $userid))) {
		if(!$_POST['password']) unset($_POST['password']);
  	$oldPass = getSavedPassword($userid);
  	$olduser = findSystemLogin($userid);
		$error = updateSystemLogin($_POST, 'clientOrSitterOnly'); // returns null on success
		if(!$error) {
			$newuser = findSystemLogin($userid);
			$loginchange = $newuser['loginid'] == $olduser['loginid'] ? '' : "{$olduser['loginid']}=>";
			$newPass = getSavedPassword($userid);
			$pwchange = $oldPass == $newPass ? 'No ' : 'Yes';
			$activation = $newuser['active'] && !$olduser['active'] ? '  Activated.' : (
										$olduser['active'] && !$newuser['active'] ? '  Deactivated.' : '');
			$tp = $temppassword ? '  Temp Password Supplied.' : '';
			logChange($_POST['roleid'], $table, 'm', 
								"Login creds updated: ($userid) $loginchange$loginid. Password Change: $pwchange.$tp");
		}
	}
  else {
		$error = "Sorry, but [$loginid] is already in use.  Please try another username.";
		// if THIS user already exists and the found user ($user) is not that user,
		// then $user now refers to the WRONG user
		// We correct this after the POST section by calling findSystemLogin login again.
	}

  if(!$error) {
		$loginIdLabel = $loginid.($active ? '' : " (inactive login)");
		if($sendcreds && in_array($role, array('client', 'provider'))) {
			$templateid = $role == 'client' ? '#UNDELETABLE - Client Login Credentials' : '#UNDELETABLE - Sitter Login Credentials';
			$templateid = fetchRow0Col0("SELECT templateid FROM tblemailtemplate WHERE label = '$templateid' LIMIT 1");
			echo "<script language='javascript' src='common.js'></script>";
			$sendcreds = "openConsoleWindow('emailcomposer', 'comm-composer.php?$role=$roleid&template=$templateid',500,500);";
		}
		echo "<script language='javascript'>{$sendcreds}if(window.opener.update) window.opener.update('$target', '$userid,$loginIdLabel'); window.close();</script>";
		exit();
	}
}

// Find user by userid again in case the wrong user was found above
if($userid) {
  $user = findSystemLogin($userid, $includeTemppassword=true);
  if(is_string($user)) {
		$error = $user;
		$user = array();
	}
}
else $user = array('bizptr' => $myBizptr, 'active'=>1);

$rights = $user['rights'] ? $user['rights'] : basicRightsForRole($role);

$windowTitle = 'System Login Editor';
require "frame-bannerless.php";
?>
<h2>System Login Editor</h2><h3><?= "$fname $lname" ?></h3>
<form name='userlogineditor' method='POST'>
<? if($error) echo "<font color='red'>$error</font>" ?>
<table width='100%'>
<?
hiddenElement('action', '');
hiddenElement('roleid', $roleid);
hiddenElement('rights', $rights);
hiddenElement('userid', $userid);
hiddenElement('target', $target);
hiddenElement('sendcreds', '');
hiddenElement('logineditor', 1);
inputRow('Username:', 'loginid', $user['loginid']);
echo "<tr><td colspan=2>";
$email  = $person['email'];
$email2  = $person['email2'];
if(!$user['loginid']) 
  if($names = suggestedLogins($userid, $lname, $fname, $nickname, $email, $email2)) {
		$names = array_merge(array('-- Suggested Usernames --' => ''),array_combine($names, $names));
    selectElement('', 'suggestions', null, $names, $onChange='takeSuggestion(this)');
    echo " ";
	}
echoButton('','Check Username Availability', 'checkAvailability("availabilitymessage")');
echo "</td></tr>";
echo "<tr style='display:hidden'><td id='availabilitymessage' colspan=2></td></tr>";

$passwordset = $user['passwordset'] ? ' <span class="tiplooks">(password is set)</span>' : '';
passwordRow("Password:$passwordset", 'password', '');
passwordRow('Retype Password:', 'password2', '');
inputRow('Temporary Password:', 'temppassword', $user['temppassword']);
$inactiveLooks = $user['active'] ? null : 'background:pink';
checkboxRow('Active', 'active', $user['active'], null, null, null, $inactiveLooks);
echo "<tr><td colspan=2 align=center>";
$buttonLabel = $userid ? 'Save Changes' : 
               ($fname ? "Save New Login for $fname $lname" :
               "Save New Login");
echoButton('', $buttonLabel, 'saveChanges()');
if(TRUE || staffOnlyTEST()) echo " <img src='art/tiny-email-message.gif' onclick='sendCreds()' title='Send temporary password to user'>";
echo "</td></tr>";
?>
</table>
<? dumpLoginEditorScripts(); ?>