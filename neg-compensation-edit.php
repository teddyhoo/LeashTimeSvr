<?
/* neg-compensation-edit.php
*
* Parameters: 
* [R] id - id of negative payment to be edited
* - or -
* [R] provider - id of provider to be charged
* [*] billableptr - billable negative payment is based on
* charge may not be modified (except for reason) once XXX
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "credit-fns.php";
require_once "gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');

extract(extractVars('amount,reason,charge,date,creationdate,lastday,provider,billableptr,saveCharge,usage,deleteCharge,id', $_REQUEST));
$idToFind = $id ? $id : $deleteCharge;
if($idToFind) {
	$negComp = fetchFirstAssoc("SELECT * FROM tblnegativecomp WHERE negcompid = $idToFind LIMIT 1");
	// Determine if neg comp was applied immediately
	if($negComp['paid']) {
		$payment = fetchFirstAssoc(
			"SELECT pmt.* 
			FROM relproviderpayablepayment
			LEFT JOIN tblproviderpayment pmt ON paymentid = providerpaymentptr
			WHERE payableptr = $idToFind AND negative = 1 
			", 1);
		if(!$payment) $wasAppliedImmediately = true;
	}
//if(mattOnlyTEST() && $deleteCharge) {echo "payment: ".print_r($payment, 1). " wasAppliedImmediately: [$wasAppliedImmediately]";exit;}
}
if($saveCharge) {
	if($id) {
		$vals = array('reason'=>$reason, 'modified'=>date('Y-m-d H:i:s'), 'modifiedby'=>$_SESSION["auth_user_id"]);
		updateTable('tblnegativecomp', $vals, "negcompid = $id", 1);
		$updateId = $id;
		
	}
	else {
		if($provider) {
			$vals = array('reason'=>$reason, 'amount'=>$amount, 'providerptr'=>$provider, 
										'date'=>date('Y-m-d', strtotime($date)), 'created'=>date('Y-m-d H:i:s'), 'createdby'=>$_SESSION["auth_user_id"]);
			if($usage == 'bookkeeping') $vals['paid'] = $amount;
			$updateId = insertTable('tblnegativecomp', $vals, 1);
		}
	}
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('history', $updateId);window.close();</script>";
	exit;
}
else if(isset($deleteCharge) && $deleteCharge) {
	//echo "DELETE";
	//if(fetchRow0Col0("SELECT negcompid FROM tblnegativecomp WHERE negcompid = $deleteCharge AND paid = 0.0 LIMIT 1"))
	if($wasAppliedImmediately || !$negComp['paid'])
		deleteTable('tblnegativecomp', "negcompid = $deleteCharge");
	else $deletefailed = true;
	if(!$deletefailed) echo "<script language='javascript'>if(window.opener.update) window.opener.update('history', $deleteCharge);window.close();</script>";
	else $error = "Could not delete this Negative Compensation: already applied.";
	exit;
}

$operation = 'Create';
if($id) {
	$operation = 'Edit';
	$provider = $negComp['providerptr'];
	$prettyDate = shortDate(strtotime($negComp['date']));
}
else {
	$negComp = array('provider'=>$provider);
}

$editable = $negComp['paid'] == 0;

if($provider) {
	$providerName = getProviderCompositeNames("WHERE providerid = $provider");
	$providerName = $providerName[$provider];
}

$header = "$operation Negative Compensation";

$windowTitle = $header;
require "frame-bannerless.php";	

if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
//print_r($source);exit;
?>
<div style='padding: 10px;padding-top:0px;'>
<h2><?= $header ?></h2>
<h2><?= ($id ? " against $providerName's account on $prettyDate" : ($provider ? " against $providerName's account" : '')) ?></h2>
<?

echo "<form name='editcharge' method='POST'>";
hiddenElement('provider', $provider);
hiddenElement('saveCharge', 1);
hiddenElement('lastday', 1);
echo "<table>";
if(!$id) {
	//echo "Client: $clientName";
	inputRow('Negative Compensation Date:', 'date', $prettyDate);
	if($lastday) labelRow('', '', 'Must be a date on or before '.displayDate($lastday), null, 'tiplooksleft');
	inputRow('Amount:', 'amount', '', '', 'dollarinput');
	radioButtonRow('Usage:', 'usage', $value=null, array('Bookkeeping Only'=>'bookkeeping', 'Apply to Next Payroll'=>'payroll'));
}
else {
	hiddenElement('issuedate', $prettyDate);
	labelRow('Negative Compensation Date:', '', $prettyDate);
	if($editable) inputRow('Amount:', 'amount', $negComp['amount'], '', 'dollarinput');
	else labelRow('Amount:', '', dollarAmount($negComp['amount']), null, null, null, null, true);
	if($editable) radioButtonRow('Usage:', 'usage', 'payroll', array('Bookkeeping Only'=>'bookkeeping', 'Apply to Next Payroll'=>'payroll'));
	labelRow('Amount applied:', '', dollarAmount($negComp['paid']), null, null, null, null, true);

	if($negComp['paid']) {
		if($payment) labelRow('Applied:', '', "Deducted on ".shortDate(strtotime($payment['paymentdate']))." from Payment #{$payment['paymentid']}", null, null, null, null, true);
		else if($wasAppliedImmediately) labelRow('Applied:', '', "Bookkeeping Only", null, null, null, null, true);
	}
	
}
inputRow('Note:', 'reason', ($negComp['reason'] ? $negComp['reason'] : $reason), '', 'VeryLongInput');
	echo "</table>";
echo "</form><p>";
echoButton('', "Save Negative Compensation", "checkAndSubmit()");
echo " ";
echoButton('', "Quit", 'window.close()');
echo " ";
if($id && ($wasAppliedImmediately || !$negComp['paid'])) echoButton('', "Delete Negative Compensation", 'deleteCharge()', 'HotButton', 'HotButtonDown');
?>
</div>
<?
if($id && staffOnlyTEST()) {
	echo "<hr>STAFF ONLY<p>";
	if($wasAppliedImmediately) echo "This was a standalone neg comp., not associated with any payment.";
	else {
		echo print_r($payable, 1)."<p>";
		/*$payment = fetchFirstAssoc(
			"SELECT pmt.* 
			FROM relproviderpayablepayment
			LEFT JOIN tblproviderpayment pmt ON paymentid = providerpaymentptr
			WHERE payableptr = $id AND negative = 1 
			", 1);*/
		echo "Deducted on ".shortDate(strtotime($payment['paymentdate']))." from Payment #{$payment['paymentid']}<p>"
			."Covering ".shortDate(strtotime($payment['startdate']))." to ".shortDate(strtotime($payment['enddate']))."<p>"
			."Amount: ".dollarAmount($payment['amount'])."<br>"
			."Adjustment: ".($payment['adjustment'] ? dollarAmount($payment['adjustment']) : '--')."<br>"
			."Type: {$payment['paymenttype']}<br>Note: ".($payment['note'] ? $payment['note'] : '--');
			//echo "<p>".print_r($payment,1);
	}
}
?>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

function deleteCharge() {
	if(confirm("Are you sure you want to delete this Negative Compensation?\nClick Ok to delete the Negative Compensation.")) {
		document.location.href='neg-compensation-edit.php?deleteCharge=<?= $id ?>';
	}
}

setPrettynames('provider','Sitter','amount','Amount', 'date','Negative Compensation Date', 'usage', 'Usage');	
	
function checkAndSubmit() {
	if(MM_validateForm(
		'provider', '', 'R',
		'date', '', 'R',
		'date', '', 'isDate',
<? if($lastday) echo "'date', '".shortDate(strtotime($lastday))."', 'isDateAfterNot',\n"; ?>
		'amount', '', 'R',
		'amount', '', 'UNSIGNEDFLOAT',
		'usage', '', 'R'
		)) {
		document.editcharge.submit();
	}
}

</script>
</body>
</html>
