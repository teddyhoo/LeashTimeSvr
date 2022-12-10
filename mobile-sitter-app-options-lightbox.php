<?
// mobile-sitter-app-options-lightbox.php
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

if($_POST['msapprefs']) {
	extract($_POST);
	setPreference('mobileSitterAppEnabled', ($mobileSitterAppEnabled ? 1 : 0));
	setPreference('mobileVersionPreferred', ($mobileSitterAppEnabled == 1 ? 1 : 0));
	if($mobileVersionPreferred == 1) // all sitters
		deleteTable('tbluserpref', "property = 'mobileVersionPreferred'", 1);
	else if(!$mobileSitterAppEnabled || !$mobileVersionPreferred) // no sitters
		deleteTable('tbluserpref', "property = 'webUIOnMobileDisabled'", 1);
		
	setPreference('postcardsEnabled', ($postcardsEnabled ? $postcardsEnabled : 0));
	if($postcardsEnabled == 1) // all sitters
		deleteTable('tbluserpref', "property = 'postcardsEnabled'", 1);
	else if(!$mobileSitterAppEnabled || !$postcardsEnabled) // no sitters
		deleteTable('tbluserpref', "property = 'postcardsEnabled'", 1);
	setPreference('postcardsEmailEnabled', ($postcardsEmailEnabled ? 1 : 0));
	
	setPreference('webUIOnMobileDisabled', ($webUIOnMobileAllowed ? 0 : 1));
	setPreference('mobileDetailedListVisit', ($mobileDetailedListVisit ? 1 : 0));
	setPreference('mobileOfferFindAVet', ($mobileOfferFindAVet ? 1 : 0));
	setPreference('mobileOfferArrivedButton', ($mobileOfferArrivedButton ? 1 : 0));
	setPreference('mobileSitterVisitNoteColor', ($mobileSitterVisitNoteColor ? $mobileSitterVisitNoteColor : 'black'));
	setPreference('mobile_private_zone_timeout_interval', ($mobile_private_zone_timeout_interval ? $mobile_private_zone_timeout_interval : 300));
	setPreference('mobileEmailsToClientsReplyToBusinessEmail', ($mobileEmailsToClientsReplyToBusinessEmail ? 1 : 0));
	setPreference('molbileSitterVisitListClientFormat', $molbileSitterVisitListClientFormat);
	echo "<script language='javascript'>parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";
?>

<h2>LeashTime Mobile Sitter App Preferences</h2>
<form method='POST' name='msaprops'>
<?
hiddenElement('msapprefs', 1);
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
<table>
<?
$props = fetchPreferences();
$options = array('No Sitters'=>'0', 
									'Selected Sitters Only'=>'selected', 
									'All Sitters'=>'1'); 
$choice = $props['mobileSitterAppEnabled'] ? ($props['mobileVersionPreferred'] ? 1 : 'selected') : '0';
echo "<tr><td colspan=2>Sitters who can use the LeashTime Mobile Sitter App:</td></tr>";
radioButtonRow('', 'mobileSitterAppEnabled', $choice, $options, 'mobileAllowed()', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);

$options = array('yes'=>'1', 'no'=>0);
echo "<tr><td id = 'webUIOnMobileAllowed' style='padding-top:20px;' colspan=2>Sitters may use the regular LeashTime Web Interface on Mobile Devices:</td></tr>";
radioButtonRow('', 'webUIOnMobileAllowed', !$props['webUIOnMobileDisabled'], $options, $onClick=null, $labelClass=null, $inputClass=null);

$options = array('yes'=>'1', 'no'=>0);
echo "<tr><td style='padding-top:20px;' colspan=2>Display service type and visit notes in the visit list:</td></tr>";
radioButtonRow('', 'mobileDetailedListVisit', $props['mobileDetailedListVisit'], $options, $onClick=null, $labelClass=null, $inputClass=null);

$options = array('yes'=>'1', 'no'=>0);
echo "<tr><td style='padding-top:20px;' colspan=2>Offer \"Find a Vet\" Page:</td></tr>";
radioButtonRow('', 'mobileOfferFindAVet', $props['mobileOfferFindAVet'], $options, $onClick=null, $labelClass=null, $inputClass=null);

$options = array('yes'=>'1', 'no'=>0);
echo "<tr><td style='padding-top:20px;' colspan=2>Offer \"Arrived\" button to mark sitter arrival for visit:</td></tr>";
radioButtonRow('', 'mobileOfferArrivedButton', $props['mobileOfferArrivedButton'], $options, $onClick=null, $labelClass=null, $inputClass=null);


if(dbTEST('agisdogs,tonkatest,dogslife')) {
	$options = array('Client name (Pets)'=>'fullname/pets', 
										'Client name'=>'fullname', 
										'Last name (Pets)'=>'name/pets', 
										'Pets (Last name)'=>'pets/name', 
										'Pets Only'=>'justpets'); 
	$choice = $props['molbileSitterVisitListClientFormat'] ? $props['molbileSitterVisitListClientFormat'] : 'fullname/pets';
	echo "<tr><td style='padding-top:20px;' colspan=2 title='$title'>[DEV] Client Column:</td></tr>";
	selectRow('', 'molbileSitterVisitListClientFormat', $choice, $options);
}

if(staffOnlyTEST()) {
	$options = array();
	for($i=5;$i<35;$i+=5) $options["$i minutes"] = $i*60;
	$title = 'Number of minutes of inactivity before sitter must again supply a password to see a client\'s secure information.';
	echo "<tr><td style='padding-top:20px;' colspan=2 title='$title'>[STAFF] Private Zone Timeout Interval (Recommended: 5 minutes)</td></tr>";
	$mobile_private_zone_timeout_interval = $props['mobile_private_zone_timeout_interval'] ? $props['mobile_private_zone_timeout_interval'] : 300;
	selectRow('', 'mobile_private_zone_timeout_interval', $mobile_private_zone_timeout_interval, $options, $onChange=null, $labelClass=null, $inputClass=null);
	//selectRow($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null)
	
	$colors = array();
	foreach(explode(',', 'black,red,darkred,blue,darkblue,green,darkgreen') as $label) $colors[$label] = $label;;
	echo "<tr><td style='padding-top:20px;' colspan=2 title='$title'>[STAFF] Display notes in color:</td></tr>";
	selectRow('', 'mobileSitterVisitNoteColor', $props['mobileSitterVisitNoteColor'], $colors, $onClick=null, $labelClass=null, $inputClass=null);
	
}

if(FALSE && staffOnlyTEST()) { // superseded by replyToOfficeInSitterToClientEmail // mobileEmailsToClientsReplyToBusinessEmail
	$options = array('yes'=>'1', 'no'=>0);
	$replyTo = $props['defaultReplyTo'] ? $props['defaultReplyTo'] : $props['bizEmail'];
	echo "<tr><td style='padding-top:20px;' colspan=2>Set \"Reply-to\" field in emails to clients to the business's \"Reply-To\" address ($replyTo):</td></tr>";
	radioButtonRow('', 'mobileEmailsToClientsReplyToBusinessEmail', $props['mobileEmailsToClientsReplyToBusinessEmail'], $options, $onClick=null, $labelClass=null, $inputClass=null);
}


//getUserPreference($_SESSION["auth_user_id"], 'postcardsEnabled')
//if(staffOnlyTEST() || dbTEST('tonkatest') ) { // NO LONGER USED: in_array('tblpostcard', fetchCol0("SHOW TABLES"))) {
if($_SESSION['preferences']['postcardsEnabled']) {
	$options = array('No Sitters'=>'0', 
										'Selected Sitters Only'=>'selected', 
										'All Sitters'=>'1'); 
	$choice = $props['postcardsEnabled'] ? $props['postcardsEnabled'] : '0';
	echo "<tr><td style='padding-top:20px;' colspan=2>Sitters who can send clients Visit Reports:</td></tr>";
	radioButtonRow('', 'postcardsEnabled', $choice, $options, 'postcardsAllowed()', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	
	//$options = array('yes'=>'1', 'no'=>0);
	//echo "<tr><td id = 'postcardsEmailEnabled' style='padding-top:20px;' colspan=2>[STAFF] Clients can receive postcards by email:</td></tr>";
	//radioButtonRow('', 'postcardsEmailEnabled', $props['postcardsEmailEnabled'], $options, $onClick=null, $labelClass=null, $inputClass=null);
}
//else if(staffOnlyTEST()) echo "<tr><td colspan=2 style='padding-top:7px;font-weight:bold;'>This database has no postcard table.";
?>
</table>
</form>
<script language='javascript'>
var hidepayEl = document.getElementById('provsched_hidepay');

function mobileAllowed() {
	var disabled = document.getElementById('mobileSitterAppEnabled_0').checked;
	document.getElementById('webUIOnMobileAllowed').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('webUIOnMobileAllowed_0').disabled = disabled;
	document.getElementById('webUIOnMobileAllowed_1').disabled = disabled;
}

function postcardsAllowed() {
	var disabled = document.getElementById('postcardsEnabled_0').checked;
	document.getElementById('postcardsEmailEnabled').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('postcardsEmailEnabled_0').disabled = disabled;
	document.getElementById('postcardsEmailEnabled_1').disabled = disabled;
}


function save() {
	document.msaprops.submit();
}

mobileAllowed();
postcardsAllowed();
</script>