<? // reports.php
// Edit email prefs for one user at a time
// params: id - clientid
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

//if(mattOnlyTEST()) {}
// Determine access privs
$locked = locked('o-#vr');
if($_SESSION["staffuser"] == 8909 && dbTEST('leashtimecustomers')) { //jody
	require_once "reports-abbreviated.php";
	exit;
}

$staffOnlyFlag = staffOnlyTEST() ? "[STAFF ONLY] " : "";
$staffOnlyFlagOrOptBiz = staffOnlyTEST() ? "[STAFF/Opt.Biz] " : "";
$optBiz = staffOnlyTEST() ? "[Opt.Biz] " : "";

$pageTitle = "Reports";

function customReportsBox() {
	$custreports = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property LIKE 'custreport%'");
	if(!$custreports) return;
	foreach($custreports as $k => $v)
		$reports[(int)substr($k, strlen('custreport'))] = $v;
	ksort($reports);
	echo "<td valign=top>
		<div class='greybox' >
		Custom Reports
		<div class='whitebox' >";
	foreach($reports as $i => $report) {
		$report = explode('|', $report);
		$title = count($report) > 2 ? "title='($i) $report[2]'" : "title='($i)'";
		echo "<a href='{$report[1]}' $title>{$report[0]}</a><p>";
	}
	echo "<p></p>
		</div>
		</div>
		</td>";	
}

include "frame.html";
?>
<style>
.greybox {padding:8px;background:lightblue;font:normal bold 1.4em arial,sans-serif;width:205px;}
.whitebox {padding:5px;background:white;font:normal bold 0.7em arial,sans-serif;margin-top:7px;}
</style>


<table><tr><td valign=top>

<div class='greybox' >
Revenue
<div class='whitebox' >
<a href='reports-revenue-client.php'>Revenue Report by Client</a><p>
<a href='reports-revenue.php'>Revenue Report by Service Type</a><p>
<a href='reports-revenue-monthly.php'>Revenue from Monthly Contracts</a><p>
<a href='reports-revenue-zip.php'>Revenue Report by ZIP Code</a><p>
<? if($_SESSION['referralsenabled']) { ?>
<a href='reports-revenue-referral.php'>Revenue Report by Referral Type</a><p>
<? } ?>
<a href='reports-payments.php'>Payments Report</a><p>
<a href='reports-refunds.php'>Refunds Report</a><p>
<a href='reports-surcharges.php'>Surcharges Report</a><p>
<a href='reports-tax-liability.php'>Tax Liability Report</a>
<? if($_SESSION['orgptr'] == 1) {// Fetch only ?>
<p><a href='reports-royalty.php'>Royalty Report</a>
<? } ?>
<? if(TRUE/*dbTEST('doggiewalkerdotcom,mobilemutts,mobilemuttsnorth,themonsterminders,canineadventure,azcreaturecomforts,queeniespets')*/) {// Select group for now ?>
<p><a href='reports-year-over-year.php'>Year Over Year Report</a>
<? } ?>
</div>
</div>
</td><td valign=top>
<div class='greybox' >
Sitters
<div class='whitebox' >
<a href='mailing-labels.php?targetType=provider' title='Mailing labels for sitters'>Sitters - Mailing Labels</a><p>
<a href='reports-payroll.php'>Payroll Report</a><p></p>
<a href='reports-payroll-projection.php'>Projected Payroll Report</a><p></p>
<a href='reports-gratuity.php'>Gratuities Report</a>
<? if(staffOnlyTEST() || $db == 'bellyrubsleesburg') { ?>
<p></p><a href='report-email-validator.php'><?= $staffOnlyFlag ?>Verify Email Addresses</a>
<? } ?>
<? if($_SESSION['preferences']['sittersPaidHourly']) { ?>
<p></p><a href='reports-hourly-wage.php'><?= $optBiz ?>Hourly Wages</a>
<? } ?>
<p></p><a href='reports-workload.php'>Workload</a>
<p></p><a href='reports-performance.php'>Arrivals / Completions</a>
<? if(TRUE || $_SESSION['preferences']['donotserveenabled']) { ?>
<p></p><a href='reports-donotserve.php'>Do Not Serve Lists</a>
<? } ?>
<? if(TRUE) {  // timeoffreportenabled enabled for all 11/19/2020
?>
<p></p><a href='reports-sitter-time-off.php'>Sitter Time Off</a>
<? } ?>
</div>
</div>
<p>
<div class='greybox' >
Pets
<div class='whitebox' >
<a href='reports-client-pets.php'>Clients and Pets</a><p>
<a href='reports-birthdays.php'>Pet Birthdays</a>
</div>
</div>

</td>
<? //************************************** ?>
<td valign=top>


<div class='greybox' >
Clients
<div class='whitebox' >
<a href='mailing-labels.php?targetType=client' title='Mailing labels for clients'>Clients - Mailing Labels</a><p>
<a href='reports-balances.php'>Account Balances Report</a><p>
<a href='reports-schedules-recurring.php'>Clients with Recurring Schedules</a><p>
<a href='reports-discounts-client.php'>Client Discounts</a><p>
<a href='reports-credit-cards.php'>Client Credit Cards</a><p></p>
<a href='reports-client-psa.php'>Client Pet Service Agreements</a><p>
<a href='reports-client-dropouts.php'>Clients Without Service</a><p>
<a href='reports-visits-raw.php'>Visits Detailed Analysis</a><p>
<a href='reports-client-custom-rates.php'>Clients With Custom Charges</a><p>
<? if(staffOnlyTEST() || dbTEST('bellyrubsleesburg')) { ?>
<p></p><a href='report-email-validator.php?clients=1'><?= $staffOnlyFlag ?>Verify Email Addresses</a>
<? } ?>
<? if(staffOnlyTEST() || $_SESSION['preferences']['enableKeyLogReport']) { /*  dbTEST('pawlosophy') */ ?>
<p></p><a href='reports-key-log.php'><?= $staffOnlyFlagOrOptBiz ?>Key Log</a>
<? } ?>
<? if(staffOnlyTEST()) { /*  dbTEST('pawlosophy') */ ?>
<p></p><a href='reports-prospect-latency.php'><?= $staffOnlyFlagOrOptBiz ?>Prospect Latency</a>
<? } ?>
</div>

