<?
// native-sitter-preference-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "preference-fns.php";
require_once "appointment-client-notification-fns.php";

// In the fancy Enhanced Visit Report, we replaced the customizable EnhancedVisitReportTemplate
// with Charles' templates, but we wanted to retain customizability of the framing of the actual sitter's note.
// So... we have altered the behavior of standardEnhancedVisitReportEmailTemplateBody()
// and the usage of the EnhancedVisitReportSubject preference.
// if(petOwnerPortalVRTest()), standardEnhancedVisitReportEmailTemplateBody() will return a
// body that merely frames the #VISIT_NOTE#


// Determine access privs
$locked = locked('o-');
$providerid = $_REQUEST['providerid'];
$clientid = $_REQUEST['clientid'];
if($_POST) {
	//print_r($_POST);
	//echo join(',', array_keys($_POST)); //notifyClientArrivalDetails
	$fields = "notifyClientArrivalDetails,notifyClientCompletionDetails,notifyClientArrivalDetailsSMS,notifyClientCompletionDetailsSMS,"
	."sendVisitArrivalByApp,sendVisitCompletionByApp,enhancedVisitReportArrivalTime,"
	."enhancedVisitReportCompletionTime,enhancedVisitReportVisitNote,enhancedVisitReportMoodButtons,"
	."enhancedVisitReportPetPhoto,enhancedVisitReportRouteMap,visitreportGeneratesClientRequest,homepagevisitreporticonsenabled,"
	."sitterReportsToClientViaServerDirectly,sitterReportsToClientDirectly,sitterReportsToClientViaServerAfterApproval,"
	."visitreportIsNOTLogged,EnhancedVisitReportSubject,EnhancedVisitReportTemplate,allowConcurrentArrivals,earlyArrivalMarkingLimit";
	$fields = explode(',', $fields);
	$template = enhancedVisitReportEmailTemplate();
	foreach($fields as $field) {
		if($field == 'EnhancedVisitReportTemplate') {
			$template['body'] = $_POST[$field] ? $_POST[$field] : standardEnhancedVisitReportEmailTemplateBody();
			if(!$template['farewell']) $template['farewell'] = sqlVal("''");
			if(!$template['extratokens']) $template['extratokens'] = sqlVal("''");
			$id = replaceTable('tblemailtemplate', $template, 1);
		}
		else if($field == 'EnhancedVisitReportSubject') {
			$template['subject'] = $_POST[$field] ? $_POST[$field] : standardEnhancedVisitReportEmailTemplateSubject();
			if(!$template['farewell']) $template['farewell'] = sqlVal("''");
			if(!$template['extratokens']) $template['extratokens'] = sqlVal("''");
			$id = replaceTable('tblemailtemplate', $template, 1);
		}
		else if($clientid) {
			// distinguish between "use default" and "not checked"
			$saveVal = $_POST[$field] ? '1' : ($_POST["default_$field"] ? null : '0');
//echo "<br>$field: ".($saveVal === null ? "NULL" : ($saveVal === '0' ? 'ZERO' : $saveVal));
			setClientPreference($clientid, $field, $saveVal);
		}
		else if($providerid) {
			// distinguish between "use default" and "not checked"
			$saveVal = $_POST[$field] ? '1' : ($_POST["default_$field"] ? null : '0');
			setProviderPreference($providerid, $field, $saveVal);
			if($field == 'visitreportGeneratesClientRequest')
				setProviderPreference($providerid, 'visitreport_NO_ClientRequest', ($saveVal == '1' ? '0' : '1'));
		}
		else {
			setPreference($field, ($_POST[$field] ? '1' : null));
			if($field == 'visitreportGeneratesClientRequest')
				setPreference('visitreport_NO_ClientRequest', ($_POST[$field] ? '0' : ($_POST["default_$field"] ? null : '1')));
			else if($field == 'earlyArrivalMarkingLimit')
				setPreference('earlyArrivalMarkingLimit', $_POST[$field]);
		}
	}
//if(mattOnlyTEST()) {print_r($_POST);exit;}	
	echo "<script language='javascript'>if(parent) { if(parent.updateProperty) parent.updateProperty('nativeSitterAppPrefs', '{$nativeSitterAppPrefs}'); parent.$.fn.colorbox.close();}</script>";
	
}






