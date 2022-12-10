<?
// social-contacts-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
//require_once "field-utils.php";
//require_once "provider-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox
$hellOnEarth = array(
	'linkedinaddress'=>'LinkedIn',
	'facebook'=>'Facebook',
	'twitteraddress'=>'Twitter',
	'instagraminaddress'=>'Instagram');


if($_POST['savethisform']) {
	//extract($_POST);
	foreach($hellOnEarth as $k => $label) {
		setPreference($k, $_POST[$k]);
		if($_POST[$k]) $fieldsSet[] = $label;
	}
	setPreference('socialcontacts', $fieldsSet = 'Set: '.($fieldsSet ? join(', ', $fieldsSet) : 'none'));
	echo "<script language='javascript'>parent.$.fn.colorbox.close();parent.updateProperty('socialcontacts', '$fieldsSet')</script>";
}

include "frame-bannerless.php";
?>

<h2>Social Media Addresses</h2>
<form method='POST' name='socialform'>
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

foreach($hellOnEarth as $fld => $label) {
	$val = getPreference($fld);
	inputRow($label, $fld, $val, $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
}
?>
</table>
</form>
<script language='javascript'>

function save() {
	document.socialform.submit();
}
</script>