<? // invoice-view.php
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
if(!isset($id)) $error = "Invoice ID not specified.";


$windowTitle = 'View Invoice';
$extraBodyStyle = 'padding:10px;background:white;';
require "frame-bannerless.php";

if($error) {
	echo $error;
	exit;
}
$invoice = getInvoice($id);

$readOnly = userRole() == 'd' && !strpos($_SESSION['rights'], '#gi');

if(!$readOnly) {
	if(isset($email)) {
		if(!$clientMode) echoButton('', 'Email this Invoice', "document.location.href=\"invoice-email.php?id=$id&email=$email\"");
		echo "<img src='art/spacer.gif' width=20 height=1>";
	}
	echoButton('', 'Print this Invoice', "document.location.href=\"invoice-print.php?ids=$id\"");
	echo "<img src='art/spacer.gif' width=40 height=1>";
	echoButton('', 'View Details', "viewDetails($id)");
}

if($invoice['lastsent']) {
	$notification = $invoice['notification'] == 'mail' ? 'printed' : ($invoice['notification'] ? $invoice['notification'].'ed' : 'sent');
	echo "<span style='font-size:1.5em'><img src='art/spacer.gif' width=30 height=1>Last $notification on ".
		shortDate(strtotime($invoice['lastsent'])).'</span>';
}
//if(!$readOnly) {echo " ";echoButton('', 'View Details', "viewDetails($id)");}

echo "<p>";

displayInvoice($invoice);
?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
function viewDetails(invoice) {
	openConsoleWindow('editcredit', 'invoice-detail-viewer.php?invoice='+invoice, 800, 440);
}
</script>
