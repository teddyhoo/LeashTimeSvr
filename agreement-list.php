<? // agreement-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "agreement-fns.php";
require_once "js-gui-fns.php";

// Determine access privs
$locked = locked('o-');

// AJAX
foreach(explode(',', 'latestAgreementVersionRequired,clientagreementrequired') as $prop) 
	if(array_key_exists($prop, $_GET)) {
		require_once "preference-fns.php";
		setPreference($prop, $_GET[$prop]);
		if($prop == 'clientagreementrequired') {
			require_once "common/init_db_common.php";
			updateTable('tblpetbiz', array('clientagreementrequired'=>($_GET[$prop] ? 1 : '0')), "bizid = {$_SESSION["bizptr"]}", 1);
		}
		exit;
	}

if($_REQUEST['yesHTML']) $_SESSION['agreementListHTMLEditor'] = $_REQUEST['yesHTML'];
if($_REQUEST['noHTML']) unset($_SESSION['agreementListHTMLEditor']);


$version = $_REQUEST['version'];
if($_POST) {
	if($_POST['action'] == 'delete') {
			$doomed = fetchRow0Col0("SELECT label FROM tblserviceagreement WHERE agreementid = {$_POST['version']} LIMIT 1", 1);
			$version = deleteTable('tblserviceagreement', "agreementid={$_POST['version']}", 1);
			$message = "Service agreement version #{$_POST['action']} [$doomed] was deleted.";
	}
	else if("{$_POST['agreementeditortoken']}" != "{$_SESSION['agreementeditortoken']}") 
		$message = "This version has already been saved.";
	else {
		$label = $_POST['label'];
		$terms = filterString($_POST['terms']);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { /*for($i=0;$i<strlen($terms);$i++) echo ord($terms[$i]).", ";*/echo $terms;exit; }		
		$html = $_POST['html'] ? 1 : '0';
		if(!$terms || !$label) $errors[] = 'Both Label and Terms are required.';
		else {
			$version = insertTable('tblserviceagreement', array('label'=>$label, 'terms'=>$terms, 'date'=>date('Y-m-d H:i:s'), 'html'=>$html), 1);
			$message = 'A new service agreement has been published.';
		}
	}
}
$_SESSION['agreementeditortoken'] = microtime(1);

//extract($_REQUEST);

$pageTitle = "Service Agreements"; //"Home";

$useHTMLEditor = $_SESSION['agreementListHTMLEditor'];
$theme = $useHTMLEditor == 'advanced' ? 'advanced' : 'simple';
if($useHTMLEditor) $extraHeadContent = "
<script type=\"text/javascript\" src=\"tinymce/jscripts/tiny_mce/tiny_mce.js\"></script>
<script type=\"text/javascript\">
	tinyMCE.init({
		mode : \"textareas\",
		theme : \"$theme\"
	});
</script>
";

$extraHeadContent .= '<script language="javascript">function showHelp() {
	$.fn.colorbox({html:"'.addSlashes(helpString()).'", width:"600", height:"450", scrolling: true, opacity: "0.3"});
}</script>';

function helpString() {
	$help = <<<HELP
<div class="fontSize1_2em"; style="padding:7px;">
On this page, you can specify:
<ul>
<li>The terms of the Service Agreement
<li>Whether your clients must sign a service agreement (once) before they can use LeashTime
<li>Whether or not ALL of your clients must sign the service agreement one time after it changes
</ul>
If a signed service agreement is required, when a client logs in, they will see the latest agreement&apos;s terms and be presented with the option to agree to the terms or to logout.  If the agreement is required, then it is required of all clients without exception.
<p>
When you create a new version of the service agreement, you can require all clients to sign the latest version before they next use LeashTime.
<p>
To create a new version (or the initial version) of the service agreement, supply a new label, and then type or paste in the terms of the agreement.
<p>
Unless the "These terms are HTML formatted" box is checked, then all single end-of-line characters will be converted to line break marks (&lt;br&gt;)  and double end-of-line characters will be converted to or paragraph marks (&lt;p&gt;) when the agreement is displayed.  Leave this box unchecked if your terms already include (&lt;br&gt;) or (&lt;p&gt;) tags.
</div>
HELP;
	return trim(str_replace("\r", "", str_replace("\n", "", $help)));

}

