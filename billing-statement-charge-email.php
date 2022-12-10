<? // billing-statement-charge-email.php
//$args = "&firstDay=$firstDay&lookahead=$emaillookahead&literal=$literal&packageptr={$_REQUEST['packageptr']}"
//ids=$id$args&includePayNowLink

$client = $_REQUEST['ids'];
$amount = $_REQUEST['amount'];
$successDestination = 
	'billing-statement-email.php'
	.substr($_SERVER["REQUEST_URI"], strpos($_SERVER["REQUEST_URI"], '?'))
	.'&paymentptr=';
	
// e.g., billing-statement-email.php?amount=1&ids=771&firstDay=2015-10-01&lookahead=30&literal=&packageptr=&excludePriors=&includePayNowLink=1&paymentptr=
$suppressSavePaymentButton = 1;

require_once "payment-edit.php";