<?
// home-safe-options-lightbox.php
require_once "prov-schedule-fns.php";
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

if($_POST['savethisform']) {
	extract($_POST);
	setPreference('homeSafeDoNotResolveRequest', ($homeSafeDoNotResolveRequest ? 1 : 0));
	setPreference('homeSafeNotifySitters', ($homeSafeNotifySitters == 1 ? 1 : 0));
	setPreference('homeSafeTextToSitters', ($homeSafeTextToSitters == 1 ? 1 : 0));
	setPreference('homeSafeSuppressed', ($homeSafeSuppressed == 1 ? 1 : 0));
	echo "<script language='javascript'>parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";
?>

<h2>Home Safe Options</h2>
<form method='POST' name='homesafeform'>
<?
hiddenElement('savethisform', 1);
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
<table>
<?
$props = fetchPreferences();
$options = array('yes'=>'1', 'no'=>0);
$fields = "homeSafeSuppressed|Do NOT Offer  the Home Safe Request Feature
homeSafeDoNotResolveRequest|Leave END schedule requests unresolved when Home Safe is received
homeSafeNotifySitters|Notify sitters when a client registers Home Safe
homeSafeTextToSitters|Text message sitters when a client registers Home Safe";
$fields = explodePairPerLine($fields, $sepr='|');
require_once "sms-fns.php";
if(!smsEnabled('fromLeashTimeAccount'))
	unset($fields['homeSafeTextToSitters']);
foreach($fields as $fld => $label) {
	echo "<tr><td id = '$fld' style='padding-top:20px;' colspan=2>$label:</td></tr>";
	$onClick = $fld == 'homeSafeNotifySitters' ? 'homeSafeNotifySittersClicked()' : null;
	radioButtonRow('', $fld, $props[$fld], $options, $onClick, $labelClass=null, $inputClass=null);
}
?>
</table>
</form>
<script language='javascript'>
var hidepayEl = document.getElementById('provsched_hidepay');

function homeSafeNotifySittersClicked() {
	var el = document.getElementById('homeSafeNotifySitters_0');
	var disabled = el.checked;
	document.getElementById('homeSafeTextToSitters').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('homeSafeTextToSitters_0').disabled = disabled;
	document.getElementById('homeSafeTextToSitters_1').disabled = disabled;
}

function save() {
	document.homesafeform.submit();
}

homeSafeNotifySittersClicked(null);

</script>