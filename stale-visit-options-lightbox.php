<?
// stale-visit-options-lightbox.php
require_once "prov-schedule-fns.php";
/* args:
provui: if 1, controls display in provider UI
*/


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
//require_once "field-utils.php";
//require_once "provider-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox
if($_POST['stalevistprefs']) {
//echo "bang!";exit;
	extract($_POST);
	setPreference('reportStaleVisits', ($_POST['reportStaleVisits'] ? $_POST['reportStaleVisits'] : 0));
	setPreference('ignoreVisitsMarkedArrived', ($_POST['ignoreVisitsMarkedArrived'] ? 1 : 0));
	$visitsStaleAfterMinutes = (int)$_POST['visitsStaleAfterMinutes'] == $_POST['visitsStaleAfterMinutes'] ? $_POST['visitsStaleAfterMinutes'] : null;
	setPreference('visitsStaleAfterMinutes', $visitsStaleAfterMinutes);
	$staleVisitsLimitDays = (int)$_POST['staleVisitsLimitDays'] == $_POST['staleVisitsLimitDays'] ? $_POST['staleVisitsLimitDays'] : null;
	setPreference('staleVisitsLimitDays', $staleVisitsLimitDays);
	$vSAM = $visitsStaleAfterMinutes ? $visitsStaleAfterMinutes : "10 minutes";
	$vSAM = $vSAM == 1 ? '1 minute' : "$vSAM";
	$summary = !$reportStaleVisits ? 'No' : "Yes, ".($reportStaleVisits == 2 ? 'for selected sitters ' : '')."after $vSAM minutes";
	setPreference('staleVisitNotificationOptions',$summary);
	
	
	if(array_key_exists('overdueOnArrival', $_POST)) {
		deleteTable('tblpreference', "property LIKE 'overdueStarting_%'", 1);
		$overdueOnArrival = explode(',', "$overdueOnArrival");
		foreach($overdueOnArrival as $i => $servicetypeid)
			setPreference("overdueStarting_".sprintf("%'.03d", $i+1), $servicetypeid);
	}
	
	echo "<script language='javascript'>parent.updateProperty('staleVisitNotificationOptions', '$summary');parent.$.fn.colorbox.close();</script>";
}

include "frame-bannerless.php";
?>

