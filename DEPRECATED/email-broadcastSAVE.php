<?// email-broadcast.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "comm-fns.php";
require_once "js-gui-fns.php";
require_once "email-fns.php";

/* displays keys held by all providers
	displays a checkbox next to each provider with an email address.
	"Send Audit" emails a message that summarizes the keys held by each provider and
	asks the provider to respond.
*/

// Determine access privs
locked('o-');
extract($_REQUEST); //filterXML,targetType,sendnow

$allowScheduleTemplatesByToken = true; ///*mattOnlyTEST() ||*/ dbTEST('auntieemspetsitting,dogslife');  // allow any schedule with #SCHEDULE# token to include a schedule

$_SESSION['preferences'] = fetchKeyValuePairs("SELECT property, value FROM tblpreference"); // TEMPORARY

$allowVisitSheetTemplatesByToken = 
	$targetType == 'provider' 
	&& (staffOnlyTEST() 
			|| $_SESSION['preferences']['enableVisitSheetEmailBrodcast']); ///*mattOnlyTEST() ||*/ dbTEST('auntieemspetsitting,dogslife');  // allow any schedule with #SCHEDULE# token to include a schedule

$rtype = $targetType == 'provider' ? 'provider' : 'client';

$altChimpMailsOption = $rtype == 'client' && (mattOnlyTEST() || dbTEST('tonkapetsitters') || dbTEST('rufusanddelilah'));

if($_SESSION['preferences']['lockEmailClientBroadcastPage']) {
	include "frame.html";
	echo "This page is unavailable.";
	include "frame-end.html";
	exit;
}

$maxEmailRecipients = 
		array_key_exists('maxEmailRecipients', $_SESSION['preferences']) 
			? $_SESSION['preferences']['maxEmailRecipients']
			: -1;
			
$ceilingNumber = 9999999;			
if($maxEmailRecipients == -1) $maxEmailRecipients = 50 ;
if($_SESSION['preferences']['emailHost']) {
	$maxEmailRecipients = $ceilingNumber;
}
if(!$maxEmailRecipients) $maxEmailRecipients = 'false';
$maxEmailsPerDay = 100;


if($preview) { // AJAX: message, template, 
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = '$preview'");
	$target = array('fname'=>'Melissa', 'lname'=>'Sitter');
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	
	echo preprocessMessage($message, 'Missy', $target, $template, $clientptr);
	exit;
}

$standardPrefix = '#STANDARD - ';

