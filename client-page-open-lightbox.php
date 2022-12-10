<?
// client-page-open-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

$property = $_REQUEST['prop'];

if($_POST) {
	if($_POST['action'] == 'drop') {setPreference($property, null);setPreference("{$property}ABBREV", null);}
	else {
		$message = trim(str_replace("\n", "<br>", str_replace("\n\n", 	"<p>", str_replace("\r", "", strip_tags("".$_POST['message'])))));
		$message = trim(str_replace("\"", "&quot;" , $message));
		$value = array('title'=>trim($_POST['title']), 'message'=>$message);
		setPreference($property, json_encode($value));
		$atitle = $value['title'] ? truncatedLabel($value['title'], 40) : '';
		$amsg = truncatedLabel($message, ($atitle ? (70 - strlen($atitle)) : 90));
		$abbrev = ($atitle ? "$atitle: " : '').$amsg;
		setPreference("{$property}ABBREV",$abbrev);
		
	}
	echo "<script language='javascript'>if(parent.updateProperty) parent.updateProperty('$property', '');parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";
?>

<h2><?= $_REQUEST['label'] ?></h2>
<form method='POST' name='msgprops'>
<table>
<?
$notice = getPreference($property);
//echo $notice;
if($notice) $notice = json_decode($notice, 'assoc');
$message = str_replace("<br>", "\n", str_replace("<p>", "\n\n", $notice['message']));
hiddenElement('prop', $property);
hiddenElement('action', '');
inputRow('Title', 'title', $value=$notice['title'], $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
textRow('Message', 'message', $value=$message, $rows=10, $cols=60, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
?>
</table>
<p>
<?
echoButton('', 'Save Announcement', 'save()');
echo ' ';
echoButton('', 'Drop Announcement', 'drop()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
</form>
<script language='javascript'>
function drop() {
	document.getElementById('action').value='drop';
	document.msgprops.submit();
}
function save(drop) {
	document.msgprops.submit();
}
</script>