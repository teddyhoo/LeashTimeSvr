<? // biz-questionnaire-wizard.php
require_once "common/init_session.php";
require_once "wizard-fns.php";

//require_once "preference-fns.php";
require_once "encryption.php";

$lockChecked = true;

$wizardFile = 'biz-questionnaire.wizardV2';

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
	else if($postedPageId == 'surcharges') postSurchargeTypes();
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
		if(strpos($k, 'surchargeTypes_') === 0) continue;
		else if($k == 'surcharges') $requestNote .= "<$key>$val</$key>";
		else if($val) $requestNote .= "<$key><![CDATA[{$val}]]></$key>";
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
	if(!$setup['bizName'] 
		|| !"{$setup['owner_fname']}{$setup['owner_lname']}" 
		|| $requestNote == '<setup></setup>') { // <setup></setup> does not always work!
		// "Ghost" request
		$request['resolved'] = 1;
	}
	foreach(explode(',', 'street1,street2,city,state,zip') as $k) $request[$k] = $setup[$k];
	$request['requesttype'] = 'BizSetup';
//print_r($request);return;	
	// login as LeashTime Clients user
	include "common/init_db_common.php";
	require_once "request-fns.php";
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
	saveNewClientRequest($request, !$request['resolved']);
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

function postSurchargeTypes() {
/*
		$source["surchargeTypes_$holNum"."_label"] = $label;
		$source["surchargeTypes_$holNum"."_date"] = $date;
		$source["surchargeTypes_$holNum"."_automatic"] = 'Y';
		$source["surchargeTypes_$holNum"."_pervisit"] = 'Y';
		$source["surchargeTypes_$holNum"."_defaultrate"] = '0.00';
		$source["surchargeTypes_$holNum"."_defaultcharge"] = '0.00';
		$source["surchargeTypes_$holNum"."_permanent"] = 1;
		$source["surchargeTypes_$holNum"."_menuorder"] = $menuorder;
*/
$types = array();
	foreach(explode(',', 
		"surchargeTypes_1_sat,surchargeTypes_1_sun,surchargeTypes_1_defaultcharge,surchargeTypes_1_defaultrate,surchargeTypes_1_automatic,surchargeTypes_1_pervisit"
		.",surchargeTypes_2_after,surchargeTypes_2_defaultcharge,surchargeTypes_2_defaultrate,surchargeTypes_2_automatic,surchargeTypes_2_pervisit"
		.",surchargeTypes_3_before,surchargeTypes_3_defaultcharge,surchargeTypes_3_defaultrate,surchargeTypes_3_automatic,surchargeTypes_3_pervisit"
	) 
		as $k) setPreference($k, $_POST[$k]);
//print_r($_POST); exit;	
	// weekend
	$surch = array();
	foreach(explode(',', "defaultcharge,defaultrate,automatic,pervisit") as $k) {
		setPreference("surchargeTypes_1_{$k}", $_POST["surchargeTypes_1_{$k}"]);		
		$surch[] = "$k='{$_POST["surchargeTypes_1_$k"]}'";
	}
	$surch[] = "filterspec=weekend_".($_POST["surchargeTypes_1_sat"] ? Sa : '').($_POST["surchargeTypes_1_sun"] ? Su : '');
	$types[] = "<surch ".join(' ', $surch).">Weekend</surch>";

	// late night
	$surch = array();
	foreach(explode(',', "defaultcharge,defaultrate,automatic,pervisit") as $k) {
		setPreference("surchargeTypes_2_{$k}", $_POST["surchargeTypes_2_{$k}"]);	
		$surch[] = "$k='{$_POST["surchargeTypes_2_$k"]}'";
	}
	$surch[] = "filterspec=after_".$_POST["surchargeTypes_2_after"];
	$types[] = "<surch ".join(' ', $surch).">Late Night</surch>";
	
	// early morning
	$surch = array();
	foreach(explode(',', "defaultcharge,defaultrate,automatic,pervisit") as $k) {
		setPreference("surchargeTypes_3_{$k}", $_POST["surchargeTypes_3_{$k}"]);	
		$surch[] = "$k='{$_POST["surchargeTypes_3_$k"]}'";
	}
	$surch[] = "filterspec=after_".$_POST["surchargeTypes_3_before"];
	$types[] = "<surch ".join(' ', $surch).">Early Morning</surch>";
	foreach($_POST as $k => $v) {
		if(strpos($k, 'surchargeTypes_') === 0) {
			if(strpos($k, 'label')) {
				$parts = explode('_', $k);
				$num = $parts[1];
				if($num < 4 || !$v) continue;
				$cols = explode(',', 'date,automatic,pervisit,defaultrate,defaultcharge,permanent,menuorder');
				$surch = array();
				setPreference("surchargeTypes_{$num}_label", $_POST["surchargeTypes_{$num}_label"]);
				foreach($cols as $col) {
					$surch[] =  "$col='{$_POST["surchargeTypes_{$num}_{$col}"]}'";
					setPreference("surchargeTypes_{$num}_{$col}", $_POST["surchargeTypes_{$num}_{$col}"]);
				}
				$types[] = "<surch ".join(' ', $surch)."><![CDATA[$v]]></surch>";
			}
		}
	}
	if($types) setPreference('surcharges', "<surcharges>".join('', $types)."</surcharges>");
//print_r($_SESSION['preferences']['surcharges']);exit;
}

