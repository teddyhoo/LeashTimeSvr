<?
// sms-options-lightbox.php
require_once "sms-fns.php";
/* args:
provui: if 1, controls display in provider UI
*/


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
//require_once "field-utils.php";
//require_once "provider-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox
//smsTurnedOn,smsGateway
if($_POST['smsprefs']) {
//echo "bang!";exit;
	setPreference('smsTurnedOn', ($_POST['smsTurnedOn'] ? 1 : 0));
	if($_POST['smsTurnedOn']) {
		setPreference('smsGateway', ($_POST['smsGateway'] ? $_POST['smsGateway'] : null));
		setPreference('smsGatewayAccountId', ($_POST['smsGatewayAccountId'] ? $_POST['smsGatewayAccountId'] : null));
		if($_POST['smsGatewayAccountToken'] == 'clear') $_POST['smsGatewayAccountToken'] = null;
		else if($_POST['smsGatewayAccountToken']) {
			require_once "encryption.php";
			setPreference('smsGatewayAccountToken', ($_POST['smsGatewayAccountToken'] ? lt_encrypt($_POST['smsGatewayAccountToken']) : null));
		}
	}
	$visitsStaleAfterMinutes = (int)$_POST['visitsStaleAfterMinutes'] == $_POST['visitsStaleAfterMinutes'] ? $_POST['visitsStaleAfterMinutes'] : null;
	$summary = $_POST['smsTurnedOn'] ? 
								$_POST['smsGateway'].' '.'Account ID set '//.$_POST['smsGatewayAccountId']
								.' (Password / token '.(getPreference('smsGatewayAccountToken') ? '' : 'not ').'set)'
						: 'Turned off';
	setPreference('smsOptions',$summary);
	
	echo "<script language='javascript'>parent.updateProperty('staleVisitNotificationOptions', '$summary');parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";
?>

<h2>Text Messaging Preferences</h2>
<form method='POST' name='staleprops'>
<?
hiddenElement('smsprefs', 1);
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
<table>
<?
$props = fetchPreferences();
$options = array('yes'=>1, 'no'=>0);
echo "<tr><td colspan=2>Allow use of Text Messaging:</td></tr>";
radioButtonRow('', 'smsTurnedOn', $props['smsTurnedOn'], $options, 'smsTurnedOnChanged()', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);

$options = array("Twilio"=>"Twilio");
$title = 'Text Message Service to be used';
echo "<tr><td id='smsGatewayLABEL' style='padding-top:20px;' colspan=2 title='$title'>Text Message service</td></tr>";
selectRow('', 'smsGateway', $props['smsGateway'], $options, $onChange=null, $labelClass=null, $inputClass=null);

echo "<tr><td id='smsGatewayAccountIdLABEL' style='padding-top:20px;' colspan=2 title='$title'>Text Message Gateway Account ID</td></tr>";
inputRow('', 'smsGatewayAccountId', $props['smsGatewayAccountId'], $labelClass=null, $inputClass='VeryLongInput');
	
echo "<tr><td id='smsGatewayAccountTokenLABEL' style='padding-top:20px;' colspan=2 title='$title'>Text Message Gateway Account Password</td></tr>";
passwordRow('', 'smsGatewayAccountToken', null, $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null);
echo "<tr><td id='smsGatewayAccountTokenHintLABEL' class='tiplooks' colspan=2 title='$title'>"
			.($props['smsGatewayAccountToken'] ? 'Password is set.  Enter "clear" to clear the password.' :  '<span style="color:red">Password is not set.</span>')."</td></tr>";
	
?>
</table>
</form>
<script language='javascript'>

function smsTurnedOnChanged() {
	var disabled = document.getElementById('smsTurnedOn_0').checked;
	document.getElementById('smsGatewayLABEL').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('smsGateway').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('smsGatewayAccountIdLABEL').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('smsGatewayAccountId').disabled = disabled;
	document.getElementById('smsGatewayAccountTokenLABEL').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('smsGatewayAccountToken').disabled = disabled;
}

function save() {
	document.staleprops.submit();
}

smsTurnedOnChanged();
</script>