if($filterXML) {
	$filterXMLObject = new SimpleXMLElement($filterXML);
	$ids = "$filterXMLObject->ids";
}
if(!$ids || $ids == 'IGNORE') {
	$ids = $_SESSION['clientListIDString'];
	unset($_SESSION['clientListIDString']);
}
if($filterXML  && (!$ids || $ids == 'IGNORE')) $possibleTargets = array();
else if($targetType == 'provider')	{
	$targetLabel = 'Sitter';
	$ids = $filterXML  ? "WHERE providerid IN ($ids)" : "";
	$possibleTargets = fetchAssociationsKeyedBy(
		"SELECT providerid, userid, email, lname, fname, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as sendname, active
			FROM tblprovider $ids ORDER BY lname, fname", 'providerid');
	
	}
else {
	$targetLabel = 'Client';
	
	$ids = $filterXML  ? "WHERE clientid IN ($ids)" : "";
	
	$possibleTargets = fetchAssociationsKeyedBy(
		"SELECT clientid, userid, email, lname, fname, CONCAT_WS(' ', fname, lname) as sendname , active
			FROM tblclient $ids ORDER BY lname, fname", 'clientid');
}

$pageTitle = "$targetLabel Email Broadcast ";
$finalMessage = '';

if($_FILES['attachment']) {
//echo "BANG: $dir";
  if($failure = $_FILES['attachment']['error']) {
		if($failure == 1) $uploaderror = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
		else if($failure == 2) $uploaderror = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.";
		else if($failure == 3) $uploaderror = "The uploaded file was only partially uploaded.";
		else if($failure == 4) $uploaderror = ''; //"No file was uploaded.";
		else if($failure == 6) $uploaderror = "Missing a temporary folder.";
		else if($failure == 7) $uploaderror = "Failed to write file to disk.";
		else if($failure == 8) $uploaderror = "File upload stopped by extension.";
	}
	else {
		$originalName = $_FILES['attachment']['name'];
		$badExtensions = dangerousAttachmentFileExtensions();
		$extension = strtoupper(substr($originalName, strrpos($originalName, '.')+1));
		if($badExtensions[$extension]) 
			$uploaderror = "Files with extension [$extension] ({$badExtensions[$extension]}) cannot be attached. ";
		if(!$uploaderror) {
			$attachDir = "{$_SESSION['bizfiledirectory']}attachments";
			ensureAttachmentDirectory("$attachDir", ($rights = 0773)); // root needs access to read and remove the file
			$randName = realpath("$attachDir")."/att".rand(1,9999999).'_'.rand(1,9999999).'.'.$extension;
			if(file_exists($randName)) unlink($randName);
			if(!move_uploaded_file($_FILES['attachment']['tmp_name'], $randName)) {
				$uploaderror = "There was an error uploading the file. Please try again!";
			}
			else {
				chmod($randName, $rights);
				$attachments = array(array('path'=>$randName, 'file'=>$_FILES['attachment']['name']));
			}
		}
//echo "BANG: ".print_r($attachment, 1);	
	}
}

if($uploaderror) 
	$_SESSION['user_notice'] = "<h2>Attachment Upload Failed</h2><font color='red'>$uploaderror</font>";
else if($_POST && ($action == 'mailChimp' || $action == 'mailChimpWithAltEmails') && !$uploaderror) {
	$chimpmsg = "<span style='font-size:1.2em;'>To copy this list to MailChimp or another Mass Mail service:"
		."<ol><li>Close this message<li>Press Ctrl-a (Command-a on Macs) or select the entire list with the mouse<li>Press Ctrl-c (Command-c on Macs) or choose <b>Copy</b> from the edit menu<li>Paste the list into the Mass Mail (e.g., MailChimp) importer using the <b>Copy/Paste from Excel</b> option</ol>"
		."<center><input type='button' value='Close' onclick='$.fn.colorbox.close()'><p><input type='button' value='Go Back to Email Page' onclick='history.back()'>";
	echo <<<CHIMP
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>		
<script type="text/javascript">\$(document).ready(function(){\$.fn.colorbox({html:"$chimpmsg", width:"750", height:"470", scrolling: true, opacity: "0.3"});
												});</script>		
CHIMP;
	echo "<pre>";
	foreach(array_keys($_POST) as $param)
		if(strpos($param, 'recip-') === 0) {
			$recipid = substr($param, strlen('recip-'));
			$recip = fetchFirstAssoc("SELECT email, fname, lname FROM tbl$rtype WHERE {$rtype}id = $recipid LIMIT 1", 1);
			$pets = getPets($recipid);
			if($pets) $pets = "\t$pets";
			echo "{$recip['email']}\t{$recip['fname']}\t{$recip['lname']}$pets<br>";
			if($action == 'mailChimpWithAltEmails') {
				$recip = fetchFirstAssoc("SELECT email2, fname2, lname2, fname, lname FROM tbl$rtype WHERE {$rtype}id = $recipid LIMIT 1", 1);
				$pets = getPets($recipid);
				if($pets) $pets = "\t$pets";
				$names = trim("{$recip['fname2']}{$recip['lname2']}") == ''
									? "{$recip['fname']}\t{$recip['lname']}" 
									: "{$recip['fname2']}\t{$recip['lname2']}";
				if($recip['email2']) echo "{$recip['email2']}\t{$names}$pets<br>";
			}
		}
	echo "</pre>";
	exit;
}
else if($_POST && $sendnow && !$uploaderror) {
	$emailsSent = 0;
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = '{$_POST['template']}'");
if($allowScheduleTemplatesByToken) {
	$scheduleTokenFound = strpos($message, '#SCHEDULE#') !== FALSE;
	$targettype = $template['targettype'];
}
if($allowVisitSheetTemplatesByToken) {
	$visitSheetTokenFound = $rtype == 'provider' && strpos($message, '#VISITSHEET#') !== FALSE;
	$targettype = $template['targettype'];
}
	if($template['label'] == '#STANDARD - Upcoming Schedule' || ($targettype == 'client' && $scheduleTokenFound)) {
		$start = date('Y-m-d', strtotime($start));
		$end = date('Y-m-d', strtotime($end));
		$additionalFilter = idsForActiveClientsWithUpcomingVisits($start, $end);
	}
	else if($template['label'] == '#STANDARD - Upcoming Sitter Schedule' || ($targettype == 'provider' && $scheduleTokenFound)) {
		$start = date('Y-m-d', strtotime($start));
		$end = date('Y-m-d', strtotime($end));
		$additionalFilter = idsForActiveSittersWithUpcomingVisits($start, $end);
	}
	foreach(array_keys($_POST) as $param)
		if(strpos($param, 'recip-') === 0) {
			$recipid = substr($param, strlen('recip-'));
			if(isset($additionalFilter) && !in_array($recipid, $additionalFilter)) continue;
			$queuedUpMessage = queueMessage($recipid, $message, $subject, $template, $chosenClientptr, $attachments);
			if($queuedUpMessage && !is_array($queuedUpMessage)) {
				$emailsSent++;
				$emailRecipients[] = $recipid;
			}
			else $failedRecipients[] = $recipid;
		}
	$finalMessage = $emailsSent." $targetLabel message".($emailsSent == 1 ? '' : 's')." sent";
	$table = "tbl$targetType";
	$col = $targetType.'id';
	if($emailsSent) {
		$names = fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM $table WHERE $col IN (".join(',', $emailRecipients).")");
		$finalMessage .= " to: <ul><li>".join('<li>', $names).'</ul>';
		if($failedRecipients) {
			$names = fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM $table WHERE $col IN (".join(',', $failedRecipients).")");
			$finalMessage .= "<p>No message sent to: <ul><li>".join('<li>', $names).'</ul>';
		}
		logChange(-1, 'tblmessage', 'e', $note="Broadcast email to $emailsSent {$targetType}s: [$subject]");
	}
	else {
		if($failedRecipients) {
			$names = fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM $table WHERE $col IN (".join(',', $failedRecipients).")");
			$finalMessage .= "<p>No message sent to: <ul><li>".join('<li>', $names).'</ul>';
		}
		$finalMessage .= ".";
	}
}

function getPets($recipid) {
	$includeActivePets = FALSE; //mattOnlyTEST();
	if($includeActivePets) {
		require_once "pet-fns.php";
		return getClientPetNames($recipid, $inactiveAlso=false, $englishList=true);
	}
}	

function ensureAttachmentDirectory($dir, $rights=0765) {
  if(file_exists($dir)) return true;
  ensureAttachmentDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, $rights);
}



function queueMessage($recipid, $message, $subject, $template, $chosenClientptr, $attachments) {
	global $targetType;
	
	if($targetType == 'provider')	{
		$target = getProvider($recipid);
		$dearName = providerShortName(getProvider($recipid));
	}
	else {
		$target = getOneClientsDetails($recipid, array('email', 'fname', 'lname', 'userid'));
		$dearName = $target['clientname'];
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($target); exit;}	
		
	$message = preprocessMessage($message, $dearName, $target, $template, $chosenClientptr);
	if(!$message) return null;
	$hasHtml = strpos($message, '<') !== FALSE;
	
	/* KLUDGE */
	$subject = str_replace('#VISITSHEETDATE#', shortDate(strtotime($_REQUEST['visitsheetdate'])), $subject);

	return enqueueEmailNotification($target, $subject, $message, null, null, $hasHtml, null, null, $attachments);
}

function preprocessMessage($message, $dearName, $target, $template, $clientptr=null) {
	if(strpos($message, '#MANAGER#') !== FALSE) {
		$managerNickname = fetchRow0Col0(
			"SELECT value 
				FROM tbluserpref 
				WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
		$message = str_replace('#MANAGER#', ($managerNickname ? $managerNickname : $_SESSION["auth_username"]), $message);
	}
	$message = str_replace('#EMAIL#', $target['email'], $message);
	$message = str_replace('#BIZID#',  $_SESSION["bizptr"], $message);
	$message = str_replace('#BIZHOMEPAGE#', $_SESSION['preferences']['bizHomePage'], $message);
	$message = str_replace('#BIZEMAIL#', $_SESSION['preferences']['bizEmail'], $message);
	$message = str_replace('#BIZPHONE#', $_SESSION['preferences']['bizPhone'], $message);
	$message = str_replace('#BIZLOGINPAGE#', "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}", $message);


	if(strposAny($message, array('#LOGINID#', '#TEMPPASSWORD#')))
		$creds = loginCreds($target);
	$message = str_replace('#RECIPIENT#', $dearName, $message);
	$message = str_replace('#FIRSTNAME#', $target['fname'], $message);
	$message = str_replace('#LASTNAME#', $target['lname'], $message);
	$message = str_replace('#LOGO#', logoIMG(), $message);
	
	$message = str_replace('#LOGINID#', $creds['loginid'], $message);
	$message = str_replace('#TEMPPASSWORD#', $creds['temppassword'], $message);
	
	$bizName = $_SESSION['preferences']['shortBizName'] 
		? $_SESSION['preferences']['shortBizName'] 
		: $_SESSION['preferences']['bizName'];
	$message = str_replace('#BIZNAME#', $bizName, $message);
	
	if(($target['clientid'] || $clientptr) && strpos($message, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		$ownerptr = $clientptr ? $clientptr : $target['clientid'];
		$petnames = getClientPetNames($ownerptr, false, true);
		$message = str_replace('#PETS#', $petnames, $message);
	}
	
	if($target['clientid']) {
		if(strpos($message, '#OPTOUT#') !== FALSE) 
			$message = replaceOptOutToken($message, $target);
		if(strpos($message, '#CREDITCARD#') !== FALSE)
			$message = str_replace('#CREDITCARD#', ccDescription($target), $message);
	}
		
		
	//$message = str_replace('#SENDER#', $signature, $message);
	//echo $body.'<p>';
	$hasHtml = strpos($message, '<') !== FALSE;
	if($hasHtml) {
		$message = str_replace("\r", "", $message);
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	
	global $allowScheduleTemplatesByToken; if($allowScheduleTemplatesByToken) {
		$scheduleTokenFound = strpos($message, '#SCHEDULE#') !== FALSE;
		$targettype = $template['targettype'];
	}
	
	if($template['label'] == '#STANDARD - Upcoming Schedule' || ($targettype == 'client' && $scheduleTokenFound))
		$message = mergeUpcomingSchedule($message, $target, 'client');
	else if($template['label'] == '#STANDARD - Upcoming Sitter Schedule' || ($targettype == 'provider' && $scheduleTokenFound))
		$message = mergeUpcomingSchedule($message, $target, 'provider');
	if($template['label'] == '#STANDARD - Client Schedule to Sitters')
		$message = mergeInClient($message, $clientptr, 'client');
		
	global $allowVisitSheetTemplatesByToken; 
	if($allowVisitSheetTemplatesByToken) {
		$visitSheetTokenFound = strpos($message, '#VISITSHEET#') !== FALSE;
		if($visitSheetTokenFound)
			$message = mergeVisitSheet($message, $target);
			if(!$message) return null;
		}
	}
	return $message;
}


function mergeVisitSheet($message, $target) {
	global $provider, $date, $suppressVisitSheetPrintLink, $suppressContactInfoForEveryOne, $emailingVisitSheet;
	$date = date('Y-m-d', strtotime($_REQUEST['visitsheetdate']));
	$provider = $target['providerid'];
	$suppressVisitSheetPrintLink = true;
	$suppressContactInfoForEveryOne = $_SESSION['preferences']['suppresscontactinfo'];
	$emailingVisitSheet = true;
	$saveMessage = $message;  // "visit-sheets.php" stomps $message
	ob_start();
	ob_implicit_flush(0);
	include "visit-sheets-for-email.php";
	$visitSheet = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	if(!$visitSheet) return null;
}
	$message = $saveMessage;
	
	//$visitSheet = file_get_contents("https://leashtime.com/visit-sheets.php?provider={$target['providerid']}&date=$start");
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	$message = str_replace('#VISITSHEET#', $visitSheet, $message);
	$message = str_replace('#VISITSHEETDATE#', shortDate(strtotime($date)), $message);
	return $message;
}




function ccDescription($target) {
	require_once 'cc-processing-fns.php';
	if(!$target['clientid']) return "ERROR: CREDITCARD info available only for clients.";
	$cc = getClearCC($target['clientid']);
	if(!$cc) return "No credit card on record.";
	return $cc['company'].' **** **** **** '.$cc['last4'].' Exp: '.expirationDate($cc['x_exp_date']);
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

function strposAny($str, $list) {
	foreach((array)$list as $candidate)
		if(strpos($str, $candidate) !== FALSE)
			return true;
}


function replaceOptOutToken($message, $client) {
	//function generateResponseURL($bizptr, $respondent, $redirecturl, $systemlogin, $expires=null, $appendToken=false) {
	require_once 'response-token-fns.php';
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($client);exit;}
	$responseURL = generateResponseURL($_SESSION['bizptr'], $client, 
										"client-broadcast-opt-out.php?client={$client['clientid']}", true, 
										date('Y-m-d H:i:s', strtotime("+5 days")), false);

if(is_array($responseURL)) {print_r($client);exit;}

	$optOutLink = $_SESSION['preferences']['optOutLink'];
	$optOutLink = $optOutLink 
			? $optOutLink 
			: "<a href='#OPTOUT#'>click here</a>";
	$optOutLink = str_replace('#OPTOUT#', $responseURL, $optOutLink); 
	return str_replace('#OPTOUT#', $optOutLink, $message);
}
	


function mergeUpcomingSchedule($message, $target, $targettype) {
	$start = date('Y-m-d', strtotime($_REQUEST['start']));
	$end = date('Y-m-d', strtotime($_REQUEST['end']));
	$schedule = $targettype == 'client' 
		? getClientSchedule($target['clientid'],$start, $end)
		: getProviderSchedule($target['providerid'],$start, $end);
}	
		
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	$message = str_replace('#SCHEDULE#', $schedule, $message);
	return $message;
}

function mergeInClient($message, $clientptr) {
	$start = date('Y-m-d', strtotime($_REQUEST['start']));
	$end = date('Y-m-d', strtotime($_REQUEST['end']));
	$client = getClient($clientptr);
	$message = str_replace("#CLIENTNAME#", "{$client['fname']} {$client['lname']}", $message);
	$message = str_replace("#ADDRESS#", 
		oneLineAddress(array($client['street1'],$client['street2'],$client['city'],$client['state'],$client['zip'])), $message);
	if($_SESSION['preferences']['suppresscontactinfo']) $phone = '[Phone number withheld]';
	else $phone = primaryPhoneNumber($client);
	
	$message = str_replace("#PHONE#", $phone, $message);
	
	if(strpos($message, '#FLAGS#') !== FALSE) {
		require_once "client-flag-fns.php";
		$flagPanel = clientFlagPanel($clientptr, $officeOnly=true, $noEdit=true, $contentsOnly=true, $onClick=null);
		if($flagPanel) $flagPanel = str_replace("src='art", "src='".globalURL('art'), $flagPanel);
		$message = str_replace("#FLAGS#", $flagPanel, $message);
	}
	
	$message = str_replace("\r", "", $message);
	$message = str_replace("\n\n", "<p>", $message);
	$message = str_replace("\n", "<br>", $message);
	
	$message = mergeUpcomingSchedule($message, array('clientid'=>$clientptr), 'client');
	
	//$message = str_replace('#SCHEDULE#', $schedule, $message);
	return $message;
	
}

function getProviderSchedule($providerid, $start, $end) {
	require_once "prov-notification-fns.php";
	ob_start();
	ob_implicit_flush(0);
	$out = providerScheduleTableForEmail($providerid, $start, $week=null, $end);
	$out = ob_get_contents();
	ob_end_clean();
	return $out;
}

function getClientSchedule($clientid, $start, $end) {
	global 	$userRole, $suppressNoVisits,
					$applySitterNameConstraintsInThisContext; // see provider-fns.php
	$oldApplyValue = $applySitterNameConstraintsInThisContext;
	$applySitterNameConstraintsInThisContext = true;

	$appts = fetchAssociationsKeyedBy("SELECT * FROM tblappointment WHERE clientptr = $clientid AND date >= '$start' AND date <= '$end'", 'appointmentid');
	$price = figureClientPrice($appts, $clientid, $start, $end);
	foreach($appts as $appt) if(!$appt['canceled']) $uncanceledAppts[] = $appt;
	$appts = $uncanceledAppts;
	require_once "appointment-calendar-fns.php";
	ob_start();
	ob_implicit_flush(0); // 
	echo '<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/style.css" type="text/css" /> 
	<link rel="stylesheet" href="https://'.$_SERVER["HTTP_HOST"].'/pet.css" type="text/css" />
<style>
body {background:white;font-size:9pt;padding-left:5px;padding-top:5px;}
</style>'."\n";
	echo logoIMG("align='center'");
	$schedule['clientptr'] = $clientid;
	$schedule['startdate'] = $start;
	$schedule['enddate'] = date('Y-m-d', strtotime($end));
}	
}	
	
	$userRole = 'c';
	$suppressNoVisits = 1;
	dumpCalendarLooks(100, 'lightblue');
	echo "<div style='width:95%'>";
	echo "<b>Price: </b>".dollarAmount($price);
	if(!$appts) echo "No appointments found.";
	else if(getClientPreference($clientid, 'sendScheduleAsList')) {
		//require_once "appointment-calendar-fns.php";
		echo clientVisitList($appts, $forceVisitTimeInclusion=false); // moved to appointment-calendar-fns.php
	}
	else appointmentTable($appts, $schedule, $editable=false, $allowSurchargeEdit=false, $showStats=false, $includeApptLinks=false, $surcharges=null);
	echo "</div>";
	$out = ob_get_contents();
	ob_end_clean();
	$applySitterNameConstraintsInThisContext = $oldApplyValue;
	return $out;
}

