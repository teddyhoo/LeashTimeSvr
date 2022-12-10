<? // mobile-frame-bannerless.php
// input: $windowTitle, $extraBodyStyle, $extraBodyAttributes, $excludeStylesheets
/* usage:
$windowTitle = 'xxx';
require "frame-bannerless.php";
*/
$windowTitle = isset($windowTitle) ? $windowTitle : '';
$extraBodyStyle = isset($extraBodyStyle) ? $extraBodyStyle : '';
$extraBodyAttributes = isset($extraBodyAttributes) ? $extraBodyAttributes : '';
$excludeStylesheets = isset($excludeStylesheets) ? $excludeStylesheets : false;
$customStyles = isset($customStyles) ? $customStyles : '';
$extraHeadContent = isset($extraHeadContent) ? $extraHeadContent : '';
if($_SESSION) {  // suppress headers when cron job is generating emails that include this file
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
}

?>
<html><!-- html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" -->
<head> 
<?
$noExtension = isset($noExtension) ? $noExtension : $_REQUEST['noExtension'];  // don't overwrite if already set

$mobileCSSKey = "mobileCSS_".hash('md5', $_SESSION['userAgent']);
if($db && $_SESSION && $_SESSION["auth_user_id"] && !array_key_exists($mobileCSSKey, $_SESSION)) {
	$_SESSION[$mobileCSSKey] = 
		fetchRow0Col0("SELECT value 
										FROM tbluserpref 
										WHERE userptr = {$_SESSION["auth_user_id"]}
											AND property = '$mobileCSSKey'");
}
$mobileSitterStyleSheet = $_SESSION[$mobileCSSKey] ? $_SESSION[$mobileCSSKey] : 'mobile-sitter.css';


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
if($countdown) { ?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
var localTimeout = <?= $localTimeout ?>;
var countdownColor = 'yellow';
function countdown() {
	var countdowndiv = document.getElementById('countdown');
	var now = parseInt(new Date().getTime()/1000); // Date.parse(new Date().toLocaleString())/1000;	
	var counter = parseInt(localTimeout - now);
	if(counter <= 0) {
		counter = 'Locked';
		<?= $pageIsPrivate ? "timeoutAction();" : '' ?>
	}
	else if(counter > 60) counter = 'Unlocked ';//+counter;
	else if(countdowndiv) countdowndiv.style.color='red';
	if(countdowndiv) countdowndiv.innerHTML = counter;
	if(counter != 'Locked') setTimeout ('countdown()', 900); 
}

function resetCountdown() {
	var counterState = document.getElementById('countdown').innerHTML;
	if(counterState == 'Locked' || counterState == '') {
	}
	else ajaxGetAndCallWith('mobile-private-login.php?reset=1', 
														function(arg,result) {
															localTimeout = result;
															document.getElementById('countdown').style.color=countdownColor;
															countdown();}, 
														1);
}
countdown();
if(window.opener && window.opener.resetCountdown) window.opener.resetCountdown();
</script>
<? } ?>

  <meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" />  
<? if(!$excludeStylesheets) { ?>  
  <link rel="stylesheet" href="<?= $mobileSitterStyleSheet ?>" type="text/css" />
	<link media="only screen and (max-device-width: 480px)" href="<?= $mobileSitterStyleSheet ?>" type= "text/css" rel="stylesheet" / >
	<!-- link media="only screen and (max-device-width: 480px)" href="mobile-sitter.css" type= "text/css" rel="stylesheet" / -->
	<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
<? } 
if($customStyles) echo "
<style>
$customStyles
</style>\n";
echo $extraHeadContent;
?>
</head> 
<body style='padding:3px;color:black;font-size:0.7em;background:#FF8B00;<?= $extraBodyStyle ?>' <?= $extraBodyAttributes ?>>
<? if($showCountdown) { ?>
<div class='countdown' id='countdown' onclick='resetCountdown()' style='<?= $countdownStyle ?>'><?= $countdown ?></div>
<? } ?>