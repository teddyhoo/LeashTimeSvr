<? // comm-general-request-to-sitter-composer.php
/*
requestid - general request id
client or provider - id of correspondent
replyto - id of msge being replied to
all - reply to all
SCRIPTVARS
properties - optional pipe-separated string to be put into a hidden element named properties
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "preference-fns.php";
require_once "email-template-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$allowMultiproviderptrs = $_SESSION['preferences']['enableSendToMultipleSitters']; //staffOnlyTEST();

//if($allowMultiproviderptrs) print_r($_POST);

$locked = locked('ka');//locked('o-'); 

extract(extractVars('replyto,all,requestid,providerptr,multiproviderptrs,correspid,corresname,spousename,correstable,correspaddr,subject,msgbody,mgrname,starting,ending', $_REQUEST));

$source = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");
if(!$source) {
	echo "Unknown request: $requestid";
	exit;
}
$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '{$source['clientptr']}' LIMIT 1");
if(!$clientName) $clientName = "{$source['fname']} {$source['lname']}";
$labelSpecific = $source['requesttype'] == 'General' ? 'General Client' : (
									$source['requesttype'] == 'Prospect' ? 'Prospect' : (
									$source['requesttype'] == 'Profile' ? 'General Client' : ''));
$label = mysql_real_escape_string("#STANDARD - Send $labelSpecific Request to Sitter");
$defaultTemplate = fetchFirstAssoc($sql = "SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
if(!$defaultTemplate) {
	// first try to generate it in email-template-fns
	ensureStandardTemplates($type='other');
	$defaultTemplate = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
}
if(!$defaultTemplate) {
	$defaultTemplate = 
		array('label'=>'#STANDARD - Send Request to Sitter', // '#STANDARD - Send Request to Sitter'
						'subject'=>"Client Request from #CLIENTNAME#",
						'body'=>"Hi #FIRSTNAME#,\n\nWe received the following request.\n\nSincerely,\n\n#BIZNAME#\n\n<hr>#REQUESTDESCRIPTION#");
	if(mattOnlyTEST()) echo "BONK! default template not set<hr>$sql<hr>$labelSpecific";
}

// KLUDGE
if($defaultTemplate['subject'] == "Schedule Request from #CLIENTNAME#") {
	$defaultTemplate['subject'] = "$labelSpecific Request from #CLIENTNAME#";
}

$template = $defaultTemplate;
	
$template['subject'] = str_replace('#CLIENTNAME#', $clientName, $template['subject']);
if($msgbody) $template['body'] = $msgbody;  // in case of error

$displayDescriptionAsDiv = staffOnlyTEST();

if($_POST) {
	//print_r($_POST);
	$msgBodyFromForm = $msgbody;
	
	if($multiproviderptrs) {
		foreach($multiproviderptrs as $provid) {
			$person = getProvider($provid);
			if(!$person['email'])
				$error[] = "{$person['fname']} {$person['lname']} has no email address.";
			else $persons[] = $person;
		}
		if($error) $error =join('<br>', $error);
	}
	else {
		$person = getProvider($providerptr); // before multiproviderptrs
		if(!$person['email']) {
			$error = "{$person['fname']} {$person['lname']} has no email address.";
		}
		else $persons[] = $person;
	}
	$sendLater = true;
	
	if(!$error && $sendLater) {
//if(mattOnlyTEST()) {echo "[error: $error] Send to ".print_r($recipients,1)." ".($sendLater ? 'later' : 'now').': '.print_r($msgbody,1);exit;}	
		foreach($persons as $person) {
			$msgbody = htmlize(preprocessMessage($person, $msgBodyFromForm));
			if(strpos($msgbody, '#REQUESTDESCRIPTION#') !== FALSE)
				$msgbody = str_replace('#REQUESTDESCRIPTION#', getRequestDescription($requestid, $noExternalCSS=true), $msgbody);
			else $msgbody .= getRequestDescription($requestid, $noExternalCSS=true);
			$result = enqueueEmailNotification($person, $subject, $msgbody, null, $mgrname, 'html');
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
		foreach($persons as $person)
			$officenotes[] = shortDate(time())." Sent to {$person['fname']} {$person['lname']}";
		updateTable('tblclientrequest', array('officenotes'=>join("\n", $officenotes)), "requestid = $requestid", 1);
		if(!$sendLater) {
			foreach($recipients as $recipient) {
				$msg['correstable'] = 'tblprovider';
				$msg['correspid'] = $recipient['providerid'];
				saveOutboundMessage($msg);
			}
		}
		echo "<script language='javascript'>if(window.opener && window.opener.update) window.opener.update('reload', null);window.close();</script>";
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




$pageTitle = "Email Request to a Sitter";

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
<?
hiddenElement('replyto', $replyto);
hiddenElement('corresname', $corrName);
hiddenElement('corresname', $corrName);
hiddenElement('correspid', $correspId);
hiddenElement('correstable', 'tblprovider');
echo "\n";
echo "<span style='display:none;' id='REQUESTDESCRIPTION'>".urlencode(getRequestDescription($requestid))."</span>\n";
echo "<span style='display:none;' id='CLIENTNAME'>$clientName</span>\n";
if(isset($properties)) hiddenElement('properties', $properties);

?><table>
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
inputRow('Subject:', 'subject', (isset($messageSubject) ? $messageSubject : $template['subject']), null, 'VeryLongInput');
$clientptr = fetchRow0Col0("SELECT clientptr FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");

if($allowMultiproviderptrs) {
$sitterDivId = 'sitterchooserdiv';
$sitterElementName = 'multiproviderptrs';
$sitterLabel = fauxLink('-- Select Sitters--', "$(\"#$sitterDivId\").toggle()", $noEcho=true, 'Choose sitters', 'sitterlabel');
$sitterDiv = sitterChoiceWidget($clientptr, $sitterDivId, $columns=4, $sitterElementName, $onchange='sitterClick()', $noEcho=true);
labelRow('To Sitters:', '', $value="$sitterLabel$sitterDiv", $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false);
}
else {
$selections = availableProviderSelectElementOptions($clientptr, null,  '--Select a Sitter--');
unset($selections['Inactive Sitters']);
selectRow('To sitter:', 'providerptr', $value=null, $selections, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);
}

$templates = array(substr($label, strlen(getSystemPrefix($defaultTemplate['label'])))=>$defaultTemplate['templateid']);
if($specialTemplates) foreach($specialTemplates as $spid=>$splabel) $templates[$spid] = $splabel;
$sql = "SELECT label, templateid 
				FROM tblemailtemplate WHERE active = '1' AND targettype = 'provider' AND body LIKE '%#REQUESTDESCRIPTION#%' ORDER BY label";


foreach(fetchKeyValuePairs($sql) as $label =>$id) {
	if(getSystemPrefix($label)) $templates[substr($label, strlen(getSystemPrefix($label)))] = $id;
	else $templates[$label] = $id;
}
if(count($templates) > 1) selectRow('Templates:', 'template', $template, $templates, 'templateChosen()');




// $messageBody may be defined by including script

$messageBody = $messageBody ? $messageBody  : preprocessMessage($nullperson, $template['body']);
if(strpos($messageBody, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($clientObject['clientid'], false, true);
}
if(strpos($messageBody, '#MANAGER#') !== FALSE) {
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
}


$substitutes = array(
										'##BizName##' => $_SESSION['bizname'],
										'#REQUESTDESCRIPTION#' => getRequestDescription($requestid),
										'#BIZNAME#' => $_SESSION['preferences']['bizName'],
										'#BIZID#' => $_SESSION["bizptr"],
										'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
										'#BIZLOGINPAGE#' => "http://leashtime.com/login-page.php?bizid={$_SESSION['bizptr']}",
										'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
										'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
										'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
										);
if(TRUE || $displayDescriptionAsDiv) unset($substitutes['#REQUESTDESCRIPTION#']);
/*if($corrName) $substitutes['#RECIPIENT#'] = $corrName;
if($correspondent) {
	$substitutes['#FIRSTNAME#'] = $correspondent['fname'];
	$substitutes['#LASTNAME#'] =  $correspondent['lname'];
}
*/
$messageBody = mailMerge($messageBody, $substitutes);
//textRow($label, $name, $value=null, $rows=3, $cols=20, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2) {

