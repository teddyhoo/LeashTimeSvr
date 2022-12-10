<? // reports-schedules-sitter-logins.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "js-gui-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('sort,start,end,print,mailsetup,csv,provider', $_REQUEST));

		
$pageTitle = "Sitter Logins";

$sitters = getAllProviderSelections($availabilityDate=null, $zip=null, $separateActiveFromInactive=true);
if($_POST) {
	$sitterUserIds = fetchAssociationsKeyedBy(
			"SELECT userid, fname, lname, providerid,
				CONCAT_WS(', ', lname, fname) as sortname,  CONCAT_WS(' ', fname, lname) as name
				FROM tblprovider
				WHERE userid IS NOT NULL", 'userid');
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	foreach(fetchKeyValuePairs(
			"SELECT userid, loginid 
				FROM tbluser 
				WHERE userid IN (".join(',', array_keys($sitterUserIds)).")")
					as $userid => $loginid) {
		$sitterUserIds[$userid]['loginid'] = $loginid;
		if(!$provider || $provider == $sitterUserIds[$userid]['providerid'])
			$loginids[strtoupper($loginid)] = $userid;
	}
	$startlogin = date('Y-m-d 00:00:00', strtotime($start));
	$endlogin = date('Y-m-d 23:59:59', strtotime($end));
	$logins = fetchAssociations($sql = 
			"SELECT TRIM(loginid) as loginid, LastUpdateDate as logintime
				FROM tbllogin
				WHERE LastUpdateDate >= '$startlogin' AND LastUpdateDate <= '$endlogin'
					AND Success = 1
					AND loginid IN ('".join("','", array_keys($loginids))."')
				ORDER BY LastUpdateDate");
	foreach($logins as $i => $login)
		$logins[$i]['name'] = $sitterUserIds[$loginids[strtoupper($login['loginid'])]]['name'];

	//print_r($sql);
}



$orderBy = "lname, fname";
if($sort) {
	$sortParts = explode('_', $sort);
	$orderBy = join(' ',$sortParts);
	if($sortParts[0] == 'citystate') $orderBy .= ", lname, fname";
}

			
if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";
	$extraHeadContent = "<style>.old {color:red;}</style>";
	include "frame.html";
	// ***************************************************************************
	if($logins) echo "Generated ".longestDayAndDateAndTime()."<p>";
?>
	<form name='reportform' method='POST'>
<?
	//echoButton('', 'Generate Report', 'genReport()');
	//echo "&nbsp;";
	$pstart = date('Y-m-d', strtotime($start));
	$pend = date('Y-m-d', strtotime($end));
	hiddenElement('csv', null);
	calendarSet('Starting:', 'start', $start, null, null, true, 'end');
	calendarSet('Ending:', 'end', $end, null, null, true, null);
	echoButton('', 'Show', "checkAndSubmit()");
	//echoButton('', 'Print Report', "spawnPrinter()");
	echo "&nbsp;";
	$finalOptions = array();
	foreach((array)$sitters as $k => $v) {
		if($k == 'Other Sitters') $k = 'Active Sitters';
		$finalOptions[$k] = $v;
	}
	$finalOptions = 
			array_merge(
				array('--All Sitters--' => ''), $finalOptions
				);
	selectElement('Sitter:', "provider", $provider, $finalOptions);

	echoButton('', 'Export to Excel', "genCSV()");
?>
	</form>
	<script language='javascript' src='common.js'></script>
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript' src='popcalendar.js'></script>
	<script language='javascript'>
	setPrettynames('end','Ending date', 'start', 'Starting date');
	function checkAndSubmit() {
	if(MM_validateForm(
		'start', '', 'R',
		'start', '', 'isDate',
		'end', '', 'R',
		'end', '', 'isDate'
		))
		document.reportform.submit();
}
	function genCSV() {
		document.getElementById('csv').value=1;
		document.reportform.submit();
		document.getElementById('csv').value=0;
	}
	
	function spawnPrinter() {
		//document.location.href='reports-revenue.php?print=1&start=$pstart&end=$pend&reportType=$reportType'>
		if(MM_validateForm()) {
			openConsoleWindow('reportprinter', 'reports-schedules-nonrecurring.php?print=1&inactive=<?= $inactive ?>', 700,700);
		}
	}
	
<? dumpPopCalendarJS(); ?>
	</script>
<?
} // if(!$print)
else if($csv) {
	function dumpCSVRow($row, $cols=null) {
//echo "R: ".print_r($row,1)."\nC: ".print_r($cols,1)."\n";
		if(!$row) echo "\n";
		if(is_array($row)) {
			if($cols) {
				$nrow = array();
				if(is_string($cols)) $cols = explode(',', $cols);
				foreach($cols as $k) $nrow[] = $row[$k];
				$row = $nrow;
			}
			echo join(',', array_map('csv',$row))."\n";
		}
		else echo csv($row)."\n";
	}

	function csv($val) {
		$val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
		$val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
		$val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
		return "\"$val\"";
	}

	header("Content-Type: text/csv");
	$specificType = $inactive ? 'Inactive' : 'Active';

	header("Content-Disposition: attachment; filename=Sitter-Logins.csv ");
	dumpCSVRow("Sitter Logins");//.str_replace('-', ' ', $specificType)
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	$columns = explodePairsLine('logintime|Login Time||loginid|Login ID||name|Sitter');
	dumpCSVRow($columns);
	$detailKeys = explode(',', 'daysofweek,timeofday,provider,pets,servicetype,status,basecharge,customcharge');
	foreach($logins as $login) {
		dumpCSVRow($login, array_keys($columns));
	}
	exit;
}  // END csv
else {
	$windowTitle = 'Clients with Nonrecurring Schedules';
	//require "frame-bannerless.php";
	fauxLink("<h1 style='display:inline;'>Click Here to Print</h1>", "printThisPage(this);");
	echo "<h2 style='text-align:center'>Clients with Recurring Schedules</h2>";
	echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')."</p>";
}

			
if( 0 && $_POST) {
	require_once "comm-fns.php";
	$msgcount = 0;
	foreach($_POST as $k => $v) {
		if(strpos($k, 'client_') !== FALSE) {
			$clientid = substr($k, strlen('client_'));
			$client = $clients[$clientid];
			$schedule = $schedules[$clientid];
			enqueueEmailNotification($client, "Next month's schedule", 
																makeMessage($client, $schedule), $cc=null, $_SESSION['auth_login_id'], $html=true, $originator=null);
			$msgcount++;
		}
	}
	$message = "Notifications sent: $msgcount";
}

$rows = array();
$columns = explodePairsLine("clientname|Client||phones|Telephone (primary phone in bold)||address|Address||citystate|City");
$columnSorts = array('clientname'=>null, 'citystate'=>null);
if($sort) {
	$sort = explode('_', $sort);
	$columnSorts[$sort[0]] = $sort[1];
}

if($message) echo "<p class='tiplooks' style='text-align:left'>$message</p>";

if($mailsetup) {
	echo "<form name='mailschedules' method='POST'><p>";
	fauxLink('Select All', 'selectAll(1)');
	echo " - ";
	fauxLink('Deselect All', 'selectAll(0)');
	echo " - ";
	echoButton('', 'Email Selected Schedules', 'emailSchedules()');
	echo "<p>";
}
else if(!$print) {
	echo "<p>";
	//echoButton('', 'Select Schedules to Email', 'document.location.href="reports-schedules-recurring.php?mailsetup=1"');
	echo "<p>";
}
if($logins) quickTable($logins, $extra=null, $style=null, $repeatHeaders=0);
if($mailsetup) echo "</form>";

//echo ">>>";print_r($allPayments);	
	
	//paymentsTable($clients, $sort);
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



function rowData($schedule, $csv=null) {
	$charge = $schedule['weeklyadjustment'] ? $schedule['weeklyadjustment'] : 0;
	//echo "SELECT servicecode, charge, adjustment FROM tblservice WHERE packageptr = {$schedule['packageid']}<p>[[".print_r($services,1)."]]";	
	$services = fetchAssociations("SELECT *, label FROM tblservice LEFT JOIN tblservicetype ON servicetypeid = servicecode WHERE packageptr = {$schedule['packageid']}");
	$serviceTypes = array();
	if(!$schedule['monthly'] && !$appts) foreach($services as $service) {
		//$serviceNames[] = getServiceName($service['servicecode']);
		$charge += count(daysOfWeekArray($service['daysofweek']))*($service['charge'] + $service['adjustment']);
		$serviceLabels[] = $service['label']." ({$service['daysofweek']})";
	}
	else if(!$schedule['monthly']) foreach($appts as $appt) $charge += $appt['charge']+$appt['adjustment'];
	else $charge = $schedule['totalprice'];
	$charge = sprintf("%.2f",$charge);
	$billing = $schedule['monthly'] ? 'Monthly Fixed' : 'Regular Per-Visit';
	$start = shortDate(strtotime($schedule['startdate']));
	return array('start'=>$start, 'billing'=>$billing, 'charge'=>$charge, 'service'=>join(', ', $serviceLabels));
}

function dumpPackageDescription($schedule, $appts=null) {
	global $print;
	static $currentPrices;
	if(!$currentPrices) $currentPrices =  fetchKeyValuePairs("SELECT servicetypeid, defaultcharge FROM tblservicetype");
	//echo "SELECT servicecode, charge, adjustment FROM tblservice WHERE packageptr = {$schedule['packageid']}<p>[[".print_r($services,1)."]]";	
	foreach(fetchAllAppointmentsForNRPackage($schedule, $schedule['clientptr']) as $appt) {
		if($appt['charge'] != $currentPrices[$appt['servicecode']]) $old[$appt['servicecode']] = $appt['charge'];
		$sum += $appt['charge'] + $appt['adjustment'];
		$servicecodes[] = $appt['servicecode'];
		$counts[$appt['servicecode']] += 1;
	}
	if(!$servicecodes) {
		return;
	}
	$services = fetchKeyValuePairs(
				"SELECT servicetypeid, label 
					FROM tblservicetype
					WHERE servicetypeid in (".join(', ', array_unique($servicecodes)).")
					ORDER BY label");
	foreach($services as $k => $v) {
		$v = "{$services[$k]}({$counts[$k]})";
		if($old[$k]) $services[$k] = "<span class='old' title='\${$old[$k]}, not \${$currentPrices[$k]}'>$v</span>";
	}
	$services = join(', ', array_unique($services));
	$charge = dollarAmount($sum); //$schedule['packageprice']);
	//sort($serviceNames);
	//$serviceNames = join(', ',array_unique($serviceNames));
	$start = shortDate(strtotime($schedule['startdate']));
	
	$start = fauxLink($start, 
		"openConsoleWindow(\"recurserv\", \"service-irregular.php?packageid={$schedule['packageid']}\", 750, 500);", 
		1, 'edit the schedule');
	
	$end = shortDate(strtotime($schedule['enddate']));
	$price = dollarAmount($schedule['totalprice']);
	echo "<table width='100%'><tr><td>$start - $end</td>".
			 "<td>Price: $charge</td><td>$services</td></tr></table>";
	//recurringPackageSummary($schedule['packageid'], 'showCharges');		 
	if($print) echo "<hr>";
}

function getCSVServiceRows($schedule, $appts=null) {
	$showCharges=true;
	$packageid = $schedule['packageid'];
  $package = getRecurringPackage($packageid);
  $services = getPackageServices($packageid);
	$providers = getProviderShortNames();
	$today = date('Y-m-d');
	if($package['cancellationdate']) {
		$status = 'Canceled as of: '.shortDate(strtotime($package['cancellationdate']));
	}
	if(!$status && $package['suspenddate']) {
		if($package['suspenddate'] <= $today && $package['resumedate'] > $today)
			$status = 'Suspended.  Resumes on '.shortDate(strtotime($package['resumedate']));
	}
	foreach($services as $service) {
		$row = array();
		$row['daysofweek'] = $service['daysofweek'];
		$row['timeofday'] = $service['timeofday'];
		$row['provider'] = $service['providerptr'] ? $providers[$service['providerptr']] : 'Unassigned';
		$row['pets'] = strip_tags($service['pets']);
		$row['servicetype'] = $_SESSION['servicenames'][$service['servicecode']];
		$row['status'] = $status ? $status : 'Active';
			// ACTIVE, SUSPENDED WILL RESUME ON MM DD YY, CANCELLED
		if($showCharges) {
			$row['basecharge'] = $service['charge'];
			$row['customcharge'] = fetchRow0Col0(
				"SELECT charge 
				FROM relclientcharge
				WHERE clientptr = {$service['clientptr']} AND servicetypeptr = {$service['servicecode']}");
			if(!$row['customcharge']) {
				$row['customcharge'] = fetchRow0Col0(
					"SELECT defaultcharge 
					FROM tblservicetype
					WHERE servicetypeid = {$service['servicecode']}");
			}
		}
		$rows[] = $row;
  }	
	return $rows;
}

function makeMessage($client, $schedule) {
	global $userRole, $displayOnly;
	$userRole = 'c';
	$displayOnly = true;
	require_once "day-calendar-fns.php";

	$starttime = strtotime("+1 month", strtotime(date('Y-m-01')));
	$start = date('Y-m-d', $starttime);
	$after = date('Y-m-d', strtotime("+1 month", $starttime));
	$history = findPackageIdHistory($schedule['packageid'], $client['clientid'], 1);
	if($history) 
		$appts = fetchAssociations("SELECT * FROM tblappointment 
																WHERE packageptr IN (".join(',', $history).") 
																	AND date >= '$start' AND date < '$after'
																ORDER BY date, starttime");
	ob_start();
	ob_implicit_flush(0);
	echo "<div style='width:700px;border:solid black 2px;'>";
	dumpPackageDescription($schedule, $appts);
	echo "</div>";
	echo '<p>';
	if($appts) {
		require_once "appointment-calendar-fns.php";
		echo '<link rel="stylesheet" href="https://leashtime.com/style.css" type="text/css" /> 
		<link rel="stylesheet" href="https://leashtime.com/pet.css" type="text/css" />
<style>
body {background:white;font-size:9pt;}
</style>'."\n";
		$schedule['startdate'] = $start;
		$schedule['enddate'] = date('Y-m-t', $starttime);
		dumpCalendarLooks(100, 'lightblue');
		echo "<div style='width:95%'>";
		appointmentTable($appts, $schedule, $editable=false, $allowSurchargeEdit=false, $showStats=false, $includeApptLinks=false, $surcharges=null);
		echo "</div>";
	/*
		require_once "client-schedule-fns.php";
		dumpCalendarStyle();
		clientCalendarTable($appts);
	*/
		
		
	}
	$data = ob_get_contents();
	ob_end_clean();
	return "Dear {$client['fname']} {$client['lname']},<p>
Here is your pet care schedule for ".date('F Y', $starttime).':<p>'.$data;
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