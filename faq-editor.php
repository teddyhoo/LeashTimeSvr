

<? // faq-editor.php 
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');

reconnectPetBizDB('LeashTimeDocs', 'localhost', $dbuser, $dbpass, 1);



if($_REQUEST['go']) {
	if($_REQUEST['newfaq']) {
		$nextFaq = fetchFirstAssoc("SELECT * FROM faq WHERE faqid = {$_REQUEST['nextfaqid']}");
		
		
//echo 'error';print_r(fetchAssociations("SELECT * FROM faq WHERE sectionptr = {$nextFaq['sectionptr']} AND sequence >= {$nextFaq['sequence']}"));

		if(!$_REQUEST["after"]) {
			updateTable('faq', array('sequence'=>sqlVal('sequence+10000')),
										"sectionptr = {$nextFaq['sectionptr']} AND sequence >= {$nextFaq['sequence']}", 1);
			$newId = insertTable('faq', array('question'=>preprocessString($_REQUEST["newfaq"]), 
																	'answer'=>preprocessString($_REQUEST["newanswer"]),
																	'sectionptr'=>$nextFaq['sectionptr'], 'sequence'=>$nextFaq['sequence']), 1);
			updateTable('faq', array('sequence'=>sqlVal('sequence-10000+1')),
										"sectionptr = {$nextFaq['sectionptr']} AND sequence >= 10000", 1);
		}
		else $newId = insertTable('faq', array('question'=>preprocessString($_REQUEST["newfaq"]), 
																	'answer'=>preprocessString($_REQUEST["newanswer"]),
																	'sectionptr'=>$nextFaq['sectionptr'], 'sequence'=>$nextFaq['sequence']+1), 1);

		echo "refresh#$newId";
		
	}
	else {
//print_r($_REQUEST);		
		foreach($_REQUEST as $k => $v) if(strpos($k, 'q_') === 0) $faqid = substr($k, strlen('q_'));
		if($faqid) 
			updateTable('faq', array('question'=>preprocessString($_REQUEST["q_$faqid"]), 
																'answer'=>preprocessString($_REQUEST["a_$faqid"]),
																'hidden'=>($_REQUEST["hide_$faqid"] ? 1 : 0)), 
									"faqid = $faqid", 1);
		echo 'ok';
	}
	exit;
	
	//echo "<root><question>{$template['subject']}</subject><body><![CDATA[$body]]>";
}

