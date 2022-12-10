<? // invoice-preview-header-edit.php

/* Params
none
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "prepayment-fns.php";
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
extract(extractVars('html,ajax', $_REQUEST));

if($ajax) {
	dumpBusinessLogoDiv(null, $html, true);
	exit;
}

if($_GET['reset']) {
	setPreference('emailedInvoicePreviewHeader',  null);
	/* $preferences is a global used in dumpBusinessLogoDiv.  Force a refresh  */
	$preferences = null;
	$message = 'Header has been reset to its original state.';
}
if($_POST) {
	setPreference('emailedInvoicePreviewHeader',  $html);  //
	echo "<script language='javascript'>window.opener.updateProperty(\"emailedInvoicePreviewHeader\", null);window.close();</script>";
	exit;
}

$invoiceHeader = getPreference('emailedInvoicePreviewHeader');
if(!$invoiceHeader) $invoiceHeader = generateDefaultBusinessLogoDivContentsForInvoicePreview(null, $raw=true);
$windowTitle = "Invoice Preview Header";;
require "frame-bannerless.php";
if($message) echo "<span class='tiplooks'>$message</span><p>";
?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<form name='propertyeditor' method='POST'>
<?
echo "<style>.textbox {font-size:1.2em;}</style>";
textRow('Invoice Preview Header', 'html', $invoiceHeader, $rows=7, $cols=80, null, 'textbox');
?>
<ul>
<li>You can use HTML tags here such as &lt;b&gt; and &lt;i&gt;.
<li>Don&apos;t use  &lt;p&gt; or &lt;br&gt;.  We convert double line-ends to &lt;p&gt; and single line-ends to &lt;br&gt;
<li>You can also use these substitution tokens:<br>
#LOGO#, #PHONE#, #FAX#, #EMAIL#, #HOMEPAGE#, #BIZNAME#, #ADDRESS#,<br>
#STREET1#, #STREET2#, #CITY#, #STATE#, #ZIP#
</ul>
<?
echo "<a href='invoice-preview-header-edit.php?reset=1' id='resetlink'>The HTML of ths header may be corrupt.  Click here to reset the header to its original state<p></a>";
echoButton('', 'See how it will look below', 'refreshHeader()');
echo "<div id='headerdisplay' style='display:block;border: solid black 1px;background:white;'>";
dumpBusinessLogoDiv(null, null, true, 999);
//dumpBusinessLogoDiv($amountDue, $html=null, $preview=false, $clientid)
echo "</div><p>";
echoButton('savebutton', 'Save', 'document.propertyeditor.submit()');
echo " ";
echoButton('', "Quit", 'window.close()');
echo "</form>";
?>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
function refreshHeader() {
	ajaxGet('invoice-preview-header-edit.php?ajax=1&html='+escape(document.getElementById('html').value), 'headerdisplay');
}

if(document.getElementById('savebutton')) document.getElementById('resetlink').style.display='none';
</script>