<? // comm-home-safe-composer.php
/*
requestid - billing reminder request id
SCRIPTVARS
properties - optional pipe-separated string to be put into a hidden element named properties
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "client-fns.php";
require_once "response-token-fns.php";
require_once "comm-fns.php";
require_once "email-fns.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
require_once "email-template-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('ka');//locked('o-'); 

extract(extractVars('replyto,all,requestid,correspid,corresname,spousename,correstable,correspaddr,subject,msgbody,mgrname,starting,ending', $_REQUEST));

$source = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");
if(!$source) {
	echo "Unknown request: $requestid";
	exit;
}
$correspid = $source['clientptr'];
$correspondent = getClient($correspid);
if($correspondent['fname2']) $spouse[] = $correspondent['fname2'];
if($correspondent['lname2']) $spouse[] = $correspondent['lname2'];
if($spouse) $spouseName = join(' ', $spouse);
$spouseLabel = $spouse ? "to alternate $spouseName" : "to alternate name";

$clientName = "{$correspondent['fname']} {$correspondent['lname']}";
$requestDate = substr($source['received'], 0, strpos($source['received'], ' '));
$lastVisit = fetchFirstAssoc(
	"SELECT *, label 
		FROM tblappointment
		LEFT JOIN tblservicetype ON servicetypeid = servicecode
		WHERE clientptr = $correspid 
		AND date = '$requestDate' 
		ORDER BY starttime DESC
		LIMIT 1", 1);
$lastVisitTime = $lastVisit['timeofday'];
$lastVisitTime = substr($lastVisitTime, 0, strpos($lastVisitTime, '-'));
$lastVisitDate = $requestDate == date('Y-m-d') ? 'today' : month3Date(strtotime($requestDate));
$lastVisitPhrase = "$lastVisitDate at $lastVisitTime";

$label = mysql_real_escape_string("#STANDARD - Send Home Safe Request to Client");
$defaultTemplate = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
if(!$defaultTemplate) {
	// first try to generate it in email-template-fns
	ensureStandardTemplates($type='other');
	$defaultTemplate = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
}
if(!$defaultTemplate) {
	$defaultTemplate = 
		array('subject'=>"Please let us know you are home safe",
						'body'=>"Hi #FIRSTNAME#,\n\nPlease let us know if you have gotten home so that we will know your pets are safe.\n\nJust <a href='#RESPONSEURL#'>click here</a> to let us know.\n\nSincerely,\n\n#BIZNAME#");
	if(mattOnlyTEST()) echo "BONK!";
}

$template = $defaultTemplate;
$template['subject'] = str_replace('#CLIENTNAME#', $clientName, $template['subject']);
if($msgbody) $template['body'] = $msgbody;  // in case of error


if($_POST) {

	$recipients = array("\"$corresname\" <$correspaddr>");
	$allSuppliedEmails[] = $correspaddr;
	if($_POST['clientemail2']) {
		$allSuppliedEmails[] = $_POST['clientemail2'];
		$recipients[] = "\"$spousename\" <{$_POST['clientemail2']}>";
		//$correspondents[] = array('tblclient', $correspid);
	}



	//print_r($_POST);
	$msgBodyFromForm = $msgbody;
	// set up responseurl
	//    $row = array('datetime'=>$date, 'token'=>$token, 'respondentptr'=>$respondentptr, 'respondenttbl'=>$respondenttbl,
  // 							'bizptr'=>$bizptr, 'url'=>$redirecturl, 'loginuserid'=>$loginuserid, 'useonce'=>$useonce);

	$responseUrl = generateResponseURL($_SESSION["bizptr"], $correspondent, "client-home-safe.php?requestid={$_POST['requestid']}", $systemlogin=true, $expires=date('Y-m-d', strtotime("+ 5 days")), $appendToken=false);
	$lastVisitTile = fancyVisitTile($lastVisit['date'], $lastVisit['timeofday'], $lastVisit['label'], $id='LASTVISIT_TILE', $extraDivStyle='');
	$msgbody = htmlize(preprocessMessage($correspondent, $msgBodyFromForm, $responseUrl, $leaveVisitTileTokenAlone=false));
//====================================================================

	if($error = sendEmail($recipients, $subject, $msgbody, '', 'html', $mgrname, $bcc, $extraHeaders, $attachments)) {
		
		if($error == 'BadCustomSMTPSettings')
			$error = "SMTP Server connection settings (host, port, username, or password) are incorrect.<p>"
								."Please review your "
								.fauxLink('<b>Outgoing Email</b> preferences', 'if(window.opener) window.opener.location.href="preference-list.php?show=4";window.close();', 1, 'Go there now')
								." in ADMIN > Preferences";
		include "frame-bannerless.php";
		//echo "Mail error:<p>".print_r($error, 1);
		//exit;
	}
	if(!$error) {  // Save outbound message
		$msg = $_POST;
		$msg['body'] = $msgbody;
		$msg['correspaddr'] = join(', ', $allSuppliedEmails);
		if($correspid && userRole() == 'p') {
			$msg['inbound'] = 0;
			$msg['originatorid'] = $_SESSION["providerid"];
			$msg['originatortable'] = 'tblprovider';
			$msg['datetime'] = date('Y-m-d H:i:s');
			$msg['transcribed'] = '';
			$msg['tags'] = $tags;

			foreach($msg as $field =>$val) 
				if(!in_array($field, $msgFields)) unset($msg[$field]);  // $msgFields defined in comm-fns.php
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
			$msg['msgbody'] = $msgbody;
			saveOutboundMessage($msg);
		}
		else if(mattOnlyTEST() && !$correspid) { // should not happen
			$msg['correspid'] = -99;
			$msg['correstable'] = 'unknown';
			saveOutboundMessage($msg);
		}
		$note = $source['officenotes'] ? $source['officenotes'] ."\n" : '';
		$note .= "Sent home safe request: ".shortDateAndDay(time()).' '.date('g:i a');
		updateTable('tblclientrequest', array('officenotes'=>$note), "requestid = {$source['requestid']}", 1);

		echo "<script language='javascript'>if(window.opener && window.opener.update) window.opener.update('reload', null);window.close();</script>";
	}

//====================================================================

}

//$client or $provider will be set, containing the recipient's id
if(isset($email) && !$correspondent) {
	$correspondent = array('fname' =>$fname, 'lname' =>$lname, 'email'=>$email);
}

$corrName = $correspondent['fname'].' '.$correspondent['lname'];
$corrAddr = $correspondent['email'];




$pageTitle = "Send Home Safe Request to $clientName";

//include "frame-client.html";
// ***************************************************************************

if($error) echo "<font color='red'>$error</font>";
$error = null;
if($message) {
	echo $message;
	include "frame-end.html";
	exit;
}
$windowTitle = $pageTitle;
$extraBodyStyle = 'padding:10px;';
require "frame-bannerless.php";

echo "<h2>$pageTitle</h2>";
$lastVisitTile = fancyVisitTile($lastVisit['date'], $lastVisit['timeofday'], $lastVisit['label'], $id='LASTVISIT_TILE', $extraDivStyle='display:none;');
echo "\n\n$lastVisitTile\n\n";
?>
<form method='POST' name='commcomposerform'>
<table>
<?
if($reference) {
	$messageBody = "\n\n======\n$corrName wrote:\n\n{$reference['body']}";
	$messageSubject = (strpos($reference['subject'], "Re: ") === 0 ? '' : "Re: ").$reference['subject'];
}
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
inputRow('From:', 'mgrname', getUsersFromName());


inputRow('Email:', 'correspaddr', ($error && $failedEMAIL ? $failedEMAIL : $corrAddr), null, 'emailInput');
$client2Checked = $client2Checked || ($error && $failedALTEMAIL);

//$offerAltEmail = staffOnlyTEST() || dbTEST('queeniespets,dogonfitness,rufusanddelilah,urbantailz,doggiewalkerdotcom')/*|| dbTEST('queeniespets')*/;
if($spouseLabel)
	echo "<tr><td colspan=2>".labeledCheckbox($spouseLabel, 'client2Checked', $client2Checked, null, null, 'enable(this, "clientemail2")', 'boxfirst', 'noEcho').
					" ".labeledInput('email: ', 'clientemail2', 
					($error && $failedALTEMAIL ? $failedALTEMAIL : ($_REQUEST['clientemail2'] ? $_REQUEST['clientemail2'] : $correspondent['email2'])), null, 'emailinput', null, null, 'noEcho')."</td></tr>";


