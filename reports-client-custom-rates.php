<? // reports-client-custom-rates.php

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




if($_REQUEST['id']) {
	$charges = fetchAssociations(
		"SELECT label, r.charge, 
				if(r.charge = -1, '--', r.charge) as charge, 
				if(r.extrapetcharge = -1, '--', r.extrapetcharge) as extrapetcharge, 
				if(r.taxrate = -1, '--', r.taxrate) as taxrate, 
				CONCAT_WS(' ', fname, lname) as clientname
			FROM relclientcharge r
			LEFT JOIN tblclient ON clientid = clientptr
			LEFT JOIN tblservicetype ON servicetypeid = servicetypeptr
			WHERE clientptr = {$_REQUEST['id']}
			ORDER BY label");
	echo "<h2>{$charges[0]['clientname']}'s Custom Charges</h2>";
	$columns = explodePairsLine('label|Service||charge|Charge||extrapetcharge|Extra Pet||taxrate|Tax');
	tableFrom($columns, $charges, 'WIDTH=100% border=1 bordercolor=black', null, null, null, null, $columnSorts=null, $rowClasses);
	exit;
}
		
$pageTitle = "Active Clients With Custom Charges";

if(!($_REQUEST['csv'])) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	include "frame.html";
	// ***************************************************************************
	echo "Generated ".longestDayAndDateAndTime().".  Click on a &#9654; to take a peek.<p>";
	echo "<a href='?csv=1'>Spreadsheet</a><p>";
	?>

	<script language='javascript' src='ajax_fns.js'></script>
	<script language='javascript'>
	function peek(clientid) {
		ajaxGet("reports-client-custom-rates.php?id="+clientid, 'detail')
	}
	</script>
<? }
function dumpCSV() {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-custom-prices.csv ");
	dumpCSVRow("Client Custom Prices");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	$cols['clientid'] = 'clientid';
	$cols['clientname'] = 'Client';
	foreach(fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype ORDER BY active DESC, label") as $k => $label)
		$cols[$k] = $label;
	dumpCSVRow($cols);
	$sql = "SELECT clientid, CONCAT_WS(' ', fname, lname) as Client, CONCAT_WS(', ', lname, fname) as sortname, label, r.charge, 
			r.extrapetcharge, r.taxrate
		FROM relclientcharge r
		LEFT JOIN tblclient ON clientid = clientptr
		LEFT JOIN tblservicetype ON servicetypeid = servicetypeptr
		WHERE tblclient.active = 1
		ORDER BY sortname";
//print_r(fetchAssociations($sql));exit;		
	$result = doQuery($sql);
	while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
//print_r($row);echo "<p>";
		$row[$row['label']] = $row['charge'];
		foreach($row as $k=>$v) $rows[$row['clientid']][$k] = $v; 
	}
	foreach($rows as $row)
		dumpCSVRow($row, $cols);
}
		
function dumpCSVRow($row, $cols=null) {
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

		
if($_REQUEST['csv']) {
	dumpCSV();
	exit;
}

$clients = fetchAssociationsKeyedBy(
	"SELECT clientid, CONCAT_WS(' ', fname, lname) as clientname, CONCAT_WS(' ', lname, fname) as sortname
		FROM relclientcharge 
		LEFT JOIN tblclient ON clientid = clientptr
		WHERE tblclient.active = 1
		ORDER BY sortname", 'clientid');
		

$rows = array();
$columns = explodePairsLine("clientname|Client");

if($message) echo "<p class='tiplooks' style='text-align:left'>$message</p>";

$rows = array();
foreach($clients as $i => $client) {
	$clientLink = clientLink($client['clientname'], $client['clientid'], ($client['cardstatus'] == 'cardnotneeded'));

	$rows[$i] = array('clientname'=>"$clientLink <span style='cursor:pointer;' title='Take a peek' onclick='peek({$client['clientid']})'>&#9654;</span>");
	
	//$rowClass = $rowClass == 'futuretaskEVEN' ? 'futuretask' : 'futuretaskEVEN';
	//echo "<tr class= '$rowClass'><td style='padding-top:5px;font-size:1.1em'>";
	//if($mailsetup && $client['email']) echo "<input type='checkbox' id='client_{$client['clientid']}' name='client_{$client['clientid']}'> 
	//<label for='client_{$client['clientid']}'>";
	//echo "{$client['clientname']}<p>";
	//if($mailsetup && $client['email']) echo "</label>";
	//dumpPackageDescription($schedules[$client['clientid']]);
	//echo "</td></tr>";
}


function clientLink($name, $clientid, $greyed) {
	$class = $greyed ? 'class="greyed"' : '';
	return "<a title='Go to client&apos;s Billing tab' href='client-edit.php?tab=billing&id=$clientid'>$name</a>";
}


?>
<style>
a.greyed:link {color:gray;}
a.greyed:visited {color:gray;}
a.greyed:hover {color:gray;}
a.greyed:active {color:gray;}
</style>

<table><tr><td valign=top>
<?
tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, null, $columnSorts=null, $rowClasses);
?>
</td><td valign=top id='detail'></td></tr></table>

<?
echo "<br><img src='art/spacer.gif' width=1 height=300>";
// ***************************************************************************
include "frame-end.html";


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