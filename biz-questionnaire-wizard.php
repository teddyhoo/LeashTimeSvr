<? // biz-questionnaire-wizard.php
require_once "common/init_session.php";
require_once "wizard-fns.php";

//require_once "preference-fns.php";
require_once "encryption.php";

$lockChecked = true;

if($_SESSION['auth_user_id'] || $_GET['kill']) {
	session_unset();
	session_destroy();
	$loggedOut = true;
	bootUpSession();
}

if($_GET['validate']) { // ajax
	echo validateSavedData();
	exit;
}

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$thisWizardPage = 'start';
if($_POST) {
	$thisWizardPage = $_POST['destination'];
	$postedPageId = $_POST['wizardpageid'];

	if($postedPageId == 'businessNameAndAddress') postBusinessNameAndAddress();
	else if($postedPageId == 'businessContacts') postBusinessContacts();
	else if($postedPageId == 'currentSolutionInfo') postCurrentSolutionInfo();
	else if($postedPageId == 'scheduleDefaults') postScheduleDefaultsChanges();
	else if($postedPageId == 'emailsettings') postEmailSettings();
	else if($postedPageId == 'pettypes') postPetTypesChanges();
	else if($postedPageId == 'creditcardsettings') postCreditCardSettings();
	else if($postedPageId == 'clientinfo') postClientInfoSettings();
	else if($postedPageId == 'billingsettings') postBillingSettings();
	else if($postedPageId == 'petcareserviceagreement') postPSAChanges();
	else if($postedPageId == 'clientsettings') postClientSettings();
	else if($postedPageId == 'sittersettings') postSitterSettings();
	else if($postedPageId == 'owner') postOwnerSettings();
	else if($postedPageId == 'servicetypes') postServiceTypes();
	else if($postedPageId == 'sitternames') postSitterNames();
	else if($postedPageId == 'done') setPreference('note', $_POST['note']);
	
}

function saveWizard() {
	global $error;
	// VALIDATE cached preferences here before proceeding
	setPreference('note', $_POST['note']);
	if(!TRUE) {
		$error = "The following values must be supplied before you finish.";
		return;
	}
	
	$setup = $_SESSION['preferences'];
	$requestNote =  "<setup>";
	$initialPSA = $setup['initialPSA'];
	unset($setup['initialPSA']);
	foreach($setup as $key => $val) {
		if($val) $requestNote .= "<$key><![CDATA[{$val}]]></$key>";
	}
	if($initialPSA) $requestNote .= "<initialPSA><![CDATA[{$initialPSA}]]></initialPSA>";
	$requestNote .=  "</setup>";
	//echo htmlentities($requestNote);
	$setup = $_SESSION['preferences'];
	$request = array(
		'note'=>$requestNote, 
		'fname'=>$setup['bizName'], 
		'lname'=>"({$setup['owner_fname']} {$setup['owner_lname']})",
		'phone'=>$setup['bizPhone'],
		'address'=>$setup['bizAddress'],
		'email'=>$setup['owner_email']);
	foreach(explode(',', 'street1,street2,city,state,zip') as $k) $request[$k] = $setup[$k];
	$request['requesttype'] = 'BizSetup';
//print_r($request);return;	
	// login as LeashTime Clients user
	include "common/init_db_common.php";
	require_once "request-fns.php";
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
	saveNewClientRequest($request);
}

function postServiceTypes() {
	foreach($_POST as $k => $v) 
		if(strpos($k, 'pref_serviceTypes') === 0 && $v)
			$types[] =  $v;
	if($types) setPreference('serviceTypes', join('|', $types));
}

function postSitterNames() {
	foreach($_POST as $k => $v) 
		if(strpos($k, 'pref_sitterNames') === 0 && $v)
			$types[] =  $v;
	if($types) setPreference('sitterNames', join('|', $types));
}