//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
hiddenElement('replyto', $replyto);
hiddenElement('corresname', $corrName);
hiddenElement('correspid', $correspid);
hiddenElement('correstable', 'tblclient');
hiddenElement('requestid', $requestid);
echo "<span style='display:none;' id='CLIENTNAME'>$clientName</span>\n";
echo "<span style='display:none;' id='LASTVISIT'>$lastVisitPhrase</span>\n";
if(isset($properties)) hiddenElement('properties', $properties);
inputRow('Subject:', 'subject', (isset($messageSubject) ? $messageSubject : $template['subject']), null, 'VeryLongInput');
$clientptr = fetchRow0Col0("SELECT clientptr FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");

$templates = array(substr($label, strlen(getSystemPrefix($defaultTemplate['label'])))=>$defaultTemplate['templateid']);
if($specialTemplates) foreach($specialTemplates as $spid=>$splabel) $templates[$spid] = $splabel;
$sql = "SELECT label, templateid 
				FROM tblemailtemplate WHERE active = '1' AND targettype = 'client' AND body LIKE '%#RESPONSEURL#%' ORDER BY label";

foreach(fetchKeyValuePairs($sql) as $label =>$id) {
	if(getSystemPrefix($label) != null) {
		$templates[substr($label, strlen(getSystemPrefix($label)))] = $id;
	}
	else $templates[$label] = $id;
}
if(count($templates) > 1) {
	selectRow('Templates:', 'template', $template, $templates, 'templateChosen()');
}

