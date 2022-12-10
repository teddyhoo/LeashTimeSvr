<?  // solveras-transfirst-conversion.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
require_once "cc-processing-fns.php";

locked('o-');
// check to see if Solveras is the current gateway
if($_SESSION['preferences']['ccGateway'] != 'Solveras')
	$error = 'Gateway is NOT Solveras!';
	
if(!$error && $_POST['go']) {
	setPreference('savedSolveras_x_tran_key', fetchPreference('x_tran_key'));
	setPreference('x_login', lt_encrypt($_POST['gatewayid']));
	setPreference('x_aux_key', lt_encrypt($_POST['regkey']));
	setPreference('ccGateway', 'TransFirstV1');
	/*
	*/
	//globalRedirect('preference-list.php?show=7');
	//exit;
	$_SESSION['frame_message'] = $message = "Transition made. <a href='preference-list.php?show=7'>Go to Preferences</a>";
}
$pageTitle = "Switch from Solveras to TransFirstV1";
$breadcrumbs = "<a href='preference-list.php' title='Preferences'>Preferences</a> - ";
include "frame.html";
if($error) echo "<span class='warning'>This Business does NOT use Solveras! (uses {$_SESSION['preferences']['ccGateway']})</span><p>";
else if(!$message) {
	
?>
Locate the following information in the email message with subject <b>Merchant Activation Alert</b>.
<p>
Please ensure that you are looking at the correct business by logging in to TransFirst <br>(see the message <i>Merchant Name: ..., Merchant ID: ..., Gateway ID: ...)</i>.
<div style='width:350px;border:solid black 1px;padding:7px;'>
This business is: <?= "<b>{$_SESSION['preferences']['bizName']}</b>" ?><br>
Biz Phone: <?= "<b>{$_SESSION['preferences']['bizPhone']}</b>" ?><br>
Email: <?= "<b>{$_SESSION['preferences']['bizEmail']}</b>" ?><br>
Address: <?= "<b>{$_SESSION['preferences']['bizAddress']}</b>" ?><br>
Home Page: <?= "<b>{$_SESSION['preferences']['bizHomePage']}</b>" ?><br>
</div><p>
<form method='POST'>
<table><tr>
	<td>
		<table>
		<tr><td>Gateway ID: <td><input id='gatewayid' name='gatewayid' size=40><p>
		<tr><td>RegKey:: <td><input id='regkey' name='regkey' size=40><p>
		</table>
	<td>Copy body of <b>Merchant Activation Alert</b> message and paste it here:<br>
	<textarea id='source' rows=3 cols=40 onclick='this.select()' oninput='extractCreds()'>Paste message here to extract credentials</textarea>
</table>
<input type='submit' value='Go!'>
<input type='hidden' name='go' value='1'>
</form>

<script language='javascript'>
function extractCreds() {
	var lines = document.getElementById('source').value.split("\n");
	var found = {gatewayid:false, regkey:false};
	for(var i=0;i<lines.length;i++) {
//if(!confirm(lines[i])) return;		
		if(lines[i].indexOf('Gateway ID: ') == 0) {
			document.getElementById('gatewayid').value = lines[i].substring('Gateway ID: '.length).trim();
			found.gatewayid = true;
		}
		if(lines[i].indexOf('RegKey: ') == 0) {
			document.getElementById('regkey').value = lines[i].substring('RegKey: '.length).trim();
			found.regkey = true;
		}
	}
	var error = [];
	if(!found.gatewayid) error[error.length] = 'Gateway ID not found';
	if(!found.regkey) error[error.length] = 'RegKey not found';
	if(error.length > 0) alert(error.join(' and '));
}
</script>
<?
}
include "frame-end.html";

