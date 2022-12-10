<? // comm-notice-composer.php
/*
THIS SCRIPT IS FOR USE EXCLUSIVELY IN THE LEASHTIME CUSTOMERS DATABASE

IT GENERATES A SYSTEM NOTICE AND IT SENDS AN EMAIL MESSAGE.

client or provider - id of correspondent
replyto - id of msge being replied to
all - reply to all
templatetype =client|provider|other|<empty>
forwardid =msgid of message to forward

SCRIPTVARS
properties - optional pipe-separated string to be put into a hidden element named properties

INCLUDED SCRIPTVARS
failedFROM
failedSUBJECT
failedEMAIL
failedALTEMAIL
sittersWithProfiles (when included by sitter-profile-email.php) -- HTML block
extraConstraints -- a string (starting with a comma) of additional pre-post constraints
extraPrettyNames -- a string (starting with a comma) of additional prettyName pairs
specialTemplates -- array of templates to be included in templates list
messageBodyTokens -- string to replace token list shown below message body box
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "comm-composer-fns.php";
require_once "email-template-fns.php";

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
	if(!$proceed) locked('o-');
}
else $locked = locked('ka');//locked('o-'); // ???

if(!dbTEST('leashtimecustomers')) {
	echo "<h1>WRONG DATABASE!</h1>  <h2>YOU MUST BE LOGGED IN TO LEASHTIME CUSTOMERS.</h2>
	This is <b>{$_SESSION['bizname']} ($db)</b>";
	exit;
}

extract(extractVars('replyto,all,client,provider,prospect,correspid,corresname,spousename,clientemail2,correstable,'
											.'correspaddr,subject,msgbody,mgrname,lname,fname,email,tags,template,forwardid', $_REQUEST));

if(!$client) {
	echo "<h1>No client ID supplied.</h2>";
	exit;
}
else {
	$checkClient = getClient($client);
	if(!$checkClient) $abort[] = "Client [$client] not found.";
	else {
		$clientName = "{$checkClient['fname']} {$checkClient['lname']}";
		if(!$checkClient['active']) $abort[] = "Client [$clientName] is not active.";
		if(!($clientBizid = $checkClient['garagegatecode'])) $abort[] = "Client [$clientName] is not associated with an installed business.";
		require_once "client-flag-fns.php";
		$flags = getClientFlags($client);
		foreach($flags as $flag) $flagsById[$flag['flagid']] = $flag;
		if($flagsById[8]) $warnings[] = "<img src='{$flagsById[8]['src']}'> Client [$clientName] is flagged as a Dead Lead.";
		if($flagsById[21]) $warnings[] = "<img src='{$flagsById[21]['src']}'> Client [$clientName] is flagged as a disconinued customer.";
		
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$clientBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $clientBizid LIMIT 1");
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
		if(!$clientBiz) $abort[] = "There is no business with ID $clientBizid.";
		if(!$clientBiz['activebiz']) $abort[] = "Business ID $clientBizid ({$clientBiz['activebiz']}) is inactive.";
		if($clientBiz['lockout']) $abort[] = "Business ID $clientBizid ({$clientBiz['bizname']}) is locked out.";
	}
}
if($abort) {
	foreach($abort as $i => $line) $abort[$i] = "<li>$line";
	echo "<ul class='warning'>".join("\n", $abort)."</ul>";
	exit;
}

if($warnings) {
	foreach($warnings as $i => $line) $warnings[$i] = "<li>$line";
	echo "<span class='warning fontSize1_1em'>WARNING</span>:<ul class='warning'>".join("\n", $warnings)."</ul>";
}

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
	$spouseLabel = $spouse ? "to alternate $spouseName" : "to alternate name";
}
else if(isset($provider)) {
	require_once "provider-fns.php";
	$correspondent = getProvider($provider);
	$corresTable = 'tblprovider';
	$corresType = 'Sitter';
	$correspId = $provider;
}
else if(isset($prospect)) {
	require_once "request-fns.php";
	$correspondent = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$prospect} LIMIT 1", 1);
	$corresTable = 'tblclientrequest';
	$corresType = 'Prospect';
	$correspId = $prospect;
}

if($_POST) {
	// confirm the original client matches the client to be emailed now
	// this aims to derail mailing when the user has logged into a different database
	if(!$correspondent || $corresname != "{$correspondent['fname']} {$correspondent['lname']}") {
		$POSTerror = "$corresname is not registered in this database.";
		require "frame-bannerless.php";
		echo "<h2>WARNING</h2>";
		echo "<font color='red'>$POSTerror</font><p>";
		echo "You are currently logged in to <b>{$_SESSION['preferences']['bizName']}</b>.<p>";
		exit;
	}
}


// if this script has been included, $error may already have occurred
if($_POST && !$error) {
	$recipients = array("\"$corresname\" <$correspaddr>");
	$allSuppliedEmails[] = $correspaddr;
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
		$prefs = $_SESSION['preferences'];
		$replyTo = $_SESSION["provider_email"];
		if(!$replyTo || $prefs['replyToOfficeInSitterToClientEmail'])
			$replyTo = $prefs['defaultReplyTo'] ? $prefs['defaultReplyTo'] : $prefs['bizEmail'];
		$extraHeaders = array('Reply-to'=> $replyTo);
	}
	else $extraHeaders = null;
	
	$msg = $_POST;
	$msg['body'] = $msg['msgbody'] ? $msg['msgbody'] : $msg['body'];
	$msg['body'] = preprocessMessage($msg['body'], $correspondent);
	$msg['msgbody'] = $msg['body'];
	$msgbody = $msg['body'];
	if($forwardid) {
		$msgbody .= forwardedContent($forwardid);
		$msg['msgbody'] = $msgbody;
	}
	$attachments = null;
	if(strpos($msgbody, '#EMBEDDEDLOGOSRC#') !== FALSE) {
		$imagepath = 	getHeaderBizLogo($_SESSION["bizfiledirectory"]);
		if($imagepath) {
			$imagepath = "https://{$_SERVER["HTTP_HOST"]}/$imagepath";
			$attachments[] = array('imagetoken'=>'#EMBEDDEDLOGOSRC#', 'imagepath'=>$imagepath);
//echo 		print_r($attachments,1);exit;
		}
	}
	
	// SWITCH CONTEXT TO CLIENT'S DATABASE
	reconnectPetBizDB($clientBiz['db'], $clientBiz['dbhost'], $clientBiz['dbuser'], $clientBiz['dbpass'], 1);
	require_once "request-fns.php";
	$reqId = saveNewSystemNotificationRequest($msg['subject'], $msg['msgbody']);
	
	// SWITCH CONTEXT TO LEASHTIME CUSTOMERS DATABASE
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);

	
	
	if($error = sendEmail($recipients, $subject, $msgbody, '', 'html', $mgrname, $bcc, $extraHeaders, $attachments)) {
		
		if($error == 'BadCustomSMTPSettings')
			$error = "SMTP Server connection settings (host, port, username, or password) are incorrect.<p>"
								."Please review your "
								.fauxLink('<b>Outgoing Email</b> preferences', 'if(window.opener) window.opener.location.href="preference-list.php?show=4";window.close();', 1, 'Go there now')
								." in ADMIN > Preferences";
		$error = "Notification #$reqId created, but... $error";
		$failedSubject = $subject;
		include "frame-bannerless.php";
		//echo "Mail error:<p>".print_r($error, 1);
		//exit;
	}
	if(!$error) {  // Save outbound message
		$msg['correspaddr'] = join(', ', $allSuppliedEmails);
		if($correspid && userRole() == 'p') {
			$msg['inbound'] = 0;
			$msg['originatorid'] = $_SESSION["providerid"];
			$msg['originatortable'] = 'tblprovider';
			$msg['datetime'] = date('Y-m-d H:i:s');
			$msg['transcribed'] = '';
			$msg['tags'] = $tags;

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
		else if(mattOnlyTEST() && !$correspid) {
			$msg['correspid'] = -99;
			$msg['correstable'] = 'unknown';
			saveOutboundMessage($msg);
		}

		echo "<script language='javascript'>if(window.opener && window.opener.update) window.opener.update('messages', null);window.close();</script>";
	}
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
if($error && $msg) {
	$replyto  = $_POST['replyto'];
	$corrName  = $_POST['corresname'];
	$corrAddr = $_POST['correspaddr'];
	$corresTable  = $_POST['correstable'];
	$client2Checked  = $_POST['client2Checked'];
	$correspondent['email2'] = $_POST['clientemail2'];
	if(isset($_POST['properties'])) $properties  = $_POST['properties'];
	$messageSubject = $subject;
	$messageBody = $_POST['msgbody'] ? $_POST['msgbody'] : $_POST['body'];
}
else if($reference) {
	$messageBody = "\n\n======\n$corrName wrote:\n\n{$reference['body']}";
	$messageSubject = (strpos($reference['subject'], "Re: ") === 0 ? '' : "Re: ").$reference['subject'];
}



$titlePrefix = $forwardid ? "Forward " : "";
$pageTitle = $pageTitle ? str_replace('#RECIPIENT#', $corrName, $pageTitle) : "{$titlePrefix}Email/Notification to $corrName";

//include "frame-client.html";
// ***************************************************************************

if($error) echo "<font color='red'>$error</font>";

if($message) {
	echo $message;
	include "frame-end.html";
	exit;
}
$windowTitle = $pageTitle;
$extraBodyStyle = 'padding:10px;';
require "frame-bannerless.php";

echo "<table><tr>";
echo "<td class='h2'>$pageTitle</td>";
echo "<td style='text-align:right;'>";
echo "<img src='art/spacer.gif' width=50 height=1>";
echoButton('', 'Send Message', 'checkAndSend()');
echo "<img src='art/spacer.gif' width=50 height=1>";
echoButton('', 'Cancel', 'window.close()', 'HotButton', 'HotButtonDown');
echo "</td></tr></table>";

?>
<form method='POST' name='commcomposerform'>
<table>
<?
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
/*
failedFROM
failedEMAIL
failedALTEMAIL

*/
inputRow('From:', 'mgrname', ($error && $failedFROM ? $failedFROM : ($_REQUEST['mgrname'] ?  : getUsersFromName())));
labelRow("To $corresType:", '', $corrName);
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
hiddenElement('replyto', $replyto);
hiddenElement('corresname', $corrName);
hiddenElement('correspid', $correspId);
hiddenElement('correstable', $corresTable);
hiddenElement('tags', $tags);
if(isset($properties)) hiddenElement('properties', $properties);
inputRow('Email:', 'correspaddr', ($error && $failedEMAIL ? $failedEMAIL : $corrAddr), null, 'emailInput');
$client2Checked = $client2Checked || ($error && $failedALTEMAIL);

