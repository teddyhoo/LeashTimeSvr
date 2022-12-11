<?
// survey-fns.php
function usageStats($force=false) {
	$status = array('enabled'=>surveysAreEnabled($force));
	if(!$status['enabled']) return $status;
	$status['submissions'] = fetchRow0Col0("SELECT COUNT(DISTINCT submissionid) FROM tblsurveysubmission");
	$status['templates'] = fetchRow0Col0("SELECT COUNT(*) FROM tblpreference WHERE property LIKE 'survey_%'");
	$status['firstsubmission'] = fetchRow0Col0("SELECT submitted FROM tblsurveysubmission ORDER BY submitted LIMIT 1");
	$status['lastsubmission'] = fetchRow0Col0("SELECT submitted FROM tblsurveysubmission ORDER BY submitted DESC LIMIT 1");
	
	return $status;
}

function surveysAreEnabled($force=false) {
	static $verdict;
	if($force || !$verdict) {
		$tables = fetchCol0("SHOW TABLES");
		$verdict = in_array('tblsurveysubmission', $tables) ? 'YES' : 'NO';
	}
	return ($verdict == 'YES');
}

/* 
NAG plan:
Allow nags to be managed from nags-list.php
...
At login time, call getSurveyNag() to find any applicable nag.
	e.g., array(array('surveyid'=>3, 'nagtext'=>'Please do this or that.', 'setdate'=>)
If found, $_SESSION['user_notice'] = $nag['nagtext']
...
In storeSurveySubmission($post), call clearNag($recipient=null, 'surveynag') 
*/


