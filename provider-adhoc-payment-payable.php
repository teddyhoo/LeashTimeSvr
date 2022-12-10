<?
// provider-adhoc-payment-payable.php
// This page is used to edit either an adhoc payment (without any individual payables) OR
// an ad-hoc payable (based on a tblothercharge record)
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-fns.php";
require "client-fns.php";
require "pay-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);

extract(extractVars('amount,note,date,providerptr,savePayable,usage,deletePayable,payableptr,paymentptr', $_REQUEST));

if($_POST['token'] && $_POST['token'] !== $_SESSION['ADHOC_ID']) {
	echo '<font color=red>Cannot resubmit this form!</font>';
	exit;
}

if($savePayable) {
	$today = date('Y-m-d');
	$amount = ($amount ? $amount : 0);

	if($paymentptr) {  // NOT NEW
		$payable = fetchFirstAssoc("SELECT * FROM relproviderpayablepayment WHERE providerpaymentptr = $paymentptr LIMIT 1");
		if(!$payable) $error = "No payable was found for this ad-hoc payment.";
		else  $payableptr = $payable['payableptr'];
		updateTable('tblproviderpayment', array('note'=>$note), "paymentid = $paymentptr", 1);
	}
	else if($payableptr && $usage == "immediate") { // NOT NEW, BUT PAYMENT IS TO BE MADE NOW
		$paymentptr = 
			insertTable('tblproviderpayment', 
				array('amount'=>$amount, 'note'=>$note, 'paymenttype'=>'adhoc', 'providerptr'=>$providerptr, 'paymentdate'=>$today), 
				1);
		insertTable("relproviderpayablepayment", array('payableptr'=>$payableptr, 'providerptr'=>$providerptr, 'providerpaymentptr'=>$paymentptr), 1);
	}
	if($payableptr) {  // NOT NEW
		$payable = fetchFirstAssoc("SELECT * FROM tblpayable WHERE payableid = $payableptr AND itemtable = 'tblothercomp' LIMIT 1");
		$compptr = $payable['itemptr'];
		$vals = array('descr'=>$note);
		// if payable is paid, only the payment note/othercomp.descr can be modified
		if($payable['paid'] == 0.0) {
			$vals['amount'] = $amount;
			if($amount != $payable['amount']) $payableVals['amount'] = $amount;
			if($paymentptr) {
				$payableVals['paid'] = $amount;
				$payableVals['datepaid'] = $today;
			}
				
			if($payableVals) updateTable('tblpayable', $payableVals, "payableid = $payableptr", 1);
		}
		updateTable('tblothercomp', $vals, "compid = $compptr", 1);
	}
	else { // NEW
		$compptr =
			insertTable("tblothercomp", 
				array('amount'=>$amount, 'descr'=>$note, 'providerptr'=>$providerptr, 'comptype'=>'adhoc', 'date'=>$today), 1);
		$payable = 
			array('amount'=>$amount, 'providerptr'=>$providerptr, 'itemtable'=>'tblothercomp', 'itemptr'=>$compptr, 'date'=>$today);
		if($usage == "immediate") {
			$payable['paid'] = $amount;
			$payable['datepaid'] = $today;
		}
		$payableptr =  insertTable("tblpayable", $payable, 1);
		
		if($usage == "immediate") { // NOT NEW, BUT PAYMENT IS TO BE MADE NOW
			$paymentptr = 
				insertTable('tblproviderpayment', 
					array('amount'=>$amount, 'note'=>$note, 'paymenttype'=>'adhoc', 'providerptr'=>$providerptr, 'paymentdate'=>$today), 
					1);
			insertTable("relproviderpayablepayment", array('payableptr'=>$payableptr, 'providerptr'=>$providerptr, 'providerpaymentptr'=>$paymentptr), 1);
		}
	}
		
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', 'charge');window.close();</script>";
	exit;
}
else if(isset($deletePayable) && $deletePayable) { // NOT USED!
	// payments are undeletable
	echo "DELETE";
	$payable = fetchFirstAssoc("SELECT * FROM tblpayable WHERE payableid = $deletePayable AND itemtable = 'tblothercomp' LIMIT 1");
	if(!$payable) $deletefailed = "Payable $deletePayable not found.";
	else {
		$compid = fetchRow0Col0("SELECT compid FROM tblothercomp WHERE compid = {$payable['itemptr']} LIMIT 1");
		if(!$compid) $deletefailed = "Compensation item {$payable['itemptr']} not found.";
	}
	if(!$deletefailed && $payable['paid'] == 0.0) {
		deleteTable('tblothercomp', "compid = $compid",1 );
		deleteTable('tblpayable', "payableid = $deletePayable", 1);
	}
	else $deletefailed = "Could not delete this Compensation: already paid.";
	if(!$deletefailed) echo "<script language='javascript'>if(window.opener.update) window.opener.update('account', 'charge');window.close();</script>";
	else {
		$windowTitle = 'Error';
		require "frame-bannerless.php";
		echo "<h2>$deletefailed</h2>";
	}
	exit;
}

