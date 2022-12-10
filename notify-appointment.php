<?  //notify-appointment.php


$newPackage = 0;
$offerConfirmationLink = 1;
$preferenceFields = array('autoEmailApptChanges'=>true);

/*
[R] packageid
[R] clientid
[R] newPackage - boolean
[R] requestConfirmation - boolean
[*] offerConfirmationLink - whether to offer confirmation link
[*] confirmationoptional
[*] appointmentid - if this regards an individual appt
[*] apptstatus -  cancellation/reactivation of appt, if applicable

[*] preferenceFields - array(key=>permissableValue,  key=>permissableValue,  )
[*] offerregistration 
[*] action - null=startup, register=try to register a user, proceed=perform notification
[*] confirmationRequested - to be sent to recipient
[*] confirmationlink - to be sent to recipient
[*] confirmationRequestText - natural language request for a replay containing "##ConfirmationURL##"
[*] subject - message subject
[*] message - text possibly with tokens:
				##FullName## => $corrName,
				##FirstName## => $correspondent['fname'],
				##LastName## => $correspondent['lname'],
				##Sitter## => $corrName,
				##BizName## => $_SESSION['bizname']
				... and ##ConfirmationRequestText## with ##ConfirmationURL##
[nope] recurring
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
extract(extractVars('clientid,packageid,newPackage,requestConfirmation,offerConfirmationLink,appointmentid,apptstatus', $_REQUEST));

$auxiliaryWindow = true; // prevent login from appearing here if session times out
locked('o-');

$confirmationlink = $offerConfirmationLink ? 'confirm-schedule.php?token=' : null;
$confirmationoptional = true;
$offerregistration = true;
$confirmationRequestText = "Please <a href='##ConfirmationURL##'>Click here</a> to confirm the schedule or request changes.";

$templateLabel = $newPackage ? '#STANDARD - New Schedule Notification' : (
									$appointmentid ? '#STANDARD - Visit Change Notification' : 
									'#STANDARD - Schedule Change Notification');
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$templateLabel' LIMIT 1");

if(!$template) {
	$subject = 'Pet Care Services Update';

	$insert = $newPackage 
		? 'We have set up the following service schedule for you.  Please review the visit and schedule details for correctness.' 
		: ($appointmentid 
				? 'We have made changes to a scheduled visit.  Please review the visit and schedule details for correctness.'
				: 'We have made changes to your service schedule.  Please review the schedule for correctness.');
	$message = "Dear ##FullName##,\n\n$insert\n\n##ConfirmationRequestText##\n\n"
			."Sincerely,\n\n##BizName##";
}
else {
	$subject = $template['subject'];
	$message = $template['body'];
}

		
require_once "appointment-calendar-fns.php";
require_once "service-fns.php";
require_once "client-fns.php";
require_once "preference-fns.php";

$history = findPackageIdHistory($packageid, $clientid, false);

$history[] = $packageid;
$history = join(',', $history);
$package = getPackage($packageid);
$recurring = isset($package['monthly']);;
if($recurring) {
	$start = strtotime($package['effectivedate'] ? $package['effectivedate'] : $package['startdate']);
	$start = max($start, strtotime(date('Y-m-d')));
	$end = strtotime($package['cancellationdate'] ? $package['cancellationdate'] : date('Y-m-d', $start+(60 * 60 * 24 * 14)));
	$start = date('Y-m-d', $start);
	$appts = fetchAssociations(
		"SELECT * 
			FROM tblappointment 
			WHERE packageptr IN ($history) 
			AND date >= '$start' AND date <= '$end'
			ORDER BY date, starttime");
}
else {
	$appts = fetchAssociations("SELECT * FROM tblappointment WHERE packageptr IN ($history) ORDER BY date, starttime");
}

$oldApplyValue = $applySitterNameConstraintsInThisContext;
$applySitterNameConstraintsInThisContext = true;

ob_start();
ob_implicit_flush(0);
dumpCalendarLooks(100, 'lightblue');
appointmentTable($appts, $packageDetails = null, $editable=false, $allowSurchargeEdit=false, $showStats=true, $includeApptLinks=false);
$contents = ob_get_contents();
ob_end_clean();

$appointmentDescription = '';
if($appointmentid) {
	require_once "appointment-fns.php";
	$appointmentDescription = appointmentDescriptionHTML($appointmentid, $package);
	if($apptstatus) {
		$color = $apptstatus == 'CANCELED' ? 'red' : 'green';
		$apptstatus = "<p style='font-weight:bold;font-size: 1.2em;color: $color;text-align:center;'>$apptstatus</p><p>";
	}
	
	$appointmentDescription = "\n<div style='background:white;width:500px;border: solid black 1px;padding:5px;'>$apptstatus<b>Visit Description:</b><p>$appointmentDescription</div>\n<p>";
}

$applySitterNameConstraintsInThisContext = $oldApplyValue;

$packageDescription = "$appointmentDescription\n<div style='background:white;width:500px;border: solid black 1px;padding:5px;'><b>Schedule Description:</b><p>".packageDescriptionHTML($package)."</div>\n";
$messageAppendix = htmlentities('<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
  <link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" />'
						."\n<p style='text-align:center'>".$packageDescription.'</p><p>'.$contents);
$preferenceFields = $preferenceFields ? $preferenceFields : array('autoEmailScheduleChanges'=>true);
$prefs = getClientPreferences($clientid);
if($newPackage) $requestConfirmation = $prefs['confirmNewSchedules'];
else $requestConfirmation = $prefs['confirmApptModifications'] ? 1 : 0;

$htmlMessage = 1;

if(mattOnlyTEST()) {
	$newtemplates[substr($template['label'], strlen('#STANDARD - '))] = $template['templateid'];
	$templates = fetchKeyValuePairs(
		"SELECT templateid, label FROM tblemailtemplate 
		WHERE targettype = 'client' 
			AND substring(label, 1, 1) != '#'", 1);
	$template = $template['templateid'];;

	foreach($templates as $k => $v)
		$newtemplates[$v] = $k;
	$templates = $newtemplates;
}

include "user-notify.php";