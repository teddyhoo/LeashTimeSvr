<?
// survey-form.php

use Aws\S3\S3Client;

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "survey-fns.php";

require_once "remote-file-storage-fns.php";
require_once 'aws-autoloader.php';


locked('+o-,+p-,+c-');

$id = $_REQUEST['id'];

if(substr("$id", 0, 1) == 'f') {
	$frameit = true;
	$id = substr("$id", 1);
}

// SANITIZE DATA HERE
if("".(int)"$id" != $id) $id = 0; // against injection attacks 8/26/2020

// BRUTE FORCE SANITIZE
if(suspiciousSubmission((array)$_POST)) $id = 0; // against injection attacks 8/26/2020

$survey = fetchSurvey($id);

if(!$survey) $error = "Survey not found.";
else if(!userCanSubmitSurvey($survey) && !$_REQUEST['omitsubmit']) $error = "Insufficient access rights.";
if($error) {
	echo $error;
	exit;
}

if($id && userRole() != 'c' && in_array('setnotability', array_keys($_REQUEST))) {
	setSurveyNotableProperty($id, $_REQUEST['setnotability']);
	$survey = fetchSurvey($id);
}
	
unset($_GET['setnotability']);
unset($_POST['setnotability']);

if($_REQUEST['thanks']) {

	if($frameit) {
		require_once "gui-fns.php";  // for responsive version
		require 
			$_SESSION["responsiveClient"] ? "frame-client-responsive.html" : (
			usingMobileSitterApp() ? "mobile-frame.php" : "frame.html");
		if(usingMobileSitterApp()) echo "<div style='padding:5px;'>";
	}
	$thankYou = $survey['thankyou'];
	if(!$thankYou) {
		$thankYou = "<h2>Thank You</h2><div class='fontSize1_4em'>Your response to the <i>#TITLE#</i> survey has been recorded.<p>Thank you.<p>Return to <a href='index.php'>Home</a> page.</div><center>#LOGO#</center>";
	}
	$thankYou = surveyMerge($thankYou);
	$thankYou = str_replace('#TITLE#', $survey['title'], $thankYou);

	echo $thankYou;
	if($frameit) {
		if(usingMobileSitterApp()) echo "</div>";
		else require $_SESSION["responsiveClient"] ? "frame-client-responsive-end.html" : "frame-end.html";
	}
	exit;
}

$options = array('highlightFlaggedChoices'=>$_REQUEST['showflags'], 'omitSubmitButton'=>$_REQUEST['omitsubmit']);

if($_POST) {
	//echo print_r($_POST, 1)."<hr><hr>";
	$submissionIdOrError = storeSurveySubmission($_POST);
	if(is_array($submissionIdOrError)) {
		$error = $submissionIdOrError['error'];
		$options['formfill'] = $_POST;
	}
	else {
		generateSurveySubmissionNotification($submissionIdOrError);
		$thankYou = $survey['thankyou'];
		if(strpos($thankYou, 'http') === 0) $redirect = $thankYou;
		else $redirect = globalURL("survey-form.php?id={$_REQUEST['id']}&thanks=1");
		session_write_close();
		header("Location: $redirect");
		exit;
	}
	//echo "<p>Subission number: $submissionId</p>";
	// Where should we redirect?
}

$highlightFlaggedChoices = $_REQUEST['showflags'];


$extraHeadContent = "<style>#InnerMostFrame {font-size:1.2em;}</style>";
if($frameit) {
	require_once "gui-fns.php";  // for responsive version
	require 
		$_SESSION["responsiveClient"] ? "frame-client-responsive.html" : (
		usingMobileSitterApp() ? "mobile-frame.php" : "frame.html");
	if(usingMobileSitterApp()) echo "<div style='padding:5px;'>";
}
else echo '<link rel="stylesheet" href="style.css" type="text/css" />
<link rel="stylesheet" href="pet.css" type="text/css" />
<style>body {padding:5px;font-size:1.0em;background-image:none;}</style>';

echo "<style>.choices {margin-left:10px;} .choices td {padding-left:12px;}</style>";
if($error) echo "<p class='warning'>$error</p>";
basicHTMLSurveyForm($survey, $options);
if($frameit) {
	if(usingMobileSitterApp()) echo "</div>";
	else require $_SESSION["responsiveClient"] ? "frame-client-responsive-end.html" : "frame-end.html";
}
exit; 


/* PLAN
Surveys

Survey description

JSON object in tblpreference
id - corresponds to NNN in preference property surveyNNN
name
audience - provider|client|user
title
intro
sections [{sectionid, label, intro},...]
questions [{qid, sequence, name, prompt, type, length, sectionid, answers [{name, label,value, required},...]}}, ...]
qnumbering none|persection|simple

tblsurveysubmission
submissionid
surveytemplateid - corresponds to NNN in preference property surveyNNN
submitted - datetime
submitterid


tblsurveyanswer
answerid - corresponds to question name or "{question id}_{answer index}"
value



Incident Reports. 

(Idea floated 2018-05-17 in ticket #18828 https://leashtime.com/support/admin/admin_ticket.php?track=NBV-DYS-ABTX

Incidents Log and Employee Review Reports Specification

1. An Incidents log which will list dated incidents that involve sitters and/or clients.
1.a Incident type to be choosable from an editable list of incident types.
1.a.1. Incident type to have a unique label, positive/negative/neutral designation.
1.a.2. Example incident types Kudos, "Above and Beyond" (positive), Complaint, "rejected job" (negative), Service Feedback, observation (neutral).
1.b. Individual incidents will be dated with the following core set of fields: recordedby, sitter, client, clientvisible(Y/N), sittervisible(Y/N), rating, resolution, resolutiondate, notes
1.b.1 Rating is a plain field whose use is up to the business. Useful when the biz employs a rating scale in service feedback reports. Applicable to positive, negative, and neutral incidents.
1.b.2. Unclear to me whether we want to go down the resolution, resolutiondate rabbit hole since that suggests more complicated workflows than are necessarily warranted.
1.c. Incident log to be viewable from client's perspective and from sitter's perspective.
1.d Incidents log to be used as a feed to Employee Review Reports.
1.e Exportable as spreadsheet.

2. Employee Review Reports - allow a manager to review a sitter's performance using a rollup of various reports for a given period:
2.a. Incidents log (number of positive, negative, and neutral incident types, a count by incident type, links to review incidents in detail.
2.b. Days Off taken in review period
2.c. Visit count and revenue generated in period.
2.d. Review of Do Not Serve List with reasons for DNS entries.
2.e. Other stats as available.

*/