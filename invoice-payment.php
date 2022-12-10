<?
/* invoice-payment.php
*
* Parameters: 
* id - id of invoice to be paid
*
* credit may not be modified (except for reason) once amountused > 0
*/

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "gui-fns.php";
require_once "credit-fns.php";
require_once "invoice-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);

$invoice = null;

if($invoiceid) {
	// get invoice number (strip away prefix)
	for($i=0; $i < strlen($invoiceid) && !is_numeric($invoiceid[$i]); $i++) ;
	$invoiceid = trim(substr($invoiceid, $i));
	if(!$invoiceid || !is_numeric($invoiceid)) $error = "Bad Invoice ID [{$_REQUEST['invoiceid']}].";
	else {
		$invoice = getInvoice($invoiceid);
		if(!$invoice) $error = "No invoice with ID [{$_REQUEST['invoiceid']}] found.";
		else if($invoice['paidinfull']) {
			$error = "Invoice [{$_REQUEST['invoiceid']}] has already been paid in full.";
			$invoice = null;
		}
	}
}

if($invoice) { 
	$client = $invoice['clientptr'];
	$invoiceid = null;  // $invoiceid will be referred to by payment-edit.php
	include "payment-edit.php";
	exit;
}

$header = 'Invoice Payment';
$windowTitle = $header;
require "frame-bannerless.php";
?>
<h2><?= $header ?></h2>
<?
if($error) {  // very low level error
  echo "<p style='color:red'>$error</p>";
}
echo "<form name='payinvoiceform' method='POST'>";
$invoiceIDMaxLength = strlen(invoiceIdDisplay(5));
labeledInput('Invoice ID:', 'invoiceid', '');
echoButton('', 'Enter Invoice ID', 'checkAndSubmit()');
hiddenElement('paybuttonlocked', '');
echo "</form>";


//print_r($source);exit;
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>

setPrettynames('invoiceid','Invoice ID');	
	
function checkAndSubmit() {
	if(document.getElementById('paybuttonlocked').value == 1) return;
	if(MM_validateForm(
		'invoiceid', '', 'R'
		)) {
		document.getElementById('paybuttonlocked').value = 1;
		document.payinvoiceform.submit();
	}
}

function autoSubmit() {
	var kval = ''+document.payinvoiceform.invoiceid.value;
	if(kval.length < <?= $invoiceIDMaxLength ?>) return true;
	checkAndSubmit();
}


if(document.payinvoiceform.invoiceid) {
	document.payinvoiceform.invoiceid.onkeyup=autoSubmit;
	document.payinvoiceform.invoiceid.onpaste=autoSubmit;
	document.payinvoiceform.invoiceid.focus();
}

</script>
</body>
</html>
