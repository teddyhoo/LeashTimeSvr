<? // comm-logger.php
/*
client or provider - id of correspondent
... or user (manager)
log - email or phone
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('o-');//locked('o-'); 

extract($_REQUEST);

if($_POST) {
	logMessage($_POST);
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
}

//$client or $provider will be set, containing the recipient's id
if(isset($client)) {
	require_once "client-fns.php";
	$correspondent = getClient($client);
	$corresTable = 'tblclient';
	$corresType = 'Client';
	$correspId = $client;
}
else if(isset($provider)) {
	require_once "provider-fns.php";
	$correspondent = getProvider($provider);
	$corresTable = 'tblprovider';
	$corresType = 'Sitter';
	$correspId = $provider;
}
else if(isset($user)) {
	$mgrs = getManagers(array($user), $ltStaffAlso=false);
	$mgr = $mgrs[$user];
	$mgr['name'] = "{$mgr['fname']} {$mgr['lname']}";
	$correspondent = $mgr;
	$corresTable = 'tbluser';
	$corresType = 'Manager';
	$correspId = $user;
}



$corrName = $correspondent['fname'].' '.$correspondent['lname'];
if($log == 'phone') {
	$corrAddr = primaryPhoneNumber($correspondent);
//if(mattOnlyTEST()) echo print_r(	$corrAddr, 1);
	$logType = 'Phone Call';
	$addrType = 'Phone';
	$addrObject = 'Phone Number';
}
else if($log == 'email') {
	$corrAddr = $correspondent['email'];
	$logType = 'Email Message';
	$addrType = 'Email';
	$addrObject = 'Email Address';
}
else if($log == 'mail') {
	$corrAddr = '';
	$logType = 'Letter';
	$addrType = 'Mail';
	$addrObject = 'Postal Address';
}
else if($log == 'text') {
	$corrAddr = primaryPhoneNumber($correspondent);
	$logType = 'Mobile Communication';
	$addrType = 'Phone Number';
	$addrObject = 'Phone Number';
}

$error = null;


$pageTitle = "$logType: $corrName";

//include "frame-client.html";
// ***************************************************************************

if($error) echo "<font color='red'>$error</font>";
if($message) {
	echo $message;
	include "frame-end.html";
	exit;
}
$windowTitle = 'Log Message';
$extraBodyStyle = 'padding:10px;';
require "frame-bannerless.php";

echo "<h2>$pageTitle</h2>";

?>
<form method='POST' name='commcomposerform'>
<table>
<?
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
radioButtonRow("$corresType Message: ", 'inbound', 1, array("from $corrName"=>1, "to $corrName"=>0));
inputRow('Manager:', 'mgrname', getUsersFromName());
hiddenElement('transcribed', $log);
hiddenElement('correspid', $correspId);
hiddenElement('correstable', $corresTable);
inputRow("$addrType:", 'correspaddr', $corrAddr, null, 'emailInput');
inputRow('Subject:', 'subject', '', null, 'emailInput');
textRow("$logType Message or Notes:", 'msgbody', '', $rows=20, $cols=80);
?>
</table>
<?
echoButton('', "Log $logType", 'checkAndSend()');
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('mgrname',"From (Manager's name)",'correspaddr',"<?= $corresType ?>'s <?= $addrObject ?>",'subject','Subject line','msgbody','Message');
function checkAndSend() {
	if(MM_validateForm('mgrname', '', 'R',
											'correspaddr','', 'R',
											<?= $log == 'email' ? "'correspaddr','', 'isEmail',\n" : "" ?>
											'subject','', 'R',
											'msgbody','', 'R'
											))
  		document.commcomposerform.submit();
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}


</script>
<?
// ***************************************************************************
//include "frame-end.html";
?>