function postCurrentSolutionInfo() {
	setPreference('howDidYouHear', $_POST['howDidYouHear']);
	setPreference('currentSolution', $_POST['currentSolution']);
	setPreference('currentSolutionOther', $_POST['currentSolutionOther']);
	setPreference('transfer', $_POST['transfer']);
}

function postOwnerSettings() {
	setPreference('owner_fname', $_POST['owner_fname']);
	setPreference('owner_lname', $_POST['owner_lname']);
	setPreference('owner_phone', $_POST['owner_phone']); // saved in request, but not in database preferences
	setPreference('owner_email', $_POST['owner_email']);
	setPreference('owner_loginid', $_POST['owner_loginid']);
}

function postSitterSettings() {
	setPreference('allowProvidertoProviderEmail', $_POST['allowProvidertoProviderEmail']);
	setPreference('trackSitterToClientEmail', $_POST['trackSitterToClientEmail']);
	setPreference('sittersCanRequestVisitCancellation', $_POST['sittersCanRequestVisitCancellation']);
	setPreference('offerTimeOffProviderUI', $_POST['offerTimeOffProviderUI']);
	setPreference('scheduleDay', $_POST['scheduleDay']);
	setPreference('scheduleDaily', $_POST['scheduleDaily']);
	setPreference('noEmptyProviderScheduleNotification', $_POST['noEmptyProviderScheduleNotification']);
}

function postClientSettings() {
	setPreference('emergencycarepermission', $_POST['emergencycarepermission']);
	setPreference('offerClientUIAccountPage', $_POST['offerClientUIAccountPage']);
	setPreference('offerClientCreditCardMenuOption', $_POST['offerClientCreditCardMenuOption']);
	setPreference('clientCreditCardRequired', $_POST['clientCreditCardRequired']);
	setPreference('suppressTimeFrameDisplayInCLientUI', $_POST['suppressTimeFrameDisplayInCLientUI']);
	setPreference('simpleClientScheduleMaker', $_POST['simpleClientScheduleMaker']);
}

function postBillingSettings() {
	setPreference('bimonthlyBillOn1', $_POST['bimonthlyBillOn1']);
	setPreference('pastDueDays', $_POST['pastDueDays']);
	setPreference('schedulesPrepaidByDefault', $_POST['schedulesPrepaidByDefault']);
}

function postBusinessContacts() {
	setPreference('bizPhone', $_POST['bizPhone']);
	setPreference('bizFAX', $_POST['bizFAX']);
	setPreference('bizEmail', $_POST['bizEmail']);
	setPreference('bizHomePage', $_POST['bizHomePage']);
	setPreference('timeZone', $_POST['timeZone']);
}	

function postBusinessNameAndAddress() {
	setPreference('bizName', $_POST['bizName']);
	setPreference('shortBizName', $_POST['shortBizName']);
	$address = array();
	foreach(array('street1', 'street2', 'city', 'state', 'zip') as $k) {
		$address[] = $_POST[$k];
		setPreference($k, $_POST[$k]);
	}
	setPreference('bizAddress', join(' | ', $address));
	setPreference('country', $_POST['country']);
}	

function postPSAChanges() {
	setPreference('initialPSA', $_POST['initialPSA']);
	
	/*if($terms = $_POST['initialPSA']) {
		$terms = str_replace("\n\n", "<p>", $terms);
		$terms = str_replace("\n", "<br>", $terms);
		insertTable('tblserviceagreement', array('label'=>"Initial Petcare Status Agreement", 'terms'=>$terms, 'date'=>date('Y-m-d H:i:s'), 'html'=>1), 1);
	}*/
}	
	
function postPetTypesChanges() {
	$types = array();
	foreach($_POST as $k => $v) 
		if( $v && strpos($k, 'pref_petTypes') !== FALSE) $types[] = $v;
	setPreference('petTypes', join('|', $types));
	
}	
	
