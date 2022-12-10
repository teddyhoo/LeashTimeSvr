<?
/* reminder-edit.php
*
* Parameters: 
* id - id of reminder to be edited
*
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "reminder-fns.php";
require_once "js-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
if(userRole() == 'd') $locked = locked('d-');
else $locked = locked('o-');

if($_REQUEST['rtype']) { // just return a remindertype as xml
	$rType = fetchFirstAssoc("SELECT * FROM tblremindertype WHERE remindertypeid = {$_REQUEST['rtype']}");
	echo "<rtype userid='{$rType['userid']}' sendon='{$rType['sendon']}' />"
				."<subject><![CDATA[{$rType['subject']}]]></subject>"
				."<message><![CDATA[{$rType['message']}]]></message></rtype>";
	exit;
}

extract(extractVars('id,subject,message,sendondom,sendondow,sendondate,sendannually,action,private,remindercode,client,provider,clientrequest', $_REQUEST));
if($action == 'saveReminder') {
	if($remindercode) {
		$rType = fetchFirstAssoc("SELECT * FROM tblremindertype WHERE remindertypeid = $remindercode");
		$reminder = array('subject'=>$rType['subject'], 'message'=>$rType['message'], 'sendon'=>$rType['sendon'], 
						'userid'=>$rType['userid'], 'remindercode'=>$remindercode);
	}
	else {$sendon = 
					$sendondom ? $sendondom : (
					$sendondow ? $sendondow : (
					$sendannually ? 'ann'.str_replace('-', '_', substr(date('Y-m-d', strtotime($sendannually)), strlen('YEAR-'))) :(
					date('Y-m-d', strtotime($sendondate)))));
		$reminder = array('subject'=>$subject, 'message'=>$message, 'sendon'=>$sendon, 
						'userid'=>($private ? $_SESSION['auth_user_id'] : 0));
	}
	if($client) $reminder['clientptr'] = $client;
	if($provider) $reminder['providerptr'] = $provider;
	if($id) {
		updateTable('tblreminder', $reminder, "reminderid = $id", 1);
	}
	else {
		insertTable('tblreminder', $reminder, 1);
	}
	require_once "request-fns.php";
	require_once "client-fns.php";
	require_once "comm-fns.php";
	sendReminders();
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "$id: ".print_r($reminder,1); exit; }	
	if($_REQUEST['pop']) echo "<script language='javascript'>window.close();</script>";
	else echo "<script language='javascript'>if(parent.update) parent.update('appointments', '$postReturn');parent.$.fn.colorbox.close();</script>";
	exit;
}
else if($action == 'deleteReminder') {
	deleteTable('tblreminder', "reminderid = $id", 1);
	if($_REQUEST['pop']) echo "<script language='javascript'>window.close();</script>";
	else echo "<script language='javascript'>if(parent.update) parent.update('appointments', '$postReturn');parent.$.fn.colorbox.close();</script>";
	exit;
}
else if($id) {
	$reminder = fetchFirstAssoc("SELECT * FROM tblreminder WHERE reminderid = $id LIMIT 1");
	if(!$reminder) $error = "Reminder #$id not found.";
	if($reminder['userid'] && $reminder['userid'] != $_SESSION['auth_user_id'])  $error = "Reminder #$id belongs to another user.";
	$client = $reminder['clientptr'];
	$provider = $reminder['providerptr'];
}
else if($clientrequest) {
	$clientrequest = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $clientrequest LIMIT 1");
	if($clientrequest['note']) $newmessage[] = $clientrequest['note'];
	if($clientrequest['officenotes']) $newmessage[] = shortDate().': '.$clientrequest['officenotes'];
	require_once "request-fns.php";
	$hiddenFields = getHiddenExtraFields($clientrequest);
	$reminder = array('userid'=>0,'remindercode'=>0,'subject'=>$clientrequest['street1'],
										'message'=>join("\n================\n", $newmessage),
										'clientptr'=>$hiddenFields['clientptr'],
										'providerptr'=>$hiddenFields['providerptr']);
	$client = $reminder['clientptr'];
	$provider = $reminder['providerptr'];
}


//$client = $_REQUEST['client'];
//if(!$client) $provider = $_REQUEST['provider'];

if($client) {
	$filter = "AND clientptr = $client";
	$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblclient WHERE clientid = $client LIMIT 1");
} 
else if($provider) {
	$filter = "AND providerptr = $provider";
	$person = fetchFirstAssoc("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tblprovider WHERE providerid = $provider LIMIT 1");
}

$operation = $id ? 'Edit' : 'Create';
$header = "$operation a Reminder".($person ? " about {$person['name']}" : "");

$windowTitle = $header;
$extraHeadContent = '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>';
require "frame-bannerless.php";	
echo "<h2>$header</h2>";
if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}

$filter = $client ? "restriction = 'client' OR restriction IS NULL" :
					($provider ? "restriction = 'sitter' OR restriction IS NULL" : '1=1');
$types = array('No Group'=>0);
foreach(fetchKeyValuePairs("SELECT label, remindertypeid FROM tblremindertype WHERE $filter ORDER BY label")
					as $k => $v) $types[$k] = $v;



$sendon = $reminder['sendon'];
if($sendon) $sendonType = 
	(int)$sendon > 31 ? 'sendondate' : (
	(int)$sendon ? 'sendondom' : (
	strpos("$sendon", 'ann') === 0 ? 'sendannually'
	: 'sendondow'));
//print_r($source);exit;
if($_SESSION["mobiledevice"] || $_SESSION["tabletdevice"]) {
	echoButton('', "Save Reminder", "checkAndSubmit(\"saveReminder\")");
	echo " ";
	$quitAction = $_REQUEST['pop'] ? 'window.close();' : 'parent.$.fn.colorbox.close();';
	echoButton('', "Quit", $quitAction);
	echo " ";
	if($id) echoButton('', "Delete Reminder", "checkAndSubmit(\"deleteReminder\")", 'HotButton', 'HotButtonDown');
}

echo "<form name='editreminder' method='POST'>";
hiddenElement('id', $id);
hiddenElement('provider', $provider);
hiddenElement('client', $client);
hiddenElement('action', '');
echo "<table>";
selectRow('Group reminder', 'remindercode', $reminder['remindercode'], $types, 'remindercodeChanged()', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
radioButtonRow('Reminder to', 'private', ($reminder['userid'] ? 1 : 0), array('Just me (private)'=>1, 'All Managers'=>0));
echo "<tr><td style='vertical-align:top;padding-top:8px;padding-left:2px;'>Send Reminder</td><td>";
labeledRadioButton('on Day of month', 'sendondomradio', 1, ($sendonType == 'sendondom' ? 1 : 0), 'sendonClicked(this)', $labelClass=null, $inputClass='sendonradio');
for($i=0; $i<32; $i++) $daysofmonth[($i ? $i : '')] = $i;
selectElement('', 'sendondom', $reminder['sendon'], $daysofmonth, $onChange=null, $labelClass=null, $inputClass='sendon', $noEcho=false);
echo "<br>";
labeledRadioButton('on Day of week', 'sendondowradio', 1, ($sendonType == 'sendondow' ? 1 : 0), 'sendonClicked(this)', $labelClass=null, $inputClass='sendonradio');
foreach(array('','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') as $d) $daysofweek[$d] = $d;
selectElement('', 'sendondow', $reminder['sendon'], $daysofweek, $onChange=null, $labelClass=null, $inputClass='sendon', $noEcho=false);
echo "<br>";
labeledRadioButton('on a particular date', 'sendondateradio', 1, ($sendonType == 'sendondate' ? 1 : 0), 'sendonClicked(this)', $labelClass=null, $inputClass='sendonradio');
echo " <div id='calendardiv' style='display:inline'>";
calendarSet('', 'sendondate', ($sendonType == 'sendondate' ? $reminder['sendon'] : ''), $labelClass=null, $inputClass='sendon', $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null);
echo "</div>";
if(staffOnlyTEST() || dbTEST('loveandkissespetsitting')) {
	echo "<br>";
	labeledRadioButton('each year on', 'sendannuallyradio', 1, ($sendonType == 'sendannually' ? 1 : 0), 'sendonClicked(this)', $labelClass=null, $inputClass='sendonradio');
	echo " <div id='annualcalendardiv' style='display:inline'>";
	$annuallyDate = strpos("$sendon", 'ann') === 0 ? date('Y').'-'.str_replace('_', '-', substr($sendon, strlen('ann'))) : '';
	calendarSet('', 'sendannually', $annuallyDate, $labelClass=null, $inputClass='sendon', $includeArrowWidgets=true, $secondDayName=null, $onChange='', $onFocus=null, $firstDayName=null);
	echo "</div>";
}
echo "</td></tr>";
countdownInputRow(100, 'Subject', 'subject', $reminder['subject'], $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');
//inputRow('Note:', 'message', ($reminder['message'] ? $reminder['message'] : $message), '', 'VeryLongInput');
textRow('Note:', 'message', ($reminder['message'] ? $reminder['message'] : $message), $rows=3, $cols=80, $labelClass=null, 'fontSize1_2em', $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null);

echo "</table>";
echo "</form><p>";
if(TRUE || (!$_SESSION["mobiledevice"] && !$_SESSION["tabletdevice"])) {
	echoButton('', "Save Reminder", "checkAndSubmit(\"saveReminder\")");
	echo " ";
	$quitAction = $_REQUEST['pop'] ? 'window.close();' : 'parent.$.fn.colorbox.close();';
	echoButton('', "Quit", $quitAction);
	echo " ";
	if($id) echoButton('', "Delete Reminder", "checkAndSubmit(\"deleteReminder\")", 'HotButton', 'HotButtonDown');
}
	//echo " ";
	//if($id && $charge['amountused'] == 0.0) echoButton('', "Delete Charge", 'deleteCharge()', 'HotButton', 'HotButtonDown');

?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

function deleteCharge() {
	if(confirm("Are you sure you want to delete this charge?\nClick Ok to delete the charge.")) {
		document.location.href='charge-edit.php?deleteCharge=<?= $id ?>';
		//window.close();
	}
}

setPrettynames('sendondate','Send On date', 'sendanually','Annual Send On date', 'subject', 'Subject', 'message', 'Message');	
	
function sendonClicked(el) {
	$('.sendon').attr('disabled', 'disabled');
	$('.sendonradio').attr('checked', false);
	$('#calendardiv').hide();
	$('#annualcalendardiv').hide();
	if(el) {
		$('#'+el.id).attr('checked', true);
		var inpname = el.id.substring(0,el.id.indexOf('radio'));
		$('#'+inpname).removeAttr('disabled');
		if(el.id.indexOf('sendondateradio') != -1) $('#calendardiv').show();
		if(el.id.indexOf('sendannuallyradio') != -1) $('#annualcalendardiv').show();
	}
}

function remindercodeChanged() {
	var sel = document.getElementById('remindercode');
	sel = sel.options[sel.selectedIndex].value;
	var on = sel != 0;
	if(on) ajaxGetAndCallWith('reminder-edit.php?rtype='+sel, refreshFields, on);
	else refreshFields(0,0);
}
	
function refreshFields(unused, resultxml) {
	var group = document.getElementById('remindercode');
	var onOff = group.selectedIndex == 0 ? 1 : 0;

	if(!onOff && resultxml) {
		var root = getDocumentFromXML(resultxml).documentElement;
		if(root.tagName == 'ERROR') {
			alert(root.nodeValue);
			return;
		}
		
		var subject, message;
		setInputValue('private', root.getAttribute('userid') > 0 ? 1 : 0);
		var sendon = parseInt(root.getAttribute('sendon'));
		var sendonVals = {'sendondate':'','sendondow':'','sendondom':''};
		if(sendon > 31) {
			sendonClicked(document.getElementById('sendondateradio'));
			sendon = root.getAttribute('sendon').split('-');
			sendonVals['sendondate'] = (sendon.length == 3 ? (sendon[1]+'/'+sendon[2]+'/'+sendon[0]) : sendon);
		}
		else if(sendon > 0) {
			sendonClicked(document.getElementById('sendondomradio'));
			sendonVals['sendondom'] = sendon;
		}
		else {
			sendonClicked(document.getElementById('sendondowradio'));
			sendonVals['sendondow'] = root.getAttribute('sendon');
		}
		setInputValue('sendondate', sendonVals['sendondate']);
		setInputValue('sendondom', sendonVals['sendondom']);
		setInputValue('sendondow', sendonVals['sendondow']);
		var nodes;
		nodes = root.getElementsByTagName('subject') ;
		if(nodes.length == 1)
			setInputValue('subject',  nodes[0].firstChild.nodeValue);
		nodes = root.getElementsByTagName('message') ;
		if(nodes.length == 1)
			setInputValue('message',  nodes[0].firstChild.nodeValue);
	}
	var els = document.getElementsByTagName('input');
	for(var i=0;i<els.length;i++)
		if(els[i].type != 'button' && els[i].type != 'hidden' && els[i].id != 'remindercode') 
			els[i].disabled = !onOff;
	var els = document.getElementsByTagName('textarea');
	for(var i=0;i<els.length;i++)
		els[i].disabled = !onOff;
}

$(document).ready(function() {
	sendonClicked($('.sendonradio:checked').get(0));
	refreshFields(0,0);
})

function checkAndSubmit(action) {
	if(action == 'deleteReminder' && confirm("Sure you want to delete this reminder?")) {
		document.getElementById('action').value=action;
		document.editreminder.submit();
		return;
	}
	var noSendon = !document.getElementById('sendondom').disabled && document.getElementById('sendondom').selectedIndex 
		|| !document.getElementById('sendondow').disabled && document.getElementById('sendondow').selectedIndex 
		|| !document.getElementById('sendondate').disabled && document.getElementById('sendondate').value
		|| !document.getElementById('sendannually').disabled && document.getElementById('sendannually').value;
	noSendon = noSendon ? null : 'When to send reminder must be specified.';
	
	var group = document.getElementById('remindercode');
//alert(action);		
	if(group.selectedIndex > 0 || MM_validateForm(
		'private', '', 'R',
		noSendon, '', 'MESSAGE',
		'subject', '', 'R',
		'message', '', 'R',
		'sendondate', '', 'isDate',
		'sendannually', '', 'isDate'
		)) {
		document.getElementById('action').value=action;
		document.editreminder.submit();
		//window.close();
	}
}
<?
dumpPopCalendarJS();
?>
</script>
</body>
</html>
