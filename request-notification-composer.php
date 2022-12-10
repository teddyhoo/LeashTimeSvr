<? // request-notification-composer.php
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

$request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = {$_REQUEST['clientrequest']} LIMIT 1");
$resolution = $request['resolution'] ? $request['resolution'] : 0;
$templates = array(0=>'#STANDARD - Request Resolved', 'honored'=>'#STANDARD - Request Honored', 'declined'=>'#STANDARD - Request Declined');
$template = $templates[$resolution];

$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$template'");
if($template) {
	$messageBody = $template['body'];
	$subject = $template['subject'];
}

/*$requestTypes = array('cancel'=>'Visit Cancellation', 'change'=>'Visit Change', 'uncancel'=>'Visit Un-Cancel', 
											'Prospect'=>'Inquiry', 
											'Profile'=>'Profile change', 'General'=>'', 'Schedule'=>'Schedule', 
											'NotificationResponse'=>'Notification Response', 'SystemNotification'=>'System Notification',
											'CCSupplied'=>'Credit Card Submission', 'Reminder'=>'Reminder');
*/


//$excludeStylesheets = false;
$bizName = $_SESSION['preferences']['bizName'] 
						? $_SESSION['preferences']['shortBizName']
						: $_SESSION['preferences']['bizName'];
$clientid = $request['clientptr'];
if(in_array($request['requesttype'], array('TimeOff', 'ICInvoice'))) {
	$extraFields = getHiddenExtraFields($request);
	if($request['requesttype'] == 'ICInvoice') $providerid = $request['providerptr'];
	else $providerid = $extraFields['providerid'];
	if($providerid) {
		//$clientid = -1;
		$recipient = fetchFirstAssoc(
			"SELECT CONCAT_WS(' ', fname, lname) as 'clientname',
							providerid, email, lname, fname
				FROM tblprovider
				WHERE providerid = $providerid
				LIMIT 1");
		$provider = $providerid; // for comm-composer.php
	}
}
else if($clientid) {
	$client = getOneClientsDetails($clientid, array('lname','fname', 'email'));
	$recipient = $client;
	$prefs = getClientPreferences($clientid);
}
else {
	$recipient = array('clientname'=>"{$request['fname']} {$request['lname']}", 'fname'=>$request['fname'], 'lname'=>$request['lname']);
}
if(strpos($messageBody, '#PETS#') !== FALSE) {
	require_once "pet-fns.php";
	$petnames = getClientPetNames($recipient['clientid'], false, true);
}

/*if(FALSE && mattOnlyTEST() && in_array($request['requesttype'], array('cancel', 'uncancel'))) {
	//-- not practical, because we would have to stash junk in the composer's HTML
	ob_start();
	ob_implicit_flush(0);
	$requestSummary = showCancellationTable($request, $uncancel=($request['requesttype'] == 'uncancel'), $noButtons=true);
	ob_end_clean();
}*/

if($request['requesttype'] == 'schedulechange') {
	require_once "request-safety.php";
	$verbs = array('cancel'=>'Cancel', 'uncancel'=>'Uncancel', 'change'=>'Change');
	$details = getHiddenExtraFields($request);
	$changetype = $details['changetype'];
	$visits = json_decode($details['visitsjson']);
	$visitCount = count($visits);
	$visits = $visitCount == 1 ? 'visit' : " $visitCount visits";
	$requestSummary = "You requested that we {$verbs[$changetype]} the following $visits.<p>";
	$requestSummary .= scheduleNotificationSummary($request);
}

if(in_array($request['requesttype'], array('cancel', 'uncancel'))) {
	//day_2012-03-05 	sole_136405
	$requestSummary = "You requested that we {$request['requesttype']} ";
	$scope = $request['scope'];
	if(strpos($scope, 'day_') === 0) 
		$requestSummary .= "all visits on ".longDayAndDate(strtotime(substr($scope, strlen('day_'))));
	else {
		$appt = 
			fetchFirstAssoc("SELECT date, timeofday FROM tblappointment WHERE appointmentid = ".substr($scope, strlen('sole_'))." LIMIT 1", 1);
		$requestSummary .= "a visit on ".longDayAndDate(strtotime($appt['date']))." at {$appt['timeofday']}";
	}
}

if($request['requesttype'] == 'change') {
	//day_2012-03-05 	sole_136405
	$requestSummary = "You requested that we change ";
	$scope = $request['scope'];
	if(strpos($scope, 'day_') === 0) 
		$requestSummary .= "all visits on ".longDayAndDate(strtotime(substr($scope, strlen('day_'))));
	else {
		$appt = 
			fetchFirstAssoc("SELECT date, timeofday FROM tblappointment WHERE appointmentid = ".substr($scope, strlen('sole_'))." LIMIT 1", 1);
		$requestSummary .= "a visit on ".longDayAndDate(strtotime($appt['date']))." at {$appt['timeofday']}";
	}
	$requestSummary .= ".<p>You wrote:<p>".(trim($request['note']) ? "<i>".trim($request['note'])."</i>" : "<i>You gave us no instructions.</i>");
}

if($request['requesttype'] == 'General') {
	//day_2012-03-05 	sole_136405
	$requestSummary = "You wrote:<p>".(trim($request['note']) ? "<i>".trim($request['note'])."</i>" : "<i>You gave us no instructions.</i>");
}

$message = mailMerge($messageBody, 
											array('#RECIPIENT#' => $recipient['clientname'],
														'#FIRSTNAME#' => $recipient['fname'],
														'#LASTNAME#' => $recipient['lname'],
														'#BIZNAME#' => $bizName,
														'#DATE#'=>longDayAndDate(strtotime($request['received'])),
														'#REQUESTTYPE#'=>$requestTypes[$request['requesttype']],
														'#REQUESTSUMMARY#'=>$requestSummary, 
														'#PETS#' => $petnames
														));
$htmlMessage = 1;
$ignorePreferences = 1;
$newtemplates = array();
if(TRUE) { // published on 5/26/2017
	$templates = fetchKeyValuePairs("SELECT templateid, label FROM tblemailtemplate WHERE label IN ('".join("','", $templates)."')", 1);
	$template = $template['templateid'];

	foreach($templates as $k => $v)
		$newtemplates[substr($v, strlen('#STANDARD - '))] = $k;
	$targetType = $request['clientptr'] ? 'client' : ($request['providerptr'] ? 'provider' : 'other');
	$extraTemplates = fetchKeyValuePairs(
		"SELECT templateid, label 
			FROM tblemailtemplate 
			WHERE
				targettype = '$targetType' AND
				templateid NOT IN (".join(',', array_keys($templates)).")
				AND (body LIKE '%#REQUESTTYPE#%' OR  body LIKE '%#REQUESTSUMMARY#%')", 1);
	if($extraTemplates) {
		$newtemplates[null] = '-------';
		foreach($extraTemplates as $k => $v)
			$newtemplates[$v] = $k;
		}
		
		
		
		
	$templates = $newtemplates;
}
$specialTemplates = $templates;

if($clientid) {
	include "user-notify.php";
}
else {
	$messageSubject = $subject;
	$messageBody = $message;
	$message = null; // necessary since message is a status message in comm-composer
	$lname = $recipient['lname'];
	$fname = $recipient['fname'];
	$email = ($request['email'] ? $request['email'] : $recipient['email']);
	include "comm-composer.php";
}