if($displayDescriptionAsDiv) $tokenExplanation = "<span class='tiplooks'> #REQUESTDESCRIPTION# will be replaced by the request below when the message is sent.";

textRow("Message:$tokenExplanation", 'msgbody', $value=$messageBody, $rows=18, $cols=80, null, 'fontSize1_2em');
//print_r($messageBody);
?>
</table>
<?
echoButton('', 'Send Message', 'checkAndSend()');
echo "<p class='tiplooks'>If the token #REQUESTDESCRIPTION# is used in the message, it will be replaced with a description of the request."
		."<br>Otherwise, a description of the request will be added at the end of the message.</p>";
if($displayDescriptionAsDiv) echo "<p><div style='display:block;background:white;border: solid 1px black;'>".getRequestDescription($requestid, 'noExternalCSS')."</div>\n";
?>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script language='javascript'>
setPrettynames('mgrname',"From (Manager's name)",'providerptr',"Sitter",'subject','Subject line','msgbody','Message');

function sitterClick() {
	let nodes = document.getElementsByName('multiproviderptrs[]');
	let names = [];
	for(let i=0; i<nodes.length; i++)
		if(nodes[i].checked) names.push(nodes[i].getAttribute('label'));
	let selections = names.length;
	names = names.join(', ');
	if(names == '') names = '-- Select Sitters--';
	$('#sitterlabel').html(names);
	$('#sitterlabel').attr('selectioncount', selections);
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
		<? if(FALSE && !$displayDescriptionAsDiv) { ?>
		body = body.replace('#REQUESTDESCRIPTION#', decodeURIComponent(document.getElementById('REQUESTDESCRIPTION').innerHTML));
		<? } ?>
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
<? if($allowMultiproviderptrs) { ?>
		let nositters = $('#sitterlabel').attr('selectioncount');
		nositters = nositters == 0 || nositters == '' || typeof nositters == 'undefined';
		if(nositters) 
			nositters = 'At least one sitter must be chosen.';
		if(MM_validateForm('mgrname', '', 'R',
												nositters,'', 'MESSAGE',
												'subject','', 'R',
												'msgbody','', 'R'
												))
<? } else { ?>
	if(MM_validateForm('mgrname', '', 'R',
											'providerptr','', 'R',
											'subject','', 'R',
											'msgbody','', 'R'
											))
<? }  ?>
  		document.commcomposerform.submit();
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

</script>
<?
// ***************************************************************************
//include "frame-end.html";
function getRequestDescription($requestid, $noExternalCSS=false) {
	global $source;
	if($source['requesttype'] == 'General') return generalRequestDescription($requestid);
	else if($source['requesttype'] == 'Prospect') return prospectRequestDescription($requestid);
	else if($source['requesttype'] == 'Profile') {
		require_once "client-profile-request-fns.php";
		$source = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = $requestid LIMIT 1");
		ob_start();
		ob_implicit_flush(0);
		echo "The following Profile Changes were requested:<br>";
		showProfileChangeDisplayTable($source, $noExternalCSS);
		$panel = ob_get_contents();
		ob_end_clean();
		return $panel;
	}
}

function generalRequestDescription($requestid) {
	global $source;
	$requestnote = $source['note'];
	if($requestnote) $requestnote = urldecode(trim($requestnote));
	else $requestnote = "";

	$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = '{$source['clientptr']}' LIMIT 1");
	// Note format
	// line 0: start|end|totalCharge
	// line 2: service|service|..<>service|service|..<>
	// service: servicecode#timeofday#pets
	// returns array(start|end|totalCharge, services=>(service1, service2, ...), (service1, service2, ...),...)
	// $schedule['services'] format: day0=>array(array(servicecode,timeofday,pets,charge),...), day1=>(...)
	$received = strtotime($source['received']);
	$message = "Client $clientName made this request on ".longDayAndDate($received).' at '.date('g:i a', $received).': ';
	if($requestnote) $message .= "\n\n$requestnote";
	return $message;
}

function prospectRequestDescription($requestid) {
	global $source;
	require_once "request-fns.php";
	ob_start();
	ob_implicit_flush(0);
	echo "<table width='90%'><tr><td colspan=2 style='font-size:2em;font-weight:bold'>Prospect Request</td></tr><tr><td valign=top><table width=100%>";
	labelRow('First Name:', '', $source['fname']);
	labelRow('Last Name:', '', $source['lname']);
	labelRow('Phone:', '', $source['phone']);
	labelRow('When to call:', '', $source['whentocall']);
	labelRow('Email:', '', $source['email']);
	echo "\n</table></td>\n";
	echo "<td valign=top><table width=100%>";
	labelRow('Date:', '', $source['date']);
	labelRow('Address:', '', '');
	if($source['address']) {
		echo "<tr><td colspan=2 style='padding-left:13px'>".str_replace("\n", '<br>', $source['address'])."</td></tr>";
	}
	else {
		$addr = array($source['street1'], $source['street2'], $source['city'], $source['state'], $source['zip']);
		echo "<tr><td colspan=2 style='padding-left:13px'>".htmlFormattedAddress($addr)."</td></tr>";
	}
	labelRow('Pets:', '', '');
	echo "<tr><td colspan=2 style='padding-left:13px'>".str_replace("\n", '<br>', $source['pets'])."</td></tr>";
	echo "\n</table></td></tr>\n";

	echo "<tr><td colspan=2><table width=90%>";
	$note = $source['note'];
	$noteLabel = $source['requesttype'] == 'change' ? 'Requested changes:' : 'Note:';
	echo "<tr><td class='notelabel'>$noteLabel</td><td style='border:solid black 1px;'>".str_replace("\n", '<br>', $note)."</td></tr>";

	displayExtraFields($source, $displayOnly=true);

	echo "</table></td></tr></table>";
	
	$requestnote = ob_get_contents();
	ob_end_clean();
	$requestnote = 
		str_replace("<label for=''>", '', 
		str_replace("</label>", '', 
		str_replace("\r", '', 
		str_replace("\n", '', 
		str_replace("id=''", '', $requestnote)))));
	
	$message .= "\n\n$requestnote";
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

