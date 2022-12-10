<? // secureClientInfo-pref-edit.php

/* Params
none
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require "preference-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
$failure = false;

if($failure) {
	$windowTitle = 'Insufficient Access Rights';
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}
extract(extractVars('secureClientInfo,secureClientInfoNoKeyIDsAtAll,secureClientInfoNoAlarmDetailsAtAll', $_REQUEST));

if($_POST) {
	setPreference('secureClientInfo',  ($secureClientInfo ? 1 : 0) );
	setPreference('secureClientInfoNoKeyIDsAtAll',  ($secureClientInfoNoKeyIDsAtAll ? 1 : 0) );
	setPreference('secureClientInfoNoAlarmDetailsAtAll',  ($secureClientInfoNoAlarmDetailsAtAll ? 1 : 0) );
	echo "<script language='javascript'>window.opener.updateProperty(\"secureClientInfo\", null);window.close();</script>";
	exit;
}

$windowTitle = "Secure Client Info - Controlling Visit Sheet Content";;
require "frame-bannerless.php";

?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<?
$currentSetting = $_SESSION['preferences']['secureClientInfo'] ? 1 : 0;
selectElement('Suppress Key, Access and Alarm Codes Info on Visit Sheets', 'secureClientInfo', $currentSetting, array('yes'=>1, 'no'=>0), 'secureClientToggled(this)');
//selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false) {

echo "<p>";
$currentSetting = $_SESSION['preferences']['secureClientInfoNoKeyIDsAtAll'] ? 1 : 0;
selectElement('Omit Key IDs from Visit Sheet Summary', 'secureClientInfoNoKeyIDsAtAll', $currentSetting, array('yes'=>1, 'no'=>0));

echo "<p>";
$currentSetting = $_SESSION['preferences']['secureClientInfoNoAlarmDetailsAtAll'] ? 1 : 0;
selectElement('Omit Alarm details from the Visit Sheet Summary', 'secureClientInfoNoAlarmDetailsAtAll', $currentSetting, array('yes'=>1, 'no'=>0));
echo "<p>";
echoButton('', 'Save', 'document.propertyeditor.submit()');
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</form>";
?>
<script language='javascript'>
function secureClientToggled(el) {
	$off = el.options[el.selectedIndex].value == '0';
	document.getElementById('secureClientInfoNoKeyIDsAtAll').disabled  = $off;
	document.getElementById('secureClientInfoNoAlarmDetailsAtAll').disabled  = $off;
}
secureClientToggled(document.getElementById('secureClientInfo'));
</script>
