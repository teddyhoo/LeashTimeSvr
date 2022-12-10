<?
// client-scheduler-welcome-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

if($_POST) {
	$message = trim(str_replace("\n", "<br>", str_replace("\n\n", 	"<p>", str_replace("\r", "", strip_tags("".$_POST['message'])))));
	$value = array('title'=>trim($_POST['title']), 'message'=>$message);
	
	$value = 
	setPreference('clientSchedulerWelcomeNotice', leashtime_real_escape_string(json_encode($value)));
	echo "<script language='javascript'>if(parent.updateProperty) parent.updateProperty('clientSchedulerWelcomeNotice', '');parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";
?>

<h2>Client Scheduler Welcome Message</h2>
<form method='POST' name='msgprops'>
<table>
<?
$notice = getPreference('clientSchedulerWelcomeNotice');
if($notice) $notice = json_decode($notice, 'assoc');
$message = str_replace("<br>", "\n", str_replace("<p>", "\n\n", $notice['message']));

inputRow('Title', 'title', $value=$notice['title'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
textRow('Message', 'message', $value=$message, $rows=10, $cols=60, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
?>
</table>
<p>
<?
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
</form>
<script language='javascript'>
function save() {
	document.msgprops.submit();
}
</script>