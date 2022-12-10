<? // lt-staff-login.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');
extract(extractVars('bizptr,confirmed', $_REQUEST));

if(userRole() != 'z') {
	echo "FAIL: You cannot access any business from here.  Please log out and log back in as staff.";
	exit;
}
if(!$confirmed) {
	$bizName = fetchRow0Col0("SELECT bizname FROM tblpetbiz WHERE bizid = $bizptr LIMIT 1");
	if(!getStaffLogin($bizptr)) {
		echo "{$_SESSION['auth_login_id']} has not logged in to $bizName before.  Create new manager login?";
	}
	else echo "Log in to $bizName now?";
	exit;
}
else {
	$user = getStaffLogin($bizptr);
	if(!$user) $user = createStaffLogin($bizptr);
	if(!$user) echo "FAILED TO CREATE STAFF USER FOR {$_SESSION['auth_loginid']} AT $bizName";
	else {
		$maxRecent = 25;
		$thisBizId = $user['bizptr'];
		$recent = fetchRow0Col0("SELECT value from tbluserpref WHERE property = 'recentdbs' AND userptr = {$_SESSION['auth_user_id']}");
		if($recent) {
			$recent = explode(',', "".fetchRow0Col0("SELECT value from tbluserpref WHERE property = 'recentdbs' AND userptr = {$_SESSION['auth_user_id']}"));
			if($recentIndex = array_search($thisBizId, $recent)) unset($recent[$recentIndex]);
			$recent = array_reverse($recent);
		}
		else $recent = array();
		array_push($recent, $thisBizId);
		$recent = array_reverse($recent);
		while(count($recent) > $maxRecent) array_pop($recent);
		replaceTable('tbluserpref', array('userptr'=>$_SESSION['auth_user_id'], property=>'recentdbs', 'value'=>join(',', $recent)), 1);
		if($failure = loginAsLTStaffFailure($user)) echo $failure;
		echo "SUCCESS";
	}
}

function createStaffLogin($bizptr) {
	$staffUser = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = {$_SESSION['auth_user_id']} LIMIT 1");
	$bizName = fetchRow0Col0("SELECT bizname FROM tblpetbiz WHERE bizid = $bizptr LIMIT 1");
	$loginid = "LTSTAFF_{$_SESSION['auth_user_id']}_$bizptr";
	$n = 1;
	$rights = $staffUser['rights'];
	if(!in_array('b', explode(',', (strlen($rights) > 2 ? substr($rights, 2) : '')))) $ccRights = '*cm,*cc';

	while(!($userid = insertTable('tbluser', 
								array('loginid'=>$loginid, 
											'bizptr'=>$bizptr, 
											'rights'=>"o-$ccRights", 
											'active'=>'1', 
											'lname'=>$staffUser['lname'], 
											'fname'=>$staffUser['fname'],
											'ltstaffuserid'=>$_SESSION['auth_user_id']),
								1)) && $n < 5) { // should never fail
		$loginid = "LTSTAFF_{$_SESSION['auth_user_id']}_$n";
		$n++;
	}
	return $userid ? getStaffLogin($bizptr) : null;
}
	
function getStaffLogin($bizptr) {
  $query = "SELECT userid, loginid, password, rights, active, bizptr, fname, lname, tempPassword, ltstaffuserid, 
  						tblpetbiz.* 
  					FROM tbluser
            LEFT JOIN tblpetbiz on bizid = bizptr
            WHERE bizptr = $bizptr AND ltstaffuserid = {$_SESSION['auth_user_id']}";
  return fetchFirstAssoc($query);
}

function loginAsLTStaffFailure($user) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);

	if(!$user) $failure = "Login not found.";
	else {
		require_once "login-fns.php";
		require_once "preference-fns.php";
		$staffuser = $_SESSION["auth_user_id"];
		loginUser($user, null, 'allowinactive');
		
		$_SESSION["staffuser"] = $staffuser;

		/* TEMPORARY */
		reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass']);
		$_SESSION['custom_pet_fields_enabled'] = in_array('relpetcustomfield', fetchCol0("SHOW TABLES"));
		//$_SESSION["officenotes_logbook_enabled"] = true;
		/* TEMPORARY */

	}
	// record login attempt at corporate
	if(!$failure) {
		reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass']);
		logChange($user['userid'], 'stafflogin', 'b' , $staffuser);
	}
	return $failure;
}

