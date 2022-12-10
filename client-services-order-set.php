<? // client-services-order-set.php
// order - csv of servicetypeid

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-services-fns.php";

// Verify login information here
locked('o');
$order = explode(',', $_REQUEST['order']);

reorderClientServices($order);
