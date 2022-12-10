<? // mobile-frame-client.php
require_once "gui-fns.php";


$mobilepattern = '/Alcatel|iPhone|SIE-|BlackBerry|Android|IEMobile|Obigo|Windows CE|Windows Phone|LG\/|LG-|CLDC|Nokia|SymbianOS|PalmSource\|Pre\/|Palm webOS|SEC-SGH|SAMSUNG-SGH/i';
$isMobile = $_SESSION["mobiledevice"] || preg_match($mobilepattern, $_SERVER['HTTP_USER_AGENT']);
$isiPhone = preg_match('/iPhone|iPod/i', $_SERVER['HTTP_USER_AGENT']);
$isiPad = preg_match('/iPad/i', $_SERVER['HTTP_USER_AGENT']);
if(mattOnlyTEST()) {
	//echo '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.1//EN"	"http://www.openmobilealliance.org/tech/DTD/xhtml-mobile11.dtd">'."\n";
	//echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN">'."\n";

	if($_REQUEST['emulate']) {
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
		$isMobile = true;
		$isiPhone = $_REQUEST['emulate'] == 'iphone';
	}
	else echo "<!DOCTYPE html>";
}
else if($isMobile) {
	echo '<!DOCTYPE html>';
}
?>
<html><!-- html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" -->
<head> 


