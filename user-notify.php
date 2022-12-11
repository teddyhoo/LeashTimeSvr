<? //user-notify.php
/*
This page opens in a pop-up after a change has been made.  It offers a manager the chance
to send a notification to a client or provider.  Since this can only happen if the intended
recipient has an email address and should only happen if he does not object to such email,
this page checks for his address and (possibly implied) acquiescence before proceeding.  
However, it makes sense to check for this before opening this page, using 
clientAcceptsEmail($recipient, $preferenceFields) or 
providerAcceptsEmail($recipient, $preferenceFields) <not yet written: 2009-10-16>

Inputs: (R = required, * = optional, @ = one required among all @'s
[@] clientid
[@] providerid
[*] preferenceFields - array(key=>permissableValue,  key=>permissableValue,  )
[*] offerregistration 
[*] confirmationoptional
[*] action - null=startup, register=try to register a user, proceed=perform notification
[*] confirmationRequested - to be sent to recipient
[*] confirmationlink - to be sent to recipient
[*] requestConfirmation - if true, request confirmation checkbox is checked
[*] confirmationRequestText - natural language request for a replay containing "##ConfirmationURL##"
[*] subject - message subject
[*] message - text possibly with tokens:
				##FullName## => $corrName,
				##FirstName## => $correspondent['fname'],
				##LastName## => $correspondent['lname'],
				##Sitter## => $corrName,
				##BizName## => $_SESSION['bizname']
				... and ##ConfirmationRequestText## with ##ConfirmationURL##
[*] htmlMessage - whether message is HTML
[*] messageAppendix - to be appended to message just before mailing
[*] messageAppendixToken - when supplied, messageAppendix is subbed in for this token, rather than being appended
[*] dueIntervalMinutes - minutes from request time that confirmation is due.  default: 24 * 60
[*] expirationMinutes - minutes from request time that confirmation token expires  default: 4 * 24 * 60

Notify a provider or client.

1. If the client has no email address, done.
2. If client email prefs disallow notification emails, done.
3. Ask Manager if he wants to send a notification to the client.
(Steps 1 - 3 should be done before now, but Steps 1-2 are repeated here)
4. If No, done. Else...
5. If user has no System Login, offer Manager opportunity to set one up in a pop up System Login editor which [REGISTRATION]
includes a "Continue without Creating a Login" button.
6. Open a Message Composer which includes a Check Box: "Request Confirmation"
7. Include a context-specific message (non-editable, perhaps containing a formatted package summary or some kind of change summary)
8. Include a checkbox (pre-checked) to include the context-specific portion in the message to be sent.
9. Include a note that the Manager may type in.
10. When sent, message includes a confirmation link if "Request Confirmation" was checked.
11. (Message may also contain other links to the client's content if the client has a System Login)

$booleanParamKeys = 'optOutMassEmail|No Mass Emails||autoEmailCreditReceipts|Automatic Credit Card Transaction Emails||'.
										'autoEmailScheduleChanges|Schedule Change Emails';


*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "system-login-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";

// Verify login information here
locked('o-');

extract(extractVars('clientid,providerid,offerregistration,confirmationoptional,confirmationlink,confirmationRequested'
										. ',confirmationRequestText,action,subject,message,dueIntervalMinutes,expirationMinutes,htmlMessage'
										.',requestConfirmation,messageAppendix,messageAppendixToken,correspaddr,clientemail2,corresname'
										.',loginid,password,password2,temppassword,active', $_REQUEST));
$dueIntervalMinutes = $dueIntervalMinutes ? $dueIntervalMinutes : 24 * 60;
$expirationMinutes = $expirationMinutes ? $expirationMinutes : 4 * 24 * 60;
//echo "confirmationRequestText 0: $confirmationRequestText<p>";
$confirmationRequestText = stripslashes(urldecode($confirmationRequestText));
$messageAppendix = stripslashes(urldecode($messageAppendix));
$message = stripslashes($message);
//echo "confirmationRequestText 0.5: $confirmationRequestText<p>";
$notificationLooks = 'font-size:1.2em';

if($clientid) {
	$role = 'client';
	$roleid = $clientid;
	$table = 'tblclient';
	$idField = 'clientid';
}
else {
	$role = 'provider';
	$roleid = $providerid;
	$table = 'tblprovider';
	$idField = 'providerid';
}
$recipient = fetchFirstAssoc("SELECT * FROM $table WHERE $idField = '$roleid' LIMIT 1");

if(/*mattOnlyTEST() &&*/ $_POST) {
	// confirm the original client matches the client to be emailed now
	// this aims to derail mailing when the user has logged into a different database
	if(!$recipient || $corresname != "{$recipient['fname']} {$recipient['lname']}") {
		logError("user-notify FAIL: role[$role] roleid[$roleid] corresname[$corresname] recipient[{$recipient['fname']} {$recipient['lname']}]");
		$POSTerror = "$corresname is not registered in this database.";
		require "frame-bannerless.php";
		echo "<h2>WARNING</h2>";
		echo "<font color='red'>$POSTerror</font><p>";
		echo "You are currently logged in to <b>{$_SESSION['preferences']['bizName']}</b>.<p>";
		exit;
	}
}




