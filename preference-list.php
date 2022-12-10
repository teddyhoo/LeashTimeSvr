<?
// preference-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "preference-fns.php";

// Determine access privs
$locked = locked('o-');

$pageTitle = "Business Preferences";
$breadcrumbs = "<a href='user-preference-list.php' title='Your Own Preferences'>Your Own Preferences</a> - ";
$breadcrumbs .= "<a href='comm-prefs.php' title='Communication Preferences for individual Clients, Sitters, and Staff'>Communication Preferences</a> ";
include "frame.html";
// ***************************************************************************
// FORMAT: key|Label|type|enumeration|Hint or space|constraints

$_SESSION["preferences"] = fetchPreferences();

$staffOnlyPreferences = array();
$helpStrings = 
"emailFromAddress|This is the email address that sends outgoing messages.  Do not supply this unless you also fill in the other fields in this section.
hideProScheduleAtTop|Hide the Pro Schedule Button at the top of the Client Sevices tab.
hideProScheduleAtBottom|Hide the Pro Schedule Button in the Short Term Schedules section of the Client Sevices tab.
hideOneDayScheduleAtTop|Hide the One Day Schedule Button at the top of the Client Sevices tab.
hideOneDayScheduleAtBottom|Hide the One Day Schedule Button in the Short Term Schedules section of the Client Sevices tab.
surchargeCollisionPolicy|Determine how the system responds when more than one automatic surcharge is applicable on the same day
secureClientInfo|When selected, omits key IDs, entry codes and alarm codes from visit sheets.
timeframeOverlapPolicy|Strict means that 8:00 am-9:00 am and 9:00 am-10:00 am overlap.  They do not when permissive is chosen.";

foreach(explode("\n", $helpStrings) as $pair) {
	$pair = explode('|', $pair);
	$help[$pair[0]] = $pair[1];
}

//$secureClientInfo = "secureClientInfo|Secure Client Info|boolean||";
$secureClientInfo = "||secureClientInfo|Secure Client Info|custom_boolean|secureClientInfo-pref-edit.php";

//foreach(getLTZones() as $label =>$zone) $timeZones[]= "$label=>$zone";
//$timeZones = join(',', $timeZones);
$staffOnlyPreferences[] = 'enforceProspectSpamDetection';
$enforceProspectSpamDetection = staffOnlyTEST() ? '||enforceProspectSpamDetection|Enforce Prospect Spam Protection|boolean' : '';
$staffOnlyPreferences[] = 'autoDeleteSpam';
$autoDeleteSpam = staffOnlyTEST() ? '||autoDeleteSpam|Auto-Delete Spam|boolean' : '';

$prefsDescription = "bizName|Business Name|string||shortBizName|Short Business Name|string"
											."||bizAddress|Business Address|custom|bizAddress-pref-edit.php"
											."||bizPhone|Business Phone|string||bizFax|Business FAX|string"
											."||bizEmail|Business Email|string||bizHomePage|Business Home Page|string"
											."||petTypes|Pet Types|list|sortable||"
											.(staffOnlyTEST() ? '||appyPetTypes|(STAFF ONLY) Change existing pet types|customlightbox|pet-counts.php|400,500'  : '')
											.(staffOnlyTEST() ? '||socialcontacts|(STAFF ONLY) Social Media Contacts|customlightbox|social-contacts-lightbox.php|600,500'  : '')
											."||timeZone|Time Zone|customlightbox|timezone-options-lightbox.php|500,320||"
											//"defaultTimeFrame|Default Visit Timeframe|string||"
											."||acceptProspectRequests|Accept Prospect Requests|boolean"
											.$enforceProspectSpamDetection
											.$autoDeleteSpam
											."||recurringScheduleWindow|Ongoing Schedule Lookahead Period (days)|maxminint|30,365"
											.$secureClientInfo
											."||fiveMinuteIntervals|Show 5 minute intervals rather than 15 minute intervals in Time Choosers|boolean"
											."||suppressLongTimeFrameWarning|Suppress the warning when a time frame is longer than four hours|boolean"
											."||timeframeOverlapPolicy|Timeframe Overlap Policy|picklist|strict,permissive";

if(TRUE || staffOnlyTEST() || $_SESSION['preferences']['enablemaxmeetingsitters']) {
	if(!$_SESSION['preferences']['enablemaxmeetingsitters'])	$staffOnlyPreferences[] = 'maxmeetingsitters';

	$prefsDescription .= "||maxmeetingsitters|Max. number of Sitters in a Meeting|picklist|2,5,10,20,30,40,50";
}
if(staffOnlyTEST()) {
	$staffOnlyPreferences[] = 'sittersPaidHourly';
	$prefsDescription .= "||sittersPaidHourly|Sitters are Paid an Hourly Rate|boolean";
}
if(TRUE || $_SESSION["preferences"]['enableStaleVisitNotifications']) {
	$prefsDescription .= "||staleVisitNotificationOptions|Overdue Visit Notification Preferences|customlightbox|stale-visit-options-lightbox.php|500,550";
}
	
