<? // timeframe-order-set.php
// order - csv of servicetypeid

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "timeframe-fns.php";

// Verify login information here
locked('o');
$order = explode(',', $_REQUEST['order']);

reorderTimeframes($order);
