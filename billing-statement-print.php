<? //billing-statement-print.php
/* Two Modes:
Automatic
	GET with ids: print all invoices
	isset(ids): print all invoices
*/
// ids
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "billing-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract($_REQUEST);

$error = "";
if(!isset($ids)) $error = "Invoice IDs not specified.";


$windowTitle = 'Print Invoices';
$extraBodyStyle = 'padding:10px;background:white;';
$extraBodyAttributes = "onMouseDown=\"if(document.getElementById('printlink').style.display!='inline') document.getElementById('printlink').style.display='inline'\"";
require "frame-bannerless.php";

if($error) {
	echo $error;   // document.getElementById('printlink').style.display='inline'
	exit;
}
echo "<center>";
fauxLink("<h1 id='printlink' style='display:inline;'>Click Here to Print</h1>", "printInvoices();");
echo "\n</center><p>\n";
$idsString = $ids;
$ids = explode(',',$ids);

$first = true;
foreach($ids as $id) { // is count($ids) ever > 0 ?
	if(!$first) echo "<p style='page-break-before:always;'>";
	$showOnlyCountableItems = TRUE  || staffOnlyTEST() ? false : true;
	$suppressPriorUnpaidCreditMarkers =  true;  // Suppress [C] marker

	require_once "billing-statement-display-class.php";
	$billingStatement = new BillingStatement($id);
	$billingStatement->
		populateBillingInvoice($firstDay, $lookahead, 
					$literal, $showOnlyCountableItems, 
					$_REQUEST['packageptr'], ($excludePriors ? 1 : 0));
	$billingDisplay = new BillingStatementDisplay($billingStatement);
	$invoiceContent = $billingDisplay->
		displayBillingInvoice($firstDay, $lookahead, $firstInvoicePrinted=$first, $literal, $showOnlyCountableItems=false, 
			$includePayNowLink=false, $packageptr, $excludePriors);
	$first = false;
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function printInvoices() {
	document.getElementById("printlink").style.display="none";window.print();
	ajaxGetAndCallWith('billing-statement-printed-ajax.php?ids=<?= $idsString."&firstDay=$firstDay&lookahead=$lookahead&literal=$literal" ?>', updateOpener, '<?= $idsString ?>')
}

function updateOpener(argle, txt) {
<? if(mattOnlyTEST()) { ?>
	//alert("["+argle+"] "+txt);
	//return;
<? } ?>
	//if(window.opener.update) window.opener.update('account', txt);
}
updateOpener();
</script>