function postCurrentSolutionInfo() {
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
		$json[$k] = $_POST[$k];
		setPreference($k, $_POST[$k]);
	}
	setPreference('bizAddress', join(' | ', $address));
	setPreference('bizAddressJSON', json_encode($json));

	if($_POST['country'] != getPreference('country')) {
		$_SESSION['surcharges-initialized'] = null;
	}
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

$source = &$_SESSION['preferences'];
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

if($thisWizardPage == 'surcharges' && !$_SESSION['surcharges-initialized']) {
	// intialize surcharge types
	for($surchIndex=1; $surchIndex<4; $surchIndex++) {
		$source["surchargeTypes_{$surchIndex}_automatic"] = 'N';
		$source["surchargeTypes_{$surchIndex}_pervisit"] = 'N';
		$source["surchargeTypes_{$surchIndex}_defaultrate"] = '0.00';
		$source["surchargeTypes_{$surchIndex}_defaultcharge"] = '0.00';
	}
	$source["surchargeTypes_1_sat"] = 'Y';
	$source["surchargeTypes_1_sun"] = 'Y';
	$source["surchargeTypes_2_after"] = '19:00';
	$source["surchargeTypes_3_before"] = '07:00';
	
	
	$i18n = getI18NProperties($source['country']);
	$holidays = $i18n['Holidays'];
//echo "($thisWizardPage) {$source['country']}: [".print_r($holidays, 1)."]";	
	$menuorder = 3;
	foreach($holidays as $label => $dates) {
		$dates = explode(',', $dates);
		foreach($dates as $d) if(substr($d, 0, 4) == date('Y')) $date = $d;
		$date = date($i18n['shortdateformat'], strtotime(($date ? $date : $d)));
		$source["surchargeTypes_{$surchIndex}_label"] = $label;
		$source["surchargeTypes_{$surchIndex}_date"] = $date;
		$source["surchargeTypes_{$surchIndex}_automatic"] = 'Y';
		$source["surchargeTypes_{$surchIndex}_pervisit"] = 'Y';
		$source["surchargeTypes_{$surchIndex}_defaultrate"] = '0.00';
		$source["surchargeTypes_{$surchIndex}_defaultcharge"] = '0.00';
		$source["surchargeTypes_{$surchIndex}_permanent"] = 1;
		$source["surchargeTypes_{$surchIndex}_menuorder"] = $menuorder;
		$surchIndex += 1;
		$menuorder++;
	}
	$_SESSION['surcharges-initialized'] = true;

//echo "($thisWizardPage) {$source['country']}: ".print_r($_SESSION,1);	
	
}
$sections = getSections($wizardFile);
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

if(!$allDone) display($wizardFile, $thisWizardPage, $source, $error);
// ##################################################

include "frame-end.html";

if($allDone) {
	session_unset();
  session_destroy();
}	

function setPreference($key, $val) {
	$_SESSION['preferences'][$key] = $val;
}

function unsetPreference($key) {
	unset($_SESSION['preferences'][$key]);
}

function getPreference($key) {
	return $_SESSION['preferences'][$key];
}

function validateSavedData() {
	require_once "gui-fns.php";
	$required = 'bizName|Business Name||street1|Address||city|City||state|State||country|Country'
							.'||bizPhone|Business Phone||bizEmail|Business Email||timeZone|Time Zone'
							.'||owner_fname|Owner First Name||owner_lname|Owner Last Name||owner_loginid|Preferred Login ID||owner_email|Owner Email'
							.'||currentSolution|Your current information management solution';
							
	foreach(explodePairsLine($required) as $field => $label)
		if(!$_SESSION['preferences'][$field])
			$problems[] = "$label is required.";
	if($_SESSION['preferences']['currentSolution'] == 'other' 
			&& !$_SESSION['preferences']['currentSolutionOther'])
		$problems[] = "The specific business information management solution you use currently is required.";
			
	if($problems) echo join("\n", $problems);
	else echo "OK";
}

