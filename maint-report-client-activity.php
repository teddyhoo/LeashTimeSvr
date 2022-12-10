<? // maint-report-client-activity.php
// for a given day show for each active business
// number of pet owners who logged in
// number of each kind of request
set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');


$date = $_REQUEST['date'];
$start = $_REQUEST['start'];
$end = $_REQUEST['end'];

$loginFilter = $date 
					? "LastUpdateDate LIKE '$date%'"
					: "LastUpdateDate >= '$start 00:00:00' AND LastUpdateDate <= '$end 23:59:59'";

$sql = "SELECT userid, bizptr
	FROM tbllogin log
	LEFT JOIN tbluser u ON u.loginid = log.loginid 
	WHERE success = 1 AND $loginFilter AND rights LIKE 'c-%'";

/* $sql = "SELECT log.loginid, 1
	FROM tbllogin log
	WHERE success = 1 AND LastUpdateDate LIKE '$date%'";*/

$users = fetchKeyValuePairs($sql);

foreach($users as $c =>$bizptr) $bizCounts[$bizptr] += 1;

$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE bizid IN (".join(',', array_keys($bizCounts)).") ORDER BY bizname", 'bizid', 1);
arsort($bizCounts);
//print_r($users);
/*
foreach($bizzes as $biz) {
	echo "{$biz['bizname']} ({$biz['db']}): clients: {$bizCounts[$biz['bizid']]}<br>";
}
*/
$excludeTypes	= explode(',', 'SystemNotification,BillingReminder,Prospect,Reminder,TimeOff,'
															.'NotificationResponse,UnassignedVisitOffer,ICInvoice,VisitReport,'
															.'BizSetup,Comment,Spam,Discontinue');
$excludeTypesQuoted = "'".join("','", $excludeTypes)."'";
$excludeTypes	= join(', ', $excludeTypes);

foreach($bizCounts as $bizid => $count) {
	$biz = $bizzes[$bizid];
	if($date) $bizRequests = requestsForDate($bizzes[$bizid], $date);
	else $bizRequests = requestsForDates($bizzes[$bizid], $start, $end);
	$row = array('Biz'=>"{$biz['bizname']} ({$biz['db']})", 'Clients'=>$count,'All Reqs'=>array_sum($bizRequests));
	$totals['Clients'] += $count;
	$totals['All Reqs'] += array_sum($bizRequests);
	foreach($bizRequests as $type=>$reqs) {
		$row[$type] = $reqs;
		$totals[$type] += $reqs;
	}
	$rows[] = $row;
}
$cols = array('Biz', 'Clients','All Reqs');
foreach(array_unique($globaltypes) as $type) $cols[] = $type;
foreach($cols as $col) $colpairs[$col] = $col;
//echo "count: ".print_r($rows,1).'<hr>';shortDate(strtotime($start))
$printDate = $date ? shortDate(strtotime($date)) : shortDate(strtotime($start)).' through '.shortDate(strtotime($end));
echo "<b>Clients who logged in $printDate</b><p>";
$theStart = $date ? $date : $start;
$simpleRequestNote = (strcmp($theStart, '2016-04-15') >= 0)
	? "<br>After 4/15/2016 12:20, Simple Schedule Requests are broken out from General Requests."
	: "";
echo "(excludes $excludeTypes)$simpleRequestNote<p>";

echo count($users).' total clients.  '.count($bizCounts).' bizzes<hr>';
$totals['Biz'] = "<b>TOTALS</b>";
$rows[] = $colpairs;
$rows[] = $totals;
tableFrom($colpairs, $rows, $attributes='border=1');


function requestsForDate($biz, $date) {
	global $globaltypes, $excludeTypesQuoted;
	$rtypes = array();
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	$requests = fetchRows("SELECT requesttype, extrafields FROM tblclientrequest WHERE received LIKE '$date%' AND requesttype NOT IN ($excludeTypesQuoted)", 1);
	foreach($requests as $pair) {
		list($type, $extra) = $pair;
		if($type == 'General' && strpos($extra, 'simpleschedule') > 0) $type = 'SimplSched';
		$rtypes[$type] += 1;
		$globaltypes[] = $type;
	}
	return $rtypes;
}
	

function requestsForDates($biz, $date1, $date2) {
	global $globaltypes, $excludeTypesQuoted;
	$rtypes = array();
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	$requests = 
		fetchRows(
			"SELECT requesttype, extrafields FROM tblclientrequest 
					WHERE received >= '$date1 00:00:00' AND received <= '$date2 23:59:59' AND requesttype NOT IN ($excludeTypesQuoted)", 1);
	foreach($requests as $pair) {
		list($type, $extra) = $pair;
		if($type == 'General' && strpos($extra, 'simpleschedule') > 0) $type = 'SimplSched';
		$rtypes[$type] += 1;
		$globaltypes[] = $type;
	}
	return $rtypes;
}
	