if($clientid && (trim($recipient['fname2']) || trim($recipient['lname2']))) {
	if($recipient['fname2']) $spouse[] = $recipient['fname2'];
	if($recipient['lname2']) $spouse[] = $recipient['lname2'];
	if($spouse) $spouseName = join(' ', (array)$spouse);
	$spouseLabel = $spouse ? "to $spouseName" : "to alternate name";
}


$fname = $recipient['fname'];
$lname = $recipient['lname'];


//echo "Recipient: ".print_r($recipient, 1)." prefs: ".print_r($preferenceFields, 1);exit;

if(!$ignorePreferences && $clientid && !clientAcceptsEmail($recipient, $preferenceFields, $allowNullEmail=true)) {
	$rEmail = $allowNullEmail || $recipient['email'];
	$reason = $rEmail ? 'by expressed preference' : 'because there is no email address for this user is on record';
	include 'frame-bannerless.php';
	echo "<h2>No Can Do</h2><span style='font-size:1.2em'>$fname $lname does not accept notifications of this type $reason.</span>";
	exit;
}

$confirmationLinkIsPossible = $confirmationlink && $recipient['userid'];  // only recipients with system logins can confirm

$error = null;
if($_POST && ($action == 'register')) {  // *** REGISTRATION STAGE
	// We are here because user does not yet have a loginid/userid.  $loginid var is from the registration form.
	// Lookup loginid
  $user = findSystemLoginWithLoginId($loginid, true);
  // user is null, a string(insufficient rights error), or an array -- a user
  // new loginid and new user
  if(!$user) { // new loginid and new user
		$data = array_merge($_POST);
		$data['bizptr'] = $_SESSION['bizptr'];
		$newuser = addSystemLogin($data, 'clientOrProviderOnly');
		if(is_string($newuser)) $error = $newuser;
		else {
			updateTable($table, array('userid'=>$newuser['userid']), "$idField=$roleid", 1);
			$recipient['userid']  = $newuser['userid'];
		}
	}
  else $error = "Sorry, but [$loginid] is already in use.  Please try another username.";
}
else if($_POST && ($action == 'continue')) {  // *** REGISTRATION DECLINED
	$confirmationLinkIsPossible = false;
}
else if($_POST && ($action == 'notify')) {  // *** NOTIFICATION STAGE
function logoIMG($attributes='') {
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes>" :'';
}	


//echo print_r($_POST,1)."<p>";
	if($confirmationRequested && $recipient['userid']) { // ## IMPORTANT ## recipient MUST have a userid
		$due = date('Y-m-d H:i:s', time()+($dueIntervalMinutes * 60));
		$expiration = date('Y-m-d H:i:s', time()+($expirationMinutes * 60));
		$tokenIsUniqueInConfirmationTable = false;
		while(!$tokenIsUniqueInConfirmationTable) {
			$responseURL = generateResponseURL($_SESSION['bizptr'], $recipient, $confirmationlink, false, $expiration, true);
			$token = substr($responseURL, strpos($responseURL, '?token=')+strlen('?token='));
			if(!fetchRow0Col0("SELECT token FROM tblconfirmation WHERE token = '$token' LIMIT 1", 1))
				$tokenIsUniqueInConfirmationTable =  true;
		}
//echo "responseURL: $responseURL<p>";
}
//echo "confirmationRequestText 1: $confirmationRequestText<p>";

		if(!$token) { // SHOULD NOT HAPPEN.  WE HAVE PROBLEMS.
			$message = str_replace('##ConfirmationRequestText##', '', $message);
//logError("NULL token from: [$responseURL] conflink: [$confirmationlink]");
		}
		else {
			$confirmationRequestText = str_replace('##ConfirmationURL##', $responseURL, $confirmationRequestText);
//echo "confirmationRequestText 2: $confirmationRequestText<p>";
//echo "message 1: $message<p>";
			$message = str_replace('##ConfirmationRequestText##', $confirmationRequestText, $message);
		}
//echo "message 2: $message<p>";
	}
	else $message = str_replace('##ConfirmationRequestText##', '', $message);
	if(strpos($message, '#LOGO#') !== FALSE)
		$message = str_replace('#LOGO#', logoIMG(), $message);
		
