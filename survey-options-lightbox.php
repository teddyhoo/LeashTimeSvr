<?
// survey-options-lightbox.php
require_once "survey-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
//require_once "field-utils.php";
//require_once "provider-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox
//smsTurnedOn,smsGateway
if($_POST['surveyprefs']) {
	print_r($_POST);
	$policy = array();
	if($_POST['timing']) $policy[] = $_POST['timing'];
	if($_POST['filter']) $policy[] = $_POST['filter'];
	setPreference('submissionNotificationPolicy', join(',', $policy));
	echo "<script language='javascript'>parent.$.fn.colorbox.close();</script>"; //parent.updateProperty('staleVisitNotificationOptions', '$summary');
}
$extraHeadContent = '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>';
include "frame-bannerless.php";
?>

<h2>Survey Notification Preferences</h2>
<form method='POST' name='surveyprops'>
<?
hiddenElement('surveyprefs', 1);
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
<table>
<?
//$props = fetchPreferences();

$policy = submissionNotificationPolicy();
$options = array('never'=>'none', 'every five minutes (digest)'=>'digest', 'in real time'=>'individual');
foreach($options as $v) if($policy[$v]) $timing = $v;
echo "<tr><td colspan=2>Notification Timing</td></tr>";
radioButtonRow('', 'timing', $value = $timing, $options, $onchange='timingChanged()', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);

$options = array("all surveys"=>"all", 
									"all surveys with flagged answers"=>"flagged", 
									"\"noteworthy\" survey types"=>"noteworthy", 
									"\"noteworthy\" survey types with flagged answers"=>"flagged&noteworthy", 
									"\"noteworthy\" survey types OR any with flagged answers"=>"flagged|noteworthy",
									);

foreach($options as $v) if($policy[$v]) $include = $v;
$include = $include ? $include : 'all';
//echo print_r($policy, 1)."<br>$include<br>";

echo "<tr><td style='padding-top:20px;' colspan=2>Report on Submissions of:</td></tr>";
radioButtonRow('', 'filter', $value = $include, $options, $onchange=null, $labelClass='filter',  $inputClass='filter', $rowId=null,  $rowStyle=null, $breakEveryN=1);

?>
<tr><td colspan=2 style='font-style:italic;padding-top:10px;'>A "noteworthy" survey type is one that has been designated noteworthy at design time.<td></tr></table>
</form>
<script language='javascript'>

function timingChanged() {
	var disabled = $('#timing_none').is(":checked");
	$('.filter').children().prop('disabled', disabled);
}

function save() {
	document.surveyprops.submit();
}

timingChanged();
</script>