<? // reports-client-psa.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";
require_once "field-utils.php";
require_once "agreement-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('sort,inactive,print', $_REQUEST));

		
$pageTitle = "Client Pet Service Agreements".($inactive ? " (Inactive Clients)" : '');


if(!$print) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
	echo "Generated ".longestDayAndDateAndTime()."<p>";
?>
	<form name='reportform' method='POST'>
<?
	//echoButton('', 'Generate Report', 'genReport()');
	//echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	echoButton('', 'Print Report', "spawnPrinter()");
	//echo "&nbsp;";
	//echoButton('', 'Export to Excel', "alert(\"Coming soon...\")");
	echo "&nbsp;";
	if($inactive)
		fauxLink('Show Active Clients', 'document.location.href="reports-client-psa.php"');
	else 
		fauxLink('Show Inactive Clients', 'document.location.href="reports-client-psa.php?inactive=1"');
?>
	</form>
	<script language='javascript' src='common.js'></script>
	<script language='javascript'>
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm()) {
			openConsoleWindow('reportprinter', 'reports-client-psa.php?print=1&inactive=<?= $inactive ?>', 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else {
	$windowTitle = $pageTitle;
	//require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>$pageTitle</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
}

$orderBy = "lname, fname";
if($sort) {
	$sortParts = explode('_', $sort);
	$orderBy = join(' ',$sortParts);
	if($sortParts[0] == 'citystate') $orderBy .= ", lname, fname";
}

$activeFilter = $inactive ? '0' : '1';

$clients = fetchAssociationsKeyedBy(
	"SELECT clientid, CONCAT_WS(' ', fname, lname) as clientname, userid
		FROM tblclient 
		LEFT JOIN tblcreditcard ON clientptr = clientid  and tblcreditcard.active = 1
		WHERE tblclient.active = $activeFilter
		ORDER BY $orderBy", 'clientid');
		
foreach($clients as $client)
	if($client['userid']) $userids[] = $client['userid'];
	
if($userids) {	
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$userids = join(',', $userids);
	$agreements = fetchAssociationsKeyedBy("SELECT userid, agreementptr, agreementdate FROM tbluser WHERE userid IN ($userids)", 'userid');	
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 'force');
}
if($agreements) {
	foreach($clients as $clientid => $client)
		if(isset($agreements[$client['userid']]))
			foreach($agreements[$client['userid']] as $k => $v)
				$clients[$clientid][$k] = $v;
}
if($_POST) {
}

$rows = array();
$columns = explodePairsLine("clientname|Client||signed|Signed||agreementdate|Date||version|Version");
//$columnSorts = array('clientname'=>null, 'agreementdate'=>null);
if($sort) {
	$sort = explode('_', $sort);
	$columnSorts[$sort[0]] = $sort[1];
}

if($message) echo "<p class='tiplooks' style='text-align:left'>$message</p>";

$versions = getServiceAgreements();
foreach($clients as $i => $client) {
	$client['clientname'] = clientLink($client['clientname'], $client['clientid'], ($client['cardstatus'] == 'cardnotneeded'));
	if(!$client['userid']) $client['clientname'] .= ' [no login]';
	if($client['agreementptr']) {
		$client['agreementdate'] = shortDate(strtotime($client['agreementdate']));
		$client['version'] = agreementLink($client['agreementptr']);
		$client['signed'] = 'Yes';
	}
	else {
		$client['signed'] = 'No';
		$client['agreementdate'] = '';
	}

	$clients[$i] = $client;
	
	//$rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	//echo "<tr class= '$rowClass'><td style='padding-top:5px;font-size:1.1em'>";
	//if($mailsetup && $client['email']) echo "<input type='checkbox' id='client_{$client['clientid']}' name='client_{$client['clientid']}'> 
	//<label for='client_{$client['clientid']}'>";
	//echo "{$client['clientname']}<p>";
	//if($mailsetup && $client['email']) echo "</label>";
	//dumpPackageDescription($schedules[$client['clientid']]);
	//echo "</td></tr>";
}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {

function agreementLink($version, $greyed=null) {
	global $versions;
	$label = $versions[$version]['label'];
	$title = 'Version date: '.shortDate(strtotime($versions[$version]['date']));
	$class = $greyed ? 'class="greyed"' : '';
	return fauxLink($label, "viewAgreement($version)", 1, $title);
	//"<a $class href='service-agreement-terms.php?version=version'>$name</a>";
}

function clientLink($name, $clientid, $greyed) {
	$class = $greyed ? 'class="greyed"' : '';
	return "<a $class href='client-edit.php?tab=basic&id=$clientid'>$name</a>";
}

function jumpButton($label, $anchor) {
	//function echoButton($id, $label, $onClick='', $class='', $downClass='', $noEcho=false, $title=null) {

	return echoButton('', $label, "document.location.href=\"#$anchor\"", null, null, 1);
}

function sectionHead($anchor, $content) {
	return array('#CUSTOM_ROW#'=>"<tr><td colspan=4 style='font-weight:bold;font-size:1.1em;background:lightblue;'><a name='$anchor'></a>$content</td></tr>");
}

?>
<style>
a.greyed:link {color:gray;}
a.greyed:visited {color:gray;}
a.greyed:hover {color:gray;}
a.greyed:active {color:gray;}
</style>
<?
$rows = array();
$signedSectionButton = jumpButton('Agreements Signed', 'agreements');
$noAgreementsSectionButton = jumpButton('No Agreements Signed', 'noagreements');
$rows[] = sectionHead('noagreements', "Clients who have not signed a Pet Service Agreement.  - Jump to: $signedSectionButton");
$rowClasses[] = null;
foreach($clients as $clientid => $client) {
	if(!$client['agreementptr']) {
		$rowClasses[] = ($rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN');
		$rows[] = $client;
	}
}
$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=4>&nbsp;</td></tr>");
$rows[] = sectionHead('agreements', "Clients who have signed a Pet Service Agreement. - Jump to: $noAgreementsSectionButton");
$rowClasses[] = null;
$rowClasses[] = null;
foreach($clients as $i => $client) {
	$rowClasses[] = ($rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN');
	if($client['agreementptr']) {
		$rowClasses[] = ($rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN');
		$rows[] = $client;
	}
}

tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, $columnSorts=null, $rowClasses);
//echo "</table>";
//if($mailsetup) echo "</form>";

	
if(!$print){
	echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
	include "frame-end.html";
}
else {
?>
	<script language='javascript'>
	function printThisPage(link) {
		link.style.display="none";window.print();
	}
	</script>
<?
}


?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function viewAgreement(version) {
	$.fn.colorbox({href: "service-agreement-terms.php?version="+version, width:"800", height:"470", iframe:true, scrolling: "auto", opacity: "0.3"});
}	

function selectAll(state) {
	var sels=[];
	var allEls = document.getElementsByTagName('input');
	for(var i=0;i<allEls.length;i++)
		if(allEls[i].id.indexOf('client_') == 0)
			allEls[i].checked = (state ? 1 : 0);
}

function emailSchedules() {
	var ok = false;
	var allEls = document.getElementsByTagName('input');
	for(var i=0;i<allEls.length;i++)
		if(allEls[i].id.indexOf('client_') == 0)
			if(allEls[i].checked) ok = true;
	if(!ok) alert('Please select at least one client first.');
	else document.mailschedules.submit();
}
	
function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	var clients = '<?= $clients ?>';
	document.location.href='reports-client-pets.php?sort='+sortKey+'_'+direction;
}
</script>

<?
function dumpPackageDescription($schedule, $appts=null) {
	global $print;
	$charge = $schedule['weeklyadjustment'] ? $schedule['weeklyadjustment'] : 0;
	//echo "SELECT servicecode, charge, adjustment FROM tblservice WHERE packageptr = {$schedule['packageid']}<p>[[".print_r($services,1)."]]";	
	$services = fetchAssociations("SELECT * FROM tblservice WHERE packageptr = {$schedule['packageid']}");
	if(!$schedule['monthly'] && !$appts) foreach($services as $service) {
		//$serviceNames[] = getServiceName($service['servicecode']);
		$charge += count(daysOfWeekArray($service['daysofweek']))*($service['charge'] + $service['adjustment']);
	}
	else if(!$schedule['monthly']) foreach($appts as $appt) $charge += $appt['charge']+$appt['adjustment'];
	else $charge = $schedule['totalprice'];
	$charge = dollarAmount($charge);
	$billing = $schedule['monthly'] ? 'Monthly Fixed' : 'Regular Per-Visit';
	//sort($serviceNames);
	//$serviceNames = join(', ',array_unique($serviceNames));
	$start = shortDate(strtotime($schedule['startdate']));
	echo "<table width='100%'><tr><td>Start Date: $start</td>".
			 "<td>Billing: <b>$billing</b></td><td>Est. Charge for Month: $charge</td></tr></table>";
	recurringPackageSummary($schedule['packageid']);		 
	if($print) echo "<hr>";
}

function dumpCalendarStyle() {
	echo "<style>
.daycalendartable { /* whole daycalendar table */
	border: solid black 1px;
	width:100%;
	border-collapse: separate;
}

.daycalendardaterow { /* daycalendar td which displays date */
	background:lightblue;
	text-align:center;
	border: solid black 1px;
	font-weight:bold;
}

.daycalendartodcell {/* daycalendar td which displays block for a time of day */
	vertical-align:top;
	border-left-width: 1px;
	border-left-color: black;
	border-left-style: solid;
}
	
.daycalendartodcellFIRST {/* daycalendar td which displays block for a time of day */
	vertical-align:top;
}
	
.daycalendartodcelltable {/* table contained by  daycalendartodcell*/
  width: 100%;
	margin-left:auto; 
	margin-right:auto;
	border-collapse: separate;
}
	
.daycalendarobjectcell {/* cell contained by daycalendartodcelltable*/
	border: solid black 1px;
}

.daycalendarobjectcellborderless {/* cell contained by daycalendartodcelltable*/
	border: solid black 0px;
}

.daycalendarappointmentcomplete{/* table contained by daycalendarobjectcell which displays a completed appointment */
  width:100%;
	background: lightgreen;
}

.daycalendarappointmentcanceled {/* table contained by daycalendarobjectcell which displays a canceled appointment */
  width:100%;
	background: pink;
}

.daycalendarappointmentnoncompleted {/* table contained by daycalendarobjectcell which displays a completed appointment */
  width:100%;
	background: #FFFF66;;
}

.daycalendarappointmenthighpriority {
  width:100%;
	border: solid red 4px;
}


.daycalendarappointment {/* table contained by daycalendarobjectcell which displays an appointment */
  width:100%;
	background: lightyellow;
}

.daycalendarappointmentcomplete td , .daycalendarappointment td,
.daycalendarappointmenthighpriority td, .daycalendarappointmentcanceled td
.daycalendarappointmentnoncompleted td {
	padding: 2px;
	font-size:1.0em;
}

.daycalendarsubrow {/* daycalendartodcell td which displays subsection, such as provider */
	background:palegreen;
	font-weight:bold;
	/*border: solid black 1px;*/
}

.daycalendartodheader {/* daycalendar td which displays time of day header */
	font-style:normal;
	text-align:center;
	width: 25%;
}
</style>";
}