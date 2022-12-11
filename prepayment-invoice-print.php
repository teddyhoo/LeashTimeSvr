<? //prepayment-invoice-print.php
/* Two Modes:
Automatic
	GET with ids: print all invoices
	isset(ids): print all invoices
*/

// ids
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "prepayment-fns.php";

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
$includePriorUnpaidBillables = !$excludePriorUnpaidBillables;
$first = true;
foreach($ids as $id) {
	if(!$first) echo "<p style='page-break-before:always;'>";
//NEWdisplayPrepaymentInvoice($invoiceOrClientId, $firstDay, $lookahead, $firstInvoicePrinted=true, $includePriorUnpaidBillables=false, $showOnlyCountableItems=false, $scope=null) {

	NEWdisplayPrepaymentInvoice($id, $firstDay, $lookahead, $first, $includePriorUnpaidBillables);
	$first = false;
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function printInvoices() {
	document.getElementById("printlink").style.display="none";window.print();
	ajaxGetAndCallWith('prepayment-invoices-printed-ajax.php?ids=<?= $idsString."&firstDay=$firstDay&lookahead=$lookahead" ?>', updateOpener, 'x')
}

function updateOpener(argle, txt) {
	//alert("["+argle+"] "+txt);
	window.opener.update();
}
updateOpener();
</script>