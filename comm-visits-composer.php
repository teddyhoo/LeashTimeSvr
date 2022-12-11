<? // comm-visits-composer.php
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
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "preference-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('ka');//locked('o-'); 

extract(extractVars('replyto,all,client,provider,correspid,corresname,spousename,correstable,correspaddr,subject,msgbody,mgrname,starting,ending,scheduleid,offer', $_REQUEST));

if($_REQUEST['getemail']) {
	echo fetchRow0Col0("SELECT email FROM tblprovider WHERE providerid = '{$_REQUEST['getemail']}' LIMIT 1", 1);
	exit;
}
	

if($scheduleid) {
	require "service-fns.php";
	$schedule = fetchFirstAssoc("SELECT * FROM tblservicepackage WHERE packageid = '$scheduleid' LIMIT 1", 1);
	$starting = $schedule['startdate'];
	$ending = $schedule['enddate'];
	$appts = fetchAllAppointmentsForNRPackage($schedule, $schedule['clientptr']);
	// add pname, pemail, pactive
	$schedulesitters = array();
	foreach($appts as $appt) 
		if(!$appt['cancelled'] && $appt['providerptr']) $schedulesitters[$appt['providerptr']] = 1;
	if($schedulesitters) {
		$schedulesitters = fetchAssociationsKeyedBy(
			"SELECT providerid, IFNULL(nickname, CONCAT_WS(' ', prov.fname, prov.lname)) as pname, prov.email as pemail, prov.active as pactive
				FROM tblprovider prov
				WHERE providerid IN (".join(',', array_keys($schedulesitters)).")", 'providerid', 1);
		foreach($appts as $i => $appt)
			if($appt['providerptr'])
				foreach($schedulesitters[$appt['providerptr']] as $k=>$v)
					$appts[$i][$k] = $v;
	}
				
}
else {
	$starting = date('Y-m-d', strtotime($starting));
	$ending = date('Y-m-d', strtotime($ending));
	$appts = fetchAssociationsKeyedBy(
		"SELECT tblappointment.*, IFNULL(nickname, CONCAT_WS(' ', prov.fname, prov.lname)) as pname, prov.email as pemail, prov.active as pactive
			FROM tblappointment
			LEFT JOIN tblprovider prov ON providerid = providerptr
			WHERE date >= '$starting' AND date <= '$ending' AND clientptr = $client
			ORDER BY date, starttime", 'appointmentid'
			);
}

$providers = array();
foreach($appts as $appt) 
	if($appt['providerptr'] && $appt['pactive']) 
		$providers[$appt['providerptr']] =  array('providerptr' => $appt['providerptr'], 'name'=>$appt['pname'], 'email'=>$appt['pemail']);

$templateLabel = $_POST['template'] ? $_POST['template'] : "#STANDARD - Client's Schedule";
$safeLabel = mysqli_real_escape_string($templateLabel);
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$safeLabel' LIMIT 1");



$clientObject = fetchFirstAssoc("SELECT clientid, fname, lname, userid FROM tblclient WHERE clientid = $client LIMIT 1");
$noPrice = $_SESSION['preferences']['omitPriceInfoInClientScheduleEmailLists'] || $_POST['noPrice'];
//global $applySitterNameConstraintsInThisContext; // see provider-fns.php > getProviderShortNames()
$oldApplyValue = $applySitterNameConstraintsInThisContext;
$applySitterNameConstraintsInThisContext = true;
$calendarSchedule = getClientCalendar($client, $starting, $ending, $appts, $noPrice);
$applySitterNameConstraintsInThisContext = $oldApplyValue;

if($_POST) {
	$clientObj = getClient($correspid);
	// confirm the original client matches the client to be emailed now
	// this aims to derail mailing when the user has logged into a different database
	if(!$clientObj || $corresname != "{$clientObj['fname']} {$clientObj['lname']}") {
		$POSTerror = "$corresname is not a client in this database.";
	}
}
if($_POST && !$POSTerror) {
	
	
	//print_r($_POST);
	$recipients = array(); //
	if($_POST['clientemail']) {
		$recipients[] = "\"$corresname\" <{$_POST['clientemail']}>";
		$correspondents[] = array('tblclient', $correspid);
	}
	if($_POST['clientemail2']) {
		$recipients[] = "\"$spousename\" <{$_POST['clientemail2']}>";
		//$correspondents[] = array('tblclient', $correspid);
	}
	foreach($_POST as $key => $email) {
		if(strpos($key, 'provemail_') !== 0) continue;
		$providerptr = substr($key, strlen('provemail_'));
		if(!$providerptr) continue;
		$provs[] = array('providerid'=> $providerptr, 'email'=>$email);
		$recipients[] = $email;
		$correspondents[] = array('tblprovider', $providerptr);
	}
	
	if($_POST['chosenprovid']) {
		$providerptr = $_POST['chosenprovid'];
		$email = $_POST['chosenprovemail'];
		$recipients[] = $email;
		$correspondents[] = array('tblprovider', $providerptr);
	}
	
	$msgBodyFromForm = $msgbody;
	
	
	$sendLater = true;
	if($sendLater) {
		if($_POST['clientemail'] || $_POST['clientemail2']) {
			if(getClientPreference($client, 'sendScheduleAsList')) {
				$oldApplyValue = $applySitterNameConstraintsInThisContext;
				$applySitterNameConstraintsInThisContext = true;
				$schedule = getClientAppointmentList($clientid, $start, $end, $appts, $noPrice);
				$applySitterNameConstraintsInThisContext = $oldApplyValue;
			}
			else $schedule = $calendarSchedule;
			$msgbody = preprocessMessage($clientObject, $msgBodyFromForm, $schedule);
			if($_POST['clientemail']) $addresses[] = $_POST['clientemail'];
			if($_POST['clientemail2']) $addresses[] = $_POST['clientemail2'];
			$clientObj['email'] = join(',', $addresses);
			enqueueEmailNotification($clientObj, $subject, $msgbody, null, $mgrname, 'html');
		}
		
		
		foreach($correspondents as $i => $corres) {
			if(getProviderPreference($corres[1], 'sendScheduleAsCalendar')) {
				if(!$providerCalendarSchedule) 
					$providerCalendarSchedule = getClientCalendar($client, $starting, $ending, $appts, 'noPrice');
				$schedule = $providerCalendarSchedule;
			}
			else {
				if(!$providerListSchedule) 
					$providerListSchedule = getClientAppointmentList($clientid, $start, $end, $appts, 'noPrice', $forceVisitTimeInclusion=true);
				$schedule = $providerListSchedule;
			}
			$msgbody = preprocessMessage($clientObject, $msgBodyFromForm, $schedule);
			if($corres[0] != 'tblprovider') continue;
			$provObj = getProvider($corres[1]);
			$provObj['email'] = $_POST['provemail_'.$provObj['providerid']];
			$pname = $provObj['nickname'] ? $provObj['nickname'] : $provObj['fname'];
			$msgbody = "Dear $pname,<p>The following visit schedule was sent to client {$clientObj['fname']} {$clientObj['lname']}<p>"
									.$msgbody;
			enqueueEmailNotification($provObj, $subject, $msgbody, null, $mgrname, 'html');
		}
	}
	
	else if($error = sendEmail($recipients, $subject, $msgbody, '', 'html', $mgrname, $bcc)) {
		if($error == 'BadCustomSMTPSettings')
			$error = "SMTP Server connection settings (host, port, username, or password) are incorrect.<p>"
								."Please review your "
								.fauxLink('<b>Outgoing Email</b> preferences', 'if(window.opener) window.opener.location.href="preference-list.php?show=4";window.close();', 1, 'Go there now')
								." in ADMIN > Preferences";
		include "frame-bannerless.php";
		echo "Mail error:<p>".print_r($error, 1);
		exit;
	}
	
	
	
	
	
	$msg = $_POST;
	if(!$sendLater) foreach($correspondents as $corres) {
		$msg['correstable'] = $corres[0];
		$msg['correspid'] = $corres[1];
		saveOutboundMessage($msg);
	}
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
}

//$client or $provider will be set, containing the recipient's id
$correspondent = array();

if(isset($client)) {
	require_once "client-fns.php";
	$correspondent = getClient($client);
	$corresTable = 'tblclient';
	$corresType = 'Client';
	$correspId = $client;
	if($correspondent['fname2']) $spouse[] = $correspondent['fname2'];
	if($correspondent['lname2']) $spouse[] = $correspondent['lname2'];
	if($spouse) $spouseName = join(' ', $spouse);
	$spouseLabel = $spouse ? "to $spouseName" : "to alternate name";
}
else if(isset($provider)) {
	require_once "provider-fns.php";
	$correspondent = getProvider($provider);
	$corresTable = 'tblprovider';
	$corresType = 'Sitter';
	$correspId = $provider;
}

if(isset($email) && !$correspondent) {
	$correspondent = array('fname' =>$fname, 'lname' =>$lname, 'email'=>$email);
}

$corrName = $correspondent['fname'].' '.$correspondent['lname'];
$corrAddr = $correspondent['email'];


if($POSTerror) {
	require "frame-bannerless.php";
	echo "<h2>WARNING</h2>";
	echo "<font color='red'>$POSTerror</font><p>";
	echo "You are currently logged in to <b>{$_SESSION['preferences']['bizName']}</b>.<p>";
	exit;
}

$error = null;


$pageTitle = "Email $corrName's Calendar";

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
if($offer) $extraHeadContent = '<script type="text/javascript" src="jquery-1.7.1.min.js"></script>';
require "frame-bannerless.php";

echo "<h2>$pageTitle</h2>";
?>
<form method='POST' name='commcomposerform'>
<table <?= FALSE ? "border=1" : "" ?>>
<?
if($reference) {
	$messageBody = "\n\n======\n$corrName wrote:\n\n{$reference['body']}";
	$messageSubject = (strpos($reference['subject'], "Re: ") === 0 ? '' : "Re: ").$reference['subject'];
}
// labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)

inputRow('From:', 'mgrname', getUsersFromName());

if(!$offer) {
	echo "<tr><td colspan=1>".labeledCheckbox("to $corrName", 'clientChecked', 'client', null, null, 'enable(this, "clientemail")', 'boxfirst', 'noEcho').
					"<td width=75% style='padding-left:20px'>".labeledInput('email: ', 'clientemail', $corrAddr, null, 'emailinput', null, null, 'noEcho')."</td></tr>";
	$emailFields[] = 'clientemail';

	echo "<tr><td colspan=1>".labeledCheckbox($spouseLabel, 'client2Checked', null, null, null, 'enable(this, "clientemail2")', 'boxfirst', 'noEcho').
					"<td style='padding-left:10px'>".labeledInput('email: ', 'clientemail2', $correspondent['email2'], null, 'emailinput', null, null, 'noEcho')."</td></tr>";
	$emailFields[] = 'clientemail2';



	foreach($providers as $prov) {
		$emailField = "provemail_{$prov['providerptr']}";
		echo "<tr><td colspan=1>".labeledCheckbox("to sitter {$prov['name']}", "prov_{$prov['providerptr']}", true, null, null, 
																								"enable(this, \"$emailField\")", 'boxfirst', 'noEcho').
						"<td style='padding-left:10px'>".labeledInput('email: ', $emailField, $prov['email'], null, 'emailinput', null, null, 'noEcho')."</td></tr>";
		$emailFields[] = $emailField;
	}
}
else {
	echo "<tr><td>To: ";
	availableProviderSelectElement($schedule['clientptr'], $date=null, 'chosenprovid', '-- Choose Sitter --', $_POST['chosenprovid'], $onchange='updateChosenEmail()', $offerUnassigned=null);
	echo "</td><td>";
	labeledInput('', 'chosenprovemail', $_POST['chosenprovemail'], null, 'emailinput', null, null);
	echo "</td></tr>";
}

//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
hiddenElement('replyto', $replyto);
hiddenElement('corresname', $corrName);
hiddenElement('corresname', $corrName);
hiddenElement('correspid', $correspId);
hiddenElement('correstable', $corresTable);
hiddenElement('spousename', $spouseName);
if(isset($properties)) hiddenElement('properties', $properties);
inputRow('Subject:', 'subject', (isset($messageSubject) ? $messageSubject : $template['subject']), null, 'verylonginput');

if($_SESSION['preferences']['enableVisitsComposerTemplates'] && in_array(userRole(), array('o', 'd'))) { // this section added 3/9/2021
	$templates = array(''=>'');
	$rType = $provider ? 'provider' : ($client ? 'client' : '');
	if($specialTemplates) foreach($specialTemplates as $spid=>$splabel) $templates[$spid] = $splabel;
	$sql = "SELECT label, templateid FROM tblemailtemplate WHERE active = '1' AND targettype = '$rType' $adminOnlyExclusion ORDER BY label"; //  AND label NOT LIKE '#STANDARD - %'

	require_once "email-template-fns.php";
	foreach(fetchKeyValuePairs($sql) as $label =>$id) {
		if(!$firstAdded) $templates['=========='] = '';
		$firstAdded = 1;
		if(getSystemPrefix($label)) $templates[substr($label, strlen(getSystemPrefix($label)))] = $id;
		else $templates[$label] = $id;
	}
	

	$orgTemplates = getOrganizationEmailTemplateOptions($rType);
	if($orgTemplates) $templates['Shared Templates'] = $orgTemplates;
	if($templates) {
		$templates = array_reverse($templates);
		$templates[''] = '';
		$templates = array_reverse($templates);
	}
	if(count($templates) > 1) selectRow("Templates:", 'template', $template['templateid'], $templates, 'templateChosen()');
}





// $messageBody may be defined by including script

$messageBody = $messageBody ? $messageBody  : preprocessMessage($clientObject, $template['body']);

if(strpos($messageBody, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($clientObject['clientid'], false, true);
}
if(strpos($messageBody, '#LOGINID#') !== FALSE || strpos($messageBody, '#TEMPPASSWORD#')) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = '{$correspondent['userid']}' LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');
}
if($client && strpos($messageBody, '#CREDITCARD#')) {
	$cclast4 = fetchRow0Col0("SELECT last4 FROM tblcreditcard WHERE clientptr = '$client' AND active=1 LIMIT 1");
}


$messageBody = mailMerge($messageBody, 
													array('##FullName##' => $corrName,
																'##FirstName##' => $correspondent['fname'],
																'##LastName##' => $correspondent['lname'],
																'##Provider##' => $corrName,
																'##BizName##' => $_SESSION['bizname'],
																'#RECIPIENT#' => $corrName,
																'#FIRSTNAME#' => $correspondent['fname'],
																'#LASTNAME#' => $correspondent['lname'],
																'#LOGINID#' => $user['loginid'],
																'#TEMPPASSWORD#' => $user['temppassword'],
																'#CREDITCARD#' => $cclast4,
																'#BIZNAME#' => $_SESSION['bizname'],
																'#PETS#' => $petnames
																));

if(!$noPrice && staffOnlyTEST()) checkboxRow('Omit charge information', 'noPrice', $noPrice, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onChange=null, $rowClass=null, $extraContent=null);

textRow('Message:', 'msgbody', $messageBody, $rows=18, $cols=80, null, 'fontSize1_2em');
?>
</table>
<?
echoButton('', 'Send Message', 'checkAndSend()');
?>
</form>
<?
$oldApplyValue = $applySitterNameConstraintsInThisContext;
$applySitterNameConstraintsInThisContext = true;
echo $calendarSchedule . getClientAppointmentList($client, $start, $end, $appts, $noPrice, $forceVisitTimeInclusion=true); 
$applySitterNameConstraintsInThisContext = $oldApplyValue;
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('mgrname',"From (Manager's name)",'correspaddr',"$corresType's Email Address",'subject','Subject line','msgbody','Message');
function checkAndSend() {
<?	
foreach((array)$emailFields as $fieldname) $tests[] = "'$fieldname','', 'isEmail'";
if($offer) {
	$tests[] = "'chosenprovemail','', 'R'";
	$tests[] = "'chosenprovemail','', 'isEmail'";
	$tests[] = "'chosenprovid','', 'R'";
}
if($tests) $tests = join(',', $tests);
$emailFields = $emailFields ? "['".join("', '", $emailFields)."']" : " new Array();";
echo "var emailfields = $emailFields;\n";
?>	
	var emailsSupplied=0;
	var emptyActives = false;
	for(var i=0;i<emailfields.length;i++) {
		var el = document.getElementById(emailfields[i]);
		if(!el.disabled && el.value) emailsSupplied++;
		else if(!el.disabled && !el.value)
			emptyActives = true;
	}
	var noEmails = 
		emailsSupplied 
			? (emptyActives ? 'For each checked recipient, an email address must be supplied.' : null) 
			: 'At least one email address must be supplied.';
	if(MM_validateForm('mgrname', '', 'R',
											//'correspaddr','', 'R',
											<?= $tests ?>,
											noEmails, '', 'MESSAGE',
											'subject','', 'R',
											'msgbody','', 'R'
											))
  		document.commcomposerform.submit();
}

function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

function enable(checkbox, inputid) {
	document.getElementById(inputid).disabled = !checkbox.checked;
}

function updateChosenEmail() {
	var providerid = document.getElementById('chosenprovid');
	if(providerid) providerid = providerid.options[providerid.selectedIndex].value;
	if(!providerid) document.getElementById('chosenprovemail').value = null;
	else $.ajax({url: 'comm-visits-composer.php?getemail='+providerid, 
							success: function(data) {document.getElementById('chosenprovemail').value = data;}});
}

function templateChosen() {
	if(!document.getElementById('template')) return;
	var id = document.getElementById('template').value;
	if(id == 0) return;
	
//alert('email-template-fetch.php?id='+id);

	var extraArgs = '<?= $_REQUEST['clientrequest'] ? "&clientrequest={$_REQUEST['clientrequest']}" : "" ?>';
	ajaxGetAndCallWith(
			'email-template-fetch.php?id='+id+extraArgs
			+'&'+<?= "'$clientProviderOrProspect".'='.$correspId."'" ?>, updateMessage, null);
}
	
function updateMessage(unused, resultxml) {
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


if(document.getElementById('clientemail2')) document.getElementById('clientemail2').disabled = true;
</script>
<?
// ***************************************************************************
//include "frame-end.html";

//	$label = mysqli_real_escape_string("#STANDARD - Client's Schedule");
//	$template = fetchRow0Col0("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
function preprocessMessage($client, $message, $schedule=false) {
	if(strpos($message, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		$petnames = getClientPetNames($client['clientid'], false, true);
	}
	
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");

	if(strpos($message, '#LOGINID#') !== FALSE || strpos($message, '#TEMPPASSWORD#')) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require_once "common/init_db_common.php";
		$user = fetchFirstAssoc("SELECT * FROM tbluser WHERE userid = '{$client['userid']}' LIMIT 1");
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');
	}
	if($client && strpos($message, '#CREDITCARD#')) {
		$cclast4 = fetchRow0Col0("SELECT last4 FROM tblcreditcard WHERE clientptr = '{$client['clientid']}' AND active=1 LIMIT 1");
	}
	
	$message = mailMerge($message, 
														array('#RECIPIENT#' => "{$client['fname']} {$client['lname']}",
																	'##FullName##' => "{$client['fname']} {$client['lname']}",
																	'##FirstName##' => $client['fname'],
																	'##LastName##' => $client['lname'],
																	'#FIRSTNAME#' => $client['fname'],
																	'#LASTNAME#' => $client['lname'],
																	'#LOGINID#' => $user['loginid'],
																	'#TEMPPASSWORD#' => $user['temppassword'],
																	'#CREDITCARD#' => $cclast4,
																	'#BIZNAME#' => $_SESSION['preferences']['bizName'],
																	'#BIZID#' => $_SESSION["bizptr"],
																	'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
																	'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
																	'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
																	'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
																	'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
																	'#PETS#' => $petnames
																	));	
	
	
	if($schedule) $message = str_replace('#LOGO#', logoIMG(), $message);
	//$message = str_replace('#SENDER#', $signature, $message);
	//echo $body.'<p>';
	$hasHtml = strpos($message, '<') !== FALSE;
	if($hasHtml && $schedule) {
		$message = str_replace("\r", "", $message);
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	if($schedule) {
		if(strpos($message, '#SCHEDULE#') === FALSE) $message = "$message<p>#SCHEDULE#";
		$message = mergeUpcomingSchedule($message, $schedule);
	}
	return $message;
}	

function mergeUpcomingSchedule($message, $schedule) {
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	$message = str_replace('#SCHEDULE#', $schedule, $message);
	return $message;
}



function getClientAppointmentList($clientid, $start, $end, $appts, $noPrice=false, $forceVisitTimeInclusion=false) {
	global 	$userRole, $suppressNoVisits, $clientPriceSummary;

	foreach($appts as $apptid => $appt) if(!$appt['canceled']) $uncanceledAppts[$apptid] = $appt;
	$appts = $uncanceledAppts;
	require_once "appointment-calendar-fns.php";
	ob_start();
	ob_implicit_flush(0);
	//echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/style.css" type="text/css" /> 
	//<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/pet.css" type="text/css" />'."\n";
	echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/style.css" type="text/css" />';
	if(FALSE) echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/no-body-background.css" type="text/css" />';
	echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/pet.css" type="text/css" />'."\n";
	$schedule['startdate'] = $start;
	$schedule['enddate'] = date('Y-m-d', strtotime($end));
	$userRole = 'c';
	$suppressNoVisits = 1;
	//dumpCalendarLooks(100, 'lightblue');
	echo "<div style='width:95%'>";
	if(!$noPrice && $appts) {
		if(!$clientPriceSummary) figureClientPriceSummary($appts, $clientid, $start, $end);
		echo "<b>Service Charges: </b>".dollarAmount($clientPriceSummary['undiscountedservices']).'<br>';
		if($clientPriceSummary['surcharges']) {
			$surchargtypes = " ({$clientPriceSummary['surchargtypes']})";
			echo "<b>Surcharges$surchargtypes: </b>".dollarAmount($clientPriceSummary['surcharges']).'<br>';
		}
		if($clientPriceSummary['discounts']) echo "<b>Discounts: </b>".dollarAmount($clientPriceSummary['discounts']).'<br>';
		echo "<b>Total Charges: </b>".dollarAmount($clientPriceSummary['total']).'<br>';
	}
	require_once "appointment-calendar-fns.php";
	echo clientVisitList($appts, $forceVisitTimeInclusion); // moved to appointment-calendar-fns.php
	echo "</div>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function getClientCalendar($clientid, $start, $end, $appts, $noPrice=false) {
	global 	$userRole, $suppressNoVisits, $clientPriceSummary;

	foreach($appts as $appt) if(!$appt['canceled']) $uncanceledAppts[] = $appt;
	$appts = $uncanceledAppts;
	require_once "appointment-calendar-fns.php";
	ob_start();
	ob_implicit_flush(0);
	echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/style.css" type="text/css" />';
	if(FALSE) echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/no-body-background.css" type="text/css" />';
	echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/pet.css" type="text/css" />'."\n";
	$schedule['clientptr'] = $clientid;
	$schedule['startdate'] = $start;
	$schedule['enddate'] = date('Y-m-d', strtotime($end));
	$userRole = 'c';
	$suppressNoVisits = 1;
	dumpCalendarLooks(100, 'lightblue');
	echo "<div style='width:95%'>";
	if(!$noPrice) {
		if(!$clientPriceSummary) figureClientPriceSummary($appts, $clientid, $start, $end);
		echo "<b>Service Charges: </b>".dollarAmount($clientPriceSummary['undiscountedservices']).'<br>';
		if($clientPriceSummary['surcharges']) {
			$surchargtypes = " ({$clientPriceSummary['surchargtypes']})";
			echo "<b>Surcharges$surchargtypes: </b>".dollarAmount($clientPriceSummary['surcharges']).'<br>';
		}
		if($clientPriceSummary['discounts']) echo "<b>Discounts: </b>".dollarAmount($clientPriceSummary['discounts']).'<br>';
		echo "<b>Total Charges: </b>".dollarAmount($clientPriceSummary['total']).'<br>';
	}
	appointmentTable($appts, $schedule, $editable=false, $allowSurchargeEdit=false, $showStats=false, $includeApptLinks=false, $surcharges=null);
	echo "</div>";
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function figureClientPriceSummary($appts, $clientid, $start, $end) {
	global $clientPriceSummary;
	require_once "tax-fns.php";
	$clientTaxRates = getClientTaxRates($clientid);  // includes default rates
	foreach((array)$appts as $apptid => $appt) if(!$appt['canceled']) $uncanceled[$appt['appointmentid']] = $appt;
	$discounts = array();
	if($uncanceled) $discounts = fetchAssociationsKeyedBy("SELECT * FROM relapptdiscount WHERE appointmentptr IN (".join(',', array_keys($uncanceled)).")", 'appointmentptr');	
	foreach((array)$uncanceled as $apptid => $appt) {
//if($_SESSION['staffuser'])echo "($apptid) ch: {$appt['charge']} adj: {$appt['adjustment']}  discounts: ".print_r($discounts[$apptid],1)."<br>";	
		$discount = $discounts[$apptid] ? $discounts[$apptid]['amount'] : 0;
		$discountsum += $discount;
		$charge = $appt['charge']+$appt['adjustment']-$discount;
		$undiscountedCharges += $appt['charge']+$appt['adjustment'];
		$tax = round($charge * $clientTaxRates[$appt['servicecode']]) / 100;
		$sum += $charge + $tax;
	}
	
	$surcharges = fetchAssociations(
		"SELECT s.charge, a.servicecode
			FROM tblsurcharge s
			LEFT JOIN tblappointment a ON appointmentid = appointmentptr
			WHERE s.clientptr = $clientid AND s.canceled IS NULL AND s.date >= '$start' AND s.date <= '$end'");
	foreach((array)$surcharges as $i => $surch) {
		if($surch['servicecode']) $tax = round($surch['charge'] * $clientTaxRates[$appt['servicecode']]) / 100;
		else $tax = round($surch['charge'] * $_SESSION['preferences']['taxRate']) / 100;
		$surchargesum += $surch['charge'] + $tax;
	}
	
	//$surchargesum = fetchRow0Col0("SELECT sum(charge) FROM tblsurcharge WHERE clientptr = $clientid AND canceled IS NULL AND date >= '$start' AND date <= '$end'");
	$surchargeLabels = fetchCol0("SELECT distinct label 
																FROM tblsurcharge 
																LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
																WHERE clientptr = $clientid 
																	AND canceled IS NULL 
																	AND tblsurcharge.date >= '$start' 
																	AND tblsurcharge.date <= '$end'");
	$clientPriceSummary = array(
		'undiscountedservices'=>$undiscountedCharges,
		'services'=>$sum, 
		'surcharges'=>$surchargesum, 
		'surchargtypes'=>join(', ', $surchargeLabels), 
		'discounts'=>$discountsum, 
		'total'=>$sum+$surchargesum/*-$discountsum*/);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "Tax: $tax<p>";print_r($clientPriceSummary);exit;}
		
}

function logoIMG($attributes='') {
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes>" :'';
}	

