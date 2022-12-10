<? // mobile-frame.php
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
<html lang="en"><!-- html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" -->
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

$mobileSitterStyleSheet = $_SESSION[$mobileCSSKey] ? $_SESSION[$mobileCSSKey] : 'mobile-sitter.css';
if($isMobile) {
	if($isiPhone) {
?>
<meta name="format-detection" content="telephone=no">
<link media="only screen and (max-device-width: 480px)" href="<?= $mobileSitterStyleSheet ?>" type= "text/css" rel="stylesheet" / >
<? } // END isiPhone 
	else if(isiPad()) { ?>
<meta name="format-detection" content="telephone=no">
<link media="only screen and (max-device-width: 768px)" href="<?= $mobileSitterStyleSheet ?>" type= "text/css" rel="stylesheet" / >
<? } // END isiPad 
	else { // START other devices 
?>
<link media="only screen" href="<?= $mobileSitterStyleSheet ?>" type= "text/css" rel="stylesheet" / >
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
<link href="<?= $mobileSitterStyleSheet ?>" type= "text/css" rel="stylesheet" / >
<? 
}

$noExtension = isset($noExtension) ? $noExtension : $_REQUEST['noExtension'];  // don't overwrite if already set
// mobile_time_offset, mobile_private_zone_timeout_interval, and mobile_private_zone_timeout set at login
if($_SESSION['mobile_private_zone_timeout']) {
	//if(time() - $_SESSION['mobile_private_zone_timeout'] < 1)
	if($_SESSION['mobile_private_zone_timeout'] < time())
		$_SESSION['mobile_private_zone_timeout'] = null;
	else if(!$noExtension) {
		$_SESSION['mobile_private_zone_timeout'] = time() + $_SESSION['mobile_private_zone_timeout_interval'];
		$localTimeout = $_SESSION['mobile_private_zone_timeout'] - $_SESSION['mobile_time_offset'];
		$countdown = $_SESSION['mobile_private_zone_timeout'] - time();
	}
	$privateZoneOpen = $localTimeout > 0;
}
//if(mattOnlyTEST()) echo " mobile_private_zone_timeout_interval: [{$_SESSION['mobile_private_zone_timeout_interval']}]";;
//if(mattOnlyTEST()) echo " privateZoneOpen: [$privateZoneOpen] mobile_private_zone_timeout: [{$_SESSION['mobile_private_zone_timeout']} = ".date('Y-m-d H:i', $_SESSION['mobile_private_zone_timeout'])."] past: [".($_SESSION['mobile_private_zone_timeout'] < time())."]";
//if(mattOnlyTEST()) echo " mobile_time_offset: [{$_SESSION['mobile_time_offset']}]";
//if(mattOnlyTEST()) echo " localTimeout: [$localTimeout]";
//if(mattOnlyTEST()) exit;
if($countdown) echo "<script language='javascript' src='ajax_fns.js'></script>";

?>
<script language='javascript' src='mobile-emergency.js'></script>
<script language='javascript'>
<?
if($mobileSitterStyleSheet != "mobile-sitter.css") echo "var isBigger = true;\n";

echo "var emergencyTel='{$_SESSION['preferences']['bizPhone']}';\n";
echo "var emergencySMS='';\n";
echo "var offerFindAVet='{$_SESSION['preferences']['mobileOfferFindAVet']}';\n";
?>
var timeout = '<?= $localTimeout ?>';
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
	else if(sel == 'map') dest = "provider-map.php?date=<?= date('Y-m-d') ?>";
	else if(sel == 'documents') dest = "mobile-provider-files.php";
	else eval(sel+'()');
	if(dest) document.location.href = dest;
}

