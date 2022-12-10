<?
// prospect-template-options-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

$property = $_REQUEST['prop'];
$labels = explodePairsLine('fname|First Name||lname|Last Name||phone|Phone||email|Email||address|Full Address||phoneOrEmail|Either Phone Or Email||'
														.'pets|Pets||note|Note||street1|Address||street2|Address 2||city|City||state|State||zip|ZIP||'
														.'whentocall|Best time for us to call||whenserviceneeded|When do you need service?||'
														.'referralsimple|How did you hear about us? (one line)||referralcodes|How did you hear about us? (menu)');
if(staffOnlyTEST()) $labels['pastclient']= 'Have we served you and your pets in the past?';

if($_POST) {
	setPreference('prospectFormFlexibleOptionSelected', $_POST['useFlexibleProspectForm']);
	foreach(array_keys($labels) as $fn) {
		if($_POST["required_$fn"]) {
			$requireds[] = $fn;
		}
		if($_POST["optional_$fn"]) {
			$optionalFields[] = $fn;
		}
	}
//echo "BANG! ".print_r($_POST, 1);			
	setPreference('prospectFormRequiredFields', join(',',(array)$requireds));
	setPreference('prospectFormOptionalFields', join(',',(array)$optionalFields));
//echo "BANG! ".print_r(getPreference('prospectFormRequiredFields'), 1);			
	foreach(explode(',', 'prospectFormGreeting,suppressMeetingFieldsInProspectForm,enforceProspectSpamDetection,prospectFormSimpleAddress,phoneNumbersDigitsOnly,prospectFormGoBackURL,useCellphoneForProspectPhone')
			as $k)
		setPreference($k, $_POST[$k]);
	
	echo "<script language='javascript'>if(parent.updateProperty) parent.updateProperty('$property', '');parent.$.fn.colorbox.close();</script>";
}

$useFlexibleProspectForm = getPreference('prospectFormFlexibleOptionSelected');
$customStyles = ".flexitable {background:#FFEFD7;border:solid gray 1px;} .flexitable td {padding:15px;}";
include "frame-bannerless.php";
?>

<h2>Prospect Form Options</h2>

<? 
fauxLink('Test Saved Settings (Computer)', 'testSavedSettings(false)', $noEcho=false, $title='Show the form as clients see it now.');
echo " - ";
fauxLink('Test Saved Settings (Mobile)', 'testSavedSettings(true)', $noEcho=false, $title='Show the form as clients see it now on mobile devices.');
echo "<p><hr>";
?>
<form method='POST' name='prospectopts'>
<?
echo "<table class='flexitable'>";
$options = array('Flexible Prospect Form'=>1, 'Standard Prospect Form'=>0);
radioButtonRow("Use", 'useFlexibleProspectForm', $useFlexibleProspectForm, $options, $onClick='flexibleProspectFormClick(this)', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null, $nonBreakingSpaceLabels=true);
echo "</table >";

echo "<div id='flexibleFormOptionsDiv'>";

?>
<table <? if(0 && mattOnlyTEST()) echo "border=1"; ?>>
<?

$props = 'prospectFormGreeting,suppressMeetingFieldsInProspectForm,enforceProspectSpamDetection,prospectFormSimpleAddress,prospectFormRequiredFields,phoneNumbersDigitsOnly,prospectFormOptionalFields,useCellphoneForProspectPhone';
$props = explode(',', $props);
$props = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('".join("','", $props)."')", 1);
//echo "BANG! ".print_r($props, 1);			