<h2>Overdue Visit Notification Preferences</h2>
<form method='POST' name='staleprops'>
<?
hiddenElement('stalevistprefs', 1);
echoButton('', 'Save Preferences', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
<table>
<?
$props = fetchPreferences();
$options = array('all'=>1, 'none'=>0, 'for selected sitters'=>2); // in future, possible add 'some'=>'2'.  If some, find visits for all sitetrs,clients where reportStaleVisits != 0
echo "<tr><td colspan=2>Report overdue visits by email to managers:</td></tr>";
radioButtonRow('', 'reportStaleVisits', $props['reportStaleVisits'], $options, 'reportOverdue()', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
$selectedSitters = fetchCol0(
		"SELECT CONCAT_WS(' ', fname, lname) 
			FROM tblproviderpref
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE property = 'reportStaleVisits' AND value = 1
			ORDER BY lname, fname");
$selectedSitters = $selectedSitters ? join(', ', $selectedSitters) : 'none';
echo "<tr id='selectedSitters'><td colspan=2><span class='tiplooks'>Select sitters in Employment tab of each sitter's profile.</span><p>Sitters selected: $selectedSitters</td></tr>";

$options = array('yes'=>1, 'no'=>0);
echo "<tr><td colspan=2 style='padding-top:20px;'>Ignore visits that have been marked as arrived:</td></tr>";
radioButtonRow('', 'ignoreVisitsMarkedArrived', $props['ignoreVisitsMarkedArrived'], $options, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);

$options = array("1 minute");
foreach(array(5,10,15,20,30,40,45,60,90,120) as $i) $options["$i minutes"] = $i;
$title = 'Visits are noted as overdue they are not marked complete after if this much time has passed after the visit end time.';
echo "<tr><td style='padding-top:20px;' colspan=2 title='$title'>Grace period after end time of visit <br>(actual time is about 3 minutes later)</td></tr>";
$visitsStaleAfterMinutes = $props['visitsStaleAfterMinutes'] ? $props['visitsStaleAfterMinutes'] : 10;
selectRow('', 'visitsStaleAfterMinutes', $visitsStaleAfterMinutes, $options, $onChange=null, $labelClass=null, $inputClass=null);

$options = array("today only"=>'1', "yesterday or today"=>'2', "two days ago onward"=>'3');
$title = 'If you save a visit as incomplete after its end time, it may be marked as overdue if scheduled for:';
echo "<tr><td style='padding-top:20px;' colspan=2 title='$title'>Consider visits from</td></tr>";
$staleVisitsLimitDays = $props['staleVisitsLimitDays'] ? $props['staleVisitsLimitDays'] : 1;
selectRow('', 'staleVisitsLimitDays', $staleVisitsLimitDays, $options, $onChange=null, $labelClass=null, $inputClass=null);
echo "<tr><td></td><td class='tiplooks'>Recommended: <b>today only</b> or<br>choose <b>yesterday and today</b> to allow for overnights. </td></tr>";
	
if(fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'enableOverdueArrivalNotifications' LIMIT 1", 1)) {
$overdueStartingServices = fetchKeyValuePairs(
		"SELECT * FROM tblpreference 
			WHERE property LIKE 'overdueStarting_%'
			ORDER BY property", 1);
$sTypes = fetchAssociationsKeyedBy(
		"SELECT label, servicetypeid, active
			FROM tblservicetype
			ORDER BY active DESC, label", 'label', 1);
$serviceTypeOptions = array('-- Add a Service Type --' => 0);
$wasActive = 1;
foreach($sTypes as $label => $type) {
	if(!$type['active'] && $wasActive) {
		$serviceTypeOptions['--- Inactive Service Types ---'] = -1;
		$wasActive = 0;
	}
	$serviceTypeOptions[$label] = $type['servicetypeid'];
}
//selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null) 

$serviceSelect = selectElement("", 'serviceTypes', $value=null, $serviceTypeOptions, $onChange='addServiceType(this)', null, null, $noEcho=true);
hiddenElement('overdueOnArrival', join(',', $overdueStartingServices));
echo "<tr><td style='padding-top:20px;' colspan=2>Visits of the following types are overdue if sitter is late arriving:</td></tr>";
echo "<tr><td colspan=2 title='$title'>$serviceSelect<br><span id='overdueStarting'>$serviceTypeHTML</span></td></tr>";
}

?>
</table>
</form>
<script language='javascript'>

function dropServiceType(doomedId) {
	var ids = document.getElementById('overdueOnArrival').value.split(',');
	var newIds = new Array();
	for(var i=0; i<ids.length; i++) {
		if(ids[i] != doomedId) newIds[newIds.length] = ids[i];
	}
	document.getElementById('overdueOnArrival').value = newIds.join(',');
	updateOverdueStarting(document.getElementById('serviceTypes'));
}


function addServiceType(sel) {
	var choiceId = sel.options[sel.selectedIndex].value;
	sel.options[sel.selectedIndex].selected = false;
	sel.selectedIndex = 0;
	if(choiceId <= 0) return;
	var chosen = document.getElementById('overdueOnArrival').value;
	var chosenIds = chosen.split(',');
	for(var i=0; i<chosenIds.length; i++)
		if(chosenIds[i] == choiceId) return;
	// otherwise rebuild list with choiceId included
	var newChosenIds = new Array();
	for(var i=0; i<sel.options.length; i++) {
		var optVal = sel.options[i].value;
		if(optVal <= 0) continue;
		else if(optVal == choiceId) newChosenIds[newChosenIds.length] = choiceId;
		else for(var j=0; j<chosenIds.length; j++) 
					if(optVal == chosenIds[j]) 
						newChosenIds[newChosenIds.length] = chosenIds[j];
	}
	document.getElementById('overdueOnArrival').value = newChosenIds.join(',');
	updateOverdueStarting(sel);
}

function updateOverdueStarting(sel) {
	var ids = document.getElementById('overdueOnArrival').value.split(',');
	if(ids.length == 1 && ids[0] == '') {
		document.getElementById('overdueStarting').innerHTML = '<i>None selected</i>';
		return;
	}
	var out = new Array(), labels = {};
	for(var i=0; i<sel.options.length; i++) 
		if(sel.options[i].value > 0) 
			labels[sel.options[i].value] = sel.options[i].label;
	for(var i=0; i<ids.length; i++) {
		if(ids[i] == '') continue;
		var redX = "<span title='Remove' style='color:red;cursor:pointer;' onclick='dropServiceType("+ids[i]+")'>X</span>";
		out[out.length] = redX+" "+labels[ids[i]];
	}
	document.getElementById('overdueStarting').innerHTML = out.join('<br>');
}

function reportOverdue() {
	var disabled = document.getElementById('reportStaleVisits_0').checked;
	document.getElementById('visitsStaleAfterMinutes').style.color = (disabled ? 'gray' : 'black');
	document.getElementById('visitsStaleAfterMinutes').disabled = disabled;
	document.getElementById('staleVisitsLimitDays').disabled = disabled;
	var selectedSitters = document.getElementById('reportStaleVisits_2').checked;
	document.getElementById('selectedSitters').style.display = selectedSitters ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none';
}

function save() {
	document.staleprops.submit();
}

reportOverdue();
updateOverdueStarting(document.getElementById('serviceTypes'));

</script>