function postScheduleDefaultsChanges() {
	// temp_CancellationDeadline_units temp_CancellationDeadline_value
	setPreference('cancellationDeadlineUnits',  $_POST['temp_CancellationDeadline_units']);
	$hours = $_POST['temp_CancellationDeadline_value'];
	if($_POST['temp_CancellationDeadline_units'] == 'minutes') $hours = $hours * 60;
	setPreference('cancellationDeadlineHours',  $hours);
	
	$defaultTimeFrame = formatPostTime('Starting', 'defaultTimeFrame').'-'.formatPostTime('Ending', 'defaultTimeFrame');
	setPreference('defaultTimeFrame',  $defaultTimeFrame); //$_POST['defaultTimeFrame']);

	
	setPreference('newServiceTaxableDefault',  ($_POST['newServiceTaxableDefault'] ? '1' : '0'));
	setPreference('taxRate',  $_POST['taxRate']);
}

function formatPostTime($prefix, $base) {
	return $_POST[$prefix."H_$base"].':'.sprintf('%02d', $_POST[$prefix."M_$base"]).' '.$_POST[$prefix."A_$base"];
}

function postCreditCardSettings() {
	if(!$_POST['updateGatewaySettings'])  return;
	if($_POST['ccGateway'] == 'none') {
		setPreference('ccGateway',  '');
	}
	else {
		setPreference('ccGateway',  $_POST['ccGateway']);
		setPreference('x_login', $_POST['x_login'] ? lt_encrypt($_POST['x_login']) : '');
		setPreference('x_tran_key', $_POST['x_tran_key'] ? lt_encrypt($_POST['x_tran_key']) : '');
		$cards = array();
		foreach($_POST as $k => $v)
			if(strpos($k, 'ccAcceptedList_') !== FALSE) $cards[] = str_replace('_', ' ' , substr($k, strlen('ccAcceptedList_')));
		setPreference('ccAcceptedList',  join(',', $cards));
		setPreference('gatewayOfferACH',  $_POST['gatewayOfferACH'] ? 1 : 0);
	}
}

function postClientInfoSettings() {
	setPreference('acceptProspectRequests',  $_POST['acceptProspectRequests']);
	setPreference('secureClientInfo',  $_POST['secureClientInfo']);
	if($_POST['secureClientInfo']) {
		setPreference('secureClientInfoNoKeyIDsAtAll',  $_POST['secureClientInfoNoKeyIDsAtAll']);
		setPreference('secureClientInfoNoAlarmDetailsAtAll',  $_POST['secureClientInfoNoAlarmDetailsAtAll']);
	}
}

function postEmailSettings() {
	global $emailProperties;
	if(!$_POST['updateEmailSettings'])  return;
	if(in_array($_POST['outboundemailoption'], array('leashtime', 'other'))) {
		setPreference('emailHost',  '');
		setPreference('smtpPort', '');
		setPreference('smtpAuthentication',  '');
		setPreference('smtpSecureConnection',  '');
		//setPreference('emailUser',  $username);
		setPreference('emailFromAddress', '');
		//if($_POST['emailPassword']) setPreference('emailPassword',  $_POST['emailPassword']);
		}
	else {
		$domain = $_POST['outboundemailoption'];
		$vals = $emailProperties[$domain];
		setPreference('emailHost',  $vals['smtp_host'] );
		setPreference('smtpPort',  $vals['smtp_port'] );
		setPreference('smtpAuthentication',  $vals['smtp_auth'] );
		if($username = $vals['smtp_user_name']) {
			$email = $_POST['emailFromAddress'];
			$username = $username == 'emailAddress' ? $email : substr($email, 0, strpos($email, '@'));
			setPreference('emailUser',  $username);
			setPreference('emailFromAddress', $email);
			if($_POST['emailPassword']) setPreference('emailPassword',  $_POST['emailPassword']);
		}
		setPreference('smtpSecureConnection',  $vals['smpt_secure_connection'] );
	}
	setPreference('defaultReplyTo',  $_POST['defaultReplyTo'] );
}

