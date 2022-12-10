<? // are-popups-enabled.php
if($_GET['testpop']) {
	echo "<h2>Pop-ups are enabled for LeashTime</h2>";
	exit;
}

$instructions = array(
'safari'=>
'<h3 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.375em; vertical-align: baseline; font-family: Georgia; font-weight: normal; line-height: 1.375em; text-align: left; color: #01256e;">Safari 5.x/6.x/7.x (OS X)</h3>
<p style="margin: 1em 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; line-height: 1.5em; color: #666666;">Safari for OS X has no per-website control over blocking pop-ups windows. Pop-ups are either blocked, or they are not. To allow pop-ups:</p>
<h4 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.0625em; vertical-align: baseline; font-family: Helvetica; font-weight: bold; line-height: 1.5em; text-align: left; color: #01256e; text-transform: uppercase;">SAFARI 5.X</h4>
<ol style="margin: 0px 0px 0px 17px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; list-style: decimal; line-height: 1.5em; color: #666666;">
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">From the Safari menu, ensure the<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Block pop-up windows</strong><span class="Apple-converted-space">&nbsp;</span>option is not checked. Unchecking this option will allow pop-ups.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">To block pop-ups once again, check<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Block pop-up windows</strong><span class="Apple-converted-space">&nbsp;</span>in the Safari menu. You can use a keyboard shortcut: shift-[command key]-K.</li>
</ol>
<h4 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.0625em; vertical-align: baseline; font-family: Helvetica; font-weight: bold; line-height: 1.5em; text-align: left; color: #01256e; text-transform: uppercase;">SAFARI 6.X/7.X</h4>
<ol style="margin: 0px 0px 0px 17px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; list-style: decimal; line-height: 1.5em; color: #666666;">
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">From the Safari menu, choose<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Preferences...</strong><span class="Apple-converted-space">&nbsp;</span>and click the<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Security</strong><span class="Apple-converted-space">&nbsp;</span>tab.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Ensure the<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Block pop-up windows</strong><span class="Apple-converted-space">&nbsp;</span>option is not checked. Unchecking this option will allow pop-ups.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">To block pop-ups once again, check the<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Block pop-up windows</strong><span class="Apple-converted-space">&nbsp;</span>checkbox.</li>
</ol>',
'chrome'=>
'<h3 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.375em; vertical-align: baseline; font-family: Georgia; font-weight: normal; line-height: 1.375em; text-align: left; color: #01256e;">Chrome .current (Windows/OS X)</h3>
<ol style="margin: 0px 0px 0px 17px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; list-style: decimal; line-height: 1.5em; color: #666666;">
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Choose <b>Settings</b> in the Chrome menu.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Show advanced settings</strong> (at the bottom).</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">In the Privacy section, click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Content settings</strong>... . The Content settings window appears.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">In the Pop-ups section, click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Manage exceptions</strong>... .</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Enter <strong>leashtime.com</strong> and choose <strong>Allow </strong>for the Behavior.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Close any remaining dialog boxes.</li>
</ol>',
'firefox'=>
'<h3 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.375em; vertical-align: baseline; font-family: Georgia; font-weight: normal; line-height: 1.375em; text-align: left; color: #01256e;">Firefox .current (Windows/OS X)</h3>
<ol style="margin: 0px 0px 0px 17px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; list-style: decimal; line-height: 1.5em; color: #666666;">
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">In Windows: click Alt-T to display the Tools menu and select<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Options...</strong><span class="Apple-converted-space">&nbsp;</span> <br /> or<span class="Apple-converted-space"> on the Mac: click Command-comma (Command+,)&nbsp; top open </span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Preferences...</strong><span class="Apple-converted-space"> <br /> </span>The Options (Windows) or General (OS X) dialog box opens.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">In the top panel of the dialog box, click on the Content icon to display the Content dialog box.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">In the Content dialog box, ensure the<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Block pop-up windows</strong><span class="Apple-converted-space">&nbsp;</span>checkbox is selected, then click the adjacent<strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;"> Exceptions.</strong>.. button.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Enter <strong>leashtime.com</strong> and click the <strong>Allow</strong> button.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Close any remaining dialog boxes.</li>
</ol>',
'ie8'=>
'<h3 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.375em; vertical-align: baseline; font-family: Georgia; font-weight: normal; line-height: 1.375em; text-align: left; color: #01256e;">Internet Explorer 8.0 (Windows 7/Vista/XP)</h3>
<p style="margin: 1em 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; line-height: 1.5em; color: #666666;">When a website attempts to launch a new pop-up window, you may see dialog boxes alerting you of pop-up windows that have been blocked. Follow the instructions below to allow pop-up windows on a per-website basis.</p>
<ol style="margin: 0px 0px 0px 17px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; list-style: decimal; line-height: 1.5em; color: #666666;">
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">From the Tools menu, select Pop-up Blocker &rarr; Pop-up Blocker Settings. The Pop-up Blocker Settings dialog box opens.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Enter <strong>leashtime.com</strong>.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Add</strong>. The selected website is added to the list of Allowed sites.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Close</strong><span class="Apple-converted-space">&nbsp;</span>to close the Pop-up Blocker Settings dialog box.</li>
</ol>',
'ie9'=>
'<h3 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.375em; vertical-align: baseline; font-family: Georgia; font-weight: normal; line-height: 1.375em; text-align: left; color: #01256e;">Internet Explorer 9.0 (Windows 7/Vista)</h3>
<p style="margin: 1em 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; line-height: 1.5em; color: #666666;">When a website attempts to launch a new pop-up window, you may see dialog boxes alerting you of pop-up windows that have been blocked. Follow the instructions below to allow pop-up windows on a per-website basis.</p>
<ol style="margin: 0px 0px 0px 17px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; list-style: decimal; line-height: 1.5em; color: #666666;">
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">From the Tools menu (the gear icon on the far right), select Internet options. The Internet Options dialog box opens.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Click on the Privacy tab.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Under Pop-up Blocker, click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Settings</strong>. The Pop-up Blocker Settings dialog box opens.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Enter <strong>leashtime.com</strong>.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Add</strong>. The selected website is added to the list of Allowed sites.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">For more information about pop-ups, see Learn more about Pop-up Blocker, located at the bottom of the dialog box.</li>
<li style="margin: 0px 0px 0px 1.42857em; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; -webkit-transform: translateZ(0px);">Click<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">Close</strong><span class="Apple-converted-space">&nbsp;</span>to close the Pop-up Blocker Settings dialog box and click the<span class="Apple-converted-space">&nbsp;</span><strong style="margin: 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline;">OK</strong><span class="Apple-converted-space">&nbsp;</span>button to close the Internet Options dialog box.</li>
</ol>',
'unknown'=>
'<h3 style="margin: 7px 0px; padding: 0px; border: 0px; font-size: 1.375em; vertical-align: baseline; font-family: Georgia; font-weight: normal; line-height: 1.375em; text-align: left; color: #01256e;">No instructions found for this browser.</h3>
<p style="margin: 1em 0px; padding: 0px; border: 0px; font-size: 13px; vertical-align: baseline; line-height: 1.5em; color: #666666;">Please consult your browser&apos;s Help system or user manual for instructions about managing Pop Up windows.</p>'
);
$agent = $_SERVER["HTTP_USER_AGENT"];
$browserKey =
	strpos($agent, 'Chrome') ? 'chrome' : (
	strpos($agent, 'Safari') ? 'safari' : (
	strpos($agent, 'Firefox') ? 'firefox' : (
	strpos($agent, 'MSIE 8') ? 'ie8' : (
	strpos($agent, 'MSIE 9') ? 'ie9' : (
	strpos($agent, 'MSIE 10') ? 'ie9' : 'unknown')))));

$lockChecked = 1;
$publicPage = 1;
$noBannerLogo = 1;
$siteName = "Are Pop-Ups enabled?";

require_once "common/init_session.php";

require "frame.html";
?>
<h2>Pop-up Window Test</h2>
<p class='fontSize1_2em'>Use this page to determine whether your browser is set to allow pop-ups in LeashTime.</p>
<p class='fontSize1_2em'>Click <a href="are-popups-enabled.php?test=1"><span class='fontSize1_2em'>Test Pop-Ups</span></a></p>
<p class='fontSize1_2em'>If no window pops up when you click the link above, then follow these instructions:</p>
<?
echo $instructions[$browserKey];
echo "<p>Your browser: $agent";
?>

<script language='javascript'>
<? if($_GET['test']) echo "openConsoleWindow('poptest','are-popups-enabled.php?testpop=1',500,150);"; ?>
function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(!w || typeof w == 'undefined') alert('Pop-ups are disabled, for LeashTime at least.\nPlease see instructions below.');
  w.document.location.href=url;
  if(w) w.focus();
}
</script>
<?
$noCommentButton = 1;
require "frame-end.html";
 