$pageTitle = "Enhanced Sitter App Preferences";

$customStyles = 
"
.sectionContent {vertical-align:top;padding:10px;}
.pinkbox {background:pink;border:solid purple 1px;padding:5px;}
.column0 {width: 150px;}
.checkboxtable {width:100%;border: solid black 0px; }
.notoppadding {padding-top:5px;}
";

include "frame-bannerless.php";
// ***************************************************************************
// FORMAT: key|Label|type|enumeration|Hint or space|constraints

$_SESSION["preferences"] = fetchPreferences();

$localprefs = $clientid ? getClientPreferences($clientid) : ($providerid ? getProviderPreferences($providerid) : null);
$staffOnlyPreferences = array();
$helpStrings = // not used
"emailFromAddress|This is the email address that sends outgoing messages.  Do not supply this unless you also fill in the other fields in this section.
hideProScheduleAtTop|Hide the Pro Schedule Button at the top of the Client Sevices tab.
hideProScheduleAtBottom|Hide the Pro Schedule Button in the Short Term Schedules section of the Client Sevices tab.
hideOneDayScheduleAtTop|Hide the One Day Schedule Button at the top of the Client Sevices tab.
hideOneDayScheduleAtBottom|Hide the One Day Schedule Button in the Short Term Schedules section of the Client Sevices tab.
surchargeCollisionPolicy|Determine how the system responds when more than one automatic surcharge is applicable on the same day
secureClientInfo|When selected, omits key IDs, entry codes and alarm codes from visit sheets.
timeframeOverlapPolicy|Strict means that 8:00 am-9:00 am and 9:00 am-10:00 am overlap.  They do not when permissive is chosen.";

foreach(explode("\n", $helpStrings) as $pair) {
	$pair = explode('|', $pair);
	$help[$pair[0]] = $pair[1];
}

$staffOnlyPreferences[] = 'enforceProspectSpamDetection';
$staffOnlyPreferences[] = 'autoDeleteSpam';

	

