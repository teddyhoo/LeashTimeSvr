<? // price-check.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";


//print_r(priceCheck(2085));

$sql = "SELECT packageid, current FROM `tblservicepackage` where irregular=0 AND onedaypackage = 0  ORDER BY `tblservicepackage`.`packageid`  DESC"; //
$vals = fetchKeyValuePairs($sql);
$ids = array_keys($vals);
sort($ids);
$n = 0;
$diff = 0;
foreach($ids as $id) {
	$check = priceCheck($id);
	if(''.$check[0] != ''.$check[1]) {
		$link = "<a target=feck href='".globalURL("service-nonrepeating.php?packageid=$id")."'>$id</a>";
		$id = $vals[$id] ? "*$link" : $link;
		echo "Package id: $id  Reported: {$check[0]} Calculated: {$check[1]}<br>";
		$diff += $check[0] - $check[1];
		$n++;
	}
}
echo "<p>$n differences.  \$$diff disparity (reported - actual)<p>* = current version of package";	
	
	

function priceCheck($packageid) {
	$package = getPackage($packageid);
	$services = fetchAssociations("SELECT *, charge+ifnull(adjustment, 0) as totalservicecharge FROM tblservice WHERE packageptr = $packageid");
	$firstDay = 0;$lastDay = 0;$daysInBetween = 0;
	foreach($services as $serv) {
		if($serv['firstLastOrBetween'] == 'first') $firstDay += $serv['totalservicecharge'];
		else if($serv['firstLastOrBetween'] == 'last') $lastDay += $serv['totalservicecharge'];
		else $middies[] = $serv;
	}
	for($day = strtotime("+ 1 day", strtotime($package['startdate'])); $day < strtotime($package['enddate']); $day = strtotime("+ 1 day", $day)) {
		foreach($middies as $serv) {
			if(daysOfWeekIncludes($serv['daysofweek'], date('w', $day)))
				$daysInBetween += $serv['totalservicecharge'];
		}
	}
	$price = (float)((int)($package['packageprice']*100)) / 100;
	return array($price, $firstDay + $lastDay + $daysInBetween);
}
		
		
		
function daysOfWeekIncludes($daysOfWeek, $dayNumber) { // dayNumber: 0-6, 0=Sunday
	if($daysOfWeek == 'Every Day') return true;
	else if($daysOfWeek == 'Weekends') return $dayNumber == 0 || $dayNumber == 6;
	else if($daysOfWeek == 'Weekdays') return $dayNumber > 0 && $dayNumber < 6;
	else {
		$allDays = array('Su','M','Tu','W','Th','F','Sa');
		$darray = explode(',', $daysOfWeek);
		for($i=0; $i < count($darray); $i++) 
			if($allDays[$dayNumber] == $darray[$i]) return true;
	}
}
		
	