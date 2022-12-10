<?
// payment-transaction-id-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "pay-fns.php";


// Determine access privs
$locked = locked('o-');

extract($_REQUEST);

$payment = getProviderPayment($id);

if($_POST) {
  if($payment['transactionid'] != $transactionid) {
    updateTable('tblproviderpayment', array('transactionid' => $transactionid), "paymentid = $id", 1);
  }
	echo "<script language='javascript'>if(window.opener.update) window.opener.update('$target'); window.close();</script>";
}
?>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" /> 
<h2>Check / Transaction # Editor</h2>
<form name='thisform' method='POST'>
<?
hiddenElement('id', $id);
hiddenElement('target', $target);
labeledInput('Check / Transaction :', 'transactionid', $payment['transactionid']);
echo " ";
echoButton('', 'Save', 'document.thisform.submit()');
echo " ";
echoButton('', 'Quit', 'window.close()');
?>
</form>