if($_SESSION["preferences"]['enableschedulenoticeoptions']) {
	$prefsDescription .= "||schedulenoticestyle|Schedule Notice Style|picklist|Dates Only,Summary,Detailed";
}
	
if($_SESSION["preferences"]['enableSMS']) 
	$prefsDescription .= "||smsOptions|Text Messaging Options|customlightbox|sms-options-lightbox.php|500,550";
	
if(TRUE /*|| staffOnlyTEST() || $_SESSION['preferences']['homeSafeEnabled']*/) {
	$prefsDescription .= "||homesafeOptions|Home Safe Options|customlightbox|home-safe-options-lightbox.php|500,400";
}

$clientUIVersion = $_SESSION['preferences']['clientUIVersion'];
if(staffOnlyTEST()) $clientUI =  "clientUIVersion|Client Portal Version|picklist|Version 1,Version 2||";
$staffOnlyPreferences[] = 'clientUIVersion';

if(staffOnlyTEST() && $clientUIVersion == 'Version 2') $clientUI .=  "clientUITesters|Client UI Testers|customlightbox|client-ui-testers-lightbox.php|650,600||";
$staffOnlyPreferences[] = 'clientUITesters';

if(!$clientUIVersion || $clientUIVersion == 'Version 1') {

	$clientUI .=  "bannerTitle|Banner Title|string||bannerSubtitle|Banner Subtitle|string"
								."||showBannerText|Show Banner Text|boolean"
								."||clientagreementrequired|Service Agreement settings<br>. . . have moved to the Service Agreements page<br>. . . (click here to go)|page|agreement-list.php" // formerly custom_boolean|clientagreementrequired-pref-edit.php
								."||showClientPSA|Offer link in Account tab for client to review Service Agreement|boolean"
								."||offerClientUIAccountPage|Offer Client Account Page|boolean"
								."||hideAccountBalanceFromClient|Hide Account Balance on Client Account Page|boolean"
								."||offerClientCreditCardMenuOption|Offer Client Credit Card Menu Option|boolean"
								."||clientCreditCardRequired|Client Credit Card Is Required|boolean"
								."||clientScheduleMakerDays|Days to show in Service Schedule Maker|picklist|4,5,6"
								."||suppressTimeFrameDisplayInCLientUI|Suppress display of visit timeframes in Client calendar|boolean"
								."||simpleClientScheduleMaker|Offer simpler Visit Request form|boolean|The \"simpler\" form is just a message composer without a scheduler tool."
								."||warnOfLateScheduling|Warn clients who schedule at the last minute|custom_boolean|lastMinuteSchedule-pref-edit.php";
	if($_SESSION['preferences']["enableresponsiveclient"]) {
	$clientUI .= "||clientUICalendarOmitYear|Omit Year view option in client's calendar|boolean"
								."||clientUICalendarOmitDay|Omit Day view option in client's calendar|boolean"
								."||clientUICalendarOmitWeek|Omit Week view option in client's calendar|boolean"
								."||clientUICalendarOmitToday|Omit Today button in client's calendar|boolean"
								."||clientUICalendarOmitPets|Omit pet names in client's calendar|boolean"
								."||clientUICalendarOmitCanceledVisits|Omit canceled visits in client's calendar|boolean";
	}
	//if($_SESSION['preferences']['clientArrivalCompletionRealTimeNotification']) $clientUI .= "||showClientCompletionDetails|Allow Clients to see visit Arrival and Completion details|boolean";
	$clientUI .= "||omitPriceInfoInClientScheduleEmailLists|Omit Service Charge in Client Schedule Email|boolean";
	//$clientUI .=  "||showClientPSA|Offer link in Account tab for client to review Service Agreement|boolean";
	if(staffOnlyTEST()) $clientUI .= "||suppressMeetingFieldsInProspectForm|Suppress Meeting Fields in Prospective Client Form|boolean";
	if(staffOnlyTEST()) $clientUI .= "||suppressMeetingFieldsInGeneralRequestForm|Suppress Meeting Fields in General Request Form|boolean";
	if(staffOnlyTEST()) $clientUI .= "||suppressChangeButtonOnVisits|Suppress Change Button On Visits|boolean";
	if(staffOnlyTEST()) $clientUI .= "||clientOwnEditSubmitReminder|Remind clients to click Submit in the Profile Editor.|boolean";
	if(staffOnlyTEST()) $clientUI .= "||suppressPayNowGratuity|Suppress Gratuity options in Client's Pay Now page.|boolean";
	$clientUI .= "||enableProfileChangeRequestReminder|Remind client of pending Profile Change Requests.|boolean";
	if(staffOnlyTEST()) $clientUI .= "||suppressClientCreditCardEntry|Do not allow clients to enter credit cards.|boolean";
	if(staffOnlyTEST()) $clientUI .= "||suppressClientCheckingAccountEntry|Do not allow clients to enter E-Check Accts.|boolean";

	$staffOnlyPreferences[] = 'suppressClientCreditCardEntry';
	$staffOnlyPreferences[] = 'suppressClientCheckingAccountEntry';
	if(TRUE) $clientUI .= "||clientschedulelookaheaddays|Days to show in client's own schedule by default|picklist|15,30,45,60,75,90";
	$staffOnlyPreferences[] = 'clientschedulelookaheaddays';

	if($_SESSION['preferences']['enableOfficeDocuments']) $clientUI .= "||offerOfficeDocumentsToClients|Offer Client Documents to clients.|boolean";
	$staffOnlyPreferences[] = 'offerOfficeDocumentsToClients';

	// Offered to ALL on 2/28/2019 if($_SESSION["preferences"]['enableFlexibleProspectFormOption']) 
		$clientUI .= "||editProspectFormTemplate|Prospect Form Options|customlightbox|prospect-template-options-lightbox.php|650,600";
	//$staffOnlyPreferences[] = 'editProspectFormTemplate';

	//OPENED FOR ALL 11/30/2018:
	 if(TRUE || $_SESSION["preferences"]['enablepageopenannouncements']) // staffOnlyTEST()
		$clientUI .= "||clientSchedulerWelcomeNoticeABBREV|Client Scheduler Welcome Message|customlightbox|client-screen-notice-lightbox.php?prop=clientSchedulerWelcomeNotice&label=Client+Scheduler+Welcome+Message|790,600"
							."||clientLoginNoticeABBREV|Client Login Notice|customlightbox|client-screen-notice-lightbox.php?prop=clientLoginNotice&label=Client+Login+Notice|790,600";
	/*else if($_SESSION['preferences']['enablepageopenannouncements']) 
		$clientUI .= "||clientSchedulerWelcomeNoticeABBREV|Client Scheduler Welcome Message|customlightbox|client-page-open-lightbox.php?prop=clientSchedulerWelcomeNotice&label=Client+Scheduler+Welcome+Message|500,420"
							."||clientLoginNoticeABBREV|Client Login Notice|customlightbox|client-page-open-lightbox.php?prop=clientLoginNotice&label=Client+Login+Notice|500,420";
	*/
	if(staffOnlyTEST()) $clientUI .=  "||brandedBusinessLoginsOnly|Allow Only Logins For Business from Branded Login Page|boolean";
	$staffOnlyPreferences[] = 'brandedBusinessLoginsOnly';
	if(staffOnlyTEST()) $clientUI .=  "||suppressClientSchedulerPriceDisplay|Suppress Price Display in Client Scheduler|boolean";
	$staffOnlyPreferences[] = 'suppressClientSchedulerPriceDisplay';


	if(staffOnlyTEST() || $_SESSION['preferences']['offerClientProviderNameDisplayMode']) $clientUI .= "||clientProviderNameDisplayMode|Show client the sitter's name|picklist|full,nickname,initials,none";
	foreach(explode(',', 'suppressMeetingFieldsInProspectForm,suppressMeetingFieldsInGeneralRequestForm,suppressChangeButtonOnVisits') as $v) $staffOnlyPreferences[] = $v;
}
else if($clientUIVersion == 'Version 2') {
	$clientUI .= "||NEWCLIENTUIOPTIONS|Client Portal Options|customlightbox|client-responsive-options-lightbox.php?show=1|750,640"
							."||omitPriceInfoInClientScheduleEmailLists|Omit Service Charge in Client Schedule Email|boolean"
							."||editProspectFormTemplate|Prospect Form Options|customlightbox|prospect-template-options-lightbox.php|650,600";
	if(staffOnlyTEST()) $clientUI .= "||suppressMeetingFieldsInProspectForm|Suppress Meeting Fields in Prospective Client Form|boolean";
	if(staffOnlyTEST()) $clientUI .= "||suppressPayNowGratuity|Suppress Gratuity options in Client's Pay Now page.|boolean";
	$staffOnlyPreferences[] = 'suppressMeetingFieldsInProspectForm';
	if(staffOnlyTEST()) $clientUI .= "||suppressMeetingFieldsInGeneralRequestForm|Suppress Meeting Fields in General Request Form|boolean";
	$staffOnlyPreferences[] = 'suppressMeetingFieldsInGeneralRequestForm';
	$staffOnlyPreferences[] = 'suppressPayNowGratuity';
	if(staffOnlyTEST()) $clientUI .=  "||brandedBusinessLoginsOnly|Allow Only Logins For Business from Branded Login Page|boolean";
	$staffOnlyPreferences[] = 'brandedBusinessLoginsOnly';
	if(staffOnlyTEST() || $_SESSION['preferences']['offerClientProviderNameDisplayMode']) $clientUI .= "||clientProviderNameDisplayMode|Show client the sitter's name|picklist|full,nickname,initials,none";
	$staffOnlyPreferences[] = 'offerClientProviderNameDisplayMode';
}

