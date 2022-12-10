<? // postcard-viewer.php
/*
clientid - id of correspondent.  client must be an active client for the logged in provider
card (optional) dump a display of a postcard
open (optional) open this card automatically
toggle (optional) toggle suppression and open (if not client or not suppressed)
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "preference-fns.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "postcard-fns.php";
//require_once "email-fns.php";
//require_once "comm-composer-fns.php";

if(userRole() == 'c') {
	locked('c-');
	$clientid = $_SESSION["clientid"];
	$notSuppressed = "AND suppressed IS NULL";
}
else {
	locked('o-');
	$clientid = $_REQUEST["client"];
}
$client = getOneClientsDetails($clientid);
$card = $_REQUEST["card"];

if($_REQUEST["toggle"]) {
	$nowSuppressed = toggleSuppression($_REQUEST["toggle"]);
	if(userRole() != 'c') $open = "?open={$_REQUEST["toggle"]}&client=$clientid";
	globalRedirect("postcard-viewer.php$open");
	exit;
}

if($_REQUEST["replytext"]) {
	replyToCardWith($_REQUEST["cardid"], $_REQUEST["replytext"]);
	if(userRole() == 'c') $open = "?open={$_REQUEST["cardid"]}&client=$clientid";
	globalRedirect("postcard-viewer.php$open");
	exit;
}

$mobileView = FALSE;

$postcards = fetchAssociations(
	"SELECT * 
		FROM tblpostcard
		WHERE clientptr = $clientid $notSuppressed
		ORDER BY created DESC");
		
function dumpPostcard($cardid, $inlineVideo=true) {
	$card = fetchFirstAssoc("SELECT * FROM tblpostcard WHERE cardid = $cardid LIMIT 1", 1);
	if(!$card) {
		echo "Postcard #$cardid not found.";
		exit;
	}
	$date = longDate(strtotime($card['created']));
	$sitter = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$card['providerptr']} LIMIT 1", 1);
	$role = userRole();
	$sendClientptr = $role == 'c' ? '' : ", {$card['clientptr']}";
	if($card['attachment']) {
		$type = attachmentType($card['attachment']);
		$title = "title='Click to ".($type == 'VIDEO' ? 'play.' : 'enlarge.')."'";
		if(TRUE || ($type != 'VIDEO' || !$inlineVideo)) {
			$anchorstart = 
				"<a target='viewattachment' href='postcard-composer-mobile.php?clientid={$card['clientptr']}&full={$card['cardid']}'>";
			$anchorend = 
				"</a>";
		}
		else { // INLINE WILL NOT WORK WITHOUT AN IFRAME, and I don't know yet how to detect or set video dimensions
			$onclick= "onclick='viewVideoInline({$card['cardid']} $sendClientptr)'";
		}
	}
	$deleteButtonLabel = $role == 'c' ? 'Delete' : ($card['suppressed'] ? 'Restore' : 'Suppress');
	$toTheClient = $role != 'c' ? ' to the client' : '';
	$hot = $card['suppressed'] ? '' : 'Hot';
	$deleteButton = 
		echoButton('suppressionToggle', $deleteButtonLabel, "toggleSuppressed({$card['cardid']} $sendClientptr)",
								"{$hot}Button", "{$hot}ButtonDown", true, "Don&apos;t show this postcard$toTheClient any more.");
	$noteHTML = htmlText($card['note']);

	echo <<<TABLE
<table width=100% class='fontSize1_1em'>
<tr><td>From: $sitter</td><td align=right>$date<img src='art/spacer.gif' width=7 height=1> $deleteButton</td></tr>
<tr><td colspan=2 id='detailcell'>$anchorstart<img $title  $onclick src="postcard-composer-mobile.php?clientid={$card['clientptr']}&display={$card['cardid']}">$anchorend</td></tr>
<tr><td colspan=2 style='padding-top:6px;'>$noteHTML</td></tr>
</table>
TABLE;
	
	if(userRole() == 'c') {
		markClientViewed($card);
		if(!$card['reply']) echoButton('', 'Write a Reply', 'openReplyBox()');
	}
	else echo ($card['emailed'] ? "Emailed to: {$card['emailed']}" : 'Not emailed.')."<p>";
	
	if($card['reply']) {
		echo "<div  class='fontSize1_1em'><hr>".(userRole() == 'c' ? 'You' : 'The client').' replied:<p>'
					.htmlText($card['reply'])
					.'</div>';
	}
	else if(userRole() == 'c') {
		echo <<<REPLYBOX
<div id='replyboxdiv' style='display:none;'>
<div class='fontSize1_1em'>
<form name='replyform' method='POST'>
<input type='button' value='Send' onclick=
'if(trim(((String)(document.getElementById("replytext").value))) != "") this.form.submit();else alert("Please type a reply first.")'>
<img src='art/spacer.gif' width=100 height=1>
<input type='button' value='Quit' onclick='$.fn.colorbox.close();'>
<input type='hidden' name='cardid' value='{$card["cardid"]}'>
<p>
Reply:
<br>
<textarea style='font-size:1.2em;' id='replytext' name='replytext' rows=10 cols=60></textarea>
</form>
</div>
</div>
REPLYBOX;
	}
	exit;
}
		
function postcardsTable(&$postcards) {
	require_once "provider-fns.php";
	echo "<table width=250px>";
	if(!$postcards) echo "<tr><td>There are no postcards yet, but you can set your postcard preferences above.</td></tr>";
	foreach($postcards as $card) {
		$provs = $provs ? $provs : getProviderNames();
		$class = $card['suppressed'] ? 'postcardsuppressed' : (
						 !$card['viewed'] ? 'postcardunviewed' : 'postcardviewed');
		if($card['attachment'] && $card['expiration'] && !file_exists($card['attachment'])) $class .=  ' postcardexpired';
		$clientArgument = userRole() == 'c' ? '' : ", {$card['clientptr']}";
		echo "<tr class='$class'><td onclick='viewPostcard({$card['cardid']} $clientArgument)'>
						<img src='postcard-composer-mobile.php?clientid={$card['clientptr']}&thumb={$card['cardid']}'></td>
						<td onclick='viewPostcard({$card['cardid']} $clientArgument)'>"
						.longDate(strtotime($card['created']))."<br>".date('g:i a', strtotime($card['created']))
						."<br>{$provs[$card['providerptr']]}</td></tr>";
	}
	echo "</table>";
}

if($card) {
		dumpPostcard($card);
		// EXITS HERE
}

if(!$mobileView) {
	if(userRole() == 'c') {
		include "frame-client.html";
		fauxLink('Postcard Preferences', "openOptions()", false, 'Review and edit your postcard preferences.');
		echo "<p>";
	}
	else {
		$breadcrumbs = "<a href='client-edit.php?id=$clientid&tab=services'>{$client['clientname']}</a> - "
										."<a href='client-edit.php?id=$clientid&tab=communication'>Communications</a> - "
										.fauxLink('Postcard Preferences', "openOptions({$client['clientid']})", 1, 'Change the client&apos;s postcard preferences.');	
		include "frame.html";
	}
}
// ***************************************************************************
?>
<table width='100%'>
<tr><td valign='top'>
<?
postcardsTable($postcards);
?>
</td><td id='detail' valign='top'></td></tr></table>
<?
if(!$mobileView) { ?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function openReplyBox() {
	$.fn.colorbox({html:$('#replyboxdiv').html(), width:"520", height:"300", scrolling: true, opacity: "0.3"});
}

function openOptions(clientptr) {
	$.fn.colorbox({href:"postcard-prefs.php"+(clientptr == undefined ? "" : "?client="+clientptr), width:"550", height:"300", iframe: "TRUE", scrolling: true, opacity: "0.3"});
}

function viewPostcard(cardid, clientptr) {
	ajaxGet("postcard-viewer.php?card="+cardid
						+(clientptr == undefined ? "" : "&client="+clientptr), 
					'detail');
}
function viewVideoInline(cardid, clientptr) { // won't work without an iframe
	ajaxGet("postcard-composer-mobile.php?full="+cardid
						+(clientptr == undefined ? "" : "&client="+clientptr), 
					'detailcell');
}

function toggleSuppressed(cardid, clientptr) {
	var verb = document.getElementById('suppressionToggle').value != 'Restore' 
							? ('<?= userRole() ?>' == 'o' ? 'suppress' : 'delete')
							: 'FALSE';
	if(verb == 'FALSE' || confirm('Are you sure you want to '+verb+' this?'))
		document.location.href="postcard-viewer.php?toggle="+cardid
							+(clientptr == undefined ? "" : "&client="+clientptr);
}

<? if($_REQUEST['open']) { ?>
viewPostcard(<?= $_REQUEST['open']?> <?= $_REQUEST['client'] ? ", {$_REQUEST['client']}" : "" ?>);
<? } ?>
	
</script>
<?
	include "frame-end.html";
}
