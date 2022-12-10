<?
// timezone-options-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

if($_POST) {
	setPreference('timeZone', $_POST['timeZone']);
	require "common/init_db_common.php";
	updateTable('tblpetbiz', array('timeZone'=>$_POST['timeZone']), "bizid = {$_SESSION['bizptr']}", 1);
	echo "<script language='javascript'>if(parent.updateProperty) parent.updateProperty('timeZone', '{$_POST['timeZone']}');parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";
?>

<h2>Your Time Zone</h2>
<form method='POST' name='tzprops'>
<?
//foreach(getLTZones() as $label =>$zone) $timeZones[]= "$label=>$zone";
selectElement('Time Zone:', 'timeZone', $_SESSION['preferences']['timeZone'], getLTZones());

echo '<p>';
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
</form>
<script language='javascript'>
function save() {
	if(!document.getElementById('timeZone').value) 
		return alert('Please pick a time zone.');
	document.tzprops.submit();
}
</script>