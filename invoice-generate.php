<? // invoice-generate.php
/* Two Modes:
Automatic - called by AJAX: 
	clients: create invoices for supplied client ids
	asOfDate: select billables with itemdates up to asOfDate
	target: invoke target with resulting invoice ids.
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "invoice-fns.php";
$locked = locked('o-'); 


if($_GET && $_GET['clients']) { // Automatic mode
	extract($_REQUEST);
	$clients = explode(',', $clients);
	$ids = array();
	foreach($clients as $client) {
		$ids[] = createCustomerInvoiceAsOf($client, $asOfDate);
	}
	$ids = join(',', $ids);
	if($target == 'email') include "invoice-email.php";
	else if($target == 'mail') include "invoice-print.php";
	exit;
}

