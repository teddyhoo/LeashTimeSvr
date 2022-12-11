<? // staff-monthly-billables.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out
extract(extractVars('id,action', $_REQUEST));
$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE clientid = $id");
$locked = locked('o-');


/*if(!staffOnlyTEST()) { echo "LeashTime Staff Only"; exit; }
if(!$_SESSION['monthlyBillableViewerAllowed']) {
	if($_POST) {
		if($_POST['answer'] =='teddles') $_SESSION['monthlyBillableViewerAllowed'] = 1;
	}
	if(!$_SESSION['monthlyBillableViewerAllowed']) {
?><head><title>Monthly Billables</title></head><form method='POST'>Restricted password? <input type=password name=answer> <input type='submit'></form>
<? //'
	exit;
	}
}
*/

if($_POST['action'] == 'delete') {
	foreach($_POST as $k => $v) {
		if(!$v || strpos($k, 'billable_') !== 0) continue;
		$bid = substr($k, strlen('billable_'));
		$doomed[] = $bid;
	}
	if(!$doomed) $error = "No billables marked for deletion.";
	else {
		$details = fetchAssociations("SELECT * FROM tblbillable WHERE billableid IN (".join(',', $doomed).")");
		deleteTable('tblbillable', "billableid IN (".join(',', $doomed).")", 1);
		$message = "Billables Deleted:<ul>";
		if(mysqli_error()) $error = "ERROR: No billables deleted.";
		foreach($details as $d) 
			$message .= "<li>[{$d['billableid']}] {$d['monthyear']} Charge: {$d['charge']} Paid: {$d['paid']}";
		$message .= "</ul>";
	}
}

require "frame-bannerless.php";
echo "<h2>Delete Monthly Billables for {$client['fname']} {$client['lname']}</h2>";
if($error) echo "<font color=red>$error</font><p>";
if($message) echo "<font color=red>$message</font><p>";

$billables = fetchAssociations($sql="SELECT * FROM tblbillable WHERE clientptr = $id AND itemtable = 'tblrecurringpackage' AND monthyear IS NOT NULL ORDER BY monthYear");

if(!$billables) echo "<span class='tiplooks fontSize1_3em'>There are no Monthly Billables to delete.</span>";

else {
?>
<form name='billableform' method='POST'>
<?
hiddenElement('action','');
echoButton('', 'Delete Selected Monthly Billables', 'checkAndSubmit()');
echo "<p>";
?>
<table border=1 bordercolor=darkgrey>
<?
$leashTimeStaff = $_SESSION["staffuser"];
$dateColumns = $leashTimeStaff ? "<td>Item Date<td>Billable Date" : "";
echo "<tr><td>&nbsp;<td>For Month<td>Charge<td>Paid$dateColumns<td>Payments</tr>";
	
foreach($billables as $b) {
	$payments = fetchAssociations("SELECT p.* FROM relbillablepayment LEFT JOIN tblcredit p ON creditid = paymentptr WHERE billableptr = {$b['billableid']}");
	$paymentcells = array();
	foreach($payments as $p) {
		$issueDateTime = strtotime($p['issuedate']);
		$issueDate = shortDate($issueDateTime)." ".date('h:i a', $issueDateTime);
		$dollars = dollarAmount($p['amount']);
		$reason = $p['reason'] ? "[".safeValue($p['reason'])."]" : '';
		$paymentcells[] = "<span style='text-decoration:underline;' title='(#{$p['creditid']})  $issueDate $dollars $reason'>#{$p['creditid']}</span>";
	}
	$monthYear = date('M Y', strtotime($b['monthyear']));
	$checkBox = $paymentcells ? "<span class='warning'>*</span>" : "<input type='checkbox' id='billable_{$b['billableid']}' name='billable_{$b['billableid']}'>";
	$deletionDisallowedOnce = $deletionDisallowedOnce || $paymentcells;
	echo "<tr><td style='text-align:center;'>";
	echo $leashTimeStaff ? "$checkBox [{$b['billableid']}]" : $checkBox;
	echo "<td>$monthYear<td>{$b['charge']}<td>".number_format($b['paid'],2);
	if($leashTimeStaff) echo "<td>{$b['itemdate']}<td>{$b['billabledate']}";
	echo "<td>".join('<br>',$paymentcells)."</tr>";
}
?>
</table>
<?
if($deletionDisallowedOnce) 
	echo "<p><div style='padding:7px;width:300px;border: solid darkgrey 1px;background:white;'><span class='warning'>*</span> Billable has been partially or completely paid.<p>"
				."Before this billable can be deleted, the associated payment(s) must be voided.  You can hover the mouse over a payment to see payment details.<p>"
				."Once this is done, you can return to this page and delete the billable.</div>";
?>
<script language='javascript'>
function checkAndSubmit() {
	var els = document.billableform.elements;
	var sels = 0;
	for(var i = 0; i < els.length; i++)
		if(els[i].type == 'checkbox' && els[i].id.indexOf('billable_') == 0 && els[i].checked)
			sels += 1;
	if(sels == 0) alert('Please select at least one billable to delete first.');
	else {
		if(!confirm('You are about to delete '+sels+' billables.  Continue?')) return;
		document.getElementById('action').value='delete';
		document.billableform.submit();
	}
}
</script>
<? }