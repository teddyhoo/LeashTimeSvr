<? // discounted-visits.php
$pageTitle = "Discounted Visits";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "gui-fns.php";
include "discount-fns.php";
include "client-fns.php";
include "invoice-fns.php";
locked('o-');

$max_rows = 999;

$columnSorts = null;
extract(extractVars('client', $_REQUEST));

if($_POST && $_POST['action'] == 'delete') {
	$appts = array();
	foreach($_POST as $k => $v) {
		if(strpos($k, 'cb_') === 0) {
			$parts = explode('_', $k);
			$appts[] = $parts[2];
		}
	}
	if($appts) {
		deleteTable('relapptdiscount', "appointmentptr IN (".join(',', $appts).")", 1);
		foreach($appts as $appointmentid) recreateAppointmentBillable($appointmentid);
	}
	$message = "Deleted ".count($appts)." discounts.";
}

if($sort) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  $sort = "$sort_key $sort_dir";
  if($sort_key != 'label') $sort .= ", label ASC";
}

$clientNames = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient WHERE active");
if($client) 
	$breadcrumbs .= "- <a href='client-edit.php?tab=services&id=$client'>{$clientNames[$client]}'s Services Page</a>";


// ***************************************************************************
include "frame.html";

if($message) echo "<font color=green>$message</font><p>";

$options = array('-- Choose a client --'=>0, '-- All Clients --'=>-1);
foreach($clientNames as $id=>$name)
	$options[$name] = $id;
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {
?>
<form name='x' method='POST'>
<?
selectElement('Client:', 'client', $client, $options, 'document.x.submit()', $labelClass=null, $inputClass=null, $noEcho=false);
hiddenElement('action', '');


if($client) {
	$columns = "date|Date||timeofday|Time Window||charge|Charge||amount|Discount||type|Type||service|Service||pets|Pets";
	if($client != -1) $oneClient = "AND tblappointment.clientptr = $client";
	else $columns = "client|Client||$columns";

	$columns = explodePairsLine("cb| ||$columns");
	$colKeys = array_keys($columns);
	$appts = fetchAssociations(  // "appointmentid IS NOT NULL" was added because of orphaned relapptdiscount rows
			"SELECT relapptdiscount.*, tblappointment.*
					FROM relapptdiscount
					LEFT JOIN tblappointment ON appointmentid = appointmentptr
					LEFT JOIN tblbillable ON itemptr = appointmentid AND itemtable = 'tblappointment'
					WHERE appointmentid IS NOT NULL AND (itemptr IS NULL OR paid = 0 OR paid IS NULL) $oneClient
					ORDER BY tblappointment.date, starttime, tblappointment.clientptr");
	$data = array();
	$rowClasses = array();
	if($appts) {
		$clients = array();
		$discounts = array();
		foreach($appts as $appt) {
			$clients[] = $appt['clientptr'];
			$discounts[] = $appt['discountptr'];
		}
		$discounts = fetchKeyValuePairs("SELECT discountid, label FROM tbldiscount WHERE discountid IN (".join(',',$discounts).")");
		if($client == -1) $clients = getClientDetails($clients);
		foreach($appts as $appt) {
			$datum = $appt;
			$cb = "cb_{$appt['clientptr']}_{$appt['appointmentid']}";
			$datum['cb'] = "<input type='checkbox' id='$cb'  name='$cb'>";
			$datum['client'] = "<label for='$cb'>{$clients[$appt['clientptr']]['clientname']}</label>";
			$datum['date'] = "<label for='$cb'>".shortDate(strtotime($appt['date']))."</label>";
			$datum['charge'] = dollarAmount($appt['charge']+$appt['adjustment']);
			$datum['amount'] = "<span style='cursor:pointer'>".dollarAmount($appt['amount'])."</span>";
			$datum['type'] = addslashes($discounts[$appt['discountptr']]);
			$datum['service'] = $_SESSION['servicenames'][$appt['servicecode']];
			$data[] = $datum;
			$rowClasses[] = 'futuretask';
		}
	}
	if(!$data) echo "<p>No Discounted Appointments found.";
	else {
		echo "<p>";
		fauxLink('Select All', 'checkAll(1)');
		echo "<img src='art/spacer.gif' width=20 height=1>";
		fauxLink('Un-Select All', 'checkAll(0)');
		echo "<img src='art/spacer.gif' width=20 height=1>";
		echoButton('', 'Delete Selected Discounts', 'deleteSelections()');
		tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses);
	}
}
?>
</form>
<?

include "refresh.inc";				

?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

function update(aspect, value) {
	refresh();
}

function checkAll(on) {
	var inputs = document.getElementsByTagName('input');
	for(var i=0;i < inputs.length; i++)
		if(inputs[i].type == 'checkbox' && inputs[i].id.indexOf('cb_') == 0)
			inputs[i].checked = (on ? true : false);
}

function deleteSelections() {
	var inputs = document.getElementsByTagName('input');
	var numselections = 0;
	for(var i=0;i < inputs.length; i++)
		if(inputs[i].type == 'checkbox' && inputs[i].id.indexOf('cb_') == 0 && inputs[i].checked)
			 numselections ++;
	if(numselections == 0)
		alert('Please select one or more discounts to delete first.');
	else {
		if(confirm('Are you sure you want to delete the '+numselections+' selected discount'+(numselections == 1 ? '' : 's')+'?')) {
			document.getElementById('action').value='delete';
			document.x.submit();
		}
	}
}
</script>
<p><img src='art/spacer.gif' height=300>
<?
include "frame-end.html";
