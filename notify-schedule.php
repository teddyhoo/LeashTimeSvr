<?  //notify-schedule.php
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
[*] ignorePreferences - ignore any preferences that might prevent this notification from being sent
[nope] recurring
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
extract(extractVars('clientid,packageid,newPackage,requestConfirmation,offerConfirmationLink,appointmentid,apptstatus,ignorePreferences', $_REQUEST));

$auxiliaryWindow = true; // prevent login from appearing here if session times out
locked('o-');

$confirmationlink = $offerConfirmationLink ? 'confirm-schedule.php?token=' : null;
$confirmationoptional = true;
$offerregistration = true;
$confirmationRequestText = "Please <a href='##ConfirmationURL##'>Click here</a> to confirm the schedule or request changes.";

//$templateLabel = $appointmentid ? '#STANDARD- Visit Change Notification' : '#STANDARD- Schedule Change Notification';
$templateLabel = $newPackage ? '#STANDARD - New Schedule Notification' : (
									$appointmentid ? '#STANDARD - Visit Change Notification' : 
									'#STANDARD - Schedule Change Notification');
$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE label = '$templateLabel' LIMIT 1");

if(!$template) {
	$subject = 'Pet Care Services Update';

	$insert = $newPackage 
		? 'We have set up the following service schedule for you.' 
		: ($appointmentid 
				? 'We have made changes to a scheduled visit.  Please review the visit and schedule details for correctness.'
				: 'We have made changes to your service schedule.  Please review the schedule for correctness.');
	$message = "Dear ##FullName##,<p>$insert<p>##ConfirmationRequestText##<p>"
			."Sincerely,<p>##BizName##";
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
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($package); exit;}
$recurring = array_key_exists('monthly', $package);
if($recurring) {
	$start = strtotime($package['effectivedate'] ? $package['effectivedate'] : $package['startdate']);
	$start = max($start, strtotime(date('Y-m-d')));
	$end = date('Y-m-d', strtotime($package['cancellationdate'] ? $package['cancellationdate'] : date('Y-m-d', $start+(60 * 60 * 24 * 14))));
	$start = date('Y-m-d', $start);
	$appts = fetchAssociations(
		"SELECT * 
			FROM tblappointment 
			WHERE canceled IS NULL
			AND packageptr IN ($history) 
			AND date >= '$start' AND date <= '$end'
			ORDER BY date, starttime");
	$surcharges = fetchAssociations(
		"SELECT s.*, a.servicecode
			FROM tblsurcharge s
			LEFT JOIN tblappointment a ON appointmentid = appointmentptr
			WHERE s.canceled IS NULL
			AND s.packageptr IN ($history) 
			AND s.date >= '$start' AND s.date <= '$end'
			ORDER BY s.date, s.starttime");
	if(($package['monthly'])) $priceInformation['services'] = $package['totalprice'];
	else {
		$priceInformation['services'] = calculateWeeklyCharge($package);
	}
}
else {
	$appts = fetchAssociations("SELECT * FROM tblappointment WHERE canceled IS NULL AND packageptr IN ($history) ORDER BY date, starttime");
	$surcharges = fetchAssociations(
	//"SELECT * FROM tblsurcharge WHERE canceled IS NULL AND packageptr IN ($history) ORDER BY date, starttime");
	"SELECT s.*, a.servicecode
		FROM tblsurcharge s
		LEFT JOIN tblappointment a ON appointmentid = appointmentptr
		WHERE s.canceled IS NULL
		AND s.packageptr IN ($history)
		ORDER BY s.date, s.starttime");
}

ob_start();
ob_implicit_flush(0);
dumpCalendarLooks(100, 'lightblue');

require_once "tax-fns.php";
if($appts) {
	foreach($appts as $appt) {
		if(!$appt['canceled'] && !$recurring) {
			$priceInformation['services'] += $appt['charge'] + $appt['adjustment'];
			$priceInformation['tax'] += figureTaxForAppointment($appt, ($recurring ? 'R' : 'N'));;
		}
		$apptIds[] = $appt['appointmentid'];
	}
	$priceInformation['discounts'] = fetchRow0Col0("SELECT sum(amount) FROM relapptdiscount WHERE appointmentptr IN (".join(",", $apptIds).')');
}
if($surcharges) {
	foreach($surcharges as $surcharge) {
		$surchargeids[] = $surcharge['surchargeid'];
		$priceInformation['surcharges'] += $surcharge['charge'];
		$stax = round($taxRate / 100 * $surcharge['charge'], 2);
if(TRUE || mattOnlyTEST()) $stax = figureTaxForSurcharge($surcharge);
		$priceInformation['tax'] += $stax;
	}
	
	$surchargeLabels = fetchCol0("SELECT distinct label 
																FROM tblsurcharge 
																LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
																WHERE surchargeid IN (".join(',', $surchargeids).")");
	if($surchargeLabels) $surchargeLabels = " (".join(', ', $surchargeLabels).")";
}
$priceInformation['total'] = $priceInformation['services'] + $priceInformation['surcharges'] - $priceInformation['discounts'];

$per = $package['monthly'] ? '(per Month)' : ($recurring ? '(per Week)' : '');

$includeTaxLine = TRUE; // staffOnlyTEST() || dbTEST('tonkapetsitters');

$bottomLine = $priceInformation['total'];
if($includeTaxLine) {
	$bottomLine = $bottomLine + $priceInformation['tax'];
}
	

$priceInformation = "<b>Services: $per</b>".dollarAmount($priceInformation['services']).'<br>'
										."<b>Surcharges$surchargeLabels: </b>".dollarAmount($priceInformation['surcharges']).'<br>'
										.'<b>Discounts: </b>'.dollarAmount(0 - $priceInformation['discounts']).'<br>'
										.($priceInformation['tax'] && $includeTaxLine ? '<b>Tax: </b>'.dollarAmount($priceInformation['tax']).'<br>' : '')
										.'<b>Total: </b>'.dollarAmount($bottomLine).'<p>';
if($_SESSION['preferences']['bottomLineOnlyInSchedNotificPriceInfo'])
	$priceInformation = '<b>Total: </b>'.dollarAmount($bottomLine).'<p>';
//$showStats=true, $includeApptLinks=true, $surcharges=null
//if(mattOnlyTEST()) {echo "<p>Appts include 253438: ".in_array(253438, $apptIds).'<p>'.join(', ', $apptIds); exit;}
//if(mattOnlyTEST()) {echo "<p>Appts: <pre>".print_r($appts,1)."</pre>"; exit;}


$oldApplyValue = $applySitterNameConstraintsInThisContext;
$applySitterNameConstraintsInThisContext = true;
appointmentTable($appts, $packageDetails = null, $editable=false, $allowSurchargeEdit=false, $showStats=true, $includeApptLinks=false, $surcharges, ($otherItems = $_SESSION['preferences']['enableShowOtherVisitsOnScheduleUpdates']));
$applySitterNameConstraintsInThisContext = $oldApplyValue;

if(!$appts) echo "This schedule contains no visits.";

$appointmentTable = ob_get_contents();
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
// added for MobileMutts.  Add controls for this in preference-list (or somewhere) eventually.
if($_SESSION['preferences']['suppressPriceInfoInClientSchedNotifications'])
	$priceInformation = '';
$packageDescription = "$appointmentDescription\n"
											."<div style='background:white;width:500px;border: solid black 1px;padding:5px;'>"
											."<b>Schedule Description:</b><p>".packageDescriptionHTML($package, null, 'packageprice').$priceInformation."</div>\n";
//if(mattOnlyTEST()) {echo $packageDescription;exit;}

$messageAppendixToken = '#SCHEDULE#';
$messageAppendix = '<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
  <link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" />'
						."\n<p style='text-align:center'>".$packageDescription.'</p><p>'.$appointmentTable;
$preferenceFields = $preferenceFields ? $preferenceFields : array('autoEmailScheduleChanges'=>true);
$prefs = getClientPreferences($clientid);
//if($newPackage) $requestConfirmation = $prefs['confirmNewSchedules'];
//else $requestConfirmation = $prefs['confirmSchedules'] ? 1 : 0;
if($newPackage) $requestConfirmation = getClientPreference($clientid, 'confirmNewSchedules');
else $requestConfirmation = getClientPreference($clientid, 'confirmSchedules');

// OVERRIDE requestConfirmation REQUEST var
$_REQUEST['requestConfirmation'] = $requestConfirmation;
$_REQUEST['offerregistration'] = $offerregistration && $requestConfirmation;

$htmlMessage = 1;

if((dbTEST('apluspetsandplants,azcreaturecomforts') || staffOnlyTEST()) && ($package['irregular'])) {
	$found = fetchKeyValuePairs(
		"SELECT label, templateid 
			FROM tblemailtemplate 
			WHERE label IN ('#STANDARD - Schedule Change Notification', '#STANDARD - New Schedule Notification')");
	$templates = array(
		'Schedule Change Notification' => $found['#STANDARD - Schedule Change Notification'],
		'New Schedule Notification' => $found['#STANDARD - New Schedule Notification']);
		
	$otherScheduleTemplates = fetchKeyValuePairs("SELECT label,templateid 
			FROM tblemailtemplate 
			WHERE targettype = 'client'
			AND label NOT IN ('#STANDARD - Schedule Change Notification', '#STANDARD - New Schedule Notification')
			AND body LIKE '%#SCHEDULE#%'
			ORDER BY label ");
	foreach($otherScheduleTemplates  as $label => $templateid) {
		require_once "email-template-fns.php";
		$systemPrefix = getSystemPrefix($label);
		$label = substr($label, strlen($systemPrefix));
		$templates[$label] = $templateid;
	}
		
	$template = $found['#STANDARD - Schedule Change Notification'];
	ksort($templates);	
}

include "user-notify.php";
//if(staffOnlyTEST()) echo $appointmentTable;