//$offerAltEmail = staffOnlyTEST() || dbTEST('queeniespets,dogonfitness,rufusanddelilah,urbantailz,doggiewalkerdotcom')/*|| dbTEST('queeniespets')*/;
if($spouseLabel)
	echo "<tr><td colspan=2>".labeledCheckbox($spouseLabel, 'client2Checked', $client2Checked, null, null, 'enable(this, "clientemail2")', 'boxfirst', 'noEcho').
					" ".labeledInput('email: ', 'clientemail2', 
					($error && $failedALTEMAIL ? $failedALTEMAIL : ($_REQUEST['clientemail2'] ? $_REQUEST['clientemail2'] : $correspondent['email2'])), null, 'emailinput', null, null, 'noEcho')."</td></tr>";


if($forwardid) $forwardedSubject = forwardedSubject($forwardid);
inputRow('Subject:', 'subject', 
	($error ? $failedSUBJECT : (
	isset($messageSubject) ? $messageSubject : (
	isset($subject) ? $subject : (
	$forwardedSubject ? $forwardedSubject : '')))), 
	null, 'emailInput');
	
if($sittersWithProfiles) echo $sittersWithProfiles;

$clientProviderOrProspect = $client ? 'client' : ($provider ? 'provider' : ($prospect ? 'prospect' : ''));
$rType = $templatetype ? $templatetype : ($client ? 'client' : ($provider ? 'provider' : ((staffOnlyTEST() || dbTEST('tlcpetsitter')) && $prospect ? 'client' : '')));
if(in_array(userRole(), array('o', 'd'))) {
	$templates = array(''=>'');
	if($specialTemplates) foreach($specialTemplates as $spid=>$splabel) $templates[$spid] = $splabel;
	$sql = "SELECT label, templateid FROM tblemailtemplate WHERE active = '1' AND targettype = '$rType' AND label NOT LIKE '#STANDARD - %' ORDER BY label";

	foreach(fetchKeyValuePairs($sql) as $label =>$id) {
		if(getSystemPrefix($label)) $templates[substr($label, strlen(getSystemPrefix($label)))] = $id;
		else $templates[$label] = $id;
	}
	require_once "email-template-fns.php";
	$orgTemplates = getOrganizationEmailTemplateOptions($rType);
	if($orgTemplates) $templates['Shared Templates'] = $orgTemplates;
	if(count($templates) > 1) selectRow('Templates:', 'template', $template, $templates, 'templateChosen()');
}

