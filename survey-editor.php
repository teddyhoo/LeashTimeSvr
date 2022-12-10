<?
// survey-editor.php

require "survey-templates.php";
exit;


require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "survey-fns.php";
require_once "gui-fns.php";
require_once "preference-fns.php";

$locked = locked('o-,#rs');

$id = $_REQUEST['id'];

$contents = $_POST['contents'];
if($contents) {
	$contents = str_replace("\n", "", $contents);
	$validJSON = json_decode($contents, 1);
	if(!$validJSON) $error = 'Invalid JSON. <a target="_blank" href="https://jsonformatter.curiousconcept.com">JSON Tester</a>	';
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
		setPreference("survey_{$_POST['delete']}", null);
		$submissionIds = fetchRow0Col0("SELECT submissionid WHERE surveytemplateid = {$_POST['delete']}", 1);
		if($submissionIds) {
			deleteTable('tblsurveysubmission', "submissionid IN (".join(',', $submissionIds).")", 1);
			deleteTable('tblsurveyanswer', "submissionptr IN (".join(',', $submissionIds).")", 1);
		}
		$message = "Survey {$_POST['delete']} deleted..";
		$id = null;
		$contents = "";
	}
	else if($_POST['id']) {
		if(!$contents) $error = 'DELETE is not implemented in this way.';
		else {
			setSurveyTemplate($id, $contents);
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
foreach($surveyKeys as $surveyKey) {
	$qid = substr($surveyKey, 7);
	if("".(int)$qid != "$qid") continue;
	$surveys[] = fetchSurvey($qid);
}
foreach($surveys as $survey) {
	$row = array('surveyid'=>$survey['id'], 'name'=>$survey['name'], 'title'=>$survey['title']);
	$row['name'] = fauxLink($row['name'], "document.location.href=\"survey-editor.php?id={$row['surveyid']}\"", 1, 2);
	$row['review'] = fauxLink('review', "showSurvey({$row['surveyid']})", 1, 2);
	$rows[] = $row;
}

$survey = $id ? fetchSurvey($id) : null;
$contents = $id ? fetchRawSurvey($id) : $contents;
$pageTitle = 'Surveys';
require "frame.html";
if($error) echo "<p class='warning'>$error</p>";
if($message) echo "<p style='color:darkgreen;'>$message</p>";
$columns = explodePairsLine('surveyid|ID||name|Name||title|Title||review|review');
tableFrom($columns, $rows, 'WIDTH=50%');
//tableFrom($columns, $data, $attributes, $class, $headerClass, $headerRowClass, $dataCellClass, $columnSorts, $rowClasses, $colClasses);
//if($id) {
	if(!$survey && $id) echo "<p class='warning'>Survey JSON is invalid.</p>";
	if($survey['id'] != $id) echo "<p class='warning'>Survey ID does NOT match the preference ID.</p>";
	echo "<h2>Survey ID {$survey['id']}: {$survey['name']} </h2>";
	$url = globalURL("survey-form.php?id=f$id");
	if($id) echo "<p><a target=\"_blank\" href=\"$url\">Test Survey $id</a></p>";
	echoButton('', 'Save Changes', 'saveSurvey(0)');
	echo " - ";
	echoButton('', 'Save As New', 'saveSurvey(1)');
	if($id) {
		echo " - ";
		echoButton('', 'Delete', 'saveSurvey(-1)', 'HotButton', 'HotButtonDown');
	}
	echo "<form name='editor' method='POST'>";
	if($desc['id']) $submissionsToDate = fetchRow0COl0("SELECT COUNT(*) FROM tblsurveysubmission WHERE surveytemplateid = {$desc['id']}", 1);
	hiddenElement('id', $id);
	hiddenElement('delete', '');
	echo "<textarea class='fontSize1_2em' rows=30 cols=80 id='contents' name='contents' >$contents</textarea>";
	echo "</form>";
//}
?>
<script>
var submissionsToDate = <?= $submissionsToDate ?>;

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
	$.fn.colorbox({href:"survey-form.php?id="+id+"&showflags=1&omitsubmit=1", width:"650", height:"470", scrolling: true, iframe: true, opacity: "0.3"});	
}

</script>
<?
require "frame-end.html";

