<? // comm-schedule-request-to-sitter-composer.php
/*
requestid - schedule request id
client or provider - id of correspondent
replyto - id of msge being replied to
all - reply to all
SCRIPTVARS
properties - optional pipe-separated string to be put into a hidden element named properties
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-sched-request-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "preference-fns.php";
require_once "email-template-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('ka');//locked('o-'); 

extract(extractVars('replyto,all,requestid,providerptr,correspid,corresname,spousename,correstable,correspaddr,subject,msgbody,mgrname,starting,ending,sendText', $_REQUEST));

$source = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");
if(!$source) {
	echo "Unknown request: $requestid";
	exit;
}
$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '{$source['clientptr']}' LIMIT 1");
require_once "pet-fns.php";
$petnames = getClientPetNames($source['clientptr'], false, true);

$label = mysql_real_escape_string("#STANDARD - Send Client Schedule Request to Sitter");
$defaultTemplate = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
if(!$defaultTemplate) { // TEMPORARY
		$label = mysql_real_escape_string("#STANDARD - Send Client Request to Sitter");
}
$defaultTemplate = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
if(!$defaultTemplate) {
	// first try to generate it in email-template-fns
	ensureStandardTemplates($type='other');
	$defaultTemplate = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
}
if(!$defaultTemplate) {
	$defaultTemplate = 
		array('subject'=>"Schedule Request from #CLIENTNAME#",
						'body'=>"Hi #FIRSTNAME#,\n\nPlease let us know if you are interested in the following schedule.\n\nSincerely,\n\n#BIZNAME#\n\n<hr>#REQUESTEDSCHEDULE#");
	echo "BONK!";
}

$template = $defaultTemplate;
	
$template['subject'] = str_replace('#CLIENTNAME#', $clientName, $template['subject']);
if($msgbody) $template['body'] = $msgbody;  // in case of error


if($_POST) {
	//print_r($_POST);
	$msgBodyFromForm = $msgbody;
	
	$person = getProvider($providerptr);
	$person['petnames'] = $petnames;
	if(!$person['email']) {
		$error = "{$person['fname']} {$person['lname']} has no email address.";
	}
	$sendLater = true;
	
	$subjectSubstitions = array('#RECIPIENT#' => "{$person['fname']} {$client['lname']}",
																	'#FIRSTNAME#' => $person['fname'],
																	'#LASTNAME#' => $person['lname'],
																	'#PETS#' => $person['petnames']);
	
	foreach($subjectSubstitions as $token => $sub)
		$subject = str_replace($token, $sub, "$subject");
	
	
	$msgbody = htmlize(preprocessMessage($person, $msgBodyFromForm));
	if(!$error && $sendLater) {
//if(mattOnlyTEST()) {echo "[error: $error] Send to ".print_r($recipients,1)." ".($sendLater ? 'later' : 'now').': '.print_r($msgbody,1);exit;}	
		$result = enqueueEmailNotification($person, $subject, $msgbody, null, $mgrname, 'html');
		if($sendText) {
			notifyByLeashTimeSMS($person, "In a few minutes you will receive a schedule request email concerning $clientName.  Please review it.");
		}
//if(mattOnlyTEST()) {echo print_r($result, 1); exit;}
	}
	
	else if(!$error && ($error = sendEmail($recipients, $subject, $msgbody, '', 'html', $mgrname, $bcc))) {
		if($error == 'BadCustomSMTPSettings')
			$error = "SMTP Server connection settings (host, port, username, or password) are incorrect.<p>"
								."Please review your "
								.fauxLink('<b>Outgoing Email</b> preferences', 'if(window.opener) window.opener.location.href="preference-list.php?show=4";window.close();', 1, 'Go there now')
								." in ADMIN > Preferences";
		include "frame-bannerless.php";
		echo "Mail error:<p>".print_r($error, 1);
		exit;
	}
	
	if($error) { print_r($error);/* error was not catastrophic */ }
	else {
		$msg = $_POST;
		$officenotes = $source['officenotes'] ? array($source['officenotes']) : array();
		$officenotes[] = shortDate(time())." Sent to {$person['fname']} {$person['lname']}";
		updateTable('tblclientrequest', array('officenotes'=>join("\n", $officenotes)), "requestid = $requestid", 1);
		if(!$sendLater) {
			foreach($recipients as $recipient) {
				$msg['correstable'] = 'tblprovider';
				$msg['correspid'] = $recipient['providerid'];
				saveOutboundMessage($msg);
			}
		}
		echo "<script language='javascript'>if(window.opener.update) window.opener.update('reload', null);window.close();</script>";
	}
}

//$client or $provider will be set, containing the recipient's id
$correspondent = array();
if(isset($providerptr)) {
	require_once "provider-fns.php";
	$correspondent = getProvider($providerptr);
	$corresTable = 'tblprovider';
	$corresType = 'Sitter';
	$correspId = $providerptr;
}

