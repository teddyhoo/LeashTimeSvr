<? // prospect-request-form-custom.php
require_once "common/init_session.php";
require_once "gui-fns.php";

initializeMeetingStuff();

if(!($bizid = $_GET['bizid'])) { echo "No business ID supplied."; exit; }
else {
	require_once('common/init_db_common.php');
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid' LIMIT 1");
	if(!$biz)  { echo "No business found for ID supplied."; exit; }
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
	$template = prospectTemplate($_GET['templateid']);
	if(!$template)  { echo "Template [{$_GET['templateid']}] not found."; exit; }
	$bizHomePage = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizHomePage' LIMIT 1");
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$form = str_replace('#BIZID#', $bizid, $template);
	$form = str_replace('#HOMEPAGE#', $bizHomePage, $form);
	$bizLink = $bizHomePage ? "<a href='$bizHomePage'>$bizName</a>" : $bizName;
	$form = str_replace('#BIZNAME#', $bizLink, $form);
	
	$_SESSION["uidirectory"] = "bizfiles/biz_$bizid/clientui/";
	if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$bizid/";
	$suppressMenu = 1;
	include "frame-client.html";
	echo "<div style='font-size:1.1em;'>";
	echo $form;
	echo "</div>";
	$noCommentButton = true;
	include "frame-end.html";

}

function prospectTemplate($templateid) {
	
	global $meetingHTML, $meetingScriptIncludes, $meetingScriptFragment;
	$spacer = "<img src='art/spacer.gif' width=1 height=100>";
	$suppressMeetingFieldsInProspectForm = 
		fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'suppressMeetingFieldsInProspectForm' LIMIT 1");
	
	if($suppressMeetingFieldsInProspectForm) {
		$meetingHTML=null; $meetingScriptIncludes=null; $meetingScriptFragment=null; $spacer = null;
	}
	if($_REQUEST['twoColumns']) {
		$columnBreak1 = "</table></td><td style='vertical-align:top;padding-left:20px;'><table>";
		$columnBreak2 = "</table></td>";
	}

	$templateid = $templateid ? $templateid : 'simple';
	if($templateid == 'simple') return "
<center>
Please fill in as much (or as little) of the following fields to ask us a question, 
pass on a comment, request a service, or to just say hello!
<p>
All we ask is that you enter a phone number or email address where we can reach you.
<p>
Thanks for your interest in #BIZNAME#!
<p>
      
<form method='POST' name='prospectinforequest' action='https://{$_SERVER["HTTP_HOST"]}/prospect-request.php'>
<input type='hidden' id='pbid' name='pbid' value='#BIZID#'> <!-- Please insert your LeashTime biz ID where it says '1'. -->
<input type='hidden' id='goback' name='goback' value='#HOMEPAGE#'> <!-- Please supply the address to return to after the message is sent. -->
<table><tr><td valign=top>
<table>
<tr><td><label for='fname'>First Name:</label></td><td><input id='fname' name='fname' maxlength=45></td></tr>
<tr><td><label for='lname'>Last Name:</label></td><td><input id='lname' name='lname' maxlength=45></td></tr>
<tr><td><label for='phone'>Phone:</label></td><td><input id='phone' name='phone' maxlength=45></td></tr>
<tr><td><label for='whentocall'>Best time for us to call:</label></td><td><input id='whentocall' name='whentocall' maxlength=45></td></tr>
<tr><td><label for='email'>Email:</label></td><td><input id='email' name='email' maxlength=60></td></tr>
<tr><td><label for='address'>Address:</label></td><td><textarea id='address' name='address' rows=4 cols=20></textarea></td></tr>
$columnBreak1
<tr><td colspan=2><label for='pets'>Tell us about your pets (names, kind):</label></td></tr>
<tr><td colspan=2><textarea id='pets' name='pets' rows=3 cols=40></textarea></td></tr>
<tr><td colspan=2><label for='note'>How can we help you?</label></td></tr>
<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=40></textarea></td></tr>
$meetingHTML
<tr><td colspan=2><input class='Button' type=button value='Send Request' onClick='checkAndSend()'></td></tr>
</table>
$columnBreak2
</td>
</tr>
</table>
$spacer
$meetingScriptIncludes
<script language='javascript'>
function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);

}

