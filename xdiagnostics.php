<? // xdiagnostics.php

//IMPORTANT: domain of this page must match that of page which logged in to the DB!  www.leashtime.com does NOT EQUAL leashtime.com
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

?>
<style>
.fauxlink {
	color:blue;
	text-decoration: underline;
	cursor: pointer;
}
</style>
<a href=index.php>Home</a><p>
You must be logged in to DEV to use this page.
<p>
<?
include "gui-fns.php";
fauxLink('View All Active Clients', 'openConsoleWindow("allclients", "all-clients-view.php?active=1",800,800)');
echo "<br>";
fauxLink('View All Inactive Clients', 'openConsoleWindow("allclients", "all-clients-view.php?active=0",800,800)');
echo "<p>";
fauxLink('Rollover Diagnostics', 'openConsoleWindow("rollover", "rollover-diagnostic.php",800,800)');
echo "<p>";
fauxLink('Zero-Rate Appts', 'openConsoleWindow("rollover", "diag-zero-rates.php",800,800)');
echo "<p>";
fauxLink('Duplicate Appointments', 'openConsoleWindow("rollover", "duplicates-report.php",800,800)');
echo "<p>";
fauxLink('Duplicate Current Billables', 'openConsoleWindow("rollover", "find-duplicate-billables.php",800,800)');
echo "<p>";
fauxLink('Compare Global WAGs', 'openConsoleWindow("compare", "compare-global-wag.php",800,800)');
echo "<p>";
fauxLink('Recent Visit Status Changes', 'openConsoleWindow("recentchanges", "appointment-status-diagnostics.php",800,800)');
echo "<p>";
fauxLink('Make Billables', 'document.location.href="make-billables.php"');
echo "<p>";
fauxLink('Recurring Clients', 'document.location.href="diag-find-noncurrent-only.php"');
echo "<p>";
fauxLink('Client Analysis', 'document.location.href="client-analysis.php"');
echo "<p>";
fauxLink('Error Log', 'document.location.href="log-viewer.php"');
echo "<p>";
fauxLink('Recent Emails', 'document.location.href="diag-recent-emails.php"');
echo "<p>";

echoButton('', 'Analyze Visit: ', 'analyzeVisit()');
?>
<input id='visitptr'>
<?
echo "<p>";

echoButton('', 'Multiple Visits: ', 'analyzeMultVisits()');
?>
<input id='multvisitptrs' size=80>
<?
echo "<p>";

echoButton('', 'Analyze Surcharge: ', 'analyzeSurcharge()');
?>
<input id='surchargeptr'>
<?
echo "<p>";

echoButton('', 'Analyze Package: ', 'analyzePackage()');
?>
<input id='packageid'>

<?
echo "<p>";
echoButton('', 'Analyze Credit: ', 'analyzeCredit()');
?>
<input id='creditid'>
<p>
Scratch:<p><textarea cols=80 rows=10></textarea>
<script language='javascript'>
function analyzeCredit() {
	var credit = document.getElementById("creditid").value;
	if(!credit) alert("Specify credit ID");
	else openConsoleWindow('creditanalyzer', "credit-analysis.php?id="+credit,800,900);
}

function analyzeSurcharge() {
	var visit = document.getElementById("surchargeptr").value;
	if(!visit) alert("Specify Surcharg ID");
	else openConsoleWindow('visitanalyzer', "surcharge-analysis.php?id="+visit,800,900);
}

function analyzeMultVisits() {
	var visit = document.getElementById("multvisitptrs").value;
	if(!visit) alert("Specify visit IDs");
	else openConsoleWindow('visitanalyzer', "appt-analysis.php?all="+visit,800,900);
}

function analyzeVisit() {
	var visit = document.getElementById("visitptr").value;
	if(!visit) alert("Specify visit ID");
	else openConsoleWindow('visitanalyzer', "appt-analysis.php?id="+visit,800,900);
}

function analyzePackage() {
	var packageid = document.getElementById("packageid").value;
	if(!packageid) alert("Specify package ID");
	else openConsoleWindow('packageanalyzer', "package-analysis.php?id="+packageid,800,900);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

</script>