function logoIMG($attributes='') {
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes>" :'';
}	

function figureClientPrice($appts, $clientid, $start, $end) {
	require_once "tax-fns.php";
	$clientTaxRates = getClientTaxRates($clientid);
	foreach($appts as $apptid => $appt) if(!$appt['canceled']) $uncanceled[] = $apptid;
	$discounts = array();
	if($uncanceled) $discounts = fetchAssociationsKeyedBy("SELECT * FROM relapptdiscount WHERE appointmentptr IN (".join(',', $uncanceled).")", 'appointmentptr');
	foreach($appts as $apptid => $appt) {
		if($appt['canceled']) continue;
//echo "($apptid) ch: {$appt['charge']} adj: {$appt['adjustment']}  discounts: ".print_r($discounts[$apptid],1)."<br>";	
		$discount = $discounts[$apptid] ? $discounts[$apptid]['amount'] : 0;
		$charge = $appt['charge']+$appt['adjustment']-$discount;
		$tax = round($charge * $clientTaxRates[$appt['servicecode']]) / 100;
		$sum += $charge + $tax;
	}
	$surchargesum = fetchRow0Col0("SELECT sum(charge) FROM tblsurcharge WHERE clientptr = $clientid AND canceled IS NULL AND date >= '$start' AND date <= '$end'");
	return $sum +$surchargesum;
}
if(staffOnlyTEST() || $db =='happyathome') $breadcrumbs = "<a href='mailing-labels.php?targetType=$targetType'>Mailing Labels</a>";

