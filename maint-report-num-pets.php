<? // maint-report-num-pets.php
// for all active clients and pets
// show the distribution of per-owner pet counts
// for each business
set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');



$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz WHERE activebiz=1 AND test=0 ORDER BY bizname", 'bizid', 1);

foreach($bizzes as $bizid => $biz) {
	$petCounts = getPetCounts($biz);
	if(!$petCounts) {
		unset($bizzes[$bizid]);
		continue;
	}
	$row = array('Biz'=>"{$biz['bizname']} ({$biz['db']})");
	foreach($petCounts as $numpets => $numclients) {
		if(!$row['Clients']) {
			$row['Clients'] = array_sum($petCounts);
			$totals['Clients'] += array_sum($petCounts);
		}
		$row[$numpets] = $numclients;
		$totals[$numpets] += $numclients;
	}
	$rows[] = $row;
}
$cols = array('Biz', 'Clients');
foreach(array_unique($totals) as $k=>$v) $cols[] = $k;
sort($cols);
// 6134+1082+118+21+3+1+1
foreach($cols as $col) $colpairs[$col] = $col;

echo "<b>Distribution of Pet Counts</b><p>";

echo $clients.' total clients.  '.count($bizzes).' bizzes<hr>';
$totals['Biz'] = "<b>TOTALS</b>";
$rows[] = $colpairs;
$rows[] = $totals;
tableFrom($colpairs, $rows, $attributes='border=1');
echo "<hr>";
echo join(',', $cols).'<br>';
foreach($rows as $row) {
	if($row['Biz'] == "<b>TOTALS</b>") break;
	foreach($cols as $k) $frow[$k] = $row[$k];
	echo join(',', $frow).'<br>';
}



function getPetCounts($biz) {
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
	$activeOwners = fetchCol0("SELECT clientid FROM tblclient WHERE active = 1");
	if(!$activeOwners) return null;
	$sql = "SELECT ownerptr, count( * )
						FROM tblpet
						WHERE active =1 AND ownerptr IN (".join(',', $activeOwners).")
						GROUP BY ownerptr";
	foreach(fetchKeyValuePairs($sql) as $id=>$count)
		$counts[$count] += 1;
	return $counts;
}