if(isset($email) && !$correspondent) {
	$correspondent = array('fname' =>$fname, 'lname' =>$lname, 'email'=>$email);
}

$corrName = $correspondent['fname'].' '.$correspondent['lname'];
$corrAddr = $correspondent['email'];




$pageTitle = "Email Schedule Request to a Sitter";

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

/*
echo "<tr><td colspan=2>".labeledCheckbox("to $corrName", 'clientChecked', 'client', null, null, 'enable(this, "clientemail")', 'boxfirst', 'noEcho').
				" ".labeledInput('email: ', 'clientemail', $corrAddr, null, 'emailinput', null, null, 'noEcho')."</td></tr>";
$emailFields[] = 'clientemail';

echo "<tr><td colspan=2>".labeledCheckbox($spouseLabel, 'client2Checked', null, null, null, 'enable(this, "clientemail2")', 'boxfirst', 'noEcho').
				" ".labeledInput('email: ', 'clientemail2', $correspondent['email2'], null, 'emailinput', null, null, 'noEcho')."</td></tr>";
$emailFields[] = 'clientemail2';



foreach($providers as $prov) {
	$emailField = "provemail_{$prov['providerptr']}";
	echo "<tr><td colspan=2>".labeledCheckbox("to sitter {$prov['name']}", "prov_{$prov['providerptr']}", true, null, null, 
																							"enable(this, \"$emailField\")", 'boxfirst', 'noEcho').
					" ".labeledInput('email: ', $emailField, $prov['email'], null, 'emailinput', null, null, 'noEcho')."</td></tr>";
	$emailFields[] = $emailField;
}
*/
//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
hiddenElement('replyto', $replyto);
hiddenElement('corresname', $corrName);
hiddenElement('corresname', $corrName);
hiddenElement('correspid', $correspId);
hiddenElement('correstable', 'tblprovider');
echo "<span style='display:none;' id='REQUESTEDSCHEDULE'>".scheduleDescription($requestid)."</span>\n";
echo "<span style='display:none;' id='CLIENTNAME'>$clientName</span>\n";
if(isset($properties)) hiddenElement('properties', $properties);
inputRow('Subject:', 'subject', (isset($messageSubject) ? $messageSubject : $template['subject']), null, 'VeryLongInput');
$clientptr = fetchRow0Col0("SELECT clientptr FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");

$selections = availableProviderSelectElementOptions($clientptr, null,  '--Select a Sitter--');


