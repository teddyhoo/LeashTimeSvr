<? // confirm-schedule.php
// called from response.php when the redirecturl points here
// token - token associated with confirmation
// logout - end session when non-null

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "confirmation-fns.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "request-fns.php";

$filter = $_REQUEST['token'] ? "token = '{$_REQUEST['token']}'" : "confid = '{$_REQUEST['confid']}'";
$confirmation = fetchFirstAssoc(
		"SELECT respondentptr, respondenttable, correspaddr as email, msgid, subject
		 FROM tblconfirmation
		 LEFT JOIN tblmessage ON msgid = msgptr
		 WHERE $filter LIMIT 1");

$locked = locked('');

$badCreds = false;
if(($role = userRole()) != 'o') {
	if(!($confirmation['respondenttable'] == 'tblclient' && $role == 'c' && $confirmation['respondentptr'] == $_SESSION["clientid"])
			&& !($confirmation['respondenttable'] == 'tblprovider' && $role == 'p' && $confirmation['respondentptr'] == $_SESSION["providerid"]) 
		) {
			
			$badCreds = true;
		}
}

if(!$badCreds && $_POST) {
	$action = $_POST['action'];
	if($action == 'respond') {
		$request = $_POST;
		$request['resolved'] = 0;
		$request['subject'] = 'Schedule changes declined';
		$request['requesttype'] = 'NotificationResponse';
		$request['clientptr'] = $_SESSION["clientid"];
		$declineMsgPtr = saveNewClientRequest($request);
		if($_REQUEST['token']) declineWithToken($_REQUEST['token'], $declineMsgPtr, 'notify');
		else decline($_REQUEST['confid'], $declineMsgPtr, 'notify');
	}
	else if($action == 'confirm') {
		if($_REQUEST['token']) confirmWithToken($_REQUEST['token'], null, 'notify');
		else confirm($_REQUEST['confid'], null, 'notify');
	}
}

$frame = $role == 'c' ? 'frame-client.html' : 'frame.html';

include $frame;

if($badCreds) echo "Insufficient rights to execute this confirmation";
else if($action) {
	if($action == 'confirm') echo "Thanks for confirming!<p>";
	if($action == 'respond') echo "Thanks for your response!  We'll get back to you shortly.<p>";
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
	hiddenElement('email', $confirmation['email']);
	hiddenElement('action', '');
	$details = getOneClientsDetails($_SESSION["clientid"], array('phone'));
	labelRow('Email', '', $confirmation['email']);
	inputRow('Phone', 'phone', '');
	inputRow('Best time for us to call', 'whentocall', '', '', 'emailInput');
	labelRow('How can we help you?', '');
	echo "<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=50></textarea></td></tr>";
	echo "<tr><td colspan=2 align=center>".echoButton('', 'Decline and Send Comment', "submitChanges(\"respond\")", null,null, 1)."</td></tr>";
?>
</table>
</form>
</center><script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('note','Note');
function submitChanges(action) {
	document.getElementById('action').value = action;
  if(action == 'respond' && !MM_validateForm('note', '', 'R')) return;
  //url = "comm-view.php?id=<?= $confirmation['msgid'] ?>";
  url = "<?= globalURL('comm-view.php?id='.$confirmation['msgid']) ?>";
	document.getElementById('note').value = 
		"RE: <a href='"+url+"'><?= $confirmation['subject'] ?></a><p>"
		+document.getElementById('note').value;
  
	document.clientrequestform.submit();
}
</script>
<?	
}
include "frame-end.html";