textRow('Greeting', 'prospectFormGreeting', $value=$props['prospectFormGreeting'], $rows=7, $cols=80, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
//checkboxRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null, $boxFirst=false)


echo "<tr><td colspan=2><table>";
checkboxRow('Suppress Meeting Setup Fields', 'suppressMeetingFieldsInProspectForm', $value=$props['suppressMeetingFieldsInProspectForm'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
//if(staffOnlyTEST()) checkboxRow('<b><i>Enforce Spam Protection</i></b>', 'enforceProspectSpamDetection', $value=$props['enforceProspectSpamDetection'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
//if(staffOnlyTEST()) checkboxRow('<b><i>Enforce Digit-Only Phone Numbers</i></b>', 'phoneNumbersDigitsOnly', $value=$props['phoneNumbersDigitsOnly'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
checkboxRow('Enforce Spam Protection', 'enforceProspectSpamDetection', $value=$props['enforceProspectSpamDetection'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
checkboxRow('Enforce Digit-Only Phone Numbers', 'phoneNumbersDigitsOnly', $value=$props['phoneNumbersDigitsOnly'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
checkboxRow('Use One Big Box for Address', 'prospectFormSimpleAddress', $value=$props['prospectFormSimpleAddress'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
checkboxRow('Assume that Phone field is a mobile (cell) phone, not home phone', 'useCellphoneForProspectPhone', $value=$props['useCellphoneForProspectPhone'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);
echo "</table></td></tr>";


$reqFields = array();
foreach(explode(',', (string)$props['prospectFormRequiredFields']) as $s) $reqFields[$s] = 1;
$optionalFields = array();
foreach(explode(',', (string)$props['prospectFormOptionalFields']) as $s) $optionalFields[$s] = 1;

labelRow('Required Fields', $name, $value=null, $labelClass='fontSize1_1em boldfont', $inputClass=null, $rowId=null,  $rowStyle='line-height: 3em;', $rawValue=false);
echo "<tr><td colspan=2><table border=0>";

	echo "<tr>";
	foreach(array('fname', 'lname') as $k) {
		echo "<td>";
		labeledCheckbox($labels[$k], "required_$k", $value=$reqFields[$k], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
		echo "</td>";
	}
	echo "</tr>";
	
	
	echo "<tr>";
	foreach(array('phone', 'email', 'phoneOrEmail') as $k) {
		echo "<td>";
		labeledCheckbox($labels[$k], "required_$k", $value=$reqFields[$k], $labelClass=null, $inputClass=null, $onClick='contactMethodClick(this)', $boxFirst=true, $noEcho=false, $title=null);
		echo "</td>";
	}
	echo "</tr>";
	
	
	echo "<tr>";
	foreach(array('note', 'address') as $k) {
		echo "<td>";
		labeledCheckbox($labels[$k], "required_$k", $value=$reqFields[$k], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
		echo "</td>";
	}
	echo "<td class='tiplooks' style='text-align:left;'>\"Full Address\" applies if  you are using One Big Box</td>";
	echo "</tr>";
	
	
	echo "<tr>";
	foreach(array('street1', 'street2') as $k) {
		echo "<td>";
		labeledCheckbox($labels[$k], "required_$k", $value=$reqFields[$k], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
		echo "</td>";
	}
	echo "<td class='tiplooks' style='text-align:left;'>The rest apply if you are use separate fields</td>";
	echo "</tr>";
	
	
	echo "<tr>";
	foreach(array('city', 'state', 'zip') as $k) {
		echo "<td>";
		labeledCheckbox($labels[$k], "required_$k", $value=$reqFields[$k], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
		echo "</td>";
	}
	echo "</tr>";
	
echo "</table></td></tr>";

	$optionalFieldNames = 'whentocall,whenserviceneeded,referralsimple,referralcodes';
labelRow('Extra Fields', $name, $value=null, $labelClass='fontSize1_1em boldfont', $inputClass=null, $rowId=null,  $rowStyle='line-height: 3em;', $rawValue=false);
	echo "<tr><td colspan=2><table border=0>";
	
	echo "<tr>";
//print_r($labels);exit;	
	foreach(array('whentocall', 'whenserviceneeded') as $k) {
		echo "<td>";
		labeledCheckbox($labels[$k], "optional_$k", $value=$optionalFields[$k], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
		echo "</td>";
	}
	echo "</tr>";
	
	echo "<tr>";
	foreach(array('referralsimple', 'referralcodes') as $k) {
		echo "<td>";
		labeledCheckbox($labels[$k], "optional_$k", $value=$optionalFields[$k], $labelClass=null, $inputClass=null, $onClick='referralMethodClick(this)', $boxFirst=true, $noEcho=false, $title=null);
		echo "</td>";
	}
	echo "</tr>";
	
	if($labels['pastclient']) {
		echo "<tr>";
		foreach(array('pastclient') as $k) {
			echo "<td>";
			labeledCheckbox($labels[$k], "optional_$k", $value=$optionalFields[$k], $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
			echo "</td>";
		}
		echo "</tr>";
	}
	
	
echo "</table></td></tr>";
	
if(TRUE) {	// enabled 12/6/2018
$prospectFormGoBackURL = getPreference('prospectFormGoBackURL');

labelRow('Go to this page after prospect form submission.', $name=null, $value=null, $labelClass='fontSize1_1em boldfont', $inputClass=null, $rowId=null,  $rowStyle='line-height: 3em;', $rawValue=false);
echo "<tr><td colspan=3 class='tiplooks' style='padding-top:0px;'>If left blank, your website URL will be used. "
			.echoButton('testurl', 'Test URL', $onClick='testThisURL("prospectFormGoBackURL")', $class='', $downClass='', $noEcho=true)
			."</td></tr>";
echo "<tr><td colspan=3><input id='prospectFormGoBackURL' name='prospectFormGoBackURL' class='VeryLongInput' value='$prospectFormGoBackURL'></td></tr>";
}

//echo $notice;
//if($notice) $notice = json_decode($notice, 'assoc');
//$message = str_replace("<br>", "\n", str_replace("<p>", "\n\n", $notice['message']));
//hiddenElement('prop', $property);
//hiddenElement('action', '');
//inputRow('Title', 'title', $value=$notice['title'], $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
?>
</table>
</div> <!-- flexibleFormOptionsDiv -->
<p>
<?
echoButton('', 'Save Changes', 'save()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
</form>
<script language='javascript' src='common.js'></script>

<script language='javascript'>
function save(drop) {
	
	var url = document.getElementById('prospectFormGoBackURL') 
						? document.getElementById('prospectFormGoBackURL').value.trim() 
						: "";
	if(url != '') {
		if(url.indexOf('http://') != 0 && url.indexOf('https://') != 0) {
			alert("The URL ["+url+"] must start with http:// or https://");
			return;
		}
	}
	document.getElementById('prospectFormGoBackURL').value = url.trim();
<? if(TRUE) { ?>	
	if(document.getElementById('enforceProspectSpamDetection').checked
		  && !document.getElementById('required_phoneOrEmail').checked	
		  && !document.getElementById('required_phone').checked	
		  && !document.getElementById('required_email').checked) {
		var warning = 'Warning:\n\nYou have not designated email or phone as required fields.\n\n'
				 			 +'Please be aware that with spam detaction activated\n'
				 			 +'Any forms received that have neither\n'
				 			 +'an email address or phone number '
		 			 +'will be judged to be spam.\n\nClick OK to save anyway.';
		 if(!confirm(warning)) return;
	}
<? } ?>	
	document.prospectopts.submit();
}

function testThisURL(name) {
	var url = document.getElementById(name).value;
	if(url == null || url.trim() == "") url = '<?= $_SESSION['preferences']['bizHomePage'] ?>';
	if(url == '') {
		alert("Please set your Business Home Page first, in ADMI > Preferences > General Business");
		return;
	}
	if(url.indexOf('http://') != 0 && url.indexOf('https://') != 0) {
		alert("The URL ["+url+"] must start with http:// or https://");
		return;
	}
	openConsoleWindow('urltest', url,700,700);
	
}

function contactMethodClick(el) {
	if(el.id == 'required_phoneOrEmail') {
		document.getElementById('required_phone').checked = false;
		document.getElementById('required_email').checked = false;
	}
	else document.getElementById('required_phoneOrEmail').checked = false;
}

function referralMethodClick(el) {
	if(el.id == 'optional_referralsimple') document.getElementById('optional_referralcodes').checked = false;
	else document.getElementById('optional_referralsimple').checked = false;
}

function testSavedSettings(mobile) {
	if(mobile) openConsoleWindow('prospecttest', 'prospect-request-form-custom.php?mob=1&bizid=<?= $_SESSION["bizptr"]; ?>', 500, 700);
	else openConsoleWindow('prospecttest', 'prospect-request-form-custom.php?bizid=<?= $_SESSION["bizptr"]; ?>', 800, 700);
}

function flexibleProspectFormClick() {
	display = document.getElementById('useFlexibleProspectForm_1').checked ? 'block' : 'none';
	document.getElementById('flexibleFormOptionsDiv').style.display=display;
}

flexibleProspectFormClick();

</script>