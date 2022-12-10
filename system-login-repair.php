<? // system-login-repair.php

require_once "common/init_session.php";
//require_once "common/init_db_petbiz.php";
require_once "common/init_db_common.php";
require_once "response-token-fns.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";

// Verify login information here
locked('z-');
$userid = $_REQUEST['userid'];
$user = findSystemLogin($userid, 'includetemppassword');
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user['bizptr']} LIMIT 1");
$rights = $user['rights'] ? $user['rights'] : 'XXXXXXX';
$roles = explodePairsLine('c-|client||p-|provider||d|dispatcher||o|owner');
$role = $roles[substr($rights, 0, 2)];

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($biz['dbhost'], $biz['db'], $biz['dbuser'], $biz['dbpass']);
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'FORCE');

$person = fetchFirstAssoc("SELECT * FROM tblclient WHERE userid = $userid LIMIT 1");
if(!$person) $person = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = $userid LIMIT 1");

if($person['clientid']) $targetRole = 'client';
else if($person['providerid']) $targetRole = 'provider';
require_once "common/init_db_common.php";

if($_POST['fix']) {
	logChange($userid, 'tbluser', 'm', "Permissions repaired.");
	$data = array('userid'=>$userid, 'rights'=>basicRightsForRole($targetRole));
	updateSystemLogin($data, $clientOrProviderOnly=true);
	$user = findSystemLogin($userid, 'includetemppassword');
	$message = "{$person['fname']} {$person['lname']} has been given standard $targetRole permissions: {$user['rights']}";
}

$windowTitle = 'System Login Repair';
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";
if($message) echo "<p class='tiplooks'>$message</p>";

if($targetRole != $role) {
	echo "<p class='warning'>{$person['fname']} {$person['lname']} ({$user['loginid']})"
				." is a $targetRole, but has a {$role}'s permissions</p>";
	echo "<form name='repairform' method='POST'>";
	echoButton('', 'Correct Permissions', 'document.repairform.submit()');
	echo " Permissions will be set to: <b>".basicRightsForRole($targetRole)."</b>";
	hiddenElement('fix', 1);
	hiddenElement('userid', $userid);
	echo "</form><p>";
}
else {
	echo "<p>{$person['fname']} {$person['lname']} ({$user['loginid']}) is a $role with permissions consistent with that role.<p>";
}
fauxLink('Return to System Login Editor', "document.location.href=\"maint-edit-user.php?userid=$userid&bizptr={$user['bizptr']}\"");