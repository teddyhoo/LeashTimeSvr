<? // fullScreen-pref-edit.php

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
extract(extractVars('fullScreenTabletView', $_REQUEST));

if($_POST) {
	$frameLayout = $fullScreenTabletView ? 'fullScreenTabletView' : null;
	setUserPreference($_SESSION["auth_user_id"], 'frameLayout',  $frameLayout);
	$_SESSION['frameLayout'] = $frameLayout ; // set at login time also
	$_SESSION['bannerLogo'] = null;
//echo "OINK: [$frameLayout]";exit;
	echo "<script language='javascript'>window.opener.updateProperty(\"frameLayout\", null);window.close();</script>";
	exit;
}

$windowTitle = "Use the Whole Window for LeashTime (Tablet View)?";;
require "frame-bannerless.php";

?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<?
$currentSetting = $_SESSION['frameLayout'] == 'fullScreenTabletView' ? 1 : 0;
selectElement('', 'fullScreenTabletView', $currentSetting, array('yes'=>1, 'no'=>0));
echo "<p>";
echoButton('', 'Save', 'document.propertyeditor.submit()');
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</form>";
?>