$sittersCanSendICInvoices = staffOnlyTEST() ? '||sittersCanSendICInvoices|Sitters Can Send IC Compensation Invoices|boolean' : '';
$staffOnlyPreferences[] = 'sittersCanSendICInvoices';
$allSittersSeeAllSitterTimeOffOption = TRUE ?"||enableTimeoffCalendarGlobalVisibility|Allow sitters to see all Time Off|boolean" : '';

$overnightOption = "||overnightsontimeoffcalendar|Time Off Calendar shows Overnights (office only)|boolean";
//$staffOnlyPreferences[] = 'overnightsontimeoffcalendar';
$unlimitedproviderschedulelookaheadOption = staffOnlyTEST() ? "||unlimitedproviderschedulelookahead|Sitters can see clients they will serve any time in the future|boolean" : '';
if($_SESSION['preferences']['enableclientListLimitOptions']) 
	$clientListLimitOptions .= 
		"||sitterclientsvisitdaysbehind|Sitters can see clients they served up to days in the past|picklist|1,3,7,14,21,30,60,90,120|If not set, the default is 1 day."
		."||sitterclientsvisitdaysahead|Sitters can see clients they will serve up to days in the future|picklist|1,3,7,14,21,30,60,90,120|If not set, the default is 14 days.";

$staffOnlyPreferences[] = 'unlimitedproviderschedulelookahead';
if(staffOnlyTEST()) $staffOnlyPreferences[] = 'clientProviderNameDisplayMode';

