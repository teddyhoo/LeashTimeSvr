<? // temp-password-assignment.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "login-fns.php";
require_once "email-fns.php";
require_once "gui-fns.php";

if($_REQUEST['logout']) {
	if($_SESSION['trainingMode']) {
		require_once "training-fns.php";
		require_once "common/init_db_petbiz.php";
		turnOffTrainingMode();
	}
	session_unset();
  session_destroy();
	if($goto) {
		globalRedirect("temp-password-assignment.php?bizid={$_REQUEST['bizid']}");
		exit;
	}
}

if($_REQUEST['bizid'] && "".(int)"{$_REQUEST['bizid']}" != $_REQUEST['bizid']) { // against injection attacks 7/31/2020
	$message = 'Sorry, your account information was not found.<p>'.
				"Return to <a href='login-page.php?bizid={$_REQUEST['bizid']}'>Login</a>"
				.($homepage ? " or to <a href='$homepage'>$bizname </a>" : "");
	$pageTitle = "Unknown User";
	logError("Temp Password request failed: [{$_REQUEST['username']}] [{$_REQUEST['emailaddressofrecord']}] [BOGUS BIZID SUPPLIED : {$_REQUEST['bizid']}]");
	include "frame.html";
	echo $message;
	include "frame-end.html";
	if(session_id()) session_destroy();
	exit;
}

if($_REQUEST['bizid']) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$_REQUEST['bizid']}' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 'force');
	$homepage = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage' LIMIT 1");
	$bizname = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	require "common/init_db_common.php";
}

if($_POST) {
	extract($_POST);
	if(!$username || !$emailaddressofrecord) $error = 'Bad data supplied.';
	require_once('common/init_db_common.php');
	$userid = fetchUserIdWithUsernameAndEmail($username, $emailaddressofrecord);
	if(!$userid || is_array($userid)) {
		$message = 'Sorry, your account information was not found.<p>'.
					"Return to <a href='login-page.php?bizid={$_REQUEST['bizid']}'>Login</a>"
					.($homepage ? " or to <a href='$homepage'>$bizname </a>" : "");
		$pageTitle = "Unknown User";
		logError("Temp Password request failed: [{$username}] [{$emailaddressofrecord}]");
	}
	else {
		$biz = fetchRow0Col0("SELECT bizptr FROM tbluser WHERE userid = $userid LIMIT 1");
		if($biz) $biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $biz LIMIT 1");
		$tmpPass = setTemporaryPassword($userid);
		$hint = strpos($tmpPass, "l") !== FALSE ? ' (all letters, no numbers)' : '';
		$body = "Your temporary password is <span style='background:yellow;font-family: Times New Roman, Times, serif'>$tmpPass</span>$hint .\n\n<p>".
						"You may use it one time to login, and you will be required to set a new password before you can use the system.\n\n<p>".
						"If you fail to set your password or forget your new password, you can request a new temporary password again.";
		if($biz) reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		$sendFailure = sendEmail($emailaddressofrecord, 'The information you requested.', $body, null, 'html');
	
		if($sendFailure) { // failure
			$message = "The password recovery service is temporarily out of service.  Please contact technical support.";
			$pageTitle = "Technical Problem";

		}
		else {
			$message = "A temporary password has been emailed to you.  You may use this password to login one time, and ".
					"you will be required to set a new password before you can use the system.<p>".
					"If you remember your old password before you login with the new password, you can ignore this temporary ".
					"password and use the old one instead.<p>".
					"<a href='login-page.php?bizid={$_REQUEST['bizid']}'>Login</a>";
			$pageTitle = "Temporary Password Assigned";
			// make a note in the local db
			logChange($userid, 'tbluser', 'q', "TEMPPASS|$username|$emailaddressofrecord");
			require "common/init_db_common.php";
			// make a note in the global db
			logChange($userid, 'tbluser', 'q', "TEMPPASS|$username|$emailaddressofrecord");
			}
	}
}

$pageTitle = isset($pageTitle) ? $pageTitle : "Forgotten Password";

$lockChecked = true;

if($thisBiz = $_REQUEST['bizid']) {
	$_SESSION["uidirectory"] = "bizfiles/biz_$thisBiz/clientui/";
	if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$thisBiz/";
	$suppressMenu = 1;
	include "frame-client.html";
}
else include "frame.html";
// ***************************************************************************

if($message) {
	echo $message;
	include "frame-end.html";
	if(session_id()) session_destroy();
	exit;
}

?>
<span class='fontSize1_1em'>
If you have forgotten your password, please submit this form and a temporary password will be emailed to your address of record.
<p>
<form name='forgottenpasswordform' method='post'>
<table>
<input type='hidden' name='bizid' value='<?= $_REQUEST['bizid'] ?>'>
<tr><td>Your Username:</td><td><input name='username' autocomplete='off'></td></tr>
<tr><td>Your Email Address:</td><td><input name='emailaddressofrecord' autocomplete='off'></td></tr>
<tr><td>&nbsp;</td><td><input type='button' class='Button' onClick='checkAndSubmit()' value='Request Temporary Password'>
		<? if($homepage) echo "<p>or visit <a href='$homepage'>$bizname</a> for help (especially if you have forgotten your Username).";
			 else echo "<p>contact your pet care provider for help  (especially if you have forgotten your Username).";
		?>
		</td></tr>
