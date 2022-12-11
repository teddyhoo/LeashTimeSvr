<? // client-own-echedule-request.php
// simple request form tailored to requesting services
// alternative to client-sched-makerV2.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "request-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";

$locked = locked('c-');//locked('o-'); 
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract($_REQUEST);
$pop = $_REQUEST['pop'];

$id = $_SESSION["clientid"] ? $_SESSION["clientid"] : ($roDispatcher ? $_REQUEST['id'] : '');
$client = getClient($id);

$error = null;

if($_POST) {
	if(!saveNewGenericRequest($_POST, $id)) $error = mysqli_error();
	if($pop && !$error) {
		echo "<script language='javascript'>if(window.opener.update) window.opener.showFrameMsg('Request has been sent.');window.close();</script>";
		exit;
	}
	$acknowledgment = 
		$_SESSION['preferences']['clientOnscreenRequestAcknowledgment']
			? $_SESSION['preferences']['clientOnscreenRequestAcknowledgment']
			: "Thank you for submitting your request.<p>We will act on it as soon as possible.";
	$message = "$acknowledgment<p><a href='index.php'>Home</a>";
}

if($pop) {
	$windowTitle = "Request Visits";
	include "frame-bannerless.php";
	echo "<h2>Request Visits</h2>";
}
else {
	$pageTitle = "Request Visits";
	if($_SESSION["responsiveClient"]) {
		$extraHeadContent = "
		<style>
		body {font-size:1.2em;} 
		.leashtime-content {font-size:1.0em;}
		td {font-size:1.0em;}  /* 1.8 /
		input.Button {font-size:1.0em;} /* 2.8 /
		</style>";
		include "frame-client-responsive.html";
		$frameEndURL = "frame-client-responsive-end.html";
	}
	else {
		include "frame-client.html";
		$frameEndURL = "frame-end.html";
	}
}
// ***************************************************************************

if($error) echo "<font color='red'>$error</font>";
if($message) {
	echo $message;
	if(!$pop) include $frameEndURL;
	exit;
}


?>
<form method='POST' name='clientrequestform'>
<table>

<?
if($client['fname2'] || $client['lname2']) {
	echo "<tr><td>";
	// ask whether it is client1 or client 2 making the request
	$names = array(displayName($client['fname'], $client['lname']), displayName($client['fname2'], $client['lname2']));
	$values = array(commaName($client['fname'], $client['lname']), commaName($client['fname2'], $client['lname2']));
	echo "Your name:</td><td>";
	labeledRadioButton($names[0], 'clientname', $values[0], $values[0], 'setName(this)');
	echo " ";
	labeledRadioButton($names[1], 'clientname', $values[1], $values[0], 'setName(this)');
	echo "</td></tr>";
}
hiddenElement('fname', $client['fname']);
hiddenElement('lname', $client['lname']);
hiddenElement('x-simpleschedule', "1");
$details = getOneClientsDetails($id, array('phone'));
inputRow('Phone', 'phone', $details['phone']);
inputRow('Best time for us to call', 'whentocall', '', '', 'emailInput');
labelRow('Please tell us what services you would like from us, and when.', '');
?>
<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=80><?= stripslashes($note) ?></textarea></td></tr>
</table>
<?
echoButton('', 'Send Request', 'checkAndSend()');
?>
</form>
<?
function displayName($fname, $lname) {
	return $fname.($fname ? ' ' : '').$lname;
}
	
function commaName($fname, $lname) {
	return "$lname,$fname";
}
?>
<script language='javascript'>
function setName(el) {
	var names = el.value.split(',');
	document.clientrequestform.fname = names[0];
	document.clientrequestform.lname = names[1];
}

function checkAndSend() {
  if(jstrim(document.clientrequestform.note.value) == '')
     alert('Please write something in the note field.');
  else document.clientrequestform.submit();
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}


</script>
<?
// ***************************************************************************
if(!$pop) include $frameEndURL;
?>
