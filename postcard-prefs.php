<? // postcard-prefs.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "preference-fns.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "postcard-fns.php";
//require_once "email-fns.php";
//require_once "comm-composer-fns.php";

if(userRole() == 'c') {
	locked('c-');
	$clientid = $_SESSION["clientid"];
}
else {
	locked('o-');
	$clientid = $_REQUEST["client"];
}
//$client = getOneClientsDetails($clientid);
if($_POST) {
	setClientPreference($clientid, 'noPostcards', ($_POST['noPostcards'] ? 1 : '0'));
	setClientPreference($clientid, 'postcardEmail', ($_POST['postcardEmail'] ? $_POST['postcardEmail'] : null));
	setClientPreference($clientid, 'postcardMediaAllowed', ($_POST['postcardMediaAllowed'] ? $_POST['postcardMediaAllowed'] : null));
	$_SESSION['user_notice'] = 'Postcard preferences saved.';
	echo "<script language='javascript'>parent.$.fn.colorbox.close();</script>";
	exit;
}

require "frame-bannerless.php";
?>
<form name='postcardprefs' method='POST'>
<?
echoButton(null, "Save Preferences", $onClick='savePrefs()', $class=null, $downClass=null, $noEcho=false, $title=null);
echo "<img src='art/spacer.gif' width=110 height=1>";
echoButton(null, "Quit", $onClick='parent.$.fn.colorbox.close();', $class=null, $downClass=null, $noEcho=false, $title=null);
?>
<p>
<table width=90%>
<?
checkboxRow('No more postcards, please:', 'noPostcards', getClientPreference($clientid, 'noPostcards'), $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null);
if($_SESSION['preferences']['postcardsEmailEnabled']) 
	inputRow('Please email postcards to:', 'postcardEmail', getClientPreference($clientid, 'postcardEmail'), $labelClass=null, $inputClass='emailInput', $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
	

//radioButtonRow($label, $name, $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null) {
$options = array('Photos Only'=>'photosOnly', 'Photos and iPhone/iPad Videos'=>'iPhoneOnly', 'Photos and All Videos'=>null);
radioButtonRow('Please send:', 'postcardMediaAllowed', getClientPreference($clientid, 'postcardMediaAllowed'), $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=1);
labelRow('', '', 'Some videos may not be playable on some devices.', null, 'tiplooks');
?>
</table>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('postcardEmail','Email Address');
function savePrefs() {
	if(MM_validateForm('postcardEmail', '', 'isEmail')) {
		document.postcardprefs.submit();
	}
}
</script>