include "frame.html";
// ***************************************************************************
if($finalMessage) {
	echo $finalMessage;
	include "frame-end.html";
	exit;
}

if($targetType == 'client') {
	$partyPoopers = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property = 'optOutMassEmail' AND value = 1");
	// if optOutMassEmail is the business default, find all clients with no stated preference
	if($_SESSION['preferences']['optOutMassEmail']) {
		$goodSports = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property = 'optOutMassEmail' AND value = 0");
		if($goodSports) $partyPoopers = array_merge($partyPoopers, fetchCol0("SELECT clientid FROM tblclient WHERE clientid NOT IN (".join(',', $goodSports).")"));
	}
}
else $partyPoopers = array();

/*$sql = "SELECT tblkey.*, CONCAT_WS(' ',fname, lname) as client 
	FROM tblkey LEFT JOIN tblclient ON clientid = clientptr
	ORDER BY lname, fname";*/
	
echoButton('',"Send Email to Selected $targetLabel".'s', 'sendMsg()');
//echo '<p>';
//fauxLink('Select All', 'selectAll(1)');
echo ' - ';
fauxLink('Select All Active', 'selectAll(1, "active")');
echo ' - ';
fauxLink('Select All Inactive', 'selectAll(1, "inactive")');
echo ' - ';
fauxLink('Deselect All', 'selectAll(0)');
echo ' <div class="tiplooks" style="display:inline;padding-left:7px;padding-right:7px;" id="selectionCount"></div> ';
//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null) {