$providerUI =  "allowProvidertoProviderEmail|Offer Sitter-to-Sitter Email|boolean"
								."||offerTimeOffProviderUI|Offer Time Off Editor to Sitters|boolean"
								.$allSittersSeeAllSitterTimeOffOption
								.$overnightOption
								.$clientListLimitOptions
								.$unlimitedproviderschedulelookaheadOption
								."||sittersCanRequestVisitCancellation|Sitters Can Request Visit Changes|boolean"
								."||sittersCanRequestClientProfileChanges|Sitters Can Request Client Profile Changes|boolean"
								.$sittersCanSendICInvoices
								."||trackSitterToClientEmail|Track Sitter-to-Client Email|boolean"
								."||replyToOfficeInSitterToClientEmail|In Sitter-to-Client Email, Use Office Email Address as Reply-To |boolean"
								."||chooseVisitListColumns|Choose Visit List Columns|customlightbox|prov-schedule-list-options-lightbox.php?provui=1|760,520"
								."||suppresscontactinfo|Suppress client email addresses and phone numbers|boolean";
$providerUI .= "||mobileSitterAppOptions|Mobile Sitter App Options|customlightbox|mobile-sitter-app-options-lightbox.php|500,550";
//if($_SESSION['preferences']['enableNativeSitterAppAccess']) $providerUI .= "||nativeSitterAppPrefs|Visit Report Options|customlightbox|native-sitter-preference-list.php|800,520||";
if(TRUE || $_SESSION['preferences']['enableNativeSitterAppAccess']) $providerUI .= "||nativeSitterAppPrefs|Native Mobile Sitter App|customlightbox|native-sitter-preference-list.php|800,520||";
//$emailPrefs = "emailFromAddress|Sender Email Address|custom|email-send-address-pref-edit.php||emailBCC|CC sent mail to|string||emailHost|SMTP (Outbound eMail) Host|string||".
//$emailPrefs = "emailFromAddress|Sender Email Address|string||";
//$staffOnlyPreferences[] = 'enableUnassignedVisitsBoard';
//if(staffOnlyTEST()) $providerUI .= "||enableUnassignedVisitsBoard|Enable the Unassigned Visits Board|boolean";
if(staffOnlyTEST()) $providerUI .= "||enableProviderTeamSchedule|Enable the Sitter's Team Schedule View (web app only)|boolean";
if(staffOnlyTEST()) $providerUI .= "||providerCanPrintIntakeForms|Sitters can generate Client Intake Forms|boolean";
if(staffOnlyTEST()) $providerUI .= "||suppressEmergencyContactinfo|Suppress emergency contact information|boolean";
if(staffOnlyTEST()) $providerUI .= "||hideReassignedFromNoteFromProviders|Suppress 'Reassigned from...' note in sitter view|boolean";

if(staffOnlyTEST() || $_SESSION['preferences']['enableOfficeDocuments'] || $_SESSION['preferences']['offerOfficeDocumentsToClients']) 
	$providerUI .= "||enableSitterDocuments|Enable My Documents feature in ADMIN menu|boolean";
if(staffOnlyTEST() && !$_SESSION['preferences']['enableOfficeDocuments']) 
	$staffOnlyPreferences[] = 'enableSitterDocuments';



//$staffOnlyPreferences[] = 'replyToOfficeInSitterToClientEmail';
$staffOnlyPreferences[] = 'enableProviderTeamSchedule';
$staffOnlyPreferences[] = 'providerCanPrintIntakeForms';
$staffOnlyPreferences[] = 'suppressEmergencyContactinfo';
$staffOnlyPreferences[] = 'hideReassignedFromNoteFromProviders';
$staffOnlyPreferences[] = 'clientOwnEditSubmitReminder';


