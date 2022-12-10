<?
// survey-templates.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "survey-fns.php";
require_once "gui-fns.php";
require_once "preference-fns.php";

locked('o-');

if($_REQUEST['initialize']) {
	initializeDatabaseTables();
}

$id = $_REQUEST['id'];

$contents = $_POST['contents'];
if($contents) {
	$contents = str_replace("\n", "", $contents);
	$validJSON = json_decode($contents, 1);
	if(!$validJSON) $error = 'Invalid JSON. <a target="_" href="https://jsonformatter.curiousconcept.com">JSON Tester</a>	';
	else {
		$jsonId = $validJSON['id'];
		if($jsonId != $id) {
			if(!$id) $error = "Do not supply an ID for a new survey.";
			else $error = "ID's do not match.";
		}
	}
}

// For new version, clear out the id parameter before submit

if(!$error) {
	if($_POST['delete']) {
		$submissionIds = fetchCol0("SELECT submissionid FROM tblsurveysubmission WHERE surveytemplateid = {$_POST['delete']}", 1);
		setPreference("survey_{$_POST['delete']}", null);
		if($submissionIds) {
			deleteTable('tblsurveysubmission', "submissionid IN (".join(',', $submissionIds).")", 1);
			deleteTable('tblsurveyanswer', "submissionptr IN (".join(',', $submissionIds).")", 1);
		}
		$message = "Survey {$_POST['delete']} deleted..";
		$id = null;
		$contents = "";
	}
	else if($_POST['id']) {
		if(!$contents) $error = 'DELETE is not implemented.';
		else {
			setPreference("survey_$id", $contents);
			$message = "Survey $id saved.";
		}
	}
	else if($contents) { // NEW survey
		$id = getNextSurveyId();
		$contents = "{\"id\": $id, ".substr($contents, 1);
		setPreference("survey_$id", $contents);
		$message = "Survey $id saved.";
	}
	else if(array_key_exists('contents', $_POST)) // empty survey
		$error = 'No content supplied.';
}

$surveyKeys = fetchCol0("SELECT property FROM tblpreference WHERE property LIKE 'survey_%'");
$surveys = array();
foreach($surveyKeys as $surveyKey) {
	$sid = substr($surveyKey, 7);
	if("".(int)$sid != "$sid") continue;
	$surveys[$sid] = fetchSurvey($sid);
}
ksort($surveys);
foreach((array)$surveys as $survey) {
	$row = array('surveyid'=>$survey['id'], 'name'=>$survey['name'], 'title'=>$survey['title']);
	$nameAction = 
		$_SERVER["SCRIPT_NAME"] == "/survey-editor.php" ? 
			"document.location.href=\"survey-editor.php?id={$row['surveyid']}\""
			: "showSurvey({$row['surveyid']})";
	if($_SERVER["SCRIPT_NAME"] == "/survey-editor.php") {
		$row['name'] = fauxLink($row['name'], $nameAction, 1, 'Edit this survey template.');
		$row['review'] = fauxLink('review', "showSurvey({$row['surveyid']})", 1, 'Review this survey template.');
	}
	else $row['name'] = fauxLink($row['name'], $nameAction, 1, 'Review this survey template.');
	
	$row['link'] = globalURL("survey-form.php?id=f{$row['surveyid']}"); //jQuery('#selectcontainer div').click(selectText);
	$row['link'] = "<span onclick='selectElementText(this)'>{$row['link']}</span>";
	$rows[] = $row;
}

$survey = $id ? fetchSurvey($id) : null;
$contents = $id ? fetchRawSurvey($id) : $contents;
$pageTitle = 'Surveys';

$reviewSurveySubmissionsPermission = adequateRights('o-,#rs');
$submissionsLink = $reviewSurveySubmissionsPermission ? "<a href='reports-survey-submissions.php'>Survey Submissions</a> - " : '';

$breadcrumbs = "$submissionsLink<a href='reports.php'>Reports</a>";	
if(mattOnlyTEST()) {
	if($_SERVER["SCRIPT_NAME"] == "/survey-editor.php") $breadcrumbs = "<a href='survey-templates.php'>Survey Templates</a> - $breadcrumbs";
	else $breadcrumbs = "<a href='survey-editor.php'>Survey Editor</a> - $breadcrumbs";
}
require "frame.html";

