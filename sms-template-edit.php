<? // sms-template-edit.php
/*
id - template id
targettype - client | provider | staff
templatebody

SCRIPTVARS
properties - optional pipe-separated string to be put into a hidden element named properties

SCRIPT MAY BE INCLUDED FOR SPECIAL PURPOSES. IF SO...
template -- a template to be created
pageTitle -- a title for the page
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "sms-template-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$wysiwyg =  $_GET['wysiwyg'];//staffOnlyTEST();//false; //mattOnlyTEST();
$badCharsWarning = 
	array("This message has characters that cannot be handled properly.",
				'Examples: "fancy" quotes (&#8216;, &#8217;, &#8220;, &#8221;) long dashes (&mdash;),<br>accented characters(&ouml; &egrave;), symbols(&pound;, &reg;), fractions(&frac12;, &frac14;), etc.');

$locked = locked('o-');//locked('o-'); 

extract(extractVars('id,label,targettype,templatebody,deleteTemplate,active,action', $_REQUEST));

if($action == 'revert') {
	$rTemplate = fetchFirstAssoc("SELECT * FROM tblsmstemplate WHERE templateid = $id LIMIT 1");
	$standards = getStandardTemplates();
	$replacement = $standards[$rTemplate['label']];
	//$replacement['label'] = $rTemplate['label'];
	//$replacement['targettype'] = $rTemplate['type'];
	if($rTemplate && $replacement) {
		foreach(array('body', 'extratokens') as $k)
			$rTemplate[$k] = $replacement[$k] ? $replacement[$k] : sqlVal("''");
		replaceTable('tblsmstemplate', $rTemplate, 1);
//echo "TEMPLATE:<p>".print_r($rTemplate, 1)."<p>.<p>".mysql_affected_rows()." rows affected.";
	}
	//exit;//
	globalRedirect("sms-template-edit.php?id=$id");
	exit;
}
	
//if(mattOnlyTEST()) {	print_r($_REQUEST);exit;}
if($action == 'preview') {
	require_once "preference-fns.php";
	setPreference('smstemplatepreview', $templatebody);
	$_SESSION["preferences"]['smstemplatepreview'] = fetchPreference('smstemplatepreview');
	$refetched = $_SESSION["preferences"]['smstemplatepreview'];
	$bodylength = $_POST['bodylength'];
if(FALSE && mattOnlyTEST()) {	
	// count EOLs in $templatebody
	$lineCount = substr_count($templatebody, "\r" );
	echo "COUNT: ".$lineCount.'<hr>';
	echo "bodylength: [$bodylength] = refetched: ".strlen($refetched);
}
	/*if($templatebody != $refetched || 
			($bodylength != strlen($refetched) && 
				$bodylength != strlen($refetched) - substr_count($templatebody, "\r" ))) 
		$errors = $badCharsWarning;*/
	$templatebody = $refetched;
	$template = array('label'=>$label, 'body'=>$templatebody,
										'active'=>($active ? 1 : 0), 'targettype'=>$targettype);
}
else if($_POST) {
	if($deleteTemplate) {
		deleteTable('tblsmstemplate', "templateid = $id", 1);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
	
	$bodylength = $_POST['bodylength'];
	if($bodylength != strlen($refetched) && $bodylength != strlen($templatebody) - substr_count($templatebody, "\r" )) $errors = $badCharsWarning;
if(mattOnlyTEST() && $errors) $errors[] = "<p>action: $action<p>bodylength: $bodylength<br>refetched: ".strlen($refetched)."<br>templatebody len: ".strlen($templatebody)."<br>substr_count: ".substr_count($templatebody, "\r" );
	$template = array('label'=>$label, 'body'=>$templatebody,
										'active'=>($active ? 1 : 0), 'targettype'=>$targettype);
	$allTemplates = fetchAssociationsKeyedBy("SELECT * FROM tblsmstemplate", 'label');
	$oldTemplate = $allTemplates[stripslashes($label)];
	if($oldTemplate) {
		if(!$id || $oldTemplate['templateid'] != $id)
			$errors[] = 'This template label is already in use for another template';
			$template['label'] =  stripslashes($template['label']);
			$template['body'] =  stripslashes($template['body']);
			if($wysiwyg) $template['body'] = str_replace("\n", "", str_replace("\r", "", $template['body']));
	}
	
	if(!$errors) {
		if($id) {
			updateTable('tblsmstemplate', $template, "templateid = $id", 1);
			$template['templateid'] = $id;
		}
		else $template['templateid'] = insertTable('tblsmstemplate', $template, 1);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
}
else if($id) $template = fetchFirstAssoc("SELECT * FROM tblsmstemplate WHERE templateid = $id LIMIT 1");
else if($template) { // supplied by script including this script
	// $pageTitle will also be set
}

$pageTitle = $pageTitle ? $pageTitle  : ($id ? 'Edit' : 'Create')." an SMS Template";

$windowTitle = $pageTitle;

if($wysiwyg) $extraHeadContent = '
<script type="text/javascript" src="tinymce/jscripts/tiny_mce/tiny_mce.js"></script>
<script type="text/javascript">
tinyMCE.init({
		//relative_urls : false,
		convert_urls : false,
		mode : "textareas",
		theme : "advanced" // simple

	});

</script>
';

$extraBodyStyle = 'padding:10px;';
require "frame-bannerless.php";

echo "<h2>$pageTitle</h2>";

if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}
?>
<form method='POST' name='templateeditor' action="sms-template-edit.php"  accept-charset="UTF-8">
<table>
<?

//$systemPrefix = getSystemPrefix($template['label']);
$isStandard = $systemPrefix;

// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
hiddenElement('id', $id);
hiddenElement('action', 0);
hiddenElement('deleteTemplate', 0);
hiddenElement('bodylength', 0);
//countdownInputRow($maxLength, $label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $position='afterinput')
if(!$isStandard)
	countdownInputRow(255, 'Label:', 'label', $template['label'], $labelClass=null, 'verylonginput', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');
else {
	hiddenElement('label', $template['label']);
	labelRow('Standard Template:', '', '<b>'.substr($template['label'], strlen($systemPrefix)).'</b>', '','','','',true);
}
$displayableTargetType = ($template['targettype'] == 'provider' ? 'sitter' : $template['targettype']).'s';
if(!$isStandard)
	radioButtonRow('Recipient type:', 'targettype', $template['targettype'], array('client'=>'client','sitter'=>'provider', 'staff'=>'staff'), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
else {
	hiddenElement('targettype', $template['targettype']);
	labelRow('Recipient type:', '', '<b>'.$displayableTargetType.'</b>', '','','','',true);
}

$templateBody = $template['body'];
if($wysiwyg) {
	if(strip_tags($templateBody) == $templateBody)
		$templateBody = str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", $templateBody)));
}

$testWarning = $wysiwyg ? " <b>RICH TEXT EDITOR AVAILABLE TO TESTERS ONLY</b>" : '';
textRow("Message body$testWarning", 'templatebody', $templateBody, $rows=15, $cols=80, $labelClass=null, $inputClass='fontSize1_2em', $rowId=null, $rowStyle=null, $maxlength=null);
$tokens = "#BIZNAME#, #BIZEMAIL#, #BIZPHONE#, #BIZHOMEPAGE#, #BIZLOGINPAGE#, #MANAGER#, #RECIPIENT#, #FIRSTNAME#, #LASTNAME#, #LOGO#, #LOGINID#, #TEMPPASSWORD#, #CREDITCARD# (clients only), #PETS# (clients only)";
if($template['extratokens']) $tokens .= ", ".$template['extratokens'];
echo "<tr><td colspan=2 style='tiplooks'>Substitution tokens: $tokens</td></tr>"; 
checkboxRow('Active:', 'active', $template['active'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null);

?>
</table>
<?
echoButton('', 'Save Template', 'checkAndSave()');
echo " ";
echoButton('', "Preview Template", (true ? 'preview2()' : 'preview()'));

if($id && !$isStandard) {
	echo " ";
	echoButton('', "Delete Template", 'dropTemplate()', 'HotButton', 'HotButtonDown');
}

if($id && $isStandard) {
	echo "<img src='art/spacer.gif' width=250 height=1>";
	echoButton('', "Factory Reset", 'resetTemplate()', 'HotButton fontSize0_7em', 'HotButtonDown');
}
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
//extract(extractVars('id,label,targettype,templatebody', $_REQUEST));


setPrettynames('label',"Template Label",'targettype','Recipient type','templatebody','Message body');
function checkAndSave() {
	if(typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
	if(MM_validateForm('label', '', 'R',
											'targettype','', 'R',
											'templatebody','', 'R'
											)) {
		document.templateeditor.submit();
	}
}

function preview() {
	if(typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
	var target = document.getElementById('targettype');
	target = 
		target && target.value ? document.getElementById('targettype').value : (
		document.getElementById('targettype_client').checked ? 'client' : (
		document.getElementById('targettype_provider').checked ? 'provider' : ''));
	if(!target) {
		alert('You must first choose a Recipient type');
		return;
	}
	var body = escape(document.getElementById('templatebody').value);
	var url = 'sms-template-preview.php?type='+target+'&body='+body+<?= $wysiwyg ? "'&noprep=1'" : "''"; ?>;
	openConsoleWindow('templatepreview', url,800,700)
	// 
}

function preview2() {
	if(typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
	var target = document.getElementById('targettype');
	target = 
		target && target.value ? document.getElementById('targettype').value : (
		document.getElementById('targettype_client').checked ? 'client' : (
		document.getElementById('targettype_provider').checked ? 'provider' : ''));
	if(!target) {
		alert('You must first choose a Recipient type');
		return;
	}
	document.templateeditor.action = 'sms-template-edit.php'; //?action=preview
	document.getElementById('bodylength').value = document.getElementById('templatebody').value.length;
	document.getElementById('action').value='preview';
	document.templateeditor.submit();
	// 
}

function dropTemplate() {
	if(confirm('Are you sure you want to delete this SMS template?')) {
			document.getElementById('deleteTemplate').value=1;
  		document.templateeditor.submit();
		
	}
}

function resetTemplate() {
	if(confirm('Are you sure you want to reset this SMS template to the original "factory" version?  Any changes you have made will be lost.')) {
			document.getElementById('action').value='revert';
  		document.templateeditor.submit();
		
	}
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

<? if($action == 'preview' && !$errors) { ?>
	var url = 'sms-template-preview.php?type=<?= $targettype ?>&cache=1<?= $wysiwyg ? "&noprep=1" : ""; ?>';
	openConsoleWindow('templatepreview', url,800,700)
<? } ?>

var gsmchars = [0x0040, 0x0394, 0x0020, 0x0030, 0x00a1, 0x0050, 0x00bf, 0x0070,
        0x00a3, 0x005f, 0x0021, 0x0031, 0x0041, 0x0051, 0x0061, 0x0071,
        0x0024, 0x03a6, 0x0022, 0x0032, 0x0042, 0x0052, 0x0062, 0x0072,
        0x00a5, 0x0393, 0x0023, 0x0033, 0x0043, 0x0053, 0x0063, 0x0073,
        0x00e8, 0x039b, 0x00a4, 0x0034, 0x0035, 0x0044, 0x0054, 0x0064, 0x0074,
        0x00e9, 0x03a9, 0x0025, 0x0045, 0x0045, 0x0055, 0x0065, 0x0075,
        0x00f9, 0x03a0, 0x0026, 0x0036, 0x0046, 0x0056, 0x0066, 0x0076,
        0x00ec, 0x03a8, 0x0027, 0x0037, 0x0047, 0x0057, 0x0067, 0x0077, 
        0x00f2, 0x03a3, 0x0028, 0x0038, 0x0048, 0x0058, 0x0068, 0x0078,
        0x00c7, 0x0398, 0x0029, 0x0039, 0x0049, 0x0059, 0x0069, 0x0079,
        0x000a, 0x039e, 0x002a, 0x003a, 0x004a, 0x005a, 0x006a, 0x007a,
        0x00d8, 0x001b, 0x002b, 0x003b, 0x004b, 0x00c4, 0x006b, 0x00e4,
        0x00f8, 0x00c6, 0x002c, 0x003c, 0x004c, 0x00d6, 0x006c, 0x00f6,
        0x000d, 0x00e6, 0x002d, 0x003d, 0x004d, 0x00d1, 0x006d, 0x00f1,
        0x00c5, 0x00df, 0x002e, 0x003e, 0x004e, 0x00dc, 0x006e, 0x00fc,
        0x00e5, 0x00c9, 0x002f, 0x003f, 0x004f, 0x00a7, 0x006f, 0x00e0];
        
        
function isValidGSMChar(e) {
	var keyCode = (e.keyCode ? e.keyCode : e.which);
	if(!gsmchars.indexOf(keyCode))
		e.preventDefault();
	return;
}

function isValidGSMStr(e) {
	var s;
	if (e.clipboardData && e.clipboardData.getData)
	{// Standards Compliant FIRST!
		s = e.clipboardData.getData('text/plain');
	}
	else if (window.clipboardData && window.clipboardData.getData)
	{// IE
		s = window.clipboardData.getData('Text');
	}
	for(var i = 0; i < s.length; i++) {
		if(gsmchars.indexOf(s.charCodeAt(i)) == -1) {
			e.preventDefault();
			alert("Character not allowed: "+s.charAt(i));
			return;
		}
	}
}

function isGSMChar(c) {
	return gsmchars.indexOf(c.charCodeAt(0)) !== -1;
}

document.getElementById('templatebody').onkeypress = isValidGSMChar;
document.getElementById('templatebody').onpaste = isValidGSMStr;

</script>
<?
// ***************************************************************************
//include "frame-end.html";
?>