<?
$mobileCSSKey = "mobileCSS_".hash('md5', $_SESSION['userAgent']);
if($db && $_SESSION && $_SESSION["auth_user_id"] && !array_key_exists($mobileCSSKey, $_SESSION)) {
	$_SESSION[$mobileCSSKey] = 
		fetchRow0Col0("SELECT value 
										FROM tbluserpref 
										WHERE userptr = {$_SESSION["auth_user_id"]}
											AND property = '$mobileCSSKey'");
}

$mobileClientStyleSheet = $_SESSION[$mobileCSSKey] ? $_SESSION[$mobileCSSKey] : 'mobile-client.css';
if($isMobile) {
	if($isiPhone) {
?>
<meta name="format-detection" content="telephone=no">
<link media="only screen and (max-device-width: 480px)" href="<?= $mobileClientStyleSheet ?>" type= "text/css" rel="stylesheet" / >
<? } // END isiPhone 
	else if(isiPad()) { ?>
<meta name="format-detection" content="telephone=no">
<link media="only screen and (max-device-width: 768px)" href="<?= $mobileClientStyleSheet ?>" type= "text/css" rel="stylesheet" / >
<? } // END isiPad 
	else { // START other devices 
?>
<link media="only screen" href="<?= $mobileClientStyleSheet ?>" type= "text/css" rel="stylesheet" / >
<? } // END other devices -- dropped  "and (max-width: 480px)"
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
<link rel="apple-touch-icon" href="art/LeashtimeLarry.jpg" />
<!--link href="mobile-sitter.css" type= "text/css" rel="stylesheet" /-->
<style>
body {
margin:0px;
font-family: arial, sans-serif, helvetica;
background-color: white;
}
</style>
<?
//if(mattOnlyTEST()) echo '<link href="mobile-sitter.css" type= "text/css" rel="stylesheet" / >';
} // isMobile
else { ?>
<link href="<?= $mobileClientStyleSheet ?>" type= "text/css" rel="stylesheet" / >
<? 
}


?>
<script language='javascript'>
<?
if($mobileClientStyleSheet != "mobile-sitter.css") echo "var isBigger = true;\n";

?>
function optionPicked() {
	var dest = null;
	var sel = document.getElementById('bannerselect');
	sel = sel.options[sel.selectedIndex].value;
	if(sel == 'logout') dest = "login-page.php?logout=1";
	else if(sel == 'mypassword') dest = "password-change-page-mobile.php";
	else if(sel == 'myclients') dest = "client-list-mobile.php";
	else if(sel == 'keyring') dest = "provider-keys-mobile.php";
	else if(sel == 'mypay') dest = "prov-current-pay.php?mobile=1";
	else if(sel == 'pastvisits') dest = "incomplete-prov-mobile.php";
	else if(sel == 'switchtoweb') dest = "prov-switch-interface.php?mode=web";
	else if(sel == 'findavet') dest = "mobile-nearby-vets.php";
	else if(sel == 'settings') dest = "mobile-settings.php";
	else eval(sel+'()');
	if(dest) document.location.href = dest;
}
/*
<option value='calendar'>Calendar
<option value='pastvisits'>Request Visits
<option value='myprofile'>My Profile
<option value='mypassword'>My Password
<option value='contactus'>Contact Company
<option value='mypayment'>My E-Pay Options
<option value='mypassword'>My Account
*/

</script>
<?= $extraHeadContent ?>
</head>
<body>


<? 
$homeLink = $homeLink ? $homeLink : 'index.php';
//todaysDateTable($theDate=null, $extraStyle=null, $noStyle=false, $justStyle=true);
//echo "[[".$_SESSION["bizfiledirectory"].'logo.jpg'."]]";
if(TRUE || !$_SESSION['bannerLogo']) { //
	$headerBizLogo = $_SESSION["bizfiledirectory"];
	if(file_exists($_SESSION["bizfiledirectory"].'logo.jpg')) $headerBizLogo .= 'logo.jpg';
	else if(file_exists($_SESSION["bizfiledirectory"].'logo.gif')) $headerBizLogo .= 'logo.gif';
	else if(file_exists($_SESSION["bizfiledirectory"].'logo.png')) $headerBizLogo .= 'logo.png';
	else $headerBizLogo = '';
	if($headerBizLogo) {
		$maxLogoBox = array(250, 50);
		$dimensions = imageDimensionsScaledToFit($headerBizLogo, $maxLogoBox[0], $maxLogoBox[1]);
		$_SESSION['bannerLogo'] = "<img src='$headerBizLogo' style='width:{$dimensions[0]}px;height:{$dimensions[1]}px;'>";
	}
	else $_SESSION['bannerLogo'] = "<img src='art/LeashtimeLarry.jpg' height=72 width=72 align=left border=0>";
}

?>   
<div class='banner'>
<table style='border: solid red 0px;width:100%;padding:0px;margin:0px;'><tr><td>
<a href='<?= $homeLink ?>'><?= $_SESSION['bannerLogo']
/*echo todaysDateTable($theDate=null, 'position:absolute;left:75px;top:5px;width:40px;z-index:999;color:black;'
																			.'font-family: arial, sans-serif, helvetica;'
		 																	.'border-color:brown;'
		 																	.'border-top:solid brown 1px;border-left:solid brown 1px;', $noStyle=true);*/
?></a></td><td>
<?															
if(!$noOptions && !$_SESSION['passwordResetRequired']) {		 																	
?>
<select id='bannerselect' onchange='optionPicked()'>
<option>Options
<?= $pageOptions ?>
<option value='calendar'>Calendar
<option value='pastvisits'>Request Visits
<option value='myprofile'>My Profile
<option value='mypassword'>My Password
<option value='contactus'>Contact Company
<? if(true /*ACCOUNT*/) { ?>
<? if(true /*CC*/) { ?>
<option value='mypayment'>My E-Pay Options
<? } ?>
<option value='mypassword'>My Account
<? } ?>
<?= $pageOptionsAtEnd ?>
<option value='logout'>Logout
</select>
</td></tr>
</table>

<div class='countdown' id='countdown' onclick='resetCountdown()'><?= $countdown ?></div>

<? 
}
else if($bizName) {
	echo "<span class='bannertitle'>$bizName</span>";
}
?>
</div>
<script language='javascript'>
addEmergencyPage();	
</script>
<?

if($_SESSION['passwordResetRequired']) include "password-change-mobile.php";  // NOTE: THIS ENDS THE PAGE!!!

if($pageIsPrivate && !$privateZoneOpen) {  // Private Zone login form
	if($_REQUEST['pagemessage']) echo "<p style='color:green;background:white;'>{$_REQUEST['pagemessage']}</p>";
	//echo "[[{$_SESSION['mobile_private_zone_timeout']}]]";
?>
<p>
<form name='passprompt' method='POST' onsubmit='return false;// prevent iPhone Go button mischief'>
Password: <input type="password" id="user_pass" size="25" maxlength="30"  onKeyup='autoSubmit(event)' onchange='privateLogin()'> <input type="button" value="Go" onclick='privateLogin()'>
<p style='text-align:center;'>or
<p style='text-align:center;'><input type="button" value="Home" onclick='document.location.href="index.php"'>
</form>
<script language='javascript'>
function privateLogin() {
	var thispage = '<?= $_SERVER["REQUEST_URI"] ?>'; // e.g. "/visit-sheet.php?....
	if(thispage.substring(0,1) == '/') thispage = thispage.substring(1);
	var pw = document.getElementById('user_pass').value;
//alert("mobile-private-login.php?pw="+pw+"&goal="+escape(thispage));	
	if(pw) document.location.href = "mobile-private-login.php?pw="+pw+"&goal="+escape(thispage);
}

function autoSubmit(e) {
	var key;
	if(window.event) key = window.event.keyCode;
	else key = e.which;
	if(key == 13 || key == 10) privateLogin();
}

</script>

<?
exit;
}

if(!$delayPageContent) echo "<div class='pagecontentdiv' id='contentdiv'>\n"; 
?>
