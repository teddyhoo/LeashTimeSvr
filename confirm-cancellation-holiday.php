<? // confirm-cancellation-holiday.php
// ?cancel=0&token=&confid=255mavjm

// called from response.php when the redirecturl points here
// token - token associated with confirmation
// logout - end session when non-null

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "confirmation-fns.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "request-fns.php";
require_once "service-fns.php";

$filter = $_REQUEST['confid'] ? "confid = '{$_REQUEST['confid']}'" : "token = '{$_REQUEST['token']}'";
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
		)
			$badCreds = true;
}

$ids = $_REQUEST['ids'] ? $_REQUEST['ids'] : $_REQUEST['cancel'];

if($_POST) $action = $_POST['action'];
else $action = $_REQUEST['cancel'] ? 'cancel' : ''; //'retain'

if(!$badCreds) {
	
	if($_POST && $action == 'retain') {
		$request = $_POST;
		$request['resolved'] = 0;
		$request['subject'] = "Holiday Cancellation Decline";
		if($_REQUEST['confid']) {
			$includeMessage = includeOriginalMessageInConfirmationNote();
			if($includeMessage) {
				$status = confirmationStatus(null, $includeDetail=true, $filter, $includeMessage);
				if((trim($status['subject']) || trim($status['body']))) {
					$origMessage = "<hr>Original message:<p>";
					if(trim($status['subject'])) $origMessage .= "Subject: ".trim($status['subject']);
					if(trim($status['body'])) $origMessage .= "<p>".trim($status['body']);
					$request['note'] .= $origMessage;
				}
			}
		}
		//$request['note'] = $request['note'] ? $request['note'] : "Please keep the visit scheduled as is.";
		$request['requesttype'] = 'NotificationResponse';
		$request['clientptr'] = $_SESSION["clientid"];
		$declineMsgPtr = saveNewClientRequest($request);
		if($_REQUEST['confid']) decline($_REQUEST['confid'], $declineMsgPtr, 'notify');
		else declineWithToken($_REQUEST['token'], $declineMsgPtr, 'notify');
	}
	else if($action == 'cancel') {
		if($_REQUEST['confid']) confirm($_REQUEST['confid'], 'Visit cancellation(s) confirmed.', 'notify');
		else confirmWithToken($_REQUEST['token'], 'Visit cancellation(s) confirmed.', 'notify');
		require_once "appointment-fns.php";
		cancelAppointments($ids, true, $additionalMods=null, $generateMemo=true, $initiator='client confirmation');
	}
}

$frame = $role == 'c' ? 'frame-client.html' : 'frame.html';

$smallScreenIsNeeded = $role == 'c' && (isMobileUserAgent() || $_GET['mob']);

if($smallScreenFrame = $smallScreenIsNeeded) { // enabled on 4/29/2019
	$frame = "frame-bannerless.php";
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	if($headerBizLogo) echo
		"<p style='text-align:center;'><img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes><p>";

}


include $frame;
if($badCreds) echo "Insufficient rights to execute this confirmation";

else if($action) {
	$idArray = is_array($ids) ? $ids : explode(',', $ids);
	$plural = count($idArray) > 1 ? 's' :'';
	//if($action == 'cancel') echo "Thanks for confirming visit cancellation!<p>";
	if($smallScreenIsNeeded) echo "<div style='font-size:4.0em'>";
	if($action == 'cancel') echo "Thanks for taking action to cancel the visit{$plural}!<p>";
	if($action == 'retain') echo "Thanks for your response!  We'll get back to you shortly.<p>";
	echo fauxLink('Go to Home Page', 'document.location.href="index.php";');
	echo "<p>";
	echo fauxLink('Log Out', 'document.location.href="login-page.php?logout=1";');
	if($smallScreenIsNeeded) echo "</div>";
}

else if(TRUE || dbTEST('dogslife,tonkatest')) retainVisitsForm($confirmation, $cancel, $smallScreenFrame);
	
