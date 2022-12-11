<? // login-page.php
require_once "common/init_session.php";
require_once "gui-fns.php";


if($_GET['clearleashtimerole'])
	setcookie('LEASHTIMEROLE', '', time()+60*60*24*30);


if ( isset ( $_GET['logout'] ) ) {
	$goto = '';
	if(userRole() == 'c' && $_SESSION['preferences']['bizHomePage']) $goto = $_SESSION['preferences']['bizHomePage'];
	if($_SESSION['trainingMode']) {
		require_once "training-fns.php";
		require_once "common/init_db_petbiz.php";
		turnOffTrainingMode();
	}
	session_unset();
  session_destroy();
 if($goto) {
		header("Location: $goto");
		exit;
	}
  $this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
}

    $pageTitle = "Login";
if(isset($sessionTimeout)) {// if included by timedoutsession.php
	$message .= "<font color=red>Your session timed out.<p></font>Enter your username and password below to log in again.<p>";
}
else if(isset($login_failed) && $login_failed) {
  if($failure == 'L') $message .= "This account is locked.  <font color=red>Please contact support.</font>"; 
  else if($failure == 'D') $message .= "Access is unavailable at this time.</font>"; 
  else $message .= "The username or password entered was incorrect.";
  $message = "<span class='warning'>$message</span><p>";
  if(($failure == 'P') && ($_POST['user_pass'] == strtoupper($_POST['user_pass'])))
    $message .= '<br>(Is it possible that your Caps Lock is on?)<p>';
}
else $message .= "Enter your username and password below to log in.<p>";

$lockChecked = true;

$thisBiz = $_REQUEST['bizid'] ? $_REQUEST['bizid'] : $_COOKIE['LEASHTIMEBIZPTR'];
//$thisBiz = $_REQUEST['bizid'];

//if(!mattOnlyTEST()) 
if("".(int)"$thisBiz" != $thisBiz) $thisBiz = 0; // against injection attacks 7/22/2020


$mobilepattern = '/Alcatel|iPhone|iPod|SIE-|BlackBerry|Android|IEMobile|Obigo|Windows CE|LG\/|LG-|CLDC|Nokia|SymbianOS|PalmSource\|Pre\/|Palm webOS|SEC-SGH|SAMSUNG-SGH/i';
$isMobile = $_REQUEST['mobile'] || preg_match($mobilepattern, $_SERVER['HTTP_USER_AGENT']);
if($isMobile /*|| mattOnlyTEST()&& $_SERVER['REMOTE_ADDR'] == '68.225.89.173'*/) {
	if(!$thisBiz) {
		include "login-form-mobile.html";
		exit;
	}
}



$_REQUEST['bizid'] = $thisBiz; // to ensure that bizid is set in the form when the page is branded by LEASHTIMEBIZPTR cookie

if($thisBiz) {
	require "common/init_db_common.php";
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$thisBiz' LIMIT 1");
	if(!$biz) include "frame.html";
	else {

		if($_REQUEST['qrcode']) {
			require_once "qrcode-fns.php";
			$qrcode = getQRCode(globalURL("login-page.php?bizid={$_REQUEST['bizid']}&qrcode=1"));
		}
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 'force');
		$homepage = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage' LIMIT 1");
		$bizname = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
		if(!$_GET['logout']) $loginMessage = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'loginMessage' LIMIT 1");
		if($loginMessage) $_SESSION['user_notice'] = $loginMessage;
		require "common/init_db_common.php";
		$_SESSION["uidirectory"] = "bizfiles/biz_$thisBiz/clientui/";
		if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$thisBiz/";
		$suppressMenu = 1;
		$doItSMALL = ((/*mattOnlyTEST() || */$thisBiz == 3 || dbTEST('dogslife')) && $isMobile);
		if($doItSMALL) {
			function getSmallBannerCSS() {
				$headerBizLogo = $_SESSION["uidirectory"];
				if(file_exists($_SESSION["uidirectory"].'../logo.jpg')) $headerBizLogo .= '../logo.jpg';
				else if(file_exists($_SESSION["uidirectory"].'../logo.gif')) $headerBizLogo .= '../logo.gif';
				else if(file_exists($_SESSION["uidirectory"].'../logo.png')) $headerBizLogo .= '../logo.png';
				else $headerBizLogo = '';
			}

			//echo "BANG!BANG!fuckingBANG!  $headerBizLogo".print_r($_SESSION,1);exit;	

				if($headerBizLogo) {
					//$dimensions = imageDimensionsScaledToFit($headerBizLogo, 386, 56);
					return "background-image: url('$headerBizLogo');";
									//background-size: {$dimensions[0]}px {$dimensions[1]}px;";
				}
			}


			$smallBannerCSS = getSmallBannerCSS();
			$extraHeadContent = <<<MOBILESTYLE
			<style>
		body {background-image: none;}
		.Sheet 
		{
			width: 100%;
		}
		div.Header 
		{
			width: 100%;
			height: 100px;
		}


		div.Header   div
		{
			$smallBannerCSS
			background-repeat: no-repeat;
			background-position: center center;
		}
		div {font-size:1.55em;}
		h2 {font-size:1.0em;font-weight:bold;}
		#logintable {font-size:1.0em;}
		.ahunnert {width:$aHunnertPercent;background:#f5f5f5;}
			</style>
MOBILESTYLE;
		}
		
		
		$mobilepattern = '/Alcatel|iPhone|SIE-|BlackBerry|Android|IEMobile|Obigo|Windows CE|Windows Phone|LG\/|LG-|CLDC|Nokia|SymbianOS|PalmSource\|Pre\/|Palm webOS|SEC-SGH|SAMSUNG-SGH/i';
		$isMobile = $_SESSION["mobiledevice"] || preg_match($mobilepattern, $_SERVER['HTTP_USER_AGENT']);
		$isiPhone = preg_match('/iPhone|iPod/i', $_SERVER['HTTP_USER_AGENT']);
		$isiPad = preg_match('/iPad/i', $_SERVER['HTTP_USER_AGENT']);
		if(TRUE || $isMobile) {
			if($isiPhone) $extraHeadContent = 
				'<meta name="format-detection" content="telephone=no">';
			// END isiPhone 
			else if(isiPad()) $extraHeadContent = 
				'<meta name="format-detection" content="telephone=no">';
			 // END isiPad 
			else {} // END other devices -- dropped  "and (max-width: 480px)"
			$extraHeadContent .= '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
			<link rel="apple-touch-icon" href="art/LeashtimeLarry.jpg" />';
			$noCommentButton = true;
		}


				include "frame-client.html";
			}
		}

