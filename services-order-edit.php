<? // services-order-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

// Verify login information here
locked('o');
//extract($_REQUEST);
if(!$_SESSION['servicenames']) {
	require_once "service-fns.php";
	getServiceNamesById('refresh');  // populates $_SESSION['servicenames']
}

$itemList = $_SESSION['servicenames'] ? $_SESSION['servicenames'] : $_SESSION['servicenames'];
$title = "Re-order Services Menu";
$saveAction = 'services-order-set.php?order=';

include "display-order-edit.php";
