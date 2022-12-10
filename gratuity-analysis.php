<? // gratuity-analysis.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "credit-fns.php";
require_once "service-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "gratuity-fns.php";


locked('o-');
if(!staffOnlyTEST()) {
	echo "LT STaff Use Only.<p><a href='index.php'>Home</a>";
	exit;
}

extract($_REQUEST);
echo "<head><title>Leashtime Gratuity Analyzer</title>
<style>
.box {width:800px;border: solid black 1px;padding-bottom:10px;}
</style>
</head>";
echo "<h2>Leashtime Client: {$_SESSION['preferences']['bizName']} ($db)</h2>";
if($paymentptr) {
	$brothers = fetchAssociations($sql = "SELECT * FROM tblgratuity WHERE paymentptr = $paymentptr",1);
	$providers = fetchKeyValuePairs($sql = "SELECT providerid, CONCAT(fname, ' ', lname) FROM tblprovider",1);
	$payment = fetchFirstAssoc("SELECT * FROM tblproviderpayment WHERE paymentid = $paymentptr",1);
	echo "<h2>Gratuities tied to Payment $paymentptr</h2>";
	echo "Date: {$payment['paymentdate']}<up>";
	foreach($brothers as $bro) {
		echo "<li><a href='gratuity-analysis.php?id={$bro['gratuityid']}'>(Gratuity: {$bro['gratuityid']}) {$providers[$bro['providerptr']]} ({$bro['providerptr']}) ".dollarAmount($bro['amount'])."</a>";
	}
	echo "</ul>";
	exit;
}
if(!$id) exit;
$providers = fetchKeyValuePairs("SELECT providerid, CONCAT_WS(' ', fname, lname) FROM tblprovider");
$grat = fetchFirstAssoc("SELECT * FROM tblgratuity WHERE gratuityid = $id",1);
if($grat['paymentptr']) $brothers = fetchAssociations($sql = "SELECT * FROM tblgratuity WHERE paymentptr = {$grat['paymentptr']} AND gratuityid != $id",1);
$clientname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = {$grat['clientptr']}", 1);
$payable = fetchFirstAssoc("SELECT * FROM tblpayable WHERE itemptr = $id AND itemtable = 'tblgratuity'",1);
$rels = fetchAssociations(
	"SELECT rp.*, paymentid, paymentdate, paymenttype, amount as paymentamount 
		FROM relproviderpayablepayment rp 
		LEFT JOIN tblproviderpayment ON paymentid = providerpaymentptr
		WHERE payableptr = '{$payable['payableid']}'", 1);
foreach($rels as $rel) $paymentids[] = $rel['providerpaymentptr'];
$color = $payable['paid'] > 0? 'palegreen' : 'white';
?>
<div class='box' style='background:<?= $color ?>;'>
<?
$date = date('m/d/Y', strtotime($grat['issuedate']));
$paidNote = $payable['paid'] ? "PAID: ".dollarAmount($payable['paid']) : 'UNPAID';
if($payable['paid'] && $payable['paid'] < $payable['amount']) $paidNote = "<font color=red>$paidNote</font>";
echo "Gratuity: $id - $date - Amount: ".dollarAmount($grat['amount'])." $paidNote<br>";
echo "Payable to: {$providers[$grat['providerptr']]} ({$grat['providerptr']})<br>";
//echo "Sitter: {$providers[$grat['providerptr']]} ({$grat['providerptr']})<br>";
echo "Client: $clientname ({$grat['clientptr']})<br>";
echo "Tied to payment: ".($grat['paymentptr'] ? "<a href='gratuity-analysis.php?paymentptr={$grat['paymentptr']}'>{$grat['paymentptr']}</a>" : 'No').'<br>';
if($brothers) {
	echo "along with:<ul>";
	foreach($brothers as $bro) {
		echo "<li><a href='gratuity-analysis.php?id={$bro['gratuityid']}'>(Gratuity: {$bro['gratuityid']}) {$providers[$bro['providerptr']]} ({$bro['providerptr']}) ".dollarAmount($bro['amount'])."</a>";
	}
	echo "</ul>";
}
echo "Note: {$grat['tipnote']}";
?>
</div>
<div class='box'>
<?
echo "Payable: ".($payable ? $payable['payableid'] : 'NONE')."<br>";
if($payable) {
	echo "Item: {$payable['itemtable']} {$payable['itemptr']}<br>";
	echo "Generated: {$payable['gendate']}<br>";
	echo "Item Date: {$payable['date']}<br>";
	echo "Date Paid: {$payable['datepaid']}<br>";
	echo "AMOUNT: ".dollarAmount($payable['amount'])."<br>";
	echo $paidNote;
}
if($rels) {
?>
</div>
<div class='box'>
PAID WITH:<table width=500border=1 bordercolor=black>
<?
foreach($rels as $rel) {
	echo "<tr><td>Payment: {$rel['paymentid']}<td>{$rel['paymentdate']}<td>".dollarAmount($rel['paymentamount'])."<td>Type: {$rel['paymenttype']}<br>"; 
}
?>
</table>
</div>
<? }