</td></tr>

<tr><td valign=top>
<div class='greybox' >
Exports (Text Spreadsheet)
<div class='whitebox' >
<a href='export-clients.php?fields=full' title='All fields'>Clients - Full Export</a><p>
<a href='export-clients.php' title='Typical Address Book fields'>Clients - Address Book</a><p></p>
<a href='export-providers.php?fields=full' title='All fields'>Sitters - Full Export</a><p>
<a href='export-providers.php' title='Typical Address Book fields'>Sitters - Address Book</a><p></p>
</div>
</div>
<div class='greybox' >
Exports (XML Spreadsheet)
<div class='whitebox' >
<?
// introduce new report versions using better formatting
$reportsClientsPetsExport = FALSE && staffOnlyTEST() ? "reports-clients-export-excel.php" : "reports-clients-export-xml.php";

?>
<a href='<?= $reportsClientsPetsExport ?>'>Clients / Pets (separate sheets)</a><p>
<a href='reports-vets-export-xml.php' title='All fields'>Veterinarians / Clinics (2 sheets)</a><p>
</div>
</div>
</td>

<? $commReports = in_array('tblreminder', fetchCol0("SHOW TABLES"));
if(commReports) {
?>	
<td valign=top>
<div class='greybox' >
Communication
<div class='whitebox' >
<a href='reports-reminders.php' title='Show delivered reminders'>Delivered Reminders</a><p>
<a href='reports-email-outbound.php'>Outbound Email Report</a><p>
<? if($_SESSION['preferences']['enableMessageArchiveFeature']) { ?>
<a href='reports-email-archived.php'><?= $optBiz ?>Archived Messages</a><p>
<?	
}
require_once "survey-fns.php";
if(surveysAreEnabled() && adequateRights('#rs')) { ?>
<a href='reports-survey-submissions.php'>Survey Submissions</a><p>
<? } ?>
</div>
</div>
</td>
<?	
}
?>
<td valign=top>
<div class='greybox' >
Miscellaneous
<div class='whitebox' >
<a href='reports-logins.php' title='Show recent logins'>Recent Logins</a><p>
<? if(staffOnlyTEST()) { ?>
<a href='reports-dispatcher-rights.php' title='Show Dispatcher Permissions'><?= $staffOnlyFlag ?>Dispatcher Permissions</a><p>
<? } ?>
</div>
</div>
</td>
</tr>

<tr>
<? customReportsBox(); ?>
<? if(staffOnlyTEST()) { ?>
<td valign=top>
<div class='greybox' >
Leashtime-Only Reports
<div class='whitebox' >
<a href='reports-balances-internal.php'>Account Balances LEASHTIME Report</a><p>
<a href='reports-ccpayments-internal.php'>CC Payments LEASHTIME Report</a><p>
<a href='reports-all-payments-internal.php'>All Payments LEASHTIME Report</a><p>
<a href='reports-recent-logins.php'>Recent Manager Login Activity</a><p>
<a href='check-paypal.php'>Check PayPal Payments</a><p>
<a href='reports-prospect-logins.php'>Prospect Logins (2 most recent months)</a><p>
<a href='reports-merchant-gateways.php'>Merchant Gateway Users</a>
</div>
</div>
</td>
<td valign=top>
<div class='greybox' >
Diagnostic Reports
<div class='whitebox' >
<a href='reports-email-outbound-staffonly.php'>STAFF Outbound Email Report</a><p>
<a href='reports-invoices.php'>Invoices</a><p>
<a href='email-queue.php'>Email Queue</a><p>
<a href='cc-transaction-history-multi.php?bydate=1'>Recent Electronic Transactions</a><p>
<a href='reports-paypal-payments.php'>Recent PayPal Payments</a><p>
<a href='reports-overbooking.php'>Client Overbooking</a><p>
</div>
</div>
<p>
<div class='greybox' >
Staff Only Test Reports
<div class='whitebox' >
<a href='reports-monthly-billables.php'>Monthly Billables</a><p>
<a href='reports-schedules-nonrecurring.php'>Clients with Nonrecurring Schedules</a><p>
<a href='reports-sitter-activity.php'>Sitter Activity</a><p>
<a href='reports-possible-client-overbookings.php'>Possible Client Overbookings</a><p>
<a href='reports-login-diagnostics.php'>Login Troubleshooter</a><p>
<a href='reports-surcharges-raw.php'>Raw Surcharges</a><p>
<a href='reports-sms-usage.php'>SMS Usage</a><p>
<a href='reports-remote-files-usage.php'>Client Documents Usage</a><p>
</div>
</td>

<? } ?>
</tr>

</table>
<img src='art/spacer.gif' width=1 height=300>
<script src='common.js'></script>
<?
// ***************************************************************************
include "frame-end.html";