//exit;

	$emailRecipient = $correspaddr ? $correspaddr : $recipient['email'];

	if($emailRecipient) {
		$allSuppliedEmails[] = $emailRecipient;
		$recipients = array("\"{$recipient['fname']} {$recipient['lname']}\" <$emailRecipient>");
		if($clientemail2) {
			$allSuppliedEmails[] = $clientemail2;
			$recipients[] = "\"$spouseName\" <$clientemail2>";
		}
		require_once "comm-fns.php";
		if($htmlMessage) $message = "<span style='$notificationLooks'>".plainTextToHtml($message).'</span>';
		
}		
		
		if($messageAppendix) {
			if($messageAppendixToken && strpos($message, $messageAppendixToken) !== FALSE)
				//$message = str_replace($messageAppendixToken, html_entity_decode($messageAppendix), $message);
				// REPLACED BY STATEMENT THOOBAN
				$message = str_replace($messageAppendixToken, $messageAppendix, $message);
			else $message .= $messageAppendix;
		}
 }		
		if($error = sendEmail($recipients, $subject, $message, null, $htmlMessage, $_POST['mgrname'])) {
			$error = "Mail error:<p>$error<br>(recipients: ".htmlentities(join(',', $recipients)).")";
		}

		else {//"msgid,inbound,correspid,correstable,mgrname,subject,body,datetime,transcribed,correspaddr"
			$messageToSave = 
				array('correspid'=>$roleid, 
							'correstable'=>$table, 
							'correspaddr'=>join(', ', $allSuppliedEmails), 
							'subject'=>$subject, 
							'body'=>$message, 
							'mgrname'=>$_POST['mgrname']);
			$msgptr = saveOutboundMessage($messageToSave);
		}
	}
	else $error = "No email address found for {$recipient['fname']} {$recipient['lname']}.";
	if(!$error && $confirmationRequested && $token) {
		// $token will be null if recipient has no userid.  Really, this should be stopped before now.
		require_once "confirmation-fns.php";
		saveNewConfirmation($msgptr, $roleid, $table, $token, $due, $expiration);
	}
	if(!$error) {
		echo "<script language='javascript'>if(window.opener && window.opener.update) window.opener.update('messages', null);window.close();</script>";
		exit;
	}
}

//	function htmlToPlainText($text) {
//	function plainTextToHtml($text) {


