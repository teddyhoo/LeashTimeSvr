<?
// reports-survey-submissions.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "survey-fns.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";

if(!adequateRights('#rs') && !locked('o-')) {
	include "frame.html";
	echo "<h2>Insufficient access rights</h2><img src='art/spacer.gif' height=300>";
	include "frame-end.html";
	exit;
}

$locked = locked('o-,#rs');

if($_GET['globalstats']) {
	if(!staffOnlyTEST()) {
		echo 'Disallowed.';
		exit;
	}
	require "common/init_db_common.php";
	$databases = fetchCol0("SHOW DATABASES");
	$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz ", 'db'); // WHERE activebiz=1
	foreach($bizzes as $biz) {
		if(!in_array($biz['db'], $databases)) continue;
		reconnectPetBizDB($biz['db'], $biz['dbhost'],  $biz['dbuser'], $biz['dbpass'], $force=true);
		$stats = usageStats($force=1);
		if($stats['enabled']) {
			$total += 1;
			$row = array_merge(
				array('name'=>fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1", 1)." ($db)"),
				$stats);
			$rows[] = $row;
		}
	}
	quickTable($rows, $extra='border=1', $style=null, $repeatHeaders=0);
	exit;
}



extract(extractVars('start,end,print,surveynames,surveyid,submitters,sort,csv,detail,mgrview', $_REQUEST));
if($detail) {
	$extraHeadContent =
		'<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
		 <script type="text/javascript" src="jquery-1.7.1.min.js"></script>
		 <script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
		 <script type="text/javascript" src="common.js"></script>
		 <link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css"> ';
	require_once "frame-bannerless.php";
	require_once "survey-submission-view.php";
	exit;
}

if($mgrview) {
	require_once "system-login-fns.php";
	$mgrs = getManagerUsers($details=true);
	$mgr = $mgrs[$mgrview];
	require_once "frame-bannerless.php";
	echo "<h2>Staff</h2><div class='fontSize1_1em' style='background:white;'>";
	echo "{$mgr['fname']} {$mgr['lname']}<p>{$mgr['email']}";
	echo "</div>";
	exit;
}
$pageTitle = "Survey Submissions Report";

if($_POST) {
	$rows = fetchSubmissions($start, $end, $submitters, $surveynames, $surveyid);
}
if($csv) {
	$survey = fetchSurvey($surveyid);
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Survey-Submissions.csv ");
	dumpCSVRow('Surveys Submitted Report');
	dumpCSVRow(array('Survey:',$survey['name'], "({$survey['id']})"));
	$version = $survey['versionlabel'] ? array($survey['versionlabel']) : array();
	if($survey['versiondate']) {
		$versiondate = strtotime($survey['versiondate']);
		$version[] = $versiondate ? shortDateAndTime($versiondate) : $desurveyc['versiondate'];
	}
	if($version) dumpCSVRow(array_merge(array('Version:'), $version));

	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");

	submissionsCSV($survey, $rows, $sort);
}
if(!$print && !$csv) {
	if(mattOnlyTEST()) {
		$settings = "- <a href='survey-nags.php'>Survey Nags</a>"
								.'- '.fauxLink('Settings', 'openSettings()', 1);
	}
	$breadcrumbs = "<a href='survey-templates.php'>Survey Templates</a>$settings - <a href='reports.php'>Reports</a>";	
	if(mattOnlyTEST()) {
		$stats = usageStats();
		foreach($stats as $k=>$v) $statstrings[] = "\"$k: {$stats[$k]}\"";
		$statstrings = join(',', $statstrings);
		$breadcrumbs .= " - <span onclick='var s=[$statstrings];alert(s.join(String.fromCharCode(13)));'>[STAFF: Stats]</span>";
		$breadcrumbs .= " - <span onclick='$.fn.colorbox({href:\"?globalstats=1\", width:\"750\", height:\"470\", scrolling: true, iframe: true, opacity: \"0.3\"});'>[STAFF: Global Stats]</span>";
	}
	include "frame.html";
	// ***************************************************************************
?>
	<form name='reportform' method='POST'>
	<table>
	<tr><td colspan=2>
<?
	$start = $start ? $start : shortDate(strtotime("- 7 days"));
	$end = $end ? $end : shortDate(time());
	echo "Show surveys...<p>";
	calendarSet('Submitted from:', 'start', $start, null, null, true, 'end');
	echo "&nbsp;";
	calendarSet('to:', 'end', $end);
	hiddenElement('csv', '');
	hiddenElement('postie', '1'); // to allow detection of a post when no criteria are supplied
?>
	</td></tr>
	<tr><td colspan=2>
<?
$options = array('All Surveys'=>-1);
$surveyNames = getSurveyNames();
if($surveyNames) $surveyNames = array_combine($surveyNames, $surveyNames);
$options = array_merge($options, $surveyNames);
labeledSelect('Surveys:', 'surveynames', $surveynames, $options, $labelClass=null, $inputClass=null, $onChange='surveyNamesChanged(this)'); echo " ";

$options = array('No Selection'=>-1);
$surveyIdsAndNames = getSurveyVersions();
$surveyNamesById = array();
foreach($surveyIdsAndNames as $idAndName) {
	if($idAndName['name'] != $lastName && $nameGroup) {
		$options[$lastName] = $nameGroup;
		$nameGroup = array();
	}
	$lastName = $idAndName['name'];
	$nameGroup["$lastName ({$idAndName['id']})"] = $idAndName['id'];
	$surveyNamesById[] = "\"s_{$idAndName['id']}\": \"$lastName\"";
}
$surveyNamesById = "var surveyNamesById = {".join(', ', $surveyNamesById)."};";
if($nameGroup) $options[$lastName] = $nameGroup;
labeledSelect('Specific Survey Version:', 'surveyid', $surveyid, $options, $labelClass=null, $inputClass=null, $onChange='versionChoiceChanged(this)'); echo "<p>";
//labeledSelect($label, $name, $value=null, $options=null, $labelClass=null, $inputClass=null, $onChange=null, $noEcho=false)


$options = array('Anyone'=>'anyone', 'All Sitters'=>'sitters', 'All Staff'=>'staff', 'All Clients'=>'clients');
$options = array_merge($options, allSittersSelectElementOptions());
labeledSelect('From:', 'submitters', $submitters, $options); echo "<p>";

?>
	</td></tr>
	
	</table>
<?
	echoButton('', 'Generate Report', 'genReport()');
	echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	/*echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	*/
	echoButton('downloadbutton', 'Download Spreadsheet', "genCSV()");
?>
	</form>
	
<? if($_POST && !$rows) echo "No results found.";
		else if($rows) {
			$columns = explodePairsLine('submitted|Submitted||name|Submitter||flags|Flags||surveyname|Survey||submissionid|Submission'); // ||version|Version
			$blackLargeCircle = '&#11044;';
			$roles = explodePairsLine('user|staff||provider|sitter||client|client');

			foreach($rows as $id => $row) {
				$flags = getFlaggedAnswers($row['submissionid']);
				$flagCount = count($flags);
				$flagHTML = null;
				//array('qnum'=>$qnum, 'qid'=>$qid, 'prompt'=>$q['prompt'], 'answerprompt'=>$a['label'], 'answer'=>$v);
				foreach($flags as $flag) {
					$flagHTML[] = safeValue(
						"Question: {$flag['qnum']}: {$flag['prompt']}"
						.($flag['answerprompt'] ? " > {$flag['answerprompt']}" : '')
						."<br>Answer: <span class='warning'>$blackLargeCircle {$flag['answer']}</span>"
						);
				}
				if($flagHTML) $flagHTML = join('<p>', $flagHTML);
				if(!$flags) $rows[$id]['flags'] = "<span title='No answers flagged.'>(0)</span>";
				else $rows[$id]['flags'] = "<span class='warning' title='$flagCount answers flagged.  Click for details.' onclick='showFlags(\"$flagHTML\")'>($flagCount)</span>";
				$rows[$id]['submitted'] = fauxLink($row['submitted'], "showSubmission({$row['submissionid']})", 1, 'Review submission');
				$rows[$id]['surveyname'] = fauxLink("{$row['surveyname']} ({$row['surveyid']})", "showSurvey({$row['surveyid']})", 1, 'Review survey');
				$role = $roles[$row['role']];
				$rows[$id]['name'] = fauxLink("{$row['name']} ($role)", "userView(\"$role\",{$row['roleid']})", 1, 'Review survey');
			}
			tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');
		}
?>
	
	
	
	
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	setPrettynames('start','Starting Date','end','Ending Date');
	function genReport() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) document.reportform.submit();
	}
	function genCSV() {
		if(MM_validateForm(
				'start', '', 'R',
				'end', '', 'R',
				'start', '', 'isDate',
				'end', '', 'isDate')) {
			document.getElementById('csv').value=1;
		  document.reportform.submit();
			document.getElementById('csv').value=0;
		}
	}
	
	<?= $surveyNamesById ?>
	
	function surveyNamesChanged(el) {
		if(el.selectedIndex == 0) {
			$('#surveyid').val(-1);
		}
		else if(surveyNamesById["s_"+$('#surveyid').val()] != $('#surveynames').val())
			$('#surveyid').val(-1);
	}
	
	function versionChoiceChanged(el) {
		if(el.selectedIndex == 0) {
			$('#downloadbutton').hide();
		}
		else {
			$('#downloadbutton').show();
			$('#surveynames').val(surveyNamesById["s_"+el.options[el.selectedIndex].value]);
		}
	}
	
	function printThisPage(link) {
		link.style.display="none";window.print();
	}

	function showSubmission(id) {
		$.fn.colorbox({href:"?detail="+id, width:"650", height:"470", scrolling: true, iframe: true, opacity: "0.3"});	
	}
	
	function showSurvey(id) {
		$.fn.colorbox({href:"survey-form.php?id="+id+"&showflags=1&omitsubmit=1", width:"650", height:"470", scrolling: true, iframe: true, opacity: "0.3"});	
	}
	
	function showFlags(flagshtml) {
		$.fn.colorbox({html:"<h2>Flagged Answers:</h2><div class='fontSize1_2em'>"+flagshtml+"</div>", width:"450", height:"470", scrolling: true, iframe: false, opacity: "0.3"});	
	}
	
	function userView(type, id) {
		var url;
		if(type == 'client') url = "client-view.php?id="+id;
		else if(type == 'sitter') url = "provider-snapshot.php?id="+id;  // provider-view.php does NOTHING!
		else url = '?mgrview='+id;
		$.fn.colorbox({href:url, width:"650", height:"470", scrolling: true, iframe: true, opacity: "0.3"});
	}
	
	function openSettings() {
		$.fn.colorbox({href:'survey-options-lightbox.php', width:"650", height:"470", scrolling: true, iframe: true, opacity: "0.3"});
	}
	versionChoiceChanged($('#surveyid')[0]);
	
