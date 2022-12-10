<?
// prov-pay-history.php
require_once "prov-schedule-fns.php";

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "pay-fns.php";

// Determine access privs
$locked = locked('p-');

$max_rows = 25;

$maintenanceBlock = false; //dbTEST('houstonsbestpetsitters');
if($maintenanceBlock) {
	include "frame.html";
	echo "<h2>This page closed for maintenance.</h2>";
	include "frame-end.html";
	exit;
}

extract($_REQUEST);

$provider = $_SESSION["providerid"];

$providerName = getProviderShortNames("WHERE providerid = $provider");
$providerName = $providerName[$provider];


$pageTitle = "$providerName's Payments";

include "frame.html";
// ***************************************************************************

hiddenElement('providerid', $provider);
dumpProviderPayForm($provider);
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
setPrettynames('provider','Sitter','starting','Starting Date','ending', 'Ending Date');	

function checkAndSubmit() {
  if(MM_validateForm(
		  'provider', '', 'R',
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
		var starting = document.provschedform.starting.value;
		var ending = document.provschedform.ending.value;
		if(starting) starting = '&starting='+starting;
		if(ending) ending = '&ending='+ending;
    document.location.href='prov-own-schedule-list.php?x=1'+starting+ending;
	}
}


function setUpRoute() {
  if(!MM_validateForm(
		  'starting', '', 'isDate')) return;
	var starting = document.provschedform.starting.value;
	var ending = document.provschedform.ending.value;
	var provider = <?= $provider ?>;
	var message;
	if(!starting) message = "No starting date has been supplied.\nSet up today's Visit Route?";
	else if(ending != starting) message = "Set up Visit Route for "+starting+"?";
	if(message && !confirm(message)) return;
	openConsoleWindow('visitsheets', 'itinerary.php?provider='+provider+'&date='+starting,750,700);
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
  w.document.location.href=url;
  if(w) w.focus();
}

<?
dumpPopCalendarJS();
?>

function update(target, val) { // called by appointment-edit
	refresh(); // implemented below
}

showPayments();
</script>
<img src='art/spacer.gif' width=1 height=300>
<?
include "js-refresh.php";

// ***************************************************************************

include "frame-end.html";
?>
