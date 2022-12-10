<? // client-services-order-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-services-fns.php";

// Verify login information here
locked('o');
//extract($_REQUEST);
$itemList = getClientServiceNameOrder();
$title = "Re-order Client Services Menu";
$saveAction = 'client-services-order-set.php?order=';
//$test=1;
include "display-order-edit.php";