unset($selections['Inactive Sitters']);
selectRow('To sitter:', 'providerptr', $value=null, $selections, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
require_once "sms-fns.php";
if(smsEnabled('fromLeashTimeAccount')) 
	echo "<tr><td colspan=2>".
	labeledCheckbox("Send a \"heads up\" text message as well", 'sendText', $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=true, $title='Send the sitter a text message asking her to check her email for this message.')
	."</td></tr>\n";

$templates = array(substr($label, strlen(getSystemPrefix($defaultTemplate['label'])))=>$defaultTemplate['templateid']);
if($specialTemplates) foreach($specialTemplates as $spid=>$splabel) $templates[$spid] = $splabel;
$sql = "SELECT label, templateid 
				FROM tblemailtemplate WHERE active = '1' AND targettype = 'provider' AND body LIKE '%#REQUESTEDSCHEDULE#%' ORDER BY label";


foreach(fetchKeyValuePairs($sql) as $label =>$id) {
	if(getSystemPrefix($label)) $templates[substr($label, strlen(getSystemPrefix($label)))] = $id;
	else $templates[$label] = $id;
}
if(count($templates) > 1) selectRow('Templates:', 'template', $template, $templates, 'templateChosen()');




// $messageBody may be defined by including script

if(strpos($template['body'], '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($clientptr, false, true);
	$nullperson['petnames'] = $petnames;
}
$messageBody = $messageBody ? $messageBody  : preprocessMessage($nullperson, $template['body']);
if(strpos($messageBody, '#MANAGER#') !== FALSE) {
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
}


$clientAddress = fetchFirstAssoc(
	"SELECT street1, street2, city, state, zip 
		FROM tblclient 
		WHERE clientid = $clientptr LIMIT 1", 1);
		
$oneLineClientAddress = str_replace("'", "&apos;", oneLineAddress($clientAddress));
$threeLineClientAddress = str_replace("'", "&apos;", htmlFormattedAddress($clientAddress));

$substitutes = array(
										'##BizName##' => $_SESSION['bizname'],
										'#REQUESTEDSCHEDULE#' => scheduleDescription($requestid),
										'#BIZNAME#' => $_SESSION['preferences']['bizName'],
										'#BIZID#' => $_SESSION["bizptr"],
										'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
										'#BIZLOGINPAGE#' => "http://leashtime.com/login-page.php?bizid={$_SESSION['bizptr']}",
										'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
										'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
										'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
										'#CLIENTNAME#' => $clientName,
										'#CLIENTADDRESSONELINE#' => $oneLineClientAddress,
										'#CLIENTADDRESSTHREELINE#' => $threeLineClientAddress
										);
/*if($corrName) $substitutes['#RECIPIENT#'] = $corrName;
if($correspondent) {
	$substitutes['#FIRSTNAME#'] = $correspondent['fname'];
	$substitutes['#LASTNAME#'] =  $correspondent['lname'];
}
*/
$messageBody = mailMerge($messageBody, $substitutes);
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
setPrettynames('mgrname',"From (Manager's name)",'providerptr',"Sitter",'subject','Subject line','msgbody','Message');

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
	if(nodes.length == 1) {
		subject = nodes[0].firstChild.nodeValue;
		subject = subject.replace('#CLIENTNAME#', document.getElementById('CLIENTNAME').innerHTML);
		document.getElementById('subject').value = subject;
	}
	nodes = root.getElementsByTagName('body') ;
	
	if(nodes.length == 1) {
		var body = nodes[0].firstChild.nodeValue;
		body = body.replace(/<p>/gi, '\n\n');
		if(body.indexOf("\n\n") == 0) body = body.substring(2);
		body = body.replace(/<\/p>/gi, '');
		body = body.replace(/<br>/gi, '\n');
		body = body.replace('#REQUESTEDSCHEDULE#', document.getElementById('REQUESTEDSCHEDULE').innerHTML);

		body = body.replace('#CLIENTADDRESSONELINE#', '<?= $oneLineClientAddress ?>');
		body = body.replace('#CLIENTADDRESSTHREELINE#', '<?= $threeLineClientAddress ?>');
		document.getElementById('msgbody').value = body;
	}
}

function checkAndSend() {
<?	
//foreach($emailFields as $fieldname) $tests[] = "'$fieldname','', 'isEmail'";
//$tests = join(',', $tests);
//$emailFields = "['".join("', '", $emailFields)."']";
//echo "var emailfields = $emailFields;\n";
?>	
	if(MM_validateForm('mgrname', '', 'R',
											'providerptr','', 'R',
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

function scheduleDescription($requestid) {
	$source = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");
	$schedule = scheduleFromNote($source['note']);
	$requestnote = explode("\n", $source['note']);  // $schedule['note'];, if we ever add it
	$requestnote = $requestnote[2];  // $schedule['note'];, if we ever add it
	//if($requestnote) $requestnote = urldecode(str_replace("\n", "<br>", str_replace("\n\n", "<p>", trim($requestnote))));
	if($requestnote) $requestnote = urldecode(trim($requestnote));
	else $requestnote = "";

	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '{$source['clientptr']}' LIMIT 1");
	
	// Note format
	// line 0: start|end|totalCharge
	// line 2: service|service|..<>service|service|..<>
	// service: servicecode#timeofday#pets
	// returns array(start|end|totalCharge, services=>(service1, service2, ...), (service1, service2, ...),...)
	// $schedule['services'] format: day0=>array(array(servicecode,timeofday,pets,charge),...), day1=>(...)
	$dayAsTime = strtotime($schedule['start']);
	foreach((array)($schedule['services']) as $day) {
		$numServices += count($day);
		if(count($day)) {
			$activeDays += 1;
			$dayVisits[] = longDayAndDate($dayAsTime);
			foreach($day as $service) 
				$dayVisits[] = "{$service['timeofday']} {$_SESSION['servicenames'][$service['servicecode']]} {$service['pets']}";
			$dayVisits[] = "";	
		}
		$dayAsTime = strtotime("+1 day", $dayAsTime);
	}
	$message = "Client $clientName has requested $numServices visits on $activeDays days between "
							.longDayAndDate(strtotime($schedule['start'])).' and '
							.longDayAndDate(strtotime($schedule['end'])).".\n\n";
	if($requestnote) $message .= "==========\nNote: $requestnote\n==========\n\n";
	$message .= join("\n", $dayVisits);
//if(mattOnlyTEST()) $message = "message<hr>".print_r($schedule, 1);	
	return $message;
}

//	$label = mysql_real_escape_string("#STANDARD - Client's Schedule");
//	$template = fetchRow0Col0("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
function preprocessMessage($person, $message) {
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");

	$substitions = array('#RECIPIENT#' => "{$person['fname']} {$client['lname']}",
																	'##FullName##' => "{$person['fname']} {$client['lname']}",
																	'##FirstName##' => $person['fname'],
																	'##LastName##' => $person['lname'],
																	'#FIRSTNAME#' => $person['fname'],
																	'#LASTNAME#' => $person['lname'],
																	'#PETS#' => $person['petnames'],
																	'#BIZNAME#' => $_SESSION['preferences']['bizName'],
																	'#BIZID#' => $_SESSION["bizptr"],
																	'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
																	'#BIZLOGINPAGE#' => "http://leashtime.com/login-page.php?bizid={$_SESSION['bizptr']}",
																	'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
																	'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
																	'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
																	'#LOGO#' => logoIMG()
																	);
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