// $messageBody may be defined by including script

if(staffOnlyTEST() && !$messageBody) $messageBody = getProcessedSig('nobrs');
if($forwardid && !$messageBody) $messageBody = '.';
textRow('Message:', 'msgbody', $messageBody, $rows=20, $cols=80, null, 'fontSize1_2em');

$standardTokens = "#BIZNAME#, #BIZEMAIL#, #BIZPHONE#, #BIZHOMEPAGE#, #BIZLOGINPAGE#, #MANAGER#, #RECIPIENT#, #FIRSTNAME#, #LASTNAME#, #LOGO#, #LOGINID#, #TEMPPASSWORD#, #CREDITCARD# (clients only), #PETS# (clients only)";
?>
<tr><td colspan=2>Substitution tokens: <?= $messageBodyTokens ? $messageBodyTokens : $standardTokens ?></td></tr>
</table>
</form>

<?
if($forwardid) {
	echo forwardedContent($forwardid);
}
?>

<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('mgrname',"From (Manager's name)",'correspaddr',"<?= $corresType ?>'s Email Address",'subject',
								'Subject line','msgbody','Message' <?= $extraPrettyNames ?>);
function checkAndSend() {
	if(typeof trim == 'function' && document.getElementById('subject')) document.getElementById('subject').value = trim(document.getElementById('subject').value);
	if(MM_validateForm('mgrname', '', 'R',
											'correspaddr','', 'R',
											'correspaddr','', 'isEmail',
											'subject','', 'R',
											'msgbody','', 'R'
											<?= $extraConstraints ?>
											))
  		document.commcomposerform.submit();
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function templateChosen() {
	if(!document.getElementById('template')) return;
	var id = document.getElementById('template').value;
	if(id == 0) return;
	
//alert('email-template-fetch.php?id='+id);
	ajaxGetAndCallWith('email-template-fetch.php?id='+id+'&'+<?= "'$clientProviderOrProspect".'='.$correspId."'" ?>, updateMessage, null);
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
		if(body.indexOf("\n\n") == 0) body = body.substring(2);
		body = body.replace(/<\/p>/gi, '');
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

if(document.getElementById('clientemail2'))
	document.getElementById('clientemail2').disabled = 
		!document.getElementById('client2Checked').checked;

templateChosen();
</script>