$breadcrumbs = '<img src="art/help.jpg" width=25 height=25 onclick="showHelp()" title="Show help for this page.">';


require "frame.html";

if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}

if($message) echo "<font color='green'>$message<p></font>";

$columns = explodePairsLine('date|Date||labelLink|Label');
$colClasses = array('datecolumn', null);
$agreements = getServiceAgreements();
$labelHashes = array();
foreach($agreements as $id => $agreement) {
	if($i > 0) $agreements[$id]['#ROW_EXTRAS#'] = 'style="background:#eeeeee;"';
	if($i == 1) $agreements[$id]['#ROW_EXTRAS#'] = 'style="background:#eeeeee;"';
	$agreements[$id]['date'] = shortDateAndTime(strtotime($agreement['date']), 'mil');
	$agreements[$id]['labelLink'] = fauxLink($agreement['label'], "editAgreement({$agreement['agreementid']})", 1);
	$agreementIds[] = $id;
	$labelHashes[] = "'".md5($agreement['label'])."'";
}
$rows = array();
if($agreements) {
	$rows[] = array('date'=>'<b>Local Current Version:</b>');
	$rows[] = $agreements[$agreementIds[0]];
	if(count($agreements) > 1) $rows[] = array('date'=>'<b>Old Versions:</b>');
	for($i=1; $i < count($agreements); $i++) 
		$rows[] = $agreements[$agreementIds[$i]];
}

?>
<style>
.currentVersionRow {background:white;};
.oldVersionRow {background:yellow;};
.datecolumn {width:150px;}
</style>
<?
if(TRUE || staffOnlyTEST() || $_SESSION['preferences']['latestAgreementVersionRequired']) {
	echo "<table>";
	$yesno = array('yes'=>'1', 'no'=>'0');
	radioButtonRow('Signed Service Agreement Required', 
									'clientagreementrequired', 
									$_SESSION['preferences']['clientagreementrequired'], 
									$yesno, 
									'updatePref(this)', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);

	radioButtonRow('Signature on Latest Service Agreement Required', 
									'latestAgreementVersionRequired', 
									$_SESSION['preferences']['latestAgreementVersionRequired'], 
									$yesno, 
									'updatePref(this)', $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $breakEveryN=null);
	echo "</table><hr>";
}

if($currentCorporateAgreement = getCurrentCorporateAgreement()) {
	$currentCorporateAgreement['date'] = shortDateAndTime(strtotime($currentCorporateAgreement['date']), 'mil');
	$currentCorporateAgreement['labelLink'] = fauxLink($currentCorporateAgreement['label'], "editAgreement(-{$currentCorporateAgreement['agreementid']})", 1);
	$rows[] = array('date'=>'<b>Global Current Version:</b>');
	$rows[] = $currentCorporateAgreement;
//print_r($currentCorporateAgreement);	
}
tableFrom($columns, $rows, 'width=70%; style="border:solid black 1px;margin-left:5px; "', $class, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses, $sortClickAction=null);


$agreement = $version > 0 ? $agreements[$version] : ($version < 0 ? getCorporateAgreementVersion(0 - $version) : array());
$hide = (!$agreement && $agreements) ? "display:none;" : "";
?>
<p>
<? 	if(!$agreements)
			echo "<div id='showeditorlink' class='tiplooks fontSize1_2em'>Enter your Service Agreement below.</div><p>";
		else 
			echo "<div id='showeditorlink' class='tiplooks fontSize1_2em' style='".($hide ? "" : "display:none;")."'>Click a link above to view a version of the Service Agreement.</div>";
