<? // custom-biz-links.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');


$bizId = $_GET['bizId'];
$bizname = fetchRow0Col0("SELECT bizname FROM tblpetbiz WHERE bizid = $bizId");
echo 
"<h2>$bizname Links</h2>
<table border=1 bordercolor=black style='font-size:1.2em'>
<tr><td>Custom login:<br> <a target=mockuptab href='https://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid=$bizId'>https://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid=$bizId</a>
</td></tr>
<tr><td>Custom login with Mobile Tag:<br><a target=mockuptab href='https://{$_SERVER["HTTP_HOST"]}/login-page.php?qrcode=1&amp;bizid=$bizId'>https://{$_SERVER["HTTP_HOST"]}/login-page.php?qrcode=1&amp;bizid=$bizId</a>
</td></tr>
<tr><td>Prospective Client, separate address fields:<br> <a target=mockuptab href='https://{$_SERVER["HTTP_HOST"]}/prospect-request-form-custom.php?templateid=fulladdress&amp;bizid=$bizId'>https://{$_SERVER["HTTP_HOST"]}/prospect-request-form-custom.php?templateid=fulladdress&amp;bizid=$bizId</a>
<tr><td>&nbsp;</td></tr>
<tr><td><span style='font-size:1.2em'>Client UI MOCKUP</span>:<br> <a target=mockuptab href='https://{$_SERVER["HTTP_HOST"]}/client-ui-mockup.php?bizid=$bizId'>https://{$_SERVER["HTTP_HOST"]}/client-ui-mockup.php?bizid=$bizId</a>
</td></tr>
</table>";