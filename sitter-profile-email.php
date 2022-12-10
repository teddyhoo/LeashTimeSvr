<? // sitter-profile-email.php
/* Two Modes:
Manual 
	GET with id - open a composer to one client
	POST - to close composer and send email
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "provider-profile-fns.php";
//require_once "prov-notification-fns.php";
require_once "email-template-fns.php"; 
if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 


$standardInvoiceMessage = "Hi #FIRSTNAME#,<p>Here is the profile of your sitter, #SITTERNAME#.<p>Sincerely,<p>#BIZNAME#<p>#SITTERPROFILE#";
$standardMessageSubject = "Sitter Profile";
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '#STANDARD - Sitter Profile'");
if($template) {
	$standardInvoiceMessage = $template['body'];
	$standardMessageSubject = $template['subject'];
}
//$excludeStylesheets = false;
$bizName = $_SESSION['preferences']['bizName'] 
						? $_SESSION['preferences']['shortBizName']
						: $_SESSION['preferences']['bizName'];

if($_POST) {  // Manual mode - send email
	//print_r($_POST);
	extract($_REQUEST);
	//$properties = explodePairsLine($properties); // starting, ending
	$providerName =  fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = {$_REQUEST['profile']} LIMIT 1");
	$msgbody = plainTextToHtml($msgbody);						

	$msgbody .=  "<hr style='page-break-after:always;'>";
//'<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
//<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" /> '.
	ob_start();
	ob_implicit_flush(0);
	dumpEmailProfile($_REQUEST['profile']);
	$profile = ob_get_contents();
	ob_end_clean();
	$msgbody = str_replace('#SITTERNAME#', $providerName, $msgbody);
	$msgbody = str_replace('#SITTERPROFILE#', $profile, $msgbody);
	if(strpos($msgbody, '#LOGO#') !== FALSE) {
		$bizfiledirectory = $_SESSION["bizfiledirectory"] ? $_SESSION["bizfiledirectory"] : "bizfiles/biz_$bizptr/";
		$headerBizLogo = getHeaderBizLogo($bizfiledirectory);
		$host = $_SERVER["HTTP_HOST"] ? $_SERVER["HTTP_HOST"] : 'leashtime.com';
		$logo =  $headerBizLogo ? "<img src='https://$host/$headerBizLogo'>" :'';
		$msgbody = str_replace('#LOGO#', $logo, $msgbody);
	}
	
	$recipients = array("\"$corresname\" <$correspaddr>");
	$allSuppliedEmails[] = $correspaddr;
	if($_POST['clientemail2']) {
		$allSuppliedEmails[] = $_POST['clientemail2'];
		$recipients[] = "\"$spousename\" <{$_POST['clientemail2']}>";
		//$correspondents[] = array('tblclient', $correspid);
	}


	//$recipients = array("\"$corresname\" <$correspaddr>");
	if($error = sendEmail($recipients, $subject, $msgbody, null, 'html')) {
		echo "Mail error:<p>$error";
		exit;
	}
	
	$outboundMessage = array_merge($_POST);
	$outboundMessage['msgbody'] = $msgbody;
	$outboundMessage['correspaddr'] = join(', ', $allSuppliedEmails);
	
	//print_r($outboundMessage);exit;
	saveOutboundMessage($outboundMessage);
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('messages', null);window.close();</script>";
	exit;
}

// Manual mode - open composer
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "email-fns.php";

$clientDetails = getOneClientsDetails($_REQUEST['client'], array('fname', 'lname', 'fullname'));
$sittersWithProfiles = getProfiledSitterOptions();
if($sittersWithProfiles) {
	ob_start();
	ob_implicit_flush(0);
	$sittersWithProfiles = array_merge(array('-- Select a Sitter Profile --'=>''), $sittersWithProfiles);
	selectRow('Sitter Profile:', 'profile', $_REQUEST['profile'], $sittersWithProfiles, $onChange=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $extraTDs=null);		
	$sittersWithProfiles = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
}
else $sittersWithProfiles = "<tr><td colspan=2 class='warning'>NO SITTER PROFILES HAVE BEEN SET UP</td></tr>";

$msgbody = mailMerge($standardInvoiceMessage, 
											array('#RECIPIENT#' => $clientDetails['fullname'],
														'#FIRSTNAME#' => $clientDetails['fname'],
														'#LASTNAME#' => $clientDetails['lname'],
														'#BIZNAME#' => $bizName
														));
$client = $_GET['client'];
$messageBody = htmlToPlainText($msgbody);
$messageSubject = $standardMessageSubject;
$extraConstraints = ", 'profile', '', 'R'";
$extraPrettyNames = ", 'profile', 'Sitter Profile'";
$pageTitle = "Send Sitter Profile to #RECIPIENT#";

if($template) {
	$prettyLabel = substr($template['label'], strlen(getSystemPrefix($template['label'])));
	$specialTemplates[$prettyLabel] = $template['templateid'];
}
include "comm-composer.php";