$source = $_SESSION['preferences'];
// ADDRESS
if($source['bizAddress']) {
	$address = $source['bizAddress'] ? explode(' | ', $source['bizAddress']) : '';
	
	
	$bizAddress = $source['bizAddress'];
	if(!$bizAddress) $bizAddress = array('','','','','');
	else $bizAddress = explode(' | ', $bizAddress);
	$fields = array('street1','street2','city','state','zip');
	if(count($bizAddress) < 5) unset($fields[1]);
	if(count($bizAddress) < 4) unset($fields[0]);
	foreach(array_merge($fields) as $n => $field) {
		$source[$field] = $bizAddress[$n];
	}
	
	
	
	//foreach(array('street1', 'street2', 'city', 'state', 'zip') as $i => $field)
	//	$source[$field] = $address[$i];
}

// EMAIL
if(!$source['outboundemailoption']) $source['outboundemailoption'] = 'leashtime';

// CANCELLATION DEADLINE
// temp_CancellationDeadline_value temp_CancellationDeadline_units 
$source['temp_CancellationDeadline_value'] = $source['cancellationDeadlineHours'];
$source['temp_CancellationDeadline_units'] = $source['cancellationDeadlineUnits'] == 'minutes' ? 'minutes' : 'hours';
if($source['cancellationDeadlineUnits'] == 'minutes')
	$source['temp_CancellationDeadline_value'] = 60 * $source['temp_CancellationDeadline_value'];
	
// PSA
if($thisWizardPage == 'petcareserviceagreement') {
}
if($thisWizardPage == 'surcharges' && !$_SESSION['biz-questionnaire-wizard-initiated']) {
	// intialize surcharge types
	$country = $_SESSION['country'];
	$i18n = getI18NProperties($country);
	$holidays = $i18n['Holidays'];
	$holNum = 0;
	$menuorder = 3;
	foreach($holidays as $label => $dates) {
		$holNum += 1;
		$dates = explode(',', $dates);
		foreach($dates as $d) if(substr($d, 0, 4) == date('Y')) $date = $d;
		$date = date($i18n['shortdateformat'], ($date ? $date : $d));
		$_SESSION["surchargeTypes_$holNum"."label"] = $label;
		$_SESSION["surchargeTypes_$holNum"."date"] = $date;
		$_SESSION["surchargeTypes_$holNum"."automatic"] = 'Y';
		$_SESSION["surchargeTypes_$holNum"."pervisit"] = 'Y';
		$_SESSION["surchargeTypes_$holNum"."defaultrate"] = '0.00';
		$_SESSION["surchargeTypes_$holNum"."defaultcharge"] = '0.00';
		$_SESSION["surchargeTypes_$holNum"."permanent"] = 1;
		$_SESSION["surchargeTypes_$holNum"."menuorder"] = $menuorder;
		$menuorder++;
	}
echo "($thisWizardPage) $country: ".print_r($i18n,1);	
	
}

$wizard = TRUE ? 'biz-questionnaireV3.wizard' : 'biz-questionnaire.wizard';
$sections = getSections($wizard);
$sectionPreview = $sections[$thisWizardPage];
if(!$sectionPreview) echo "Where's [$thisWizardPage]?";
foreach($sectionPreview as $key => $value) {
	if($value && strpos($key, 'wysiwyg_') !== FALSE) {
		$theme = $value == 1 ? 'simple' : $value;
		$extraHeadContent = '
	<script type="text/javascript" src="tinymce/jscripts/tiny_mce/tiny_mce.js"></script>
	<script type="text/javascript">
		tinyMCE.init({
			mode : "textareas",
			theme : "'.$theme.'"
		});
	</script>
	';
	}
}