?>
<div id='editordiv' style='padding:10px;background:lightblue;<?= $hide ?>'>
<form name='agreementeditor' method='POST'>
<table>
<?
hiddenElement('version', $version);
hiddenElement('action', '');
hiddenElement('agreementeditortoken', $_SESSION['agreementeditortoken']);
?>
<tr><td colspan=2>
<?
echoButton('', 'Save as New Local Version', 'checkAndSubmit()');
echo "<img src='art/spacer.gif' width=20 height=1>";
echoButton('', 'Cancel', 'document.location.href = "agreement-list.php"');
echo "<img src='art/spacer.gif' width=20 height=1>";
echoButton('', 'Preview', 'openConsoleWindow("agreementpreview", "agreement-preview.html", 600, 600)');

if(staffOnlyTEST() && $version) {
	echo " ";
	// count signed copies of this version
	$sigs = countSignatures($version);
	echo " ";
	if($sigs) echo "<i>$sigs signatures.</i>";
	else echoButton('', 'Delete', "deleteAgreement($version)", 'HotButton', 'HotButtonDown', null, 'STAFF ONLY, when no signatures');
	if($_SESSION['agreementListHTMLEditor'])
		fauxLink('Plain Editor', 'document.location.href="agreement-list.php?noHTML=1"');
	echo " - ";
	if($_SESSION['agreementListHTMLEditor'] !== '1')
		fauxLink('Simple HTML Editor', 'document.location.href="agreement-list.php?yesHTML=1"');
	echo " - ";
	if($_SESSION['agreementListHTMLEditor'] !== 'advanced')
		fauxLink('Advanced HTML Editor', 'document.location.href="agreement-list.php?yesHTML=advanced"');
}
?>
</td></tr>
<?
if($version) {
	labelRow('Version date: ', '', $agreement['date']);
	labelRow('Version: ', '', ($version < 0 ? '<b>(Global)</b> ' : '').$agreement['label'], '', '', '', '', $rawValue=true);
}


$rows = $useHTMLEditor ? 35 : 30;


countdownInputRow(255, 'New Label:', 'label', '', $labelClass=null, $inputClass='VeryLongInput', $rowId=null,  $rowStyle=null, $onBlur=null, $position='underinput');
checkboxRow('These terms are HTML formatted', 'html', $agreement['html']);
textRow('Terms: ', 'terms', $agreement['terms'], $rows, $cols=100, $labelClass=null, $inputClass=null);
?>
</table>
</form>
</div>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='rsa.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
setPrettynames('label', 'A Label for this version','terms','A set of Terms for this version');
var labelHashes = new Array(<?= join(',', $labelHashes) ?>);

function updatePref(el) {
	var propNVal = el.id.split('_');
	$.ajax({url:"agreement-list.php?"+propNVal[0]+"="+propNVal[1],
					success: function(data) {alert('Setting has been changed.');}});
}

function deleteAgreement(version) {
	if(confirm("You are about to delete this version.  Proceed?")) {
		document.getElementById('action').value = 'delete';
		document.agreementeditor.submit();
	}
}

function editAgreement(version) {
	document.location.href = "agreement-list.php?version="+version;
}

function checkAndSubmit(version) {
	if(typeof tinymce !== 'undefined') { tinymce.triggerSave(); }
	
	var dupLabel = '';
	document.getElementById('label').value = jstrim(document.getElementById('label').value);
	document.getElementById('terms').value = jstrim(document.getElementById('terms').value);
	var labelHash = ""+hex_md5(document.getElementById('label').value);
	for(var i=0;i<labelHashes.length;i++) {

		if(labelHashes[i] == labelHash)
			dupLabel = 'This label is already in use.';
	}
	if(MM_validateForm('label', '', 'R',
											'terms', '', 'R',
											dupLabel, '', 'MESSAGE'))
			document.agreementeditor.submit();
}

$(document).ready(function() {
	$('#clientagreementrequired_0').parent().parent().children().first().each(function (i, el) {
		el.title = 'Client must sign agreement (check "I agree") when logging in the first time.';
		el.style.cursor='pointer';
	});
	$('#latestAgreementVersionRequired_0').parent().parent().children().first().each(function (i, el) {
		el.title = 'Signature is required for latest version of agreement, even if an earlier version was signed.';
		el.style.cursor='pointer';
	});
});
</script>
<?
require "frame-end.html";
?>

