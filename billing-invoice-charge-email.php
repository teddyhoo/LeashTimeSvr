<? // billing-invoice-charge-email.php
//$args = "&firstDay=$firstDay&lookahead=$emaillookahead&literal=$literal&packageptr={$_REQUEST['packageptr']}"
//ids=$id$args&includePayNowLink

$client = $_REQUEST['ids'];
$amount = $_REQUEST['amount'];
$successDestination = 
	'billing-invoice-email.php'
	.substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], '?'))
	.'&paymentptr=';
$suppressSavePaymentButton = 1;

require_once "payment-edit.php";
