<? // faq-generator.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";

if($_REQUEST['preview']) {
?>
<head>
  <link rel="icon" href="/art/favicon16.ico" type="image/x-icon" />
  <link rel="shortcut icon" href="/art/favicon16.ico" type="image/x-icon" />
  <link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
	<script type="text/javascript" src="jquery.busy.js"></script> 	
	<script type="text/javascript">jQuery().busy("defaults", { img: 'art/busy.gif', offset : 0, hide : false });</script> 	
	<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
</head>
<body>
<?
}



if(!mysql_select_db('LeashTimeDocs')) echo "Failed to select ['LeashTimeDocs']: ".mysql_error();


$faqs = fetchAssociations("SELECT * FROM faq ORDER BY sectionptr, sequence");
$sections = fetchAssociations("SELECT * FROM faqsection ORDER BY sequence");

echo "\n".'<script type="text/javascript" src="jquery.min.js"></script>'; // http://ajax.googleapis.com/ajax/libs/jquery/1.3.2/jquery.min.js

if($_REQUEST['switch']) echo "<a href=faq-generator.php?switch=1 target=faqviewer>Normal View</a> - <a href='faq-generator.php?switch=1&expandAll=1&showAnswers=1' target=faqviewer>Expanded View</a><hr>";

//echo "<p><span class='linky' onclick='showIndexQuestions(1)'>Show All Questions</span>";
echo "<p><span class='linky' onclick='showMainQuestions(1)'>Show All Questions</span>";
echo " - ";
//echo "<span class='linky' onclick='showIndexQuestions()'>Hide All Questions</span></p>";
echo "<span class='linky' onclick='showMainQuestions()'>Hide All Questions</span></p>";

/*dumpQuestions();
echo "<hr>";
dumpAnswers();*/


dumpSingleList($showSections=true, $expandAll=$_REQUEST['expandAll'], $showNumbers=true, $showAnswers=$_REQUEST['showAnswers'])
?>

<script language='javascript'>
var showAllIndex = 0;
var showAllAnswersVar = 0;
function toggleIndexSection(sectionId) {
	if(!showAllIndex) showIndexQuestions(0);
	var div = document.getElementById('index_'+sectionId);
	div.style.display = (div.style.display == 'none' ? 'block' : 'none');
}

function toggleAnswerSection(sectionId) {
	if(!showAllAnswersVar) showIndexQuestions(0);
	var hidden = document.getElementById('answersection_'+sectionId).style.display == 'none';
	$('.answersection').hide();
	if(hidden) $('#answersection_'+sectionId).show();
}

function showMainQuestions(on) {
		$('.answersection').css('display', on ? 'block' : 'none');
}

function toggleAnswerDisplay(faqid, sectionid) {
	$('.answersection').css('display', 'none');
	$('#answersection_'+sectionid).css('display', 'block');
	$('#answer_'+faqid).siblings('.answer').css('display', 'none');
	$('#answer_'+faqid).siblings('.answer').removeClass('selectedA');
	$('#answer_'+faqid).siblings('.mainquestion').removeClass('selectedQ');
	$('#answer_'+faqid).addClass('selectedA');
	$('#question_'+faqid).addClass('selectedQ');
	var span = document.getElementById('answer_'+faqid);
	var display = span.style.display == 'block' ? 'none' : 'block';
	span.style.display = display;
}

function showIndexQuestions(on) {
	showAllIndex = on;
	$('.indexsection').css('display', (on ? 'block' : 'none'));
}

function showAllAnswers(on) {
	showAllAnswersVar = on;
	$('.answer').css('display', (on ? 'block' : 'none'));
	$('.mainquestion').css('display', (on ? 'block' : 'none'));
	$('.answersectionhead').css('display', (on ? 'block' : 'none'));
}

function showAnswer(faqid, sectionId) {
	$('#answersdiv').css('display', 'block');
	showAllAnswers(0);
	//alert(document.getElementById('question_'+faqid));
	$('#answersectionhead_'+sectionId).css('display', 'inline');
	
	$('#answersection_'+sectionId).css('display', 'inline');
	$('#answersection_'+sectionId).children('.mainquestion').css('display', 'inline');
	$('#answersection_'+sectionId).children('.mainquestion').removeClass('selectedQ');

	$('#answersection_'+sectionId).children('.answer').css('display', 'none');
	$('#answersection_'+sectionId).children('.answer').removeClass('selectedA');
	
	$('#question_'+faqid).css('display', 'inline');
	$('#question_'+faqid).addClass('selectedQ');
	$('#answer_'+faqid).css('display', 'block');
	$('#answer_'+faqid).addClass('selectedA');

}

function showAllAnswerSections(on) {
	showAllAnswersVar = on;
	/*var divs = document.getElementsByTagName('div');
	for(var i=0; i < divs.length; i++)
		if(divs[i].className.indexOf('answersection') != -1) {
			divs[i].style.display = (on ? 'block' : 'none');
		}*/
	var divs = document.getElementsByTagName('div');
	for(var i=0; i < divs.length; i++)
		if(divs[i].className.indexOf('answersectionhead') != -1) {
			divs[i].style.display = (on ? 'block' : 'none');
		}
	if(on) return;
	$(".mainquestion").css
	var divs = document.getElementsByTagName('span');
	for(var i=0; i < divs.length; i++)
		if(divs[i].className.indexOf('mainquestion') != -1 ||
		   divs[i].className.indexOf('answer') != -1) {
			divs[i].style.display = 'none';
		}
	
}