require_once "email-fns.php";
$_SESSION['preferences']['smtpStatus'] = getSMTPStatus();
$emailPrefs = "emailFromAddress|Sender Email Address|custom|email-send-address-pref-edit.php||";

$emailPrefs .=					
		"defaultReplyTo|Reply to Email Address|email"
					."||emailBCC|CC sent mail to|email"
					.(mattOnlyTEST() ? "||emailHost|SMTP (Outbound eMail) Host|custom|email-smtp-host-pref-edit.php" 
														: "||emailHost|SMTP (Outbound eMail) Host|string")
					."||smtpPort|SMTP Port|string||".
					//"smtpAuthentication|Use SMTP Authentication|boolean||".
					"smtpSecureConnection|Use Secure Connection|picklist|no,tls,starttls,ssl,sslv2,sslv3||".
					"emailUser|Email User Name|string||emailPassword|Email Password|password";
if(FALSE && staffOnlyTEST()) { 
	$emailPrefs = "smtpStatus|Outbound Email Setup|custom|email-outbound-setup.php||$emailPrefs"; 
	
}
if(TRUE || mattOnlyTEST())
	$emailPrefs .= "||testSMTPSettings|Test Outgoing Email|custom|email-test-outbound-settings.php|,670,500"; 
if(staffOnlyTEST())
	$emailPrefs .= "||testEmailAddress|Test Email Address (Staff Only)|custom|email-test-address.php|,670,500"; 
if(staffOnlyTEST()) {
//		else if($group[2] == 'page') /*$val =*/ $label = fauxLink($group[1], $group[3], 1, $title);
	if($mqstart = getPreference('mailQueueSendStarted')) {
		$status = "Queue started: $mqstart (possibly stalled).";
	}
	if($mqdisabled = getPreference('mailQueueDisabled')) {
		$status = "<font color=red>Queue disabled: $mqdisabled</font>.";
	}
	$queueLength = fetchRow0Col0("SELECT count(*) FROM tblqueuedemail");
	$status = ($queueLength ? "$queueLength messages in queue.  " : 'Queue is empty.  ').$status;
	$_SESSION['preferences']['emailqueueSTATUS'] = $status;
	$emailPrefs .= "||emailqueueSTATUS|Email queue (Staff Only)|page|email-queue.php|,670,500"; 
}
											
$clientEmailPrefs =											
											"optOutMassEmail|Mass Email: Clients Opt Out by default|boolean"
											."||autoEmailCreditReceipts|Automatic Credit Card Transaction Emails|boolean"
											."||autoEmailScheduleChanges|Schedule Change Emails|boolean"
											."||confirmNewSchedules|Always request confirmation for new schedules|boolean"
											."||confirmSchedules|Always request confirmation for schedule changes|boolean"
											."||autoEmailApptCancellations|Appointment Cancellation Emails|boolean"
											."||confirmApptCancellations|Always request confirmation for appointment cancellations|boolean"
											."||autoEmailApptReactivations|Appointment Reactivation Emails|boolean"
											."||confirmApptReactivations|Always request confirmation for appointment reactivations|boolean"
											."||autoEmailApptChanges|Appointment Change Emails|boolean"
											."||confirmApptModifications|Always request confirmation for appointment changes|boolean";		
																						

$providerEmailPrefs =	""									
											//"||optOutMassEmail|Mass Email: Sitters Opt Out by default|boolean"
											."autoEmailScheduleChangesProvider|Schedule Change Emails|boolean"
											."||confirmNewSchedulesProvider|Always request confirmation for new schedules|boolean"
											."||confirmSchedulesProvider|Always request confirmation for schedule changes|boolean"
											."||autoEmailApptCancellationsProvider|Appointment Cancellation Emails|boolean"
											."||confirmApptCancellationsProvider|Always request confirmation for appointment cancellations|boolean"
											."||autoEmailApptReactivationsProvider|Appointment Reactivation Emails|boolean"
											."||confirmApptReactivationsProvider|Always request confirmation for appointment reactivations|boolean"
											."||autoEmailApptChangesProvider|Appointment Change Emails|boolean"
											."||confirmApptModificationsProvider|Always request confirmation for appointment changes|boolean";								

