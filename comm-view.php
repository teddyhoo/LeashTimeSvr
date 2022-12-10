<? // comm-view.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "sms-fns.php";


if(userRole() == 'o') $locked = locked('o-');//locked('o-'); 
else if(userRole() == 'c') $locked = locked('c-');//locked('o-'); 

if($_REQUEST['queued']) {
	$message = fetchFirstAssoc("SELECT * FROM tblqueuedemail WHERE emailid = {$_REQUEST['id']} LIMIT 1", 1);
	$msgfields = $message['tblmsgfields'] ? explodePairsLine($message['tblmsgfields']) : array();
	$message['from'] = $msgfields['mgrname'];
	$message['corresp'] = '--';
	$message['correspaddr'] = $message['recipients'];
	$message['datetime'] = $message['addedtime'];
	$message['mgrname'] = $msgfields['mgrname'];
	if($msgfields['correspid']) {
		$message['correspid'] = $msgfields['correspid'];
		$message['correstable'] = 'tbl'.($type = substr($msgfields['correstable'], 3));
	}
}
else {
	$message = getMessage($msgid = $_REQUEST['id']);
	if(!$message) {
		$message = getArchivedMessage($msgid);
		$archived = 1;
	}
	$metadata = metaDataForMessage($msgid);
}


if(userRole() == 'c') {
	if($message['correspid'] != $_SESSION["clientid"] || $message['correstable'] != 'tblclient') {
		echo 'Insufficient Access Rights.';
		exit;
	}
}
else if(in_array(userRole(), array('o', 'd')) && $msgid && $_REQUEST['toggle']) {
	$test = $_SESSION["commviewer_$msgid"];
	$_SESSION["commviewer_$msgid"] = null;
	unset($_SESSION["commviewer_$msgid"]);
	if($test == $_REQUEST['toggle']) { // avoid doubleclick
		updateTable('tblmessage', array('hidefromcorresp'=>sqlVal('if(hidefromcorresp = 1, 0, 1)')), "msgid = $msgid", 1);
		$message = getMessage($msgid);
	}
}

$corresType = $message['correstable'] == 'tblclient' ? 'Client' : (
							$message['correstable'] == 'tblprovider' ? 'Sitter' : (
							$message['correstable'] == 'tblclientrequest' ? 'Prospect' : (
							$message['correstable'] == 'tbluser' ? 'Manager' : $message['correstable'])));

//$client or $provider will be set, containing the recipient's id
if($corresType == 'Client') {
	require_once "client-fns.php";
	$correspondent = getClient($message['correspid']);
}
else if($corresType == 'Sitter') {
	require_once "provider-fns.php";
	$correspondent = getProvider($message['correspid']);
}
else if($corresType == 'Prospect') {
	require_once "request-fns.php";
	$correspondent = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$message['correspid']} LIMIT 1", 1);
}
else if($corresType == 'Manager') {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$correspondent = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = {$message['correspid']} AND bizptr = '{$_SESSION['bizptr']}' LIMIT 1", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
}
$originator = $message['originatorid'] ? getProvider($message['originatorid']) : '';
$corrName = $correspondent['fname'].' '.$correspondent['lname'];
$transcribed = $message['transcribed'];
$corrAddr = $message['correspaddr'];


if($metadata) $msgType = 'SMS';
else $msgType = $transcribed == 'phone' ? 'Phone' : 
					(!$transcribed || $transcribed == 'email' ? 'Email' : 
					/* $transcribed == 'mail'*/ 'Mail');

$error = null;


$pageTitle = ($msgType == 'SMS' ? 'SMS (Text Message)' :
		($transcribed == 'phone' ? 'Logged Phone Call' : 
		($transcribed == 'email' ? 'Logged Email Message' : 
		($transcribed == 'mail' ? 'Mail' : 
		($_REQUEST['queued'] ? 'Unsent ' : '').'Email')))).
		($message['inbound'] ? ' from ' : ' to ').
		$corrName;

//include "frame-client.html";
// ***************************************************************************

if($error) echo "<font color='red'>$error</font>";

