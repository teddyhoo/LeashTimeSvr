<? // lt-issue-editor.php

// lightbox

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "request-fns.php";

locked('o');
if(!staffOnlyTEST()) {
	echo "Insufficient rights.";
	exit;
}

if(!dbTEST('leashtimecustomers')) {
	echo "Log In to LeashTime Customers First.<p>Then <a href='javascript:window.location.href=window.location.href'>Refresh</a>";
	exit;
}

if($_POST) {
	$owner = trim($_POST['owner']) ? trim($_POST['owner']) : '';
	$officeNote[] = "Opened by: {$_SESSION["auth_username"]}";
	if($owner && $owner != $_SESSION["auth_username"])
		if($owner) $officeNote[] = "Assigned to: {$owner}";
	$officeNote = join('<br>', $officeNote);
	
	$request['requesttype'] = $_POST['type'] == 'bug' ? 'BugReport' : 'Comment';
	$request['note'] = $_POST['issue'];
	$request['clientptr'] = $_POST['clientptr'];
	$names = fetchFirstAssoc("SELECT lname, fname FROM tblclient WHERE clientid = {$_POST['clientptr']} LIMIT 1", 1);
	foreach($names as $k =>$v) $request[$k] = $v;
	$request['officenotes'] = $officeNote;
	
	$subject = "Issue: {$_POST['subject']}";
	$extrafields = "<extrafields>";
	$extrafields .= "<extra key='x-label-Subject'><![CDATA[$subject]]></extra>";
	$extrafields .= "</extrafields>";
	$request['extrafields'] = $extrafields;

	saveNewBugReportCommentRequest($request, $notify=true);
	echo "<script language='javascript'>parent.location.href='index.php'</script>";
}
include "frame-bannerless.php";
?>
<h2>New Issue</h2>
<form name='issueform' method='post'>
<table width="90%">
<?
//hiddenElement('');
echoButton('', 'Submit', 'checkAndSubmit()', $class='', $downClass='', $noEcho=false, $title=null);
echo "<p>";
$options = array('Bug Report'=>'bug', 'Question / Comment'=>'comment');
echo "<tr><td colspan=2>Is this a Bug Report or a Comment/Question?</td><tr>";
radioButtonRow('', 'type', 'comment', $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);

$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) as name, lname, fname FROM tblclient  ORDER BY fname, lname");
$dups = array();
foreach($clientNames as $clientid => $name) {
	if($dups[$name]) $clientNames[$clientid] .= " (".(1+$dups[$name]).")";
	$dups[$name] = 1 + $dups[$name];
}
$options = array_merge(array('-- Select a Client --' => ''), array_flip($clientNames));
selectElement('Client:', 'clientptr', 0, $options);
inputRow('Assigned to:', 'owner', $_SESSION["auth_username"], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
inputRow('Subject:', 'subject', '', $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
textRow('Issue: ', 'issue', $value=null, $rows=20, $cols=80, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
?>
</table>
</form>
<script language='javascript' src='check-form.js'></script>

<script language='javascript'>
setPrettynames('clientptr', 'Client');
function checkAndSubmit() {
	if(MM_validateForm(
		'clientptr', '', 'R',
		'issue', '', 'R'
		)) {
		document.issueform.submit();
	}
}
</script>

