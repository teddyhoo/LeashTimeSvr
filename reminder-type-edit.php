<?
/* reminder-type-edit.php
*
* Parameters: 
* id - id of reminder type to be edited
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

extract(extractVars('id,label,subject,message,sendondom,sendondow,sendondate,sendannually,action,private,restriction', $_REQUEST));
if($action == 'saveReminder') {
	$sendon = 
		$sendondom ? $sendondom : (
		$sendondow ? $sendondow : (
		$sendannually ? 'ann'.str_replace('-', '_', substr(date('Y-m-d', strtotime($sendannually)), strlen('YEAR-'))) : (
		date('Y-m-d', strtotime($sendondate)))));
}
	$reminder = array('subject'=>$subject, 'message'=>$message, 'label'=>$label, 'sendon'=>$sendon,
					'userid'=>($private ? $_SESSION['auth_user_id'] : 0), 'restriction'=>$restriction);
	if($id) {
		updateTable('tblremindertype', $reminder, "remindertypeid = $id", 1);
		unset($reminder['label']);
		unset($reminder['restriction']);
		updateTable('tblreminder', $reminder, "remindercode = $id", 1);
	}
	else {
		insertTable('tblremindertype', $reminder, 1);
	}
	echo "<script language='javascript'>if(parent.update) parent.update('appointments', '$postReturn');parent.$.fn.colorbox.close();</script>";
	exit;
}
else if($action == 'deleteReminder') {
	deleteTable('tblremindertype', "remindertypeid = $id", 1);
	deleteTable('tblreminder', "remindercode = $id", 1);
	echo "<script language='javascript'>if(parent.update) parent.update('appointments', '$postReturn');parent.$.fn.colorbox.close();</script>";
	exit;
}

else if($id) {
	$reminder = fetchFirstAssoc("SELECT * FROM tblremindertype WHERE remindertypeid = $id LIMIT 1");
	if(!$reminder) $error = "Reminder #$id not found.";
	if($reminder['userid'] && $reminder['userid'] != $_SESSION['auth_user_id'])  $error = "Reminder #$id belongs to another user.";
}

$operation = $id ? 'Edit' : 'Create';
$header = "$operation a Group Reminder";
$scheduledReminders = fetchRow0Col0("SELECT count(*) FROM tblreminder WHERE remindercode = $id LIMIT 1");
foreach(fetchKeyValuePairs("SELECT remindertypeid, label FROM tblremindertype") as $rtid => $label) 
	$reminderTypes[] = "'".md5($label)."':$rtid";
$reminderTypes = join(',', (array)$reminderTypes);
$windowTitle = $header;
$extraHeadContent = '<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>';
require "frame-bannerless.php";	

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
echo "<h2>$header</h2>";
if($id) echo "Number of reminders of this type currently scheduled: $scheduledReminders<p>";
$sendon = $reminder['sendon'];
//if($sendon) $sendonType = (int)$sendon > 31 ? 'sendondate' : ((int)$sendon ? 'sendondom' : 'sendondow');
if($sendon) $sendonType = 
	(int)$sendon > 31 ? 'sendondate' : (
	(int)$sendon ? 'sendondom' : (
	strpos("$sendon", 'ann') === 0 ? 'sendannually'
	: 'sendondow'));
//print_r($source);exit;
echo "<form name='editreminder' method='POST'>";
hiddenElement('id', $id);
hiddenElement('action', '');
echo "<table>";
countdownInputRow(100, 'Label', 'label', $reminder['label'], $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');
radioButtonRow('Reminder to', 'private', ($reminder['userid'] ? 1 : 0), array('Just me (private)'=>1, 'All Managers'=>0));
radioButtonRow('Reminder subjects may include', 'restriction', $reminder['restriction'], array('Clients'=>'client', 'Sitters'=>'sitter', 'Both'=>''));
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

if(!$id) $message = "
<ul>
 #NAMELOOP#
<li>#NAME#
#ENDNAMELOOP#
</ul>";
textRow('Note: (the #NAMELOOP# and #NAME# tags indicate where and how the list of names will appear)', 'message', ($reminder['message'] ? $reminder['message'] : $message), $rows=7, $cols=80, $labelClass=null, 'fontSize1_2em', $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null);



echo "</table>";
echo "</form><p>";
echoButton('', "Save Reminder", "checkAndSubmit(\"saveReminder\")");
echo " ";
echoButton('', "Quit", 'parent.$.fn.colorbox.close();');
echo " ";
if($id) echoButton('', "Delete Reminder", "checkAndSubmit(\"deleteReminder\")", 'HotButton', 'HotButtonDown');
	//echo " ";
	//if($id && $charge['amountused'] == 0.0) echoButton('', "Delete Charge", 'deleteCharge()', 'HotButton', 'HotButtonDown');

?>
</div>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='rsa.js'></script>
<script language='javascript'>

function labelNotUnique() {
	var labels = { <?= $reminderTypes ?>};
	label = document.getElementById('label').value;
	var label = label ? hex_md5(label) : '';
	if(label != '' && labels[label] && labels[label] != <?= $id ? $id : '0' ?>)
		return "This label is already in use for another group reminder.";
}

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
	
$(document).ready(function() {sendonClicked($('.sendonradio:checked').get(0));})

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
	var uniqueLabel = labelNotUnique();
	if(MM_validateForm(
		'label', '', 'R',
		uniqueLabel, '', 'MESSAGE',
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
