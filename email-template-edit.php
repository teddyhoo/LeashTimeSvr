<? // email-template-edit.php
/*
id - template id
targettype - client | provider
templatesubject
templatebody
personalize - boolean
salutation - "Dear"

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
require_once "email-fns.php";
require_once "email-template-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
$wysiwyg =  $_GET['wysiwyg'];//staffOnlyTEST();//false; //mattOnlyTEST();
$badCharsWarning = 
	array("This message has characters that cannot be handled properly.",
				'Examples: "fancy" quotes (&#8216;, &#8217;, &#8220;, &#8221;) long dashes (&mdash;),<br>accented characters(&ouml; &egrave;), symbols(&pound;, &reg;), fractions(&frac12;, &frac14;), etc.');

$locked = locked('o-');//locked('o-'); 

extract(extractVars('id,label,targettype,templatesubject,templatebody,personalize,salutation,deleteTemplate,active,action', $_REQUEST));

if($action == 'revert') {
	$rTemplate = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = $id LIMIT 1");
	$standards = getStandardTemplates();
	$replacement = $standards[$rTemplate['label']];
	//$replacement['label'] = $rTemplate['label'];
	//$replacement['targettype'] = $rTemplate['type'];
	if($rTemplate && $replacement) {
		foreach(array('subject', 'body', 'extratokens', 'salutation', 'farewell') as $k)
			$rTemplate[$k] = $replacement[$k] ? $replacement[$k] : sqlVal("''");
		replaceTable('tblemailtemplate', $rTemplate, 1);
//echo "TEMPLATE:<p>".print_r($rTemplate, 1)."<p>.<p>".mysqli_affected_rows()." rows affected.";
	}
	//exit;//
	globalRedirect("email-template-edit.php?id=$id");
	exit;
}
	
}
if($action == 'preview') {
	require_once "preference-fns.php";
	setPreference('emailtemplatepreview', $templatebody);
	$_SESSION["preferences"]['emailtemplatepreview'] = fetchPreference('emailtemplatepreview');
	$refetched = $_SESSION["preferences"]['emailtemplatepreview'];
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
	$salutation = $salutation ? $salutation : '';
	$template = array('label'=>$label, 'subject'=>$templatesubject, 'body'=>$templatebody, 'personalize'=>($personalize ? 1 : 0),
										'active'=>($active ? 1 : 0), 'salutation'=>$salutation, 'targettype'=>$targettype);
}
else if($_POST) {
	if($deleteTemplate) {
		deleteTable('tblemailtemplate', "templateid = $id", 1);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
	
	$bodylength = $_POST['bodylength'];
	if($bodylength != strlen($refetched) && $bodylength != strlen($templatebody) - substr_count($templatebody, "\r" )) $errors = $badCharsWarning;
if(mattOnlyTEST() && $errors) $errors[] = "<p>action: $action<p>bodylength: $bodylength<br>refetched: ".strlen($refetched)."<br>templatebody len: ".strlen($templatebody)."<br>substr_count: ".substr_count($templatebody, "\r" );
	$salutation = $salutation ? $salutation : '';
	$template = array('label'=>$label, 'subject'=>$templatesubject, 'body'=>$templatebody, 'personalize'=>($personalize ? 1 : 0),
										'active'=>($active ? 1 : 0), 'salutation'=>$salutation, 'targettype'=>$targettype);
	$allTemplates = fetchAssociationsKeyedBy("SELECT * FROM tblemailtemplate", 'label');
	$oldTemplate = $allTemplates[stripslashes($label)];
	if($oldTemplate) {
		if(!$id || $oldTemplate['templateid'] != $id)
			$errors[] = 'This template label is already in use for another template';
			$template['label'] =  stripslashes($template['label']);
			$template['subject'] =  stripslashes($template['subject']);
			$template['body'] =  stripslashes($template['body']);
			if($wysiwyg) $template['body'] = str_replace("\n", "", str_replace("\r", "", $template['body']));
			$template['salutation'] =  stripslashes($template['salutation']);
	}
	
	if(!$errors) {
		if($id) {
			updateTable('tblemailtemplate', $template, "templateid = $id", 1);
			$template['templateid'] = $id;
		}
		else $template['templateid'] = insertTable('tblemailtemplate', $template, 1);
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
}
else if($id) $template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = $id LIMIT 1");
else if($template) { // supplied by script including this script
	// $pageTitle will also be set
}

$pageTitle = $pageTitle ? $pageTitle  : ($id ? 'Edit' : 'Create')." an Email Template";

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
<form method='POST' name='templateeditor' action="email-template-edit.php"  accept-charset="UTF-8">
<table>
<?

$systemPrefix = getSystemPrefix($template['label']);
$isStandard = $systemPrefix;

// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
hiddenElement('id', $id);
hiddenElement('action', 0);
hiddenElement('deleteTemplate', 0);
hiddenElement('bodylength', 0);
//countdownInputRow($maxLength, $label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $position='afterinput')
if(!$isStandard)
	countdownInputRow(255, 'Label:', 'label', $template['label'], $labelClass=null, 'VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');
else {
	hiddenElement('label', $template['label']);
	labelRow('Standard Template:', '', '<b>'.substr($template['label'], strlen($systemPrefix)).'</b>', '','','','',true);
}
$displayableTargetType = ($template['targettype'] == 'provider' ? 'sitter' : $template['targettype']).'s';
if(!$isStandard)
	radioButtonRow('Recipient type:', 'targettype', $template['targettype'], array('clients'=>'client','sitters'=>'provider'), $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null);
else {
	hiddenElement('targettype', $template['targettype']);
	labelRow('Recipient type:', '', '<b>'.$displayableTargetType.'</b>', '','','','',true);
}
countdownInputRow(255, 'Subject:', 'templatesubject', $template['subject'], $labelClass=null, 'VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');

$templateBody = $template['body'];
if($wysiwyg) {
	if(strip_tags($templateBody) == $templateBody)
		$templateBody = str_replace("\n", "<br>", str_replace("\n\n", "<p>", str_replace("\r", "", $templateBody)));
}

$testWarning = $wysiwyg ? " <b>RICH TEXT EDITOR AVAILABLE TO TESTERS ONLY</b>" : '';
textRow("Body of Email$testWarning", 'templatebody', $templateBody, $rows=15, $cols=80, $labelClass=null, $inputClass='fontSize1_2em', $rowId=null, $rowStyle=null, $maxlength=null);
$tokens = "#BIZNAME#, #BIZEMAIL#, #BIZPHONE#, #BIZHOMEPAGE#, #BIZLOGINPAGE#, #MANAGER#, #RECIPIENT#, #FIRSTNAME#, #LASTNAME#, #LOGO#, #LOGINID#, #TEMPPASSWORD#, #CREDITCARD# (clients only), #PETS# (clients only)";
if($template['extratokens']) $tokens .= ", ".$template['extratokens'];
echo "<tr><td colspan=2 style='tiplooks'>Substitution tokens: $tokens</td></tr>"; 
//checkboxRow('Personalize:', 'personalize', $template['personalize'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null);
checkboxRow('Active:', 'active', $template['active'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null);
//inputRow('Salutation:', 'salutation', ($template['salutation'] ? $template['salutation'] : ''));

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
//extract(extractVars('id,label,targettype,templatesubject,templatebody,personalize,salutation', $_REQUEST));

setPrettynames('label',"Template Label",'targettype','Recipient type','templatesubject','Subject line','templatebody','Body of Email');
function checkAndSave() {
	if(typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
	if(MM_validateForm('label', '', 'R',
											'targettype','', 'R',
											'templatesubject','', 'R',
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
	var url = 'email-template-preview.php?type='+target+'&body='+body+<?= $wysiwyg ? "'&noprep=1'" : "''"; ?>;
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
	document.templateeditor.action = 'email-template-edit.php'; //?action=preview
	document.getElementById('bodylength').value = document.getElementById('templatebody').value.length;
	document.getElementById('action').value='preview';
	document.templateeditor.submit();
	// 
}

function dropTemplate() {
	if(confirm('Are you sure you want to delete this email template?')) {
			document.getElementById('deleteTemplate').value=1;
  		document.templateeditor.submit();
		
	}
}

function resetTemplate() {
	if(confirm('Are you sure you want to reset this email template to the original "factory" version?  Any changes you have made will be lost.')) {
			document.getElementById('action').value='revert';
  		document.templateeditor.submit();
		
	}
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

<? if($action == 'preview' && !$errors) { ?>
	var url = 'email-template-preview.php?type=<?= $targettype ?>&cache=1<?= $wysiwyg ? "&noprep=1" : ""; ?>';
	openConsoleWindow('templatepreview', url,800,700)
<? } ?>
</script>
<?
// ***************************************************************************
//include "frame-end.html";
?>