</table>
<p>
<a href='login-page.php<? if($_REQUEST['bizid']) echo "?bizid={$_REQUEST['bizid']}"; ?>'>Back to Login Page</a>
</span>
<?
if(FALSE && (mattOnlyTEST() || userRole() == 'z')) {
	$days = 10;
	$earliest = date('Y-m-d 00:00:00', strtotime("- $days DAYS"));
	$recentAssignments = fetchAssociations("SELECT * FROM tblchangelog WHERE note LIKE 'TEMPPASS%' AND time > '$earliest' ORDER BY time DESC", 1);
	foreach($recentAssignments as $i => $assignment) {
		$note = explode('|', $assignment['note']);
		$time = strtotime($assignment['time']);
		$recentAssignments[$i]['date'] = shortDateAndTime($time);
		$recentAssignments[$i]['username'] = "[{$note[1]}]";
		$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE loginid = '".trim($note[1])."' LIMIT 1", 1);
		$recentAssignments[$i]['role'] = $user['rights'][0];
		$recentAssignments[$i]['email'] = $note[2];
	}
	
	$databases = fetchCol0("SHOW databases");
	$INACTIVEspan = "<span class='warning'>(inactive) </span>";
	$recentErrors = fetchAssociations("SELECT * FROM tblerrorlog WHERE message LIKE 'Temp Password request failed%' AND time > '$earliest' ORDER BY time DESC", 1);
	foreach($recentErrors as $i => $error) {
		$time = strtotime($error['time']);
		$recentErrors[$i]['date'] = shortDateAndTime($time);
		$userNameAndEmailAddressSupplied = array();
		preg_match_all("/\[([^]]+)\]/", $error['message'], $userNameAndEmailAddressSupplied); // first=complete match, secon=stripped
		list($username, $email) = $userNameAndEmailAddressSupplied[0];
		list($bare_username, $bare_email) = $userNameAndEmailAddressSupplied[1];
		$recentErrors[$i]['username'] = $username;
		$recentErrors[$i]['email'] = $email;
		require "common/init_db_common.php";
		$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE loginid = '".trim($bare_username)."' LIMIT 1", 1);
		
		if(!$user) $recentErrors[$i]['error'] = "user [".trim($bare_username)."] does not exist.";
		else if(1){
			$inactiveLogin  = !$user['active'] ? $INACTIVEspan : '';
			$recentErrors[$i]['username'] = "$inactiveLogin$username";
			$recentErrors[$i]['role'] = $user['rights'][0];
			$role = $user['rights'][0];
			//echo "[[[$role]]]".print_r($user);
			if(in_array($role, array('c','p'))) {
				$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1", 1);
				if(!in_array($biz['db'], $databases)) $recentErrors[$i]['error'] = "dead biz: {$biz['db']}";
				else {
					reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
					$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");
					$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE userid = {$user['userid']} LIMIT 1");
					if($role == 'p') {
						$person = $provider;
						if(!$person) {
							$recentErrors[$i]['error'] = 'No sitter with this userid was found';
							if($client) $recentErrors[$i]['error'] .= "<br>However, the client {$client['fname']} {$client['lname']} DOES have this login id";
						}
						else {
							$inactivePerson  = !$person['active'] ? $INACTIVEspan : '';
							$recentErrors[$i]['error'] = 
								"{$inactivePerson}person: {$person['fname']} {$person['lname']}"
								."<br>email: {$person['email']}";
						}
					}
					if($role == 'c') {
						$person = $client;
						if(!$person) {
							$recentErrors[$i]['error'] = 'No client with this userid was found';
							if($provider) $recentErrors[$i]['error'] = "<br>However, the sitter {$provider['fname']} {$provider['lname']} DOES have this login id";
						}
						else {
							$inactivePerson  = !$person['active'] ? $INACTIVEspan : '';
							$recentErrors[$i]['error'] = 
								"{$inactivePerson}person: {$person['fname']} {$person['lname']}"
								."<br>email: {$person['email']}";
						}
					}
				}
			}
			else {
				$recentErrors[$i]['error'] = "staff: {$user['fname']} {$user['lname']}";
			}








			
		}
		//preg_match_all("/\[([^\]]*)\]/", $error['message'], $userNameAndEmailAddressSupplied);
		//echo "<p>".$error['message']."<br>";print_r($userNameAndEmailAddressSupplied);
		//echo "<p>{$username}, {$email}";
		//$assignment['username'] = "[{$note[1]}]";
		//$assignment['role'] = $user['rights'][0];
		//$assignment['email'] = $note[2];
	}
	
	
	//logError("Temp Password request failed: [{$username}] [{$emailaddressofrecord}]");

	echo "<hr>Matt/z- Only<p>";
	if(!$recentAssignments) echo "No temp password assignments in the last $days days.";
	else {
		$columns = explodePairsLine('date|Date||username|User Name||role|Role||email|Email');
		echo "Temp password assignments in the last $days days<p>";
		tableFrom($columns, $recentAssignments, "border=1", $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses);
	}
	echo "<hr>";
	if(!$recentErrors) echo "No temp password assignment errors in the last $days days.";
	else {
		$columns = explodePairsLine('date|Date||username|User Name||role|Role||email|Email||error|Error');
		echo "Temp password assignment ERRORS in the last $days days<p>";
		tableFrom($columns, $recentErrors, "border=1", $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses);
	}


}

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('username','Username','emailaddressofrecord','Email address of record');
function checkAndSubmit() {
	if(MM_validateForm(
		'username', '', 'R',
		'emailaddressofrecord', '', 'R',
		'emailaddressofrecord', '', 'isEmail'
		)) {
		document.forgottenpasswordform.submit();
	}
}
</script>
<?
// ***************************************************************************

include "frame-end.html";
//phpinfo();
?>