fauxLink('WARNING', 'warning()', $noEcho=false, 'Click here to read a warning.', $id=null, $class=null, 'font-size:1.2em;font-weight:bold;color:red;padding-right:7px;padding-right:7px;');
if(adequateRights('#ex')) echo ' '.fauxLink('Mass Mail List', 'mailChimp()', 1, 'Generate a list for use in a mass email campaign.');
echo '<p>';
echo "<style>.pad {padding-left: 10px;}</style>";
echo "<table>";
echo "<form name='emailform' method='POST' enctype='multipart/form-data'><tr><td valign='top'>";
hiddenElement('filterXML', $filterXML);
hiddenElement('targetType', $targetType);
hiddenElement('sendnow', 0);
hiddenElement('action', 0);
echo "<table>";
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>";
if($targetType == 'provider')	fauxLink('Filter Sitters', 'openFilter("filter-providers.php")');
else fauxLink('Filter Clients', 'openFilter("filter-clients.php")');
echo "</td></tr>";
$resultCount = $filterXMLObject->resultCount ? $filterXMLObject->resultCount : count(explode(',',$filterXMLObject->ids));
$filterDesc = $filterXML && trim($filterXMLObject->ids) ? "Current Filter: $filterXMLObject->filter<br>Found: $resultCount" : '';
echo "<tr><td colspan=4 style='padding-bottom: 5px;' id='filter'>$filterDesc</td></tr>";
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Active $targetLabel"."s</td></tr>";
listTargets($possibleTargets, 1);
echo "<tr><td colspan=4 style='font-size:1.1em;font-weight:bold'>Inactive $targetLabel"."s</td></tr>";
listTargets($possibleTargets, 0);
echo "</table></td>";

$defaultMessage = "Dear #RECIPIENT#,\n\nType message here.";
messageForm();
echo "</form></table>";


function messageForm() {
	global $targetType, $defaultMessage,	$standardPrefix, $templateOptions;
	$target = $targetType == 'provider' ? 'provider' : 'client';
	$templateOptions = fetchKeyValuePairs("SELECT label, templateid FROM tblemailtemplate WHERE targettype = '$target' AND active = 1 ORDER BY label");
	$templates = array(''=>'0');
	require_once "email-template-fns.php";
	foreach($templateOptions as $label => $id) {
		$systemPrefix = getSystemPrefix($label);
		$templates[($systemPrefix ? substr($label, strlen($systemPrefix)) : $label)] = $id;
	}
	require_once "email-template-fns.php";
	$orgTemplates = getOrganizationEmailTemplateOptions($target);
	if($orgTemplates) $templates['Shared Templates'] = $orgTemplates;
	$canSpam = <<<CANSPAM
<div style='display:block;border:solid black 1px;background:white;width:400px;height:150px;font-size:1.1em;padding:7px;'>
<img src='art/lightning-smile-small.jpg' style='float:right;clear:right;'>
To learn how to use this page effectively, how to let your clients opt out of future mass emails,
and how to stay on the right side of the CAN-SPAM (Mass Email) law, see:<p align='center'>
<a href='javascript:openConsoleWindow("canspam", "http://training.leashtime.com/MassEmailAndCAN-SPAM.pdf", 800, 600)'>
Using Mass Email in LeashTime and<br>the CAN-SPAM Law</a></div>
CANSPAM;

	echo "<td valign=top style='padding-left:20px;padding-right:20px;background:lightgrey;'>";
	echo "<span style='font-weight:bold;'>Templates:</span><br>";
	selectElement('', 'template', 0, $templates, 'templateChosen()', $labelClass=null, $inputClass=null);
	
	if(mattOnlyTEST()) {
		echo " <label for='attachment'>Attachment:</label><input type='file' name='attachment' id='attachment'>";
	}
	
	echo "<div id='startEndFields' style='display:none;'>";
	calendarSet('Starting:', 'start', $start, null, null, true, 'end');
	calendarSet('Ending:', 'end', $end, null, null, true);
	echo "</div>";
	
	echo "<div id='visitSheetDateFieldDIV' style='display:none;'>";
	calendarSet('Visit Sheet Date:', 'visitsheetdate', $visitsheetdate, null, null, true, 'end');
	echo "<div class='tiplooks'>Visit Sheet messages will not be sent to sitters with no visits.</div>";
	echo "</div>";
	
	echo "<div id='clientselector' style='display:none;'>";
	hiddenElement('chosenClientptr', '');
	global $noSearchPopMenu;
	$onMouseout = $noSearchPopMenu ? '' : "onMouseout='delayhidemenu()'";
	echo "<label $labelClass for='clienttodiscuss'>Client:</label>
					<input id='clienttodiscuss' onKeyUp='showDiscussionClientMatches(this)' $onMouseout onfocus='this.value=\"\"'>";
	echo " <span id='chosenClientName'></span><br>";
	global $noSearchPopMenu;
	if($noSearchPopMenu) {
		echo "<div id='targetclientsearchresults' style='display:none;background:white;'></div>";
	}
	echoButton('clientSchedulePreviewButton', 'Preview', 'displayPreview()');
	echo "</div>";
	echo "<p><span style='font-weight:bold;'>Subject:</span><br><input id='subject' name='subject' class='VeryLongInput' autocomplete='off'><p>
<span style='font-weight:bold;'>Message:</span><br>
<textarea rows=10 cols=60 name='message' id='message'>".safeValue($defaultMessage)."</textarea><p>
<span class='tiplooks'>All occurrances of <b>#RECIPIENT#</b> will be replaced with the recipient's name.</span>
$canSpam
</td></tr>\n";
}

function listTargets($possibleTargets, $active) {
	global $partyPoopers; // the worst kind
	$n = 0;
	foreach($possibleTargets as $id => $details) {
		if($details['active'] != $active) continue;
		$n++;
		$cbid = "recip-$id";
		$isActive = $active ? 'ISACTIVE=1' : '';
		$disabledReason = $details['email'] && !in_array($id, $partyPoopers)
											? ''
											: (!$details['email'] ? 'No email address' : 'Declines broadcast emails');
		$checkBox = $disabledReason 
								? "<input type='checkbox' disabled>" 
								: "<input name='$cbid' id='$cbid' type='checkbox' $isActive onclick='updateSelectionCount()'>";
		$label = "{$details['fname']} {$details['lname']} ".($details['nickname'] ? "({$details['nickname']})" : '');
		$loginTag = $details['userid'] 
			? "<img src='art/smiley.gif' title='User has login credentials.' height=15 width=15> " 
			: "<img src='art/notsomuch.gif' title='User has no login credentials.' height=15 width=15> ";
		echo "<tr><td>$checkBox</td><td style='font-weight:bold;' colspan=3>$loginTag<label for='$cbid'>$label - ".
						(!$disabledReason ? $details['email'] : "<i>$disabledReason</i>")."</label></td></tr>";
	}
	if(!$n) echo "<tr><td colspan=3 style='font-style:italic'>None found</td></tr>";
}	
?>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

