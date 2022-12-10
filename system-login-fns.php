<?
// system-login-fns.php
require_once "common/db_fns.php";
require_once "login-fns.php";

$loginfields = explode('|', 'loginid|userid|bizptr|orgptr|password|rights|active|temppassword|lname|fname|email|isowner');

function getLoginFields() {
	return explode('|', 'loginid|userid|bizptr|orgptr|password|rights|active|temppassword|lname|fname|email|isowner');
}

function basicRightsForRole($role) {
// RIGHTS: rq - client request, va - view appointment, vp - view pets, vc - view clients, vh - view pay history
	return 
		$role == 'owner' ? 'o-' :
		($role == 'client' ? 'c-rq,va,vp' :
		($role == 'provider' ? 'p-va,vc,ma,vh,vp,#cl' : 
		($role == 'dispatcher' ? 'd-#ev,#ec,#es,#vr,#gi,#pp,#km' : null)
		));
}

function getManagerUsers($details=false) {
		global $globalbizptr; // set in cron-fns.php
		$localBizPtr = $globalbizptr ? $globalbizptr : $_SESSION['bizptr'];
		global $db, $dbhost, $dbuser, $dbpass;
		list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
		include "common/init_db_common.php";
		if($details) $users = fetchAssociationsKeyedBy("SELECT userid, loginid, lname, fname, email, rights FROM tbluser WHERE rights LIKE 'o-%' AND bizptr = $localBizPtr", 'userid', 1);
		else $users = fetchKeyValuePairs("SELECT userid, loginid FROM tbluser WHERE rights LIKE 'o-%' AND bizptr = $localBizPtr");
		list($db, $dbhost, $dbuser, $dbpass) = array($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
		if($_SESSION) include "common/init_db_petbiz.php";
		else reconnectPetBizDB();
		return $users;
}
	
function validateSystemLogin($roleid, $role, $loginid=null) {
	$prettyRoles = array('client'=>'client', 'provider'=>'sitter', 'owner'=>'manager', 'dispatcher'=>'dispatcher');
	$rightsRoles = array('c'=>'client', 'p'=>'sitter', 'o'=>'manager', 'd'=>'dispatcher');
	if($roleid && $role) {
		$userData = fetchFirstAssoc("SELECT userid, active FROM tbl$role WHERE $role"."id = {$roleid} LIMIT 1");
		if(!$userData) $errors[] = "No {$prettyRoles[$role]} [$roleid] found for this business.";
		$notes[] = "This {$prettyRoles[$role]} is ".($userData['active'] ? '' : 'NOT')." active";
		$user = findSystemLogin($userData['userid'], 'includetemppassword');
	}
	else if($loginid) $user = findSystemLoginWithLoginId($loginid, 'nullifnotfound');
	if(is_string($user)) $errors[] = "No user named [$loginid] found for this business.";
	else if(!$user) $errors[] = "No user named [$loginid] found.";
	if(!$errors) {
		$notes[] = "This {$prettyRoles[$role]}'s login is ".($user['active'] ? '' : 'NOT')." active";
		$notes[] = "This {$prettyRoles[$role]}'s password is ".($user['passwordset'] ? '' : 'NOT')." set";
		$notes[] = "This {$prettyRoles[$role]} has ".($user['temppasswordset'] ? 'a' : 'NO')." temporary password set";
		if($role && $user['rights'][1] == '-' && $role[0] != $user['rights'][0])
			$errors[] = "This user is a {$prettyRoles[$role]} but has rights associated with a {$rightsRoles[$user['rights'][0]]}.";
		else if(substr($user['rights'], 1, 1) != '-') 
			$errors[] = "This user has damaged rights [{$user['rights']}].";
		if(!$errors && $user['active'] && $userData['active'] &&
				($user['temppasswordset'] || $user['passwordset'])) {
			if($user['temppassword']) $orTemppassword = " or with temp password <span style='background:yellow'>{$user['temppassword']}</span>";
			$notes[] = "This {$prettyRoles[$role]} should be able to login as <b>{$user['loginid']}</b> with the correct password$orTemppassword.";
		}
		else $errors[] = "This user cannot login at this time.";
	}
	return array('notes'=>$notes, 'errors'=>$errors);
}

function addSystemLogin($data, $clientOrProviderOnly=false) {
	$loginfields = getLoginFields();
	global $dbhost, $db, $dbuser, $dbpass;
	
	if(suspiciousUserName($data['loginid']))
		return "ERROR: invalid user name.  choose provide another.";
	
	if($clientOrProviderOnly && (!$data['rights'] || !in_array($data['rights'][0], array('c', 'p'))))
		return "ERROR: invalid attempt to add a user with manager credentials.  Please contact LeashTime support.";
		
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";

	$data['password'] = isset($data['password']) ? encryptPassword($data['password']) : '';
	foreach($data as $key => $val) {
		if(!in_array($key, $loginfields)) unset($data[$key]);
  }
  $data['active'] = isset($data['active']) &&  $data['active'] ? 1 : 0;

  insertTable('tbluser', $data, 1);
  $data['userid'] = mysql_insert_id();
  $src = $_SESSION["bizptr"] ? "BIZ" : "LT";
  $pass = $data['password'] ? 'PASS' : 'NOPASS';
  logChange($data['userid'], 'tbluser', 'c', "$src|{$data['loginid']}|{$data['lname']}|{$data['fname']}|{$data['email']}|$pass|{$data['temppassword']}");
	if(function_exists('reconnectPetBizDB')) reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
	return $data;
}

function getSavedPassword($userid) {
	global $loginfields;
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$pass = fetchRow0Col0("SELECT password FROM tbluser WHERE userid = '$userid'");
	if(function_exists('reconnectPetBizDB')) reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
	return $pass;
}

function updateSystemLogin($data, $clientOrProviderOnly=false) {
	global $loginfields;
	global $dbhost, $db, $dbuser, $dbpass;
	
	if(suspiciousUserName($data['loginid']))
		return "ERROR: invalid user name.  choose provide another.";
		
	if($clientOrProviderOnly && ($data['rights'] && !in_array($data['rights'][0], array('c', 'p'))))
		return "ERROR: invalid attempt to add a user with manager credentials.  Please contact LeashTime support.";

	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";
	if(isset($data['password'])) $data['password'] = encryptPassword($data['password']);	
	foreach($data as $key=> $val) {
		if(!in_array($key, $loginfields)) unset($data[$key]);
  }
  $data['active'] = isset($data['active']) &&  $data['active'] ? 1 : 0;
  $userid = $data['userid'];
  unset($data['userid']);
  $oldversion = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = $userid LIMIT 1");
  foreach($data as $key => $val)  {
  	if($key == 'password' && !$data['password']) continue;
  	if($oldversion[$key] != $data[$key]) $mods[] = "$key|{$oldversion[$key]}|{$data[$key]}";
	}
	$mods = join('||', (array)$mods);
	
  updateTable('tbluser', $data, "userid = $userid", 1);
  $src = $_SESSION["bizptr"] ? "BIZ" : "LT";
  logChange($userid, 'tbluser', 'm', "$src||$mods");
	if(function_exists('reconnectPetBizDB')) reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
}

function findSystemLogin($userid, $includeTemppassword=false) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$includeTemppassword = $includeTemppassword ? ", temppassword" : '';
	
  $user = fetchFirstAssoc("SELECT bizptr, loginid, rights, active, userid, orgptr, 
  													IF(password IS NULL OR TRIM(password) = '', 0, 1) as passwordset,
  													IF(temppassword IS NULL OR TRIM(temppassword) = '', 0, 1) as temppasswordset
  													$includeTemppassword
  													FROM tbluser WHERE userid = $userid");
  
	if(function_exists('reconnectPetBizDB')) reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);

  if(!$user) $error = "No user found for userid $userid";
  else if($_SESSION['bizptr'] && ($user['bizptr'] != $_SESSION['bizptr'])) $error = "Insufficient rights to edit user with id $userid";
  return $error ? $error : $user;
}

