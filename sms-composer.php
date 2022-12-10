<? // sms-composer.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "sms-fns.php";

locked('o-');

extract(extractVars('client,provider,user,msg,num,action', $_REQUEST));

if($client) {
	$correspondent = getClient($client);
	$label = 'Client';
}
else if($provider) {
	$correspondent = getProvider($provider);
	$label = 'Sitter';
}
else if($user) {
	$correspondent = getUserByID($user);
	// make sure user belongs to this database
	if($correspondent['bizptr'] != $_SESSION['bizptr']) {
		echo "Bad user(db) [{$correspondent['bizptr']}]";
		exit;
	$label = 'User';
	}
}

$num = $num ? $num : findSMSPhoneNumberFor($correspondent);

if($action == 'send') {
	notifyByLeashTimeSMS($correspondent, trim($msg));
	$msg = '';
}

require "frame-bannerless.php";

?>
Text message to <?= $correspondent['fname'].' '.$correspondent['lname'].": $num" ?><p>
<form method='POST' name='smsform'>
<?
echoButton('send', 'Send', $onClick='checkAndSubmit()', $class='', $downClass='', $noEcho=false, $title=null);
hiddenElement('action', '');
if(smsEnabled($fromLeashTimeAccount=true)) echo " Max: 140 characters";
else echo " Text messaging is turned off.";
?>
<br>
<textarea rows=3 cols=60 id='msg' name='msg' maxlength=160><?= $msg ?></textarea>
</form>
<script language='javascript'>
<? if(!smsEnabled($fromLeashTimeAccount=true)) { ?>
	document.getElementById('send').disabled = true;
	document.getElementById('send').style.display = 'none';
	document.getElementById('msg').style.display = 'none';
<? } ?>
function checkAndSubmit() {
	if(document.getElementById('msg').value.trim().length == 0) {
		alert('Please type a message first');
		return;
	}
	document.getElementById('send').disabled = true;
	document.getElementById('action').value= 'send';
	document.smsform.submit();
}
</script>
