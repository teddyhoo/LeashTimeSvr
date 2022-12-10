<? // sms-view.php
use Twilio\Rest\Client; // for checking on unresolved messages

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "sms-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
locked('o-');

$extraHeadContent = "<style>
	.outbound {background: #eeeeee; width:80%; padding:5px;} 
	.inbound {background: #6165FF; color:white; margin-left:auto; margin-right: 0px; width:80%; padding:5px;}
	.timeline {font-size:0.9em;color:gray;text-align:center;padding:10px;}
	.latest {font-family:\"Times New Roman\", Georgia, Serif;border:solid darkgrey 1px;margin-bottom:5px;}
</style>\n"//position:relative;float:right;
.'<link rel="stylesheet" href="font-awesome-4.6.3/css/font-awesome.min.css">';	
if($_GET['client']) {
	$correspondent = getClient($_GET['client']);
	$label = 'Client';
	$arg = "client={$_GET['client']}";
}
else if($_GET['provider']) {
	$correspondent = getProvider($_GET['provider']);
	$label = 'Sitter';
	$arg = "provider={$_GET['provider']}";
}
else if($_GET['user']) {
	$correspondent = getUserByID($_GET['user']);
	// make sure user belongs to this database
	if($correspondent['bizptr'] != $_SESSION['bizptr']) {
		echo "Bad user(db) [{$correspondent['bizptr']}]";
		exit;
	}
	$label = 'User';
	$arg = "user={$_GET['user']}";
}

// MESSAGE SENDING
if($_POST['action'] == 'send') {
	$msgToSend = trim($_POST['msg']);
	$result = notifyByLeashTimeSMS($correspondent, $msgToSend);
	if(is_string($result)) $errors[] = "Message was not sent: $result";
	else $msg = '';
	globalRedirect("sms-view.php?$arg&scroll=1&error=".urlencode(join("<br>", $errors)));
}
// END MESSAGE SENDING


if($daysPast = $_SESSION['preferences']['smsViewDays'])
	$filter = "datetime >= '".date('Y-m-d 00:00:00', strtotime("-$daysPast days"))."'";


//print_r($correspondent);
$messages = getSMSCommsFor($correspondent, $filter, $clientflg=$_GET['client'], $totalMsg=false);

$width = 500;
$textwidth = 80;;

require "frame-bannerless.php";
if($_GET['back']) 
	$backButton = '<div class="fa fa-arrow-left fa-1x" style="display:inline;color:gray;cursor:pointer;" title="Back to Recent messages" onclick="document.location.href=&quot;sms-recent-view.php&quot;"></div>';
echo "<h2>$backButton $label ".correspondentLink($correspondent)." Mobile Messages</h2>";
if($daysPast) echo "... in the last $daysPast days.<p>";

function correspondentLink($correspondent) {
	if($correspId = $correspondent['clientid'])
		$correspondentType = 'client';
	else if($correspId = $correspondent['providerid'])
		$correspondentType = 'provider';
	if(!$correspId) 
		return "{$correspondent['fname']} {$correspondent['lname']}";
	$url = "$correspondentType-edit.php?id=$correspId";
	
	return fauxLink("{$correspondent['fname']} {$correspondent['lname']}",
											"window.parent.location.href=\"$url\"", 1, "Go to this $correspondentType's profile.");									
}


$refreshButton0 = '<div class="fa fa-refresh fa-1x" style="display:inline;color:gray;cursor:pointer;padding-left:3px;padding-top:3px;" title="Refresh" onclick="document.location.href=&quot;?XXX&quot;"></div>';

$refreshButton = str_replace('XXX', $arg, $refreshButton0);
if($messages) {
	$msg =  $messages[count($messages) - 1];
	//$jumpLink = "<a href='#thebottom'>(jump down)</a>";
	$jumpLink = fauxLink('(jump down)', 'window.scrollTo(0,document.body.scrollHeight);', 1, 'See the latest message in context');;
	echo "<div class='fontSize1_6em latest'>$refreshButton The Latest: $jumpLink<p><i>";
	
	if(!in_array($msg['status'], array('delivered', 'received'))
				&& time() - strtotime($msg['sortdate']) < 10) {
		// try fetching the message again until delivered or for two seconds max
		$t0 = microtime(1);
		while(microtime(1) - $t0 < 2000) {
			$msg = getSMSComm($msg);
			if(in_array($msg['status'], array('delivered', 'received')))
				break;
		}
	}
	
	dumpMessage($msg);
	echo "</i></div>";
}

function dumpMessage($msg) {
	
	/*if(!in_array($msg['status'], array('delivered', 'received'))) {
		// in case callback failed, check to see if visit was received after all
		require_once "twilio-gateway-class.php";
		$gateway = getLeashTimeTwilioGateway();
		$sms = $gateway->getSMSById($id);
		if($sms) $smsStatus = $sms->status;
		else echo "<h1>BANG! BANG!</h1>";
		print_r($sms);echo "<h1>$smsStatus</h1>";
	}*/
	
	
	
	$warning = in_array($msg['status'], array('delivered', 'received', 'sent', 'queued')) ? '' : "<span style='color:red;' title='Status: {$msg['status']} from: {$msg['fromphone']} to: {$msg['tophone']}'>X</span>";
	$warningPrefix = !$warning ? '' : "<span style='color:red;'>MESSAGE COULD NOT BE SENT<br></span>";
	$estPrice = ($msg['numsegments'] * 0.0075)." {$msg['priceunit']}";
	$length = strlen($msg['body']).' chars. ';
	$cost = "STAFF ONLY: $length {$msg['numsegments']} segments, {$msg['priceunit']} {$msg['price']} ($estPrice)";
	$cost = "<span title='$cost' style='font-weight:bold;'>$</span>";
	echo "<div class='timeline'>{$msg['displaydate']} $warning $cost</div>";
	$class = $msg['inbound'] ? 'inbound' : 'outbound';
	$body = str_replace("\n", "<br>", str_replace("\n\n", "<p>", $msg['body']));
	//."<pre>".print_r($msg, 1)."</pre>";
	echo "<div class='$class'>$warningPrefix$body</div>";
}
?>


<div  class="fontSize1_2em" style='width:<?= $width ?>px;background:white;border:solid gray 1px;padding:5px;'>
<? 
if(!$messages) echo "No text messages found for {$correspondent['fname']} {$correspondent['lname']}.";
foreach($messages as $i => $msg) {
	// if the last message is not delivered,it may be because it was just sent, but the delivery notice is delayed
	// if the last message is undelivered and less than 10 seconds old...
	if($i + 1 == count($messages) 
					&& !in_array($msg['status'], array('delivered', 'received'))
					&& time() - strtotime($msg['sortdate']) < 10) {
		// try fetching the message again until delivered or for two seconds max
		$t0 = microtime(1);
		while(microtime(1) - $t0 < 2000) {
			$msg = getSMSComm($msg);
			if(in_array($msg['status'], array('delivered', 'received')))
				break;
		}
	}
	dumpMessage($msg);
}
echo "<a name='thebottom'></a>";
?>
</div>
<?

$offerComposer = smsEnabled($fromLeashTimeAccount=true); // enabled for all beta testers 4/16/2018
$primarySMSPhoneNumber = $_POST['num'] ? $_POST['num'] : findSMSPhoneNumberFor($correspondent);

if($_REQUEST['error']) echo "<span class='warning'><br>{$_REQUEST['error']}</span>";
if(!$primarySMSPhoneNumber) {
	$problem = $label == 'User' ? 'does not have a designated text-enabled phone number' : 'does not have a text-enabled Primary phone number';
	echo "<p><div id='charcounter' class='tiplooks'>$label {$correspondent['fname']} {$correspondent['lname']} $problem.<br></div>";
}

else if($offerComposer) {
?>
<p>
<?
$recipientType = $_GET['client'] ? 'client' : ($_GET['provider'] ? 'provider' : ($_GET['user'] ? 'user' : ''));
$templateTypes = explodePairsLine('client|client||provider|provider||user|staff');
echoButton('send', 'Send', $onClick='checkAndSubmit()', $class='', $downClass='', $noEcho=false, $title=null);
echo " Text message to {$correspondent['fname']} {$correspondent['lname']}: $primarySMSPhoneNumber [STAFF ONLY]";
echo "<br>Max: 160 characters.  <span id='charcounter' class='tiplooks'></span>";
if((staffOnlyTEST() || dbTEST('sarahrichpetsitting')) && $recipientType) {
	require_once "sms-template-fns.php";
	ensureSMSTemplatesTableExists();
	$templateType = $templateTypes[$recipientType];
	$options = getSMSTemplates($templateType);
	$options = array_merge(array('-- Select a Template --'=>0), $options);
	echo "<br>";
	selectElement('Template: ', 'template', $value=null, $options, $onChange="templateChosen()", $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null);
}
?>
<br>
<form method='POST' name='smsform'>
<? hiddenElement('action', ''); ?>
<textarea rows=3 cols=60 id='msg' name='msg' maxlength=160 onkeyup='countDownChars(this, 160)'><?= $msgToSend ?></textarea>
</form>
<?
}


//$refreshButton0 = '<div class="fa fa-refresh fa-1x" style="display:inline;color:gray;cursor:pointer;" title="Refresh" onclick="document.location.href=&quot;?XXX&quot;"></div>';
$refreshButton = str_replace('XXX', "$arg&scroll=1", $refreshButton0);
$refreshButton = str_replace('1x', '2x', $refreshButton);
echo $refreshButton;

?>
<script language='javascript' src='ajax_fns.js'></script>

<script language='javascript'>
<? if($_REQUEST['scroll']) echo "window.scrollTo(0,document.body.scrollHeight);"; ?>

function updateAndScrollToBottom() {
	document.location.href='?<?= $_SERVER["QUERY_STRING"] ?>&scroll=1';
}

function countChars(el) {
	document.getElementById('charcounter').innerHTML = "("+el.value.length+" characters)";
}

function countDownChars(el, max) {
	document.getElementById('charcounter').innerHTML = "("+(max-el.value.length)+" characters)";
}

<? if($offerComposer) { ?>
function checkAndSubmit() {
	if(document.getElementById('msg').value.trim().length == 0) {
		alert('Please type a message first');
		return;
	}
	document.getElementById('send').disabled = true;
	document.getElementById('action').value= 'send';
	document.smsform.submit();
}
<? } ?>

function templateChosen() {
	if(!document.getElementById('template')) return;
	var id = document.getElementById('template').value;
	if(id == 0) return;
//alert('email-template-fetch.php?id='+id);

	var extraArgs = '<?= $_REQUEST['clientrequest'] ? "&clientrequest={$_REQUEST['clientrequest']}" : "" ?>';
	ajaxGetAndCallWith(
			'sms-template-fetch.php?id='+id+extraArgs
			+'&<?= $arg ?>', updateMessage, null);
}

function updateMessage(unused, resultJSON) {
	var obj = JSON.parse(resultJSON);
	document.getElementById('msg').value = obj.body;
}




</script>
<?
/*
'status'=>$metas[$msg['msgid']]['status'],
'numsegments'=>$metas[$msg['msgid']]['numsegments'],
'price'=>$metas[$msg['msgid']]['price'],
'priceunit'=>$metas[$msg['msgid']]['priceunit']);
*/