function checkAndSend() {
  if(!validEmail(document.prospectinforequest.email.value) &&
     jstrim(document.prospectinforequest.phone.value) == '')
     alert('Please supply an email address or phone number where we can reach you.');
  else document.prospectinforequest.submit();
}

$meetingScriptFragment
</script>
";	

	if($templateid == 'fulladdress') return "
<center>
Please fill in as much (or as little) of the following fields to ask us a question, 
pass on a comment, request a service, or to just say hello!
<p>
All we ask is that you enter a phone number or email address where we can reach you.
<p>
Thanks for your interest in #BIZNAME#!
<p>
      
<!-- prospect-request-form.html -->
<form method='POST' name='prospectinforequest' action='https://{$_SERVER["HTTP_HOST"]}/prospect-request.php'>
<input type='hidden' id='pbid' name='pbid' value='#BIZID#'>
<input type='hidden' id='goback' name='goback' value='#HOMEPAGE#'>
<table><tr><td valign=top>
<table>
<tr><td><label for='fname'>First Name:</label></td><td><input id='fname' name='fname' maxlength=45></td></tr>
<tr><td><label for='lname'>Last Name:</label></td><td><input id='lname' name='lname' maxlength=45></td></tr>
<tr><td><label for='phone'>Phone:</label></td><td><input id='phone' name='phone' maxlength=45></td></tr>
<tr><td><label for='whentocall'>Best time for us to call:</label></td><td><input id='whentocall' name='whentocall' maxlength=45></td></tr>
<tr><td><label for='email'>Email:</label></td><td><input id='email' name='email' maxlength=60></td></tr>

<tr><td><label for='street1'>Address:</label></td><td><input id='street1' name='street1' maxlength=60></td></tr>
<tr><td><label for='street2'>Address 2:</label></td><td><input id='street2' name='street2' maxlength=60></td></tr>
<tr><td><label for='city'>City:</label></td><td><input id='city' name='city' maxlength=60></td></tr>
<tr><td><label for='state'>State:</label></td><td><input id='state' name='state' maxlength=60></td></tr>
<tr><td><label for='zip'>ZIP:</label></td><td><input id='zip' name='zip' maxlength=60></td></tr>
$columnBreak1
<tr><td colspan=2><label for='pets'>Tell us about your pets (names, kind):</label></td></tr>
<tr><td colspan=2><textarea id='pets' name='pets' rows=3 cols=40></textarea></td></tr>
<tr><td colspan=2><label for='note'>How can we help you?</label></td></tr>
<tr><td colspan=2><textarea id='note' name='note' rows=4 cols=40></textarea></td></tr>
$meetingHTML
<tr><td colspan=2><input class='Button' type=button value='Send Request' onClick='checkAndSend()'></td></tr>
</table>
$columnBreak2
</td>
</tr>
</table>
$spacer
$meetingScriptIncludes
<script language='javascript'>
function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);

}

function checkAndSend() {
  if(!validEmail(document.prospectinforequest.email.value) &&
     jstrim(document.prospectinforequest.phone.value) == '')
     alert('Please supply an email address or phone number where we can reach you.');
  else document.prospectinforequest.submit();
}

$meetingScriptFragment
</script>
";	
}