// ##################################################
include "frame.html";
$wizardWidth = $sectionPreview['wizardwidth'] ? $sectionPreview['wizardwidth'] : '700px';
?>
<style>
.wizarddiv {padding: 20px;}
.wizardtitle {font:14pt Palatino Linotype, Book Antiqua, Palatino, serif bold }
.wizardblurb {font:10pt arial,helvetica,sans-serif }
.wizarderror {font:10pt arial,helvetica,sans-serif; color:red }
.wizardtable {width:<?= $wizardWidth ?>;font:10pt arial,helvetica,sans-serif;}
.wizardtextinput {width:300px;}
.wizardhelpdiv {height:30px;color:darkgreen;font:italic 10pt arial,helvetica,sans-serif ;}
.ui-state-default {margin:0px;}
</style>
<?
if($_GET['save']) {
	// generate a request in LeashTime Customers
	saveWizard();
	if(!$error) {
		echo "<span class='wizardtitle'>Thank you for submitting your business information.<p>We will contact you shortly.</span>";
		$allDone = true;
	}
}

if(!$allDone) display($wizard, $thisWizardPage, $source, $error);
// ##################################################

include "frame-end.html";
$_SESSION['biz-questionnaire-wizard-initiated'] = TRUE;

if($allDone) {
	session_unset();
  session_destroy();
}	

function setPreference($key, $val) {
	$_SESSION['preferences'][$key] = $val;
}

function validateSavedData() {
	require_once "gui-fns.php";
	$required = 'bizName|Business Name||street1|Address||city|City||state|State||country|Country'
							.'||bizPhone|Business Phone||bizEmail|Business Email||timeZone|Time Zone'
							.'||owner_fname|Owner First Name||owner_lname|Owner Last Name||owner_loginid|Preferred Login ID||owner_email|Owner Email'
							; //.'||currentSolutionOther|Your current information management solution';
							
	foreach(explodePairsLine($required) as $field => $label)
		if(!$_SESSION['preferences'][$field])
			$problems[] = "$label is required.";
	//if($_SESSION['preferences']['currentSolution'] == 'other' 
	//	&& !$_SESSION['preferences']['currentSolutionOther'])
	//	$problems[] = "The specific business information management solution you use currently is required.";
			
	if($problems) echo join("\n", $problems);
	else echo "OK";
}

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function finalAct() {
	$.ajax({
	  url: "biz-questionnaire-wizard.php?validate=1"
	}).done(function(data) { 
	  if(data == 'OK') {
			document.location.href='biz-questionnaire-wizard.php?save=1';
			return true;
		}
		else alert(data);
	});
	;
}

function secureClientInfoOptionChanged(el) {
	var val = typeof el == 'string' ? el : el.value;
	document.getElementById('secureClientInfoNoKeyIDsAtAll_1').disabled = val == 0;
	document.getElementById('secureClientInfoNoKeyIDsAtAll_0').disabled = val == 0;
	document.getElementById('secureClientInfoNoAlarmDetailsAtAll_1').disabled = val == 0;
	document.getElementById('secureClientInfoNoAlarmDetailsAtAll_0').disabled = val == 0;
}

function emailOptionChanged(el, noAlert) {
	var val = typeof el == 'string' ? el : el.value;
	document.getElementById('emailFromAddress').disabled = val == 'leashtime' || val == 'other';
	if(document.getElementById('emailPassword')) document.getElementById('emailPassword').disabled = val == 'leashtime' || val == 'other';
	if(noAlert) return;
	if(val == 'leashtime') alert("The email user name and password will be ignored if LeashTime is the chosen email server.");
	else if(val == 'other') alert("For other email providers, email settings must be set manually.");
}

function gatewayChanged(el, noAlert) {
	var val = typeof el == 'string' ? el : el.value;
	if(document.getElementById('x_login')) {
		document.getElementById('x_login').disabled = (val == 'none');
		document.getElementById('x_tran_key').disabled = (val == 'none');
	}
	
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++)
		if(els[i].name && els[i].name.indexOf('ccAcceptedList') >= 0)
			els[i].disabled = (val == 'none');
	if(noAlert) return;
	if(val == 'none') alert("None of the other settings are active unless a gateway is selected.");
}


