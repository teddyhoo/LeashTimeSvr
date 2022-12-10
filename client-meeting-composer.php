<? // client-meeting-composer.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "comm-fns.php";
require_once "client-fns.php";
require_once "request-fns.php";
require_once "gui-fns.php";
require_once "email-fns.php";
require_once "invoice-gui-fns.php";
require_once "preference-fns.php";
require_once "email-template-fns.php";
if($_POST) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-'); 
extract(extractVars('startdate,timeofday,clientptr,providers,packageid', $_REQUEST));

if($providers) {
	$sitters = fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid IN ($providers)") ;
	$lastSitter = array_pop($sitters);
	$sitters = $sitters ? join(', ', $sitters)." and $lastSitter" : $lastSitter;
}

$label = mysql_real_escape_string("#STANDARD - Meeting Set Up");
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$label' LIMIT 1");
if($template) {
	$messageBody = $template['body'];
	$subject = $template['subject'];
}
//$excludeStylesheets = false;
$bizName = $_SESSION['preferences']['shortBizName'] 
						? $_SESSION['preferences']['shortBizName']
						: $_SESSION['preferences']['bizName'];
$clientid = $request['clientptr'];
if($clientptr) {
	$client = getOneClientsDetails($clientptr, array('lname','fname', 'email'));
	$recipient = $client;
	$prefs = getClientPreferences($clientptr);
}
if(strpos($messageBody, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($clientptr, false, true);
}

if(strpos($message, '#MANAGER#') !== FALSE) 
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");

$message = mailMerge($messageBody, 
													array('#RECIPIENT#' => "{$client['fname']} {$client['lname']}",
																'##FullName##' => "{$client['fname']} {$client['lname']}",
																'##FirstName##' => $client['fname'],
																'##LastName##' => $client['lname'],
																'#FIRSTNAME#' => $client['fname'],
																'#LASTNAME#' => $client['lname'],
																'#EMAIL#' => $client['email'],
																'#BIZID#' => $_SESSION["bizptr"],
																'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
																'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
																'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
																'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
																'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),																
																'#BIZNAME#' => $bizName,
																'#PETS#' => $petnames,
																'#DATE#' => longestDayAndDate(strtotime($startdate)),
																'#TIME#' => substr($timeofday, 0, strpos($timeofday, '-')),
																'#SITTERS#' => $sitters
																));	
$htmlMessage = 1;
$ignorePreferences = 1;
$client = $clientptr;
if(FALSE && $clientptr) include "user-notify.php";
else {
	$messageSubject = $subject;
	$messageBody = $message;
	$message = null; // necessary since message is a status message in comm-composer
	$lname = $recipient['lname'];
	$fname = $recipient['fname'];
	$email = $client['email'];
	$templatetype = 'other';
	include "comm-composer.php";
}
