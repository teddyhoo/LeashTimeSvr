<? // feedback.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "gui-fns.php";
require_once "email-fns.php";


if(!$_SESSION["auth_login_id"]) {
	$error = "You're logged out.  Please log in again to contact us.";
}

if(!$error) {	
	$name = null;
	$role = userRole();
	if(in_array($role, array('c','p'))) {
		if($role == 'c') {
			$name = $_SESSION["clientname"];
			$email = array_key_exists("clientemail", $_SESSION) ? $_SESSION["clientemail"] : fetchRow0Col0("SELECT email FROM tblclient WHERE clientid = {$_SESSION['clientid']} LIMIT 1");
		}
		else {
			$name = $_SESSION["fullname"] ? $_SESSION["fullname"] : fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$_SESSION['providerid']} LIMIT 1");
			$email = array_key_exists("provider_email", $_SESSION) ? $_SESSION["provider_email"] : fetchRow0Col0("SELECT email FROM tblprovider WHERE providerid = {$_SESSION['providerid']} LIMIT 1");
		}
	}
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$biz = $_SESSION['bizptr'] ? fetchRow0Col0("SELECT bizname FROM tblpetbiz WHERE bizid = {$_SESSION['bizptr']}") : '--';
	$org = $_SESSION['orgptr'] ? fetchRow0Col0("SELECT orgname FROM tblbizorg WHERE orgid = {$_SESSION['orgptr']}") : '--';
	if(!$name) {
		$user = fetchFirstAssoc("SELECT CONCAT_WS(' ', fname, lname) as name, email FROM tbluser WHERE userid = {$_SESSION['auth_user_id']} LIMIT 1");
		$name = $user['name'];
		$email = $user['email'];
	}
	if($_POST) {
		$role = userRole();
		$roles = array('p'=>'Provider', 'c'=>'Client', 'o'=>'Manager', 'x'=>'Corporate', 'z'=>'Leashtime support', 'd'=>'dispatcher');
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		if(!$name) $name = "(user {$_SESSION['auth_login_id']})";
		$type = $_REQUEST['type'] == 'comm_bug' ? 'Bug Report' : 'Comment';
		$suppliedName = $_REQUEST['name'] == $name ? '' : $_REQUEST['name'];
		$body =
		"<b>$type</b> from:<p>"
		."<b>User:</b> $name [login: {$_SESSION['auth_login_id']}] [{$_SESSION['auth_user_id']}]<br>"
		.($suppliedName ? "<b>Name supplied:</b> $suppliedName<br>" : '')
		."<b>Role:</b> {$roles[$role]}<br>"
		."<b>Email:</b> $email<br>"
		."<b>Business:</b> $biz".($_SESSION['bizptr'] ? " [{$_SESSION['bizptr']}]" : '')."<br>"
		."<b>Organization:</b> $org".($_SESSION['orgptr'] ? " [{$_SESSION['orgptr']}]" : '')."<p>"
		//."<b>Browser:</b> ".print_r(get_browser(null, true), 1)."<p>"
		."<b>Browser:</b> {$_SERVER["HTTP_USER_AGENT"]}<p>"
		."<b>IP Address:</b> {$_SERVER["REMOTE_ADDR"]}<p>"
		."<b>URL:</b> {$_REQUEST['url']}<p><b>Comments:</b><p>"
		.str_replace("\r", "", 
				str_replace("\n", "<br>", 
				str_replace("\n\n", "<p>", $_REQUEST['body'])));
		
		
		
		
		//$error = sendEmail('ted@leashtime.com,matt@leashtime.com', "$type from ".($suppliedName ? $suppliedName : $name), $body, $cc = null, $html=1);
		$error = sendEmail('support@leashtime.com', "$type from ".($suppliedName ? $suppliedName : $name), $body, $cc = null, $html=1);
		submitLeashTimeCustomersRequest($_REQUEST['type'], "$type from ".($suppliedName ? $suppliedName : $name), $body);
		if(!$error) {
			if(!$noClose) echo "<script language='javascript'>window.close();</script>";
			if($redirect) header("Location: $redirect"); // set in the info site's prospect.php script
			exit;
		}
	}
}

function submitLeashTimeCustomersRequest($rtype, $subject, $body) {
	include "common/init_db_common.php";
	include "request-fns.php";
	
	$extrafields = "<extrafields>";
	$extrafields .= "<extra key='x-label-Subject'><![CDATA[$subject]]></extra>";
	$extrafields .= "</extrafields>";

	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$request['requesttype'] = $rtype == 'comm_bug' ? 'BugReport' : 'Comment';
	$request['note'] = $body;
	$request['subject'] = $subject;
	$request['extrafields'] = $extrafields;
	saveNewBugReportCommentRequest($request, $notify=false);
}

$frame = $_REQUEST['mobile'] ? "mobile-frame-bannerless.php" : "frame-bannerless.php";
include $frame;
if($error) {
	echo "<font color=red>$error</font>";
	exit;
}
//echo "In progress...".$_REQUEST['url'];exit;
?>
<style>
p {font-size: 1.1em;}
</style>
<h2>Comments and Bug Reports</h2>
<p>
This form will send a message to <b>LeashTime Technical Support</b>.
<p style='color:red;font-size:1.3em;text-align:center;'>This message <b>will not be sent to <?= $_SESSION["bizname"] ?></b>.
<p> Please use it to submit comments or questions about <b>LeashTime</b>, and to report bugs.<p>
Please supply an email address or phone number if you wish LeashTime to get back to you.
<hr>
<form name='feedbackform' method='POST'>
<table style='font-size:1.1em'>
<?
$options = array('Bug Report'=>'comm_bug', 'Question / Comment'=>'comm_comment');
echo "<tr><td colspan=2>Is this a Bug Report or a Comment/Question?</td><tr>";
radioButtonRow('', 'type', 'comm_comment', $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
inputRow("Your name:", 'name', $name, '', 'emailInput');
$cols = $_REQUEST['mobile'] ? 46 : 80;
textRow('Your Comments', 'body', '', $rows=15, $cols, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null);
?>
</table>
<?
echoButton('', 'Send', 'if(!document.getElementById("body").value) alert("Please write something first."); else document.feedbackform.submit()');
?>
</form>