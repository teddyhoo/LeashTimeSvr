<? //invoice-print.php
/* Two Modes:
Automatic
	GET with ids: print all invoices
	isset(ids): print all invoices
*/

// ids
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
if(userRole() == 'c') {
	$locked = locked('c-');
	$client = $_SESSION['auth_user_id'];
	$clientMode = 1;
}
else $locked = locked('o-');
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

updateTable('tblinvoice', array('notification'=>'mail'), "invoiceid IN ($idsString) AND notification IS NULL");

$first = true;
foreach($ids as $id) {
	if(!$first) echo "<p style='page-break-before:always;'>";
	displayInvoice($id, $first);
	$first = false;
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function printInvoices() {
	document.getElementById("printlink").style.display="none";window.print();
	<? if(!$clientMode) { ?>
	ajaxGetAndCallWith("invoices-printed-ajax.php?ids=<?= $idsString ?>", updateOpener, 'x')
	<? } ?>
}
function updateOpener(argle, txt) {
	//alert("["+argle+"] "+txt);
	window.opener.update();
}
updateOpener();
</script>