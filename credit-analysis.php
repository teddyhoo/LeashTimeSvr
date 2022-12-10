<?  // credit-analysis.php
// 757
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "credit-fns.php";
require_once "service-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "appointment-fns.php";
require_once "credit-fns.php";

extract($_REQUEST);
echo "<h2>Leashtime Client: {$_SESSION['preferences']['bizName']} ($db)</h2>";
?>
<div style='width:800px;border: solid black 1px;'>
<?
$credit = fetchFirstAssoc("SELECT *, concat_ws(' ',fname,lname) as client FROM tblcredit left join tblclient on clientid = clientptr WHERE creditid = $id");
if(!$credit) {echo "Bad credit ID: $id";exit;}
if(!$credit['payment']) echo "Credit: <b>$id</b> ";
else echo "Payment: <b>$id</b> ";
echo "Client: <b>{$credit['client']}</b> Amount: <b>{$credit['amount']}</b> Date: <b>".date('m/d/Y H:i', strtotime($credit['issuedate']))."</b><br>";
echo "Reason: <b>{$credit['reason']}</b> Amount used: <b>{$credit['amountused']}</b> Reference: <b>{$credit['externalreference']}</b> Gratuity:";
echo "<b>".($credit['includesgratuity'] ? 'Yes' : 'No')."</b>";
$billables = fetchAssociations("SELECT relbillablepayment.amount as portion, tblbillable.*
																	FROM relbillablepayment
																	LEFT JOIN tblbillable ON billableid = billableptr
																	WHERE paymentptr = $id");
if($billables) echo "<p>Applied to:<br><table border=1>";								

$surchargeNames = getSurchargeTypesById();
$discountNames = fetchKeyValuePairs("SELECT discountid, label FROM tbldiscount");

foreach($billables as $billable) {
	echo "<tr><td><p>Portion: {$billable['portion']}";
	echo "<tr><td width=25%>Billable: {$billable['billableid']}<br>Charge: {$billable['charge']} (original total)<br>Tax: {$billable['tax']} (included)<br>Paid: {$billable['paid']}<br>Still Owed: ".($billable['charge'] - $billable['paid'])
		."<br>Item Date: {$billable['itemdate']}<br>Billable Date: {$billable['billabledate']}<td>";
	if($billable['itemtable'] == 'tblappointment') {
		$appt = getAppointment($billable['itemptr']);
		$discount = fetchFirstAssoc("SELECT * FROM relapptdiscount WHERE appointmentptr = {$appt['appointmentid']} LIMIT 1");
		echo "<b>Appointment: ".shortDateAndDay(strtotime($appt['date']))." - {$appt['timeofday']} - {$_SESSION['servicenames'][$appt['servicecode']]}</b><br>"
					.print_r($appt,1)."<b><br>Subtotal: \$".($appt['charge']+$appt['adjustment'])."</b>";
		if($discount) echo "<br><b>Discount: \${$discount['amount']}</b>";
		echo "<br><b>Pretax Total: \$".($appt['charge']+$appt['adjustment']-$discount['amount'])."</b>";
	}
	else if($billable['itemtable'] == 'tblothercharge') {
		echo "Other Charge: ".print_r(getOtherCharge($billable['itemptr'],1),1);
	}
	else if($billable['itemtable'] == 'tblsurcharge') {
		$sur = getSurcharge($billable['itemptr'], 1);
		echo "<b>Surcharge: ".shortDateAndDay(strtotime($sur['date']))." - {$sur['timeofday']} - {$surchargeNames[$sur['surchargecode']]}</b><br>"
		.print_r($sur,1)
		."<br><b>Pretax Total: \${$sur['charge']}</b>";
	}
}
		

	
function getOtherCharge($id, $withNames=true) {
	$joins = ''.
	$extraFields = '';
	if($withNames) {
		$extraFields .= ", CONCAT_WS(' ',tblclient.fname, tblclient.lname) as client";
		$joins .= " LEFT JOIN tblclient ON clientid = tblothercharge.clientptr";
	}
	return fetchFirstAssoc("SELECT tblothercharge.* $extraFields FROM tblothercharge $joins WHERE chargeid = $id LIMIT 1");
}