<? dumpPopCalendarJS(); ?>
</script>
<?
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
} // end if onscreen version

	
function fetchSubmissions($start, $end, $submitters, $surveyName, $surveyid) {
	if($submitters == 'anyone') ; // no-op
	else if($submitters == 'clients') $tests[] = "submittertable = 'tblclient'";
	else if($submitters == 'sitters') $tests[] = "submittertable = 'tblprovider'";
	else if($submitters == 'staff') $tests[] = "submittertable = 'tbluser'";
	else $tests[] = "submittertable = 'tblprovider' AND submitterid = $submitters";
	
	if($start) $tests[] = "submitted >= '".date('Y-m-d 00:00:00', strtotime($start))."'";
	if($end) $tests[] = "submitted <= '".date('Y-m-d 23:59:59', strtotime($end))."'";

	if($surveyid && $surveyid != -1) $tests[] = "surveytemplateid = '$surveyid'";
	else if($surveyName && $surveyName != -1) $tests[] = "surveyname = '$surveyName'";
	
	$sql = "SELECT * FROM tblsurveysubmission "
				.($tests ? "WHERE ".join(' AND ', $tests) : '')
				." ORDER BY submitted DESC";
	$submissions = fetchAssociationsKeyedBy($sql, 'submissionid', 1);
	//if(mattOnlyTEST()) print_r($sql);
	foreach($submissions as $sid => $submission) {
		$submitter = fetchSubmitter($submission);
		$row = array('submissionid'=>$sid,
									'surveyname'=>$submission['surveyname'],
									'surveyid'=>$submission['surveytemplateid'],
									'surveytemplateid'=>$submission['surveytemplateid'], // for scoring
									'submitted'=>shortDateAndTime(strtotime($submission['submitted'])));
//echo "sid: [$sid] ".print_r($submission, 1)."\n\n";							
		$row = array_merge($row, $submitter);
		$rows[] = $row;
	}
	return (array)$rows;
}