if($confirmationLinkIsPossible  && !$recipient['userid'] && $offerregistration) {  // REGISTRATION FORM
		// set up a user registration form
		$windowTitle = "Register $role as a system user";
		require "frame-bannerless.php";
		$rights = basicRightsForRole($role);
?>
<h2>System Login Editor</h2><h3><?= "$fname $lname" ?></h3>
<? if($error) echo "<font color='red'>$error</font>"; ?>
<form name='userlogineditor' method='POST' action='user-notify.php'>
<? if($error) echo "<font color='red'>$error</font>" ?>
<table width='100%'>
<?
// Pass-throughs
hiddenElement('clientid', $clientid);
hiddenElement('providerid', $providerid);
hiddenElement('confirmationoptional', $confirmationoptional);
hiddenElement('requestConfirmation', $requestConfirmation);
hiddenElement('confirmationlink', $confirmationlink);
hiddenElement('confirmationRequestText', stripslashes($confirmationRequestText));
hiddenElement('subject', $subject);
hiddenElement('message', $message);
hiddenElement('htmlMessage', $htmlMessage);
hiddenElement('messageAppendix', $messageAppendix);
hiddenElement('messageAppendixToken', $messageAppendixToken);
hiddenElement('appointmentid', $appointmentid);
hiddenElement('corresname', "$fname $lname");

hiddenElement('action', '');
hiddenElement('roleid', $roleid);
hiddenElement('rights', $rights);
hiddenElement('userid', '');
inputRow('Username:', 'loginid', $user['loginid']);
echo "<tr><td colspan=2>";
if(!$user['loginid']) 
  if($names = suggestedLogins($userid, $lname, $fname, $nickname, $email)) {
		$names = array_merge(array('-- Suggested Usernames --' => ''),array_combine($names, $names));
    selectElement('', 'suggestions', null, $names, $onChange='takeSuggestion(this)');
    echo " ";
	}
echoButton('','Check Username Availability', 'checkAvailability("availabilitymessage")');
echo "</td></tr>";
echo "<tr style='display:hidden'><td id='availabilitymessage' colspan=2></td></tr>";

passwordRow('Password:', 'password', '');
passwordRow('Retype Password:', 'password2', '');
inputRow('Temporary Password:', 'temppassword', '');
checkboxRow('Active', 'active', $user['loginid'] ? $user['active'] : 1);
echo "<tr><td colspan=2 align=center>";
echoButton('', "Save New Login for $fname $lname", 'saveChanges()');
echo " or ";
echoButton('', "Proceed without registering $fname $lname", 'document.getElementById("action").value="continue"; document.userlogineditor.submit()');
echo "<p>Note: A confirmation link cannot be sent to this user until he/she is registered.";
echo "</td></tr>";
?>
</table>
</form>

<?
dumpLoginEditorScripts(); 
}
else {  // EMAIL FORM
	require_once "email-fns.php";

	// set up a composer
	$windowTitle = 'Send a Notification';
	$extraBodyStyle = 'padding:10px;';
	require "frame-bannerless.php";
	echo "<table><tr>";
	echo "<td class='h2'>$windowTitle</td>";
	echo "<td style='text-align:right;'>";
	echo "<img src='art/spacer.gif' width=50 height=1>";
	echoButton('', 'Send Message', 'checkAndSend()');
	echo "<img src='art/spacer.gif' width=50 height=1>";
	echoButton('', 'Cancel', 'window.close()', 'HotButton', 'HotButtonDown');
	echo "</td></tr></table>";
	
	if($error) echo "<font color=red>$error</font><p>";
?>
<form method='POST' name='commcomposerform' action='user-notify.php'>
<table>
<?
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
require_once "comm-fns.php";
$mgrname = getUsersFromName();

inputRow('From:', 'mgrname', $mgrname);
labelRow("To $role:", '', "$fname $lname ");

if($templates) {
	require_once "email-template-fns.php";
	selectRow('Templates:', 'template', $template, $templates, 'templateChosen()');
}


//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
hiddenElement('clientid', $clientid);
hiddenElement('providerid', $providerid);
hiddenElement('confirmationlink', $confirmationlink);
hiddenElement('confirmationRequestText', stripslashes($confirmationRequestText));
hiddenElement('originalmessage', $message);
hiddenElement('htmlMessage', $htmlMessage);
hiddenElement('messageAppendix', $messageAppendix);
hiddenElement('messageAppendixToken', $messageAppendixToken);
hiddenElement('appointmentid', $appointmentid);
hiddenElement('corresname', "$fname $lname");

hiddenElement('action', '');
if(isset($properties)) hiddenElement('properties', $properties);
inputRow('Email:', 'correspaddr', $recipient['email'], null, 'emailInput');
if($clientid) {
echo "<tr><td colspan=2>".labeledCheckbox($spouseLabel, 'client2Checked', null, null, null, 'enable(this, "clientemail2")', 'boxfirst', 'noEcho').
				" ".labeledInput('email: ', 'clientemail2', $recipient['email2'], null, 'emailinput', null, null, 'noEcho')."</td></tr>";
$emailFields[] = 'clientemail2';
}
inputRow('Subject:', 'subject', (isset($subject) ? $subject : ''), null, 'emailInput');
//radioButtonRow($label, $name, $value=null, $options, $onClick=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null)
if($clientid && strpos($message, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	if($appointmentid) $petnames = getAppointmentPetNames($appointmentid, $petnames=null, $englishList=true);
	else $petnames = getClientPetNames($clientid, false, true);
}

if($recipient['userid'] && strpos($message, '#LOGINID#') !== FALSE || strpos($message, '#TEMPPASSWORD#') !== FALSE) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = {$recipient['userid']} LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass, 1);
	$recipient['loginid'] = $user['loginid'];
	$recipient['temppassword'] = $user['temppassword'];
}