function findSystemLoginWithLoginId($loginid, $nullIfNotFound=false) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";
  $user = fetchFirstAssoc("SELECT bizptr, userid, rights, active, temppassword FROM tbluser WHERE loginid = '$loginid'");
	if(function_exists('reconnectPetBizDB')) reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);

  if(!$user) {
		if($nullIfNotFound) return null;
		$error = "No user found for loginid $loginid";
	}
  else if($_SESSION['bizptr'] && ($user['bizptr'] != $_SESSION['bizptr'])) $error = "Insufficient rights to edit user with id $loginid";
	// reconnectPetBizDB($dbhostN=null, $dbN=null, $dbuserN=null, $dbpassN=null)

  return $error ? $error : $user;
}

function suggestedLogins($userid=0, $lname='', $fname='', $nickname='', $email='', $email2='') {
	global $dbhost, $db, $dbuser, $dbpass, $minLoginIdLength;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";

	$lname = str_replace("'", "", str_replace('"', "", $lname));
	$fname = str_replace("'", "", str_replace('"', "", $fname));
	$names = array();
	if($email) $names[] = "$email";
	if($email2) $names[] = "$email2";
	if($lname && $fname) {
	  $names[] = "$lname{$fname[0]}";
	  $names[] = "{$fname[0]}$lname";
	  $names[] = "$fname.$lname";
	}
	if($email) $names[] = $email;
	if($nickname = trim((string)$nickname)) {
		$nickname = str_replace(' ', '', $nickname);
		$names[] = $nickname;
	}
	foreach($names as $i => $name) $names[$i] = mysql_real_escape_string($names[$i]);
	$namesStr = "'".join("','", $names)."'";

	$userid = $userid ? $userid : '0';
	
	if(!$minLoginIdLength) $minLoginIdLength = 6;
	
	foreach($names as $i => $name)
		if(strlen($name) < $minLoginIdLength) {
			$width = $minLoginIdLength - strlen($name);
			$names[$i] = $name.sprintf("%0$width"."d", 1);
		}
	
	$names = array_diff($names, fetchCol0("SELECT loginid FROM tbluser WHERE loginid IN ($namesStr) AND userid <> $userid"));
	if(function_exists('reconnectPetBizDB')) reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
  return $names;
}