var templateLookup = {0:''
	<? 	if($templateOptions) echo ",";
			foreach((array)$templateOptions as $label => $id) $temps[] = "$id:'".safeValue($label)."'";
			if($templateOptions) echo join(',', $temps);
	?>

};



setPrettynames('message','Message','subject','Subject','start','Starting','end','Ending', 'chosenClientptr', 'Client', 'visitsheetdate', 'Visit Sheet Date');
function selectAll(on, isactive) {
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled &&
				(!isactive ||
				  (isactive == "active" && cbs[i].getAttribute('ISACTIVE')) ||
				  (isactive == "inactive" && !cbs[i].getAttribute('ISACTIVE'))
				 )
				)
			cbs[i].checked = on ? true : false;
	updateSelectionCount();
}

function updateSelectionCount() {
	var cbs = document.getElementsByTagName('input');
	var boxcount = 0;
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled &&
				cbs[i].checked)
					boxcount++;
	document.getElementById('selectionCount').innerHTML = "Names selected: "+boxcount;
}

function mailingLabels() {
	document.location.href='mailing-labels.php?targetType=<?= $targetType ?>';
}

function mailChimp() {
	var selCount = 0;
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			if(cbs[i].checked) selCount++;
	var noSelections = '';
	if(selCount == 0) {
		alert("Please select at least one <?= $targetLabel ?> first.");
		return;
	}
	document.getElementById('action').value = 'mailChimp';
<? if($altChimpMailsOption) { ?>
	if(confirm('Click OK to include Alternate Email Addresses for the selected clients as well.')) 
		document.getElementById('action').value = 'mailChimpWithAltEmails';
<? } ?>
	document.emailform.submit();
}

function sendMsg() {
	var maxEmailRecipients = <?= $maxEmailRecipients ?>;
	if(maxEmailRecipients == false) {
		alert('This function is unavailable.  Please contact customer support.');
		return;
	}
	var selCount = 0;
	var cbs = document.getElementsByTagName('input');
	for(var i=0;i<cbs.length;i++)
		if(cbs[i].type == 'checkbox' && !cbs[i].disabled)
			if(cbs[i].checked) selCount++;
	var noSelections = '';
	if(selCount > maxEmailRecipients)
		noSelections = "No more than "+maxEmailRecipients+" messages may be sent at one time.";
	else if(selCount > 50) {
		var tedswarning = 'Warning: sending too many messages at once may prompt your email provider to block your email.';
<? if(TRUE && mattOnlyTEST()) { ?>
		if(!confirm(tedswarning)) return;
<? } ?>
	}
		
	if(selCount == 0)
		noSelections = "Please select at least one <?= $targetLabel ?> first.";
		
	var unsafeAttachment = '';
<? if(mattOnlyTEST()) { 
	echo "var danger = {";
	foreach(dangerousAttachmentFileExtensions() as $ext=>$label)
		echo  "$ext:'$label', ";
	echo "}\n";
?>

	var attachment = document.getElementById('attachment').value;
	if(attachment.lastIndexOf('.') > 0) {
		attachment = attachment.substring(attachment.lastIndexOf('.')+1).toUpperCase();
		if(attachment) {
			//alert(attachment);
			if(eval('danger.'+attachment) != undefined) 
				unsafeAttachment = 'Files with extension [attachment]	('+eval('danger.'+attachment)+') cannot be uploaded.';
		}
	}

<? } ?>
		
		
		
	var formArgs = [unsafeAttachment, '', 'MESSAGE',
									noSelections, '', 'MESSAGE',
									'message', '' , 'R',
									'subject', '', 'R'];
	var chosenTemplate = document.getElementById('template');
	chosenTemplate = templateLookup[chosenTemplate.options[chosenTemplate.selectedIndex].value];
	var isUpcomingSchedule = chosenTemplate == '#STANDARD - Upcoming Schedule' 
														|| chosenTemplate == '#STANDARD - Upcoming Sitter Schedule'
														|| chosenTemplate == '#STANDARD - Client Schedule to Sitters';
<?= !$allowScheduleTemplatesByToken ? '' : 
		"\nisUpcomingSchedule = isUpcomingSchedule || (document.getElementById('message').value.indexOf('#SCHEDULE#') != -1);"
?>
														
														
	if(isUpcomingSchedule) {
		formArgs.push('start');formArgs.push('');formArgs.push('R');
		formArgs.push('start');formArgs.push('');formArgs.push('isDate');
		//formArgs.push('start');formArgs.push('NOT');formArgs.push('isPastDate');
		formArgs.push('end');formArgs.push('');formArgs.push('R');
		formArgs.push('end');formArgs.push('NOT');formArgs.push('isDate');
		//formArgs.push('end');formArgs.push('NOT');formArgs.push('isPastDate');
	}
	
	var hasVisitSheet =  
<?= !$allowVisitSheetTemplatesByToken ? 'false;' : "document.getElementById('message').value.indexOf('#VISITSHEET#') != -1;"; ?>
	if(hasVisitSheet) {
		formArgs.push('visitsheetdate');formArgs.push('');formArgs.push('R');
		formArgs.push('visitsheetdate');formArgs.push('');formArgs.push('isDate');
	}
	
	if(MM_validateFormArgs(formArgs)) {
		if(isUpcomingSchedule && !confirm("Schedules will be sent only to the selected active <?= $targetLabel ?>s with\nvisits in the specified time frame. Continue?"))
			return;
		document.getElementById('sendnow').value = 1;
		document.emailform.submit();
	}
}

function templateChosen() {
	var id = document.getElementById('template').value;
	toggleVisitSheetDateDisplay(id);
	toggleStartEndDisplay(id);
	toggleClientChooserDisplay(id);
	if(id == 0) return;
	
<?  ?>
	ajaxGetAndCallWith('email-template-fetch.php?id='+id, updateMessage, null);
}

function toggleVisitSheetDateDisplay(id) {
	var label = templateLookup[id];
	var shouldDisplay = 
<?= !$allowVisitSheetTemplatesByToken ? 'false;' : 
		"document.getElementById('message').value.indexOf('#VISITSHEET#') != -1;"
?>
	var display =  shouldDisplay ? 'block' : 'none'; 
	document.getElementById('visitSheetDateFieldDIV').style.display = display;
}