$managerNickname = fetchRow0Col0(
	"SELECT value 
		FROM tbluserpref 
		WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");

$messageBody = mailMerge(htmlToPlainText($message), 
													array('#RECIPIENT#' => "$fname $lname",
																'#FIRSTNAME#' => $fname,
																'#LASTNAME#' => $lname,
																'#EMAIL#' => $recipient['email'],
																'#BIZNAME#' => $_SESSION['preferences']['shortBizName'],
																'#LOGINID#' => $recipient['loginid'],
																'#TEMPPASSWORD#' => $recipient['temppassword'],
																'#CREDITCARD#' => ccDescription($recipient),
																'#BIZID#' => $_SESSION["bizptr"],
																'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
																'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
																'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
																'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
																'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
																'##FullName##' => "$fname $lname",
																'##FirstName##' => $fname,
																'##LastName##' => $lname,
																'##Sitter##' => 'provider',
																'##BizName##' => $_SESSION['preferences']['shortBizName'],
																'#PETS#' => $petnames
																));																


if($confirmationoptional && $confirmationLinkIsPossible) {	
	radioButtonRow('Confirmation requested:', 'confirmationRequested', $requestConfirmation, array('Yes'=>1, 'No'=>0));
	echo "<tr><td colspan=2>If confirmation requested is selected, the request link will appear where <b>##ConfirmationRequestText##</b> appears in the message.</td></tr>";
}
else {
	$messageBody = mailMerge($messageBody, array('##ConfirmationRequestText##' => ""));
}
//##ConfirmationRequestText## with ##ConfirmationURL##
textRow('Message:', 'message', $messageBody, $rows=20, $cols=80, null, 'messagetextarea');
?>
</table>
</form>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('mgrname',"From (Manager's name)",'correspaddr',"<?= $role == 'client' ? 'Client' : 'Sitter' ?>'s Email Address",'clientemail2',"Alt Email Address, if supplied",'subject','Subject line','msgbody','Message');
function checkAndSend() {
	if(MM_validateForm('mgrname', '', 'R',
											'correspaddr','', 'R',
											'correspaddr','', 'isEmail',
											'clientemail2','', 'isEmail',
											'subject','', 'R',
											'msgbody','', 'R'
											)) {
			document.getElementById('action').value = 'notify';								
  		document.commcomposerform.submit();
  		};
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function enable(checkbox, inputid) {
	document.getElementById(inputid).disabled = !checkbox.checked;
}

function templateChosen() {
	if(!document.getElementById('template')) return;
	var id = document.getElementById('template').value;
	if(id == 0) return;
	
//<? if(mattOnlyTEST()) echo "alert('email-template-fetch.php?id='+id+'&'+"."'$role=$roleid&clientrequest={$_REQUEST['clientrequest']}'".");\n"; ?>
//alert('email-template-fetch.php?id='+id);
	ajaxGetAndCallWith('email-template-fetch.php?id='+id+'&'+<?= "'$role=$roleid&clientrequest={$_REQUEST['clientrequest']}&appointment=$appointmentid'" ?>, updateMessage, null);
}

function updateMessage(unused, resultxml) {
<?  ?>
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
		document.getElementById('message').value = body;
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



if(document.getElementById('clientemail2')) enable(client2Checked, 'clientemail2')
</script>
<?
}
function ccDescription($target) {
	require_once 'cc-processing-fns.php';
	if(!$target['clientid']) return "ERROR: CREDITCARD info available only for clients.";
	$cc = getClearCC($target['clientid']);
	if(!$cc) return "No credit card on record.";
	return $cc['company'].' **** **** **** '.$cc['last4'].' Exp: '.expirationDate($cc['x_exp_date']);
}