$faqs = fetchAssociations(
			"SELECT faq.*, faqsection.label as sectionlabel
			FROM `faq`
			LEFT JOIN faqsection ON sectionptr = sectionid
			WHERE 1
			ORDER BY faqsection.sequence, faq.sequence");
			


if($_REQUEST['replace']) {  // in lightbox
	file_put_contents ('manual/faq.htm', file_get_contents('http://leashtime.com/faq-generator.php'));
	echo "<font color='darkgreen'><h2>Replaced FAQs</h2></font><p>";
	exit;
}

if($_REQUEST['structure']) {
		foreach($faqs	as $faq) {
			if($faq['sectionptr'] != $section)
				echo "{$faq['sectionptr']}|{$faq['sectionlabel']}\n";
			$section = $faq['sectionptr'];
			$max = 40;
			$quest = $faq['question'];
			if(strlen($quest) > $max) $quest = substr($quest, 0, min($max, strlen($quest))).'...';
			$quest = safeValue($quest);
			echo "Q|{$faq['sectionptr']}_{$faq['sequence']}|$quest\n";
		}
	exit;
}
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
?>
<input type='button' value="Replace FAQ's" onclick='replace()'>
<input type='button' value="Instructions" onclick='instructions()'>
<hr>
<?
		
$section = null;
foreach($faqs	as $faq) {
	if($faq['sectionptr'] != $section) {
		if($section) $rows[] = 
			array('#CUSTOM_ROW#'=> "\n<tr class=questionlinerow><td class=questionline>
										<input type='button' value='New' onclick='openNewFAQEditor($faqid, 1)'></td></tr>");
		
		$rows[] = array('#CUSTOM_ROW#'=> "\n<tr><td colspan=2 class='sectionhead'>{$faq['sectionlabel']} <input type='button' value='Preview' onclick='preview()'></td></tr>");
	}
	$section = $faq['sectionptr'];
	$question = safeValue($faq['question']);
	$answer = safeValue($faq['answer']);
	$selectId = "MOVE|$section"."_{$faq['sequence']}";
	$faqid = $faq['faqid'];
	$checked = $faq['hidden'] ? 'CHECKED' : '';
	$rows[] = array('#CUSTOM_ROW#'=> "\n<tr class=questionlinerow><td class=questionline><a name='anchor_$faqid'></a> <form name='form$faqid' method='post'>
										<input type='hidden' name='go' value=1>
										<input type='button' value='Save' onclick='save($faqid)'>
										Hide: <input type=checkbox id='hide_$faqid' name='hide_$faqid' $checked>
										Move After:
										<select id='SELECT_$faqid' name='$selectId'></select>
										<input type='button' value='New' onclick='openNewFAQEditor($faqid)'><br>
										Q{$faq['sequence']}. 
										<input id='q_$faqid' name='q_$faqid' value='$question' size=120></td></tr>");
	$rows[] = array('#CUSTOM_ROW#'=> "\n<tr><td class=answerline>
										<textarea id='a_$faqid' name='a_$faqid' cols=90 rows=4>$answer</textarea></form></td></tr>");
}

$rows[] = 
			array('#CUSTOM_ROW#'=> "\n<tr class=questionlinerow><td class=questionline>
										<input type='button' value='New' onclick='openNewFAQEditor($faqid, 1)'></td></tr>");



?>

<style>
.sectionhead  {background:#e0f8ff;padding-top:30px;vertical-align:middle;font-weight:bold;font-size:14pt;}
.questionlinerow  {}
.questionline  {background:white;padding-top:20px;}
.answerline  {background:white;}
</style>
<?
$columns = array(''=>'');
tableFrom($columns, $rows, "width='100%'", null, 'sortableListHeader', null, 'sortableListCell', null, $rowClasses, $colClasses);

$instructions = "This page lets you maintain the database of FAQs and replace the FAQs shown
on the FAQ page on the leashtimecom/info site.<p>
<ul>
<li><b>Save</b> - saves an individual question/answer in the db.
<li><b>New</b> - opens a <b>New FAQ question</b> dialog.
<li><b>Hidden</b> - marks the question as hidden (unmark it to publish).
<li><b>Move After</b> - lets you move the question to another place in the list.
<li><b>Preview</b> - shows what the entire finished list will look like after <b>Replace</b> is clicked.
<li><b>Replace FAQ's</b> - Takes the database of FAQs and replaces the contents on the live website.
</ul>
<b>Note</b> - questions without answers are automatically hidden in the preview and the final page.";

$instructions = str_replace("\n", ' ', str_replace("\r", '', $instructions));
//echo "||$instructions||";

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='common.js'></script>
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script language='javascript'>

function openNewFAQEditor(faqid, after) {
	var formhtml = "<input type='button' value='Save New FAQ' onclick='saveNew("+after+")'>\n"
+"<p>Question:<br><input id='newfaq' value='' size=120>\n"
+"<input type=hidden id='nextfaqid' value='NEXT_FAQ_ID' size=120>"
+"<p>Answer:<br><textarea id='newanswer' cols=90 rows=4></textarea>";
	formhtml = formhtml.replace('NEXT_FAQ_ID', faqid);
	$(document).ready(function(){$.fn.colorbox({html:formhtml, width:"830", height:"300", scrolling: true, opacity: "0.3"});
		});
}

function saveNew(after) {
		var q = 'newfaq='+escape(document.getElementById('newfaq').value);
		var a = 'newanswer='+escape(document.getElementById('newanswer').value);
		var x = 'nextfaqid='+escape(document.getElementById('nextfaqid').value);
		var hide = 'hide_'+faqid+'='+escape(document.getElementById('hide_'+faqid).value);
		after = after ? '&after=1' : '';
		ajaxGetAndCallWith('faq-editor.php?go=1&'+q+'&'+a+'&'+x+after+'&'+hide, postSave, 1);
}

function save(faqid) {
	//eval("document."+'form'+faqid).submit();
		var move = document.getElementById('SELECT_'+faqid);
		move = move.name+'='+move.options[move.selectedIndex].value;
		var q = 'q_'+faqid+'='+escape(document.getElementById('q_'+faqid).value);
		var a = 'a_'+faqid+'='+escape(document.getElementById('a_'+faqid).value);
		var hide = 'hide_'+faqid+'='+escape(document.getElementById('hide_'+faqid).value);
		ajaxGetAndCallWith('faq-editor.php?go=1&'+move+'&'+q+'&'+a+'&'+hide, postSave, 1);
}

var sURL;
function postSave(arg, response) {
	response = jstrim(response);
	if(response == 'ok') alert('FAQ Saved');
	else if(response.indexOf('error') == 0) alert('['+response+']');
	else if(response == 'moved') updateStructSelects(arg, response);
	else if(response.indexOf('refresh') == 0) {
		sURL = 'faq-editor.php'+response.substring('refresh'.length);
		refresh();
	}
	else  alert('['+response+']');
}

function refreshSelects() {
	ajaxGetAndCallWith('faq-editor.php?structure=1', updateStructSelects, 1);
}

function updateStructSelects(x, rawdata) {
	var selects = document.getElementsByTagName('select');
	for(var i=0; i<selects.length;i++)
		updateStructSelect(selects[i], rawdata);
}

function updateStructSelect(el, rawdata) {
	//alert(rawdata);
	el.options.length=0;
	el.options[el.options.length] = new Option("Select", 0, false, false);
	el.options[0].style.backgroundcolor='yellow';
	var data = rawdata.split("\n");
	for(var s = 0; s < data.length; s++) {
		if(!jstrim(data[s])) continue;
		var line = data[s].split('|');
		var option;
		//if(!confirm(line)) return;
		if(line[0] == 'Q') option = new Option(line[2], line[1], false, false);
		else {
			option = new Option(line[1], line[0], false, false);
			option.style.fontWeight='bold';
			
		}
		el.options[el.options.length] = option;
	}
}

function preview() {
	$.fn.colorbox({href: "faq-generator.php?preview=1", width:"600", height:"600", iframe:true, scrolling: "auto", opacity: "0.3"});
}

function instructions() {
	var html = "<div><?= $instructions ?></div>";
	$.fn.colorbox({html: html, width:"600", height:"600", iframe:false, scrolling: "auto", opacity: "0.3"});
}


function replace() {
	$.fn.colorbox({href: "faq-editor.php?replace=1", width:"600", height:"200", iframe:true, scrolling: "auto", opacity: "0.3"});
}

refreshSelects();
</script>
<? include "refresh.inc";

function preprocessString($str) {
	$str = str_replace("“", '"', $str);
	$str = str_replace("”", '"', $str);
	$str = str_replace("’", "'", $str);
	return $str;
}
