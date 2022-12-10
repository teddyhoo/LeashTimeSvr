<? //eula-sign.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "eula-fns.php";
require_once "gui-fns.php";

$goHome = false;
if($_POST) {
	if($_POST['iagree']) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		include "common/init_db_common.php";
		signEULA($_POST['eulaid']);
		list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
		include "common/init_db_petbiz.php";
		$goHome = "Thank you!<p>You may <a href='index.php'>Proceed</a>";
		$message = $goHome;
		$_SESSION['eulaSignatureRequired'] = 0;
	}
	else {
		$message = "Your agreement to these terms is required before you can proceed.";
		$moveOn = false;
	}
}

$action = 'eula-sign.php';
// frame start has already been output

if($goHome) {
	header("Location: ".globalURL("index.php"));
	exit;
}

if($message) {
	$color = $goHome ? 'green' : 'red';
	echo "<font color='$color'><h2>$message</h2></font>";
	if($moveOn) {
		include "frame-end.html";
		exit;
	}
}

list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
include "common/init_db_common.php";
$agreement = getBizEULA(null);
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);

if($agreement && $agreement['eulaid']) {  // if none defined, just breeze through
$agreementTerms = filterString($agreement['terms']);

?>
<form name='clientagreement' method='POST' action='<?= $action ?>'>
<? hiddenElement('eulaid', $agreement['eulaid']); ?>
<h2>Leashtime End User License Agreement</h2>
Please review this agreement and check the box marked "I agree" before proceeding:
<p>
<div style='background:#eeeeee;border: solid black 1px;padding:5px;overflow:auto;height:400px;'>
<?= $agreement['html'] ? $agreementTerms : htmlizeEULA($agreementTerms) ?>
<p>
<span style='font-style:italic;font-size:80%'>Agreement version dated: <?= shortDateAndTime(strtotime($agreement['date'])) ?></span>
</div>
<?
labeledCheckBox('I agree', 'iagree', 0, null, null, null, true);
//labeledRadioButton('I agree', 'iagree', 1, null);
echo " ";
echoButton('', 'Proceed', 'checkAndSubmit()');
echo " or ";
echoButton('', 'I Decline', 'logout()');
echo " ";
if($agreement['pdfurl']) fauxLink('Print this agreement', "openConsoleWindow(\"EULA\", \"agreements/{$agreement['pdfurl']}\")");
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('iagree','Your agreement');
function logout() {
	document.location.href='login-page.php?logout=1';
}

function checkAndSubmit() {
	var msg = document.getElementById('iagree').checked ? '' : 'Your agreement is required.';
	if(MM_validateForm(
		msg, '', 'MESSAGE'
		)) {
		document.clientagreement.submit();
	}
}
</script>
<script language='javascript' src='common.js'></script>



<?
// ***************************************************************************

include "frame-end.html";
exit;
}