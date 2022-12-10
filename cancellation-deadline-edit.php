<? // cancellation-deadline-edit.php
/* Params
none
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
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
extract(extractVars('cancellationDeadlineHours,cancellationDeadlineUnits,cancellationDeadlineWarning', $_REQUEST));

if($_POST) {
	setPreference('cancellationDeadlineHours', 
									$cancellationDeadlineHours * ($cancellationDeadlineUnits == 'days' ? 24 : 1) );
	setPreference('cancellationDeadlineUnits', $_POST['cancellationDeadlineUnits']);
	setPreference('cancellationDeadlineWarning', $_POST['cancellationDeadlineWarning']);
	echo "<script language='javascript'>window.close();</script>";
	exit;
}

$windowTitle = $_SESSION['preferences']['bizName'];
$windowTitle = ($windowTitle ? "$windowTitle's" : 'Your')." Visit Cancellation Preferences";;
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>";

?>

<?
echo "<form method='POST' name='prefeditor'>";
echo "<table>";
echo "<tr><td colspan=2>";
echoButton('', 'Save', 'saveForm()');
echo "</td><tr><td colspan=2>Any visit cancellation requests by clients must be made at least:</td></tr>";
$prefs = $_SESSION['preferences'];
$deadline = !$prefs['cancellationDeadlineHours'] 
		? ''
		: $prefs['cancellationDeadlineHours'] / ($prefs['cancellationDeadlineUnits'] == 'days' ? 24 : 1);
inputRow('Cancellation Deadline:', 'cancellationDeadlineHours', $deadline);
radioButtonRow('', 'cancellationDeadlineUnits', $prefs['cancellationDeadlineUnits'], 
								array('No deadline'=>'', 'Hours'=>'hours', 'Days ... in advance.'=>'days'), "hoursClicked(this)");
// labelRow('','','in advance.');
echo "<tr><td colspan=2>When a client requests a visit cancellation past this deadline, display this notification:</td></tr>";
countdownInputRow(300, 'Notice to client:', 'cancellationDeadlineWarning', $prefs['cancellationDeadlineWarning'], null, 'VeryLongInput',null,null,null,'underinput');
//We cannot guarantee visit cancellations less than 24 hours before the visit.
echo "</table>";
echo "</form>";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('x_login','Merchant Login','x_tran_key','Merchant Transaction Key');

function hoursClicked(el) {
	if(el.id == 'cancellationDeadlineUnits_') document.getElementById('cancellationDeadlineHours').value='';
}

function saveForm() {
	var msg = (!document.getElementById('cancellationDeadlineUnits_').checked && !jstrim(document.getElementById('cancellationDeadlineHours').value))
		? 'Cancellation deadline must be set or "No deadline" must be selected.' : '';
	var msg2 = (document.getElementById('cancellationDeadlineUnits_').checked && jstrim(document.getElementById('cancellationDeadlineHours').value))
		? 'You must choose Hours or Days if you specify a deadline.' : '';
  if(MM_validateForm(
  	'cancellationDeadlineHours', '', 'UNSIGNEDINT',
  	msg, '', 'MESSAGE',
  	msg2, '', 'MESSAGE',
  	'cancellationDeadlineWarning', 'cancellationDeadlineHours', 'RIFF'
  	
  )) {
		document.prefeditor.submit();
	}
}

function jstrim(str) {
	if(!str) return str;
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

</script>