if(!surveysAreEnabled()) {
	echo "Surveys are not enabled for this business.<p>";
	if(mattOnlyTEST()) 
		fauxLink('Iniialize database tables', 
			"if(confirm(\"Initialize tables?\")) document.location.href=\"survey-templates.php?initialize=1\"",
			null, null, null, "fauxlink fontSize1_5em bold"
			);
}
	



if($error) echo "<p class='warning'>$error</p>";
if($message) echo "<p style='color:darkgreen;'>$message</p>";
$columns = explodePairsLine('surveyid|ID||name|Name||title|Title||review|review||link|Link');
if($_SERVER["SCRIPT_NAME"] != "/survey-editor.php") unset($columns['review']);
tableFrom($columns, $rows, 'WIDTH=97%');
if($_SERVER["SCRIPT_NAME"] == "/survey-editor.php") {
	if(!$survey && $id) echo "<p class='warning'>Survey JSON is invalid.</p>";
	if($survey['id'] != $id) echo "<p class='warning'>Survey ID does NOT match the preference ID.</p>";
	echo "<h2>Survey ID {$survey['id']}: {$survey['name']} </h2>";
	$url = globalURL("survey-form.php?id=f$id");
	if($id) echo "<p><a target=\"_\" href=\"$url\">Test Survey $id</a></p>";
	echoButton('', 'Save Changes', 'saveSurvey(0)');
	echo " - ";
	echoButton('', 'Save As New', 'saveSurvey(1)');
	if($id) {
		echo " - ";
		echoButton('', 'Delete', 'saveSurvey(-1)', 'HotButton', 'HotButtonDown');
		echo " - ";
		echoButton('', 'Normalize Selection', 'normalizeContentSelection()', 'HotButton', 'HotButtonDown');
	}
	echo "<form name='editor' method='POST'>";
	hiddenElement('id', $id);
	hiddenElement('delete', '');
	if($survey['id']) $submissionsToDate = fetchRow0COl0($sql = "SELECT COUNT(*) FROM tblsurveysubmission WHERE surveytemplateid = {$survey['id']}", 1);
	echo "<textarea class='fontSize1_2em' rows=30 cols=80 id='contents' name='contents' >$contents</textarea>";
	echo "</form>";
}
?>
<script>
var submissionsToDate = <?= $submissionsToDate ? $submissionsToDate : '0'?>;
function saveSurvey(action) {
	var id = document.getElementById('id').value;
	if(action == 1) document.getElementById('id').value='';
	if(action == -1) {
		var andThese = submissionsToDate ? " and "+submissionsToDate+' submissions' : '';
		if(confirm("Delete Survey "+id+andThese+'?'))
			document.getElementById('delete').value=document.getElementById('id').value;
		else return;
	}
	document.editor.submit();
}

function showSurvey(id) {
	$.fn.colorbox({href:"survey-form.php?id="+id+"&showflags=1&omitsubmit=1", width:"750", height:"470", scrolling: true, iframe: true, opacity: "0.3"});	
}

function selectElementText(container) {
    if (document.selection) { // IE
        var range = document.body.createTextRange();
        range.moveToElementText(container);
        range.select();
    } else if (window.getSelection) {
        var range = document.createRange();
        range.selectNode(container);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
    }
}

function normalizeContentSelection() {
	var selectedtext = $('#contents').prop('selectedtext');
	if(selectedtext == '') {
		alert('Select some text first.');
		return;
	}
	var correctedText = selectedtext.replace(/\"/g, '&quot;');alert(correctedText);
	$('#contents').val($('#contents').val().replace(selectedtext, correctedText));
}

$(document).ready(function() {

	$('#contents').select(function(e) {
		$(e.target).prop('selectedtext', 
			e.target.value.substring(e.target.selectionStart, e.target.selectionEnd));});
		
});
</script>
<img src='art/spacer.gif' height=300>
<?
require "frame-end.html";

