<? // maint-admin-counts.php
// use this script by hand to modify all LT biz databases
set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";

// find active dispatcher + manager counts for ALL bizzes

$counts = fetchAssociationsKeyedBy(
"SELECT bizptr, bizptr, count(*) as admins FROM tbluser 
	WHERE (rights LIKE 'o-%' OR rights LIKE 'd-%') AND active = 1 AND ltstaffuserid = 0 GROUP BY bizptr", 'bizptr');

// eliminate test and inactive bizzes
foreach(fetchCol0("SELECT bizid FROM tblpetbiz WHERE activebiz = 0 OR test = 1") as $k)
	unset($counts[$k]);
unset($counts[0]);
//$counts = array_merge($counts);

//print_r($counts);

foreach(fetchAssociations("SELECT * FROM tblpetbiz WHERE bizid IN (".join(',', array_keys($counts)).")") as $ass) {
	$counts[$ass['bizid']]['bizname'] = $ass['bizname'];
	$counts[$ass['bizid']]['db'] = $ass['db'];
}

$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);

$clients = fetchKeyValuePairs(
	"SELECT garagegatecode, fname
		FROM tblclient
		LEFT JOIN tblclientpref ON clientptr = clientid AND property LIKE 'flag_%' AND value LIKE '2|%'
		WHERE garagegatecode IS NOT NULL AND active = 1 AND value IS NOT NULL
		AND garagegatecode IN (".join(',', array_keys($counts)).") ORDER BY fname", 1);


function cmpadmins($a, $b) {
	return $a['admins'] < $b['admins'] ? 1 : (
		$a['admins'] > $b['admins'] ? -1 : 0);
}
uasort($counts, 'cmpadmins');

echo "<h2>Gold Star Businesses with Active Admins (owners and dispatchers)</h2>";
foreach($counts as $bizid => $biz)
	echo "{$clients[$bizid]}  ({$biz['db']}) {$biz['admins']}<br>";
	