function surveyNags() {
/* return array('client'=>array(34=>array('clientid'=>34, 'surveyid'=>3, 'setdate'=>'...'),...),
		'provider'=>array(array('providerid'=>34, 'surveyid'=>3, 'setdate'=>'...'),...),
*/
	$surveyNames = getSurveyNamesById();
	$surveyTitles = getSurveyTitlesById();
	foreach(fetchAssociationsKeyedBy(
		"SELECT clientptr, value as nag, fname, lname,
			CONCAT_WS(', ', lname, fname) as sortname, 
			CONCAT_WS(' ', fname, lname) as name
			FROM tblclientpref 
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE property = 'surveynag'
			ORDER BY sortname", 'clientptr', 1) as $clientid =>$row) {
			
		$nags['client'][$row['clientptr']] = $row;
		$nag = json_decode($row['nag'], 'ASSOC');
		$nags['client'][$clientid]['nag'] = $nag;
		$nags['client'][$clientid]['nag']['surveyname'] = $surveyNames[$nag['surveyid']];
		$nags['client'][$clientid]['nag']['title'] = $surveyTitles[$nag['surveyid']];
	}
	foreach(fetchAssociationsKeyedBy(
		"SELECT providerptr, value as nag, fname, lname,
			CONCAT_WS(', ', lname, fname) as sortname, 
			CONCAT_WS(' ', fname, lname) as name
			FROM tblproviderpref 
			LEFT JOIN tblprovider ON providerid = providerptr
			WHERE property = 'surveynag'
			ORDER BY sortname", 'providerptr', 1) as $providerid =>$row) {
		$nags['provider'][$row['providerptr']] = $row;
		$nag = json_decode($row['nag'], 'ASSOC');
		$nags['provider'][$providerid]['nag'] = $nag;
//print_r($nags['provider'][$providerid]);echo "<hr>";
		$nags['provider'][$providerid]['nag']['surveyname'] = $surveyNames[$nag['surveyid']];
		$nags['provider'][$providerid]['nag']['title'] = $surveyTitles[$nag['surveyid']];
	}
	foreach(fetchAssociationsKeyedBy(
		"SELECT userptr, value as nag
			FROM tbluserpref
			WHERE property = 'surveynag'", 'userptr', 1) as $userid =>$row) {
		$nags['staff'][$row['userptr']] = $row;
		$nag = json_decode($row['nag'], 'ASSOC');
		$nags['staff'][$userid]['nag'] = $nag;
//print_r($nags['provider'][$providerid]);echo "<hr>";
		$nags['staff'][$userid]['nag']['surveyname'] = $surveyNames[$nag['surveyid']];
		// look up staff name and populate fname, lname, sortname, name
		// sort by sortname
	}
  return (array)$nags;
}
	
function getDisplayableSurveyNagText($recipient=null) {
	if(!($nag = getSurveyNag($recipient))) return;	
	return withHTMLEolns($nag['nagtext']);
}
	
function getSurveyNag($recipient=null) {
	// This returns a nag for the recipient named (or logged in, if not named).
	// nag structure, e.g., : array(array('surveyid'=>3, 'nagtext'=>'Please do this or that.')
	if(!$recipient)
		$recipient = sessionRoleIdentity();
	return getRecipientNag($recipient, 'surveynag');
}


function setSurveyNag($recipients, $surveyid, $nagText=null, $linkLabel=null, $persistent=null) {
	// $persistent is not yet offered in the editor, but when set the nag will display every time frame is rendered.
	$nagText = $nagText ? $nagText : "Please submit this #SURVEYLINK# at your earliest convenience";
	if(!$linkLabel) {
		$survey = fetchSurvey($surveyid);
		$linkLabel = $survey['name'];
	}
	$link = "<a href=\"".globalURL("survey-form.php?id=f$surveyid")."\">$linkLabel</a>";
	if(strpos($nagText, '#SURVEYLINK#') !== FALSE || strpos($nagText, 'survey-form.php?id=f') !== FALSE)
		$nagText = str_replace('#SURVEYLINK#', $link, $nagText);
	else $nagText .= $link;
	require_once "field-utils.php";
	$nagText = addslashes(withHTMLEolns($nagText));
	$nagObject = 
		array(
			'surveyid'=>$surveyid, 
			'nagtext'=>$nagText, 
			'setdate'=>date('Y-m-d H:i:s'), 
			'persistent'=>($persistent ? "true" : "false"));
	setNag($recipients, $nagObject, $nagText, 'surveynag');
}
	
function getRecipientNag($recipient=null, $nagProperty) {
	// This returns a nag for the recipient named (or logged in, if not named).
	// nag structure, e.g., : array(array('surveyid'=>3, 'nagtext'=>'Please do this or that.', 'setdate'=>)
	if(!$recipient)
		$recipient = sessionRoleIdentity();
		//array($submittertable=>$submitterid, 'sessionroletable'=>$sessionroletable, 'sessionroleid'=>$sessionroleid);
	if($nagAddress = getNagAddress($recipient, $nagProperty)) {
		$nag = fetchRow0Col0(
			"SELECT value FROM {$nagAddress['preftable']}
				WHERE {$nagAddress['prefownerfield']} = {$nagAddress['prefownerid']}
							AND property = '$nagProperty'
				LIMIT 1", 1);
		if($nag) return json_decode($nag, 'ASSOC');
	}
}


function clearNag($recipient=null, $nagProperty) {
	// clears recipient's nag, if found.
	// works for any nag type
	if(!$recipient)
		$recipient = sessionRoleIdentity();
	if(!$recipient) return null;
	// e.g., DELETE FROM tblproviderpref WHERE providerptr = 39 AND 'property' = 'surveynag'
	if($nagAddress = getNagAddress($recipient, $nagProperty)) {
		deleteTable($nagAddress['preftable'], "{$nagAddress['prefownerfield']} = {$nagAddress['prefownerid']}
							AND property = '$nagProperty'", 1);
	}
}

function setNag($recipients, $nagObject, $nagText, $nagProperty) {
	// this function separates the nag functionality from the survey functionality for possible reuse.
	// recipients may be
	// array(array('clientid'=>47, ...), array('clientid'=>48, ...),...)
	// OR array('clientid'=>47, ...)
	// should we consider a start and expiration?
	// a recipient may have at most one survey nag at a time, stored in their preference table under property 'surveynag'.
	
	if(!$recipients[0] || !is_array($recipients[0])) $recipients = array($recipients);

	foreach($recipients as $r) {
		if($nagAddress = getNagAddress($r, $nagProperty)) {
//echo "BANG! ".print_r($nagAddress, 1);	
			replaceTable($nagAddress['preftable'], 
				array($nagAddress['prefownerfield']=>$nagAddress['prefownerid'],
							'property'=>$nagAddress['property'],
							'value'=>json_encode($nagObject)), 1);
		}
	}
}

function getNagAddress($recipient, $nagProperty='surveynag') {
	/* returns e.g.: array('preftable'=>'tblproviderpref', 
									'prefownerfield'=>'providerptr',
									'prefownerid'=>45,
									'property'=>'surveynag')
	*/
	// $recipient may be an array with clientid, providerid, or userid key
	// OR an array with sessionroletable key
	if($recipient['sessionroletable'])
		$recipient[substr($recipient['sessionroletable'], 3).'id'] = $recipient['sessionroleid'];
	$submittertable = 
		$recipient['clientid'] ? 'tblclientpref' : (
		$recipient['providerid'] ? 'tblproviderpref' : (
		$recipient['userid'] ? 'tbluserpref' : null));
	if($submittertable) {
		$ptrfields = array('tblclientpref'=>'clientptr', 'tblproviderpref'=>'providerptr', 'tbluserpref'=>'userptr');
		$submitteridfield = $idfields[$submittertable];
		$idfields = array('tblclientpref'=>'clientid', 'tblproviderpref'=>'providerid', 'tbluserpref'=>'userid');
		return array('preftable'=>$submittertable, 
									'prefownerfield'=>$ptrfields[$submittertable],
									'prefownerid'=>$recipient[$idfields[$submittertable]],
									'property'=>$nagProperty);
	}
}
			
// END NAG FUNCTIONS

function formatAnswer($value, $answerSubstitutes=null) {
	$answerSubstitutes= (array)$answerSubstitutes;
	$txt = $answerSubstitutes[$value];
	return $txt ? $txt : $value;
}

function setSurveyNotableProperty($surveyIdOrSurveyString, $notable) {
	if($surveyIdOrSurveyString && "".(int)"$surveyIdOrSurveyString" == $surveyIdOrSurveyString) {
		$surveyId = $surveyIdOrSurveyString;
		if(!$surveyString = fetchRawSurvey($surveyIdOrSurveyString)) return;
	}
	else {
		$surveyString = $surveyIdOrSurveyString;
		$survey = str_replace("\n", "", $survey);
		$survey = json_decode($survey, 1);
		$surveyId = $survey['id'];
	}
	$notable = $notable ? 'true' : 'false';
	$propPos = findPropertyPosition($surveyString, "surveyisnotable");
	$replacement = "\"surveyisnotable\":\"$notable\"";
	if($propPos) $final = str_replace($propPos['fragment'], $replacement, $surveyString);
	else {
		$insertPosition = findPropertyPosition($surveyString, "name");
		
		if(substr($surveyString, $insertPosition['end'], 1) == ',') {
			$final = substr_replace(
									$surveyString, 
									"\n$replacement,\n", 
									$insertPosition['end']+1, 
									0);
//echo "<p>FINAL ($surveyId) => [$surveyString]<p>[$replacement]<p>[".($insertPosition['end']+1)."]==><p>$final";
}
//else echo "OOPS: [".substr($surveyString, $insertPosition['end'], 1)."]";
	}
//echo "<p>FINAL (survey_$surveyId) => <p>$final";
	if($final) setSurveyTemplate($surveyId, $final);

	return $final;
}

function setSurveyTemplate($surveyId, $contents) {
	require_once "preference-fns.php";
	setPreference("survey_$surveyId", $contents);
}

function findPropertyPosition($surveyIdOrSurveyString, $property) {
//echo "<p>[$surveyIdOrSurveyString][$property]=>$insertPosition<hr>";
//echo "<p>BING \"$property\" IN $surveyIdOrSurveyString";
	if($surveyIdOrSurveyString && "".(int)"$surveyIdOrSurveyString" != $surveyIdOrSurveyString)
		$surveyString = $surveyIdOrSurveyString;
	else if(!($surveyString = fetchRawSurvey($surveyIdOrSurveyString))) return;

	$start = strpos($surveyString, "\"$property\":");
//echo "<p>BANG \"$property\": [".print_r($start, 1)."] => [$replacement]<p>$surveyString";
	if(!$start) return;
	$end = strpos($surveyString, ",", $start);
	$end = $end ? $end : strpos($surveyString, "}", $start);
	return $end ? array('start'=>$start, 'end'=>$end, 'fragment'=>substr($surveyString, $start, $end-$start))
				: null;
}

function fetchSubmitter($submission) {
	static $people, $mgrs;
	$peoplekey = "{$submission['submittertable']}_{$submission['submitterid']}";
	$people = (array)$people;
	if($_SESSION && $people[$peoplekey]) return $people[$peoplekey]; // breaks in non-session mode
//echo "peoplekey: $peoplekey ".print_r($people,1)."<hr>\n";	
	
	$idByType = explodePairsLine('tblclient|clientid||tblprovider|providerid||tbluser|userid');
	$idField = $idByType[$submission['submittertable']];
	$role = substr($idField, 0, strrpos($idField, 'id'));
	if($submission['submittertable'] == 'tbluser') {
		if(!$mgrs) {
			require_once "system-login-fns.php";
			$mgrs = getManagerUsers($details=true);
		}
		$mgr = $mgrs[$submission['submitterid']];
		$mgr['name'] = "{$mgr['fname']} {$mgr['lname']}";
		$mgr['sortname'] = "{$mgr['lname']}, {$mgr['fname']}";
		$mgr['role'] = $role;
		$mgr['roleid'] = $mgr['userid'];
		$people[$peoplekey] = $mgr;
	}
	else $people[$peoplekey] =
		fetchFirstAssoc($sql = 
			"SELECT $idField as roleid, fname, lname, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(', ', lname, fname) as sortname, '$role' as role
				FROM {$submission['submittertable']}
				WHERE $idField = {$submission['submitterid']}
				LIMIT 1", 1);
//echo "peoplekey ({$submission['submittertable']}): $peoplekey<hr>\n".print_r($people[$peoplekey], 1);	
				
	return $people[$peoplekey];
}

function findSubmissionsConcerning($person, $omitIds=null) {
	$tableIds = explodePairsLine('clientptr|clientid||providerptr|providerid||userptr|userid'); // userptr not a thing
	foreach($tableIds as $submissionField=>$idfield) {
		if($personId = $person[$idfield]) {
			break;
		}
	}
	if($omitIds) $omitIds = "AND submissionid NOT IN (".join(',', $omitIds).")";

	return fetchAssociations(
		"SELECT * 
			FROM tblsurveysubmission 
			WHERE $submissionField = $personId $omitIds
			ORDER BY submitted", 1);
}
	


function findSubmissionsFrom($person) {
	$tableIds = explodePairsLine('tblclient|clientid||tblprovider|providerid||tbluser|userid');
	foreach($tableIds as $table=>$idfield) {
		if($person[$idfield]) {
			$submitterid = $person[$idfield];
			$submittertable = $table;
			break;
		}
	}
			if(mattOnlyTEST()) print_r("");

	return fetchAssociations(
		"SELECT * 
			FROM tblsurveysubmission 
			WHERE submitterid = $submitterid AND submittertable = '$submittertable'
			ORDER BY submitted", 1);
}
	
function fetchSurveySubmission($id) {
	return fetchFirstAssoc("SELECT * FROM tblsurveysubmission WHERE submissionid = $id", 1);
}

function displaySurveySubmission($id, $answerSubstitutes=null, $flaggedAnswerClass='warning', $identifySubmitter=true) {
	$submission = $id && is_array($id) ? $id : fetchSurveySubmission($id);
	$answersRaw = fetchAssociations("SELECT * FROM tblsurveyanswer WHERE submissionptr = {$submission['submissionid']}", 1);
	foreach($answersRaw as $ans)
		$answers[$ans['questionid']][$ans['answerid']] = $ans['value'];
	$desc = fetchSurvey($submission['surveytemplateid']);
	if($desc['title']) echo "<h2>{$desc['title']}</h2>\n";
	$version = $desc['versionlabel'] ? $desc['versionlabel'] : '';
	$version .= " ({$desc['id']})";
	$versiondate = strtotime($desc['versionlabel']);
	$versiondate = $versiondate ? shortDateAndTime($versiondate) : $desc['versiondate'];
	echo "<p style='font-size:0.7em;color:gray;font-style:italic;'>$version $versiondate</p>";
	echo "<p style='font-size:0.7em;color:gray;font-style:italic;'>Submission ID: {$submission['submissionid']}</p>";
	echo "<p>Submitted: ".shortDateAndTime(strtotime($submission['submitted']));
	$submitterRoleLabels = array('tblclient'=>'client', 'tblprovider'=>'sitter', 'tbluser'=>'staff');
	if($identifySubmitter) {
		$submitter = fetchSubmitter($submission);
		echo " by {$submitter['name']} ({$submitterRoleLabels[$submission['submittertable']]})";
	}
	if($submission['clientptr']) {
		$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$submission['clientptr']} LIMIT 1", 1);
		echo "<p>Client concerned: <b>$clientName</b>";
	}
	if($submission['providerptr']) {
		$providerName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$submission['providerptr']} LIMIT 1", 1);
		echo "<p>Sitter concerned: <b>$providerName</b>";
	}
	
	

	$submissionScores = scoreSubmission($submission);
	if($submissionScores['scorable']) {
		$modeValue = $submissionScores['mode'] == -1 ? '<i>no mode</i>' : $submissionScores['mode'];
		$highScore = $desc['scorable'] == (int)"{$desc['scorable']}" ? "/{$desc['scorable']}" : '';
		$stats = "Average answer (mean): {$submissionScores['mean']}$highScore
			Scored answers: {$submissionScores['scoredanswers']} 
			Answer total: {$submissionScores['totalscore']}
			Mode answer: $modeValue
			Median answer: {$submissionScores['median']}";
		$titlestats = str_replace("\n", '', str_replace("\t", "", $stats));
		echo "<div class='fauxlink' onclick='$(\"#scorestats\").toggle();' title='$titlestats'>Score: {$submissionScores['mean']}$highScore</div>";
		echo "<div id='scorestats' style='display:none;padding:15px;background:#CCFF9D;'>".(str_replace("\n", "<br>", $stats))."</div>";
	}
	
	echo "</p>";
	if($desc['intro']) echo "<p>{$desc['intro']}</p>\n";
	foreach($desc['questions'] as $q) {
		$qid = $q['qid'];
		if($q['paragraph']) {
			echo "<section style='color:black;'>{$q['paragraph']}</section>";
			continue;
		}
		if($desc['qnumbering']) {
			if($desc['qnumbering'] == 'simple') $qnum += 1;
			// else if persection reset to 1 if new section
		}
		if($q['type'] == 'radios') {
			$useUnorderedList = 1;
			$valueFormat = $useUnorderedList ? '<li CLASS>VALUE' : '<span CLASS>VALUE</span>';
			echo $rflag;
			if($qnum) echo "$qnum. ";
			echo "\n{$q['prompt']}<br>";
			if($useUnorderedList) echo "<ul>";
			foreach($q['answers'] as $aid => $a) {
				if(array_key_exists($aid, (array)$answers[$qid]))
					$v = $answers[$qid][$aid];
				else $v = formatAnswer('no reply', $answerSubstitutes);
				$choices = getChoices($a);
				$vf = choiceIsFlagged($v, $choices) ? "class='$flaggedAnswerClass'" : "";
				$vf = str_replace('CLASS', $vf, $valueFormat);
				$vf = str_replace('VALUE', $v, $vf);
				
				echo "$vf - {$a['label']}<br>";
			}
			if($useUnorderedList) echo "</ul>";
			echo "<p>";
		}
		else if($q['type'] == 'radio') {
			$aid = 0;
			$useUnorderedList = 1;
			$valueFormat = $useUnorderedList ? '<li CLASS>VALUE' : '<span CLASS>VALUE</span>';
			echo $rflag;
			if($qnum) echo "$qnum. ";
			echo "\n{$q['prompt']}<br>";
			if($useUnorderedList) echo "<ul>";
			foreach($q['answers'] as $a) {
				if(array_key_exists($aid, (array)$answers[$qid]))
					$v = $answers[$qid][$aid];
				else $v = formatAnswer('no reply', $answerSubstitutes);
				$choices = getChoices($a);
				$vf = choiceIsFlagged($v, $choices) ? "class='$flaggedAnswerClass'" : "";
				$vf = str_replace('CLASS', $vf, $valueFormat);
				$vf = str_replace('VALUE', $v, $vf);
				echo "$vf<br>";
			}
			if($useUnorderedList) echo "</ul>";
			echo "<p>";
		}
		else if($q['type'] == 'textbox') {
			$aid = 0;

	
			$a = $q['answers'][0];
			if(array_key_exists($aid, (array)$answers[$qid])) {
				$v = $answers[$qid][$aid];
				$rawV = $v;
			}
			else if(!$v) $v = formatAnswer('no reply', $answerSubstitutes);
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			if($rawV && answerIsFlagged($a))
				$prompt = "<span class='$flaggedAnswerClass'>$prompt</a>";

			echo "\n$prompt<br>";
			//if($v) echo "\n<p>$v</p>>";
			//else {
				$useUnorderedList = 1;
				if($useUnorderedList) echo "<ul><li>";
				echo formatAnswer($v, $answerSubstitutes);
				if($useUnorderedList) echo "</ul>";
		//}
			//echo "<p>";
		}
		else if($q['type'] == 'date') {
			$aid = 0;
		//echo " qid: $qid, aid: $aid";print_r($answers);
			$a = $q['answers'][0];
			if(array_key_exists($aid, (array)$answers[$qid])) {
				$v = $answers[$qid][$aid];
				$rawV = $v;
			}
			else if(!$v) $v = formatAnswer('no reply', $answerSubstitutes);
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			if($rawV && answerIsFlagged($a))
				$prompt = "<span class='$flaggedAnswerClass'>$prompt</a>";

			echo "\n$prompt<br>";
			//if($v) echo "\n<p>$v</p>>";
			//else {
				$useUnorderedList = 1;
				if($useUnorderedList) echo "<ul><li>";
				echo formatAnswer($v, $answerSubstitutes);
				if($useUnorderedList) echo "</ul>";
		//}
			//echo "<p>";
		}
		else if($q['type'] == 'recentsitter') {
			$aid = 0;
		//echo " qid: $qid, aid: $aid";print_r($answers);
			$a = $q['answers'][0];
			if(array_key_exists($aid, (array)$answers[$qid])) {
				$v = $answers[$qid][$aid];
				if($v) $v = fetchRow0Col0("SELECT CONCAT(fname, ' ', lname, IF(nickname IS NULL, '', CONCAT(' (',nickname, ')')))
					FROM tblprovider
					WHERE providerid = $v
					LIMIT 1", 1);
				$rawV = $v;
			}
			if(!$v) $v = formatAnswer('no reply', $answerSubstitutes);
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			if($rawV && answerIsFlagged($a))
				$prompt = "<span class='$flaggedAnswerClass'>$prompt</a>";

			echo "\n$prompt<br>";
			//if($v) echo "\n<p>$v</p>>";
			//else {
				$useUnorderedList = 1;
				if($useUnorderedList) echo "<ul><li>";
				echo formatAnswer($v, $answerSubstitutes);
				if($useUnorderedList) echo "</ul>";
		//}
			echo "<p>";
		}
		else if($q['type'] == 'recentclient') {
			$aid = 0;
		//echo " qid: $qid, aid: $aid";print_r($answers);
			$a = $q['answers'][0];
			if(array_key_exists($aid, (array)$answers[$qid])) {
				$v = $answers[$qid][$aid];
				require_once "client-fns.php";
				if($v) $v = clientLabelForSitters($v);
				$rawV = $v;
			}
			if(!$v) $v = formatAnswer('no reply', $answerSubstitutes);
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			if($rawV && answerIsFlagged($a))
				$prompt = "<span class='$flaggedAnswerClass'>$prompt</a>";

			echo "\n$prompt<br>";
			//if($v) echo "\n<p>$v</p>>";
			//else {
				$useUnorderedList = 1;
				if($useUnorderedList) echo "<ul><li>";
				echo formatAnswer($v, $answerSubstitutes);
				if($useUnorderedList) echo "</ul>";
		//}
			echo "<p>";
		}
		else if($q['type'] == 'fileupload') {
			$commonJSIsNeeded = 1;
			$aid = 0;
			$a = $q['answers'][0];
			$v = $answers[$qid][$aid];
			$rawV = '';
			if(array_key_exists($aid, (array)$answers[$qid])) {
				$v = $answers[$qid][$aid];
				require_once "remote-file-storage-fns.php";
				$rawV = $v;
				if($v) {
					if(!($entry = getRemoteFileEntry($v))) {
						$v = 'file not available.';
						$rawV = null;
					}
					else {
						$fname = basename($entry['remotepath']);
						$fname = explode('_', $fname);
						$fname = array_pop($fname);
					}
				}
			}
			if(!$v) $v = formatAnswer('no reply', $answerSubstitutes);
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			if($rawV && answerIsFlagged($a))
				$prompt = "<span class='$flaggedAnswerClass'>$prompt</a>";

			echo "\n$prompt<br>";
			if($rawV) {
				echo "View File: ";
				fauxLink($fname, "openConsoleWindow(\"fileviewer\", \"client-file-view.php?id=$rawV\", 700, 700);");
			}	
			else echo $v;
			echo "\n<p>";
		}

	}
	if($commonJSIsNeeded) echo "<script src='common.js'></script>";
}

function getFlaggedAnswers($id) {
	$submission = fetchFirstAssoc("SELECT * FROM tblsurveysubmission WHERE submissionid = $id", 1);
	$answersRaw = fetchAssociations("SELECT * FROM tblsurveyanswer WHERE submissionptr = $id", 1);
	foreach($answersRaw as $ans)
		$answers[$ans['questionid']][$ans['answerid']] = $ans['value'];
	$desc = fetchSurvey($submission['surveytemplateid']);
	$flaggedAnswers = array();
	foreach($desc['questions'] as $q) {
		if($desc['qnumbering']) {
			if($desc['qnumbering'] == 'simple') $qnum += 1;
			// else if persection reset to 1 if new section
		}
		$qid = $q['qid'];
		if($q['type'] == 'radios') {
			//if($qnum) echo "$qnum. ";
			//echo "\n{$q['prompt']}<br>";
			foreach($q['answers'] as $aid => $a) {
				if(array_key_exists($aid, (array)$answers[$qid]))
					$v = $answers[$qid][$aid];
				else $v = formatAnswer('no reply', $answerSubstitutes);
				$choices = getChoices($a);
				if(choiceIsFlagged($v, $choices)) 
					$flaggedAnswers[] = 
						array('qnum'=>$qnum, 'qid'=>$qid, 'prompt'=>$q['prompt'], 'answerprompt'=>$a['label'], 'answer'=>$v);
			}
		}
		else if($q['type'] == 'radio') {
			$aid = 0;
			foreach($q['answers'] as $a) {
				if(array_key_exists($aid, (array)$answers[$qid]))
					$v = $answers[$qid][$aid];
				else $v = formatAnswer('no reply', $answerSubstitutes);
				$choices = getChoices($a);
				if(choiceIsFlagged($v, $choices)) 
					$flaggedAnswers[] = 
						array('qnum'=>$qnum, 'qid'=>$qid, 'prompt'=>$q['prompt'], 'answer'=>$v);
			}
			if($useUnorderedList) echo "</ul>";
			echo "<p>";
		}
		else if($q['type'] == 'textbox') {
		}
	}
	return $flaggedAnswers;
}

function getChoices($answer) {
	if($answer['choices']['range']) {
		$range = explode(',',$answer['choices']['range']);
		if($answer['choices']['flag']) $flags = explode(',',$answer['choices']['flag']);
		for($i = $range[0]; $i <= $range[1]; $i++) {
			$flag = $flags ? in_array($i, $flags) : false;
			$choice = array('label'=>$i, 'value'=>$i);
			if($flag) $choice['flag'] = "true";
			$choices[] = $choice;
		}
		return $choices;
	}
	else return $answer['choices'];
}

function scoreSubmission($id) {
	$submission = $id && is_array($id) ? $id : fetchSurveySubmission($id);
	$desc = fetchSurvey($submission['surveytemplateid']);
	if(!$desc['scorable']) return array('scorable'=>false);
	$answersRaw = fetchAssociations("SELECT * FROM tblsurveyanswer WHERE submissionptr = {$submission['submissionid']}", 1);
	foreach($answersRaw as $ans)
		$answers[$ans['questionid']][$ans['answerid']] = $ans['value'];
	foreach($desc['questions'] as $q) {
		$qid = $q['qid'];
		foreach($q['answers'] as $aid => $a)
			if($a['scorable'] && array_key_exists($aid, (array)$answers[$qid]))
				$scores[] = $answers[$qid][$aid];
	}
	$submissionScore = array('scorable'=>true, 'scoredanswers'=>count($scores), 'totalscore'=>array_sum($scores));
	if($scores) {
		$submissionScore['mean'] = array_sum($scores) / count($scores);
		foreach($scores as $score) $mode[$score] += 1;
		asort($mode);
		$mode = array_reverse($mode, $preserve_keys=true);
		$vals = array_keys($mode);
		if(count($mode) == 1) $submissionScore['mode'] = $mode[0];
		else if($mode[$vals[0]] == $mode[$vals[1]]) $submissionScore['mode'] = -1; // no value was more frequent than any other
		else $submissionScore['mode'] = $vals[0];
		$submissionScore['median'] = getMedian($scores);
	}
	return $submissionScore;
}

function getMedian($arr) {
	sort($arr);
	//Count how many elements are in the array.
	$num = count($arr);
	//Determine the middle value of the array.
	$middleVal = floor(($num - 1) / 2);
	//If the size of the array is an odd number,
	//then the middle value is the median.
	if($num % 2) { 
			return $arr[$middleVal];
	} 
	//If the size of the array is an even number, then we
	//have to get the two middle values and get their
	//average
	else {
			//The $middleVal var will be the low
			//end of the middle
			$lowMid = $arr[$middleVal];
			$highMid = $arr[$middleVal + 1];
			//Return the average of the low and high.
			return (($lowMid + $highMid) / 2);
	}
}

function choiceIsFlagged($v, $choices) {
	foreach($choices as $choice)
		if($choice['value'] == $v && $choice['flag'])
			return true;
}

function answerIsFlagged($a) { // for non-multichoice
	return $a['flag'];
}

function sessionRoleIdentity() {
	if($_SESSION["clientid"]) {
		$sessionroleid = $_SESSION["clientid"];
		$sessionroletable = 'tblclient';
	}
	else if($_SESSION["providerid"]) {
		$sessionroleid = $_SESSION["providerid"];
		$sessionroletable = 'tblprovider';
	}
	else {
		$sessionroleid = $_SESSION["auth_user_id"];
		$sessionroletable = 'tbluser';
	}
	return array($sessionroletable=>$sessionroleid, 'sessionroletable'=>$sessionroletable, 'sessionroleid'=>$sessionroleid);
}

function storeSurveySubmission($post) {
}
	$surveyid = $post['surveyid'];
	$survey = fetchSurvey($surveyid);
	
	$roleIdentity = sessionRoleIdentity();
		
	$submissionId = insertTable('tblsurveysubmission', array(
		'surveytemplateid'=>$surveyid,
		'surveyname'=>$survey['name'],
		'submitted'=>date('Y-m-d H:i:s'),
		'submitterid'=>$roleIdentity['sessionroleid'],
		'submittertable'=>$roleIdentity['sessionroletable']), 1);
	unset($post['surveyid']);
	
	foreach($post as $k=>$v) {
		// $k format is either "{question name}" or "{question name}_{answer index}"
		if(strpos($k, '_')) list($qname, $aname) = explode('_', $k);
		else list($qname, $aname) = array($k, '');

		foreach($survey['questions'] as $q) {
			$qid = '0';
			$aid = '0';
			if($qname == $q['name']) {
				$qid = $q['qid'];
//echo "found $qname ($qid): <br>";		
				if($aname) foreach($q['answers'] as $i => $a) {
					if($aname == $a['name']) {
						$aid = $i;
//echo "==>found $aname ($aid): ".print_r($a, 1)."<br>";		
					}
				}
				if(!$aid) $aid = '0';
				if(!$v && ($q['type'] == 'textbox' || $q['type'] == 'date')) $v = sqlVal("''");
//echo("<b>$qname, $aname ($submissionId, $qid, $aid)</b><br>");
				foreach($q['answers'] as $i => $a) {
					if($a['clientptr']) updateTable('tblsurveysubmission', array('clientptr'=>$v), "submissionid=$submissionId", 1);
					if($a['providerptr']) updateTable('tblsurveysubmission', array('providerptr'=>$v), "submissionid=$submissionId", 1);
				}
				if($q['type'] == 'fileupload') { // no-op
				}
				insertTable('tblsurveyanswer', array(	
					'submissionptr'=>$submissionId,
					'questionid'=>$qid,
					'answerid'=>$aid,
					'value'=>$v), 1);
			}
		}
	}
	if($_FILES) {
		foreach($_FILES as $k => $v) {
			if($v['error'] && $v['error'] != UPLOAD_ERR_NO_FILE)
				$uploadErrors[] = "{$v['name']} could not be uploaded beause ".uploadErrorExplanation($v['error']);
			else $numFiles += 1;
		}
		if($uploadErrors) $error = join('<br>', $uploadErrors);
		$submission = fetchFirstAssoc("SELECT * FROM tblsurveysubmission WHERE submissionid = $submissionId LIMIT 1", 1);
		if($numFiles && !$uploadErrors) foreach($_FILES as $k => $v) {
			if($v['error'] == UPLOAD_ERR_NO_FILE) continue;
			$qname = $k;
			foreach($survey['questions'] as $q) {
				$qid = '0';
				$aid = '0';
				if($qname == $q['name']) {
					$qid = $q['qid'];
					if(!$submission['clientptr']) {echo "NO CLIENTPTR! $qid => $formFieldName [$usename]";exit;}
					if($submission['clientptr']) {
						$fileName = $v['name'];
						$usename = "submission/$submissionId/sub_{$submissionId}_$fileName";
						//$usename = "submission/$submissionId/submission_{$submissionId}_$qid";
						$result = uploadPostedFile($k, $submission['clientptr'], 'tblclient', $usename);
						if($result['error']) {
							$error = $result;
							continue;
						}
						$remoteFileEntry = findFileForOwner($usename, $submission['clientptr'], 'tblclient');
						insertTable('tblsurveyanswer', array(	
							'submissionptr'=>$submissionId,
							'questionid'=>$qid,
							'answerid'=>0,
							'value'=>($result['remotefileid'] ? $result['remotefileid'] : -99)), 1);
						}
				}
			}
		}
	}
	if($error) {
		deleteTable('tblsurveyanswer', "submissionptr = $submissionId", 1);
		deleteTable('tblsurveysubmission', "submissionid = $submissionId", 1);
		return $error;
	}
	
	// satisfy any associated nag
	clearNag($recipient=null, 'surveynag');

	return $submissionId;
}

function deleteSubmission($submissionId) {
	$allAnswersRaw = fetchAssociations("SELECT * FROM tblsurveyanswer WHERE submissionptr = $submissionId", 1);
	$allAnswers = array();
	foreach($allAnswersRaw as $ans)
		$allAnswers[$ans['questionid']][$ans['answerid']] = $ans['value'];
	$surveyTemplateId =  fetchRow0Col0("SELECT surveytemplateid FROM tblsurveysubmission WHERE submissionid = $submissionId LIMIT 1", 1);
	$survey = fetchSurvey($surveyTemplateId);
	if(!$survey) {echo "Survey [$surveyTemplateId] not found.  Aborting."; exit;}
//print_r($survey);
	foreach((array)$survey['questions'] as $q) {
//echo "<p>".print_r($q, 1);exit;
		if($q['type'] == 'fileupload' && $allAnswers[$q['qid']][0]) {
			require_once "remote-file-storage-fns.php";
			$fileId = $allAnswers[$q['qid']][0];
			deleteTable('tblremotefile', "remotefileid = $fileId", 1);
		}
	}
//exit;
	deleteTable('tblsurveyanswer', "submissionptr = $submissionId", 1);
	deleteTable('tblsurveysubmission', "submissionid = $submissionId", 1);
}

function uploadErrorExplanation($code) {
	$explanations = array(
		UPLOAD_ERR_OK => 'The upload succeeded.',
		UPLOAD_ERR_INI_SIZE => 'the file is too big.',
		UPLOAD_ERR_FORM_SIZE => 'the file is too big in this case.',
		UPLOAD_ERR_PARTIAL => 'the file was only partially uploaded.',
		UPLOAD_ERR_NO_FILE => 'no file was uploaded.',
		UPLOAD_ERR_NO_TMP_DIR => 'missing temporary folder.',
		UPLOAD_ERR_CANT_WRITE => 'failed to write to disk.',
		UPLOAD_ERR_EXTENSION => 'a system extension stopped the upload.');
	return $explanations[$code];
}

function fetchRawSurvey($id) {
	if("".(int)"$id" != "$id") return; // against injection attacks
	return fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'survey_$id' LIMIT 1", 1);
}

function fetchSurvey($id) {
	if($survey = fetchRawSurvey($id)) {
		$survey = str_replace("\n", "", $survey);
		return json_decode($survey, 1);
	}
}


function basicHTMLSurveyForm($desc, $options=null) {
	echo basicHTMLSurveyFormString($desc, $options);
}

// question types: "recentsitter", "client" (menu based on client visibility rules), "date", "date range"
// add "providerptr", "clientptr" to the submissions table, for incident reports and other uses
// survey attributes: "scored"
// answer attributes: 
//    "scored" -- scored surveys can provide a total, mean, median, and mode for all scored answers
//    "providerptr" -- boolean, for when "recentsitter" is the question type, answer is used to populate the submission providerptr
//    "clientptr" -- boolean, for when "client" is the question type, answer is used to populate the submission clientptr

function basicHTMLSurveyFormString($desc, $formOptions=null) {
	// $desc is expected to be a valid array
	//$desc = json_decode($json, 1);
	$prettyNames = array();
	ob_start();
	ob_implicit_flush(0);
	$formOptions = (array)$formOptions;
	$highlightFlaggedChoices = $formOptions['highlightFlaggedChoices'];
	$omitSubmitButton = $formOptions['omitSubmitButton'];
	
	
	$requiredFlag = $desc['requiredflag'] ? $desc['requiredflag'] : "<font color=red title='Required answer.'>* </font>";
	$requiredFlagLegend = $desc['requiredflaglegend'] ? $desc['requiredflaglegend'] : "<font color=red>* Required answer.</font>";
	if($desc['title']) echo "<h2>{$desc['title']}</h2>\n";
	
	if($_REQUEST['showflags'] && TRUE) { // mattOnlyTEST()
		require_once "gui-fns.php";
		$notable = $desc["surveyisnotable"] == 'true' ? '1' : '0';
		$url = $_SERVER["REQUEST_URI"] . "&setnotability=";
		$radios = radioButtonSet('surveyisnotable', $value=$notable, array('Yes'=>1, 'No'=>0), 
														$onClick="document.location.href=\"$url\"+this.value", $labelClass=null, $inputClass=null, $rawLabel=false);
		echo "<div style='padding:5px;'>This survey is noteworthy: ";
		foreach($radios as $radio) echo "$radio ";
		echo "</div>";
	}
	
	
	if($highlightFlaggedChoices) {
		$roles = $desc['submitterroles'];
		if(!$roles) $access = 'any logged in user.'; // open to all logged in users
		else {
			$roles = array_map('trim', explode(',', $roles));
			$access = join(', ', $roles).'.';
		}
		echo "<div style='background:lightblue;padding:5px;'>Submitter can be: $access</div>";
		$framedURL = globalURL("survey-form.php?id=f{$desc['id']}");
		if($basedOn = $desc['basedonid']) {
			$params = array_merge($_REQUEST); // kludge
			$prefix = substr("{$_REQUEST['id']}", 0, 1) == 'f' ? 'f' : '';
			$params['id'] = "$prefix{$desc['basedonid']}";
			foreach($params as $k => $v) $pstring[] = "$k=$v";
			//require_once "gui-fns.php";
			//$prior = fauxLink("Prior Version", "document.location.href=\"".globalURL("survey-form.php?".join('&', $params))."\"", 1, "View prior version");
			$prior = "<a href='".globalURL("survey-form.php?".join('&', $pstring))."' title='View prior version'>Prior Version (#{$desc['basedonid']})</a>";
			$basedOn = "<br>Based on: $prior";
		}
			
		
		echo "<div style='background:ivory;padding:5px;'>URL: $framedURL</div>";
		$submissionsToDate = fetchRow0COl0("SELECT COUNT(*) FROM tblsurveysubmission WHERE surveytemplateid = {$desc['id']}", 1);
		$submissionsToDate = $submissionsToDate ? $submissionsToDate : '<i>none</i>';
		echo "<table width=100%><tr>";
		echo "<td style='padding:5px;'>Submissions to date: $submissionsToDate</td>";
		echo "<td style='background:pink;padding:5px;'>Flagged Choices</td>";
		//echo "<div style='padding:5px;'>Submissions to date: $submissionsToDate</div>";
		echo "<td style='color:red;padding:5px;'>* Required answer</td>";
		echo "</tr></table><hr>";
	}
	if($desc['intro']) echo "<p>{$desc['intro']}</p>\n";
	echo "<form name='surveyform' method='POST' enctype='multipart/form-data'>";
	echo "<input type='hidden' name='surveyid' value='{$desc['id']}'>\n";
	
	$prefilledFields = (array)$formOptions['formfill'];
	foreach($desc['questions'] as $q) {
		if($q['paragraph']) {
			echo "\n<p>{$q['paragraph']}</p>";
			continue;
		}
		if($desc['qnumbering']) {
			if($desc['qnumbering'] == 'simple') $qnum += 1;
			// else if persection reset to 1 if new section
		}
		if($q['type'] == 'radios') {
			echo "\n<p>";
			if($qnum) echo "$qnum. ";
			echo "\n{$q['prompt']}";
			$useUnorderedList = 0;
			if($useUnorderedList) echo "<ul>";
			else echo "<table class='choices'>";
			foreach($q['answers'] as $a) {
				$rflag = $a['required'] ? $requiredFlag : '';
				$rname = "{$q['name']}_{$a['name']}";
				if($a['required']) {
					$constraints[] = "'$rname', '', 'RRADIO'";
					if($qnum) $qindicator = "($qnum) ";
					$prettyName = $a['prettyname'] ? $a['prettyname'] : $a['name'];
					$prettyNames[] = "'$rname','$qindicator{$q['name']}: $prettyName'";
				}
				$choices = getChoices($a);
				if($useUnorderedList) echo '<li>';
				else {
					$choiceCount = count($choices);
					if($a['labelabove']) echo "<tr><td  style='padding-top:15px;' colspan=$choiceCount>$rflag{$a['label']}</td></tr>";
					echo "<tr>";
				}
				foreach($choices as $i => $ch) {
					$rid = "{$rname}_{$ch['value']}";
					$checked = $prefilledFields[$rname] == $ch['value'] ? 'CHECKED' : '';
					$flagClass = $highlightFlaggedChoices && $ch['flag'] ? "style='background:pink'" : '';
					if(!$useUnorderedList) echo '<td>';
					if($a['buttonfirst']) echo "\n<input type='radio' name='$rname' id='$rid' value='{$ch['value']}' $checked> <label for='$rid' $flagClass>{$ch['label']}</label> ";
					else echo "\n<label for='$rid' $flagClass>{$ch['label']}</label> <input type='radio' name='$rname' id='$rid' value='{$ch['value']}' $checked> ";
					if(!$useUnorderedList) echo '</td>';
				}
				if($useUnorderedList) echo "- $rflag{$a['label']}<br>";
				else if(!$a['labelabove']) echo "<td>$rflag{$a['label']}</td>";
				if(!$useUnorderedList) echo "</tr>";
			}
			if($useUnorderedList) echo "</ul>";
			else echo "</table>";
			echo "</p>";
		}
		else if($q['type'] == 'radio') {
			$rflag = $q['answers'][0]['required'] ? $requiredFlag : '';
			echo "\n<p>";
			echo $rflag;
			if($qnum) echo "$qnum. ";
			echo "\n{$q['prompt']}";
			$useUnorderedList = 0;
			if($useUnorderedList) echo "<ul>";
			else echo "<table class='choices'>";
			foreach($q['answers'] as $a) {
				$rname = "{$q['name']}";
				if($a['required']) {
					$constraints[] = "'$rname', '', 'RRADIO'";
					if($qnum) $qindicator = "($qnum) ";
					$prettyName = $a['prettyname'] ? $a['prettyname'] : $a['name'];
					$prettyNames[] = "'$rname','$qindicator$prettyName'";
				}
				$choices = getChoices($a);
				echo $listitem;
				foreach($choices as $i => $ch) {
					$rid = "{$rname}_{$ch['value']}";
					$checked = $prefilledFields[$rname] == $ch['value'] ? 'CHECKED' : '';
					$flagClass = $highlightFlaggedChoices && $ch['flag'] ? "style='background:pink'" : '';
					if(!$useUnorderedList) echo '<td>';
					if($a['buttonfirst']) echo "\n<input type='radio' name='$rname' id='$rid' value='{$ch['value']}' $checked> <label for='$rid' $flagClass>{$ch['label']}</label> ";
					else echo "\n<label for='$rid' $flagClass>{$ch['label']}</label> <input type='radio' name='$rname' id='$rid' value='{$ch['value']}' $checked> ";
					if(!$useUnorderedList) echo '</td>';
				}
				if(!$useUnorderedList) echo "</tr>";
			}
			if($useUnorderedList) echo "</ul>";
			else echo "</table>";
			echo "</p>";
		}
		else if($q['type'] == 'textbox') {
			$a = $q['answers'][0];
			if($a['requiredif']) $constraint = "'{$q['name']}', '{$a['requiredif']}', 'RIFF'";
			if($a['requiredifradio']) $constraint = "'{$q['name']}', '{$a['requiredifradio']}', 'RIFFRADIO'";
			else if($a['required']) $constraint = "'{$q['name']}', '', 'R'";
			else $constraint = "";
			$rflag = $constraint ? $requiredFlag : '';
			if($constraint) {
				$constraints[] = $constraint;
				$prettyName = $a['prettyname'] ? $a['prettyname'] : $q['name'];
				$prettyNames[] = "'{$q['name']}','$qindicator$prettyName'";
			}
			echo "<p>";
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			$flagClass = $highlightFlaggedChoices && answerIsFlagged($a) ? "style='background:pink'" : '';
			if(answerIsFlagged($a))
				$prompt = "<span $flagClass>$prompt</a>";

			echo "\n$prompt<br>";
			$filler = $prefilledFields[$q['name']];
			$placeholder = $a['placeholder'] ? "placeholder=\"".safeValue($a['placeholder'])."\"" : "";
			echo "\n<textarea rows=2 cols=60 name='{$q['name']}' id='{$q['name']}' $placeholder>$filler</textarea>";
			echo "</p>";
		}
		else if($q['type'] == 'date') {
			$a = $q['answers'][0];
			if($a['requiredif']) $constraint = "'{$q['name']}', '{$a['requiredif']}', 'RIFF'";
			if($a['requiredifradio']) $constraint = "'{$q['name']}', '{$a['requiredifradio']}', 'RIFFRADIO'";
			else if($a['required']) $constraint = "'{$q['name']}', '', 'R'";
			else $constraint = "";
			$rflag = $constraint ? $requiredFlag : '';
			$constraint .= ", '{$q['name']}', '', 'isDate'";
			if($constraint) {
				$constraints[] = $constraint;
				$prettyName = $a['prettyname'] ? $a['prettyname'] : $q['name'];
				$prettyNames[] = "'{$q['name']}','$qindicator$prettyName'";
			}
			echo "<p>";
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			$flagClass = $highlightFlaggedChoices && answerIsFlagged($a) ? "style='background:pink'" : '';
			if(answerIsFlagged($a))
				$prompt = "<span $flagClass>$prompt</a>";

			$filler = $prefilledFields[$q['name']];
			echo "\n$prompt<br>";
			labeledInput(' ', $q['name'], $value=$filler, $labelClass=null, $inputClass='dateField', $onBlur=null, $maxlength=null, $noEcho=false);
			echo "</p>";
		}
		else if($q['type'] == 'recentclient') {
			$a = $q['answers'][0];
			if($a['requiredif']) $constraint = "'{$q['name']}', '{$a['requiredif']}', 'RIFF'";
			if($a['requiredifradio']) $constraint = "'{$q['name']}', '{$a['requiredifradio']}', 'RIFFRADIO'";
			else if($a['required']) $constraint = "'{$q['name']}', '', 'R'";
			else $constraint = "";
			$rflag = $constraint ? $requiredFlag : '';
			if($constraint) {
				$constraints[] = $constraint;
				$prettyName = $a['prettyname'] ? $a['prettyname'] : $q['name'];
				$prettyNames[] = "'{$q['name']}','$qindicator$prettyName'";
			}
			echo "<p>";
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			$flagClass = $highlightFlaggedChoices && answerIsFlagged($a) ? "style='background:pink'" : '';
			if(answerIsFlagged($a))
				$prompt = "<span $flagClass>$prompt</a>";
			echo "\n$prompt";
			require_once "provider-fns.php";
			require_once "client-fns.php";
			// WTF? $clientValue = $_REQUEST['clientcontext'] && $a['clientptr'] ? $_REQUEST['clientcontext'] : null;
			$clientValue = $prefilledFields[$q['name']];

			if($_SESSION["providerid"] || $_SESSION["clientid"]) {
				$clientIds = array();
				if($_SESSION["providerid"])
					$clientIds = getActiveClientIdsForProvider($_SESSION["providerid"]);
				$options = array('Select a client'=>'');
				foreach($clientIds as $clientptr)
					$options[clientLabelForSitters($clientptr)] = $clientptr;
					selectElement(' ', $q['name'], $clientValue, $options, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
			}
			else {
				$includeClientChoiceJS = true;
				hiddenElement($q['name'], $clientValue);
				$initialClientDisplay = 
					$clientValue ? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $clientValue LIMIT 1", 1)
												: 'Click to select a client';
				echo "<div id='{$q['name']}_client' style='width:300px;border: solid black 1px;padding-left:5px;' onclick='launchClientPicker(\"{$q['name']}\")'>$initialClientDisplay</div>";
			}

			echo "</p>";
		}
		else if($q['type'] == 'recentsitter') {
			$a = $q['answers'][0];
			if($a['requiredif']) $constraint = "'{$q['name']}', '{$a['requiredif']}', 'RIFF'";
			if($a['requiredifradio']) $constraint = "'{$q['name']}', '{$a['requiredifradio']}', 'RIFFRADIO'";
			else if($a['required']) $constraint = "'{$q['name']}', '', 'R'";
			else $constraint = "";
			$rflag = $constraint ? $requiredFlag : '';
			if($constraint) {
				$constraints[] = $constraint;
				$prettyName = $a['prettyname'] ? $a['prettyname'] : $q['name'];
				$prettyNames[] = "'{$q['name']}','$qindicator$prettyName'";
			}
			echo "<p>";
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			$flagClass = $highlightFlaggedChoices && answerIsFlagged($a) ? "style='background:pink'" : '';
			if(answerIsFlagged($a))
				$prompt = "<span $flagClass>$prompt</a>";
			echo "\n$prompt";
			require_once "provider-fns.php";
			//require_once "gui-fns.php";
			$options = $_SESSION["clientid"] ? recentSittersMenuOptions($_SESSION["clientid"]) : (
									$_SESSION["providerid"] ? array() : (
									staffProviderOptions()));
			$options = array_reverse($options);
			$options['Select a sitter'] = 0;
			$options = array_reverse($options);
			// WTF? $providerValue = $_REQUEST['providercontext'] && $a['providerptr'] ? $_REQUEST['providercontext'] : null;
			$providerValue = $prefilledFields[$q['name']];

			selectElement(' ', $q['name'], $providerValue, $options, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
			echo "</p>";
		}
		else if($q['type'] == 'fileupload') {
			$a = $q['answers'][0];
			if($a['requiredif']) $constraint = "'{$q['name']}', '{$a['requiredif']}', 'RIFF'";
			if($a['requiredifradio']) $constraint = "'{$q['name']}', '{$a['requiredifradio']}', 'RIFFRADIO'";
			else if($a['required']) $constraint = "'{$q['name']}', '', 'R'";
			else $constraint = "";
			$rflag = $constraint ? $requiredFlag : '';
			if($constraint) {
				$constraints[] = $constraint;
				$prettyName = $a['prettyname'] ? $a['prettyname'] : $q['name'];
				$prettyNames[] = "'{$q['name']}','$qindicator$prettyName'";
			}
			echo "<p>";
			echo $rflag;
			if($qnum) echo "$qnum. ";
			$prompt = $q['prompt'];
			$flagClass = $highlightFlaggedChoices && answerIsFlagged($a) ? "style='background:pink'" : '';
			if(answerIsFlagged($a))
				$prompt = "<span $flagClass>$prompt</a>";
			echo "\n$prompt";
			echo "<input title= 'Attach a file to this survey.' type='file' id='upload' id='{$q['name']}' name='{$q['name']}' autocomplete='off' onchange=''>";
		}		
		echo "</p>";
	}
	if(!$omitSubmitButton) echo "<input type='button' value='Submit' onclick='checkAndSubmit()'>";
	echo "</form>";
	$version = $desc['versionlabel'] ? $desc['versionlabel'] : '';
	$version .= " ({$desc['id']})";
	$versiondate = strtotime($desc['versiondate']);
	$versiondate = $versiondate ? shortDateAndTime($versiondate) : $desc['versiondate'];
	if($constraints) {
		$constraints = join(",\n", $constraints);
		$constraints = "MM_validateForm($constraints)";
		echo "<p>$requiredFlagLegend</p>";
	}
	else $constraints = "true";
	echo "<p style='font-size:0.7em;color:gray;font-style:italic;'>$version $versiondate</p>";
	if($prettyNames) $prettyNames = "setPrettynames(".join(', ',$prettyNames).");";
	if($includeClientChoiceJS) {
		$clientScript = <<<CLIENTSCRIPT
		function launchClientPicker(name) {
			var url = "client-chooser-lightbox.php?title=Choose+a+Client&prompt=Choose+a+Client&intro=Only+anagers/dispatchers+see+this.+Sitters+see+a+menu&update="+name;
			$.fn.colorbox({href:url, width:"450", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
		}
		
		function update(target, value) {
			// e.g., "992|Joe Smith"	
			var idAndName = value.split('|');
			$('#'+target).val(idAndName[0]);
			$('#'+target+'_client').html(' '+idAndName[1]);
		}
CLIENTSCRIPT;
	}
	global $onLoadFragments;
	$onLoadFragments[] = "$('.dateField').datepicker();";
	echo <<<SCRIPT
<link type="text/css" rel="stylesheet" href="responsiveclient/assets/css/libs/jquery-ui/jquery-ui-planar.css" /><!-- NB: matt added this css. -->
<script src="responsiveclient/assets/js/libs/jquery-ui/jquery-ui.min.js"></script>
	
<script src='check-form.js'></script>
<script>
$prettyNames 
function checkAndSubmit() {
  if($constraints) document.surveyform.submit();
}

$clientScript

</script>
SCRIPT;

	$out = ob_get_contents();
	ob_end_clean();
	
	
	return surveyMerge($out);
}

function staffProviderOptions() {
	require_once "provider-fns.php";
	return getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=true);
}

function surveyMerge($surveyHTML) {
	if(strpos($surveyHTML, '#LOGO#') !== FALSE) {
		$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
		$headerBizLogo = $headerBizLogo ? "<img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes>" :'';
	}
	if(strpos($surveyHTML, '#PETS#') !== FALSE && $_SESSION["clientid"]) {
		require_once "pet-fns.php";
		$petnames = getClientPetNames($_SESSION["clientid"], false, true);
	}
	$subs =
		array(
			'#LOGO#' => $headerBizLogo,
			'#BIZNAME#' => $_SESSION['preferences']['shortBizName'],
			'#BIZID#' => $_SESSION["bizptr"],
			'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
			'#BIZLOGINPAGE#' => "http://leashtime.com/login-page.php?bizid={$_SESSION['bizptr']}",
			'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
			'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
			'#PETS#' => $petnames, // to snapshot petnames (perhaps in a hidden field)
			);
	foreach($subs as $token => $sub)
		$surveyHTML = str_replace($token, $sub, $surveyHTML);
	return $surveyHTML;
}

function suspiciousSubmission($sub) {
	$suspiciousFrags = explode(",", "SELECT ,SELECT(,UNION ,UNION(,SLEEP(");
	foreach($sub as $v) {
		foreach($suspiciousFrags as $frag) {
			if(strpos(strtoupper("$v"), $frag) !== FALSE)
				$suspicious = true;
			}
	}
	return $suspicious;
}



function initializeDatabaseTables() {
	foreach(dbSetupQueries() as $sql) doQuery($sql, 1);
}

function dbSetupQueries() {
	return array(
		"CREATE TABLE IF NOT EXISTS `tblsurveysubmission` (
		  `submissionid` int(11) NOT NULL AUTO_INCREMENT,
		  `surveytemplateid` int(11) NOT NULL,
		  `surveyname` varchar(255) DEFAULT NULL,
		  `submitted` datetime NOT NULL,
		  `submitterid` int(11) NOT NULL,
		  `submittertable` varchar(40) NOT NULL,
		  `clientptr` int(11) DEFAULT NULL,
		  `providerptr` int(11) DEFAULT NULL,
  		`officeonlynote` text,
		  PRIMARY KEY (`submissionid`),
		  KEY `submitter` (`submitterid`,`submittertable`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;",

		"CREATE TABLE IF NOT EXISTS `tblsurveyanswer` (
			`submissionptr` int(11) NOT NULL,
			`questionid` int(11) NOT NULL,
			`answerid` int(11) NOT NULL DEFAULT '0',
			`value` varchar(255) NOT NULL,
			PRIMARY KEY (`submissionptr`,`questionid`,`answerid`),
			KEY `submissionptr` (`submissionptr`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
}

function getSurveyVersions() {
	$result = doQuery("SELECT property, value FROM tblpreference WHERE property LIKE 'survey_%'");
	$versions = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		$qid = substr($row['property'], 7);
		if("".(int)$qid != "$qid") continue;
		$desc = fetchSurvey($qid);
		$versions[] = array('id'=>$qid, 'name'=>$desc['name']);
	}
	usort($versions, 'svCompare');
	return $versions;
}

function svCompare($a, $b) {
	return $a['name'] < $b['name'] ? -1 : (
				$a['name'] > $b['name'] ? 1 : (
				$a['id'] < $b['id'] ? -1 : (
				$a['id'] > $b['id'] ? 1 : 0)));
}

function getSurveyNamesById() {
	$result = doQuery("SELECT property, value FROM tblpreference WHERE property LIKE 'survey_%'");
	$names = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		$qid = substr($row['property'], 7);
		if("".(int)$qid != "$qid") continue;
		$desc = fetchSurvey($qid);
		if($desc['name']) $names[$qid] = $desc['name'];
	}
  asort($names);
  return $names;
}

function getSurveyTitlesById() {
	$result = doQuery("SELECT property, value FROM tblpreference WHERE property LIKE 'survey_%'");
	$names = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		$qid = substr($row['property'], 7);
		if("".(int)$qid != "$qid") continue;
		$desc = fetchSurvey($qid);
		if($desc['title']) $names[$qid] = $desc['title'];
	}
  asort($names);
  return $names;
}

function getSurveyNames() {
  return array_keys(array_flip(getSurveyNamesById()));
}

function getSurveyTitles() {
  return array_keys(array_flip(getSurveyTitlesById()));
}

function getNextSurveyId() {
	$max = 0;
	$surveyKeys = fetchCol0("SELECT property FROM tblpreference WHERE property LIKE 'survey_%'");
	foreach($surveyKeys as $surveyKey) {
		$qid = substr($surveyKey, 7);
		if("".(int)$qid != "$qid") continue;
		$max = max($max, (int)$qid);
	}
	return $max + 1;
}

function getSurveysNamed($name) {
	$surveyKeys = fetchCol0("SELECT property FROM tblpreference WHERE property LIKE 'survey_%'");
	foreach($surveyKeys as $surveyKey) {
		$qid = substr($surveyKey, 7);
		if("".(int)$qid != "$qid") continue;
		$desc = fetchSurvey($id);
		if($desc['name'] == $name)
			$surveys[] = $desc;
	}
	return $surveys;
}

function userCanSubmitSurvey($idOrSurvey, $idType=null) {
	// submitterroles - comma-separated list: client,sitter,staff
	$survey = is_array(($idOrSurvey)) ? ($idOrSurvey) : fetchSurvey($idOrSurvey);
	$roles = $survey['submitterroles'];
	$idType = 
		$idType ? $idType : (
		$_SESSION["clientid"] ? 'client' : (
		$_SESSION["providerid"] ? 'sitter' : (
		$_SESSION["auth_user_id"] ? 'staff' : '')));
	

	if(!$roles) return true; // open to all logged in users
	else {
		$roles = array_map('trim', explode(',', $roles));
		$ok = ($idType == 'client' && in_array('client', $roles)) 
						|| ($idType == 'sitter' && in_array('sitter', $roles)) 
						|| ($idType == 'staff' && in_array('staff', $roles));
	}
	return $ok;
}

function recentSittersMenuOptions($clientid) {
	$options = array();
	if(!$clientid) return array();
	foreach(findRecentSittersForSurvey($clientid) as $provid => $label)
		$options[$label] = $provid;
	return $options;
}

function findRecentSittersForSurvey($client, $sitters=null) {  // WTF is sitters?
	require_once "provider-fns.php";
	$lookback = 90;
	if($sitters && 0+$sitters == $sitters && $sitters < 0) {
		$lookback = 0-$sitters;
		$sitters = null;
	}
	$start = date('Y-m-d', strtotime("- $lookback days"));
	if($sitters) $sitters = array_unique(explode(',',  $sitters));
	else $sitters = array_keys(fetchKeyValuePairs("SELECT providerptr FROM tblappointment WHERE clientptr = $client AND date >= '$start'"));
	if($sitters) $sitters = fetchKeyValuePairs(
		"SELECT CONCAT_WS(' ', fname, lname), providerid, lname, fname 
		FROM tblprovider 
		WHERE providerid IN (".join(',', $sitters).") AND active = 1
		ORDER BY lname, fname");
	require_once "provider-fns.php";
	$finalSitters = array();
//print_r($sitters);
	foreach($sitters as $prov => $provid)
		if(!in_array($client, doNotServeClientIds($provid))) {
			$name = getDisplayableProviderName($provid, $overrideAsClient=false);
			$finalSitters[$provid] = is_array($name) && $name['none'] ? 'name suppressed' : $name;
		}
	return $finalSitters;
}

function submissionNotificationPolicy() {
	// governs when to generate System Notifications of received survey submissions
	// event type is "q"
	// may be called outside of a session (when digest message is being generated)
	require_once "preference-fns.php";
	$bits = fetchPreference('submissionNotificationPolicy');
	//$bits = $_SESSION['preferences']['submissionNotificationPolicy'];
	if(!$bits) return array('none'=>1);
	$bits = explode(',', $bits);
	foreach($bits as $bit) $policy[$bit] = 1;
	return $policy;
	/*
	none - never generate (default)
	digest - check every five minutes and report
	individual - report in real time
	
	modifiers:
	all (default)
	flagged
	noteworthy
	flagged|noteworthy
	flagged&noteworthy
	
	example: digest,flagged,noteworthy 
			-- report (in a digest) only submissions that are flagged AND where the survey is designated "noteworthy"
	examples: individually,flagged|noteworthy
			-- report each submission individually if it is flagged OR where the survey is designated "noteworthy"
	*/
}

function generateSurveySubmissionNotification($submissionId) {
	$policy = submissionNotificationPolicy();
	if(!$policy['individual']) return;
	$submission = fetchSurveySubmission($submissionId);
	if(!$submission) return; // error!
	if(!$notable = submissionIsNotable($submission)) return;
	$survey = $notable['survey'];
	require_once "request-fns.php";
	require_once "preference-fns.php";
	$subject = "{$survey['name']} Survey received.";
	$submitter = fetchSubmitter($submission);
	$note = "{$submitter['fname']} {$submitter['lname']} answered the survey.<p>Answers flagged: "
				.($notable['flagcount'] ? $notable['flagcount'] : '0');
	$extraFields = array(
		'eventtype'=>'q',
		'creator'=>"{$submitter['fname']} {$submitter['lname']} ({$submitter['role']})", 
		'submissionid'=>$submissionId);
	saveNewSystemNotificationRequest($subject, $note, $extraFields, $clientptr=$submitter['clientid']);
}

function submissionIsNotable($submission, $policy=null) {
	if(!$submission) return; // error!
	$flagged = getFlaggedAnswers($submission['submissionid']);
	$policy = $policy ? $policy : submissionNotificationPolicy();
	if(!$flagged && ($policy['flagged'] || $policy['flagged&noteworthy'])) return;
	$survey = fetchSurvey($submission['surveytemplateid']);
	if(!$survey) return; // error!
	if($policy['noteworthy'] && !$survey['surveyisnotable']) return;
	if($policy['flagged|noteworthy'] && !$survey['surveyisnotable'] && !$flagged) return;
	return array('notable'=>true, 'flagcount'=>count($flagged), 'survey'=>$survey);
}

function generateSubmissionDigestNotification() {
	// may be called outside of a session
	$policy = submissionNotificationPolicy();
	if(!$policy['digest']) return;
	require_once "preference-fns.php";
	$lastDigest = fetchPreference('lastSurveySubmissionNotification');
	//$lastDigest = 1600463748;
	$where = $lastDigest ? "WHERE submitted >= '".date('Y-m-d H:i:s', $lastDigest)."'" : '';
	$latestSubmissions = fetchAssociations($sql = "SELECT * FROM tblsurveysubmission $where ORDER BY submitted", 1);
	setPreference('lastSurveySubmissionNotification', time());
	if(!$latestSubmissions) return;
	require_once "request-fns.php";
	$subject = "Survey Submission(s) received";
	$leftPad = ' style="padding-left:5px;"';
	$note = "<h2>$subject:</h2><table><tr><th>Submitted</th><th$leftPad>Submitter</th><th$leftPad>Flagged Answers</th></tr>";
	foreach($latestSubmissions as $submission) {
		if(!($notable = submissionIsNotable($submission, $policy))) continue;
		$notableCount += 1;
		$submitted = shortDateAndTime(strtotime($submission['submitted']));
		$submitter = fetchSubmitter($submission);
		$note .= "<tr><td>$submitted</td><td$leftPad>{$submitter['fname']} {$submitter['lname']}</td><td$leftPad>"
				.($notable['flagcount'] ? $notable['flagcount'] : '0')
				."</td></tr>";
	}
	$note .= "</table>";
	$extraFields = array(
		'eventtype'=>'q',
		'creator'=>"{$submitter['fname']} {$submitter['lname']} ({$submitter['role']})", 
		'submissionsdigest'=>true);
	saveNewSystemNotificationRequest($subject, $note, $extraFields, $clientptr=null);
}


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
surveyname
submitted - datetime
submitterid


tblsurveyanswer
submissionptr
answerid - corresponds to question name or "{question id}_{answer index}"
value


{"id": 2, "name": "COVID-19 Reporting Form", "title": "COVID-19 Reporting Form", 
"versionlabel": "Version 2",
"versiondate": "August 14, 2020 12:30",
"intro": "Please respond to the following questions about how you feel today.",
"qnumbering": "simple",
"questions": [
{"qid": 1, "name": "symptoms", 
 "prompt": "Are you currently experiencing, or have you experienced in the past 14 days, any of the following symptoms? (Please take your temperature before you answer this question.)",
 "type": "radios",
 "answers": [
   {"name": "fever", "required": "true", 
     "label":"Fever (100.4&deg; F/37.8&deg; C or greater as measured by an oral thermometer)",
     "choices": [{"label": "Yes", "value": "Yes", "flag": "true"}, {"label": "No", "value": "No"}]
   },
   {"name": "cough", 
     "label":"Cough",
     "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
   },
   {"name": "breathingdifficulty", 
     "label":"Shortness of breath or difficulty breathing",
     "choices": [{"label": "Yes", "value": "Yes", "flag": "true"}, {"label": "No", "value": "No"}]
   },
   {"name": "sorethroat", 
     "label":"Sore throat",
     "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
   },
   {"name": "losstasteorsmell", 
     "label":"New loss of taste or smell",
     "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
   },
   {"name": "chills", 
     "label":"Chills",
     "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
   },
   {"name": "aches", 
     "label":"Head or muscle aches",
     "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
   },
   {"name": "digestiveupset", 
     "label":"Nausea, diarrhea, vomiting",
     "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
   }
 ]
 },
{"qid": 2, "name": "symptomsproximity", 
 "prompt": "In the past 14 days, have you been in close proximity to anyone who was experiencing any of the above symptoms or has experienced any of the above symptoms since your contact?",
 "type": "radio",
 "answers": [
 {
  "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
 }
 ]
 },
{"qid": 3, "name": "positiveproximity", 
 "prompt": "In the past 14 days, have you been in close proximity to anyone who has tested positive for COVID-19?",
 "type": "radio",
 "answers": [
 {
  "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
 }
 ]
 },
{"qid": 4, "name": "awaitingtestresults", 
 "prompt": "Have you been tested for COVID-19 and are waiting to receive test results?",
 "type": "radio",
 "answers": [
 {
  "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
 }
 ]
 },
{"qid": 5, "name": "covid19positive", 
 "prompt": "Have you have tested positive for COVID-19, or are you presumptively positive for COVID-19 based on your health care provider's assessment or your symptoms?",
 "type": "radio",
 "answers": [
 {
  "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
 }
 ]
 },
{"paragraph": "NOTE: If you have tested positive for COVID-19 or have been presumptively positive for COVID-19 based on your health care provider's assessment or your symptoms, please contact your manager when: (1) you have had no fever for at least 72 hours (3 full days), without the use of fever-reducing medications; (2) your other symptoms have improved; and at least 7 days have elapsed since your symptoms first appeared."},
{"qid": 6, "name": "travelabroad", 
 "prompt": "In the past 14 days, have you been in close proximity to anyone who has been on a commercial flight or traveled outside of the United States?",
 "type": "radio",
 "answers": [
 {
  "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
 }
 ]
 },
{"qid": 7, "name": "higherrisk",  
 "prompt": "Is there any reason why you feel you are at higher risk of contracting COVID-19 or experiencing complications from COVID-19 by entering client homes? If [yes], please provide a brief explanation.",
 "type": "radio",
 "answers": [
 {
 	"required": "true",
  "choices": [{"label": "Yes", "value": "Yes"}, {"label": "No", "value": "No"}]
 }
 ]
 },
{"qid": 8, "name": "higherriskexplanation",  
 "prompt": "Explanation:",
 "type": "textbox",
 "answers": [
 {
 	"requiredifradio": "higherrisk__Yes"
 }
 ]
 
},
{"paragraph": "Certification"},
{"paragraph": "I hereby certify that the responses provided above are true and accurate to the best of my knowledge."},
{"paragraph": "Note: The information collected on this form will be used to determine only whether you may be infected with COVID-19. The information on this form will be maintained as confidential. Any questions should be directed to your manager or #BIZNAME# owner."}
]
}

*/
