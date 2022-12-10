<? // clientagreementrequired-pref-edit.php

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
extract(extractVars('clientagreementrequired', $_REQUEST));

if($_POST) {
	setPreference('clientagreementrequired',  ($clientagreementrequired ? 1 : 0) );
	require "common/init_db_common.php";
	updateTable('tblpetbiz', 
							array('clientagreementrequired'=>$_SESSION['preferences']['clientagreementrequired']),
							"bizid = {$_SESSION["bizptr"]}", 1);
	echo "<script language='javascript'>window.opener.updateProperty(\"clientagreementrequired\", null);window.close();</script>";
	exit;
}

$windowTitle = "Is a Signed Service Agreement Required for Clients to Use the System?";;
require "frame-bannerless.php";

?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<?
$currentSetting = $_SESSION['preferences']['clientagreementrequired'] ? 1 : 0;
selectElement('', 'clientagreementrequired', $currentSetting, array('yes'=>1, 'no'=>0));
echo "<p>";
echoButton('', 'Save', 'document.propertyeditor.submit()');
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</form>";
?>
