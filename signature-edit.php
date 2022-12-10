<? // signature-edit.php

/* Params
none
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
require_once "comm-fns.php";

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
extract(extractVars('html,ajax', $_REQUEST));




if($ajax) {
	echo processRawSig($html);
	exit;
}

if($_POST) {
	setPreference('composerSignature',  $html);
	echo "<script language='javascript'>window.opener.updateProperty(\"composerSignature\", null);window.close();</script>";
	exit;
}

$windowTitle = "Email Composer Signature";;
require "frame-bannerless.php";

?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<?
echo "<style>.textbox {font-size:1.2em;}</style>";
$composersig = getPreference('composerSignature');
$composersig = $composersig ? $composersig : "===\n#NAME#\n#BIZNAME#\nPhone: #PHONE#\n#HOMEPAGE#";
textRow($windowTitle, 'html', $composersig, $rows=7, $cols=80, null, 'textbox');
?>
<ul>
<li>You can use HTML tags here such as &lt;b&gt; and &lt;i&gt;.
<li>Don&apos;t use  &lt;p&gt; or &lt;br&gt;.  We convert double line-ends to &lt;p&gt; and single line-ends to &lt;br&gt;
<li>You can also use these substitution tokens:<br>
#LOGO#, #PHONE#, #FAX#, #EMAIL#, #HOMEPAGE#, #BIZNAME#, #ADDRESS#,<br>
#STREET1#, #STREET2#, #CITY#, #STATE#, #ZIP#
</ul>
<?
echoButton('', 'Preview Email Composer Signature Below', 'refreshPrettyDisplay()');
echo "<div id='prettydisplay' style='display:block;border: solid black 1px;background:white;'>";
processRawSig($composersig);
echo "</div><p>";
echoButton('', 'Save', 'document.propertyeditor.submit()');
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</form>";
?>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
function refreshPrettyDisplay() {
	ajaxGet('signature-edit.php?ajax=1&html='+escape(document.getElementById('html').value), 'prettydisplay');
}

</script>
