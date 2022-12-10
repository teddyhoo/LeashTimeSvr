<? // logbook-editor.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "item-note-fns.php";

locked('o-');

extract(extractVars('itemtable,itemptr,sort,title,editid,savenote,priornoteptr,restorenote'
										.',retireid,deleteid,printable,print,subject,targetid,updateaspect'
										.',summaryid,summarycount,summarytitle,summaryitemtable,summarytotal'
										.',returnURL,returnTag,logOpenFunction', $_REQUEST));
//print_r($_REQUEST);
// NOTES - add 'lastedited', 'lasteditedby'?  add 'subject'?

//print_r($_REQUEST);exit;
if($summaryid) { // return a summary table for use in the client editor
//echo "<p>".print_r($_REQUEST, 1)."<p>";
	if($summarytotal) {
		$numnotes = fetchRow0Col0("SELECT count(*) FROM relitemnote WHERE itemtable = '$summaryitemtable' AND itemptr = $summaryid");
		echo "$numnotes##ENDCOUNT##";
	}
	itemSummary($summaryid, $summaryitemtable, $summarycount, $summarytitle, $logOpenFunction);
	exit;
}
else if($editid && !$savenote) { // return an in-place editor
	logbookItemEditor($editid, $itemtable, $itemptr);
	exit;
}
else if($editid && $savenote) { // save an in-place editor
	if($editid == -1) {
		$editid = 
		insertTable('relitemnote', 
			array('note'=>$savenote, 
						'itemtable'=>$itemtable, 
						'itemptr'=>$itemptr, 
						'date'=>date('Y-m-d H:i:s'), 
						'priornoteptr'=>$priornoteptr,
						'subject'=>$subject,
						'authorid'=>$_SESSION['auth_user_id']),
			1);
	}
	else updateTable('relitemnote', 
			array('note' =>$savenote,
						'subject'=>$subject/*, 
						'date'=>date('Y-m-d H:i:s'), 
						'authorid'=>$_SESSION['auth_user_id']*/), "noteid = $editid");
	if(!mysql_error()) echo $subject.'#ENDOFSUBJECT#'.displayableNote($savenote, $subject, $editid);
	exit;
}
else if($retireid) {
	$note = fetchFirstAssoc("SELECT note, subject FROM relitemnote WHERE noteid = $retireid LIMIT 1");
	echo displayableNote($note['note'], $note['subject'], $retireid);
	exit;
}

else if($deleteid) {
	deleteNote($deleteid);
	echo "deleted";
	exit;
}

