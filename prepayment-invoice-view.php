<? // prepayment-invoice-view.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "prepayment-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);

$error = "";
if(!isset($id)) $error = "Client ID not specified.";


$windowTitle = 'View Prepayment Invoice';
$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";

if($error) {
	echo $error;
	exit;
}

$sendOptions = array('email'=>"Email This Invoice", 'print'=>"Print This Invoice");
if($invoiceby == 'mail') {
	$sendOptions = array_reverse($sendOptions);
}
echo "<table><tr>";
$emaillookahead = $lookahead ? $lookahead : round((strtotime($lastDay) - strtotime($firstDay)) / 86400); // 24 * 60 * 60
$args = "&firstDay=$firstDay&lookahead=$emaillookahead&excludePriorUnpaidBillables=$excludePriorUnpaidBillables";
if(TRUE || mattOnlyTEST()) {
$args .= "&scope=$scope";
//echo "[scope = $scope] NRDateFilter: $NRDateFilter";
//exit;
}

$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#gi');

if(TRUE || mattOnlyTEST()) {
$button = 'BigButton';
$buttonDown = 'BigButtonDown';
foreach($sendOptions as $k => $v) {
	if($k == 'email') {
		echoButton('', 'Email This Invoice', "document.location.href=\"prepayment-invoice-email.php?ids=$id$args\"", $button, $buttonDown);
		if(!$email) echo "<br>(but no email address on record)";
	}
	else echoButton('', 'Print This Invoice', "window.print()", $button, $buttonDown);
	$button = '';
	$buttonDown = '';
}
}
else {
$each = each($sendOptions);
$noEmail = !$email && $each['key'] == 'email';
if(!$readOnly) {
	echoButton('', $each['value'], "document.location.href=\"prepayment-invoice-{$each['key']}.php?ids=$id$args\"", 'BigButton', 'BigButtonDown');
	echo " or ";
	$each = each($sendOptions);
	echoButton('', $each['value'], "document.location.href=\"prepayment-invoice-{$each['key']}.php?ids=$id$args\"");
	if($noEmail)
		echo "<br>(but no email address on record)";
}
}



echo "<p>";

$includeallAppointmentsInPrepaymentInvoice = $includeall;

if($lastDay && !$lookahead) 
	$lookahead = round((strtotime($lastDay) - strtotime($firstDay)) / 86400); // 24 * 60 * 60
//$lookahead = round((strtotime($lastDay) - strtotime($firstDay)) / 86400); // 24 * 60 * 60

$includePriorUnpaidBillables = !$excludePriorUnpaidBillables;

//NEWdisplayPrepaymentInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true, $includePriorUnpaidBillables=false, $showOnlyCountableItems=false, $scope=null)

if(TRUE) NEWdisplayPrepaymentInvoice($id, $firstDay, $lookahead, $firstInvoicePrinted=true, $includePriorUnpaidBillables, $showOnlyCountableItems=false, $scope); // $scope is passed in for BillingReminders
//else displayPrepaymentInvoice($id, $firstDay, $lookahead);

if($screenLog) echo "<div style='background:lightblue'>$screenLog</div>";

