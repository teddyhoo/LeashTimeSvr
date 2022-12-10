<? // bizAddress-pref-edit.php

/* Params
none
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "zip-lookup.php";
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
extract(extractVars('bizAddress,street1,street2,city,state,zip', $_REQUEST));

if($_POST) {
	foreach(array($street1, $street2, $city, $state, $zip) as $part)
		if($part) $parts[] = $part;
	$bizAddress = join(' | ', $parts);
	setPreference('bizAddress',  $bizAddress);
	foreach(explode(',', 'street1,street2,city,state,zip') as $k)
		$json[$k] = $_POST[$k];
	setPreference('bizAddressJSON', json_encode($json));
	echo "<script language='javascript'>window.opener.updateProperty(\"bizAddress\", null);window.close();</script>";
	exit;
}

$windowTitle = "Business Address";;
require "frame-bannerless.php";

?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<?
$bizAddress = getPreference('bizAddress');
if(!$bizAddress) $bizAddress = array('','','','','');
else $bizAddress = explode(' | ', $bizAddress);
$fields = array('street1','street2','city','state','zip');
if(count($bizAddress) < 5) unset($fields[1]);
if(count($bizAddress) < 4) unset($fields[0]);
foreach(array_merge($fields) as $n => $k) {
	$address[$k] = $bizAddress[$n];
}
addressTable('Business Address', '', $address, $constrained=false);
echo "<p>";
echoButton('', 'Save', 'document.propertyeditor.submit()');
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</form>";
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>

<script language='javascript'>
<? dumpZipLookupJS(); ?>
function supplyLocationInfo(cityState,addressGroupId) {
	var cityState = cityState.split('|');
	if(cityState[0] && cityState[1]) {
		var city = document.getElementById('city');
		var state = document.getElementById('state');
		var needConfirmation = false;
		needConfirmation = needConfirmation || (city.value.length > 0 && (city.value.toUpperCase() != cityState[0].toUpperCase()));
		needConfirmation = needConfirmation || (state.value.length > 0 && (state.value.toUpperCase() != cityState[1].toUpperCase()));
		if(!needConfirmation || confirm("Overwrite city and state with "+cityState[0]+", "+cityState[1]+"?")) {
		  if(city.value.toUpperCase() != cityState[0].toUpperCase()) city.value = cityState[0];
		  if(state.value.toUpperCase() != cityState[1].toUpperCase()) state.value = cityState[1];
		}
	}
}


</script>