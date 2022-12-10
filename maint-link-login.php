<? // maint-link-login.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('o-');
//extract(extractVars('userid', $_REQUEST));
if(userRole() != 'o') {
	echo "FAIL: You cannot access any business from here.  Please log out and log back in as staff.";
	exit;
}

else {
	$olduser = getUserLogin($_SESSION['auth_user_id']);
	$user = getUserLogin($_GET['userid']);
	if(!$user) $failure = "Invalid destination user.";
	else if($olduser['linkgroup'] != $user['linkgroup']) $failure = "Invalid destination user link.";
	else if(!$failure && !($failure = loginAsUserFailure($user))) echo "SUCCESS";
	
	if($failure) echo $failure;
}

function getUserLogin($userid) {
  $query = "SELECT tbluser.*, 
  					tblpetbiz.*, tbluserpref.value as linkgroup 
  					FROM tbluser
            left join tblpetbiz on bizid = bizptr
            left join tbluserpref on userptr = userid AND property = 'linkgroup'
            WHERE userid = $userid";
  return fetchFirstAssoc($query);
}

function loginAsUserFailure($user) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	$lastUserId = $_SESSION['auth_user_id'];
	$lastBiz = "{$_SESSION["bizname"]},{$_SESSION["db"]}";
	if(!$user) $failure = "Login not found.";
	else {
		require_once "login-fns.php";
		clearSessionButLeave(array());
		require_once "preference-fns.php";
		loginUser($user);
		
		/* TEMPORARY */
		reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass']);
		$_SESSION['custom_pet_fields_enabled'] = in_array('relpetcustomfield', fetchCol0("SHOW TABLES"));
		//$_SESSION["officenotes_logbook_enabled"] = true;
		/* TEMPORARY */

	}
	// record login attempt at corporate
	if(!$failure) {
		reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass']);
		logChange($user['userid'], 'linkeduserlogin', 'b' , "$lastUserId,$lastBiz=>{$_SESSION['auth_user_id']},{$_SESSION["bizname"]},{$_SESSION["db"]}");
	}
	return $failure;
}

