
<? // appt-analysis.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "credit-fns.php";
require_once "service-fns.php";
require_once "pay-fns.php";
require_once "provider-fns.php";
require_once "preference-fns.php";
require_once "gui-fns.php";
require_once "appointment-fns.php";


locked('o-');
if(!staffOnlyTEST()) {
	echo "LT STaff Use Only.<p><a href='index.php'>Home</a>";
	exit;
}

extract($_REQUEST);
echo "<head><title>Leashtime Appointment Analyzer</title></head>";
echo "<h2>Leashtime Client: {$_SESSION['preferences']['bizName']} ($db)</h2>";
if($all) {
	$appts = explode(',', $all);
	foreach($appts as $appt) {
		$label = $appt == $id ? "<b>$appt</b>" : $appt;
		echo "<a href='appt-analysis.php?id=$appt&all=$all'>$label</a> ";
	}
	echo "<p>";
}
if(!$id) exit;
$appt = getAppointment($id,1);
$color = $appt['canceled'] ? 'pink' : 'white';
?>
<div style='width:800px;border: solid black 1px;background:<?= $color ?>;'>
<?
$date = date('m/d/Y', strtotime($appt['date']));
$custom = $appt['custom'] ? ' <font color=red>custom</font></a> ' : '';
$status = $appt['completed'] ? " <font color=green>Completed: {$appt['completed']}</font></a> " : (
					$appt['canceled'] ? " <font color=red>Canceled: {$appt['canceled']}</font></a> " : ' incomplete ');
if($arrived = fetchRow0Col0("SELECT date from tblgeotrack WHERE appointmentptr = $id AND event = 'arrived' LIMIT 1", 1))
	$status .= " (arrived: $arrived)";
echo "Appointment: $id - $date - {$appt['starttime']}$custom$status [client: {$appt['client']}] - [provider: {$appt['provider']}]<br>";
$package = getPackage($appt['packageptr']);
$packageType = $package['monthly'] ? 'Monthly Recurring' :
							 ($package['onedaypackage'] ? 'One Day' :
							 ($package['enddate'] ? 'Nonrecurring' : 'Weekly Recurring'));
