<? //agreement-sign.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "agreement-fns.php";

$goHome = false;
$frameEndURL = $frameEndURL ? $frameEndURL : "frame-end.html";

if($_POST) {
	if($_POST['iagree']) {

		// IDEA: preserve agreement history with:
		$dt = date('Y-m-d H:i:s'); 
		replaceTable('tbluserpref', array('userptr'=>$_SESSION["auth_user_id"], 'property'=>"agreement_$dt", 'value'=>$_POST['agreementptr']), 1);
		
		// ... and make sure previous version is saved in the history...
		require_once "agreement-fns.php";
		$currentAgreement = clientAgreementSigned($_SESSION["auth_user_id"]);
		if($currentAgreement &&
				!fetchFirstAssoc(
					"SELECT property, value 
						FROM tbluserpref 
						WHERE userptr = {$_SESSION["auth_user_id"]} AND property LIKE 'agreement_{$currentAgreement['agreementdate']}' LIMIT 1", 1))
			replaceTable('tbluserpref', array('userptr'=>$_SESSION["auth_user_id"], 'property'=>"agreement_{$currentAgreement['agreementdate']}", 'value'=>$currentAgreement['agreementid']), 1);
		
		// elsewhere: "SELECT property, value FROM tbluserpref WHERE userptr = $userId AND property LIKE 'agreement_%'"
		///    to report on agreement history

		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		include "common/init_db_common.php";
		updateTable('tbluser', array('agreementdate'=>$dt, 'agreementptr'=>$_POST['agreementptr']), "userid = {$_SESSION["auth_user_id"]}");
//echo "user: {$_SESSION["auth_user_id"]}	vals: ".print_r(array(date('Y-m-d H:i:s'), "agreementptr: {$_POST['agreementptr']}"),1);exit;
		list($dbhost, $db, $dbuser, $dbpass) = array($dbhost1, $db1, $dbuser1, $dbpass1);
		include "common/init_db_petbiz.php";
		$goHome = "Thank you!<p>You may <a href='index.php'>Proceed</a>";
		$message = $goHome;
		$_SESSION['clientAgreementRequired'] = 0;
	}
	else {
		$message = "Your agreement to these terms is required before you can proceed.";
		$moveOn = false;
	}
}
$action = 'agreement-sign.php';
// frame start has already been output

if($goHome) {
	if($_POST['requestedURL']) {
		$_SESSION['frame_message'] = "Thanks!";
		$destination = $_POST['requestedURL'];
	}
	else $destination = "index.php";
	header("Location: ".globalURL($destination));
	exit;
}

if($message) {
	$color = $goHome ? 'green' : 'red';
	echo "<font color='$color'><h2>$message</h2></font>";
	if($moveOn) {
		include $frameEndURL;
		exit;
	}
}

$agreement = getCurrentServiceAgreement();
if($agreement && $agreement['agreementid']) {  // if none defined, just breeze through
$agreementTerms = filterString($agreement['terms']);

// Check to see if user is required to sign because the version has changed (test is: user has signed before)
list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
include "common/init_db_common.php";
$newAgreement = fetchRow0Col0("SELECT agreementptr FROM tbluser WHERE userid = {$_SESSION["auth_user_id"]} LIMIT 1");
reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
$agreementNote = $newAgreement 
	? '<b>The service agreement has been changed.</b> Please review the terms of this agreement and check the box marked "I agree" before proceeding:'
	: 'Please review this agreement and check the box marked "I agree" before proceeding:';
	
?>
<form name='clientagreement' method='POST' action='<?= $action ?>'>
<? 
	hiddenElement('agreementptr', $agreement['agreementid']); 
	if(strpos($_SERVER["REQUEST_URI"], 'agreement-sign.php') === FALSE) 
		hiddenElement('requestedURL', $_SERVER["REQUEST_URI"]); ?>

<h2>Service Agreement</h2>
<?= $agreementNote ?>
<p>
<div style='background:#eeeeee;border: solid black 1px;padding:5px;overflow:auto;height:400px;'>
<? // $agreement['html'] ? $agreementTerms : htmlizeAgreementText($agreementTerms) ?>
<? $ucase = strtoupper($agreementTerms);
	 if(strpos($ucase, '<P>') == FALSE && strpos($ucase, '<BR>') == FALSE) echo htmlizeAgreementText($agreementTerms);
	 else echo $agreementTerms;
?>
<p>
<span style='font-style:italic;font-size:80%'>Agreement version dated: <?= shortDateAndTime(strtotime($agreement['date'])) ?></span>
</div>
<?
labeledCheckBox('I agree', 'iagree', 0, null, null, null, true);
//labeledRadioButton('I agree', 'iagree', 1, null);
echo " ";
echoButton('', 'Proceed', 'checkAndSubmit()');
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('iagree','Your agreement');
function checkAndSubmit() {
	var msg = document.getElementById('iagree').checked ? '' : 'Your agreement is required.';
	if(MM_validateForm(
		msg, '', 'MESSAGE'
		)) {
		document.clientagreement.submit();
	}
}
</script>



<?
// ***************************************************************************

include $frameEndURL;
exit;
}