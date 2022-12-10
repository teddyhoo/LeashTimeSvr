<? // maint-amnesia.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');
if(!$_SESSION['whosyerdaddy']) {
	if($_POST) {
		if($_POST['answer'] =='baby') $_SESSION['whosyerdaddy'] = 1;
	}
	if(!$_SESSION['whosyerdaddy']) {
?><head><title>LeashTime STAFF: Amnesia</title></head><form method='POST'>Who's yer daddy? <input type=password name=answer> <input type='submit'></form>
<? //'
	exit;
	}
}

$windowTitle = 'What was I looking for again?';
include 'frame-maintenance.php';

?>
Useful scripts:<p>
<table border=1 bordercolor=black bgcolor=white>

<tr><th>Script<th>Params<th>Context<th>Description

<tr><td>kill-visit-data-before.php<td>date, invoicedate (YYYY-mm-dd)<td>Business Manager login
	<td>Deletes all visits, charges, billables, payables, payments, invoices, etc. BEFORE (not including) a given date
	
<tr><td>killNRPackage.php<td>id, descndents(bool), ancestors(bool) <td>Business Manager login
	<td>Deletes a NR package and optionally related NR packages
	
<tr><td>randomnames.php<td>(none)<td>Business Manager login
	<td>Randomly renames all clients, client contacts, and addresses.  Clears all client and provider userids.
	
<tr><td><a href='native-app-filespace-status.php'>native-app-filespace-status.php</a><td>(none)<td>Business Manager login
	<td><b>Use to review outboarding.  Use pet-photos-outboard.php (in separate sandbox) to set up outboarding.
	
<tr><td><a href='reports-deadwood.php'>reports-deadwood.php</a><td>(none)<td>Business Manager login
	<td><b>Use to choose MOTHBALL candidates to reclaim DISK SPACE
	
<tr><td><a href='message-archive-setup.php'>maint-archive-setup.php</a><td>(none)<td>Business Manager login
	<td><b>Use to choose set up message archive to reclaim DISK SPACE
	
<tr><td><a href='maint-reports-errorlog.php'>maint-reports-errorlog.php</a><td>(none)<td>Business Manager login
	<td><b>Use maint-dbs-modify to report on largest error tables.  Use reports-errors (from queue page) to prune email errors
	
<tr><td><a href=maint-mask-emails.php>maint-mask-emails.php</a><td>(none)<td>Business Manager login
	<td>Masks all emails to no unintentional emails are sent.
	
<tr><td>invoice-analysis.php</a><td>(none)<td>Business Manager login
	<td>Analyze an invoice
	
<tr><td>generate-monthly-billables.php</a><td>(none)<td>Business Manager login (staff only)
	<td>Generate Monthly Billables.  May need to hand-edit item date.
	
<tr><td><a href=import-bluewave.php>import-bluewave.php</a></a><td>(none)<td>Business Manager login (staff only)
	<td>Import bluewave data stored in s:/clientimports.
	
<tr><td>https://leashtime.com/cc-retire-all-cards.php<td>(none)<td>Business Manager login (staff only)
	<td>Retire cards entered through a gateway when a client changes gateways.
	
<tr><td><a href=client-login-setup.php>client-login-setup.php</a></a><td>(none)<td>Business Manager login (staff only)
	<td>Assign logins to clients based on email address.
	
<tr><td><a href=changelog.php>changelog.php</a></a><td>(none)<td>Business Manager login (staff only)
	<td>Query the change log.
	
<tr><td><a href=maint-notices.php>maint-notices.php</a></a><td>(none)<td>(Staff Only)
	<td>Manage user notices.
	
<tr><td><a href=compare-dbs-cmp-dbs.php?all=dogslife>compare-dbs-cmp-dbs.php?all=dogslife</a></a><td>(none)<td>(Staff Only)
	<td>Analyze db differences.  args: all or a, b.
	
<tr><td><a href=maint-flist.php?pat=*>maint-flist.php?pat=*</a></a><td>pat<td>(Staff Only)
	<td>List files.
	
<tr><td><a href=email-usage-overview.php>maint-flist.php?pat=*</a></a><td>pat<td>(Staff Only)
	<td>Show email usage for a business.
<tr><td><a href=vcard-phone-transfer.php>vcard-phone-transfer.php</a><td>(none)<td>(Staff Only)
	<td>VCard code developed for Dog Camp LA
<tr><td><a href=cc-usage-report.php?from=1/1/2011&to=>cc-usage-report.php?from=1/1/2011&to=</a><td>(none)<td>(Staff Only)
	<td>Show CC volume
<tr><td>email-queue.php<td>(none)<td>(Staff Only - log in to biz and type ".q" in search)
	<td>Manage the email queue
<tr><td><a href=connect-test.php>connect-test.php</a><td>(none)<td>Any context<td>Page for clients to test browser/OS compatibility with LeashTime.
<tr><td>PASSWORD OVERRIDE<td>(none)<td><td>From $_SERVER['REMOTE_ADDR'] == '68.225.89.173', you can login as anyone with password "passwordoverride"
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/maint-report-mobile-sitter-logins.php>maint-report-mobile-sitter-logins.php</a><td>(opt)since=a date<td>(Staff Only)<td>Mobile Sitter App usage
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/setup/purge-demo-db.php>setup/purge-demo-db.php</a><td><td>(Staff Only)<td>Purge contact info from a demo database
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/maint-log-report.php>maint-log-report.php</a><td><td>(Staff Only)<td>Activity (Log report)  *BROKEN*
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/zipcityedit.php>zipcityedit.php</a><td><td>(Staff Only)<td>Edit City names in the ZIP code database
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/maint-test-db-manager.php>maint-test-db-manager.php</a><td><td>(Staff Only)<td>Maintain (delete) TEST databases
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/client-ui-sample.php>client-ui-sample.php</a><td><td>(Staff Only)<td>Fast cycle through customer UI's<br>(counts down -- https://<?= $_SERVER["HTTP_HOST"] ?>/client-ui-sample.php?bizid=343&min=200)
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/maint-silent-businesses.php>maint-silent-businesses.php</a><td><td>(Staff Only)<td>Silent (inactive) Business report -- no recent logins
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/hundred-k-mod-sql.txt>hundred-k-mod-sql.txt</a><td><td>(Staff Only)<td>Hundred K Mod -- allow amounts up to $99,999.99
<tr><td><a href=https://<?= $_SERVER["HTTP_HOST"] ?>/maint-link-user.php>maint-link-user.php</a><td><td>(Staff Only)<td>Link owners of separate businesses
<tr><td>https://leashtime.com/cc-retire-all-cards.php<td>(none)<td>Business Manager login (staff only)
	<td>Retire cards entered through a gateway when a client changes gateways.
	
	
</table>	
