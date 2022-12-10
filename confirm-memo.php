<? // confirm-memo.php
// called from response.php when the redirecturl points here
// token - token associated with confirmation
// logout - end session when non-null

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "confirmation-fns.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "request-fns.php";

$filter = $_REQUEST['token'] ? "token = '{$_REQUEST['token']}'" : "confid = '{$_REQUEST['confid']}'";
$confirmation = fetchFirstAssoc(
		"SELECT respondentptr, resolutiondate, respondenttable, correspaddr as email, msgid, subject
		 FROM tblconfirmation
		 LEFT JOIN tblmessage ON msgid = msgptr
		 WHERE $filter LIMIT 1");

$locked = locked('');

$error = !$confirmation;
if(!$error && $confirmation['resolutiondate']) $error = "This confirmation request has already received a response.";


if(!$error && ($role = userRole()) != 'o') {
	if(!($confirmation['respondenttable'] == 'tblclient' && $role == 'c' && $confirmation['respondentptr'] == $_SESSION["clientid"])
			&& !($confirmation['respondenttable'] == 'tblprovider' && $role == 'p' && $confirmation['respondentptr'] == $_SESSION["providerid"]) 
		)
			$error = "Insufficient rights to execute this confirmation";
}

if(!$error && $_REQUEST['action']) {
	$action = $_REQUEST['action'];
	if($action == 'decline' && $_POST) {
		$request = $_REQUEST;
		$request['resolved'] = 0;
		$request['requesttype'] = 'NotificationResponse';
		$request['providerptr'] = $confirmation['respondentptr'];
		$pname = getProviderShortNames("WHERE providerid = '{$confirmation['respondentptr']}' LIMIT 1");
		$pname = $pname ? current($pname) : 'sitter';
		$request['subject'] = "Confirmation declined by $pname";
		$declineMsgPtr = saveNewClientRequest($request);
		if($_REQUEST['token']) declineWithToken($_REQUEST['token'], $declineMsgPtr, 'notify');
		else decline($_REQUEST['confid'], $declineMsgPtr, 'notify');
		//  any remaining tokens for the confirmation will be consumed as they expire (and when generateResponseURL() is called)
		$_SESSION['frame_message'] = "Thanks for responding.";
	}
	else if($action == 'confirm') {
		if($_REQUEST['token']) confirmWithToken($_REQUEST['token'], null, 'notify');
		else confirm($_REQUEST['confid'], null, 'notify');
		$_SESSION['frame_message'] = "Thanks for confirming!";
	}
	if($action == 'confirm' || $_POST) {
		header("Location: ".globalURL("index.php"));
		exit;
	}
}

$frame = $role == 'c' ? 'frame-client.html' : 'frame.html';

include $frame;

if($error) {
	echo "<font color='red'>$error</font><p>";
	echo fauxLink('Go to Home Page', 'document.location.href="index.php";');
	echo "<p>";
	echo fauxLink('Log Out', 'document.location.href="login-page.php?logout=1";');
}
else if($_POST || ($action == 'confirm')) {  // WILL NEVER HAPPEN
	echo $action == 'confirm' ? "<h2>Thanks for confirming!</h2>" : "<h2>Thanks for responding.</h2>";

	echo fauxLink('Go to Home Page', 'document.location.href="index.php";');
	echo "<p>";
	echo fauxLink('Log Out', 'document.location.href="login-page.php?logout=1";');
}
else {
	echo "<h2>Please Confirm These Changes or Send Us a Comment</h2><center>";
	echoButton('', 'Confirm', "submitChanges(\"confirm\")");
?>
<p>- or -
<form method='POST' name='clientrequestform'>
<table>
<?
	hiddenElement('action', '');
	hiddenElement('confid', $_REQUEST['confid']);
	hiddenElement('token', $_REQUEST['token']);
	labelRow('Comments:', '');
	echo "<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=50></textarea></td></tr>";
	echo "<tr><td colspan=2 align=center>".echoButton('', 'Decline and Send Comment', "submitChanges(\"decline\")", null,null, 1)."</td></tr>";
?>
</table>
</form>
</center><script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('note','Note');
function submitChanges(action) {
	document.getElementById('action').value = action;
  url = "comm-view.php?id=<?= $confirmation['msgid'] ?>";
	document.getElementById('note').value = 
		"RE: <a href='"+url+"'><?= $confirmation['subject'] ?></a><p>"
		+document.getElementById('note').value;
  
	document.clientrequestform.submit();
}
</script>
<?	
}
include "frame-end.html";
