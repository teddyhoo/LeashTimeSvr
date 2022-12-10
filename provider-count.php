<? // provider-count.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";


function countProvidersPerMonth($date) {
	$start = date('Y-m-1', strtotime($date));
	$end = date('Y-m-t', strtotime($date));
	$providers = fetchCol0("SELECT DISTINCT providerptr from tblappointment
				WHERE canceled IS NULL AND date >= '$start' AND date <= '$end'");
	return $providers;
}

$date = $_REQUEST['date'];
if(!$date) $date = date('Y-m-d');
print_r(countProvidersPerMonth($date));