$appt['packageType']	= $packageType;           
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
$billables = fetchAssociations("SELECT * FROM tblbillable WHERE itemptr = $id AND itemtable = 'tblappointment' ORDER BY billableid DESC");
if(!$billables) echo "No Billable.";
else { foreach($billables as $billable) {
	$color = $billable['superseded'] ? 'lightgrey' : 'white';
	echo "<div style='background:$color;border-bottom:solid black 1px;'>\n";
	echo "Billable: {$billable['billableid']}<br>Charge: {$billable['charge']} (original total charge)<br>Tax: {$billable['tax']} (incl in total charge)<br>Paid: {$billable['paid']}<br>Still Owed: ".($billable['charge'] - $billable['paid'])
		."<br>Item Date: {$billable['itemdate']}<br>Billable Date: {$billable['billabledate']}\n<br>Invoiced: #{$billable['invoiceptr']}\n</div>";
}
$billable = $billables[0];
?>
</div>
<? if($disc = fetchFirstAssoc(
			"SELECT i.*, cat.amount as catamount, cat.ispercentage, label
				FROM relapptdiscount i
					LEFT JOIN tbldiscount cat ON discountid = discountptr
				WHERE appointmentptr = $id", 1)) {
?>
<div style='width:800px;border: solid black 1px;'>
<?
	echo "Discount: ".dollarAmount($disc['amount'])."<br>";
	echo "Type: {$disc['label']} ({$disc['discountptr']})<br>";
	$catAmount = $disc['ispercentage'] ? "{$disc['catamount']}%" : dollarAmount($disc['catamount']);
	echo "Category amount: $catAmount<br>";
}
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
$invoiceitems = fetchAssociations("SELECT relinvoiceitem.*, tblinvoice.* FROM relinvoiceitem LEFT JOIN tblinvoice ON invoiceid = invoiceptr WHERE billableptr = {$billable['billableid']}");
if(!$invoiceitems) echo "Not invoiced.";
else {
	foreach($invoiceitems as $invoiceitem)
		echo "<dt>Invoice: {$invoiceitem['invoiceptr']}<dd>Date: {$invoiceitem['date']}<dd>Charge: {$invoiceitem['charge']} (amount still owed on billable at Invoice time)<dd>Prepaid amount: {$invoiceitem['prepaidamount']}</dt>";
}
?>
</div>
<?
if($billable['paid']) {
$payments = fetchAssociations("SELECT tblcredit.* FROM relbillablepayment LEFT JOIN tblcredit ON creditid = paymentptr WHERE billableptr = {$billable['billableid']}");
if($payments) {
?>
<div style='width:800px;border: solid black 1px;'>
<?
foreach($payments as $payment) {
	$ctype = $payment['payment'] ? 'Payment' : 'Credit';
	echo "<a href='#payment{$payment['creditid']}'>$ctype ID: {$payment['creditid']}</a> Amount: {$payment['amount']} Date: {$payment['issuedate']} Reason: {$payment['reason']}<br>";
}
?>
</div>
<?
}
}


} // if billable
if($appt['serviceptr']) {
?>
<div style='width:800px;border: solid black 1px;'>
<?
	$service = fetchFirstAssoc("SELECT * FROM tblservice WHERE serviceid = {$appt['serviceptr']} LIMIT 1");
	$service['servicename'] = $_SESSION['servicenames'][$service['servicecode']];
	echo "Service: ".print_r($service, 1)."<br>";
}
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
	$prettyAppt = array_merge($appt);
	$prettyAppt['createdby'] = userName($prettyAppt['createdby']);
	$prettyAppt['modifiedby'] = userName($prettyAppt['modifiedby']);
	echo "Visit: ".print_r($prettyAppt, 1)."<br>";
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
	$discount = fetchFirstAssoc("SELECT label, relapptdiscount.*  FROM relapptdiscount LEFT JOIN tbldiscount ON discountid = discountptr WHERE appointmentptr = {$appt['appointmentid']}");
	echo "Discount: ".($discount ? print_r($discount, 1) : 'None')."<br>";
?>
</div>
<? if($appt['custom'] && $service) { ?>
<div style='width:800px;border: solid black 1px;'>
<?
	echo "Custom Delta: <br>";
	$customFields = explode(', ', "providerptr, timeofday, pets, servicecode, charge, adjustment, rate, bonus, note, highpriority, surchargenote");
	foreach($customFields as $field) 
		if($appt[$field] != $service[$field]) echo " $field ({$service[$field]} => {$appt[$field]})";
?>
</div>

<? } ?>



<div style='width:800px;border: solid black 1px;'>
<?
echo "<a href='package-analysis.php?id={$appt['packageptr']}'>Package: {$appt['packageptr']}</a> ";
$allAppts = getAllScheduledAppointments($appt['packageptr']);
$allAppts = array_keys($allAppts);
$allStr = join(',', $allAppts);
echo $allStr ? "Visits: ".count($allAppts) : "No Visits.";

if($allAppts && $showVisits) 
foreach($allAppts as $x) {
	$label = $x == $id ? "<b>$x</b>" : $x;
	echo "<a href='appt-analysis.php?id=$x&all=$allStr'>$label</a> ";
}
else if(!$showVisits) echo "<a href='appt-analysis.php?id=$id&showVisits=1'>Show Visits</a>";
echo "<p>";
echo packageDescriptionHTML(getPackage($appt['packageptr']), null);
?>
</div>
<?
if(!($payable = fetchFirstAssoc("SELECT * FROM tblpayable LEFT JOIN relproviderpayablepayment ON payableptr = payableid WHERE itemptr = $id AND itemtable = 'tblappointment'")))
	echo "No Payable.";
else {
	$paymentdescr = $payable['providerpaymentptr'] ? " (provider payment #{$payable['providerpaymentptr']})" : '';
	echo "Payable: {$payable['payableid']}<br>Sitter: {$payable['providerptr']}<br>Amount: {$payable['amount']}<br>Paid: {$payable['paid']}$paymentdescr"
		."<br>Date: {$payable['date']}<br>Date Paid: {$payable['datepaid']}<br>Generated: {$payable['gendate']}";
}
function userName($userid) {
	$user = getUserByID($userid);
	$name = "{$user['fname']}{$user['lname']}" ? "{$user['fname']}{$user['lname']}" : $user['loginid'];
	return $name." ($userid)";
}

$dates = array();
$dates[] = array($appt['created'], "Created by ".userName($appt['createdby']));
$dates[] = array($appt['modified'], "Last Modified by ".userName($appt['modifiedby']));
foreach($billables as $billable) {
	$dates[] = array($billable['billabledate'], "Billable [{$billable['billableid']}] Created");
	//$dates[] = array($billable['paid'], "Billable paid off");
	if($payments) foreach($payments as $payment) $dates[] = array($payment['issuedate'], "Payment made");
	if($invoiceitem) $dates[] = array($invoiceitem['date'], "Invoiced") ;
}

$lognotes = fetchAssociations("SELECT time, note, user FROM tblchangelog WHERE itemptr = $id AND itemtable = 'tblappointment'");

foreach($lognotes as $entry) $dates[] = array($entry['time'], "{$entry['note']} by ".userName($entry['user']));

function dateSort($a, $b) {return strcmp($a[0], $b[0]);}
uasort($dates, 'dateSort');
?>
<hr>
History:<br>
<table>
<?
foreach($dates as $event) echo "<tr><td>{$event[0]}<td>&nbsp;{$event[1]}<br>";
?>
</table>
<?
if($payments) {
?>
<div style='width:800px;border: solid black 1px;'>Payments Details:<p>
<?
foreach($payments as $payment) {
	$ctype = $payment['payment'] ? 'Payment' : 'Credit';
	echo "<a name='payment{$payment['creditid']}'></a><hr><b>$ctype ID: {$payment['creditid']}</b> Amount: {$payment['amount']} Date: {$payment['issuedate']} Reason: {$payment['reason']}<br>";
	$parts = fetchAssociations("SELECT itemtable, itemptr, monthyear, itemdate, billabledate, charge, paid
															FROM relbillablepayment
															LEFT JOIN tblbillable ON billableid = billableptr
															WHERE paymentptr = {$payment['creditid']}");
	if(!$parts) continue;
	echo "<table border=1><tr><th>Type<th>Item<th>Item Date<th>Billable Date<th>Charge<th>Total Paid";
	foreach($parts as $part) {
		$type = $part['monthyear'] ? 'Monthly Fixed' : ($part['itemtable'] == 'tblappointment' ? 'Visit' : $part['itemtable']);
		echo "<tr><td>$type<td>{$part['itemptr']}<td>{$part['itemdate']}<td>{$part['billabledate']}
					<td>{$part['charge']}<td>{$part['paid']}";
	}
	echo "</table>";
}
?>
</div>
<?
}
echo "<h3>Visit Prefs</h3>";
foreach(fetchKeyValuePairs("SELECT property, value FROM tblappointmentprop WHERE appointmentptr = $id", 1) as $k => $v)
	echo "<b>$k</b><br>$v<p>";
echo "<h3>Events</h3>";
$events = fetchAssociations(
	"SELECT `time`,operation,user,note 
		FROM  tblchangelog 
		WHERE itemptr = $id AND itemtable='tblappointment' 
		ORDER BY `time`", 1);
quickTable($events, $extra="border=1", $style=null, $repeatHeaders=0);
?>