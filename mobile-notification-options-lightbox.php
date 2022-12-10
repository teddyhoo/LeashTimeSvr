<?
// mobile-notification-options-lightbox.php
require_once "prov-schedule-fns.php";
/* args:
*/


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
//require_once "field-utils.php";
//require_once "provider-fns.php";
require_once "preference-fns.php";
require_once "sms-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

if($_POST['mobilenotificationpprefs']) {
	extract($_POST);
	setPreference('leashTimeDisabledSMS', ($leashTimeDisabledSMS ? 1 : 0));
	setPreference('smsTurnedOn', ($smsTurnedOn ? 1 : 0));
	if($smsTurnedOn) {// leave subsidiary settings alone if NOT $smsTurnedOn
		enableSMS();
		setPreference('overdueVisitManagerSMSExcludeAddress', ($overdueVisitManagerSMSExcludeAddress ? 1 : 0));
		setPreference('enableOverdueVisitManagerSMS', ($enableOverdueVisitManagerSMS ? 1 : 0));
		setPreference('enableOverdueVisitSitterSMS', ($enableOverdueVisitSitterSMS ? 1 : 0));
		setPreference('overdueVisitSitterSMSExcludeAddress', ($overdueVisitSitterSMSExcludeAddress ? 1 : 0));
		setPreference('enableSitterMemoSMS', ($enableSitterMemoSMS ? 1 : 0));
		setPreference('resumeSMSDate', null);
	}
	else setPreference('resumeSMSDate', ($resumeSMS ? date('Y-m-d', strtotime('first day of next month')) : null));
	echo "<script language='javascript'>parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";

function hr() {
	echo "<tr><td style='padding-top:10px;border-bottom:solid gray 1px;' colspan=2></td></tr>";
}

function sectionRow($id, $label) {
	echo "<tr><td id='$id' style='padding-top:20px;' colspan=2>$label</td></tr>";
}
?>

<h2>LeashTime Mobile Notification Preferences</h2>
<form method='POST' name='msaprops'>
<?
hiddenElement('mobilenotificationpprefs', 1);
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
<table>
<?
$props = fetchPreferences();

$yesNoOptions = array('yes'=>'1', 'no'=>0);
echo "<tr style='background:pink'><td id='leashTimeDisabledSMS' style='color:red;padding:3px;' colspan=2>LeashTime STAFF Only: Disable SMS:</td></tr>";
radioButtonRow('', 'leashTimeDisabledSMS', $props['leashTimeDisabledSMS'], $yesNoOptions, $onClick='mainSwitchThrown()', $labelClass=null, $inputClass=null, $rowId=null, $rowStyle='background:pink;color:red');

hr();
sectionRow('smsTurnedOn', 'Enable the sending of SMS (text) messages:');
//echo "<tr><td id='smsTurnedOn' style='padding-top:20px;' colspan=2>Enable the sending and receiving of SMS (text) messages:</td></tr>";
radioButtonRow('', 'smsTurnedOn', $props['smsTurnedOn'], $yesNoOptions, $onClick='mainSwitchThrown()', $labelClass=null, $inputClass=null);

echo "<tr id='resumeSMSRow'><td id='resumeSMS' style='padding-top:7px;' colspan=2>Turn SMS Messaging back on next month:</td></tr>";
$resumeSMS = $props['resumeSMSDate'] ? 1 : 0;
radioButtonRow('', 'resumeSMS', $resumeSMS, $yesNoOptions, $onClick=null, $labelClass=null, $inputClass=null, $rowId='resumeSMSRadioRow');

hr();
sectionRow('enableOverdueVisitManagerSMS', 'Send overdue visit notices by SMS to managers:');
radioButtonRow('', 'enableOverdueVisitManagerSMS', $props['enableOverdueVisitManagerSMS'], $yesNoOptions, $onClick=null, $labelClass=null, $inputClass=null);

$options = array('yes'=>'1', 'no'=>0);
echo "<tr><td id='overdueVisitManagerSMSExcludeAddress' style='padding-top:10px;' colspan=2>- Exclude client address:</td></tr>";
radioButtonRow('', 'overdueVisitManagerSMSExcludeAddress', $props['overdueVisitManagerSMSExcludeAddress'], $options, $onClick=null, $labelClass=null, $inputClass=null);

hr();
sectionRow('enableOverdueVisitSitterSMS', 'Send overdue visit notices by SMS to sitters:');
radioButtonRow('', 'enableOverdueVisitSitterSMS', $props['enableOverdueVisitSitterSMS'], $yesNoOptions, $onClick=null, $labelClass=null, $inputClass=null);

echo "<tr><td id='overdueVisitSitterSMSExcludeAddress' style='padding-top:7px;' colspan=2>- Exclude client address:</td></tr>";
radioButtonRow('', 'overdueVisitSitterSMSExcludeAddress', $props['overdueVisitSitterSMSExcludeAddress'], $yesNoOptions, $onClick=null, $labelClass=null, $inputClass=null);

hr();
sectionRow('enableSitterMemoSMS', 'Send visit/schedule change notices by SMS to sitters:');
radioButtonRow('', 'enableSitterMemoSMS', $props['enableSitterMemoSMS'], $yesNoOptions, $onClick=null, $labelClass=null, $inputClass=null);

?>
</table>
</form>
<script language='javascript'>
var hidepayEl = document.getElementById('provsched_hidepay');

function mainSwitchThrown() {
	var disabled = document.getElementById('smsTurnedOn_0').checked;
	var ids = ("enableOverdueVisitManagerSMS,enableOverdueVisitSitterSMS,enableSitterMemoSMS,"+
						"overdueVisitManagerSMSExcludeAddress,overdueVisitSitterSMSExcludeAddress").split(',');
	for(var i=0; i < ids.length; i++) {
		document.getElementById(ids[i]).style.color = (disabled ? 'gray' : 'black');
		document.getElementById(ids[i]+'_0').disabled = disabled;
		document.getElementById(ids[i]+'_1').disabled = disabled;
	}
	var table_row = '<?= $_SESSION['tableRowDisplayMode'] ?>'; // handles Internet Explorer.  Set in login-fns.php
	//alert('disabled: '+disabled+" "+dis);
	document.getElementById('resumeSMSRow').style.display = (disabled ? table_row : 'none');
	document.getElementById('resumeSMSRadioRow').style.display = (disabled ? table_row : 'none');
	document.getElementById('resumeSMS_0').style.disabled = !disabled;
	document.getElementById('resumeSMS_1').style.disabled = !disabled;
}

function save() {
	document.msaprops.submit();
}

mainSwitchThrown();
</script>