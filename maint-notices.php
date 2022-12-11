<? // maint-notices.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');

extract($_REQUEST);

if($showAgain) {
	updateTable('relusernotice', array('shownomore'=>0), "noticeptr=$showAgain", 1);
	$n = mysqli_affected_rows() ? mysqli_affected_rows() : '0';
	echo "Notice #$showAgain will be shown again. $n rows affected.<p>";
	$viewReport = $showAgain;
}

if($viewReport) {
	$report = fetchAssociations(
		"SELECT relusernotice.*, bizptr, ifnull(bizname, CONCAT('(', db,')')) as bizname, 
			CONCAT_WS(' ', fname, lname) as username, loginid, rights
		 FROM relusernotice
		 LEFT JOIN tbluser ON userid = userptr
		 LEFT JOIN tblpetbiz ON bizid = bizptr
		 WHERE noticeptr = $viewReport
		 ORDER BY date");
	$roles = explodePairsLine('o|Manager||p|Sitter||c|Client||d|Dispatcher||z|LT Staff');
	$columns = explodePairsLine('date|Date||bizname|Business||role|Role||loginid|Login||username|User Name||shownomore|No More');
	foreach($report as $i => $row) {
		$noMore = $noMore || $row['shownomore'];
		$role = $row['rights'] ? $roles[substr($row['rights'], 0, 1)] : '?';
		$report[$i]['role'] = $role;
		$report[$i]['date'] = date('m/d/Y H:i', strtotime($row['date']));
		$rowClass =	strpos($rowClass, 'EVEN') ? 'futuretask' : 'futuretaskEVEN';
		$rowClasses[] =	$rowClass;
	}
	$extraBodyStyle = ";background-image:url('');";
	include "frame-bannerless.php";
	echo 'This notice has been shown to '.count($report).' users. ';
	if($noMore) fauxLink('Clear All "Show No More" flags', 
						"document.location.href=\"maint-notices.php?showAgain=$viewReport\"",
						0, 'Click here to override all past "Do not show again" clicks');
	echo '<p>';
	tableFrom($columns, $report, "", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'
	exit;
}

if($delete) {
	deleteTable('tblusernotice', "noticeid = $delete", 1);
	echo "<script language='javascript'>parent.$.fn.colorbox.close();parent.update();</script>";
	exit;
}

if($showmessage) {
	$innerHTML = fetchRow0Col0(
		"SELECT innerhtml 
		 FROM tblusernotice 
		 WHERE noticeid = $showmessage LIMIT 1");
	$noParagraphs = strpos($innerHTML, '<p') === FALSE;
	$innerHTML = str_replace("\r", "", $innerHTML);
	if($noParagraphs) {
		$innerHTML = str_replace("\n\n", "<p>", $innerHTML);
		$innerHTML = str_replace("\n", "<br>", $innerHTML);
	}
	else $innerHTML = str_replace("\n", " ", $innerHTML);
	$innerHTML = str_replace('"', '&quot;', $innerHTML);
	
	echo '<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" /> ';
	echo "<body style='background:white;'><div class='noticeblock'>$innerHTML</div>";
	echo "
<script language='javascript'>
function toggleNoticeDiv(linkSpan, forceoff) {
	forceoff = typeof forceoff == 'undefined' ? false : forceoff;
	var children = linkSpan.parentNode.childNodes;
	for(var i = 1; i < children.length; i++) {
		var el = children[i];
		if(el.tagName) {
			var show = el.tagName.toLowerCase() == 'div' ? 'block' : 'inline';
			el.style.display = forceoff ? 'none' : (el.style.display == 'none' ? show : 'none');
		}
	}
}

function highlightNoticeItem(item) {
	var wasOff = item.parentNode.childNodes[1].style.display == 'none';
	var parent = item.parentNode;
	while(parent != null && (!parent.tagName || (parent.tagName != 'OL' && parent.tagName != 'UL'))) {
		parent = parent.parentNode;
	}
	if(parent.tagName.toLowerCase != 'ol' && parent.tagName.toLowerCase != 'ul') {
		var children = parent.childNodes;
		for(var k = 0; k < children.length; k++) {
			if(typeof children[k].childNodes[0] != 'undefined' &&
					children[k].childNodes[0].tagName == 'SPAN' ) {
							children[k].style.borderWidth=1;
							children[k].style.borderColor='black';
							toggleNoticeDiv(children[k].childNodes[0], true);
						}
		}
	}
	if(wasOff) toggleNoticeDiv(item);
}
</script>";
	exit;
}

if($savenotice) {
	$usertypes = array();
	foreach(explode(',', 'o,d,c,p,z') as $code)
		if($_POST["usertype_$code"])
			$usertypes[] = $code;
	$usertypes = join(',', $usertypes);

	$added = $added ? date('Y-m-d H:i:s', strtotime($added)) : date('Y-m-d H:i:s');
	$notice = array(
		'premieres'=>date('Y-m-d H:i:s', strtotime($premieres)),
		'expires'=>($expires ? date('Y-m-d H:i:s', strtotime($expires)) : sqlVal("''")),
		'showonce'=>($showonce ? 1 : 0),
		'logintimeonly'=>($logintimeonly ? 1 : 0),
		'staffonly'=>($staffonly ? 1 : 0),
		'targetpagepattern'=>($targetpagepattern ? $targetpagepattern : sqlVal("''")),
		'usertypes'=>sqlVal("'$usertypes'"),
		'bizptr'=>($bizptr ? $bizptr : 0),
		'orgptr'=>($orgptr ? $orgptr : 0),
		'innerhtml'=>($innerhtml ? $innerhtml : sqlVal("''")),
		'added'=>$added,
		'label'=>$label
	);
	if($noticeid) updateTable('tblusernotice', $notice, "noticeid = $noticeid", 1);
	else insertTable('tblusernotice', $notice, 1);
	
	for($i=0;$i < strlen($innerhtml); $i++) {
		if(substr($innerhtml, $i, 1) == "'") $numQuotes++;
	}
	if($numQuotes % 2)
		echo "<script language='javascript'>parent.lightBoxWarning('WARNING: Message has an odd number of single quotes.');</script>";
	
	echo "<script language='javascript'>parent.$.fn.colorbox.close();parent.update();</script>";
	exit;
}

if(isset($noticeid)) {
	if($noticeid) {
		$notice = fetchFirstAssoc(
			"SELECT tblusernotice.*, bizname 
			 FROM tblusernotice 
			 LEFT JOIN tblpetbiz ON bizid = bizptr
			 WHERE noticeid = $noticeid LIMIT 1");
		$added = date('m/d/Y H:i', strtotime($notice['added']));
		$premieres = date('m/d/Y H:i', strtotime($notice['premieres']));
		$expires = date('m/d/Y H:i', strtotime($notice['expires']));
		$showonce = $notice['showonce'];
		$logintimeonly = $notice['logintimeonly'];
		$staffonly = $notice['logintimeonly'];
	}
	include "frame-bannerless.php";
	echo "<form name='editnotice' method='POST'>";
	echo "<table>";
	hiddenElement('savenotice', 1);
	hiddenElement('noticeid', $noticeid);
	hiddenElement('added', $added);
	$deleteButton = $noticeid ? "<input type='button' onclick='deleteNotice()' value='Delete' class='HotButton'>" : '';
	$button = "$added <input type='button' onclick='saveNotice()' value='Save' class='Button'> $deleteButton";
	labelRow('Added:', '', $button, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
	inputRow('Label:', 'label', $notice['label'], $labelClass=null, $inputClass='VeryLongInput');
	calendarRow('Premieres:', 'premieres', $premieres, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $timeAlso=1);
	calendarRow('Expires:', 'expires', $expires, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null, $timeAlso=1);
	checkboxRow("Show Once Only:", 'showonce', $notice['showonce'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null);
	checkboxRow("Show at Login Time:", 'logintimeonly', $notice['logintimeonly'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null);
	checkboxRow("Show to Staff Only:", 'staffonly', $notice['staffonly'], $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null);
	inputRow("Show on pages matching:", 'targetpagepattern', $notice['targetpagepattern']);
	ob_start();
	ob_implicit_flush(0);
	foreach(explodePairsLine('Manager|o||Dispatcher|d||Sitter|p||Client|c||LT Staff|z') as $label => $code) {
		$checked = strpos($notice['usertypes'], $code) !== FALSE;
		labeledCheckbox($label, "usertype_$code", $checked, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
	}
	$userTypeCBs = ob_get_contents();
	ob_end_clean();
	labelRow('User Types', '', $userTypeCBs, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=true);
	$bizoptions =  array('Nobody'=>-1, 'Any Business'=>null);
	foreach(fetchKeyValuePairs("SELECT ifnull(bizname, CONCAT('(', db, ')')) as nm, bizid FROM tblpetbiz WHERE activebiz = 1  ORDER BY nm") 
						as $label=>$id) {
		$bizoptions[$label] = $id;
	}
	selectRow("Business:", 'bizptr', ($noticeid ? ($notice['bizptr'] ? $notice['bizptr'] : null) : -1), $bizoptions);
	inputRow("Org:", 'orgptr', $notice['orgptr']);
	textRow('HTML', 'innerhtml', $notice['innerhtml'], $rows=20, $cols=90, $labelClass=null, $inputClass='fontSize1_3em', $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null);
	echo "</table>";
	echo "</form>";
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

function deleteNotice() {
	if(confirm('Delete this notice?')) {
		var notice = document.getElementById('noticeid').value;
		document.location.href="maint-notices.php?delete="+notice;
	}
}

function saveNotice() {
	if(MM_validateForm(
			"premieres", "", "R",
			"premieres", "", "isDateTime",
			"expires", "", "R",
			"expires", "", "isDateTime"))
			document.editnotice.submit();
}

<?
dumpPopCalendarJS();
?>
</script>
<?
	exit;
}


$notices = fetchAssociations(
	"SELECT tblusernotice.*, bizname 
	 FROM tblusernotice 
	 LEFT JOIN tblpetbiz ON bizid = bizptr
	 ORDER BY noticeid");

$columns = explodePairsLine("id|ID||bullseye| ||label| ||dateadded|Added||premierdate|Premiers||expiration|Expires||once|Once||loginTime|Login||pattern|Pattern||bizname|Business||orgptr|Org||staff|Staff||utypes|User Types");

$now = date('Y-m-d H:i:s');
$utypeLabels = explodePairsLine('o|Manager||d|Dispatcher||p|Sitter||c|Client||z|LT Staff');

foreach($notices as $notice) {
	$row = $notice;
	$title = str_replace("\n", ' ', str_replace("\r", '', strip_tags($row['innerhtml'])));
	$title = truncatedLabel(safeValue($title), 50);
	$row['id'] = fauxLink($row['noticeid'], "openEditor({$row['noticeid']})", 1, $title);
	$row['bullseye'] = fauxLink("<img src='art/branch.gif'>", "showMessage({$row['noticeid']})", 1);
	$row['dateadded'] = date('m/d/Y H:i', strtotime($notice['added']));
	$row['premierdate'] = date('m/d/Y H:i', strtotime($notice['premieres']));
	$row['expiration'] = date('m/d/Y H:i', strtotime($notice['expires']));
	if(strtotime($notice['expires']) < time()) $row['expiration'] = "<span style='font-weight:bold;color:#555555'>{$row['expiration']}</span>";
	$row['once'] = $notice['showonce'] ? 'yes' : 'no';
	$row['loginTime'] = $notice['logintimeonly'] ? 'yes' : 'no';
	$row['staff'] = $notice['staffonly'] ? 'yes' : 'no';
	$row['bizname'] = $row['bizname'] ? $row['bizname'] : ($row['bizptr'] == -1 ? 'Nobody' : 'All Businesses');
	$row['pattern'] = $notice['targetpagepattern'];
	foreach(explode(',', $notice['usertypes']) as $t)
		$row['utypes'][] = $utypeLabels[$t];
	if($row['utypes']) {
		$row['utypes'] = join(', ', $row['utypes']);
		$row['utypes'] = fauxLink($row['utypes'], "viewReport({$row['noticeid']})", 1);
	}
	$premcmp = strcmp($notice['premieres'], $now);
	$expmcmp = $notice['expires'] ? strcmp($now, $notice['expires']) : -1;
	$inProgress = $premcmp <=  0 && $expmcmp < 0;
	$past = $premcmp <=  0 && $expmcmp >= 0;
	$rowClass =	$past ? (strpos($rowClass, 'EVEN') ? 'completedtask' : 'completedtaskEVEN') : (
							$inProgress ? (strpos($rowClass, 'EVEN') ? 'noncompletedtask' : 'noncompletedtaskEVEN') : (
							(strpos($rowClass, 'EVEN') ? 'futuretask' : 'futuretaskEVEN')));
	$rowClasses[] =	$rowClass;
	$rows[] = $row;
}


$windowTitle = "User Notices";
include 'frame-maintenance.php';
?>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<style>
.biztable td {padding-left:10px;}
</style>
<?
fauxLink("New Notice", "openEditor(\"\")");
tableFrom($columns, $rows, "", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'
?>
<script language='javascript'>
function openEditor(id) {
	$.fn.colorbox({href: "maint-notices.php?noticeid="+id, width:"750", height:"570", iframe:true, scrolling: "auto", opacity: "0.3"});
}

function viewReport(id) {
	$.fn.colorbox({href: "maint-notices.php?viewReport="+id, width:"800", height:"570", iframe:true, scrolling: "auto", opacity: "0.3"});
}

function showMessage(id) {
	$.fn.colorbox({href: "maint-notices.php?showmessage="+id, width:"750", height:"470", iframe:true, scrolling: "auto", opacity: "0.3"});
}

function lightBoxWarning(warning) {
	alert(warning);
	//$.fn.colorbox({html: warning, width:"550", height:"470", iframe:true, scrolling: "auto", opacity: "0.3"});
}

function update() {
	document.location.href="maint-notices.php";
}

</script>