function submissionsCSV($survey, $submissions, $sort) {
	$columns = 'submitted|Submitted||submissionid|Submission||fname|Submitter first||lname|Submitter last'; // ||sourcereference|Payment Method||reason|Notes
	foreach($survey['questions'] as $q) {
		$qid = $q['qid'];
		if(!$q['answers']) continue;
		foreach($q['answers'] as $ai => $a) {
			$answerCols[] = ($answerColId = "{$qid}_{$ai}");
			$columns .= "||$answerColId|{$q['name']}".($a['name'] ? "_{$a['name']}" : '');
			$columnTypes[$answerColId] = $q['type'];
		}
	}
//if(mattOnlyTEST()) {print_r($survey);exit;}	
	if($survey['scorable'])
		$columns .= "||mean|Mean Score||scoredanswers|Answer Count||totalscore|Answer Total||median|Median Score||mode|Mode Score";
	$columns = explodePairsLine($columns);
	dumpCSVRow($columns);

	foreach($submissions as $sub) {
//print_r($sub);
		$row = array('submissionid'=>$sub['submissionid'], 'submitted'=>$sub['submitted'], 'fname'=>$sub['fname'], 'lname'=>$sub['lname']);
		$answersRaw = fetchAssociations("SELECT * FROM tblsurveyanswer WHERE submissionptr = {$sub['submissionid']}", 1);
		$answersByColId = array();
		foreach($answersRaw as $suba)
			$answersByColId["{$suba['questionid']}_{$suba['answerid']}"] = $suba;
//print_r($answersByColId);exit;
		foreach($answersByColId as $colId => $suba) {
//echo 	"\n$colId => ".print_r($suba, 1);	exit;
			$v = $suba['value'] ? $suba['value'] : '';
			if($v) {
				if($columnTypes[$colId] == 'recentsitter')
					$v = getDisplayableProviderName($v);
				else if($columnTypes[$colId] == 'recentclient') {
					require_once "client-fns.php";
					$v = clientLabelForSitters($v);
				}
				else if($columnTypes[$colId] == 'fileupload') {
					require_once "remote-file-storage-fns.php";
					$v = getRemoteFileEntry($v);
					$v = $v['remotepath'] ? basename($v['remotepath']) : 'file not found';
				}
			}
			$row[$colId] = $v;
		}
		if($survey['scorable']) {
			$submissionScore = scoreSubmission($sub);
//if(mattOnlyTEST()) {print_r($sub);exit;}
			$row['mean'] = $submissionScore['mean'];
			$row['scoredanswers'] = $submissionScore['scoredanswers'];
			$row['totalscore'] = $submissionScore['totalscore'];
			$row['median'] = $submissionScore['median'];
			$row['mode'] = $submissionScore['mode'];
			
		}
		dumpCSVRow($row, array_keys($columns));
	}	
}

function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if(is_array($row)) {
		if($cols) {
			$nrow = array();
			if(is_string($cols)) $cols = explode(',', $cols);
			foreach($cols as $k) $nrow[] = $row[$k];
			$row = $nrow;
		}
		echo join(',', array_map('csv',$row))."\n";
	}
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}
