<? // client-own-request.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "request-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";

$locked = locked('c-');//locked('o-'); 
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

$USE_DATEPICKER = $_SESSION["responsiveClient"];

extract($_REQUEST);
$pop = $_REQUEST['pop'];

$id = $_SESSION["clientid"] ? $_SESSION["clientid"] : ($roDispatcher ? $_REQUEST['id'] : '');
$client = getClient($id);

$error = null;

if($_POST) {
	if(!saveNewGenericRequest($_POST, $id)) $error = mysql_error();
	if($pop && !$error) {
		echo "<script language='javascript'>if(window.opener.update) window.opener.showFrameMsg('Request has been sent.');window.close();</script>";
		exit;
	}
	if($mobileclient) {
		$_SESSION['popup_message'] = 'Request has been sent.';
		echo "<script language='javascript'>document.location.href='index.php';</script>";
		exit;
	}
	$acknowledgment = 
		$_SESSION['preferences']['clientOnscreenRequestAcknowledgment']
			? $_SESSION['preferences']['clientOnscreenRequestAcknowledgment']
			: "Thank you for submitting your request.<p>We will act on it as soon as possible.";
	$message = "$acknowledgment<p><a href='index.php'>Home</a>";
}

$noteCols = 80;
if($mobileclient) {
	$noteCols = 40;
	//$extraBodyStyle = 'font-size:1.1em;';
	$customStyles = "
h2 {font-size:2.5em;} 
td {font-size:1.5em;} 
.standardInput {font-size:1.5em;}
.emailInput {font-size:1.5em;}
/*input:radio {font-size:1.5em;} */
label {font-size:1.5em;} 
.mobileLabel {font-size:2.0em;} 
.mobileInput {font-size:2.0em;}
textarea {font-size:1.2em;}
input.Button {font-size:2.0em;}
input.ButtonDown {font-size:2.0em;}
";
}
if($pop || $mobileclient) {
	$windowTitle = "Submit a Request";
	include "frame-bannerless.php";
	echo "<h2>Submit a Request</h2>";
}
else {
	$pageTitle = "Submit a Request";
	if($_SESSION["responsiveClient"]) {
		$pageTitle = "<i class=\"fa fa-fw fa-envelope\"></i> Submit a Request";
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

$spacer = "<img src='art/spacer.gif' width=1 height=145>";
initializeMeetingStuff();




?>
<form method='POST' name='clientrequestform'>
<table width=100%>

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
$details = getOneClientsDetails($id, array('phone'));
inputRow('Phone', 'phone', $details['phone']);
inputRow('Best time for us to call', 'whentocall', '', '', 'emailInput');
labelRow('How can we help you?', '');
?>
<tr><td colspan=2><textarea id='note' name='note' rows=4 
		colsX=<?= $noteCols ?>
		style='width:100%;'
		><?= stripslashes($note) ?></textarea></td></tr>
<?
echo $meetingHTML;

echoButton('', 'Send Request', 'checkAndSend()');

if($mobileclient) {
	echo "<img src='art/spacer.gif' width=10 height=1>";
	echoButton('', "Quit", "document.location.href=\"index.php?date={$source['date']}\"");
}

?>
</table>
</form>

<?
echo $spacer;

function displayName($fname, $lname) {
	return $fname.($fname ? ' ' : '').$lname;
}
	
function commaName($fname, $lname) {
	return "$lname,$fname";
}

//#######################################
echo $meetingScriptIncludes;
?>
<script language='javascript'>
<?
if($USE_DATEPICKER) {
	require_once "js-gui-fns.php";
	dumpJQueryDatePickerJS();
}

?>
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

<?= $meetingScriptFragment // overrides previous checkAndSend() ?>
</script>
<?
// ***************************************************************************
$onLoadFragments[] = "initializeCalendarImageWidgets();";
if(!$pop) include $frameEndURL;

function meetingDateElements($i) {
	$name = "meetingdate$i";
	$s = "<label for='$name'>Date:</label>";
	global $USE_DATEPICKER;
	if($USE_DATEPICKER)
	$s .= "<input DISABLED class='dateInput calendarwidget ' id='$name' name='x-oneline-$name' value=''  autocomplete='off' onFocus='this.select();' autocomplete='off'> ";
	else
	$s .= "<input DISABLED class='dateInput' id='$name' name='x-oneline-$name' autocomplete='off' size=12 
					value='' onFocus='if(this.value==\"Click there ===>\") this.value=\"\";'>
					<img src='https://{$_SERVER['HTTP_HOST']}/art/popcalendar.gif' 
					onclick='dateButtonAction(this,document.getElementById(\"meetingdate1\"),\"1\",\"15\",\"2005\")'>
				";
	return $s;
}

function initializeMeetingStuff() {
	global $meetingHTML, $meetingScriptIncludes, $meetingScriptFragment;
	$date1Elements = meetingDateElements(1);
	$date2Elements = meetingDateElements(2);
	$date3Elements = meetingDateElements(3);
	if($_SESSION['preferences']['suppressMeetingFieldsInGeneralRequestForm']) $meetingHTML = '';
	else $meetingHTML = <<<HTML
<tr><td colspan=2>Would you like to schedule a meeting with us? <input name='meet' id='yesmeet' type='radio' onChange='toggleMeeting(this)'> <label for='yesmeet'>yes</label> <input id='nomeet' name='meet' type='radio' onclick='toggleMeeting(this)' CHECKED> <label for='nomeet'>no</label></td></tr>
<tr id='meetingdatetr0' style='display:none'><td colspan=2>Please tell us when is convenient for you:</td></tr>
<tr id='meetingdatetr1' style='display:none'>
<td>
$date1Elements
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime1" name="x-oneline-meetingtime1" size=10>
</td>
</tr>
<tr id='meetingdatetr2' style='display:none'>
<td>
$date2Elements
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime2" name="x-oneline-meetingtime2" size=10>
</td>
</tr>
<tr id='meetingdatetr3' style='display:none'>
<td>
$date3Elements
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime3" name="x-oneline-meetingtime3" size=10>
</td>
</tr>
HTML;

$meetingScriptIncludes = <<<HTML2
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/popcalendar.js'></script>
<script language='javascript' src='https://{$_SERVER["HTTP_HOST"]}/check-form.js'></script>
HTML2;

$meetingScriptFragment = <<<HTML3
//  Javascript for the Meeting section
function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);

}

function checkAndSend() {
	setPrettynames('meetingdate1', 'Meeting Date #1', 'meetingtime1', 'Meeting Time #1', 
									'meetingdate2', 'Meeting Date #2', 'meetingtime2', 'Meeting Time #2', 
									'meetingdate3', 'Meeting Date #3', 'meetingtime3', 'Meeting Time #3', 
									'note', 'Note');
	var mincontactmsg, args;
	document.clientrequestform.note.value = jstrim(document.clientrequestform.note.value);
	var args = new Array('note', '', 'R');
	if(document.getElementById("yesmeet")) {
		if(document.getElementById("yesmeet").checked &&
				!(jstrim(document.getElementById("meetingdate1").value) 
				|| jstrim(document.getElementById("meetingdate2").value) 
				|| jstrim(document.getElementById("meetingdate3").value))) {
			 args[args.length] = 'At least one meeting date is required if you want a meeting.';
			 args[args.length] = '';
			 args[args.length] = 'MESSAGE';
		}
		if(document.getElementById("yesmeet")) {
			args[args.length] = 'meetingdate1';
			args[args.length] = '';
			args[args.length] = 'isDate';
			args[args.length] = 'meetingdate1';
			args[args.length] = 'NOT';
			args[args.length] = 'isPastDate';
			args[args.length] = 'meetingdate2';
			args[args.length] = '';
			args[args.length] = 'isDate';
			args[args.length] = 'meetingdate2';
			args[args.length] = 'NOT';
			args[args.length] = 'isPastDate';
			args[args.length] = 'meetingdate3';
			args[args.length] = '';
			args[args.length] = 'isDate';
			args[args.length] = 'meetingdate3';
			args[args.length] = 'NOT';
			args[args.length] = 'isPastDate';
			args[args.length] = 'meetingtime1';
			args[args.length] = 'meetingdate1';
			args[args.length] = 'RIFF';
			args[args.length] = 'meetingtime2';
			args[args.length] = 'meetingdate2';
			args[args.length] = 'RIFF';
			args[args.length] = 'meetingtime3';
			args[args.length] = 'meetingdate3';
			args[args.length] = 'RIFF';
		}
	}
  if(MM_validateFormArgs(args)) document.clientrequestform.submit();
}

function toggleMeeting(el) {
	var rowShow = navigator.appName.indexOf('Internet Explorer') >= 0 ? 'block' : 'table-row';
	var display = el.id == 'yesmeet' ? rowShow : 'none';
	var disabled = el.id == 'nomeet';
	document.getElementById("meetingdatetr0").style.display = display;
	document.getElementById("meetingdatetr1").style.display = display;
	document.getElementById("meetingdatetr2").style.display = display;
	document.getElementById("meetingdatetr3").style.display = display;
	document.getElementById("meetingdate1").disabled = disabled;
	document.getElementById("meetingtime1").disabled = disabled;
	document.getElementById("meetingdate2").disabled = disabled;
	document.getElementById("meetingtime2").disabled = disabled;
	document.getElementById("meetingdate3").disabled = disabled;
	document.getElementById("meetingtime3").disabled = disabled;
}

var localDateFormat = 'mm/dd/yyyy';

function dateButtonAction(ctl, date, month, day, year) {
  var datePosition = getAbsolutePosition(document.getElementById(date.id));
  var offset = addOffsets(getTabPageOffset(date), getContainerOffset(date, 'Sheet'), getContainerOffset(date, 'contentLayout'));
  showCalendar(ctl, date, localDateFormat,"en",1,datePosition.x-offset.x, datePosition.y-offset.y);
}

function getAbsolutePosition(element) {
    var r = { x: element.offsetLeft, y: element.offsetTop };
    if (element.offsetParent) {
      var tmp = getAbsolutePosition(element.offsetParent);
      r.x += tmp.x;
      r.y += tmp.y;
    }
    return r;
}

function addOffsets() {
	var r = {x: 0, y: 0};
	for(var i=0; i < addOffsets.arguments.length; i++) {
		r.x += addOffsets.arguments[i].x;
		r.y += addOffsets.arguments[i].y;
	}
	return r;
}

function getTabPageOffset(element) {
	// since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
	while(element.offsetParent) {
		var parent = element.offsetParent;
    if(parent.id && parent.id.indexOf('tabpage_') == 0) return {x: parent.offsetLeft, y: parent.offsetTop-23}; // 23 for tab height
    element = parent;
	}
	return {x: 0, y: 0};
}

function getContainerOffset(element, containerClassName) {
	// since tabs hide divs, the Y coord is thrown off by their hidden heights.  return their heights.
	while(element.offsetParent) {
		var parent = element.offsetParent;
    if(parent.className == containerClassName) return {x: parent.offsetLeft, y: parent.offsetTop};
    element = parent;
	}
	return {x: 0, y: 0};
}
//  END Javascript for the Meeting section
HTML3;

}
