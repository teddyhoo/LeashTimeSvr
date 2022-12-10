<? // sms-recent-view.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "sms-fns.php";
require_once "preference-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
locked('o-');

setUserPreference($_SESSION['auth_user_id'], 'lastRecentSMSReviewDate', date('Y-m-d H:i:s'));

$extraHeadContent = "<style>
.outbound {background: #eeeeee; width:80%; padding:5px;} 
.inbound {background: #9BA2FF; color:white; margin-left:auto; margin-right: 0px; width:80%; padding:5px;}
.timeline {font-size:0.9em;color:gray;text-align:center;padding:10px;}
.latest {font-family:\"Times New Roman\", Georgia, Serif;border:solid darkgrey 1px;margin-bottom:5px;}
.correspline {font-size:1.2em;color:888888;text-align:left;width: 50%;}
</style>"; //position:relative;float:right;

function getCorrespondent($msg) {
	if($msg['correstable'] == 'tblclient') {
		$correspondent = getClient($msg['correspid']);
		$correspondent['label'] = 'client';
		$correspondent['viewarg'] = "client={$correspondent['clientid']}";
	}
	else if($msg['correstable'] == 'tblprovider') {
		$correspondent = getProvider($msg['correspid']);
		$correspondent['label'] = 'sitter';
		$correspondent['viewarg'] = "provider={$correspondent['providerid']}";
	}
	else if($msg['correstable'] == 'tbluser') {
		$correspondent = getUserByID($msg['correspid']);
		// make sure user belongs to this database
		if($correspondent['bizptr'] != $_SESSION['bizptr']) {
			echo "Bad user(db) [{$correspondent['bizptr']}]";
			exit;
		}
		$correspondent['label'] = 'staffer';
		$correspondent['viewarg'] = "user={$correspondent['userid']}";
	}
	return $correspondent;
}

function getFirstSubsequentOutboundTextMessage($msg) {
	return fetchFirstAssoc(
		"SELECT *
			FROM tblmessage 
				LEFT JOIN tblmessagemetadata ON msgptr = msgid
			WHERE msgid > {$msg['msgid']}
				AND correstable = '{$msg['correstable']}'
				AND correspid = {$msg['correspid']}
				AND type = 'sms'
			ORDER BY msgid
			LIMIT 1", 1);
}

//print_r($correspondent);
$days = $_REQUEST['days'] ? $_REQUEST['days'] : 1;
$hours = 24 * $days;
$sinceDate = date('Y-m-d h:i:s', strtotime("- $hours hours"));
$messages = (array)getSMSCommsFor(-1, $filter="datetime > '$sinceDate'", $clientflg=false, $totalMsg=false);
$latest = array();
foreach($messages as $msg) {
	// mark inbound messages as negative in $latest
	$msgid = $msg['msgid'] * ($msg['inbound'] ? -1 : 1);
	// if msg is inbound or last msg for corresp was not inbound, replace it
	$lastOneForCorresp = $latest[$msg['correstable']][$msg['correspid']];
	$lastOneForCorresp = $lastOneForCorresp ? $lastOneForCorresp  : 0;
	if($msgid < 0 || $lastOneForCorresp >= 0)
		$latest[$msg['correstable']][$msg['correspid']] = $msgid;
}
foreach($messages as $i => $msg) {
	if($msg['msgid'] != abs($latest[$msg['correstable']][$msg['correspid']]))
		unset($messages[$i]);
	else if(!$msg['inbound'] & !$_GET['showoutbound'])
		unset($messages[$i]);
}
$messages = array_reverse(array_merge($messages));

	

$width = 500;
$textwidth = 80;;
require "frame-bannerless.php";
$refreshButton = '<div class="fa fa-refresh fa-1x" style="display:inline;color:gray;cursor:pointer;" title="Refresh" onclick="document.location.href=&quot;?XXX&quot;"></div>';
$refreshButton = str_replace('XXX', $_SERVER["QUERY_STRING"], $refreshButton);
$shown = (int)$_REQUEST['days'] > 1 ? " ({$_REQUEST['days']} days)" : '';
echo "<h2>$refreshButton Recent Mobile Messages$shown</h2>";
echo '<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';	
echo "<p class='tiplooks'>This shows only the latest text from or to each recipient.  Click to view a text thread.</p>";
if(FALSE && $messages) {
	$msg =  $messages[count($messages) - 1];
	$refreshButton = '<div class="fa fa-refresh fa-1x" style="display:inline;color:gray;cursor:pointer;" title="Refresh" onclick="document.location.href=&quot;?XXX&quot;"></div>';
	$arg = $correspondent['clientptr'] ? "client={$correspondent['clientptr']}" : (
					$correspondent['providerptr'] ? "provider={$correspondent['providerptr']}" : (
					$correspondent['userptr'] ? "user={$_GET['user']}" : ''));
					
	$refreshButton = str_replace('XXX', $arg, $refreshButton);
	echo "<div class='fontSize1_6em latest'>$refreshButton The Latest: <a href='#thebottom'>(jump down)</a><p><i>";
	dumpMessage($msg);
	echo "</i></div>";
}

function dumpMessage($msg) {
	$warning = in_array(strtolower($msg['status']), array('delivered', 'received', 'sent', 'queued')) ? '' : "<span style='color:red;' title='Status: {$msg['status']} from: {$msg['fromphone']} to: {$msg['tophone']}'>X</span>";
	$warningPrefix = !$warning ? '' : "<span style='color:red;'>MESSAGE COULD NOT BE SENT<br></span>";
	$estPrice = ($msg['numsegments'] * 0.0075)." {$msg['priceunit']}";
	$length = strlen($msg['body']).' chars. ';
	$cost = "STAFF ONLY: $length {$msg['numsegments']} segments, {$msg['priceunit']} {$msg['price']} ($estPrice)";
	$cost = "<span title='$cost' style='font-weight:bold;'>$</span>";
	echo "<div class='timeline'>{$msg['displaydate']} $warning $cost</div>";
	$corresp = getCorrespondent($msg);
	$fromTo = $msg['inbound'] ? 'From' : 'To';
if(TRUE || mattOnlyTEST()) {
	if($msg['inbound']) {
		$response = getFirstSubsequentOutboundTextMessage($msg);
		if($response) $checkMark = '<span title="Reply has been sent" style="color:black;font-weight:bold">&check; </span>'; // bright green check mark
	}
}
	$label = "$checkMark{$corresp['fname']} {$corresp['lname']}";
	$label = fauxLink("<div class='fa fa-mobile fa-2x'></div> $label", "document.location.href=\"sms-view.php?back=1&{$corresp['viewarg']}\"", 1, "View this person&apos;s text thread.");
	$class = $msg['inbound'] ? 'inbound' : 'outbound';
	$correspLabel = $corresp['label'] == 'staffer' ? 'admin' : $corresp['label'];
	echo "<div class='$class correspline'>$fromTo: $correspLabel $label</div>";
	$body = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $msg['body']));
	//."<pre>".print_r($msg, 1)."</pre>";
	echo "<div class='$class'>$warningPrefix$body</div>";
}
?>


<div style='width:<?= $width ?>px;background:white;border:solid gray 1px;padding:5px;'>
<? 
$moreDays = $days + 1;
$lessDays = max($days - 1, 1);
if($currentOutbond = $_GET['showoutbound']) fauxLink('Hide Outbound Texts', "document.location.href=\"?showoutbound=0&days=$days\"");
else fauxLink('Show Outbound Texts Also', "document.location.href=\"?showoutbound=1&days=$days\"");

echo " - ";
fauxLink('Show More', "document.location.href=\"?showoutbound=$currentOutbond&days=$moreDays\"");
if($days > 1) {
	echo " - ";
	fauxLink('Show Less', "document.location.href=\"?showoutbound=$currentOutbond&days=$lessDays\"");
}
echo "<p>";

$qualifier = $_GET['showoutbound'] ? '' : ' inbound';
if(!$messages) echo "No recent$qualifier text messages found.";
foreach($messages as $msg) {
	dumpMessage($msg);
}
echo "<a name='thebottom'></a>";
?>
</div>
<?
echo str_replace('1x', '2x', $refreshButton);
/*
'status'=>$metas[$msg['msgid']]['status'],
'numsegments'=>$metas[$msg['msgid']]['numsegments'],
'price'=>$metas[$msg['msgid']]['price'],
'priceunit'=>$metas[$msg['msgid']]['priceunit']);
*/