if(TRUE || staffOnlyTEST()) { // enabled 11/24/2013

	if(!$_SESSION['preferences']['providerScheduleEmailPrefs']) {
		$_SESSION['preferences']['providerScheduleEmailPrefs'] =
		"Daily schedules: ".($_SESSION['preferences']['scheduleDaily'] ? 'yes' : 'no')
			."<br>Weekly schedules: {$_SESSION['preferences']['scheduleDay']}"
			."<br>".($_SESSION['preferences']['noEmptyProviderScheduleNotification']
							? "Don't send empty schedules" 
							: 'Send schedules even if empty')
			.(!getPreference('masterScheduleRecipients')
				? "<br>Do not send out Master Schedules"
				: "<br>Send Master Schedule ({$_POST['masterScheduleDays']} days) to ".count(explode(',', getPreference('masterScheduleRecipients')))." staff.");
							
	}
	$scheduleNotifications = "providerScheduleEmailPrefs|Sitter Schedule Email Preferences|custom|prov-notification-pref-edit.php";
}
else {
	$scheduleNotifications = "scheduleDay|Send Weekly Schedules|picklist|Never,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday"
														."||noEmptyProviderScheduleNotification|Suppress provider schedule email when there are no visits|boolean"; 
	$scheduleNotifications = "scheduleDaily|Send Daily Schedules by Default|boolean||".$scheduleNotifications;						
}

function ifStaffOr($prop, $target) {
	return staffOnlyTEST() || $_SESSION['preferences'][$prop] == $target;
}

function ifNotStaffAnd($prop, $target) {
	return !staffOnlyTEST() && $_SESSION['preferences'][$prop] == $target;
}


function ifStaffOrAnyOf($props, $target) {
	$props = is_array($props) ? $props : explode(',', $props);
	foreach($props as $prop) 
		if(ifStaffOr($prop, $target)) 
			return true; 
}


$displayPrefs = "includePaymentsInVisitList|Include Payments in Client Visits List|boolean"
								.(ifNotStaffAnd('hideProScheduleAtTop', true) ? '' : "||hideProScheduleAtTop|Hide Pro Schedule Button At Top|boolean")
								.(ifNotStaffAnd('hideProScheduleAtBottom', true) ? '' : "||hideProScheduleAtBottom|Hide Pro Schedule Button At Bottom|boolean")
								.(ifNotStaffAnd('hideOneDayScheduleAtTop', true) ? '' : "||hideOneDayScheduleAtTop|Hide One Day Schedule Button At Top|boolean")
								.(ifNotStaffAnd('hideOneDayScheduleAtBottom', true) ? '' : "||hideOneDayScheduleAtBottom|Hide One Day Schedule Button At Bottom|boolean")
								."||showChargeAndRateInScheduleEditorsByDefault|Show Charge and Rate in Ongoing Schedule Editors by Default|boolean"
								."||showCalendarPageInBanner|Show Today&apos;s Date as a Calendar Page in the Banner|boolean"
								."||chooseMgrVisitListColumns|Choose Visit List Columns|customlightbox|prov-schedule-list-options-lightbox.php?|760,520"
								.(!staffOnlyTEST() ? "" : "||includeOriginalNotesInVisitLists|Include Original Visit Notes In Visit Lists|boolean")

								."||hideArrivalMarkersInSitterVisitLists|Hide Arrival Indicators in visit lists|boolean"
								.(!staffOnlyTEST() ? "" : "||showVisitNotesInBlack|Show Visit Notes in black|boolean")
								.(FALSE ? "" : "||showPackageNotesInVisit|Show Schedule Notes in Visit For Manager Where No Visit Note is Present|boolean")
								.(!staffOnlyTEST() ? "" : "||preferredVisitSheetFormat|Visit Sheet Format|picklist|Standard=>standard,Compact=>petrx")
								."||requestlistcolumns|Home Page Request List Columns|custom|request-list-columns-pref-edit.php" // enabled 9/11/2019
								."||homePageInactiveSitters|Home Page Includes Inactive Sitters|boolean"
								.(!$_SESSION['preferences']['enableOlarkChat'] ? "" : "||suppressChat|Suppress Chat button|boolean")
								; 
$staffOnlyPreferences[] = 'hideProScheduleAtTop';
$staffOnlyPreferences[] = 'hideProScheduleAtBottom';
$staffOnlyPreferences[] = 'hideOneDayScheduleAtBottom';
$staffOnlyPreferences[] = 'hideOneDayScheduleAtTop';
$staffOnlyPreferences[] = 'hideOneDayScheduleAtBottom';
$staffOnlyPreferences[] = 'showVisitNotesInBlack';
$staffOnlyPreferences[] = 'preferredVisitSheetFormat';
$staffOnlyPreferences[] = 'includeOriginalNotesInVisitLists';
//if(!$_SESSION['preferences']['enableRequestListColumns']) $staffOnlyPreferences[] = 'requestlistcolumns';

$mobileSitterPrefs = /*mobileKeyDescriptionForKeyId*/"|Show Key Descriptions .. has moved to Key Management Preferences|page|preference-list.php?show=11|,670,500";

$visitCancellationPrefs ="cancellationDeadlineHours|Cancellation Deadline|custom|cancellation-deadline-edit.php";
$clientDefaultPrefs ="emergencycarepermission|Client authorizes emergency medical care by default|boolean";