else {
	echo "<h2>Please Confirm Visit Cancellation or Send Us a Comment</h2><center>";
	echoButton('', 'Confirm Visit Cancellation', "submitChanges(\"cancel\")");
?>
<p>- or -
<form method='POST' name='clientrequestform'>
<table>
<?
	hiddenElement('email', $confirmation['email']);
	hiddenElement('action', '');
	hiddenElement('ids', $cancel);
	$details = getOneClientsDetails($_SESSION["clientid"], array('phone'));
	labelRow('Email', '', $confirmation['email']);
	inputRow('Phone', 'phone', '');
	inputRow('Best time for us to call', 'whentocall', '', '', 'emailInput');
	labelRow('How can we help you?', '');
	echo "<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=50></textarea></td></tr>";
	echo "<tr><td colspan=2 align=center>".echoButton('', 'Decline Visit Cancellation and Send Comment', "submitChanges(\"retain\")", null,null, 1)."</td></tr>";
?>
</table>
</form>
</center><script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('note','Note');
function submitChanges(action) {
	document.getElementById('action').value = action;
  //if(action == 'retain' && !MM_validateForm('note', '', 'R')) return;
  url = "comm-view.php?id=<?= $confirmation['msgid'] ?>";
  var note = ""+document.getElementById('note').value;
  if(action == 'cancel') note = "Visit(s) canceled";
  else if(note.trim().length == 0) 
  	note = "Please keep the visit(s) scheduled as is.";
	document.getElementById('note').value = 
		"RE: <a href='"+url+"'>Holiday confirmation:</a> <p>"
		+note;
 
	document.clientrequestform.submit();
}
</script>
<?	
}
if(!$smallScreenFrame) include "frame-end.html";

function retainVisitsForm($confirmation, $cancel, $smallScreenFrame) {
	$numCols = 2;
	if($smallScreenFrame) {
		$numCols = 1;
		$style = "style='font-size:4em;'";
		$h2style = "style='font-size:2em;'";
		$tableStyle = "style='width:100%'";
	}
	else $tableStyle = "style='width:60%'";
	$idArray = is_array($cancel) ? $cancel : explode(',', $cancel);
	$plural = count($idArray) > 1 ? 's' :'';
	$retainVisitsButton = echoButton('', "Keep the Scheduled Visit$plural", "submitChanges(\"retain\")", null,null, 1);
	$details = getOneClientsDetails($_SESSION["clientid"], array('phone'));
	echo <<<FORM
<center $style><h2 $h2style>Confirm Visit$plural</h2>
If you want to drop us a note, you can use the form below.  Either way, the visit$plural will stay on the schedule.<br>&nbsp;<br>
<form method='POST' name='clientrequestform'>
<table $tableStyle>
<tr><td style='padding-bottom:10px;' colspan=2 align=center>$retainVisitsButton</td></tr>
FORM;
	hiddenElement('email', $confirmation['email']);
	hiddenElement('action', '');
	hiddenElement('ids', $cancel);
	//labelRow('Your Email', '', $confirmation['email']);
	echo "<tr><td colspan=$numCols>How can we help you?</td></tr>";
	echo "<tr><td colspan=$numCols><textarea id='note' name='note' rows=4 style='width:100%'></textarea></td></tr>";
	if($smallScreenFrame) {
		inputTwoRows('Your Phone (if you would like a call)', 'phone', '', '', 'fullWidthInput');
		inputTwoRows('Best time for us to call', 'whentocall', '', '', 'fullWidthInput');
	}
	else {		
		inputRow('Your Phone (if you would like a call)', 'phone', '', '', 'fullWidthInput');
		inputRow('Best time for us to call', 'whentocall', '', '', 'fullWidthInput');
	}
	echo <<<FORMEND
</table>
</form>
<hr>or... 
FORMEND;
	echoButton('', "Just Cancel the Visit$plural", "submitChanges(\"cancel\")");
	if($smallScreenFrame)
		echo "<p><a class='fauxlink' onClick='document.location.href=\"index.php\";'   >Go to Home Page</a><p><a class='fauxlink' onClick='document.location.href=\"login-page.php?logout=1\";'   >Log Out</a>";

	echo <<<SCRIPT
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('note','Note');
function submitChanges(action) {
	document.getElementById('action').value = action;
  //if(action == 'retain' && !MM_validateForm('note', '', 'R')) return;
  url = "comm-view.php?id={$confirmation['msgid']}";
  var note = ""+document.getElementById('note').value;
  if(action == 'cancel') note = "Visit(s) canceled";
  else if(note.trim().length == 0) 
  	note = "Please keep the visit(s) scheduled as is.";
	document.getElementById('note').value = 
		"RE: <a href='"+url+"'>Holiday confirmation:</a> <p>"
		+note;
  
	document.clientrequestform.submit();
}
</script>
SCRIPT;
}
