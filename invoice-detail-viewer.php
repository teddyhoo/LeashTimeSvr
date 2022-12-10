<? // invoice-detail-viewer.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
extract(extractVars('client,invoice,asOfDate', $_REQUEST));
$asOfDate = $asOfDate ? $asOfDate : date('Y-m-d');

if($invoice) {
	$invoiceVals = fetchFirstAssoc("SELECT clientptr, asofdate FROM tblinvoice WHERE invoiceid = $invoice LIMIT 1");
	$client = $invoiceVals['clientptr'];
	$asOfDate = $invoiceVals['asofdate'];
}
$client = getOneClientsDetails($client);

if($invoice) $windowTitle = " # $invoice";
$windowTitle = "Invoice$windowTitle Details: {$client['clientname']} ";
if(!$invoice)  $windowTitle .= "Preview ";
$windowTitle .= "As of:  $asOfDate";
//$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>Canceled visits are omitted<p>";
createInvoiceDetailView($asOfDate, $client['clientid'], $invoice);

function serviceLink($row) {
	$petsTitle = $row['pets'] 
	  ? htmlentities("Pets: {$row['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	$targetPage = 'appointment-view.php';
	$label = $row['custom'] ? '<b>(M)</b> ' : '';
	$label .= $_SESSION['servicenames'][$row['servicecode']];
	return "<a href=# 
	       onClick='openConsoleWindow(\"editappt\", \"$targetPage?id={$row['appointmentid']}\",530,450)' 
	       >$label</a>"; //title='$petsTitle'
}

function surchargeLink($row) {
	static $surchargeNames;
	if(!$surchargeNames) $surchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	$targetPage = 'surcharge-edit.php';
	$label = $surchargeNames[$row['surchargecode']];
	return "<a href=# 
	       onClick='openConsoleWindow(\"editappt\", \"$targetPage?id={$row['surchargeid']}\",530,450)' 
	       >Surcharge: $label</a>";
}

function chargeLink($row, $billableptr) {
	
	return $row['comptype'] == 'adhoc' ? 'Adhoc payment' : 'Gratuity';
	
	$myTitle = $row['descr'] 
	  ? htmlentities("Pets: {$row['descr']}", ENT_QUOTES)
	  : "No note specified.";
	$targetPage = "provider-adhoc-payment-payable.php?payableptr=$payableptr";
	$label = $row['comptype'] == 'adhoc' ? 'Adhoc payment' : 'Gratuity';
	return "<a href=# 
	       onClick='openConsoleWindow(\"editappt\", \"$targetPage\",530,450)' 
	       title='$myTitle'>$label</a>";
}

?>
<script language='javascript' src='common.js'></script>