$daysOfMonth[] = '--';
for($i=1;$i<=31;$i++) $daysOfMonth[] = $i;
$daysOfMonth = join(',',$daysOfMonth);
$billingPrefs = "pastDueDays|Past Due Days|int"; //||automaticBilling|Bill Automatically|boolean|";
$billingPrefs .= "||statementsDueOnPastDueDays|Payment Due notice in Statements|picklist|Upon Receipt,After Past Due Days,Suppress";
//else if(staffOnlyTEST()) $billingPrefs .= "||statementsDueOnPastDueDays|Statement payments are due after Past Due Days (not \"Upon Receipt\")|boolean";
$dom = " Day of Month";
//$bimonthlyBillingPrefs = //bimonthlyThroughDate1|Service Through$dom|picklist|$daysOfMonth||bimonthlyThroughDate2|Service Through$dom|picklist|$daysOfMonth||
$billingPrefs .= 
								"||bimonthlyBillOn1|Bill On$dom|picklist|$daysOfMonth"
								."||bimonthlyBillOn2|Bill On$dom|picklist|$daysOfMonth";
//$monthlyBillingPrefs = 
//								"monthlyBillOn|Bill On$dom|picklist|$daysOfMonth||".
$billingPrefs .= 
								"||monthlyServicesPrepaid|Enable Fixed-Price Monthly Schedules|boolean"
								//."||ccGateway|Credit Card Gateway|picklist|-- Select Gateway --,Authorize.net,SAGE"
								."||ccGateway|Credit Card Merchant Info|custom|cc-merchant-edit.php"
								."||ccAcceptedList|Credit Cards Accepted|custom|cc-accepted-pref-edit.php"
								."||autoEmailCreditReceipts|Auto-Email Credit Receipts|boolean"
								."||newServiceTaxableDefault|New Services Taxable By Default|boolean"
								."||taxRate|Tax Rate %|float"
								."||surchargeCollisionPolicy|Automatic Surcharge Collision Policy|picklist|Apply the greatest charge,Apply the smallest charge,Apply all charges"
								;

$staffOnlyPreferences[] = 'schedulesPrepaidByDefault';
$staffOnlyPreferences[] = 'invoiceEmailsFromCurrentManager';
if(staffOnlyTEST()) $billingPrefs .= "||schedulesPrepaidByDefault|New Schedules are Prepaid by default|boolean";
if(staffOnlyTEST()) $billingPrefs .= "||invoiceEmailsFromCurrentManager|Use logged-in manager name in From line of emailed invoices/statements|boolean";
$billingPrefs .= "||invoiceHeader|Invoice Header|custom|invoice-header-edit.php|,750,520";
$billingPrefs .= "||emailedInvoicePreviewHeader|Invoice Preview Header|custom|invoice-preview-header-edit.php|,750,520";

$billingPrefs .= "||suppressInvoiceTimeOfDay|Suppress Visit Time of Day in Statements|boolean";
$billingPrefs .= "||suppressInvoiceSitterName|Suppress Visit Sitter Name in Statements|boolean";

$billingPrefs .= "||includeInvoiceGratuityLine|Include a gratuity line at the end of invoices|boolean";
$billingPrefs .= "||suppressDetachHereLine|Suppress the \"Detach Here\" line in invoices|boolean";

if(staffOnlyTEST()) $billingPrefs .= "||betaBillingEnabled|Enable Beta Billing|boolean";
	$billingPrefs .= "||includePayNowLink|Include a \"Pay Now\" link in invoices|boolean";

$billingPrefs .= "||markStartFinish|Mark visits at Start/End of Short Term schedules|boolean";

$lookaheadDays[]='';
for($i=1;$i<=15;$i++) $lookaheadDays[]=$i;
$lookaheadDays = join(',',$lookaheadDays);
$billingPrefs .= "||sendBillingReminders|Send Billing Reminders|boolean"
								 ."||billingReminderLookaheadDays|Billing Reminder Lookahead (days) |picklist|$lookaheadDays";
if(staffOnlyTEST()) {
	$billingPrefs .= "||suppressPayNowGratuity|Suppress Gratuity options in Client's Pay Now page.|boolean";
	$staffOnlyPreferences[] = 'suppressPayNowGratuity';
}

								 
/*
$from = "Notices from LeashTime <notice@leashtime.com>";
$to = "Matt Lindenfelser <thule@aol.com>";
$subject = "Hi! #2";
$body = "Hi,\n\nHow are you?";

$host = "smtp.1and1.com";
$username = "notice@leashtime.com";
$password = "not11ce";
*/
for($i=7;$i<=75;$i++) $days[]=$i;
$days = join(',',$days);
$holidayPrefs ="holidayVisitLookaheadPeriod|Holiday Visit Lookahead Period (days)|picklist|$days";


/*// TESTING
if($_SESSION['auth_login_id'] == 'matt') {
	$prefsDescription .= '||HR||secureKeyEnabled|Secure Key|boolean||HR';
	if(isset($_SESSION['preferences']['secureKeyEnabled']))
	  $_SESSION['secureKeyEnabled'] = $_SESSION['preferences']['secureKeyEnabled'];
}*/


