<?
// email-queue.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "gui-fns.php";
require_once "preference-fns.php";

// Determine access privs
$locked = locked('o-');

if($_SESSION['staffuser']) {
	if($_GET['restart']) {
		setPreference('mailQueueSendStarted', null);
		$_SESSION['frame_message'] = "$db email queue restarted at ".shortDate(time()).' '.date('H:i:s');
		globalRedirect('email-queue.php');  // to get rid of the restart param
		exit;
	}

	if($_GET['enable']) {
		setPreference('mailQueueDisabled', null);
		globalRedirect('email-queue.php');  // to get rid of the restart param
		exit;
	}

	if($_GET['disable']) {
		setPreference('mailQueueDisabled', date('Y-m-d H:i:s')." (".date_default_timezone_get().") by ".($_SESSION["auth_userfname"] ? "{$_SESSION["auth_userfname"]} {$_SESSION["auth_userlname"]}" : $_SESSION["auth_login_id"]));
		globalRedirect('email-queue.php');  // to get rid of the restart param
		exit;
	}
}

$pageTitle = "Email Queue";
$breadcrumbs = "<a href='preference-list.php?show=4' title='Outgoing Email Preferences'>Outgoing Email Preferences</a>";
$breadcrumbs .= " - <a href='comm-prefs.php' title='Communication Preferences for individual Clients, Sitters, and Staff'>Communication Preferences</a> ";
$breadcrumbs .= " - <a href='reports-email-outbound.php' title='Outbound Email report'>Outbound Email report</a> ";
$breadcrumbs .= " - ".fauxLink('Errors', 'showErrors()', 'Show errors');
$breadcrumbs .= " - ".fauxLink('Memos', 'showMemos()', 'Show memos on deck');
include "frame.html";
if(!$_SESSION['staffuser']) {
	echo "<h2>Staff Use Only</h2>";
	echo "<a href='index.php'>Home</a>";
	include "frame-end.html";
	exit;
}


if($_POST) {
	foreach($_POST as $k => $v)
		if(strpos($k, 'msg_') === 0) 
			$ids[] = substr($k, 4);
	if($ids) deleteTable('tblqueuedemail', "emailid IN (".join(',', $ids).")", 1);
}


$queue = fetchAssociations("SELECT * FROM tblqueuedemail ORDER BY emailid");

foreach($queue as $i => $msg) {
	$msgfields = $msg['tblmsgfields'] ? explodePairsLine($msg['tblmsgfields']) : array();
	//mgrname|Mike Perry||correspid|49||correstable|tblprovider
//labeledCheckbox($label, $name, $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=false, $noEcho=false, $title=null) {
	
	$msg['id'] = labeledCheckbox($msg['emailid'], "msg_{$msg['emailid']}", 0, 0, 'mycheck', 0, true, true);
	$msg['from'] = $msgfields['mgrname'];
	$msg['corresp'] = '--';
	if($msgfields['correspid']) {
		$type = substr($msgfields['correstable'], 3);
		if($type == 'user')  $msg['corresp'] = "manager ({$msgfields['correspid']})";
		else if(!$type) {
			$msg['corresp'] = "{$msgfields['correspid']} {$msgfields['correstable']}";
		}
		else {
			$link = "$type-edit.php?id=";
			$person = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tbl$type WHERE {$type}id = {$msgfields['correspid']} LIMIT 1");
			$msg['corresp'] = "<a href='$link{$msgfields['correspid']}'>$person</a>";
		}
	}
	if(mattOnlyTEST()) {
		//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)
		$msg['subject'] = fauxLink($msg['subject'],
												"openConsoleWindow(\"qmessage\", \"comm-view.php?queued=1&id={$msg['emailid']}\",600,600)",
												'noecho');
	}
	if($msg['corresp']) $msg['recipients'] = "{$msg['corresp']} ".$msg['recipients'];
	$msg['date'] = shortDateAndTime(strtotime($msg['addedtime']), 'mil');
	$queue[$i] = $msg;
}

$columns = explodePairsLine('id|ID||date|Added||recipients|To||subject|Subject');

//function tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
?>
<form method='post' name='killmsgs'>
<?
if(count($queue)) {
	fauxLink('Select All', 'selectAll(1)');
	echo " - ";
	fauxLink('Deselect All', 'selectAll(0)');
	echo " - ";
	echoButton('', 'Delete', 'killSelectedMessages()');
}
if($start = getPreference('mailQueueSendStarted')) {
	echo " Queue started: $start (possibly stalled)";
	echoButton('', 'Restart', 'if(confirm("Are you sure?")) document.location.href="email-queue.php?restart=1"');
}
if($disabled = getPreference('mailQueueDisabled')) {
	echo " <br><font color=red>Queue disabled: $disabled</font>";
	echoButton('', 'Re-Enable', 'if(confirm("Are you sure?")) document.location.href="email-queue.php?enable=1"');
}
else {
	echoButton('', 'Disable', 'if(confirm("Are you sure?")) document.location.href="email-queue.php?disable=1"', 'HotButton', 'HotButtonDown');
}

$reloadSymbol = "&#8635;";
echo " <span style='font-size:1.5em;font-weight:bold;'>".fauxLink($reloadSymbol, "document.location.href=document.location.href", 1)."</span>";
if(count($queue) == 0) echo '<p>The queue is empty.' ;
else {
	echo '<p>Queued messages at '.date('H:i:s').': '.count($queue).'<p>';
	tableFrom($columns, $queue, 'width=100%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}
?>
</form>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
var nchecked = 0;
function killSelectedMessages() {
	nchecked = 0;
	$('.mycheck').each(function(i, el) {if(el.checked) nchecked++;});
	if(nchecked == 0) {
		alert('Please select at least one message to delete first.');
		return;
	}
	document.killmsgs.submit();
}

function selectAll(onoff) {
	$('.mycheck').attr('checked', onoff);
}


function showErrors() {
	$.fn.colorbox({href:"reports-errors.php", width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: "true"});
}

function showMemos() {
	$.fn.colorbox({href:"reports-memos-on-deck.php?lightbox=1", width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: "true"});
}
</script>
<?
include "frame-end.html";
