<?  // reports-deadwood.php
// analyze storage consumption of inactive clients

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "preference-fns.php";
require_once "gui-fns.php";

locked('z-'); 

// GOLD STARS
$leashtime = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
reconnectPetBizDB($leashtime['db'], $leashtime['dbhost'], $leashtime['dbuser'], $leashtime['dbpass']);
$clients = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient WHERE garagegatecode > 0");
foreach($clients as $ltclientid => $garagegatecode) {
	$goldstar = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' AND value like '2|%'");
	if(!$goldstar) unset($clients[$ltclientid]);
}
$goldstars = $clients; // ltclientid => bizid

// FORMER CLIENTS greystar(21), deadlead(8)
$clients = fetchKeyValuePairs("SELECT clientid, garagegatecode FROM tblclient WHERE garagegatecode > 0");
foreach($clients as $ltclientid => $garagegatecode) {
	$former = fetchRow0Col0("SELECT clientptr 
												FROM tblclientpref 
												WHERE clientptr = $ltclientid AND property LIKE 'flag_%' 
													AND (value like '8|%' OR value like '21|%')");
	if(!$former) unset($clients[$ltclientid]);
}
$formerclients = $clients; // ltclientid => bizid
require "common/init_db_common.php";

foreach($formerclients as  $ltclientid => $bizid) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '$bizid' LIMIT 1");
	$thisdeadwood = array('bizid'=>$bizid.($biz['activebiz'] ? '*' : ''));
	//reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$thisdeadwood['db'] = $biz['db'];
	$thisdeadwood['bizname'] = $biz['bizname'];
	$sql = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS megabytes 
					FROM information_schema.TABLES WHERE table_schema = '{$biz['db']}'";
	$dbstorage = fetchRow0Col0($sql);
	$thisdeadwood['dbstorage'] = number_format($dbstorage);
	$bizfilebytes = explode("\t", shell_exec("du -b -s bizfiles/biz_$bizid"));
	$bizfilemegabytes = $bizfilebytes[0] / 1024 / 1024;
	$thisdeadwood['bizfilestorage'] = number_format($bizfilemegabytes);
	$thisdeadwood['total'] = number_format($bizfilemegabytes+$dbstorage);
	$deadwood[] = $thisdeadwood;
}
function cmptotal($a, $b) {$a = $a['total'];$b = $b['total'];return $a > $b ? -1 : ($a < $b ? 1 : 0); }
usort($deadwood, 'cmptotal');
echo "<h2>Former clients</h2>See: https://leashtime.com/reports-deadwood.php<p>File sizes in MB. * = active biz<p>";
quickTable($deadwood, $extra=null, $style=null, $repeatHeaders=0);