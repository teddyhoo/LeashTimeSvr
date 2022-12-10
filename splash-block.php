<?
// splash-block.php
/* Throw up a splash window to block the screen if:
*   - cookies are not enabled for this site
*   - Javascript is disabled
*   - the current browser is not supported.
*/
require_once "common/init_session.php";


$noCookieMessage = "Please ensure your browser is set to accept a cookie from: {$_SERVER["HTTP_HOST"]}<p>";
$welcomeMessage = 'Welcome to LeashTime!\n\nPlease click Ok to continue';

$browser = $_SERVER["HTTP_USER_AGENT"];
$matches = array('Firefox'=>'', 
								'MSIE 6.0'=>'Internet Explorer v6', 'MSIE 7.0'=>'Internet Explorer v7', 'MSIE 8.0'=>'Internet Explorer v8', 
								'Chrome'=>'', 'Safari'=>'');

$cookie_help = array(
	'MSIE 6.0' => 'http://support.microsoft.com/kb/283185',
	'MSIE 7.0' => 'http://windowshelp.microsoft.com/Windows/en-us/help/ff035adb-411d-40f3-8f9f-23e158f7b8be1033.mspx',
	'MSIE 8.0' => 'http://windowshelp.microsoft.com/Windows/en-us/help/ff035adb-411d-40f3-8f9f-23e158f7b8be1033.mspx',
	'Firefox' => 'http://support.mozilla.com/en-US/kb/Enabling+and+disabling+cookies',
	'Chrome' => 'http://www.google.com/support/chrome/bin/answer.py?hl=en&answer=95647',
	'Safari' => 'http://docs.info.apple.com/article.html?path=Safari/3.0/en/9277.html'
	);

if($noCookie) foreach($matches as $match=>$prettyName) 
	if(strpos($browser, $match)) {
		$cookie_help = $cookie_help[$match];
		$prettyName = $prettyName ? $prettyName : $match;
		$noCookieMessage .= "<p><div style='display:inline;position:relative;background:white;padding:10px;width:300px'><a href='$cookie_help'>Working with cookies in $prettyName</a></div>";
	}


// $noCookie cannot be tested for in IE the very first time, so we set $noCookie in login.php

$splash_msg = $noCookie ? $noCookieMessage : '';
if(!isset($lockChecked) || !$lockChecked) $splash_msg .= "Hey, the lock wasn't checked on this page!<p>";

$warningDivStart = "<center><div style='position:absolute;z-index:999;padding:100px;text-align:center;top:10px;left:10px;width:800px;height:600px;background:yellow;border: solid black 2px;font: bold 12pt Tahoma,Arial,Verdana;'>";
$welcomeDivStart = "<center><div style='position:absolute;z-index:999;padding:100px;text-align:center;top:10px;left:10px;width:800px;height:600px;background:white;border: double black 1px;font: bold 12pt Tahoma,Arial,Verdana;'>";
//phpinfo();exit;
if($splash_msg) {
	if($splash_msg == $noCookieMessage)  // cookie is only problem
		if($_SERVER["REQUEST_URI"] == '/' && !isset($_REQUEST['splashblockredirect']))
			$welcomeScript = "alert('$welcomeMessage');document.location.href='index.php?splashblockredirect=1';";
?>
<script language="javascript">
<?= $welcomeScript ? $welcomeScript : "document.write(\"$warningDivStart$splash_msg</div></center>\");" ?>
</script>
<?
	//if(!$welcomeScript) exit();
}
?>
<noscript>
<?= $warningDivStart.($splash_msg ? $splash_msg.'<p>and<p> ' : '') ?>
Javascript is disabled in your browser.  It must be enabled for you to use this site.
</div></center>
</noscript>
