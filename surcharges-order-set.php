<? // surcharges-order-set.php
// order - csv of servicetypeid

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "surcharge-fns.php";

// Verify login information here
locked('o');
$order = explode(',', $_REQUEST['order']);

foreach($order as $menuorder => $surchargetypeid)
	doQuery("UPDATE tblsurchargetype SET menuorder=$menuorder WHERE surchargetypeid=$surchargetypeid",1);
	
getSurchargeTypesById(1);	