<? // surcharges-order-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "surcharge-fns.php";

// Verify login information here
locked('o');
//extract($_REQUEST);

$itemList = getSurchargeTypesById();
$title = "Re-order Surcharges Menu";
$saveAction = 'surcharges-order-set.php?order=';

include "display-order-edit.php";