$windowTitle = 'View Log';
$extraBodyStyle = 'padding:10px;';
if($_SESSION["responsiveClient"]) {
	$extraBodyStyle .=  !$_SESSION["deskTopUser"] ? "font-size:1.5em !important;" : '';
}
require "frame-bannerless.php";
?>
<div onclick='window.close()' title='Close this.' style='color:#808080;cursor:pointer;position:absolute;right:3px;top:0px;font-size:3.3em;font-weight:bold;'>&#10005;<!-- &#9746 --></div> 	
<?
echo "<h2>$pageTitle</h2>";
if($originator) {
	echoButton('', 'Reply', "reply($msgid)");
	echo " ";
	echoButton('', 'Reply to All', "reply($msgid, 1)");
	echo "<p>";
}
/*$_SESSION['preferences']['enableCommunicationForwarding'] && */
if($correspondent && in_array(userRole(), array('o', 'd'))) {
	$corrRole = $correspondent['clientid'] ? 'client' : (
							$correspondent['providerid'] ? 'provider' : (
							$correspondent['userid'] ? 'user' : ''));
	echoButton('', 'Forward', "document.location.href=\"comm-composer.php?$corrRole={$correspondent[$corrRole.'id']}&forwardid={$_REQUEST['id']}\"");
}
if(staffOnlyTEST() 
			&& $_REQUEST['id'] 
			//&& $_REQUEST['statement'] 
			&& !$message['inbound']
			&& !$message['transcribed']
			&& in_array(userRole(), array('o', 'd'))) {
	$_SESSION["commviewer_$msgid"] = time();
	echo "<p>";
	labeledCheckbox("Hide from $corresType", 'unused', $message['hidefromcorresp'], $labelClass=null, $inputClass=null, 
									$onClick="document.location.href=\"comm-view.php?id=$msgid&statement=1&toggle={$_SESSION['commviewer_'.$msgid]}\"", 
									$boxFirst=false, $noEcho=false, "Hide this message from the $corresType");
}
?>
<table border=0 style='padding-left:0px;'>
<?
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
if($metadata && !in_array($metadata['status'], array('delivered', 'received')))
	echo "<tr><td colspan=2><span class='warning fontSize1_2em' title='Status: {$metadata['status']} from: {$metadata['fromphone']} to: {$metadata['tophone']}'>THIS MESSAGE WAS NOT DELIVERED.</span></td></tr>";

if(!$message['inbound']) {
	if($originator) labelRow("From Sitter:", '', "{$originator['fname']} {$originator['lname']} <{$originator['email']}>");
	else labelRow('From:', 'mgrname', $message['mgrname']);
}
labelRow(($message['inbound'] ? 'From' : 'To')." $corresType:", '', $corrName);
$dateLabel = $transcribed ? 'Logged on:' : ($_REQUEST['queued'] ? 'Added to queue:' : 'Date:');
if(mattOnlyTEST() && $archived) $dateLabel .= ' (archived)';
labelRow($dateLabel, '', date('D F j, Y g:i a', strtotime($message['datetime'])));
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)

if(strpos($message['correspaddr'], '|')) {
		$adds = array();
		$parts = explode('|', $message['correspaddr']);
		foreach($parts as $labelList) {
			$labelList = explode(':', $labelList);
			if(trim($labelList[1])) 
				labelRow("{$labelList[0]}:", '', $labelList[1]);

		}
}
else if($msgType == 'Mail') labelRow("Printed:", 'correspaddr', "This message was printed.");
else if($msgType == 'SMS') labelRow("Phone:", 'correspaddr', $message['correspaddr']);
else labelRow("$msgType:", 'correspaddr', $message['correspaddr']);


labelRow('Subject:', 'subject', $message['subject'], null, 'emailInput');
//textDisplayRow('Message:', 'msgbody', $message['body']);
labelRow('Message:', '', '<img src="art/spacer.gif" width=400 height=1>', '','','','','raw');
?>
</table>
<div style='padding-left:10px;padding-top:10px;'>
<?
$displayText = $message['body'];
if(noTablesOrLineBreaks($displayText)) {
	$displayText = str_replace("\n\n", '<p>', $message['body']);
	$displayText = str_replace("\n", '<br>', $displayText);
}
echo $displayText;

if($metadata && staffOnlyTEST()) {
	echo "<span style='color:blue;'><p>SMS:<br>message length: ".strlen($message['body'])."<br>num segments: {$metadata['numsegments']}<br>from: {$metadata['fromphone']}<br>to: {$metadata['tophone']}</span>";
}
if($_REQUEST['section']) {
?>
</div>
<script language='javascript'>

var el = document.getElementById('section_'+<?= $_REQUEST['section']; ?>);
if(el) el.style.backgroundColor = '#FFFF40'; //#FFFF40 =  yellow '#FFC3C3' = pink
</script>
<?
}
?>
<script language='javascript'>

function reply(msgid, toall) {
	document.location.href='comm-composer.php?replyto='+msgid+'&all='+toall;
}
</script>
<?
// ***************************************************************************
//include "frame-end.html";
