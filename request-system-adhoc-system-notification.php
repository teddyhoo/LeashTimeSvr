<? // request-system-adhoc-system-notification.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";

// For LeashTime STaff Use Only
// Context: Dashboard or Client's Own DB

$userRole = userRole();
if(!($userRole = userRole())) $error = "Not logged in.";
else if($userRole == 'o' && !$_SESSION["staffuser"]) $error = 'LeashTime Staff Use Only.';
else if($userRole == 'z') $error = '';
else if(!in_array($userRole, array('o', 'z'))) $error = "Insufficient permissions: $userRole";

if($error) { echo $error; exit; }

if($_POST) {
	
	function hasAny($str, $pats) {
		foreach(explode(',', $pats) as $pat)
			if(strpos($str, $pat) !== FALSE)
				return true;
	}
	
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	$bizid = $_POST['bizid'];
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1", 1);
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $bizv['dbpass']);
	require_once "request-fns.php";
	
	$msgBody = $_POST['msgbody'];
	$convertEOLs = !hasAny($msgBody, '<br ,<br>,<p ,<p>');
	if($convertEOLs) $msgBody = str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", $msgBody)));

	$reqId = saveNewSystemNotificationRequest($_POST['subject'], $msgBody, $extra=array('creator'=>$_POST['creator']));
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	require_once "frame-bannerless.php";
	echo "<h2>Success</h2>Generated System Request # $reqId";
	exit;
}

if($userRole == 'o') {
	$bizid = $_SESSION["bizptr"];
	$userid = $_SESSION["staffuser"];
}
else if($userRole == 'z') {
	$bizid = $_REQUEST["bizid"];
	$userid = $_SESSION["auth_user_id"];
}

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
require "common/init_db_common.php";
$username = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE userid = $userid LIMIT 1", 1);
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1", 1);
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $bizv['dbpass']);
require_once "preference-fns.php";
$bizName = fetchPreference('bizName');
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);

require_once "frame-bannerless.php";
?>
<h2>Generate a System Request</h2>
<h3>Business: <span class='fontSize1_6em'><?= $bizName ?></span></h3>
<b>Creator:</b> <?= $username ?><p>
<form name='notification' method="POST">
<? 
hiddenElement('bizid', $bizid);
hiddenElement('creator', $username);
echoButton('', 'Generate', "checkAndSubmit()"); 
?><p>
Subject: <input class='VeryLongInput' id='subject' name='subject'><p>
Message:<br><textarea id='msgbody' name='msgbody' style='width:400px;height:200px;'></textarea>
</form>
<script language='javascript' src='check-form.js'></script>
<script>
function checkAndSubmit(version) {
	if(MM_validateForm('subject', '', 'R',
											'msgbody', '', 'R'))
			document.notification.submit();
}
</script>