//if(mattOnlyTEST()) {echo "<tr><td>".print_r($templates, 1);exit;}



// $messageBody may be defined by including script

$messageBody = $messageBody ? $messageBody  : preprocessMessage($correspondent, $template['body'], null, $leaveVisitTileTokenAlone=true);
if(strpos($messageBody, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($clientptr, false, true); //$clientObject['clientid']
	$messageBody = str_replace('#PETS#', $petnames, $messageBody);
}
if(strpos($messageBody, '#MANAGER#') !== FALSE) {
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
}

//textRow($label, $name, $value=null, $rows=3, $cols=20, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2) {

textRow('Message:', 'msgbody', $value=$messageBody, $rows=18, $cols=80, null, 'fontSize1_2em');
//print_r($messageBody);
?>
</table>
<?
echoButton('', 'Send Message', 'checkAndSend()');
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('mgrname',"From (Manager's name)",'subject','Subject line','msgbody','Message');

function templateChosen() {
	if(!document.getElementById('template')) return;
	var id = document.getElementById('template').value;
	if(id == 0) return;
	
//alert('email-template-fetch.php?id='+id);
	ajaxGetAndCallWith('email-template-fetch.php?id='+id+'&client=<?= $correspid ?>', updateMessage, null);
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
	if(nodes.length == 1) {
		subject = nodes[0].firstChild.nodeValue;
		subject = subject.replace('#CLIENTNAME#', document.getElementById('CLIENTNAME').innerHTML);
		document.getElementById('subject').value = subject;
	}
	nodes = root.getElementsByTagName('body') ;
	
	if(nodes.length == 1) {
		var body = nodes[0].firstChild.nodeValue;
		var lastvisit =  document.getElementById('LASTVISIT').innerHTML;
		body = body.replace(/#LASTVISIT#/gi, lastvisit);
		if(document.getElementById('LASTVISIT_TILE')) 
			lastvisit =  document.getElementById('LASTVISIT_TILE').innerHTML;
		body = body.replace(/#LASTVISIT_TILE#/gi, lastvisit);
		body = body.replace(/<p>/gi, '\n\n');
		if(body.indexOf("\n\n") == 0) body = body.substring(2);
		body = body.replace(/<\/p>/gi, '');
		body = body.replace(/<br>/gi, '\n');
		document.getElementById('msgbody').value = body;
	}
}

function checkAndSend() {
	document.getElementById('subject').value = jstrim(document.getElementById('subject').value);
	if(MM_validateForm('mgrname', '', 'R',
											'correspaddr','', 'R',
											'correspaddr','', 'isEmail',
											'clientemail2','', 'isEmail',
											'subject','', 'R',
											'msgbody','', 'R'
											))
  		document.commcomposerform.submit();
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

</script>
<?
// ***************************************************************************
//include "frame-end.html";


//	$label = mysql_real_escape_string("#STANDARD - Client's Schedule");
//	$template = fetchRow0Col0("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
function preprocessMessage($person, $message, $responseURL=null, $leaveVisitTileTokenAlone=true) {
	global $lastVisitPhrase, $lastVisitTile;
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");

	if(strposAny($message, array('#LOGINID#', '#TEMPPASSWORD#')) && $person) {
		if(!$person['userid'] && ($person['clientid'] || $person['providerid'])) {
			$type = $person['clientid'] ? 'client' : 'provider';
			$targetid =  $person['clientid'] ? $person['clientid'] : $person['providerid'];
			$person['userid'] = fetchRow0Col0("SELECT userid FROM tbl{$type} WHERE {$type}id = $targetid LIMIT 1");
		}
		$creds = loginCreds($person);
	}
	if(strpos($message, '#CREDITCARD#') !== FALSE && $person)
		$cc = ccDescription($person);
	if(strpos($message, '#EPAYMENT#') !== FALSE && $person)
		$epayment = ePayDescription($person);
	if(strpos($message, '#PETS#') !== FALSE && $person) {
		require_once "pet-fns.php";
		$petnames = $person['clientid'] ? getClientPetNames($person['clientid'], false, true) : /* prospect */ 'your pets';
	}
	$substitions = array('#RECIPIENT#' => "{$person['fname']} {$client['lname']}",
																	'##FullName##' => "{$person['fname']} {$client['lname']}",
																	'##FirstName##' => $person['fname'],
																	'##LastName##' => $person['lname'],
																	'#RECIPIENT#' => $person['fname'].' '.$person['lname'],
																	'#FIRSTNAME#' => $person['fname'],
																	'#LASTNAME#' => $person['lname'],
																	'#BIZNAME#' => $_SESSION['preferences']['bizName'],
																	'#BIZID#' => $_SESSION["bizptr"],
																	'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
																	'#BIZLOGINPAGE#' => "http://leashtime.com/login-page.php?bizid={$_SESSION['bizptr']}",
																	'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
																	'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
																	'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
			'#LOGINID#' => $creds['loginid'],
			'#TEMPPASSWORD#' => $creds['temppassword'],
			'#PETS#' => $petnames,
																	'#LOGO#' => logoIMG()																	
																	);
	if($responseURL) $substitions['#RESPONSEURL#'] = $responseURL;
	$substitions['#LASTVISIT#'] = $lastVisitPhrase;
	if(!$leaveVisitTileTokenAlone) $substitions['#LASTVISIT_TILE#'] = $lastVisitTile;
	if(!$person) 
		foreach(explode(',','#RECIPIENT#,##FullName##,#FIRSTNAME#,#LASTNAME#,##FirstName##,##LastName##') as $k)
			unset($substitions[$k]);

	$message = mailMerge($message, $substitions);	
	
	
	if($schedule) $message = str_replace('#LOGO#', logoIMG(), $message);
	//$message = str_replace('#SENDER#', $signature, $message);
	//echo $body.'<p>';
	$hasHtml = strpos($message, '<') !== FALSE;
	if($hasHtml && $schedule) {
		$message = str_replace("\r", "", $message);
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	return $message;
}	

function htmlize($message) {
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	return $message;
}

function logoIMG($attributes='') {
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='https://leashtime.com/$headerBizLogo' $attributes>" :'';
}	

function strposAny($str, $list) {
	foreach((array)$list as $candidate)
		if(strpos($str, $candidate) !== FALSE)
			return true;
}

function ccDescription($target) {
	require_once 'cc-processing-fns.php';
	if(!$target['clientid']) return "ERROR: CREDITCARD info available only for clients.";
	$cc = getClearCC($target['clientid']);
	if(!$cc) return "No credit card on record.";
	return $cc['company'].' **** **** **** '.$cc['last4'].' Exp: '.expirationDate($cc['x_exp_date']);
}

function ePayDescription($target) {
	require_once 'cc-processing-fns.php';
	if(!$target['clientid']) return "ERROR: E-Payment info available only for clients.";
	$source = getClearPrimaryPaySource($target['clientid']);
	if(!$source) return "No e-payment source on record.";
	if($source['acctnum']) return "E-checking account: {$source['acctnum']}";
	else if($source['last4']) 	
		return $source['company'].' **** **** **** '.$source['last4'].' Exp: '.expirationDate($source['x_exp_date']);
}

function loginCreds($target) {
	if(!$target['userid']) return array('loginid'=>'NO LOGIN ID FOUND FOR USER', 'temppassword'=>'NO TEMP PASSWORD FOUND FOR USER');
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$creds = fetchFirstAssoc("SELECT loginid, temppassword, userid FROM tbluser WHERE userid = {$target['userid']} LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	return $creds;
}
