<? // services-order-set.php
// order - csv of servicetypeid

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";

// Verify login information here
locked('o');
$order = explode(',', $_REQUEST['order']);

foreach($order as $menuorder => $servicetypeid)
	doQuery("UPDATE tblservicetype SET menuorder=$menuorder WHERE servicetypeid=$servicetypeid");
	
getServiceNamesById(1);	