function showAllAnswerContent(on) {
	showAllAnswersVar = on;
	$(".answersectionhead").css('display', (on ? 'block' : 'none'));
	$(".answersection").css('display', (on ? 'block' : 'none'));
	$(".mainquestion").css('display', (on ? 'inline' : 'none'));
	$(".answer").css('display', (on ? 'block' : 'none'));
}
<?
if($_GET['flat']) {
?>
showAllAnswerContent(1);
<?
}
?>
</script>
<style>
.indexsectionhead { cursor:pointer;font:12pt Arial Black, Gadget, sans-serif; color:#255E8D;}
.indexsection {padding-left: 20px;cursor:pointer;font:10pt Arial , Helvetica, sans-serif; color:#255E8D;line-height:18pt;}
.indexsection p {line-height:15px;}
.linky {cursor:pointer;font:10pt Arial , Helvetica, sans-serif; color:#255E8D;}
.answersectionhead {font:12pt Arial Black, Gadget, sans-serif; cursor:pointer;}
.answersection {padding-top: 10px;}
.mainquestion {color:#255E8D;display:none;cursor:pointer;}
.answer {color:green;display:none;font:10pt Arial , Helvetica, sans-serif;padding-left:20px;padding-top:5px;}
.selectedQ {font:bold 12pt Arial , Helvetica, sans-serif;}
.selectedA {font:10pt Arial , Helvetica, sans-serif ;}
</style>
<?
function dumpQuestions($showSections=true, $expandAll=false, $showNumbers=true) {
	global $sections, $faqs;
	$num = 1;
	foreach($sections as $section) {
		$sectionId = $section['sequence'];
		if($showSections) {
			$display = $expandAll ? 'block' : 'none';
			echo "\n\n<span class='indexsectionhead' onclick='toggleIndexSection($sectionId)'>{$section['label']}</span><br>"
						."<div class='indexsection' id='index_$sectionId' style='display:$display'>";
			$num = 1;
		}
		foreach($faqs as $i => $faq) {
			if($faq['sectionptr'] != $section['sectionid'] || !trim($faq['answer']) || $faq['hidden']) continue;
			$numDisplay = $showNumbers ? "$num. " : "";
			echo "\n<span class='indexquestion' onclick='showAnswer({$faq['faqid']}, $sectionId)'>$numDisplay{$faq['question']}</span><br>";
			$num++;
		}
		if($showSections) {
			echo "</div>";
		}
	}
}

function dumpAnswers($showSections=true, $expandAll=false, $showNumbers=true) {
	global $sections, $faqs;
	$num = 1;
	echo "<div id='answersdiv' style='display:none'>";
	foreach($sections as $section) {
		$sectionId = $section['sequence'];
		if($showSections) {
			$display = $expandAll ? 'block' : 'none';
			echo "\n\n<span class='answersectionhead' id='answersectionhead_$sectionId' onclick='toggleAnswerSection($sectionId)'>{$section['label']}</span><br>"
						."<div class='answersection' id='answersection_$sectionId' style='display:$display'>";
			$num = 1;
		}
		foreach($faqs as $i => $faq) {
			if($faq['sectionptr'] != $section['sectionid'] || !trim($faq['answer']) || $faq['hidden']) continue;
			$numDisplay = $showNumbers ? "$num. " : "";
			echo "\n<span class='mainquestion' id='question_{$faq['faqid']}' onclick='toggleAnswerDisplay({$faq['faqid']}, $sectionId)'>$numDisplay{$faq['question']}</span><br>";
			echo "\n<div class='answer' id='answer_{$faq['faqid']}'>".convertAnswer($faq['answer'])."</div><br>";
			$num++;
		}
		if($showSections) {
			echo "</div>";
		}
	}
	echo "</div>";
}

function dumpSingleList($showSections=true, $expandAll=false, $showNumbers=true, $showAnswers=false) {
	global $sections, $faqs;
	$num = 1;
	echo "<div id='answersdiv'>";
	foreach($sections as $section) {
		$sectionId = $section['sequence'];
		if($showSections) {
			$display = $expandAll ? 'block' : 'none';
			echo "\n\n<span class='answersectionhead' style='color:#255E8D' id='answersectionhead_$sectionId' onclick='toggleAnswerSection($sectionId)'>{$section['label']}</span><br>"
						."<div class='answersection' id='answersection_$sectionId' style='display:$display'>";
			$num = 1;
		}
		$display = $showAnswers ? "style='display:block;'" : '';
		foreach($faqs as $i => $faq) {
			if($faq['sectionptr'] != $section['sectionid'] || !trim($faq['answer']) || $faq['hidden']) continue;
			$numDisplay = $showNumbers ? "$num. " : "";
			echo "\n<span class='mainquestion' style='display:inline;' id='question_{$faq['faqid']}' onclick='toggleAnswerDisplay({$faq['faqid']}, $sectionId)'>$numDisplay{$faq['question']}</span><br>";
			echo "\n<div class='answer' $display id='answer_{$faq['faqid']}'>".convertAnswer($faq['answer'])."</div><br>";
			$num++;
		}
		if($showSections) {
			echo "</div>";
		}
	}
	echo "</div>";
}


function convertAnswer($str) {
	$str = str_replace("\n\n", "<p>", trim($str));
	$str = str_replace("\n", "<br>", $str);
	$str = str_replace("“", '"', $str);
	$str = str_replace("”", '"', $str);
	$str = str_replace("’", "'", $str);
	return $str.'<br>';
}

