<?
// payment-detail.php

// id - payment id.  Or may be 'current'
// may be included

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "pay-fns.php";

// Determine access privs
$locked = locked('vh');

extract($_REQUEST);


if($_POST['paymentdate'] && userRole() != 'p') {
	updateTable('tblproviderpayment', 
		array('paymentdate'=>date('Y-m-d', strtotime($_POST['paymentdate'])),
					'transactionid'=>$_POST['transactionid'],
					'note'=>$_POST['note']), 
		"paymentid = $id", 1);
	$message = "Changes saved.";
}

$payment = getProviderPayment($id);
$provider = $payment['providerptr'];

if(userRole() == 'p' && $_SESSION["providerid"] != $provider) {
  echo "<h2>Insufficient rights to view this page..<h2>";
  exit;
}

$provider = getProviderNames("WHERE providerid = $provider");
$provider = $provider[$payment['providerptr']];

if($rollback) {
	$locked = locked('o-');
	include "frame-bannerless.php";
	if(rollbackPayment($id))
		echo "<center><span clas='h2'>The ".dollarAmount($payment['amount'])." payment to $provider on ".shortDate(strtotime($payment['paymentdate']))
					." has been rolled back.</h2></center>";
	else echo "<center><span clas='h2'>Failed to rolled back.payment.</h2></center>";
	fauxlink('Close Window', 'window.close()');
	exit;
}




$payables = getPaymentPayables($id);
if(staffOnlyTEST() && $aggregateView) {
	fauxLink("Edit Payment #$id", 
		"openConsoleWindow(\"paydetail\", \"payment-detail.php?id=$id\",700,600)", 0, "Show details");
	echo "<p>";
}
else if(mattOnlyTEST()) {echo "######<br>".print_r($id, 1)."<br>#######<p>";}

//if($_SESSION['staffuser']) { foreach($payables as $p) if($p['date'] == '2010-12-16') echo print_r($p,1).'<br>';}


getPayableDetails($payables);
getPaymentNegativeComps($payables, $id);

foreach($payables as $payable) {
	$due += $payable['amount']-$payable['paid'];
	if(isset($payable['clientptr'])) $clientids[] = $payable['clientptr'];
}
if($clientids) getClientDetails(array_unique($clientids));

//print_r($payables[1]);
$payDate = shortDate(strtotime($payment['paymentdate']));
$throughDate = $payment['enddate'] ? longestDayAndDate(strtotime($payment['enddate'])) : '';
$amount = dollarAmount($payment['amount']);

$windowTitle = "Payment Viewer: $provider Pay Date: $payDate";
$customStyles = ".dateRow {background: lightblue;font-weight:bold;text-align:center;}";

if(!$aggregateView) require_once "frame-bannerless.php";
$due = dollarAmount($due);
$transactionLink = 
  (userRole() != 'p'  && !$aggregateView)
	? labeledInput("Check / Transaction #: ", 'transactionid', $payment['transactionid'], $labelClass=null, $inputClass=null, null, null, 'noEcho')
	: "Check / Transaction #: {$payment['transactionid']}";
if(!$aggregateView) echo "<h2>Payment Viewer: $provider</h2>";
if(userRole() == 'o' && !$aggregateView && $_SESSION['staffuser']) {
	echo "<div style='float:right;position:relative;top:-30px;'>";
	echoButton('', 'Roll Back', "rollBack($id)", 'HotButton', 'HotButtonDown');
	echo "</div>";
}
if(userRole() != 'p' && !$aggregateView) echo "<form name='paymentdetailform' method='POST'>";;
echo "<table width=100% border=1 bordercolor=black bgcolor=white><tr><td>Pay Date: ";

		
if(userRole() == 'p' || $aggregateView) echo $payDate;
else {
	require_once "js-gui-fns.php";
	calendarSet('', 'paymentdate', $payDate);
}
		
