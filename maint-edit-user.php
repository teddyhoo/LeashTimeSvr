<?
//maint-edit-user.php

/* Rules:
1. Only an Super-user may use this page.
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
require_once "common/init_db_common.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";

$minLoginIdLength = 4;

// Verify login information here
locked('z-');
extract(extractVars('userid,loginid,role,nickname,email,target,bizptr,password,rights,temppassword,password,active,duplicate,isowner', $_REQUEST));

$dbs = fetchCol0("SHOW DATABASES");


if($_REQUEST['history']) { // AJAX
	$history = fetchAssociations(
		"SELECT log.*, loginid, CONCAT_WS(' ', fname, lname) as person 
			FROM tblchangelog log
			LEFT JOIN tbluser ON user = userid
			WHERE itemtable = 'tbluser' AND itemptr = {$_REQUEST['history']} ORDER BY `time` ASC", 1);
	$ops = explodePairsLine('c|created||m|modified');
	foreach($history as $i => $incident) {
		$history[$i]['op'] = $ops[$incident['operation']];
		$history[$i]['userdetails'] = "<span style='text-decoration:underline' title='[{$incident['user']}] {$incident['loginid']}'>{$incident['person']}</span>";
	}
	$columns = explodePairsLine('time|Time||op|Operation||note|Note||userdetails|User');
	tableFrom($columns, $history, 'border=solid black 1px');
	exit;
}

if($_REQUEST['countlogins']) {
	echo fetchRow0Col0("SELECT count(*) FROM tbllogin WHERE loginid = '{$_REQUEST['countlogins']}'");
	exit;
}

if($_REQUEST['kill']) {
	$userid = $_REQUEST['kill'];
}

if($_REQUEST['setkillswitch']) {
	$userid = $_REQUEST['setkillswitch'];
  $user = fetchFirstAssoc(
		"SELECT bizptr, loginid, temppassword, userid, rights, active, 
  				lname, fname, email, 
  				if(password is null, '(not set)', if(password = '', '(not set)', '(set)')) as passwordset
  	FROM tbluser 
  	WHERE userid = '$userid'");
  if(!is_array($user)) {
		$error = $user;
		$user = array();
	}
	else {
		$bizptr = $user['bizptr'];
		$biz = fetchFirstAssoc("SELECT db FROM tblpetbiz WHERE bizid = $bizptr LIMIT 1");
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if(setKillSwitch($userid, $user['loginid'])) 
			echo "Just set kill switch for [$userid] [$loginid]";
		else echo "No kill switch set: [$userid] [$loginid]";
	}
	exit;
}

if($userid) {
  $user = fetchFirstAssoc(
		"SELECT bizptr, loginid, temppassword, userid, rights, active, 
  				lname, fname, email, isowner,
  				if(password is null, '(not set)', if(password = '', '(not set)', '(set)')) as passwordset
  	FROM tbluser 
  	WHERE userid = '$userid'");
  if(!is_array($user)) {
		$error = $user;
		$user = array();
	}
	else {
		$bizptr = $user['bizptr'];
		$role = strlen($user['rights']) < 2 ? null : substr($user['rights'], 0, 2);
//echo "ROLE: [$role]<hr>";exit;
		$role = $role && $role[1] == '-' ? $role[0] : $role;
		if($bizptr) {
			$dbName = fetchRow0Col0("SELECT db FROM tblpetbiz WHERE bizid = $bizptr LIMIT 1");
			$userDetails = userDetails($user, $dbName);
			if($userDetails) {
				if($role != 'o' && $role != 'd') {
					$lname = $userDetails['lname'];
					$fname = $userDetails['fname'];
					$email = $userDetails['email'];
				}
				$nickname = $userDetails['nickname'];
				$roleid = $userDetails['roleid'];
			}
		}
		
	}
}
else {
	$user = array('bizptr' => $bizptr, 'active'=>1);
	$orgptr = fetchRow0Col0("SELECT orgptr FROM tblpetbiz WHERE bizid = $bizptr LIMIT 1");
	$dbName = fetchRow0Col0("SELECT db FROM tblpetbiz WHERE bizid = $bizptr LIMIT 1");
	
}

$originalUser = $duplicate ? $duplicate : $userid;
if($originalUser) $originalUser = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = $originalUser LIMIT 1");

if($duplicate) {
	$user['rights'] = $originalUser['rights'];
	$dupUserPrefs = 
		fetchKeyValuePairs("SELECT property, value FROM $dbName.tbluserpref WHERE userptr = $duplicate", 1);
}
else if($_REQUEST['kill']) {
	if(!isset($_SESSION['whosyerdaddy'])) $error = "Insufficient rights.";
	else {
		deleteTable('tbluser', "userid=$userid");
		$biz = fetchFirstAssoc("SELECT db FROM tblpetbiz WHERE bizid = $bizptr LIMIT 1");
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if($userDetails['providerid']) {
			require_once "provider-fns.php";
			wipeProvider($userDetails['providerid']);
		} 
		else if($userDetails['clientid']) {
			require_once "client-fns.php";
			wipeClient($userDetails['clientid']);
		}
		require_once "common/init_db_common.php";
		echo "Done";
		exit;
	}
}

if($error) {
  echo $error;
  exit;
}

function userDetails($user, $dbname) {
	global $dbs;
	if(!in_array($dbname, $dbs)) return array('error'=> "DB $dbname does not exist.");
	if(strpos($user['rights'], 'c-') === 0) $sql = "SELECT *, clientid as roleid FROM $dbname.tblclient WHERE userid = {$user['userid']} LIMIT 1";
	else if(strpos($user['rights'], 'p-') === 0) $sql = "SELECT *, providerid as roleid FROM $dbname.tblprovider WHERE userid = {$user['userid']} LIMIT 1";
	$details = $sql ? fetchFirstAssoc($sql) : array();
	$details['userprefs'] = fetchKeyValuePairs("SELECT property, value FROM $dbname.tbluserpref WHERE userptr = {$user['userid']}", 1);
	return $details;
}

$error = null;
if($_POST && ($_POST['action'] == 'register')) {
	// Lookup loginid
  $user = fetchFirstAssoc("SELECT bizptr, userid, rights, active, lname, fname FROM tbluser WHERE loginid = '$loginid'");
  // user is null, a string(insufficient rights error), or an array -- a user
  // new loginid and new user
	$data = array_merge($_POST);
	$data['isowner'] = $isowner && ($role == 'manager' || $rights[0] == 'o') ? 1 : '0';
//if(mattOnlyTEST()) {print_r($data);exit;}	

  if(!$user && !$userid) { // new loginid and new user
		$data['bizptr'] = $bizptr;
		//loginid|userid|bizptr|orgptr|password|rights|active|temppassword|lname|fname|email
		$data['rights'] = $duplicate ? $rights : basicRightsForRole($role);
		if($role == 'dispatcher') $data['orgptr'] = $orgptr ? $orgptr : -1;
		$newuser = addSystemLogin($data);
		/*if($role == 'client') {
			$table = 'tblclient';
			$keyfield = 'clientid';
		}
		else if($role == 'provider') {
			$table = 'tblprovider';
			$keyfield = 'providerid';
		}
		updateTable($table, array('userid'=>$newuser['userid']), "$keyfield={$_POST['roleid']}", 1);*/
		$userid = $newuser['userid'];
		if(!in_array($dbName, $dbs)) $dbName = null;
		if($dbName) foreach($_POST as $k => $v)
			if(strpos($k, 'userpref_') === 0) {
				$prop = substr($k, strlen('userpref_'));
				if(!$v) deleteTable($dbName.'.tbluserpref', "userptr = $userid AND property='$prop'", 1);
				else replaceTable($dbName.'.tbluserpref', array('userptr' => $userid, 'property' => $prop, 'value'=>$v), 1);
			}
	}
	// old user and unchanged loginid or new, unused loginid
  else if(!$user || ($userid && is_array($user) && $user['userid'] == $userid)) {
//echo "SAVE: ".print_r($_POST,1);exit;	
		if(!$data['password']) unset($data['password']);
//if(mattOnlyTEST()) {print_r($data);exit;}		
		updateSystemLogin($data);
		if($_POST['clearnotices']) deleteTable('relusernotice', "userptr = '$userid'", 1);
	}
  else {
		$error = "Sorry, but [$loginid] is already in use.  Please try another username.";
		// at this point, $user is the conflicting user.  echo "USER: ".print_r($user, 1);
		$user = $originalUser;
	}
  if(!$error) {
		if(!in_array($dbName, $dbs)) $dbName = null;
		if($dbName) foreach($_POST as $k => $v)
			if(strpos($k, 'userpref_') === 0) {
				$prop = substr($k, strlen('userpref_'));
				if(!$v) deleteTable($dbName.'.tbluserpref', "userptr = $userid AND property='$prop'", 1);
				else replaceTable($dbName.'.tbluserpref', array('userptr' => $userid, 'property' => $prop, 'value'=>$v), 1);
			}
		
		$loginIdLabel = $loginid.($active ? '' : " (inactive login)");
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('$target', '$userid,$loginIdLabel'); window.close();</script>";
		exit();
	}
}
// case 1: established user
// case 2: duplicate setup: use duplicate's rights
// case 3: duplicate failed: use requested rights
// case 4: creation or renaming failed: use requested rights
$rights = $_POST['rights'] ? $_POST['rights'] : (
					$originalUser['rights'] ? $originalUser['rights'] : (
					$user['rights'] ? $user['rights'] : basicRightsForRole($role)));
					