setPrettynames('taxRate','Tax Rate', 'bizEmail', 'Business Email', 'defaultReplyTo', 'Reply To Email Address', 
								'pastDueDays', 'Invoices Overdue (days)', 'temp_CancellationDeadline_value', 'Cancellation Deadline',
								'temp_CancellationDeadline_units','Hours or Minutes', 'bizHomePage', 'Business Website');
<?
for($i=1;$sectionPreview["field_$i"];$i++)
	$pairs[] = "'".$sectionPreview["field_$i"]."','"
						.($sectionPreview["prettyname_$i"] ? $sectionPreview["prettyname_$i"] : $sectionPreview["label_$i"])
						."'";
echo "setPrettynames(".join(',', $pairs).");\n"

?>
function prevalidate() {
	var page = $('#wizardpageid').val();
	if(page == 'businessNameAndAddress')
		return MM_validateFormAndContinue(
			'bizName','','R', 
			'street1','','R', 
			'city','','R', 
			'state','','R', 
			'country','','R'
			);
	/*else if(page == 'currentSolutionInfo') {
		//var needOther = 
		//	$('#currentSolution').val() == 'other' && !$('#currentSolutionOther').val()
		//		? 'If Other is chosen, please specify what solution was used.' : '';
		return MM_validateFormAndContinue(
			'currentSolutionOther','','R'//, 
			//needOther,'','MESSAGE'
			);
	}*/
	else if(page == 'businessContacts')
		return MM_validateFormAndContinue(
			'bizPhone','','R', 
			'bizEmail','','R'
			);
	else if(page == 'owner')
		return MM_validateFormAndContinue(
			'owner_fname','','R', 
			'owner_lname','','R', 
			'owner_email','','R', 
			'owner_loginid','','R', 
			'owner_phone','','R'
			);
	return true;
}

function validate() {
	return MM_validateForm(
		'taxRate','','UNSIGNEDFLOAT', 
		'bizHomePage','','isURL', 
		'bizEmail','','isEmail', 
		'defaultReplyTo','','isEmail', 
		'emailFromAddress','','isEmail', 
		'pastDueDays','','UNSIGNEDINT',
		'temp_CancellationDeadline_value','','UNSIGNEDINT' );
}



function gotoAgreementsPage() {
	parent.document.location.href = 'agreement-list.php';
}

<? 
if($thisWizardPage == 'creditcardsettings') echo "\ngatewayChanged('{$source['ccGateway']}', 'noAlert');";
else if($thisWizardPage == 'emailsettings') echo "\nemailOptionChanged('{$source['outboundemailoption']}', 'noAlert');";
else if($thisWizardPage == 'clientinfo') echo "\nsecureClientInfoOptionChanged('{$source['secureClientInfo']}');";
if($thisWizardPage == 'petcareserviceagreement') {
	$agreementPageLink = "<span style=\"color:blue;text-decoration:underline;cursor:pointer;\" onclick=\"gotoAgreementsPage()\">Service Agreements Page</span>";
	if($agreementStatus == 'corporate')
		echo "document.getElementById('wizardblurb').innerHTML = 
								'A corporate PSA already exists, but you may override it here.'
									+' Or you can view the corporate agreement in the $agreementPageLink.';";
	else if($agreementStatus == 'exists') {	
		echo "document.getElementById('wizardblurb').innerHTML = 
								'A PSA already exists.  To view it, please visit the $agreementPageLink.';
					document.getElementById('initialPSA').parentNode.parentNode.style.display = 'none';\n";
	}
	else echo "document.getElementById('wizardblurb').innerHTML = 
								'Please paste your initial Petcare Service Agreement in the box below if a signed service agreeement is required.';";
	
}
?>
</script>