else include "frame.html";
if(mattOnlyTEST()) echo "isMobile: [$isMobile]";

// ***************************************************************************
$downForMaintenance = 0;
if($downForMaintenance) {
	$bizMessage = !$biz ? '' : "Please contact $bizname directly at <a href='$homepage'>$homepage</a><br>if you need assistance during this time.<p align=center>";
	$message = "
<h1 align=center>LeashTime is Down for Scheduled Maintenance</h1> 
<span style='font-size:1.2em'><img src='art/lightning-smile-small.jpg' style='float:right;'>
<p align=center>
LeashTime is undergoing a <b>major server upgrade</b> starting 
<p align=center>
 <span style='color:green;font-weight:bold;font-family:times new roman,serif;font-size:1.5em;'>Saturday April 16 8:30 pm ET </span>
<p align=center>
and may be unavailable until early Sunday morning.
<p align=center>
$bizMessage
Thank you for your patience while we improve LeashTime.
</span>
";	
}
echo $message ? $message : '';

if(!$downForMaintenance) {
	if(mattOnlyTEST() && !$thisBiz && in_array($_COOKIE["LEASHTIMEROLE"], array('o', 'd'))) 	{ include "login-form-390-LT.html";}
	else if($doItSMALL) {
		$noFrame = true;
		include "login-form-mobile.html";
	}
	else include "login-form-390.html";
	
	if(FALSE && $biz && $biz['db'] == 'dogslife') { // mattOnlyTEST()
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 'force');
		$loginpagepromobox = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'loginpagepromobox' LIMIT 1", 1);
		// {"width": 300, "height": 300, "src": "https://loveandkissespetsitting.net"}
		// {"width": 300, "height": 300, "src": "https://leashtime.com/testpromo.php"}
		require "common/init_db_common.php";
		if(!$loginpagepromobox) ; // no-op
		//loginpagepromobox - height, width, href
		else if($loginpagepromobox) {
			$loginpagepromobox = json_decode($loginpagepromobox, 'asASSOC');
			if($loginpagepromobox === null) echo "invalid promobox";
			else if(!$loginpagepromobox['src']) echo ""; // no-op
			else {
				$height = $loginpagepromobox['height'] ? $loginpagepromobox['height'] : '100';
				$width = $loginpagepromobox['width'] ? $loginpagepromobox['width'] : '390';
				$src = $loginpagepromobox['src'];
				echo "<iframe src=\"$src\" height=\"$height\" width=\"$width\" ></iframe>";
			}
		}
	}
}
// ***************************************************************************
?>
<p>
<? if(!$thisBiz) { ?>
<a href='http://www.facebook.com/LeashTime' target='leashtimefacebook' title='Check us out on FaceBook'>
<img style="vertical-align:top" border=0 src='art/facebook.png'>&nbsp;&nbsp;<span style='font-size:1.2em;font-decoration:none'>facebook.com/LeashTime</span>
</a>
<?
	if(mattOnlyTEST()) {
		//echo "<p><a href='?clearleashtimerole=1'>Clear Role (test)</a>";
	}
}

include "frame-end.html";
//phpinfo();
?>
