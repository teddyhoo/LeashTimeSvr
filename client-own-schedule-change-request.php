<? // client-own-schedule-change-request.php
// simple request form tailored to requesting services

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "request-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";

$locked = locked('c-');//locked('o-'); 
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');


/*
Show details of the visits to change/cancel/uncancel
(Offer a per-visit note line?)
Offer the request note field.
[Send Request]

BORROW CODE FROM client-own-schedule-change.php to create the request


*/

extract($_REQUEST);
$pop = $_REQUEST['pop'];

$clientid = $_SESSION["clientid"] ? $_SESSION["clientid"] : ($roDispatcher ? $_REQUEST['id'] : '');
$client = getClient($clientid);

$error = null;
$thankYouUserNoticeHTML = 
	$_SESSION['preferences']['clientOnscreenRequestAcknowledgment']
	? $_SESSION['preferences']['clientOnscreenRequestAcknowledgment']
	: "Thank you for submitting your request.<p>We will act on it as soon as possible.";
	
$labels = array('cancel'=>'Cancellation', 'uncancel'=>'UnCancellation', 'change'=>'Change');
$verbs = array('cancel'=>'Cancel', 'uncancel'=>'Unancel', 'change'=>'Change');
if($lightbox) {
	echo //$extraHeadContent = 
		"<style>body {font-size:1.2em;} .tiplooks {font-size:14pt;}</style>"
		.'<script src="responsiveclient/assets/js/libs/jquery/jquery-1.11.2.min.js"></script>';
}
else if($_SESSION["responsiveClient"]) {
	$extraHeadContent = "<style>body {font-size:1.2em;} .tiplooks {font-size:14pt;}</style>";
	$frameStartURL = "frame-client-responsive.html";
	$frameEndURL = "frame-client-responsive-end.html";
}
else if($pop) {
	$frameStartURL = "frame-bannerless.php";
}
else if(!$pop) {
	$frameStartURL = "frame-client.html";
	$frameEndURL = "frame-end.html";
}
if($_GET['thankyou']) {
	$message = "$thankYouUserNoticeHTML<p><a href='index.php'>Home</a>";
	if($pop) {
		include $frameStartURL;
		echo "<h2>$windowTitle</h2>";
	}
	else {
		$pageTitle = $windowTitle;
		include $frameStartURL;
	}
	// ***************************************************************************

	if($error) echo "<font color='red'>$error</font>";
	if($message) {
		echo $message;
		if(!$pop) include "$frameEndURL";
		exit;
	}
}

$windowTitle = "Request {$labels[$op]} of Visits";
$extraHeadContent = '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>';
if($pop) {
	include $frameStartURL;
	echo "<h2>$windowTitle</h2>";
}
else if($lightbox) {
	// we are in a light box
}
else {
	$pageTitle = $windowTitle;
	include $frameStartURL;
}
// ***************************************************************************

if($error) echo "<font color='red'>$error</font>";
if($message) {
	echo $message;
	if($frameEndURL) include $frameEndURL;
	exit;
}


?>
<form method='POST' name='clientrequestform'>
<table>

<? 
echo "<tr><td>";
$appts = fetchAssociations("SELECT * FROM tblappointment WHERE appointmentid IN ($ids) ORDER BY date, starttime", 1); 
if($lightbox) {
	$pluralizer = count($appts) > 1 ? 's' : '';
	echo "{$verbs[$op]} ".count($appts)." visit$pluralizer?<td><tr><td>";
	echoButton('', 'Proceed', 'checkAndSend()');
	echo "</td><td style='text-align:right;'>";
	$backAction = 'lightBoxIFrameClose()';
	echoButton('', 'Quit', $backAction);
	echo "</td></tr>";
	$noteboxDetails = "rows=4 cols=40";

}
else {
	echoButton('', 'Send Request', 'checkAndSend()');
	echo "</td><td style='text-align:right;'>";
	$backAction = $back ? "document.location.href=\"$back\"" : 'window.history.back();';
	echoButton('', '&#x2039; Back', $backAction);
	echo "</td></tr>";
	$noteboxDetails = "rows=4 cols=80";
}

if($client['fname2'] || $client['lname2']) {
	// ask whether it is client1 or client 2 making the request
	$names = array(displayName($client['fname'], $client['lname']), displayName($client['fname2'], $client['lname2']));
	$values = array(commaName($client['fname'], $client['lname']), commaName($client['fname2'], $client['lname2']));
	if($lightbox) {	
		echo "<tr><td>I am: ";
		labeledRadioButton($names[0], 'clientname', $values[0], $values[0], 'setName(this)');
		echo " ";
		labeledRadioButton($names[1], 'clientname', $values[1], $values[0], 'setName(this)');
		echo "</td></tr>";
	}
	else {
		echo "<tr><td>";
		echo "Your name:</td><td>";
		labeledRadioButton($names[0], 'clientname', $values[0], $values[0], 'setName(this)');
		echo " ";
		labeledRadioButton($names[1], 'clientname', $values[1], $values[0], 'setName(this)');
		echo "</td></tr>";
	}
}
hiddenElement('fname', $client['fname']);
hiddenElement('lname', $client['lname']);
hiddenElement('operation', $op);
hiddenElement('back', $back);
hiddenElement('successUserNotice', $thankYouUserNoticeHTML); // will become $_SESSION['user_notice']