<? if($countdown) { ?>
var localTimeout = <?= $localTimeout ?>;
<? //if(loginidsOnlyTEST('jody.tonka,testbenball')) {echo "alert('localTimeout['+localTimeout+']');";} ?>
var countdownColor = 'yellow';
function countdown() {
	var countdowndiv = document.getElementById('countdown');
<? // if(loginidsOnlyTEST('jody.tonka')) echo "alert('Date locale['+new Date().toLocaleString()+']');"; ?>
<? // if(loginidsOnlyTEST('jody.tonka')) echo "alert('Date.parse['+Date.parse(new Date().toLocaleString())+']');"; ?>
	var now = parseInt(new Date().getTime()/1000);
<? //if(loginidsOnlyTEST('jody.tonka,testbenball')) echo "if(isNaN(now)) now = new Date().getTime()/1000;"; ?>
	
	// PROBLEM: in some cases (later versions of iPhone) countdowndiv's content shows as NaN in red
	//  probably because localTimeout is not set or not an integer
	var counter = parseInt((isNaN(parseInt(localTimeout)) ? 0 : localTimeout) - now);
	//var counter = localTimeout - now;
	
	
	if(counter <= 0) {
		counter = 'Locked';
		<?= $pageIsPrivate ? "document.location.href='index.php?noExtension=1'" : '' ?>
	}
	else if(counter > 60) counter = 'Unlocked ';//+counter;
	else if(countdowndiv) countdowndiv.style.color='red';
	if(countdowndiv) {
		countdowndiv.innerHTML = counter;
	}
	//else if(!confirm('No div!')) return;
	if(counter != 'Locked') setTimeout ('countdown()', 900); 
}

function resetCountdown() {
	if(document.getElementById('countdown').innerHTML == 'Locked') {
	}
	else ajaxGetAndCallWith('mobile-private-login.php?reset=1', 
														function(arg,result) {
															localTimeout = result;
															document.getElementById('countdown').style.color=countdownColor;
															countdown();}, 
														1);
}
countdown();
<? } ?>
</script>
</head>
<body>

<? if(!$isMobile) { ?>
<script language='javascript'>
function rotateScreen() {
	var testframe = document.getElementById('TESTFRAME');
	var w = testframe.style.width;
	testframe.style.width = testframe.style.height;
	testframe.style.height = w;
}
</script>
<input type=button onclick='rotateScreen()' value='Rotate'>
<div style='width:320px;height:480px;overflow:scroll;position:absolute;top:40px;left:40px;border: solid black 1px;background-color: white;' id='TESTFRAME'>
<? } ?>


<div class='banner'>
<? 
$homeLink = $homeLink ? $homeLink : 'index.php';
todaysDateTable($theDate=null, $extraStyle=null, $noStyle=false, $justStyle=true);
?>
<a href='<?= $homeLink ?>'><img src='art/LeashtimeLarry.jpg' height=72 width=72 align=left border=0>
<?
echo todaysDateTable($theDate=null, 'position:absolute;left:75px;top:5px;width:40px;z-index:999;color:black;'
																			.'font-family: arial, sans-serif, helvetica;'
		 																	.'border-color:brown;'
		 																	.'border-top:solid brown 1px;border-left:solid brown 1px;', $noStyle=true);
?></a><?															
if(!$noOptions && !$_SESSION['passwordResetRequired']) {		 																	
?>
<select id='bannerselect' class='bannerselect' onchange='optionPicked()'>
<option>Options
<?= $pageOptions ?>
<option value='pastvisits'>Past Visits
<!-- option class='disabled'>Pay History -->
<option value='myclients'>My Clients
<!-- option class='disabled'>Send Email -->
<? if($_SESSION['secureKeyEnabled'])  { ?>
<option value='keyring'>My Key Ring
<? } ?>
<option value='mypassword'>My Password
<? if($_SESSION['preferences']["enableMobileSitterMap"]) echo "<option value='map'>Map"; ?>
<? if(dbTEST('dogslife,tonkatest'))  { ?>
<option value='documents'>My Documents
<? } ?>
<option value='mypay'>My Pay
<? if(mattOnlyTEST() || dbTEST('tonkatest,tonkapetsitters')) echo "<option value='settings'>Settings"; ?>
<?= $pageOptionsAtEnd ?>
<? 
	if(!$_SESSION['preferences']["webUIOnMobileDisabled"])  { ?>
<option value='switchtoweb'>Switch to Web App
<? } ?>
<!-- option class='disabled'>Full Mode -->
<? 
	if($_SESSION['preferences']["mobileOfferFindAVet"])  { ?>
<option value='findavet'>Find a Vet!
<? } ?>
<option value='logout'>Logout
</select>

<div class='countdown' id='countdown' onclick='resetCountdown()'><?= $countdown ?></div>

<img src='art/emergency-button.png' style='position:absolute;left: 265px; top:44px;' border=0
	onclick='showEmergencyPage()'>
<? 
$commentsEnabled = true;
if(FALSE && $commentsEnabled) { ?>
<img src='art/suggestions.gif' style='position:absolute;left: 265px; top:38px;' border=0
	onclick='var w = window.open("","feedback", "");w.document.location.href="feedback.php?url="+escape("<?= $_SERVER['REQUEST_URI'] ?>")+"&mobile=1"' 
>
<?  } //if($commentsEnabled) ?>

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

	if(pw) document.location.href = "mobile-private-login.php?pw="+ encodeURIComponent(pw)+"&goal="+escape(thispage);
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