$_SESSION['ADHOC_ID'] = md5(date('Y-m-d h:i:s'));


$activeProviderSelections = array_merge(array('--Select a Sitter--' => ''), getActiveProviderSelections());

$windowTitle = "Ad-Hoc Sitter Payment";
$customStyles = ".dateRow {background: lightblue;font-weight:bold;text-align:center;}";
require "frame-bannerless.php";

?>
<h2>Ad-Hoc Provider Payment</h2>

<form name='adhocpayform' method='POST'>
<table width=480>

<?
$payable = $payableptr ? fetchFirstAssoc("SELECT * FROM tblpayable WHERE payableid = $payableptr AND itemtable = 'tblothercomp' LIMIT 1") : null;
$payment = $paymentptr ? fetchFirstAssoc("SELECT * FROM tblproviderpayment WHERE paymentid = $paymentptr LIMIT 1") : null;
if($payable) {
	$providerptr = $payable['providerptr'];
	$comp = fetchFirstAssoc("SELECT * FROM tblothercomp WHERE compid = {$payable['itemptr']} LIMIT 1");
}
$editable = !$payable || $payable['paid'] == 0.0;
if(!$providerptr && $editable) selectRow('Provider:', 'providerptr', null, $activeProviderSelections);
else {
	//next($activeProviderSelections);
	//$pname = array_search($providerptr, (array)current($activeProviderSelections));
	$pname = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $providerptr", 1);
	hiddenElement('providerptr', $providerptr);
	labelRow('Sitter:', '', $pname);
	labelRow('Date:', '', shortDate(strtotime($comp['date'])));
	if($payable['datepaid']) labelRow('Date Paid:', '', shortDate(strtotime($payable['datepaid'])));
}
hiddenElement('token', $_SESSION['ADHOC_ID']);
hiddenElement('savePayable', '');
hiddenElement('deletePayable', '');
if($editable) inputRow('Pay:', 'amount', $payable['amount']);
else labelRow('Pay:', '', dollarAmount($payable['amount']), null, null, null, null, true);
inputRow('Check / Transaction #:', 'transactionid', $payment['transactionid']);
inputRow('Note', 'note', $comp['descr'],'','emailInput');
if($editable) {
	$usageValue = $payable ? 'payroll' : null;
	radioButtonRow('When:', 'usage', $usageValue, array('Pay Immediately'=>'immediate', 'Apply to Payroll'=>'payroll'), 'usageClicked(this)');
}
?>
</table>
</form>
<?
echoButton('', 'Pay Sitter', 'payProvider()');
if(staffOnlyTEST() && $payable && $payable['paid'] == 0) {
	echo "<img src='art/spacer.gif' width=20 height=1>";
	echoButton('', 'Delete Payment', 'deletePayment()', 'HotButton', 'HotButtonDown');
}
?>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('providerptr','Sitter','amount','Pay', 'usage', 'When');

function deletePayment() {
	if(confirm('Delete this payment?')) {
		document.getElementById('deletePayable').value='<?= $payableptr ?>';
		document.adhocpayform.submit();
	}
}

function payProvider() { 
  if(!MM_validateForm('providerptr', '', 'R',
  										'amount', '', 'R',
  										'amount', '', 'UNSIGNEDFLOAT',
											'usage', '', 'R'))
  	return;
  if(!document.adhocpayform.note.value &&
      !confirm('You have not entered a Note describing this payment.\n\nContinue anyway?'))
    return;
  document.getElementById('savePayable').value=1;
  document.adhocpayform.submit();
}

function usageClicked(el) {
	var val = el ? el.value : 
			(document.getElementById('usage_payroll').checked ? 'payroll' : '');
	document.getElementById('transactionid').disabled = (val == 'payroll');
}
usageClicked(null);
</script>
