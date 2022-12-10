<?
// survey-submission-view.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "survey-fns.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";


$locked = locked('o-,#rs');

extract(extractVars('start,end,print,surveynames,submitters,sort,csv,detail,mgrview', $_REQUEST));

if($detail) $_REQUEST['id'] = $detail;  // from reports-survey-submissions.php

if($_POST['delete']) {
	deleteSubmission($_POST['delete']);
	echo "<script>
	
	function refreshParent() {
		if(window.opener && window.opener.searchForMessages) window.opener.searchForMessages();
		else if(typeof parent.searchForMessages == 'function') parent.searchForMessages();
		else if(typeof parent.genReport == 'function'); parent.genReport();
	}

	
	refreshParent()</script>";
	exit;
}



if($_REQUEST['id']) { // MODE 1: DISPLAY
	$submission = fetchSurveySubmission($_REQUEST['id']);
	if($_GET['print']) {
		echo '<link rel="stylesheet" href="style.css" type="text/css" /> 
		<link rel="stylesheet" href="pet.css" type="text/css" />
		<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">'."\n";
		echo "<div class='fontSize1_2em' style='background:white;padding:5px;'>";
		//fauxLink("[print]<br>", "printPage(this);");
		echo "<div class='fa fa-print fa-2x' $buttonStyle title='Print survey' onclick='printPage(this);'></div>";

		displaySurveySubmission($submission, array('no reply'=>"<i>no reply</i>"));
		echo "</div>
		<script>function printPage(el) {el.style.display='none';window.print();el.style.display='inline';}</script>";
		exit;
	}
	if(!$omitOfficeNotes) {
		$officeonlynote = "{$submission['officeonlynote']}";
		$displayNote = $officeonlynote ? $officeonlynote : "<i>No office note</i>";
		echo "<div class='fontSize1_1em' style='background:lightgrey;margin-bottom:5px;' id='officeonlynote' name='officeonlynote'>"
					.str_replace("\n", "<br>", str_replace("\n\n", "<p>", $displayNote))
					."<p>".fauxLink('[edit]', "editOfficeOnlyNote()", 1, '')
					."</div>";
	}
	if(TRUE) {
		echo "<div align='right'>"
		.(TRUE ? // [print]
			"<div class='fa fa-print fa-2x' $buttonStyle title='Print survey' onclick='openConsoleWindow(\"sendsurvey\", \"survey-submission-view.php?print=1&id={$_REQUEST['id']}\",700,700)',700,700)'></div><img src='art/spacer.gif' width=20>"
			//fauxLink('<img src="art/printer20.gif">', "openConsoleWindow(\"printsurvey\", \"survey-submission-view.php?print=1&id={$_REQUEST['id']}\",700,700)", 1, '')."<img src='art/spacer.gif' width=20>"
			: '')
		."<div class='fa fa-envelope-o fa-2x' $buttonStyle title='Email survey' onclick='openConsoleWindow(\"sendsurvey\", \"survey-submission-email.php?id={$_REQUEST['id']}\",700,700)'></div>"
		//.fauxLink('[email]', "openConsoleWindow(\"sendsurvey\", \"survey-submission-email.php?id={$_REQUEST['id']}\",700,700)", 1, '')
		."</div>";
	}
	$extraHeadContent =
		'<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
		 <script type="text/javascript" src="jquery-1.7.1.min.js"></script>
		 <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
		 <script type="text/javascript" src="common.js"></script>
		 <link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';
	require_once "frame-bannerless.php";
	echo "<div class='fontSize1_1em' style='background:white;padding:5px;'>";
	displaySurveySubmission($submission, array('no reply'=>"<i>no reply</i>"));
	echo "</div>";
	if(mattOnlyTEST()) {
		echo "<form name='deleteSubmission' method='POST'>";
		hiddenElement('delete', $_REQUEST['id']);
		echoButton('', 'Delete', "if(confirm(\"Delete this submission?\")) document.deleteSubmission.submit();", 'HotButton', 'HotButtonDown');
		echo "</form>";
	}
?>
<script>
function editOfficeOnlyNote() {
	$.fn.colorbox({href:'survey-submission-view.php?edit=<?= $_REQUEST['id'] ?>', width:'500', height:'250', iframe: true, scrolling: true, opacity: '0.3'})
}

function refreshParent() {
	if(window.opener && window.opener.searchForMessages) window.opener.searchForMessages();
	else if(typeof parent.searchForMessages == 'function') parent.searchForMessages();
	else if(typeof parent.genReport == 'function'); parent.genReport();
}
</script>
<?
	exit;
}

if($_POST['save']) { // MODE 3: SAVE (in lightbox)
	updateTable('tblsurveysubmission', array('officeonlynote'=>$_POST['officeonlynote']), "submissionid={$_POST['save']}", 1);
	echo "<script>parent.$.fn.colorbox.close();parent.location.href='survey-submission-view.php?id={$_POST['save']}'</script>";
	exit;
}

if($_GET['edit']) { // MODE 2: EDIT (in lightbox)
	$submission = fetchSurveySubmission($_GET['edit']);
	$officeonlynote = "{$submission['officeonlynote']}";
	require_once "frame-bannerless.php";
	echo "<form name='officeonlynoteform' method='POST'><table>";
	hiddenElement('save', $_GET['edit']);
	textRow('Office Only Note', 'officeonlynote', $officeonlynote, $rows=3, $cols=60, $labelClass=null, $inputClass='fontSize1_2em', $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
	echo "</table>";
	echoButton('', 'Save', 'document.officeonlynoteform.submit()');
	echo "<img src='art/spacer.gif' height=1 width=30>";
	echoButton('', "Quit", 'parent.$.fn.colorbox.close();');	
	exit;
}