$purposeDescription = 
	"html|"
	.str_replace("\n", " ", "The sitter mobile app allows you to track the sitter via GPS, if the sitter permits it. Depending on
your business needs, you may want to share all, part or none of this information with your pet owner
clients. LeashTime allows you to set the policy regarding sharing the information with the client and 
permissions regarding who may share this information with clients. 
<p></p>
<ul> 
<li> What information about the walk do you want to share with clients?</li>
<li>What method of communication do you want to use with your clients?</li></ul>
<p>Visit status communications sent to clients can be defined at a global preference level and then overridden for each individual client. </p>");

$sendVisitArrivalNotificationPrefs = 
	(staffOnlyTEST() && !($providerid || $clientid) ? "sectionlabel|Concurrent Visits||sectiontype|checkboxes||allowConcurrentArrivals|Allow (does not affect iPhone)|boolean||endcheckboxes||" : '')
	."sectionlabel|Method"
	."||sectiontype|checkboxes"
	."||notifyClientArrivalDetails|Email|boolean"
	."||showClientArrivalDetails|Show on Client Home page|boolean"
	//."||notifyClientArrivalDetailsSMS|boolean"
	//."||sendVisitArrivalByApp|boolean"
	."||pinkbox|<span style='font-size:1.1em'>Please Note</span><p>These settings apply to visit arrivals marked in the original Mobile Sitter App as well."
	;

$sendVisitCompletionNotificationPrefs = 
	"sectionlabel|Method"
	."||sectiontype|checkboxes"
	."||notifyClientCompletionDetails|Email|boolean"
	."||showClientCompletionDetails|Show on Client Home page|boolean"
	//."||notifyClientCompletionDetailsSMS|boolean"
	//."||sendVisitCompletionByApp|boolean"
	."||pinkbox|<span style='font-size:1.1em'>Please Note</span><p>These settings apply to visits marked complete in the original Mobile Sitter App as well."
	;

$sendEnhancedVisitReportPrefs = 
	"sectionlabel|Check all that apply:"
	."||sectiontype|checkboxes"
	."||enhancedVisitReportArrivalTime|Arrival Time|boolean"
	."||enhancedVisitReportCompletionTime|Completion Time|boolean"
	."||enhancedVisitReportVisitNote|Visit Note|boolean"
	."||blank"
	."||enhancedVisitReportMoodButtons|Mood Buttons|boolean|Text names only in text messages"
	."||blank"
	."||enhancedVisitReportPetPhoto|Pet Photo|boolean|Photo is included in email and text messages"
	."||blank"
	."||enhancedVisitReportRouteMap|Map of Route|boolean"
	."||pinkbox|<span style='font-size:1.1em'>Please Note</span><p>If none are selected, then no Enhanced Visit Report will be sent to the client."
	;

$iconExamples = "visit-report-unsubmitted.jpg,visit-report-sent-with-photo.jpg,visit-report-sent.jpg";
foreach(explode(',', $iconExamples) as $icon)
	$iconText[] = "<img src='".globalUrl("art/newvisitreporticons/$icon")."'>";
$iconText = "Show visit report status icons like ".join(' and ', $iconText);

$sendMessageSendPrefs = 
	"sectionlabel|Create Notifications on Home page when..."
	."||sectiontype|checkboxes"
	."||visitreportGeneratesClientRequest|Enhanced Visit Reports are submitted|boolean"
	//."||visitarrivalGeneratesClientRequest|Visit arrivals are reported (not recommended)|boolean"
	//."||visitcompletionGeneratesClientRequest|Visit completions are reported (not recommended)|boolean"
	."||pinkbox|<span style='font-size:1.1em'>Please Note</span><p>If manager approval is required before Enhanced Visit Reports are sent, Notifications on Home Page will be created regardless]"
	//."||visitreportIsNOTLogged|Do NOT save message in the client Communications log |boolean"
	."||endcheckboxes||sectionlabel|Show visit report status icons on Home page||sectiontype|checkboxes||homepagevisitreporticonsenabled|$iconText|boolean"
;

$sitterPrefs = 
	"sectionlabel|Enhanced Visit Reports"
	."||sectiontype|checkboxes"
	//."||sitterReportsToClientDirectly|Sitter can send directly (will be copied to server)|boolean|-|sitterSendClicked"
	."||sitterReportsToClientViaServerDirectly|sent directly to client|boolean|-|sitterSendClicked"
	."||sitterReportsToClientViaServerAfterApproval|sent to client after manager approval|boolean|-|sitterSendClicked"
	;
	
$emailTemplate = 
	"sectiontype|textarea"
	."||html|To reset the template subject or body to the default, delete the field entirely and save the form."
	."||special|EnhancedVisitReportSubject|Subject:|oneline"
	."||special|EnhancedVisitReportTemplate| |textarea"
	;
	
if($providerid) $prefListSections = 
						array(
									'What is the purpose of the server configuration? '=>$purposeDescription, 
									'Sitter Options '=>$sitterPrefs
									);
else if($clientid) $prefListSections = 
						array(
									'What is the purpose of the server configuration? '=>$purposeDescription, 
									'Arrival Report'=>$sendVisitArrivalNotificationPrefs, 
									'Completion Report '=>$sendVisitCompletionNotificationPrefs, 
									'Enhanced Visit Report '=>$sendEnhancedVisitReportPrefs,
									'Home Page Notifications'=>$sendMessageSendPrefs,
									);
else {
	$prefListSections = 
						array(
									'What is the purpose of the server configuration? '=>$purposeDescription, 
									'Arrival Report'=>$sendVisitArrivalNotificationPrefs, 
									'Completion Report'=>$sendVisitCompletionNotificationPrefs, 
									'Enhanced Visit Report'=>$sendEnhancedVisitReportPrefs,
									'Home Page Notifications'=>$sendMessageSendPrefs,
									'Manager Approval'=>$sitterPrefs,
									'Enhanced Visit Report Email Template '=>$emailTemplate
									);
	if(staffOnlyTEST()) {
		// Add Visit Constraints SECOND in order
		$minutesPrior = $_SESSION['preferences']['earlyArrivalMarkingLimit'];
		$options = explodePairsLine('0|No Limit||5|5 minutes||10|10 minutes||15|15 minutes||20|20 minutes||30|30 minutes||60|One Hour||120|Two Hours||180|Three Hours');
		$options = array_flip($options);
		$selectElement = selectElement('', 'earlyArrivalMarkingLimit', $value=$minutesPrior, $options, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=true, $optExtras=null, $title=null);
		$visitConstraintPrefs = 
			"sectionlabel|Allow Arrival Reporting As Early As"
			."||html|Allow Arrival Reporting As Early As $selectElement prior to the start time"
			;
		$firstKey = array_keys($prefListSections);
		$firstKey = $firstKey[0];
		$reverse = array_reverse($prefListSections, $preserve_keys = true);
		$firstValue =array_pop($reverse);
		$reverse['Visit Constraints'] = $visitConstraintPrefs;
		$reverse[$firstKey] = $firstValue;
		$prefListSections = array_reverse($reverse, $preserve_keys = true);
	}
}



$allOrNone = array('all'=>'all', 'none'=>array());									
									
$showSections = 
	isset($allOrNone[$_REQUEST['show']]) 
	? $allOrNone[$_REQUEST['show']]
	: ($_REQUEST['show'] ? explode(',', $_REQUEST['show']) : array());

nativeSitterPreferencesTable($prefListSections, $help, $showSections, $localprefs, $explanations=null, $sectionParams=null, ($clientid || $providerid));

if($_REQUEST['dump']) {
	foreach($prefListSections as $label => $section) {
		echo "<p># $label<br>";
		$section = explode('||', $section);
		foreach($section as $value) {
			$value = explode('|', $value);
			$k = $value[0];
			echo "$k = \"{$localprefs[$k]}\"<br>";
		}
	}
}
		
function nativeSitterPreferencesTable($sections, &$help, $showSections, $localprefs=false, $explanations=null, $sectionParams=null, $showDefaults=false) { // array('label1'=>petTypes|Pet Types|list||bizName|Business Name|string||...
	global $Maint_editor_user_id;
	
	$localprefs = $localprefs ? $localprefs : $_SESSION['preferences'];
	
	$localprefs['visitreportGeneratesClientRequest'] = !$localprefs['visitreport_NO_ClientRequest'];
	
	$n = 1;
	
	echo "<form name='nativeSitterPreferences' method='POST'>";
	
	echo "<table width=100%>\n<tr><td>";
	echo "<table width=100%><tr><td>"; // first row contents
	$userArg = $Maint_editor_user_id ? "&id=$Maint_editor_user_id" : '';
	if($showSections != 'all') fauxLink('Show All Preferences', 'toggleAllSections("showHide")', $noEcho=false, $title=null, $id='showHide');
	//echo "<a href='".basename($_SERVER['SCRIPT_NAME'])."?show=all$userArg'>Show All Preferences</a>\n";
	else fauxLink('Hide All Preferences', 'showAllSections("showHide", false)', $noEcho=false, $title=null, $id='showHide');
	//echo "<a href='".basename($_SERVER['SCRIPT_NAME'])."?show=none$userArg'>Hide All Preferences</a>\n";
	echo "\n";
	echo " - <span class='tiplooks'>Click on a bar to expand the section.  Click it again to shrink it.</span></span>";
	
	echo "</td><td style='text-align:right;'>";
	echoButton(null, 'Save Settings', $onClick='this.form.submit()', $class='', $downClass='', $noEcho=false, $title=null);
	echo "</td></tr></table>";// END first row contents
	echo "</td></tr>";

	$explanations = (array)$explanations;
	foreach($sections as $label => $section) {
		startAShrinkSection($label, "section$n", 
														($showSections != 'all' && !in_array($n, $showSections)));
		$n++;
		nativePreferencesEditorLauncher($section, $help, $localprefs, $explanations[$label], $sectionParams[$label]['applyToAll'], $showDefaults);
		endAShrinkSection();
		//echo "<h3>$label</h3>\n";
	}
	echo "</table>\n";
}

function nativePreferencesEditorLauncher($prefKeysAndTypes, &$help, $localprefs=false, $explanationString=null, $applyToAll=null, $showDefaults=false) { // petTypes|Pet Types|list||bizName|Business Name|string||...
	// $applyToAll: null|sitters|clients
	global $Maint_editor_user_id;
	
	echo "<table style='font-size:1.05em;'>\n";
	if($explanationString) foreach(explode('||', $explanationString) as $expl) {
		$expl = explode('|', $expl);
		$explanations[$expl[0]] = $expl[1];
	}
	
	
	foreach(explode('||', $prefKeysAndTypes) as $groupStr) {
		$group = explode('|', $groupStr);
		$groupStr = urlencode($groupStr);

		
		$title = $help[$group[0]] ? $help[$group[0]] : 'Edit this property';
		
		if($group[0] == 'sectionlabel') {
			$sectionlabel = $group[1];
			continue;
		}
		else if($group[0] == 'sectiontype') {
			$sectiontype = $group[1];
			if($sectiontype == 'checkboxes') {
				echo "<tr><td class='sectionContent column0'>$sectionlabel</td><td class='sectionContent notoppadding'>";
				echo "<table class='checkboxtable'>";
			}
			continue;
		}
		else if($group[0] == 'endcheckboxes')
			echo "</td></tr></table>";
		else if($group[0] == 'html') echo "<tr><td class='sectionContent'>$group[1]</td><tr>";
		else if($group[0] == 'blank') echo "<tr><td>&nbsp;</td><tr>";
		else if($group[0] == 'pinkbox') echo "<tr><td><div class='pinkbox'>{$group[1]}</div></td><tr>";
		else if($group[0] == 'special' && $group[3] == 'textarea')
			echo "<tr><td class='sectionContent'><textarea style='width:600px;;height:150px;' id='{$group[1]}' name='{$group[1]}'></textarea></td><tr>";
		else if($group[0] == 'special' && $group[3] == 'oneline')
			echo "<tr><td class='sectionContent'><label $labelClass for='{$group[1]}'>{$group[2]}</label> <input class='VeryLongInput' id='{$group[1]}' name='{$group[1]}' $onBlur $maxlength  autocomplete='off'></td><tr>\n";
		else {
			$rawVal = $val = $localprefs[$group[0]];
			if($group[2] == 'boolean') {
				$onClick = 
					$group[4] ? "{$group[4]}(this, ".($showDefaults ? '1' : '0').")" : (
					$showDefaults ? 'toggleDefault(this, 0)' : '');
				echo "<tr><td>";
				labeledCheckbox($group[1], $group[0], $rawVal, $labelClass=null, $inputClass=null, $onClick, $boxFirst=true, $noEcho=false, $title=null);
				if($showDefaults) {
					// if the current value equals the default, show the default
					// if the 
					echo " ";
					$default = $_SESSION["preferences"][$group[0]];
					$usedefault =!array_key_exists($group[0], $localprefs);
					//if($rawVal == $default && !
					labeledCheckbox('Use the System Default (= '.($default ? 'YES' : 'NO').')', "default_{$group[0]}", $usedefault, $labelClass=null, $inputClass=null, $onClick, $boxFirst=true, $noEcho=false, $title=null);
				}
				echo "</td></tr>";
				if($group[3] && $group[3] != '-') echo "<tr><td style='font-size:0.9em'>{$group[3]}</td></tr>";
			}
		}
	}
	if($sectiontype == 'checkboxes')
		echo "</td></tr></table>";
	
	echo "</table>\n</form>\n";
}

?>
<script language='javascript'>
function toggleAllSections(linkid) {
	var el = document.getElementById(linkid);
	var yes = el.innerHTML.indexOf('Show') == 0;
	for(var i=1; ; i+=1) {
		var sectionid = 'section'+i;
		if(!document.getElementById(sectionid)) break;
		if(yes) showShrinkDiv(sectionid);
		else hideShrinkDiv(sectionid);
	}
	if(yes) el.innerHTML = 'Hide All Preferences';
	else el.innerHTML = 'Show All Preferences';
}

<? 

dumpShrinkToggleJS();
?>

function openSections() {
	var open = new Array();
	var numClosed = 0;
	var el;
	for(var i=1; el = document.getElementById('section'+i); i++) {
		if(el.style.display == 'none') numClosed++;
		else open.push(i);
	}
	if(numClosed == 0) return 'all';
	else return open.join(',');
}

function sitterSendClicked(el, handleDefault) {
	var keys = 'sitterReportsToClientDirectly|sitterReportsToClientViaServerDirectly|sitterReportsToClientViaServerAfterApproval';
	keys = keys.split('|');
	var newvalue = el.checked;
	for(var i=0; i<keys.length; i++)
		if(document.getElementById(keys[i]))
			document.getElementById(keys[i]).checked = 
				(document.getElementById(keys[i]).id == el.id ? newvalue : false);
	if(handleDefault) toggleDefault(el);
}

function toggleDefault(el, unused) {
	var elid = el.id;
	var otherid;
	if(elid.indexOf('default_') == 0) otherid = elid.substring('default_'.length);
	else otherid = 'default_'+elid;
	document.getElementById(otherid).checked = false;
}

<?
$template = enhancedVisitReportEmailTemplate($template);
// The semantics of EnhancedVisitReportTemplate changed with Charles' enhanced Visit Report,
// going from the complete email body to just the the visit note framing text
if(petOwnerPortalVRTest()) {
	$storedTemplate = savedEnhancedVisitReportTemplate();
	$templateBody = $storedTemplate ? $storedTemplate['body'] : standardEnhancedVisitReportEmailTemplateBody();
}
else $templateBody = $template['body'];

//if(mattOnlyTEST()) echo "/* TEMPPPP ".print_r($template,1)." */";
$EnhancedVisitReportTemplate = str_replace("\r", '', $templateBody);

for($sepr=9999; strpos($EnhancedVisitReportTemplate, $sepr) === FALSE; $sepr++) ;
echo "var endo = /$sepr/g;\n";
$EnhancedVisitReportTemplate = str_replace("\n", $sepr, $EnhancedVisitReportTemplate);


for($sepr+=1; strpos($EnhancedVisitReportTemplate, $sepr) === FALSE; $sepr++) ;
echo "var quot = /$sepr/g;\n";
$EnhancedVisitReportTemplate = str_replace("\"", $sepr, $EnhancedVisitReportTemplate);



echo "var evrt = \"$EnhancedVisitReportTemplate\";
evrt = evrt.replace(endo, \"\\n\");
evrt = evrt.replace(quot, '\"');\n";
?>
document.getElementById('EnhancedVisitReportTemplate').innerHTML = evrt;
document.getElementById('EnhancedVisitReportSubject').value = '<?= safeValue($template['subject']) ?>'.replace(/&apos;/gi, "'");


</script>
<?
include "refresh.inc";