//$rights = $user['rights'] ? $user['rights'] : basicRightsForRole($role);
if($userid) $noticesViewed = fetchRow0Col0("SELECT count(*) FROM relusernotice WHERE userptr = '$userid'");

$windowTitle = 'System Login Editor';
require "frame-bannerless.php";
if($duplicate) {
	$dupRights = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = $duplicate LIMIT 1");
	$dupRights = "<b>(with same rights as $dupRights)</b>";
}

?>
<h2>System Login Editor</h2><h3><?= "$fname $lname" ?></h3>
<?= $dupRights ?>
<form name='userlogineditor' method='POST'>
<? if($error) echo "<font color='red'>$error</font>" ?>
<table width='100%'>
<?
hiddenElement('action', '');
hiddenElement('roleid', $roleid);
//selectRow($label, $name, $value=null, $options=null, $onChange=null
$longRole = $rights[0] == 'o' ? 'owner' : 'dispatcher';
//echo "[[$rights]] $longRole";
if(!$userid && !$duplicate) selectRow('Role', 'role', $longRole, array('Dispatcher'=>'dispatcher', 'Manager'=>'owner'), 'roleChanged()');
checkboxRow('Is an owner', 'isowner', $originalUser['isowner'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
hiddenElement('bizptr', $bizptr);
hiddenElement('rights', $rights);
hiddenElement('userid', $userid);
hiddenElement('origloginid', $user['loginid']);
hiddenElement('target', $target);
hiddenElement('logineditor', 1);
hiddenElement('duplicate', $duplicate);
inputRow('Username:', 'loginid', $user['loginid']);
if(!$user['rights'] || strpos($user['rights'], 'o-') === 0  || strpos($user['rights'], 'd-') === 0) {
	inputRow('First Name:', 'fname', $user['fname']);
	inputRow('Last Name:', 'lname', $user['lname']);
	inputRow('Email:', 'email', $user['email'], '', 'emailInput');
}
else {
	hiddenElement('fname', '');
	hiddenElement('lname', '');
}
echo "<tr><td colspan=2>";
if(!$user['loginid']) 
  if($names = suggestedLogins($userid, $lname, $fname, $nickname, $email)) {
		$names = array_merge(array('-- Suggested Usernames --' => ''),array_combine($names, $names));
    selectElement('', 'suggestions', null, $names, $onChange='takeSuggestion(this)');
    echo " ";
	}
echoButton('','Check Username Availability', 'checkAvailability("availabilitymessage")');
echo "</td></tr>";
echo "<tr style='display:hidden'><td id='availabilitymessage' colspan=2></td></tr>";

passwordRow("Password: {$user['passwordset']}:", 'password', '');
passwordRow('Retype Password:', 'password2', '');
inputRow('Temporary Password:', 'temppassword', $user['temppassword']);
$inactiveLooks = $user['active'] ? null : 'background:pink';
checkboxRow('Active', 'active', $user['active'], null, null, null, $inactiveLooks);
if($userid) {
	if(TRUE || mattOnlyTEST()) labelRow('', 'killsession', 
		fauxLink('Kill Session', "killSwitch()", 1, 'Kill the user&apos; current session'), $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
	labelRow('History', 'history', 
		fauxLink('Show', "userHistory()", 1, 'Show history'), $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
	checkboxRow("$noticesViewed notices viewed.  Clear?", 'clearnotices', 0);
}
	
echo "<tr><td colspan=2><hr></td></tr>";

$uprefs = array("frameLayout|Use tablet display for this user");

if($role == 'o' || $role == 'd') $uprefs[] = 'suppressRevenueDisplay|Hide sitter revenue from manager'
															.'||managerNickname|From Name in your emails';
$uprefs = join('||', (array)$uprefs);

$uprefs = explodePairsLine($uprefs);

if($duplicate)
	$userDetails['userprefs'] = $dupUserPrefs;

if($userDetails['error']) labelRow('WARNING', '', $userDetails['error'], $labelClass='warning', $inputClass=null);
foreach($uprefs as $k => $label) 
	if(!isset($userDetails['userprefs'][$k])) $userDetails['userprefs'][$k] = null;
if($uprefs) {
	echo "<tr><td>User Prefs - <b>CAREFUL</td><td><hr></td></tr>";
	foreach($userDetails['userprefs'] as $key => $value) {
		$label = $uprefs[$key] ? $uprefs[$key]."<br>($key)" : $key;
		inputRow($label, 'userpref_'.$key, $value);
	}
	echo "<tr><td colspan=2 align=center><hr></td></tr>";
}

echo "<tr><td colspan=2 align=center>";
if(mattOnlyTEST() && $userid) {
	echoButton('', 'Check/Repair Permissions', "document.location.href=\"system-login-repair.php?userid=$userid\"");
	echo "<img src='art/spacer.gif' width=30 height=1>";
	echoButton('', 'Link User', "document.location.href=\"maint-link-user.php?userid=$userid\"");
	echo "<img src='art/spacer.gif' width=30 height=1>";
}
$buttonLabel = $userid ? 'Save Changes' : 
               ($fname ? "Save New Login for $fname $lname" :
               "Save New Login");               
echoButton('', $buttonLabel, 'saveChanges()');
//user['loginid']
echo " <span onclick='countLogins(\"logincount\")' style='cursor:pointer;text-decoration:underline;'>Count Logins</span>: <span id='logincount'></span>";
echo " <span onclick='viewLogins(document.userlogineditor.loginid.value)' style='cursor:pointer;text-decoration:underline;'>View Logins (30 days)</span>";
echo "</td></tr>";
echo "<tr><td colspan=2 style='padding-top:15px;'>";
if(($userid && (!$logins || $userDetails['providerid'] || $userDetails['clientid'])&& isset($_SESSION['whosyerdaddy']))) {
	//print_r($userDetails);
	echoButton('', 'Erase User', "if(confirm(\"Really?\")) document.location.href=\"maint-edit-user.php?kill=$userid\"", 'HotButton', 'HotButtonDown');
}
//if(mattOnlyTEST()) echo "[[$rights]]";
if($userid && strpos('od', $rights[0]) !== FALSE)
	echoButton('', 'New User With Same Rights', "document.location.href=\"maint-edit-user.php?bizptr=$bizptr&duplicate=$userid\"");
?>
</table>
</form>
<? dumpLoginEditorScripts(); ?>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script language='javascript'>
function viewLogins(loginid) {
	window.opener.location.href='maint-logins.php?dayspast=30&pattern='+loginid;
}

function countLogins(tdid) {
	if(!document.userlogineditor.loginid.value) return;
	var loginid = document.userlogineditor.loginid.value;
	var url = 'maint-edit-user.php?countlogins='+loginid;
	//if($TEST) alert(url);
	document.getElementById(tdid).innerHTML='Working...';
  var xh = getxmlHttp();
  xh.open('GET',url,true);
  xh.onreadystatechange=function() { 
		if(xh.readyState==4) { 
			document.getElementById(tdid).innerHTML=xh.responseText; 
  	} 
  }
  xh.send(null);
}


function roleChanged() {
	var el = document.getElementById('isowner');
	var row = $(el).parent().parent();
	var roleEl = document.getElementById('role');
	var shouldHide;
	if(roleEl) shouldHide = roleEl.options[roleEl.selectedIndex].value == 'dispatcher' ? true : false;
	else {
		var rights = document.getElementById('rights').value;
		shouldHide = rights[0] == 'o' ? false : true;
	}
	var isHidden = row.css('display') == 'none';
	if(shouldHide != isHidden) row.toggle();
}
roleChanged();
</script>