function toggleStartEndDisplay(id) {
	var label = templateLookup[id];
	var shouldDisplay = label == '#STANDARD - Upcoming Schedule' || label == '#STANDARD - Upcoming Sitter Schedule';
<?= !$allowScheduleTemplatesByToken ? '' : 
		"\nshouldDisplay = shouldDisplay || (document.getElementById('message').value.indexOf('#SCHEDULE#') != -1);"
?>
	var display =  shouldDisplay ? 'block' : 'none'; 
	document.getElementById('startEndFields').style.display = display;
}




function toggleClientChooserDisplay(id) {
	var label = templateLookup[id];
	var display = label == '#STANDARD - Client Schedule to Sitters'  ? 'block' : 'none'; 
	document.getElementById('clientselector').style.display = display;
	if(display == 'block' ) document.getElementById('startEndFields').style.display = display;
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
		document.getElementById('message').value = nodes[0].firstChild.nodeValue;
<?= !$allowScheduleTemplatesByToken ? '' : "\ntoggleStartEndDisplay(0);" ?>
<?= !$allowVisitSheetTemplatesByToken ? '' : "\ntoggleVisitSheetDateDisplay(0)" ?>
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

function getFilter() {
	/*$result = "<root><filter>".join(' ', $filterDescription)."</filter>"
						."<ids>".join(' ', $filterDescription)."</ids>"
						."<start>$start</start>"
						."<end>$start</end>"
						."<status>$clientstatus</status>"
						."</root>"; */
	var filter = new Array('','','');
//alert(document.getElementById('filterXML'));	
	var filterXML = document.getElementById('filterXML').value;
	if(!filterXML) return filter; 
	var root = getDocumentFromXML(filterXML).documentElement;
	var nodes = root.getElementsByTagName('start') ;
	if(nodes.length == 1 && nodes[0].firstChild) filter[0] = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('end') ;
	if(nodes.length == 1 && nodes[0].firstChild) filter[1] = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('status') ;
	if(nodes.length == 1 && nodes[0].firstChild) filter[2] = nodes[0].firstChild.nodeValue;
	nodes = root.getElementsByTagName('addedOnOrAfter') ;
	if(nodes.length == 1 && nodes[0].firstChild) filter[3] = nodes[0].firstChild.nodeValue;
	return filter;
}

function openFilter(scriptName) {
	var filter = getFilter();
	var url = scriptName+'?start='+filter[0]+'&end='+filter[1]+'&status='+filter[2]+'&addedOnOrAfter='+filter[3];
	openConsoleWindow('filterwindow', url,870,600);
}

function update(aspect, data) {
	if(aspect == 'filter') {
		document.getElementById('filterXML').value = data;
		var root = getDocumentFromXML(data).documentElement;
		var nodes = root.getElementsByTagName('error');
		if(nodes.length == 1) {
			alert(data);
			return;
		}
		
		nodes = root.getElementsByTagName('filter');
		if(nodes.length == 1) {
			//var desc = 'Current Filter: '+nodes[0].firstChild.nodeValue;
			//nodes = root.getElementsByTagName('ids');
			//if(nodes.length == 1) desc += "<br>Found: "+nodes[0].firstChild.nodeValue.split(',').length;
			//document.getElementById('filter').innerHTML = desc;
			document.emailform.submit();
		}
	}
}

// CLIENT CHOOSER FUNCTIONS

function displayPreview() {
	if(!MM_validateForm(
		'start', '', 'R',
		'start', '', 'isDate',
		'end', '', 'R',
		'end', '', 'isDate',
		'chosenClientptr', '', 'R'
		)) {
		return;
	}
	var start = escape(document.getElementById('start').value);
	var end = escape(document.getElementById('end').value);
	var template = document.getElementById('template').options[document.getElementById('template').selectedIndex].value;
	var url = "email-broadcast.php?preview="+template+"&clientptr="+document.getElementById('chosenClientptr').value
						+"&start="+start+"&end="+end
						+"&message="+escape(document.getElementById('message').value);
	$.fn.colorbox({href:url, width:"750", height:"470", scrolling: true, opacity: "0.3", iframe: "true"});
}

function showDiscussionClientMatches(element, test) {
	if(element.value.length < 2) return;
	var pat = escape(element.value);
	ajaxGetAndCallWith('getSearchMatches.php?clientsOnly=1&pat='+pat, rebuildDiscussionClientMenu, element);
}

function rebuildDiscussionClientMenu(element, content) {
	if(!content) return;
	var url1 = 'javascript:pickClient(', url2=')';
	var html = '';
	var arr = content.split('||');
	for(var i = 0; i < arr.length; i++) {
		if(arr[i] == '--') html += '<hr>';
		else {
			var line = arr[i].split('|');
			<? $onFocusBlur = "onFocus='this.className=\"popitfocus\"' onBlur='this.className=\"popitmenu\"'"; ?>
			html += '<a href=\''+url1+line[0]+', "'+escape(line[1])+'"'+url2+"\' onFocus='this.className=\"popitfocus\"' onBlur='this.className=\"popitmenu\"'>"+line[1]
			+""
			+'</a>';
		}
	}
	<? 
		if($noSearchPopMenu) echo "populateTargetClientSearchResults(element, html);";
		else echo "showmenu2(element,html);";
	?>
}

function pickClient(id, name) {
	document.getElementById('chosenClientName').innerHTML = name;
	document.getElementById('chosenClientptr').value = id;
	// update message with client values and show preview button
	document.getElementById('clienttodiscuss').value = '';
	
	if(document.getElementById('targetclientsearchresults'))
		document.getElementById('targetclientsearchresults').style.display = 'none';
}

function populateTargetClientSearchResults(element, html) {
	var resultsDiv = document.getElementById('targetclientsearchresults');
	html = html.replace(/<\/a>/gi,"</a><br>");
	html = "<a href='javascript:pickClient(\"\", \"\")'>-- No Selection --</a><br>"+html;
	resultsDiv.style.display = 'block';
	resultsDiv.innerHTML = html;
}

/*

		function openBoxSearch(start) {
			var sdiv = document.getElementById('searchdiv');
			var w=window,d=document,e=d.documentElement,g=d.getElementsByTagName('body')[0],
				width=w.innerWidth||e.clientWidth||g.clientWidth,height=w.innerHeight||e.clientHeight||g.clientHeight;
			<? if($fullScreenMode && $screenIsTablet) { ?>
			sdiv.style.left= width - $('#searchdiv').width()+'px';  // had trouble making jquery offset() work..$(window).width() does work, though
			<? } ?>
			sdiv.style.display='block';
			document.getElementById('searchdiv').style.display='block';
			//$('#searchdiv').css('display:block');
			document.getElementById('searchbox').value=start;
			document.getElementById('searchbox').focus();
			document.getElementById('searchresults').innerHTML='';
		}

		function closeBoxSearch() {
			document.getElementById('searchdiv').style.display='none';
		}

*/

function showmenu2(element, which, optWidth){
	if (!document.all&&!document.getElementById)
	return
	clearhidemenu()
	menuobj=ie5? document.all.popitmenu : document.getElementById("popitmenu")
	menuobj.innerHTML=which
	menuobj.style.width=(typeof optWidth!="undefined")? optWidth : defaultMenuWidth
	menuobj.contentwidth=menuobj.offsetWidth
	menuobj.contentheight=menuobj.offsetHeight

	var elheight = element.offsetHeight;

	var position = $('#'+element.id).offset();
	//alert(position.left+', ',+position.top);
	if(document.getElementById('InnerMostFrame')) {
		position.left = position.left - $('#InnerMostFrame').offset().left;
	}
	eventX=position.left;
	eventY=position.top + menuobj.offsetHeight;

//if(confirm(window.pageYOffset+' '+eventY)) return;

	//crossobj.left = ((fixedX == -1) ? cv.offsetLeft + leftpos : fixedX)+'px';
	//crossobj.top = ((fixedY == -1) ? cv.offsetTop + toppos + extraY : fixedY)+'px';
	
	

	menuobj.style.top = eventY + elheight +'px';
	menuobj.style.left = eventX +'px';
	
	
	//Find out how close the mouse is to the corner of the window
	var windowwidth = !ie5? window.innerWidth : iecompattest().clientWidth;
	var rightedge=windowwidth-eventX
	var bottomedge=!ie5? window.innerHeight-eventY : iecompattest().clientHeight-eventY
	//if the horizontal distance isn't enough to accomodate the width of the context menu
	if (true || rightedge<menuobj.contentwidth)
		//move the horizontal position of the menu to the left by its width
		menuobj.style.left=!ie5? window.pageXOffset+eventX+element.offsetWidth-menuobj.contentwidth+"px" : 
													iecompattest().scrollLeft+eventX-menuobj.contentwidth+"px"
	else
		//position the horizontal position of the menu where the mouse was clicked
		menuobj.style.left=!ie5? window.pageXOffset+eventX+"px" : iecompattest().scrollLeft+eventX+"px"
	//same concept with the vertical position
	if (bottomedge<menuobj.contentheight)
		menuobj.style.top=!ie5? window.pageYOffset+eventY-menuobj.contentheight+"px" : iecompattest().scrollTop+eventY-menuobj.contentheight+"px"
	else
		menuobj.style.top=!ie5? window.pageYOffset+eventY+"px" : iecompattest().scrollTop+event.clientY+"px"
//alert('IE5: ['+ie5+'] NS6: ['+ns6+'] LEFT: ['+menuobj.style.left+'] top: ['+menuobj.style.top+']');


	var mobilepattern = /Alcatel|iPhone|iPod|SIE-|BlackBerry|Android|IEMobile|Obigo|Windows CE|LG\/|LG-|CLDC|Nokia|SymbianOS|PalmSource\|Pre\/|Palm webOS|SEC-SGH|SAMSUNG-SGH/i;
	
	if(navigator.userAgent.match(/iPhone|iPod|iPad/i)) {
		menuobj.style.left=(window.pageXOffset-(menuobj.contentwidth/2))+'px';
		menuobj.style.top=window.pageYOffset+'px';
	}
	else if(navigator.userAgent.match(mobilepattern)) {
		menuobj.style.left=(windowwidth-menuobj.contentwidth)+'px';
		menuobj.style.top='0px';
	}
	
	menuobj.style.visibility="visible"
	//$('.InnerMostFrame').bind('click', function() {hidemenu();});
	if(document.getElementById('InnerMostFrame')) {
		document.getElementById('InnerMostFrame').onclick=hidemenu;
	}
	return false
}

<?
$emailConstraintMessage = $maxEmailRecipients == $ceilingNumber 
	? 'There is no limit on the number of emails permitted at one time for your business.'
	: "The maximum number of emails permitted at one time is $maxEmailRecipients.";
$maxEmailsPerDayMessage = $maxEmailRecipients == $ceilingNumber 
	? 'an inordinate number of messages at once'
	: "more than $maxEmailsPerDay mass email messages per day";

$warning = "<h2>WARNING</h2>
<span style='font-size:1.3em'>
<p>
$emailConstraintMessage
<p>
We strongly recommend that you <u>do not use this page to send $maxEmailsPerDayMessage</u>,
since doing so can cause spam detection software to label your communication as spam, and can get your mailserver
blacklisted.
<p>
While this is a serious problem if you use LeashTime&apos;s email server (since it can disrupt email communication
from your own business as well as other LeashTime businesses) it is even more serious if you use your own SMTP server.
<p>
Many email providers place daily limits on the number of messages you can send.  Once you exceed that limit, they <u>stop
sending your email</u>, usually for at least 24 hours.
<p>
Please consider using a mass email marketing service such as MailChimp (<a target='mailchimp' href='http://mailchimp.com'>http://mailchimp.com</a>)
if you wish to broadcast your message to large numbers of people.  The Mass Mail List link can be used to construct a list for export to a mass mail service.
</spam>";
$warning = str_replace("\n", ' ', str_replace("\r", '', $warning));
?>

function warning() {
	$.fn.colorbox({html:"<?= $warning ?>", width:"750", height:"470", scrolling: true, opacity: "0.3"});
}



<? dumpPopCalendarJS(); ?>

</script>

<?

// ***************************************************************************

include "frame-end.html";


// ******* UPCOMING SCHEDULE SUPPORT ****************************************
function idsForActiveClientsWithUpcomingVisits($start, $end) {
	return fetchCol0("SELECT distinct clientptr 
										FROM tblappointment
										LEFT JOIN tblclient ON clientid = clientptr
										WHERE active = 1 AND date >= '$start' AND date <= '$end'");
}

function idsForActiveSittersWithUpcomingVisits($start, $end) {
	return fetchCol0("SELECT distinct providerptr 
										FROM tblappointment
										LEFT JOIN tblprovider ON providerid = providerptr
										WHERE active = 1 AND date >= '$start' AND date <= '$end'");
}


?>
