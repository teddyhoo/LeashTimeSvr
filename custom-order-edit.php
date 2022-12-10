<? // custom-order-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "preference-fns.php";
require_once "custom-field-fns.php";

// Verify login information here
locked('o');
$prefix = $_REQUEST['prefix'];
if($_REQUEST['order']) {
	// save order
	setPreference("order_$prefix", $_REQUEST['order']);
}

$itemList = customFieldDisplayOrder($_REQUEST['prefix']);  // custom1=>"My Label", ..
$title = "Re-order Custom Fields";
$saveAction = "custom-order-edit.php?prefix=$prefix&order=";

include "display-order-edit.php";