echo "</td><td>Type: {$payTypes[$payment['paymenttype']]}</td></tr>";
$adjustment = $payment['adjustment'] ? "Including adjustment: ".dollarAmount($payment['adjustment']) : '&nbsp;';
if(mattOnlyTEST()) {
foreach($payables as $pber) $sum += $pber['amount'];
$sum = ' ('.dollarAmount($sum).')';
}
echo "<tr><td>$transactionLink</td><td>Check Amount: $amount $sum</td><td>$adjustment</td></tr>";
               "";
if($throughDate) echo "<tr><td colspan=4>Period Ending: $throughDate</td></tr>";
echo "<tr><td colspan=4>Note: ";
echo userRole() == 'p' || $aggregateView
	? $payment['note']
	:labeledInput('', 'note', $payment['note'], $labelClass=null, $inputClass='VeryLongInput', null, null, 'noEcho')
			." ".echoButton('', 'Save Changes', 'saveChanges()', null, null, 'noEcho');

echo "</td></tr>";
echo "</table>";
if($message) echo "<p><span class='tiplooks fontSize1_1em' >$message</span><p>";
if(userRole() != 'p' && !$aggregateView) echo "</form>";;
if($throughDate) echo "<h3>Services Rendered</h3>";

if($payables) payablesTable($payables, 'noEdit', 'showPaid', $noLinks=false, $colsup);

if(FALSE && mattOnlyTEST()) { // diagnostic
	//print_r($payables);
	$paidTotal  = 0;
	foreach($payables as $pb) $paidTotal += $pb['paid'];
	if($payment['amount'] == $paidTotal) echo "<p><span style='color:green;'>Checks out, Matt.";
	else echo "<p><span style='color:red;'>Payment amount: [$amount] - Payables sum: [$paidTotal] = MISSING: ".($payment['amount'] - $paidTotal);
	$lastDay = $payment['enddate'] ? $payment['enddate'] : $payment['paymentdate'];
	$payables = getUnpaidPayables($lastDay, $payment['providerptr'], $firstDay=null);
	if($payables) {
		getPayableDetails($payables);
		getPaymentNegativeComps($payables, $id);
		echo "<div style='border:solid red 1px;'>";
		$paidTotal = 0;
		foreach($payables as $pb) {
			$paidTotal += $pb['amount'] - $pb['paid'];
			$upIds[] = $pb['payableid'];
			echo "<div style='width:100%;'>";
			echo "UPDATE tblpayable SET paid = amount WHERE payableid = {$pb['payableid']};<br>";
			echo "INSERT INTO relproviderpayablepayment (payableptr, providerpaymentptr, providerptr) 
							VALUES ({$pb['payableid']}, $id, {$pb['providerptr']});<br>";
			echo "</div>";
		}

		echo "<p>Unpaid Payables owed: [$paidTotal]<br>"; //.join(',', $upIds).'<br>';
		payablesTable($payables, 'noEdit', 'showPaid', $noLinks=false, $colsup);
		echo "</div>";
	}
	echo "</span>";
}


if(userRole() != 'p' && !$aggregateView) { ?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<?
}
?>

<script language='javascript'>
function saveChanges() {
	if(MM_validateForm(
		  'paymentdate', '', 'R',
		  'paymentdate', '', 'isDate'))
		 document.paymentdetailform.submit();
}
function update(target, val) { // called by appointment-edit
	refresh(); // implemented below
}

function rollBack(id) {
	if(!confirm('CAUTION\n\nRolling back a payment will'
							+'\npermanently and irrevocably erase'
							+'\nall record of this payment'
							+'\nand may cause unintended side-effects.')) {
		alert('Rollback canceled.');
		return;
	}
	document.location.href='payment-detail.php?rollback=1&id='+id;
}

function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

<? if(userRole() != 'p') dumpPopCalendarJS(); ?>

</script>
<?
include "js-refresh.php";
?>
