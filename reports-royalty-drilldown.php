<? // reports-royalty-drilldown.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "projections.php";

locked('o-');
if(!$_SESSION["corporateuser"]) { 
	echo "Insufficient Access Rights";
	exit;
}
$start = $_REQUEST['start'];
$end = $_REQUEST['end'];

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=Revenues_$start"."_$end.csv ");

dumpRevenuesDrillDownCSVInRange($start, $end);