function dumpLoginEditorScripts($roadHome='') {
	global $minLoginIdLength;
	if(!$minLoginIdLength) $minLoginIdLength = 6;
	$TEST = mattOnlyTEST() ? "true" : 'false';
	echo "
<script language='javascript' src='".$roadHome."ajax_fns.js'></script>
<script language='javascript' src='".$roadHome."check-form.js'></script>
<script language='javascript'>
setPrettynames('loginid','Username','password','Password', 'password2', 'Retype Password', 'temppassword', 'Temporary Password');
function saveChanges() {
	// if userid, then password1,password2 not required
	// if temporary password, then password1,password2 not required
	if(!document.userlogineditor.userid.value && !document.userlogineditor.temppassword.value) {
		if(!MM_validateForm(
			'loginid', '', 'R',
			'loginid', '$minLoginIdLength', 'MINLEN',
			'loginid', '65', 'MAXLEN',
			trimWarning('loginid'), '', 'MESSAGE',
			'password', '', 'R',
			'password2', '', 'R',
			'password', 'password2', 'EQ',
			trimWarning('fname'), '', 'MESSAGE',
			trimWarning('lname'), '', 'MESSAGE',
			trimWarning('temppassword'), '', 'MESSAGE')
			)
			return;
	}
	else {
		var origloginid = document.getElementById('origloginid');
		var shortloginidwarning = '';
		if(origloginid 
				&& (origloginid.value != document.getElementById('loginid').value)
				&& document.getElementById('loginid').value.length < $minLoginIdLength)
			shortloginidwarning = prettyName('loginid')+' must be at least $minLoginIdLength characters in length.';
			
		if(!MM_validateForm(
			'loginid', '', 'R',
			//'loginid', '$minLoginIdLength', 'MINLEN',
			shortloginidwarning, '', 'MESSAGE',			
			'loginid', '45', 'MAXLEN',
			trimWarning('loginid'), '', 'MESSAGE',
			trimWarning('fname'), '', 'MESSAGE',
			trimWarning('lname'), '', 'MESSAGE',
			trimWarning('temppassword'), '', 'MESSAGE')
			)
			return;
	}
	document.getElementById('action').value = 'register';
	document.userlogineditor.submit();
}

function trimWarning(nm) { 
	var s = document.getElementById(nm);
	if(!s) return null;
	s = s.value;
	if(s != trim(s))
		return prettyName(nm)+' must not have leading or trailing blanks.';
}

function checkAvailability(tdid) {
	if(!document.userlogineditor.loginid.value) return;
	var loginid = document.userlogineditor.loginid.value;
	var url = '".$roadHome."check-loginid.php?loginid='+loginid+'&userid='+document.userlogineditor.userid.value;
	//if($TEST) alert(url);
  var xh = getxmlHttp();
  xh.open('GET',url,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { document.getElementById(tdid).innerHTML=xh.responseText;document.getElementById(tdid).parentNode.style.display='{$_SESSION['tableRowDisplayMode']}' } }
  xh.send(null);
}

function userHistory() {
	if(!document.userlogineditor.userid.value) return;
	var userid = document.userlogineditor.userid.value;
	var url = '".$roadHome."maint-edit-user.php?history='+userid;
	//if($TEST) alert(url);
  var xh = getxmlHttp();
  xh.open('GET',url,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { document.getElementById('history').innerHTML=xh.responseText; } }
  xh.send(null);
}
	
function killSwitch() {
	if(!document.userlogineditor.userid.value) {alert('No user id.'); return;}
	if(!confirm('About to set session kill switch for this user.')) {alert('Kill switch aborted.'); return;}
	var userid = document.userlogineditor.userid.value;
	var url = '".$roadHome."maint-edit-user.php?setkillswitch='+userid;
	//if($TEST) alert(url);
  var xh = getxmlHttp();
  xh.open('GET',url,true);
  xh.onreadystatechange=function() { if(xh.readyState==4) { document.getElementById('history').innerHTML=xh.responseText; } }
  xh.send(null);
}

function takeSuggestion(el) {
	if(el.selectedIndex) {
		document.userlogineditor.loginid.value = el.options[el.selectedIndex].value;
	}
	el.selectedIndex = 0;
}

function sendCreds() {
	if(!MM_validateForm('temppassword', '', 'R'))
			return;
	document.userlogineditor.sendcreds.value = 1;
	saveChanges();
}

document.getElementById('loginid').select();

</script>
";
}