function dumpSurchargeValidation() {
	echo <<<SURCHARGEVAL
function lineProblems(i, prefix) {
	var problems = new Array();
	// if surcharge i is new make sure the name is not already in use
	var el = document.getElementById(prefix+i+'_label');
//if(!el) alert('missing: '+prefix+'label_'+i);	
	var label = jstrim(el.value);
	if(!label) return;
  var useLabel = "["+label+"]";
	// make sure price is a float
	var price = jstrim(el = document.getElementById(prefix+'defaultcharge_'+i).value);
	if(price.length == 0) problems[problems.length] = useLabel+" must have a price.";
	else if(!isUnsignedFloat(price)) problems[problems.length] = useLabel+"'s price must be a number.";
	// if ispercentage make sure rate is a float or percentage and that rate <= 100
	var rate = jstrim(document.getElementById(prefix+'defaultrate_'+i).value);
	if(rate.length == 0) problems[problems.length] = useLabel+" must have a pay rate.";
	if(!isUnsignedFloat(price)) problems[problems.length] = useLabel+"'s rate must be a number.";
	
//el = document.getElementById(prefix+'date_'+i);
//if(!el) alert('missing: '+prefix+'date_'+i);	
	var date = jstrim(document.getElementById(prefix+'date_'+i).value);
	var filter = document.getElementById(prefix+'filter_'+i);
	if(date != -1) {
		// allow m/d, m.d, m-d
		if(!validMonthYear(date)) problems[problems.length] = useLabel+"'s date (M/D) must be a valid date for this year or next year.";
	}
	else if(filter && filter.value == 'before') {
		var time = document.getElementById(prefix+'spec1_'+i).value
		if(!time && !document.getElementById(prefix+'active_'+i).checked) ;
		else {
			time = validTime(time);
//alert('before: '+time);			
			if(!time) problems[problems.length] = useLabel+"'s time must be a valid time of day.";
			else if(time > '12:00') problems[problems.length] = useLabel+"'s time must fall between midnight and noon.";
		}
	}
	else if(filter && filter.value == 'after') {
		var time = document.getElementById(prefix+'spec1_'+i).value
		if(!time && !document.getElementById(prefix+'active_'+i).checked) ;
		else {
			time = validTime(time);
//alert('after: '+time);			
			if(!time) problems[problems.length] = useLabel+"'s time must be a valid time of day.";
			else if(time < '12:00') problems[problems.length] = useLabel+"'s time must fall between noon and midnight.";
		}
	}
	return problems;
}

function validMonthYear(monthyear) {
	monthyear = jstrim(monthyear);
	var format;
	var usregex = /^(0?[1-9]|1?[012])[- /](0?[1-9]|[12][0-9]|3[01])$/;
	var worldregex = /^(0?[1-9]|[12][0-9]|3[01])[.](0?[1-9]|1?[012])$/;
  if(usregex.test(monthyear)) format = 'US';
  else if(worldregex.test(monthyear)) format = 'WORLD';
  else return null; //doesn't match pattern, bad date
	
  var md = monthyear.split('-');
  if(md.length < 2) md = monthyear.split('-');
  if(md.length < 2) md = monthyear.split(' ');
  if(md.length < 2) md = monthyear.split('/');
  if(md.length < 2) md = monthyear.split('.');
  if(format == 'WORLD') {
		var holder = md[0];
		md[0] = md[1];
		md[1] = holder;
	}
  var thisYear = <?= date('Y') ?>;
  if(isValidDate(md[1],md[0],thisYear)) return ''+md[0]+'/'+md[1]+'/'+thisYear;
  if(isValidDate(md[1],md[0],thisYear+1)) return ''+md[0]+'/'+md[1]+'/'+(thisYear+1);
	return null;
}

function isValidDate(Day,Mn,Yr){
	var DateVal = Mn + "/" + Day + "/" + Yr;
	var dt = new Date(DateVal);
	if(dt.getDate()!=Day) return(false);
	else if(dt.getMonth()!=Mn-1) return(false);
	else if(dt.getFullYear()!=Yr) return(false);
	return(true);
}


function validTime(time) {
	time = jstrim(time);
	var regex = /^((([0]?[1-9]|1[0-2])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?( )?(AM|am|aM|Am|PM|pm|pM|Pm))|(([0]?[0-9]|1[0-9]|2[0-3])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?))$/
  if(!regex.test(time))
    return null; //doesn't match pattern, bad date
  var parts = time.split(' ');
  parts = parts[0].split(':');
  if(time.toUpperCase().indexOf('P') > -1) parts[0] = parseInt(parts[0])+12;
  if((''+parts[0]).length == 1) parts[0] = '0'+parts[0];
  return ''+parts[0]+':'+parts[1];
}


function jstrim(str) {
	return str.replace(/^\s\s*/, '').replace(/\s\s*$/, '');
}

SURCHARGEVAL;
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
	else if(page == 'currentSolutionInfo') {
		var needOther = 
			$('#currentSolution').val() == 'other' && !$('#currentSolutionOther').val()
				? 'If Other is chosen, please specify what solution was used.' : '';
		return MM_validateFormAndContinue(
			'currentSolution','','R', 
			needOther,'','MESSAGE'
			);
	}
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