function initializeMeetingStuff() {
	global $meetingHTML, $meetingScriptIncludes, $meetingScriptFragment;


$meetingHTML = <<<HTML
<tr><td colspan=2>Would you like to schedule a meeting with us? <input name='meet' id='yesmeet' type='radio' onChange='toggleMeeting(this)'> <label for='yesmeet'>yes</label> <input id='nomeet' name='meet' type='radio' onclick='toggleMeeting(this)' CHECKED> <label for='nomeet'>no</label></td></tr>
<tr id='meetingdatetr0' style='display:none'><td colspan=2>Please tell us when is convenient for you:</td></tr>
<tr id='meetingdatetr1' style='display:none'>
<td>
Date: <input DISABLED class="dateInput" id="meetingdate1" name="x-oneline-meetingdate1" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
			<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
					onclick='dateButtonAction(this,document.getElementById("meetingdate1"),"1","15","2005")'>
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime1" name="x-oneline-meetingtime1" size=10>
</td>
</tr>
<tr id='meetingdatetr2' style='display:none'>
<td>
Date: <input DISABLED class="dateInput" id="meetingdate2" name="x-oneline-meetingdate2" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
			<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
					onclick='dateButtonAction(this,document.getElementById("meetingdate2"),"1","15","2005")'>
</td>
<td style='padding-left:5px;'>
 at what time? <input DISABLED id="meetingtime2" name="x-oneline-meetingtime2" size=10>
</td>
</tr>
<tr id='meetingdatetr3' style='display:none'>
<td>
Date: <input DISABLED class="dateInput" id="meetingdate3" name="x-oneline-meetingdate3" autocomplete="off" size=12 
					value='' onFocus='if(this.value=="Click there ===>") this.value="";'> 
			<img src="https://{$_SERVER["HTTP_HOST"]}/art/popcalendar.gif" 
					onclick='dateButtonAction(this,document.getElementById("meetingdate3"),"1","15","2005")'>
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

if($_REQUEST['require']) { // eventually, gather required fields from prefs
	$required = explode(',', $_REQUEST['require']);
	$allowed = explodePairsLine('fname|First Name||lname|Last Name||phone|Phoe||email|Email||address|Address||'
															.'pets|Pets||note|Note||street1|Address||street2|Address 2||city|City||state|State||zip|ZIP');
	foreach($required as $fld) {
		if($allowed[$fld]) {
			$requiredFieldJavaScript .= "args[args.length] = '$fld';\n";
			$requiredFieldJavaScript .= "args[args.length] = '';\n";
			$requiredFieldJavaScript .= "args[args.length] = 'R'\n";
			$prettyNames[] = "'$fld', '{$allowed[$fld]}'";
		}
		else $badNames[]= $fld;
	}
	if($prettyNames) $requiredFieldJavaScript .= "setPrettynames(".join(', ', $prettyNames).")\n";
	if($badNames) 
		$badNames = "document.write(\"<p style='color:red'>Warning: required fields are unknown: ".join(', ', $badNames)."</p>\");";
}



require_once('common/init_db_common.php');
$petbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$_GET['bizid']}' LIMIT 1");
$_SESSION["i18nfile"] = getI18NPropertyFile($petbiz["country"]);
$localDateFormat = getI18Property('popupcalendarformat', $default='mm/dd/yyyy');




$meetingScriptFragment = <<<HTML3
//  Javascript for the Meeting section
function validEmail(src) {
  var regex = /^[a-zA-Z0-9._%+-`'`]+@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,4}$/;  // checks for ' character
  return regex.test(src);
}

function checkAndSend() {
	setPrettynames('meetingdate', 'Meeting Date', 'meetingtime', 'Meeting Time');
	var mincontactmsg, args;
	var args = new Array();	
	$requiredFieldJavaScript
  if(!validEmail(document.prospectinforequest.email.value) &&
     jstrim(document.prospectinforequest.phone.value) == '') {
		 args[args.length] = 'Please supply an email address or phone number where we can reach you.';
		 args[args.length] = '';
		 args[args.length] = 'MESSAGE';
	}
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
  if(MM_validateFormArgs(args)) document.prospectinforequest.submit();
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

var localDateFormat = '$localDateFormat';

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

$badNames

//  END Javascript for the Meeting section
HTML3;

}