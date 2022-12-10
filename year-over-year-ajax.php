<? // year-over-year-ajax.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "year-over-year-fns.php";

// return JSON arrays suitable for use by chartist.js
// stats arg should be:
// 		yoy-visits-ytd-month - returns double-bar visit counts for year-over-year, year to date, by month 
//			ex: https://leashtime.com/year-over-year-ajax.php?stats=yoy-visits-ytd-month
// 		yoy-visits-ytd-month and baseYear - returns double-bar visit counts for year-over-year, year to date, by month 
//			ex: https://leashtime.com/year-over-year-ajax.php?stats=yoy-visits-ytd-month&baseYear=2015

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('#vr');

$stats = $_GET['stats'];

if($stats == 'yoy-visits-ytd-month') {
	/*
	var data = {
		labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			series: [
			[5, 4, 3, 7, 5, 10, 3, 4, 8, 10, 6, 8],
			[3, 2, 9, 5, 4, 6, 4, 6, 7, 8, 7, 4]
		]
	};
	*/
	$data['labels'] = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	
	$_GET['baseYear'];
	$baseYear = $yearLabel = $baseYear ? $baseYear : date('Y'); 
	$data['series'][] = 
		array('name'=>$yearLabel-1, 'data'=>yearToDateMonthlyVisitCounts($lastYear=true, $baseYear));
	$data['series'][] = 
		array('name'=>$yearLabel, 'data'=>	yearToDateMonthlyVisitCounts($lastYear=false, $baseYear));
	$data['labels'] = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	echo json_encode($data);
}
	
