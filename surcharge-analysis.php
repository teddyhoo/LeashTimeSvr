<? // surcharge-analysis.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "credit-fns.php";
require_once "service-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "appointment-fns.php";
require_once "surcharge-fns.php";

extract($_REQUEST);
echo "<h2>Leashtime Client: {$_SESSION['preferences']['bizName']} ($db)</h2>";
?>
<div style='width:800px;border: solid black 1px;'>
<?
$sur = getSurcharge($id,1);
$sur['label'] = getSurchargeName($sur['surchargecode']);
$sur['isauto'] = $sur['automatic'] ? 'Automatic' : '';
echo "Surcharge: $id [{$sur['label']}] [Charge: \${$sur['charge']}] [{$sur['isauto']}]<br>";
if($sur['appointmentptr']) $appt = getAppointment($sur['appointmentptr'], 1);
$package = getPackage($sur['packageptr']);
$packageType = $package['monthly'] ? 'Monthly Recurring' :
							 ($package['onedaypackage'] ? 'One Day' :
							 ($package['enddate'] ? 'Nonrecurring' : 'Weekly Recurring'));
$sur['packageType']	= $packageType;           
$appt['packageType']	= $packageType;           
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
$billable = fetchFirstAssoc("SELECT * FROM tblbillable WHERE itemptr = $id AND itemtable = 'tblsurcharge'");
if(!$billable) echo "No Billable.";
else {
	echo "Billable: {$billable['billableid']}<br>Charge: {$billable['charge']} (original total charge)<br>Tax: {$billable['tax']} (incl in total charge)<br>Paid: {$billable['paid']}<br>Still Owed: ".($billable['charge'] - $billable['paid'])
		."<br>Item Date: {$billable['itemdate']}<br>Billable Date: {$billable['billabledate']}";
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
?>
<div style='width:800px;border: solid black 1px;'>
<?
	echo "Surcharge: ".print_r($sur, 1)."<br>";
?>
</div>
<?
if($appt['serviceptr']) {
?>
<div style='width:800px;border: solid black 1px;'>
<?
	echo "Visit: ".print_r($appt, 1)."<br>";
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
	$service = fetchFirstAssoc("SELECT * FROM tblservice WHERE serviceid = {$appt['serviceptr']} LIMIT 1");
	$service['servicecode'] = $_SESSION['servicenames'][$service['servicecode']];
	echo "Service: ".print_r($service, 1)."<br>";
}
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
	//$discount = fetchFirstAssoc("SELECT label, relapptdiscount.*  FROM relapptdiscount LEFT JOIN tbldiscount ON discountid = discountptr WHERE appointmentptr = {$appt['appointmentid']}");
	//echo "Discount: ".($discount ? print_r($discount, 1) : 'None')."<br>";
?>
</div>
<div style='width:800px;border: solid black 1px;'>
<?
echo packageDescriptionHTML(getPackage($sur['packageptr']), null);
?>
</div>
<?
if(!($payable = fetchFirstAssoc("SELECT * FROM tblpayable WHERE itemptr = $id AND itemtable = 'tblappointment'")))
	echo "No Payable.";
else {
	echo "Payable: {$payable['payableid']}<br>Sitter: {$payable['providerptr']}<br>Amount: {$payable['amount']}<br>Paid: {$payable['paid']}"
		."<br>Date: {$payable['date']}<br>Date Paid: {$payable['datepaid']}<br>Generated: {$payable['gendate']}";
}
function userName($userid) {
	$user = getUserByID($userid);
	$name = "{$user['fname']}{$user['lname']}" ? "{$user['fname']}{$user['lname']}" : $user['loginid'];
	return $name." ($userid)";
}
$dates = array();
$dates[] = array($sur['created'], "Created by ".userName($sur['createdby']));
$dates[] = array($sur['modified'], "Last Modified by ".userName($sur['modifiedby']));
if($billable) {
	$dates[] = array($billable['billabledate'], "Billable Created");
	//$dates[] = array($billable['paid'], "Billable paid off");
	if($payments) foreach($payments as $payment) $dates[] = array($payment['issuedate'], "Payment made");
	if($invoiceitem) $dates[] = array($invoiceitem['date'], "Invoiced") ;
}

$lognotes = fetchAssociations("SELECT time, note, user FROM tblchangelog WHERE itemptr = $id AND itemtable = 'tblsurcharge'");

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
?>