if($_SESSION['preferences']['mod_securekey']) {
	$labelFormats = parse_ini_file('key-label-formats.txt', true);
	foreach($labelFormats as $format) {
		$dbsallowed = $format['dbsallowed'];
		if($dbsallowed) {
			$dbsallowed = explode(',', trim($dbsallowed));
			if(!in_array($db, $dbsallowed)) continue;
		}
		if(mattOnlyTEST() || !$format['experimental'])
			$parts[] = $format['label'];
	}
	$parts = join(',',$parts);
	$keyPrefs ="keyLabelSize|Key Label Size|picklist|--,$parts";
	$keyPrefs .= "||mobileKeyDescriptionForKeyId|Show Key Descriptions Rather than Key IDs|boolean";

}

$leashTimeStaffPrefs = 
	"scheduleRequestAcknowledgement|Schedule Request Acknowledgement|string"
	."||prospectRequestResponse|Prospect Request Response|string"
	."||calendarWeekStart|Calendar Week starts on|picklist|Monday,Sunday"
	."||invoicingEnabled|Invoicing Enabled|boolean"
	."||taxationStartDate|No Taxation Before|string"
	
	//."||nativeSitterAppPrefs|Native Sitter App Options|customlightbox|native-sitter-preference-list.php|800,520||"
;

if($_SESSION['preferences']['enableMessageArchiveCron'])
	$leashTimeStaffPrefs .= "||archiveMessageDaysOld|Archive Messages Older Than|picklist|270,300,330,365,400,450,480,510,540";
//bizLogo|Business Logo

$prefListSections = 
						array(
									'General Business'=>$prefsDescription, 
									'Client User Interface'=>$clientUI, 
									'Sitter User Interface'=>$providerUI, 
									'Outgoing Email'=>$emailPrefs,
									//'Client Email'=>$clientEmailPrefs,
									'Client Defaults'=>$clientDefaultPrefs,
									//'Sitter Email'=>$providerEmailPrefs,
									'Sitter Schedule Notifications'=>$scheduleNotifications,
									'Billing Preferences'=>$billingPrefs,
									'Display Preferences'=>$displayPrefs,
									'Cancellation Deadline Preferences'=>$visitCancellationPrefs,
									'Holiday Preferences'=>$holidayPrefs
									
									//'Bi-Monthly Billing Preferences'=>$bimonthlyBillingPrefs,
									//'Monthly Billing Preferences'=>$monthlyBillingPrefs
									);
if(staffOnlyTEST()) $keyPrefs .= "||maxKeySafes|Maximum number of Key Safes to allow|picklist|5,10,15,20,50";
//if(staffOnlyTEST()) $keyPrefs .= "||maxKeyCopies|Maximum number of key copies to allow|picklist|10,15,20,25,50";

$staffOnlyPreferences[] = 'maxKeySafes';
$staffOnlyPreferences[] = 'maxKeyCopies';
									
if($keyPrefs) 
	$prefListSections['Key Management Preferences'] = $keyPrefs;
if(FALSE && $_SESSION['preferences']['mobileSitterAppEnabled']) 
	$prefListSections['Mobile Sitter Preferences'] =$mobileSitterPrefs;
if(staffOnlyTEST()) {
	$prefListSections['LeashTime Staff Only'] = $leashTimeStaffPrefs;
}
	
$allOrNone = array('all'=>'all', 'none'=>array());									
									
$showSections = 
	isset($allOrNone[$_REQUEST['show']]) 
	? $allOrNone[$_REQUEST['show']]
	: ($_REQUEST['show'] ? explode(',', $_REQUEST['show']) : array());

preferencesTable($prefListSections, $help, $showSections);

if($_REQUEST['dump']) {
	foreach($prefListSections as $label => $section) {
		echo "<p># $label<br>";
		$section = explode('||', $section);
		foreach($section as $value) {
			$value = explode('|', $value);
			$k = $value[0];
			echo "$k = \"{$_SESSION['preferences'][$k]}\"<br>";
		}
	}
}
		
//preferencesEditorLauncher($prefsDescription);
?>
<script language='javascript'>
<? 
dumpPrefsJS();
dumpShrinkToggleJS();
?>

function openSections() {
	var open = new Array();
	var numClosed = 0;
	var el;
	for(var i=1; el = document.getElementById('section'+i); i++) {
		if(el.style.display == 'none') numClosed++;
		else open.push(i);
	}
	if(numClosed == 0) return 'all';
	else return open.join(',');
}

function updateProperty(property, value) {
	//window.refresh();
<? if(mattOnlyTEST()) echo ""; ?>	
	document.location.href='<?= basename($_SERVER['SCRIPT_NAME']) ?>?show='+openSections();
	document.getElementById('prop_'+property).scrollIntoView();
}

</script>
<?
include "refresh.inc";
// ***************************************************************************

include "frame-end.html";
?>

