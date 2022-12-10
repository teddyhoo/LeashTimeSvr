<? // prospect-leashtime.php

require_once "common/init_session.php";
//if($_REQUEST['support']) {print_r($_REQUEST['support']);exit;}
//if(mattOnlyTEST()) {	require_once "common/init_db_common.php";logError("prospect-leashtime.php: ".print_r($_POST,1));}
if(in_array(userRole(), array('o', 'd', 'c', 'p'))) {
//if(mattOnlyTEST()) {echo "USER ROLE: [".userRole()."]<hr>";/*exit;*/}
	// generate a regular request by the client business
	$_POST['type'] = 'comm_comment';
	$_POST['name'] = "{$_POST['fname']} {$_POST['lname']}";
	submitFeedBackInstead(); // this will include logged in user info
	//$bizid = $_SESSION["bizptr"];
	//require_once "common/init_db_common.php";
	//$ltcustbiz = fetchFirstAssoc("SELECT db, dbhost, dbuser, dbpass FROM tblpetbiz WHERE bizid = 68 LIMIT 1");
	//list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	//reconnectPetBizDB($dbN=$ltcustbiz['db'], $dbhostN=$ltcustbiz['dbhost'], $dbuserN=$ltcustbiz['dbuser'], $dbpassN=$ltcustbiz['dbpass'], $force=true);
	//$ltclient = fetchFirstAssoc("SELECT * FROM tblclient WHERE garagegatecode = $bizid LIMIT 1");
	//	require_once "common/init_db_common.php";
}
//}
else if($_REQUEST['support']) {
	// send an email to support
	sendEmailToSupport();
}
else {
	$_POST['pbid'] = 68;
	require_once "prospect-request.php";
}

function sendEmailToSupport() {
	$note = buildNoteHTML($withBrowserDetails=true);
	require_once "email-fns.php";
	$subject = 
		"Info site support: " // SUBJECT is CRITICAL FOR Help Desk TO DETERMINE THE REAL SENDER ADDRESS.  See: W:\html\hlptick\inc\pipe_functions.inc.php
		."{$_POST['lname']} / {$_POST['fname']}";
	$error = sendEmail('support@leashtime.com', $subject, $note, $cc = null, $html=1);
	if($error) echo $error;
}

function submitFeedBackInstead() {
	$_POST['type'] = 'comm_comment';
	require "common/init_db_petbiz.php";
	$noClose = 1;
	$redirect = "http://leashtime.com/info/?q=node/1";
	$_REQUEST['body'] = buildNoteHTML();
	include "feedback.php";
}

function buildNoteHTML($withBrowserDetails=false) {
	$note = str_replace("\n\n", "<p>", str_replace("\n", "<br>", str_replace("\r", "", $_POST['note'])));
	
//global $TEST; $TEST = 1;
//echo "NOTE: ".print_r($_POST,1);
	if($withBrowserDetails)
		$withBrowserDetails = 
"\n<tr>
<td style='font-weight:bold'>Browser:</td>
<td>{$_SERVER["HTTP_USER_AGENT"]}</td>
</tr>
<tr>
<td style='font-weight:bold'>IP Address:</td>
<td>{$_SERVER["REMOTE_ADDR"]}</td>
</tr>\n";
	
	$note = <<<BODY
<table style="border: solid black 0px;" border="0">
<tbody>
<tr>
<td colspan=2><b>This feedback was sent from the LeashTime Info Site <i>Contact Support</i> form</b></td>
</tr>
<tr>
<td style='font-weight:bold'>Your Name:</td>
<td>{$_POST['lname']}</td>
</tr>
<tr>
<td style='font-weight:bold'>Your Business&apos;s Name:</td>
<td>{$_POST['fname']}<br /></td>
</tr>
<tr>
<td style='font-weight:bold'>Phone:</td>
<td>{$_POST['phone']}<br /></td>
</tr>
<tr>
<td style='font-weight:bold'>Email:</td>
<td>{$_POST['email']}<br /></td>
</tr>
$withBrowserDetails<tr>
<td style='font-weight:bold'>Questions/Comments:</td>
</tr>
<tr>
<td colspan="2">$note</td>
</tr>
</tbody>
</table>
<p>\n
WEB FORM Email: {$_POST['email']}\nSubmitter: {$_POST['lname']}\n.
BODY;
	return $note;
}

$index = substr($_SERVER["HTTP_HOST"], 0, strpos($_SERVER["HTTP_HOST"], '?'));

if(!$TEST) echo "Location: http://{$_SERVER["HTTP_HOST"]}$index";
//header("Location: http://{$_SERVER["HTTP_HOST"]}$index");