labelRow('Comments or instructions', '');
?>
<tr><td colspan=2><textarea class='requestorchangenote' id='note' name='note' <?= $noteboxDetails ?>><?= stripslashes($note) ?></textarea></td></tr>
<?
require_once "request-safety.php";
$theseVisits = count($appts) == 1 ? "this visit" : "these ".count($appts)." visits";
if($lightbox) {
	foreach($appts as $appt) {
		hiddenElement("visit_{$appt['appointmentid']}", $appt['appointmentid']);
		echo "\n";
	}
}
else {
	echo "<tr><td colspan=2>You selected $theseVisits to <span class='bold fontSize1_2em'>{$verbs[$op]}</span></td></tr>";
	echo "<tr><td colspan=2>";
	clientScheduleChangeDetailEditTable($appts, $includeNoteInputs=false, $op);
	echo "</td></tr>";
}




//$details = getOneClientsDetails($id, array('phone'));
//inputRow('Phone', 'phone', $details['phone']);
//inputRow('Best time for us to call', 'whentocall', '', '', 'emailInput');
?>
</table>
</form>
<?
function displayName($fname, $lname) {
	return $fname.($fname ? ' ' : '').$lname;
}
	
function commaName($fname, $lname) {
	return "$lname,$fname";
}

if($lightbox) echo '<script src="lightbox-layer-featherlight.js"></script>'."\n";
?>
<script language='javascript' src='check-form.js'></script>

<script language='javascript'>
setPrettynames('note','Which Visits','action','Change or Cancel');
function checkAndSend() {
<? if($op == 'change') { // require at least one note to describe the change ?>
	var noNote = true;
	$('.requestorchangenote').each(function(index, el) {if(trim(el.value) != '') noNote = false;});
	if(noNote) {
		alert('Please give us some idea of what changes you want.');
		return;
	}
<? } ?>
  // POST TO client-own-schedule-change-json.php instead document.clientrequestform.submit();
  //alert(JSON.stringify(buildJSONRequest()));
	$.ajax({
	    url: 'client-own-schedule-change-json.php',
	    dataType: 'json', // comment this out to see script errors in the console
	    type: 'post',
	    contentType: 'application/json',
	    data: JSON.stringify(buildJSONRequest()),
	    processData: false,
	    success: submitSucceeded,
	    error: submitFailed // until I figure this out...Figured it out! ?>
	    });
}

function buildJSONRequest() {
	// fname -- hidden
	// lname -- hidden
	// visit_9082309, value = 9082309, visit_9082310, value = 9082310, ...
	// visitnote_9082309, value = "...", visit_9082310, value = "...", ...
	var request = {
		fname:$('#fname').val(), 
		lname:$('#lname').val(), 
		visits:[], 
		groupnote:$('#note').val(),
		changetype:$('#operation').val(),
		successUserNotice:$('#successUserNotice').val()
		};
		
	$('input[type="hidden"]').each(function(index, el) {
		let visitid;
		if(el.id.indexOf('visit_') == 0) {
			visitid = el.id.split('_');
			visitid = visitid[1];
			request.visits.push({id:visitid, note: $('#visitnote_'+visitid).val()});
		}
	});
	return request;
}

function setName(el) {
	var names = el.value.split(',');
	document.clientrequestform.fname.value = names[1];
	document.clientrequestform.lname.value = names[0];
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}


function submitSucceeded(data, textStatus, jQxhr) {
	<?= mattOnlyTEST() ? 'console.log(data);console.log(textStatus);console.log(jQxhr);' : "console.log('schedule json submitted.');" ?>
	<? if($lightbox) { ?>
		window.parent.update('actioncomplete', $('#operation').val());
	<? } else { ?>
	document.location.href="?thankyou=1";
	<? } ?>
}

function submitFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'
		+<?= mattOnlyTEST() ? 'errorThrown' : '"Please notify support."' ?>;
	console.log(message );
	<?= mattOnlyTEST() ? 'console.log("jqXhr: "+jqXhr);console.log("textStatus: "+textStatus);' : '' ?>
}

<? if($_SESSION["responsiveClient"]) { ?>
var contentWidthQuery = window.matchMedia("(max-width: 650px)");
// Attach listener function on state changes
contentWidthQuery.addListener(setFormWidth);

function setFormWidth(query) {
	var narrow = query.matches;
	if(narrow) {//alert('narrow');
		$('textarea.requestorchangenote').attr('cols',null);
		//$('textarea.requestorchangenote').css('width:400px;');
		$('textarea.requestorchangenote').attr('cols',50);
		$('input.requestorchangenote').removeClass('VeryLongInput');
		$('input.requestorchangenote').width(350);
		//alert('textarea: '+$('textarea.requestorchangenote').css('width')+' input: '+$('input.requestorchangenote').css('width'));
	}
	else {//alert('wide');
		// cols=80, requestorchangenote 400px
		$('textarea.requestorchangenote').css('width:null;');
		$('textarea.requestorchangenote').attr('cols',80);
	$('input.requestorchangenote').addClass('VeryLongInput');
	}
} 
<? } ?>


</script>
<?
// ***************************************************************************
$onLoadFragments[] = 'setFormWidth(contentWidthQuery);'; // alert('boop');
if($frameEndURL) include "$frameEndURL";
?>
