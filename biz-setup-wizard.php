<? // biz-setup-wizard.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "wizard-fns.php";

require_once "preference-fns.php";
require_once "encryption.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

$locked = locked('o-');

$emailProperties = parse_ini_file('email-properties.txt', true);
$thisWizardPage = 'start';
if($_POST) {
	$thisWizardPage = $_POST['destination'];
	$postedPageId = $_POST['wizardpageid'];

	if($postedPageId == 'businessNameAndAddress') postBusinessNameAndAddress();
	if($postedPageId == 'businessContacts') postBusinessContacts();
	else if($postedPageId == 'scheduleDefaults') postScheduleDefaultsChanges();
	else if($postedPageId == 'emailsettings') postEmailSettings();
	else if($postedPageId == 'pettypes') postPetTypesChanges();
	else if($postedPageId == 'creditcardsettings') postCreditCardSettings();
	else if($postedPageId == 'billingsettings') postBillingSettings();
	else if($postedPageId == 'petcareserviceagreement') postPSAChanges();
	else if($postedPageId == 'clientsettings') postClientSettings();
	else if($postedPageId == 'sittersettings') postSitterSettings();
	
}

function postSitterSettings() {
	setPreference('allowProvidertoProviderEmail', $_POST['allowProvidertoProviderEmail']);
	setPreference('trackSitterToClientEmail', $_POST['trackSitterToClientEmail']);
	setPreference('sittersCanRequestVisitCancellation', $_POST['sittersCanRequestVisitCancellation']);
	setPreference('offerTimeOffProviderUI', $_POST['offerTimeOffProviderUI']);
	setPreference('scheduleDay', $_POST['scheduleDay']);
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
	//setPreference('schedulesPrepaidByDefault', $_POST['schedulesPrepaidByDefault']);
}

function postBusinessContacts() {
	setPreference('bizPhone', $_POST['bizPhone']);
	setPreference('bizFax', $_POST['bizFax']);
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
		$json[$k] = $_POST[$k];
		setPreference($k, $_POST[$k]);
	}
	setPreference('bizAddress', join(' | ', $address));
	setPreference('bizAddressJSON', json_encode($json));
}	

function postPSAChanges() {
	if($terms = $_POST['initialPSA']) {
		$terms = str_replace("\n\n", "<p>", $terms);
		$terms = str_replace("\n", "<br>", $terms);
		insertTable('tblserviceagreement', array('label'=>"Initial Petcare Status Agreement", 'terms'=>$terms, 'date'=>date('Y-m-d H:i:s'), 'html'=>1), 1);
	}
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
?>
<style>
.wizarddiv {padding: 20px;}
.wizardtitle {font:14pt arial,helvetica,sans-serif bold }
.wizardblurb {font:10pt arial,helvetica,sans-serif }
.wizardtable {width:500px;font:10pt arial,helvetica,sans-serif;}
.wizardtextinput {width:300px;}
.wizardhelpdiv {height:30px;color:darkgreen;font:italic 10pt arial,helvetica,sans-serif ;}
</style>
<?
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
// eventually, use emailProperties to set vals in the wizard
foreach($emailProperties as $host => $props) {
	if($props['smtp_host'] == $source['emailHost'])
		$source['outboundemailoption'] = $host;
}
if(!$source['outboundemailoption']) $source['outboundemailoption'] = 'leashtime';

// CANCELLATION DEADLINE
// temp_CancellationDeadline_value temp_CancellationDeadline_units 
$source['temp_CancellationDeadline_value'] = $source['cancellationDeadlineHours'];
$source['temp_CancellationDeadline_units'] = $source['cancellationDeadlineUnits'] == 'minutes' ? 'minutes' : 'hours';
if($source['cancellationDeadlineUnits'] == 'minutes')
	$source['temp_CancellationDeadline_value'] = 60 * $source['temp_CancellationDeadline_value'];
	
// PSA
if($thisWizardPage == 'petcareserviceagreement') {
	require_once "agreement-fns.php";
	$agreements = getServiceAgreements();
	if($agreements) $source['initialPSA'] = '';current($agreements);
	$agreementStatus = $agreements ? 'exists' : (getCurrentCorporateAgreement() ? 'corporate' : 'none');
}


display('biz-setup.wizard', $thisWizardPage, $source);

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function emailOptionChanged(el, noAlert) {
	var val = typeof el == 'string' ? el : el.value;
	document.getElementById('emailFromAddress').disabled = val == 'leashtime' || val == 'other';
	document.getElementById('emailPassword').disabled = val == 'leashtime' || val == 'other';
	if(noAlert) return;
	if(val == 'leashtime') alert("The email user name and password will be ignored if LeashTime is the chosen email server.");
	else if(val == 'other') alert("For other email providers, email settings must be set manually.");
}

function gatewayChanged(el, noAlert) {
	var val = typeof el == 'string' ? el : el.value;
	document.getElementById('x_login').disabled = val == 'none';
	document.getElementById('x_tran_key').disabled = val == 'none';
	
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++)
		if(els[i].name && els[i].name.indexOf('ccAcceptedList') >= 0)
			els[i].disabled = val == 'none';
	if(noAlert) return;
	if(val == 'none') alert("None of the other settings are active unless a gateway is selected.");
}


setPrettynames('taxRate','Tax Rate', 'bizEmail', 'Business Email', 'defaultReplyTo', 'Reply To Email Address', 
								'pastDueDays', 'Invoices Overdue (days)', 'temp_CancellationDeadline_value', 'Cancellation Deadline',
								'temp_CancellationDeadline_units','Hours or Minutes', 'bizHomePage', 'Business Home Page');
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
								'Please paste your initial Petcare Service Agreement in the box below,';";
	
}
?>
</script>