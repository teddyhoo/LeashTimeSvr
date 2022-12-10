<? // billing-statement-view.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "billing-fns.php";
	require_once "cc-processing-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);

$error = "";
if(!isset($id)) $error = "Client ID not specified.";


$windowTitle = 'Billing Invoice';
$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";

if($error) {
	echo $error;
	exit;
}

$sendOptions = array('email'=>"Email This Invoice", 'print'=>"Print This Invoice");
$paymentSource = getClearPrimaryPaySource($id);
if(staffOnlyTEST() && $paymentSource) {
	if(!isAnExpiredCard($paymentSource)) $sendOptions['charge'] = 'Charge Client and Email This Invoice';
	else if($paymentSource['ccid']) $expiredCard = 1;
}
if($invoiceby == 'mail') {
	$sendOptions = array_reverse($sendOptions);
}

if($packageptr && !$firstDay) {
	$package = fetchFirstAssoc("SELECT * from tblservicepackage WHERE packageid = $packageptr LIMIT 1", 1);
	$firstDay = $package['startdate'];
	$lastDay = $package['enddate'];
}

$emaillookahead = $lookahead ? $lookahead : round((strtotime($lastDay) - strtotime($firstDay)) / 86400); // 24 * 60 * 60

$args = "&firstDay=$firstDay&lookahead=$emaillookahead&literal=$literal&packageptr=$packageptr&excludePriors={$_REQUEST['excludePriorUnpaid']}";
$each = each($sendOptions);
$noEmail = !$email && $each['key'] == 'email';

$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#gi');
if($lastDay && !$lookahead) 
	$lookahead = round((strtotime($lastDay) - strtotime($firstDay)) / 86400); // 24 * 60 * 60

if(!$readOnly) {
	$links = 
		array(
			'print'=>"document.location.href=\"billing-statement-print.php?ids=$id$args\"",
			'email'=>"javascript:document.location.href=\"billing-statement-email.php?ids=$id$args&includePayNowLink=\"+(payNowEnabled() && document.getElementById(\"includePayNowLink\").checked ? 1 : 0)",
			'charge'=>"javascript:document.location.href=\"billing-statement-charge-email.php?amount=\"+document.getElementById(\"amountDue\").value+\"&ids=$id$args&includePayNowLink=\"+(payNowEnabled() && document.getElementById(\"includePayNowLink\").checked ? 1 : 0)",
					);
	echoButton($each['key'].'Button', $each['value'], $links[$each['key']], 'BigButton', 'BigButtonDown');
	echo " or ";
	$each = each($sendOptions);
	echoButton($each['key'].'Button', $each['value'], $links[$each['key']]);
	if($each = each($sendOptions)) {
		echo " or ";
		echoButton($each['key'].'Button', $each['value'], $links[$each['key']]);
	}
	else if($expiredCard) echo " <span style='color:red'>Client's credit card has expired.</span>";
	
	
	hiddenElement('amountDue', 0);
	
	$includePayNowLink = $_SESSION['preferences']['includePayNowLink'];
	$client = fetchFirstAssoc("SELECT clientid, userid FROM tblclient WHERE clientid = $id", 1);
	if($includePayNowLink && merchantInfoSupplied() && $_SESSION['preferences']['ccAcceptedList'] && $client['userid']) {//
			echo "<br>";
			labeledCheckbox('... with a Pay Now Link', 'includePayNowLink', $includePayNowLink, null, null, 'payNowClicked()', true);
			//$inv = getBillingInvoice($id, $firstDay, $lookahead, $literal, $excludePriorUnpaidBillables);
			//$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>calculateAmountDue()); // calculateAmountDue() uses gloabls from getBillingInvoice
			require_once "billing-statement-display-class.php";
			$billingStatement = new BillingStatement($id);
			$billingStatement->
				populateBillingInvoice($firstDay, $lookahead, 
							$literal, $showOnlyCountableItems, 
							$_REQUEST['packageptr'], $_REQUEST['excludePriorUnpaid']);
			$amountDue = $billingStatement->calculateAmountDue();
			$payNowInfo = array('note'=>$standardMessageSubject, 'amount'=>$amountDue); // calculateAmountDue() uses gloabls from getBillingInvoice
			$payNowLink = payNowLink($client, $payNowInfo);
	}
	else if(!$client['userid']) {
			echo "<br>(Client must have LeashTime login credentials to include a Pay Now link)."; 
	}
	if($noEmail)
		echo "<br>(but no email address on record)";
}


echo "<p>";

$payNowArgument = !'important'; // Pay Online option will only appear if you have "Credit Cards Accepted" supplied mattOnlyTEST() ? true : !$noEmail;
//function displayBillingInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true, $literal=false, $showOnlyCountableItems=false) {
//displayBillingInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true, $literal=false, $showOnlyCountableItems=false)
//displayBillingInvoice($id, $firstDay, $lookahead, $firstInvoicePrinted=true, $literal, $excludePriorUnpaidBillables, $payNow=$payNowArgument, $_REQUEST['packageptr']);
require_once "billing-statement-display-class.php";
$billingDisplay = new BillingStatementDisplay($id);
$billingDisplay->
		displayBillingInvoice($firstDay, $lookahead, $firstInvoicePrinted=true, 
		$literal, $excludePriorUnpaidBillables, $payNow=$payNowArgument, 
		$_REQUEST['packageptr'], $_REQUEST['excludePriorUnpaid']);
// displayBillingInvoice sets $amountDue
?>
<script language='javascript'>

function doOnLoad() {
	payNowClicked();
	<? if($amountDue <= 0) echo "if(document.getElementById('chargeButton')) document.getElementById('chargeButton').style.display='none';" ?>
}

function payNowClicked() {
	if(payNowEnabled()) {
		var el = document.getElementById('includePayNowLink');
		document.getElementById('paynowcell').innerHTML = el.checked ? "<?= $payNowLink ?>" : '';
	}
}

function payNowEnabled() {
	return document.getElementById('includePayNowLink');
}

window.onload = doOnLoad;
if(document.getElementById('amountDue')) document.getElementById('amountDue').value = '<?= $amountDue ?>';
</script>
<?
if($screenLog) echo "<div style='background:lightblue'>$screenLog</div>";

