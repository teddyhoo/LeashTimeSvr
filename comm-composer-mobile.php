<? // comm-composer-mobile.php
/*
client or provider - id of correspondent
replyto - id of msge being replied to
all - reply to all
SCRIPTVARS
properties - optional pipe-separated string to be put into a hidden element named properties
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "comm-composer-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

if(userRole() == 'p') {
	// ensure provider-client email is permitted
	$proceed = $_REQUEST['client'] && !$_REQUEST['$provider'] && $_SESSION['preferences']['trackSitterToClientEmail'];
	if($proceed) {
		// ensure client belongs to provider
		require_once "provider-fns.php";
		$activeClients = getActiveClientIdsForProvider($_SESSION["providerid"]);
		
		//$id = $_SESSION["clientid"];
		if(!in_array($_REQUEST['client'], $activeClients)) {
			$proceed = false;
			$error = "Insufficient access rights.";
		}
		
	}
	if(!$proceed) {
		$extraBodyStyle = 'padding:10px;';
		require "mobile-frame-bannerless.php";
		echo "<h1>$error</h1><h2>";
		fauxLink('<b>Close</b>', 'if(window.opener) window.close(); else parent.$.fn.colorbox.close()', 0, 'Go back');
		echo "</h2>";
		exit;
	}
}

else $locked = locked('ka');//locked('o-');  // ??

extract(extractVars('replyto,all,client,provider,correspid,corresname,spousename,clientemail2,correstable,correspaddr,subject,msgbody,mgrname,lname,fname,email', $_REQUEST));


if($replyto) {
	$reference = fetchFirstAssoc("SELECT * FROM tblmessage WHERE msgid = $replyto LIMIT 1");
	if($reference['correstable'] == 'tblclient') $client = $reference['correspid'];
	else $provider = $reference['correspid'];
}

if(isset($client)) {
	require_once "client-fns.php";
	$correspondent = getClient($client);
	$corresTable = 'tblclient';
	$corresType = 'Client';
	$correspId = $client;
	if($correspondent['fname2']) $spouse[] = $correspondent['fname2'];
	if($correspondent['lname2']) $spouse[] = $correspondent['lname2'];
	if($spouse) $spouseName = join(' ', $spouse);
	$spouseLabel = $spouse ? "to $spouseName" : "to altername name";
}
else if(isset($provider)) {
	require_once "provider-fns.php";
	$correspondent = getProvider($provider);
	$corresTable = 'tblprovider';
	$corresType = 'Sitter';
	$correspId = $provider;
}



if($_POST) {
	$allSuppliedEmails[] = $correspaddr;
	$recipients = array("\"$corresname\" <$correspaddr>");
	if($_POST['clientemail2']) {
		$allSuppliedEmails[] = $_POST['clientemail2'];
		$recipients[] = "\"$spousename\" <{$_POST['clientemail2']}>";
		//$correspondents[] = array('tblclient', $correspid);
	}
	
	if($reference && $all) {  // use BCC addresses only
		if(strpos($reference['correspaddr'], '|')) {
			$adds = array();
			$parts = explode('|', $reference['correspaddr']);
			foreach($parts as $labelList) {
				$labelList = explode(':', $labelList);
				if(strtoupper($labelList[1]) == 'BCC' && trim($labelList[1])) $bcc = $labelList[1];
			}
		}
	}
	
	if(userRole() == 'p') {
		$replyTo = $_SESSION["provider_email"];
		$prefs = $_SESSION['preferences'];
		if(!$replyTo || $prefs['replyToOfficeInSitterToClientEmail'])
			$replyTo = $prefs['defaultReplyTo'] ? $prefs['defaultReplyTo'] : $prefs['bizEmail'];
		$extraHeaders = array('Reply-to'=>$replyTo);
	}
	else $extraHeaders = null;
	
	$msg = $_POST;
	$msg['body'] = $msg['msgbody'] ? $msg['msgbody'] : $msg['body'];
	$msg['body'] = preprocessMessage($msg['body'], $correspondent);
	$msg['msgbody'] = $msg['body'];
	$msgbody = $msg['body'];

	if($error = sendEmail($recipients, $subject, $msgbody, '', 'html', $mgrname, $bcc, $extraHeaders)) {
		if($error == 'BadCustomSMTPSettings')
			$error = "SMTP Server connection settings (host, port, username, or password) are incorrect.<p>"
								."Please review your "
								.fauxLink('<b>Outgoing Email</b> preferences', 'if(window.opener) window.opener.location.href="preference-list.php?show=4";window.close();', 1, 'Go there now')
								." in ADMIN > Preferences";
		include "frame-bannerless.php";
		echo "Mail error:<p>".print_r($error, 1);
		exit;
	}
	$msg['correspaddr'] = join(', ', $allSuppliedEmails);
	if($correspid && userRole() == 'p') {
		$msg['inbound'] = 0;
		$msg['originatorid'] = $_SESSION["providerid"];
		$msg['originatortable'] = 'tblprovider';
		$msg['datetime'] = date('Y-m-d H:i:s');
		$msg['transcribed'] = '';
		
		foreach($msg as $field =>$val) 
			if(!in_array($field, $msgFields)) unset($msg[$field]);  // $msgFields defined in coom-fns.php
		insertTable('tblmessage', $msg, 1);
		
		require_once "event-email-fns.php";
		$notifees = notifyStaff('k', "Sitter note to client ({$_SESSION["shortname"]} => $corresname): $subject", $msg['body']);
		if(!$notifees && !$providerids) {
			$note = "Message from: {$_SESSION['fullname']}\n\nTo: Manager (none on notification duty)\n\n{$msg['body']}";
			$msg2 = array('inbound'=>1,'datetime'=>date('Y-m-d H:i:s'),
										'transcribed'=>'','body'=>$note,'subject'=>$subject, 
										'correspaddr'=>"{$_SESSION['fullname']} <{$_SESSION['provider_email']}>",
										'correspid'=>$_SESSION['providerid'], 'correstable'=>'tblprovider',
										'originatorid'=>$msg['originatorid'], 'originatortable'=>'tblprovider');
			insertTable('tblmessage', $msg2, 1);
		}
		
	}
	else if($correspid) {
		saveOutboundMessage($msg);
	}
	
	echo "<script language='javascript'>
					alert('Message sent.');
					if(parent != null && navigator.userAgent.indexOf('Windows Phone') != -1) parent.$.fn.colorbox.close();
					else if(window.opener) {
						if(window.opener.update) window.opener.update('messages', null);
						else window.close();
					}
					else window.location.href='index.php';
				</script>";
}

//$client or $provider will be set, containing the recipient's id

if($correspondent) {
	$corrName = $correspondent['fname'].' '.$correspondent['lname'];
	$corrAddr = $correspondent['email'];
}
else {
	$corrName = "$fname $lname";
	$corrAddr = $email;
}


$error = null;


$pageTitle = "Email to $corrName";

//include "frame-client.html";
// ***************************************************************************

if($error) echo "<font color='red'>$error</font>";
if($message) {
	echo $message;
	exit;
}
$windowTitle = $pageTitle;
$extraBodyStyle = 'padding:10px;';
$pageIsPrivate = 1;
$showCountdown = 1;
$countdownStyle = 'position:absolute;top:10px;left:250px;';
require "mobile-frame-bannerless.php";

echo "<h2>$pageTitle</h2>";
?>
<form method='POST' name='commcomposerform'>
<?
echoButton('sendbutton', 'Send Message', 'checkAndSend()');
echo " ";
echoButton('', 'Quit', 'quit()');
?>
<table>
<?
if($reference) {
	$messageBody = "\n\n======\n$corrName wrote:\n\n{$reference['body']}";
	$messageSubject = (strpos($reference['subject'], "Re: ") === 0 ? '' : "Re: ").$reference['subject'];
}
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
inputRow('From:', 'mgrname', getUsersFromName());
labelRow("To $corresType:", '', $corrName);
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
hiddenElement('replyto', $replyto);
hiddenElement('corresname', $corrName);
hiddenElement('correspid', $correspId);
hiddenElement('correstable', $corresTable);
if(isset($properties)) hiddenElement('properties', $properties);
inputRow('Email:', 'correspaddr', $corrAddr, null, 'emailInput');
if($spouseLabel)
	echo "<tr><td colspan=2>".labeledCheckbox($spouseLabel, 'client2Checked', null, null, null, 'enable(this, "clientemail2")', 'boxfirst', 'noEcho').
					" ".labeledInput('email: ', 'clientemail2', $correspondent['email2'], null, 'emailinput', null, null, 'noEcho')."</td></tr>";



inputRow('Subject:', 'subject', (isset($messageSubject) ? $messageSubject : ''), null, 'emailInput');

$rType = $client ? 'client' : ($provider ? 'provider' : '');
if(in_array(userRole(), array('o', 'd'))) {
	$templates = array_merge(array(''=>''), fetchKeyValuePairs("SELECT label, templateid FROM tblemailtemplate WHERE active = '1' AND targettype = '$rType' AND label NOT LIKE '#STANDARD - %' ORDER BY label"));
	require_once "email-template-fns.php";
	$orgTemplates = getOrganizationEmailTemplateOptions($rType);
	if($orgTemplates) $templates['Shared Templates'] = $orgTemplates;
	if(count($templates) > 1) selectRow('Templates:', 'template', '', $templates, 'templateChosen()');
}
// $messageBody may be defined by including script


textRow('Message:', 'msgbody', $messageBody, $rows=20, $cols=44, null, 'fontSize1_2em');

if(in_array(userRole(), array('o', 'd')) && count($templates) > 1) {
?>
<tr><td colspan=2>Substitution tokens: #BIZNAME#, #RECIPIENT#, #FIRSTNAME#, #LASTNAME#, #LOGO#, #LOGINID#, #TEMPPASSWORD#, #CREDITCARD# (clients only), #PETS# (clients only)</td></tr>
<?  } ?>
</table>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('mgrname',"From (Manager's name)",'correspaddr',"<?= $corresType ?>'s Email Address",'subject','Subject line','msgbody','Message');
function checkAndSend() {
	if(MM_validateForm('mgrname', '', 'R',
											'correspaddr','', 'R',
											'correspaddr','', 'isEmail',
											'subject','', 'R',
											'msgbody','', 'R'
											)) {
			if(document.getElementById('sendbutton')) document.getElementById('sendbutton').disabled=true;
  		document.commcomposerform.submit();
	}
}

function quit() {
	if(!confirm('Quit without sending?')) return;
	if(window.opener) window.close();
	else if(parent != null && navigator.userAgent.indexOf('Windows Phone') != -1) parent.$.fn.colorbox.close();
	else window.location.href='index.php';
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function templateChosen() {
	var id = document.getElementById('template').value;
	if(id == 0) return;
	
//alert('email-template-fetch.php?id='+id);
	ajaxGetAndCallWith('email-template-fetch.php?id='+id+'&'+<?= "'$rType".'='.$correspId."'" ?>, updateMessage, null);
}

function updateMessage(unused, resultxml) {
	//alert(resultxml);
	var root = getDocumentFromXML(resultxml).documentElement;
	if(root.tagName == 'ERROR') {
		alert(root.nodeValue);
		return;
	}
	/*if(root.getAttribute('name') != elementName) {
		alert('Element '+elementName+' has a different name than '+root.tagName+" "+ root.getAttribute('name'));
		return;
	}*/
	var subject, message;
	var nodes = root.getElementsByTagName('subject') ;
	if(nodes.length == 1)
		document.getElementById('subject').value = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('body') ;
	
	if(nodes.length == 1) {
		var body = nodes[0].firstChild.nodeValue;
		body = body.replace(/<p>/gi, '\n\n');
		body = body.replace(/<br>/gi, '\n');
		document.getElementById('msgbody').value = body;
	}
}

function getDocumentFromXML(xml) {
	try //Internet Explorer
		{
		xmlDoc=new ActiveXObject("Microsoft.XMLDOM");
		xmlDoc.async="false";
		xmlDoc.loadXML(xml);
		return xmlDoc;
		}
	catch(e)
		{
		parser=new DOMParser();
		xmlDoc=parser.parseFromString(xml,"text/xml");
		return xmlDoc;
		}
}

function enable(checkbox, inputid) {
	document.getElementById(inputid).disabled = !checkbox.checked;
}

function timeoutAction() {
	if(parent != null && navigator.userAgent.indexOf('Windows Phone') != -1) parent.$.fn.colorbox.close();
	else window.close();
}

if(document.getElementById('clientemail2')) document.getElementById('clientemail2').disabled = true;


</script>
