<? // clients-logging-in.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";

$start = $_GET['start'];
$end = $_GET['end'];
if(!$start || !$end) "Need start and end.";
$start = date('Y-m-d', strtotime($start));
$end = date('Y-m-d', strtotime($end));

$loginids = fetchCol0(
	"SELECT DISTINCT TRIM(loginid) 
		FROM tbllogin
		WHERE success = 1
		AND LastUpdateDate >= '$start'
		AND LastUpdateDate <= '$end'", 1);
		
$counts = fetchKeyValuePairs(
	"SELECT bizptr, COUNT(*) 
	 FROM tbluser WHERE loginid IN ('".join("','", $loginids)
	 ."') AND rights LIKE 'c-%'
	 GROUP BY bizptr", 1);
	 
print_r($counts);
