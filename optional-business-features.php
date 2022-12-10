<? // optional-business-features.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";

$locked = locked('o-');

if(!staffOnlyTEST()) {
	echo "Must be staff.";
	exit;
}

if($checkFeature = $_GET['checkFeature']) {
	require_once "common/init_db_common.php";
	$allDBs = fetchCol0("SHOW databases");
	foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz=1") as $biz) {
		if(!in_array($biz['db'], $allDBs)) continue;
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], $force=true);
		if($val = fetchPreference($checkFeature)) {
			//$bname = fetchPreference('bizName').(fetchPreference('shortBizName') ? " (".fetchPreference('shortBizName').")" : '')." (db: $db)";
			$bname = "<span class='fauxlink EXTRA' title='".(fetchPreference('shortBizName') ? "Shortname: ".safeValue(fetchPreference('shortBizName')) : 'No short name')."'>"
								.(fetchPreference('bizName') ? fetchPreference('bizName') : fetchPreference('shortBizName'))."</span> ($db)";
			$bnames[$biz['bizid']] = $bname;
			$vals[$biz['bizid']] = $val;
		}
	}
	if(!$bnames) echo "$checkFeature is not in use by any business.";
	else {
		require "common/init_db_common.php";
		$ltBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers'", 1);
		reconnectPetBizDB($ltBiz['db'], $ltBiz['dbhost'], $ltBiz['dbuser'], $ltBiz['dbpass'], $force=true);



		$clients = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient WHERE garagegatecode > 0");
		$clients2 = array_merge($clients);
		$clients3 = array_merge($clients);
		$clients4 = array_merge($clients);
		$clients5 = array_merge($clients);
		foreach($clients as $ltclientid => $garagegatecode) {
			$goldstar = fetchRow0Col0("SELECT clientptr 
														FROM tblclientpref 
														WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '2|%'");
			if(!$goldstar) unset($clients[$ltclientid]);
		}
		$goldstars = $clients; // ltclientid => bizid

		foreach($clients2 as $ltclientid => $garagegatecode) {
			$trial = fetchRow0Col0("SELECT clientptr 
														FROM tblclientpref 
														WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '1|%'");
			if(!$trial) unset($clients2[$ltclientid]);
		}
		$trials = $clients2; // ltclientid => bizid

		foreach($clients3 as $ltclientid => $garagegatecode) {
			$deadtrial = fetchRow0Col0("SELECT clientptr 
														FROM tblclientpref 
														WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '8|%'");
			if(!$deadtrial) unset($clients3[$ltclientid]);
		}
		$deadtrials = $clients3; // ltclientid => bizid

		foreach($clients4 as $ltclientid => $garagegatecode) {
			$greystar = fetchRow0Col0("SELECT clientptr 
														FROM tblclientpref 
														WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '21|%'");
			if(!$greystar) unset($clients4[$ltclientid]);
		}
		$greystars = $clients4; // ltclientid => bizid

		/*// FORMER CLIENTS greystar(21), deadlead(8)
		foreach($clients5 as $ltclientid => $garagegatecode) {
			$former = fetchRow0Col0("SELECT clientptr 
														FROM tblclientpref 
														WHERE clientptr = $ltclientid AND property LIKE 'flag_%' 
															AND (value like '8|%' OR value like '21|%')");
			if(!$former) unset($clients[$ltclientid]);
		}
		$formerclients = $clients; // ltclientid => bizid*/

		$colors = array(
			'goldstars'=> '#FFD700', // Gold
			'trials'=> '##FFA500', // Orange
			'deadtrials'=> '#808000', // Dark green
			'greystars'=> ' 	#808080', // Gray
			//'formerclients'=> '#696969' // DimGray
			);
//print_r(join(', ', $goldstars));
//echo "<hr>".print_r($bnames, 1);
		foreach($bnames as $id => $nm) {
			$class = 'other';
			if(in_array($id, $goldstars)) $class = 'goldstars';
			else if(in_array($id, $trials)) $class = 'trials';
			else if(in_array($id, $deadtrials)) $class = 'deadtrials';
			else if(in_array($id, $greystars)) $class = 'greystars';
			$counts[$class] += 1;
			//else if(in_array($id, $formerclients)) $class = 'formerclients';
			$bnames[$id] = str_replace('EXTRA', $class, $nm);
			$bnames[$id] .= " [{$vals[$id]}]";
//echo "<hr>{$bnames[$id]} ==> $class";
		}
		sort($bnames);
		echo "<style>
		.content {background: #D0D0D0}
		.fauxlink {text-decoration: underline; cursor:pointer;}
		.goldstars {color: #EEC400}
		.trials {color: #EC9600}
		.deadtrials {color: #808000}
		.greystars {color:  	#808080}
		.formerclients {color: #696969}
		.other {color: #7986B7}
		</style>
		<div class= 'contentX'>
";
		foreach(explode(',', 'goldstars,trials,deadtrials,greystars,other') as $type) {//,formerclients
			$count = $counts[$type] ? $counts[$type] : '0';
			echo "<span class='$type'>$type ($count)</span> ";
		}
		echo "<p>The following businesses have '$checkFeature' enabled:<ul><li>";
		echo join('<li>', $bnames);
		echo "</div>";
	}
	exit;
}

//Pay Now Link|optionEnabledPayNowInvoiceLink|Pay Now link preference enabled.
//Managers and Dispatchers|managersAndDispatchers|Managers and Dispatchers option in ADMIN.

$allOptionsRaw = explode("\n", trim(
"[DISCONTINUED] Google Calendar|optionEnabledGoogleCalendarSitterVisits|Visits can be pushed to sitter calendars.
#Mail Chimp|optionEnabledMailChimpButton|Mail Chimp button offered on Email Broadcast page.
#Enhanced Daily Summary on Home Page|optionEnabledEnhancedDailySummaryView|Enhanced Daily Summary enabled.
Beta Billing|betaBillingEnabled|Same as option in Preferences [ Billing Preferences ].
Beta Billing 2|betaBilling2Enabled|Pre-empts ORIGINAL billing.
Sitters Can Send IC Invoices|sittersCanSendICInvoices|Sitters Can Send IC Invoices.
Master Schedule Sitter Nicknames|masterScheduleNicknames|Use Sitter Nicknamess in Master Schedule
[BETA] Enable List Limit Options for Sitter Client Lists|enableclientListLimitOptions|Let managers set limits on which clients a sitter can see
[BETA] Enable Pending Visits List (Client Home Page)|enableClientPendingRequests|Enable link on Client Home page (schedule)
#[BETA] Enable Client Login Page/Client Scheduler Announcements|enablepageopenannouncements|Enable Announcements on Client Login and Client Schedule Creator..
[BETA] Enable Dedicated Payments|enablededicatedpayments|Enable Dedicated Payments using the Two Stage Payment Editor..
[BETA] Enable Office Task Management|enablerequestassignments|Enable Office Task Management (Client Request Assignment) functionality...
[BETA] Mobile Sitter Map|enableMobileSitterMap|Include the 'Map' option in the Mobile Sitter Web App.
#[BETA] Notify Clients of Arrivals/Completions in Real Time|clientArrivalCompletionRealTimeNotification|When a sitter arrives/completes using the Mobile Sitter App, email the client (per client preference).
[BETA] Nearby Clients Map (client profile only)|offerNearbyClientsMap|Offer a link to a map that shows other clients and prospects who live near a client.
[BETA] Nearby Clients Map (SITTER profile only)|offerSitterNearbyClientsMap|Offer a link to a map that shows clients and prospects who live near a sitter.
[BETA] Billing Reminders Account Balance|billingreminderaccountbalance|Show Account Balance in Billing Reminders
#[BETA] Home Safe Enabled|homeSafeEnabled|Include Home Safe button in Billing Reminders
[BETA] Unassigned Visits Board|enableUnassignedVisitsBoard|Enable the Unassigned Visits Board (needs to have table added to work)
[BETA] Show Completion icon in Sitter Visit lists|showCompletionIndicatorsInSitterVisitLists|Show the green [Completed] icon on completed visits in sitter visit lists
[BETA] Show Clients Which Visits are Paid|showClientPaidVisits|Show the Paid In Full message where appropriate
[BETA] Make Service Agreement in Client Profile a Link|offerManagerLinkToClientsAgreement|Make Service Agreement in Client Profile (manager view) a Link
[BETA] Offer \"No Credit Card Required\" box for Individual Clients|enableIndividualClientCreditCardsNotRequired|Offer an individual exemption to \"Credit Card Required\"
[BETA] Offer Options for Schedule Change Staff Notices|enableschedulenoticeoptions|Offer Dates/Summary/Detailed Options for Schedule Change Staff Notices
[BETA] Offer Client Email Templates in Prospect Request Email|enableclienttemplatesforprospects|In Composers opened from (not yet client) Prospect Requests, offer client templates in the menu
[BETA] Confirm Visit Cancellation in Visit Lists|confirmVisitCancellationInLists|Make manager confirm before canceling or uncanceling a visit to prvent mistakes.
[BETA] Enable Printable Client Snapshot|printableclientsnapshot|Add icon to snapshot window to convert to a print-friendly format
Spam-filtering Prospect Form (Ask Matt)|enforceProspectSpamDetection|A version of the LeashTime-hosted prospect form that generates [Prospect Spam] requests (already resolved) in response to most spambots.
#Use Full Window to Show LeashTime Option|optionEnabledFrameLayout|Offer the Use Full Window to Show LeashTime option
Key Management Functions|mod_securekey|Offer access to Key Management functionality
[BETA] Enable EZ Schedule Email Button|enableEZScheduleEmailButton|Include an email button in EZ Schedules and Billing Reminders
Suppress Price Info in Client Schedule Notification descriptions|suppressPriceInfoInClientSchedNotifications|Don't show subtotals, tax or total prce in description"));
//[BETA] Do Not Serve List|donotserveenabled|Offer the Do Not Serve list feature, where sitters are made unavailable to certain clients.
//[BETA] Show Arrival icon in Sitter Visit lists|showArrivalsInSitterVisitLists|Show the blue [Arrived] icon on incomplete visits in sitter visit lists
//[BETA] Enable Customized Billing Flag Icons|enablecustomizedbillingflags|Enable Customized Billing Flags..
//[BETA] Enable Larger Meetings Option|enablemaxmeetingsitters|Allow meetings to invite more than two sitters..
//Latest PSA Version Signature Required|latestAgreementVersionRequired|Before each login, confirm client has signed the latest version of the pet service agreement.
//Provider Map for Multiple Sitters|enableMultiSitterMaps|Add the All Visits and Visits Right Now feature
//[BETA] Nearby Sitters Map (client profile only, for now)|offerNearbySittersMap|Offer a link to a map that shows sitters who live near a client.

//$allOptionsRaw[] = "Master Schedule Daily Email|dailyMasterScheduleEmailOption|Managers can opt to receive Master Schedule by Email";
//$allOptionsRaw[] = "[BETA] Stale Visit Notifications|enableStaleVisitNotifications|Generate <b>Overdue Visits</b> requests and notify concerned managers";
//$allOptionsRaw[] = "Use New Rate Calculations|useNewRateCalculations|Use New Rate Calculations that properly handle Multiple pet charges";
$allOptionsRaw[] = "[BETA] Billing 2 Email Templates|enableBilling2EmailTemplates|Allow choice of email templates in Beta Billing 2";
//$allOptionsRaw[] = "[BETA] Allow Sitters to See All Time Off|enableTimeoffCalendarGlobalVisibility|Allow sitters to see all time off.";
$allOptionsRaw[] = "[BETA] Public Sitter Profiles|enableSitterProfiles|Public Sitter Profile feature.";
if(mattOnlyTEST()) $allOptionsRaw[] = "[ALPHA] Message Archive Feature|enableMessageArchiveFeature|";
if(mattOnlyTEST()) $allOptionsRaw[] = "[ALPHA] Message Archive Cron Job|enableMessageArchiveCron|<font color=red>BE SURE TO HAND-PROCESS THE DATABASE BEFORE TURNING THIS ON</font>";
$allOptionsRaw[] = "[ALPHA] TSYS Transaction Express Available|enableTransactionExpressAdapter|";
$allOptionsRaw[] = "[BETA] Offer Client Simple Multi-Day Visit Change/Cancel Request Form|offerSimpleMultiDayChangeCancelRequestForm|";
//$allOptionsRaw[] = "[BETA] Offer client Simple MultiDay Change/Cancel button|offerSimpleMultiDayChangeCancelRequestForm|Offer client a button to cancel/change multiple visits using a simple form";
$allOptionsRaw[] = "[BETA] Enable Summary View of Sitter Payables|enableSitterPayablesSummary|(Staff sees this view already)";
$allOptionsRaw[] = "[BETA] Let mobile sitters view client schedules on days when they have no visits|sitterCanViewSchedulesOnDaysWithNoVisits|Even days when they have no visits.";
//$allOptionsRaw[] = "[BETA] Enable Lean WAG view (with colors)|enableLeanWAGView|(Staff sees this view already)";
$allOptionsRaw[] = "[BETA] Enable \"we have keys...\" in client filter|enableWeHaveKeysClientFilter|";


// enabled for all 11/19/2020$allOptionsRaw[] = "[BETA] Enable \"with services in period...\" in client filter|enableServicesInPeriodClientFilter|(Staff sees this view already)";
// enabled for all 11/19/2020$allOptionsRaw[] = "[BETA] Enable \"Who are...Prospects\" in client filter|enableWhoAreProspectsInClientFilter|(Staff sees this view already)";
// enabled for all 11/19/2020$allOptionsRaw[] = "[BETA] Enable \"With visits recent as\" in client filter|enableVisitsRecentAsInClientFilter|";


// enabled for all 11/19/2020 $allOptionsRaw[] = "[BETA] Enable \"Download Spreadsheet\" in Projected Payroll|enableProjectedPayrollDownload|(Staff sees this view already)";
$allOptionsRaw[] = "[BETA] Enable Client Visible Custom Fields Option|enableClientVisibleCustomFields|Allows custom fields to be viewed by sitters, but not clients.<br><font color=red>BE SURE TO HAND-SET CLIENT VISIBLE BOXES</font>";
$allOptionsRaw[] = "[BETA] Enable Pre-filled Client Intake Forms|enablePrefilledIntakeForm|Offer \"Print Intake Form\" in client options [Services tab]";
//$allOptionsRaw[] = "[BETA] Forward Emails/Communications|enableCommunicationForwarding|Offer \"Forward\" button in many communication viewers.  (Messages in Communication tab)";
$allOptionsRaw[] = "[BETA] Enable Visit Sheet Email via Alerts/Emails|enableVisitSheetEmailBrodcast|Enable Emailing of Visit Sheets via Alerts/Emails";
//$allOptionsRaw[] = "[RELEASED -- OPTION NOT USED] Enable Pay Online Button Graphic|enablePayOnlineButtonGraphic|Use a button rather than a link for Pay Online in billing statements";
//$allOptionsRaw[] = "[BETA] NATIVE SITTER APP|enableNativeSitterAppAccess|Sitters can login via Native Sitter App";
$allOptionsRaw[] = "[BETA] Bulk Request resolution|enableBulkRequestResolution|In the Request Report, show checkboxes and Resolve button";
$allOptionsRaw[] = "[BETA] Referral Reports With Notes|enableUseReferralNotesAsCats|In the Rev by Referrals Report, allow referral notes to be used as subcats";
$allOptionsRaw[] = "[BETA] Sitter Notes in Mobile and Native|enableSitterNotesChatterMods|Support for the Dialy Sitter Notes reportand Chatter Functionality";
$allOptionsRaw[] = "[BETA] Remind client of pending Profile Change Request|enableProfileChangeRequestReminder|Display a reminder to a client if she views her profile after sending a change request";
//$allOptionsRaw[] = "[BETA] Enable Client Documents/Files Features|enableClientFilesFeatures|Turns on Client files/documents features, including custom field types";
//$allOptionsRaw[] = "[BETA] Enable Sitter Time Off Blackouts|enableTimeoffBlackouts|Allow managers to set up blackout periods in the Sitter Time Off Calendar";
$allOptionsRaw[] = "[BETA] Offer Delete Time Off link in Request Editor|enableDeleteTimeOffLink|Offer manager a link to delete time off n a Time Off request";
$allOptionsRaw[] = "[BETA] Overdue Arrival Visit Notifications|enableOverdueArrivalNotifications|Allow certain service types to be desiganed as overdue when arrival, rather than completion, is late.";
$allOptionsRaw[] = "[BETA] Overdue Arrival Event Breakout|enableOverdueArrivalEventType|Establish the Overdue Visits Event Type (separate from Client Requests).";
$allOptionsRaw[] = "[BETA] Offer Client Provider Name Display Mode|offerClientProviderNameDisplayMode|Allow manager to control/suppress of sitter display name to client.";
$allOptionsRaw[] = "[BETA] Preview Visits to be unassigned by Timeoff Editor|enableTimeoffPreview|Offer a link to preview visits that will be unassigned when Time Off is saved.";
$allOptionsRaw[] = "[BETA] Show other visits on schedule update notices|enableShowOtherVisitsOnScheduleUpdates|Show other visits on schedule update notices.";
$allOptionsRaw[] = "[BETA] Turn On Mobile Messaging|enableMobileMessaging|Allow text messaging (SMS)";
$allOptionsRaw[] = "[BETA] Show Client Flags in Request Editors|showClientFlagsInRequests|";
$allOptionsRaw[] = "[BETA] Enable Charge Email Templates|enableChargeEmailTemplates|Allow use of editable templates for \"Your Card Has Been Charged\" msgs";
// enabled for all 2020-10-04 $allOptionsRaw[] = "[BETA] Enable Visit Reports to Alt Email|enableVisitReportAltEmails|Allow client Alt email address to receive visit reports.";
$allOptionsRaw[] = "[BETA] Enable Visit Reports (Apply Visit Photo to Pet Profile)|enableVisitPhotoToPetPhoto|Show \"Use visit photo...\" link in Visit Report request viewer.";
$allOptionsRaw[] = "[ALPHA] Enable Value Packs|enableValuePacks|Allow use of Value Packs";
//$allOptionsRaw[] = "[BETA] Enable Request List Columns|enableRequestListColumns|Show Request List Column chooser";
$allOptionsRaw[] = "[BETA] Enable Olark Chat|enableOlarkChat|Offer Olark Chat when this business's managers are logged in";
$allOptionsRaw[] = "[BETA] Enable Manual Payment Receipts|enableManualPaymentReceipts|Offer the Send Receipt link in payment editors";
$allOptionsRaw[] = "[BETA] Enable Key Office Notes|enableKeyOfficeNotes|Make Office Only Key Notes available.";
// opened to all on 2/28/2019 $allOptionsRaw[] = "[BETA] Enable Flexible Prospect Form Option|enableFlexibleProspectFormOption|Allow businesses to choose the Flexible Prospect form.";
// added to comm-prefs.php 8/28/2020 $allOptionsRaw[] = "[BETA] Enable Sitter Tip Memos|enableSitterTipMemos|Allow sitters to receive notices when Gratuities are received.";
$allOptionsRaw[] = "[ALPHA] Show Overnight visits in Time Off Calendar|overnightsontimeoffcalendar|Show Overnight visits on the Time Off Calendar.";
$allOptionsRaw[] = "[ALPHA] Delay Visit Report Email to ensure photo upload|delayVisitReportEmailSending|A VR sometimes is submitted before photo upload is complete.";
$allOptionsRaw[] = "[BETA] Enable Client Profile \"Last Access\" Notice|enableClientProfileLastAccessNotice|Show the Last Access notice in client profile editors.";
$allOptionsRaw[] = "[BETA] Client UI Multi-Day Cancel|enableclientuimultidaycancel|Clients can select visits to cancel as a group.";
$allOptionsRaw[] = "[BETA] Send Visit Reports from Mobile Sitter Web App|postcardsEnabled|Selected sitters can send Visit Reports from the Mobile Sitter Web App.";
$allOptionsRaw[] = "[BETA] Enable Admin Only Key Safes|enableAdminOnlyKeySafes|Allow key safes to be marked Admin Only (managers and dispatchers).";
// enabled for all 3/6/2019 $allOptionsRaw[] = "[BETA] Enable Send to Sitters button in Requests|enableSendRequestToSitters|Send to Sitters button in the request editor.";
$allOptionsRaw[] = "[BETA] Key Log Report|enableKeyLogReport|ADMIN > Reports > Clients Key Log.";
$allOptionsRaw[] = "[BETA] Visit Perfomance Icons|enableVisitPerformanceIcons|Show icons in Arrivals/Completions list";
//$allOptionsRaw[] = "[BETA] Client Sitters|enableClientSitters|Preferred Sitters, Do not Assign, Nearest Sitters.";
$allOptionsRaw[] = "[BETA] First Visit, Last Visit, # Completed in Exports|enableFirstLastCompletedInExports|Add three columns to client exports.";
// opened to all on 8/28/2020 $allOptionsRaw[] = "[BETA] Payment Editor Applied To... link|enablePaymentAppliedToLink|Let managers see what payment was applied to.";
// enabled for all 11/19/2020 $allOptionsRaw[] = "[BETA] Sitter Time Off Report|timeoffreportenabled|Enable Sitter Time Off Report";
// added as Native Sitter App Option 9/2/2020 --$allOptionsRaw[] = "[BETA] Visit Report Icons on Home Page|homepagevisitreporticonsenabled|Enable Visit Report status icon display on Home page";
$allOptionsRaw[] = "[BETA] Distinguish Preferred and Banned Sitters in Nearby Sitters Maps|preferredAndBannedSittersInMaps|Show Do Not Assign and Preferred Sitters differently in Nearby Sitters maps.";
//$allOptionsRaw[] = "[BETA] Enable Send to Sitters button in PROFILE Requests|enableSendToSittersInProfileRequests|";
$allOptionsRaw[] = "[BETA] Enable Incomplete Schedule Notifications|enableIncompleteScheduleNotifications|Create a daily (or periodic) notification listing incomplete (failed) client schedule request creation attempts";
$allOptionsRaw[] = "[BETA] Enable Office Documents|enableOfficeDocuments|Allow upload and maintenance of documents for the business.";
$allOptionsRaw[] = "[BETA] Enable Photo Gallery Option|enablePhotoGalleryOption|Allow manager to decide whether to offer photo gallery in responsinve client portal.";
$allOptionsRaw[] = "[BETA] Enable Last Visit Note in mobile apps|enableLastVisitNote|Show the time, sitter and note for last visit to client, in sitter apps";
$allOptionsRaw[] = "[BETA] Enable Multi Week Recurring Schedules|enableMultiWeekRecurring|Enable multi week recurring schedules.";
$allOptionsRaw[] = "[BETA] Enable Multi Sitter Choice in Day Maps|enableMultiSitterChoiceMaps|In visit maps from the Home page, allow choice of multi sitters.";
//$allOptionsRaw[] = "[BETA] Prospect Request Existing (Dup) Client Detection|enableProspectDuplicateClientDetection|Show \"Duplicate?\" link in Prospect requests.";
$allOptionsRaw[] = "[BETA] Enable Visit Date Change (Move a visit)|enableChangeVisitDate|Allow visit dates to be changed.";
$allOptionsRaw[] = "[BETA] Enable Last Changed Note in Time Off Editor|enableTimeOffLastChange|Enabled the note (managers only) that shows who last touched the Time Off.";
$allOptionsRaw[] = "[ALPHA] Client Service Pet Type Filter|enableclientservicepettypefilter|Limit client service choice by their pet types.";
$allOptionsRaw[] = "[BETA] Visit photo rotator|enablevisitphotorotation|Allow manager to rotate visit photos.";
$allOptionsRaw[] = "[BETA] Enable Gratuity Soliciation email template|enableGratuitySoliciation|Allow manager to edit and use the Gratuity Soliciation email template in Clients>Email/Alerts";
$allOptionsRaw[] = "[BETA] Offer Daily Pay Spreadsheet in Sitter Pay History tab MANAGER|enableDailyPayOffice|Make Daily Pay spreadsheet available to managers in sitter Pay History tab";
$allOptionsRaw[] = "[BETA] Offer Daily Pay Spreadsheet on Sitter Pay History page SITTER|enableDailyPaySitter|Make Daily Pay spreadsheet available to SITTERS on Pay History page";
$allOptionsRaw[] = "[BETA] Allow Multiple Recipients in Request \"Send to Sitter\" button|enableSendToMultipleSitters|Allow selection of multiple sitters in \"Send to Sitter\" composer";
$allOptionsRaw[] = "[BETA] Include templates choice in Visits Email Composer|enableVisitsComposerTemplates|... for sending schedules from Service tab";
//$allOptionsRaw[] = "[ALPHA] Responsive Client UI|enableresponsiveclient|Enable the Responsive version of the client UI for this business.";
sort($allOptionsRaw);
foreach($allOptionsRaw as $line) {
	$parts = explode('|', $line);
	if($parts[0][0] == '#') continue;
	$allOptions[$parts[1]] = array('label' =>$parts[0], "description"=>$parts[2], "key"=>$parts[1]);
}

foreach($allOptions as $k => $opt) {
	if(fetchPreference($k)) $enabledOptions[] = $opt['label'];
	else $nonEnabledOptions[] = $opt['label'].' - '.$opt['description'];
}
$enabledOptions = "Currently enabled [$db] <ul><li>".join('<li>', $enabledOptions)."</ul>";
$nonEnabledOptions = "Currently NOT enabled<ul><li>".join('<li>', $nonEnabledOptions)."</ul>";

if($_POST['go']) {
	foreach($allOptions as $k => $unused)	setPreference($k, ($_POST[$k] ? 1 : 0));
	$_SESSION['secureKeyEnabled'] = $_SESSION["preferences"]['mod_securekey']; // *ugh*
	$message = "Options saved.";
}
$bname = getPreference('shortBizName') ? getPreference('shortBizName') : (
					getPreference('bizName') ? getPreference('bizName') : "($db)");
$pageTitle = "Optional Business Features for $bname";
include "frame.html";
if($message) echo "<p class='tiplooks'>$message</p>";
echo "<p>Reminder: an option may be enabled for the business, but not selected (in Preferences or elsewhere).</p>";
fauxLink('Enabled Options', 'showEnabledOptions()');
// ***************************************************************************
echo "<form name='opts' method='POST'>";
hiddenElement('go',1);
echoButton('', 'Save', "document.opts.submit()");
echo "<p><table>";
foreach($allOptions as $option) {
	
	echo "<tr><td style='padding-top:7px;'>";
	$url = "optional-business-features.php?checkFeature={$option['key']}";
	fauxLink('Who?', "$.fn.colorbox({href:\"$url\", width:\"750\", height:\"470\", iframe: true, scrolling: true, opacity: \"0.3\"});");
	echo "</td><td>";
	labeledCheckbox($option['label'], $option['key'], getPreference($option['key']), 0 , 0 , 0, 'boxOnLeft');
	echo "</td><td style='padding-top:7px;padding-left:20px;'>{$option['description']}</td></tr>";
}
echo "</table></form>";
// ***************************************************************************
?>
<script src="common.js"></script>
<script>
function showEnabledOptions() {
<? $optionsSummary = 
		str_replace("\r", "", 
			str_replace("\n", "", 
				str_replace("'", "&apos;", $enabledOptions."<hr><hr><hr><hr>".$nonEnabledOptions))); ?>
	var opts = '<?= $optionsSummary ?>';
	$.fn.colorbox({html:opts, width:"600", height:"500", scrolling: true, opacity: "0.3"});
}
</script>
<?
include "frame-end.html";
