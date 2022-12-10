<?
// provider-adhoc-payment.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-fns.php";
require "client-fns.php";
require "pay-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);

if($_POST) {
	if($_POST['token'] !== $_SESSION['ADHOC_ID']) {
		echo '<font color=red>Cannot resubmit this form!</font>';
		exit;
	}
	else unset($_POST['token']);
	unset($_SESSION['ADHOC_ID']);
	$payment = array_merge($_POST);
	$payment['paymenttype'] = 'adhoc';
	$payment['paymentdate'] = date('Y-m-d');;
  insertTable('tblproviderpayment', $payment, 1);
  $names = getProviderCompositeNames("WHERE providerid={$_POST['providerptr']} LIMIT 1");
  $amount = dollarAmount($payment['amount']);
  $transactionid = $payment['transactionid'] ? $payment['transactionid'] : '<i>None supplied.</i>';
  $note = $payment['note'] ? $payment['note'] : '<i>None supplied.</i>';
  echo "<link rel='stylesheet' href='style.css' type='text/css' /> 
  <link rel='stylesheet' href='pet.css' type='text/css' /> 
<h2>A Payment has been recorded</h2>
  To: {$names[$_POST['providerptr']]}<br>
  Check / Transaction #: $transactionid<br>
  Amount: $amount<br>
  Note: $note<p>
  <center>";
  fauxLink('Close Window', 'window.close()');
  echo "</center>";
  exit;
}

$_SESSION['ADHOC_ID'] = md5(date('Y-m-d h:i:s'));


$activeProviderSelections = array_merge(array('--Select a Sitter--' => ''), getActiveProviderSelections());

$windowTitle = "Ad-Hoc Sitter Payment";
$customStyles = ".dateRow {background: lightblue;font-weight:bold;text-align:center;}";
require "frame-bannerless.php";

?>
<h2>Ad-Hoc Sitter Payment</h2>

<form name='adhocpayform' method='POST'>
<table width=350>

<?
selectRow('Sitter:', 'providerptr', null, $activeProviderSelections);
hiddenElement('token', $_SESSION['ADHOC_ID']);
inputRow('Pay:', 'amount');
inputRow('Check / Transaction #:', 'transactionid');
inputRow('Note', 'note','','','emailInput');
?>
</table>
</form>
<?
echoButton('', 'Pay Sitter', 'payProvider()');

?>

<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('provider','Sitter','amount','Pay');

function payProvider() { 
  if(!MM_validateForm('providerptr', '', 'R',
  										'amount', '', 'R',
  										'amount', '', 'UNSIGNEDFLOAT'))
  	return;
  if(!document.adhocpayform.note.value &&
      !confirm('You have not entered a Note describing this payment.\n\nContinue anyway?'))
    return;
  document.adhocpayform.submit();
}


function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

</script>
