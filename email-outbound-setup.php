<?  // email-outbound-setup.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require "email-fns.php";
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

if($_REQUEST['backup']) {
	$liveSettings = fetchKeyValuePairs(
		"SELECT * FROM tblpreference 
			WHERE property IN 
				('emailFromAddress','emailHost','smtpPort',
					'smtpSecureConnection', 'emailUser', 'emailPassword')");/*, */
	foreach($liveSettings as $k => $v)
		setPreference("BACKUP$k", $v);
	$message = "Settings backed up.";
}		

if($_REQUEST['restore']) {
$backupSettings = fetchKeyValuePairs(
	"SELECT * FROM tblpreference 
		WHERE property IN 
			('BACKUPemailFromAddress','BACKUPemailHost','BACKUPsmtpPort',
				'BACKUPsmtpSecureConnection', 'BACKUPemailUser', 'BACKUPemailPassword')");
	foreach($backupSettings as $k => $v)
		setPreference(substr($k, strlen('BACKUP')), $v);
	$message = "Settings restored.";
}		

$status = getSMTPStatus();
$internalSMTP = $_SESSION['preferences']['emailHost'] ? '0' : '1';
$options = array("Use LeashTime's SMTP Server"=>'1', "Use an External SMTP Server"=>'0');

if($_POST) {
	if($_POST['internalSMTP']) {
		foreach(explode(',', 'emailHost,smtpPort,smtpSecureConnection,emailUser,emailPassword') as $setting)
			setPreference($setting, null);
		echo "<script language='javascript'>window.opener.updateProperty(\"emailFromAddress\", null);window.close();</script>";
		exit;
	}
	globalRedirect('email-send-address-pref-edit.php');
}

$windowTitle = "Outbound Email Setup";;
require "frame-bannerless.php";
?>
<h2 style='padding-top:0px;'><?= $windowTitle ?></h2>
<?
$emailPrefs = "emailFromAddress|Sender Email Address|custom|email-send-address-pref-edit.php||".
							"emailBCC|CC sent mail to|string||emailHost|SMTP (Outbound eMail) Host|string||".
							"smtpPort|SMTP Port|string||".//smtpAuthentication|Use SMTP Authenication|boolean||".
							"smtpSecureConnection|Use Secure Connection|picklist|no,tls,ssl,sslv2,sslv3||".
							"emailUser|User Name|string||emailPassword|Password|password";											

if($message) echo "<span class='tiplooks'>$message</span><p>";

?>
Current Status: <?= $status ?>
<p>
<form name='propertyeditor' method='POST'>
<?
hiddenElement('go',1);
hiddenElement('action','');
$radios = radioButtonSet('internalSMTP', $internalSMTP, $options, 'radioClick(this)', $labelClass=null, $inputClass=null);
foreach($radios as $radio) echo "$radio ";
echo "<p><div id='notice'></div><p>";
echoButton('', 'Save', 	'document.propertyeditor.submit()');
echo " ";
echoButton('', 'Quit', 'window.close()');
$backupSettings = fetchKeyValuePairs(
	"SELECT * FROM tblpreference 
		WHERE property IN 
			('BACKUPemailFromAddress','BACKUPemailHost','BACKUPsmtpPort',
				'BACKUPsmtpSecureConnection', 'BACKUPemailUser')");/*, 'BACKUPemailPassword'*/
$liveSettings = fetchKeyValuePairs(
	"SELECT * FROM tblpreference 
		WHERE property IN 
			('emailFromAddress','emailHost','smtpPort',
				'smtpSecureConnection', 'emailUser')");/*, 'emailPassword'*/
if(mattOnlyTEST()) { 
	echo "<hr>";echoButton('', 'Backup', 'backupSettings()');
	if($backupSettings) {
		echo " ";
		echoButton('', 'Restore', 'restoreSettings()');
		echo "<table width=100% border=1><tr><th>Setting<th>Live<th>Backed Up</tr>";
		foreach($liveSettings as $k => $v)
			echo "<tr><td>$k<td>$v<td>{$backupSettings['BACKUP'.$k]}<tr>";
		echo "</table>";
	}
}
?>
</form>
<script language='javascript'>
function backupSettings() {
	if(confirm('Backup settings?'))
		document.location.href='email-outbound-setup.php?backup=1';
}

function restoreSettings() {
	if(confirm('Backup settings?'))
		document.location.href='email-outbound-setup.php?restore=1';
}

function radioClick(el) {
	document.getElementById('notice').innerHTML = 
		el.value == 1 ? 'NOTE: All of your email settings except Sender, CC, and Reply-to email addresses will be cleared.'
		: 'Please assemble your SMTP email settings:<ul>'
			+'<li>Sender Email Address<li>SMTP (Outbound eMail) Host<li>SMTP Port<li>Secure Connection (ssl, tls, etc)'
			+'<li>Email User Name (may be your email address, or may not)<li>Password'
			+'</ul>and then click Save to start entering settings for your own SMTP Server account.';
}
</script>