else if($print) {
	echo "<a href='javascript:window.print()'>Print this page</a><p>";
	echo "<h2>$title</h2>";
	printItemNotes($print);
	exit;
}
// $itemtable, $itemptr
$sort = $sort ? $sort : 'date asc';
$notes = fetchAssociations("SELECT * FROM relitemnote WHERE itemtable = '$itemtable' AND itemptr = $itemptr ORDER BY $sort");
$title = $title ? $title : 'Notes:';
require_once "frame-bannerless.php";
//print_r($returnURL);
if($returnURL) {
	fauxLink(($returnTag ? $returnTag : 'Return'), "returnTo(\"$returnURL\")");
}
?>
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<style>
.notes { background:white;width:100%}
.notes td { padding-bottom:10px; }
.notesepr { border-top: solid black 1px; }
.headerrow { background:#f5f5f5; } /*  already tried border-top: 1px solid black; border-top: solid black 1px;*/
.notedate { text-align:left; font-weight:bold; }
.authorid { text-align:left; font-weight:bold; padding-left:5px;  }
.subjecttd { text-align:left; font-weight:bold; padding-left:5px;}
.notetd { }
</style>
<h2><?= $title ?></h2>
<? 
echoButton('newnote', 'New Note', 'edit(-1, 1)'); 
echo " ";
if($printable) echoButton('', 'Print Selected Notes', 'printNotes()'); 
?>
<p><table class='notes'>
<?
expandableNoteRows($notes, $itemtable, $itemptr, $printable, $targetid);
?>
</table>
<script language='javascript' src='ajax_fns.js'></script>
<script type="text/javascript" src="check-form.js"></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function showRow(id) {
	//alert("["+id+"]");
	$('.notetd').toggle(false);
	$('#displaynote_'+id).toggle(true);
	$('#quitbutton').click();
}

setPrettynames('savenote', 'Note');
function saveEditor() {
	if(!MM_validateForm('savenote', '', 'R'))
		return;
	var editid = document.getElementById('editid').value;
	var priornoteptr = document.getElementById('priornoteptr').value;
	var params = 'priornoteptr='+(priornoteptr ? priornoteptr : '0')
			+ '&editid='+editid
			+ '&itemtable='+escape(document.getElementById('itemtable').value)
			+ '&itemptr='+document.getElementById('itemptr').value
			+ '&savenote='+escape(document.getElementById('savenote').value)
			+ '&subject='+escape(document.getElementById('subject').value)
			+ '&updateaspect=<?= $updateaspect ?>';
//alert(params);return;			
	if(editid == -1) submitAJAXFormAndCallWith(params, 'logbook-editor.php', function(x,y) {update(1);}, null);
	//else submitAJAXForm(params, 'logbook-editor.php', 'displaynote_'+editid);
	else submitAJAXFormAndCallWith(params, 'logbook-editor.php', 
			function(id, subjectAndNote) {
				subjectAndNote = subjectAndNote.split('#ENDOFSUBJECT#');
				document.getElementById('subject_'+editid).innerHTML = subjectAndNote[0];
				document.getElementById('displaynote_'+editid).innerHTML = subjectAndNote[1];
				update();
			}
	, editid);
}

function retireEditor() {
	var editid = document.getElementById('editid').value;
	if(editid == -1) {
		document.getElementById('displaynote_-1').innerHTML = '';
		document.getElementById('displaynote_-1').style.display = 'none';
	}
	else ajaxGet('logbook-editor.php?retireid='+editid, 'displaynote_'+editid);
}

function edit(id, showFirst) {
	if(id == -1 && document.getElementById('editid') && document.getElementById('editid').value == -1)
		return;
	var moreargs = '';
	if(typeof showFirst != 'undefined' && showFirst) {
		showRow(id);
		moreargs = '&itemtable=<?= urlencode($itemtable) ?>&itemptr=<?= $itemptr ?>';
	}
	ajaxGet('logbook-editor.php?editid='+id+moreargs, 'displaynote_'+id);
}

function deleteNote(id) {
	if(!confirm('Delete this note permanently?')) return;
	ajaxGetAndCallWith('logbook-editor.php?deleteid='+id, update, 1)
}

function update(shouldRefresh) {
	var mom = parent ? parent : (window.opener ? window.opener : null);
	if(mom && mom.update) mom.update('<?= $updateaspect ?>', 0);
	if(shouldRefresh) refresh();
}

function printNotes() {
	var selections = new Array();
	$('input').each(
			function(index, el) { 
				if(el.type == 'checkbox' && el.checked && el.id.indexOf('sel_') == 0) 
					selections[selections.length] = el.id.substring('sel_'.length);
			});
	if(selections.length == 0) {
		alert('Please select at least one note to print.');
		return;
	}
	selections = selections.join(',');
	openConsoleWindow("printnotes", "logbook-editor.php?print="+selections+"&title=<?= urlencode($title) ?>", 600, 600);
}

function returnTo(returnURL) {
	//ajaxGet(returnURL, 'cboxContent', 1);
		parent.$.fn.colorbox({href:returnURL, width:"700", height:"650", iframe: false, scrolling: true, opacity: "0.3"});

}

var sURL = '<?= "logbook-editor.php?itemtable=".urlencode($itemtable)."&itemptr=$itemptr&title=".urlencode($title) ?>'; //(window.location.pathname);
</script>
<? require 'refresh.inc' ?>