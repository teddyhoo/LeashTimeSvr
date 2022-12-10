<? // timeframe-order-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "timeframe-fns.php";

// Verify login information here
locked('o');
//extract($_REQUEST);
$itemList = getTimeframeOrder();
$title = "Re-order Named Time Frames Menu";
$saveAction = 'timeframe-order-set.php?order=';
//$test=1;
include "display-order-edit.php";
