<? // reports-native-sitter-logins.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');

if($loginid = $_GET['loginid']) { // dump what we know about this loginid
	$user = fetchFirstAssoc("SELECT tbluser.*, bizname, bizid, db, dbhost, dbuser, dbpass
														FROM tbluser LEFT JOIN tblpetbiz ON bizid = bizptr WHERE loginid = '$loginid' LIMIT 1");
	if(!$user) {echo "[$loginid] is an unknown login id"; exit;}
	//function labelRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $rawValue=false)
	echo "<style>.biz {font: bold;font-size:1.3em;}
		.name {font: bold;font-size:1.3em;}</style>";

	echo "<table>";
	labelRow('Login ID:', $name, "$loginid [{$user['rights']}]");
	labelRow('User ID:', $name, $user['userid']);
	labelRow('Name:', $name, "{$user['fname']} {$user['lname']}", $labelClass=null, $inputClass='name');
	labelRow('Business:', $name, "{$user['bizname']} ({$user['bizid']})", $labelClass=null, $inputClass='biz');
	labelRow('Password:', $name, ($user['password'] ? 'set' : 'not set'));
	labelRow('Temp Password:', $name, $user['temppassword']);
	labelRow('Email:', $name, $user['email']);
	labelRow('Agreement:', $name, ($user['agreementptr'] ? $user['agreementdate'] : 'no'));
	if(!$user['rights']) labelRow('Error:', $name, 'No rights found', null, 'warning');
	echo "<tr><td colspan=2><hr></td></tr>";
	if($user['rights']) {
		$role = $user['rights'][0];
		reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass'], 1);
		$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");
		$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE userid = {$user['userid']} LIMIT 1");
		if($role == 'p') {
			$person = $provider;
			if(!$person) {
				$warning = 'No sitter with this userid was found';
				if($client) $warning .= "<br>However, the client {$client['fname']} {$client['lname']} DOES have this login id";
				labelRow('Error:', $name, $warning, null, 'warning', null, null, 1);
			}
			else {
				labelRow('Sitter:', $name, "{$person['fname']} {$person['lname']} ({$person['nickname']})", $labelClass=null, $inputClass='name');
				labelRow('Sitter ID:', $name, $person['providerid']);
			}
		}
		if($role == 'c') {
			$person = $client;
			if(!$person) {
				$warning = 'No client with this userid was found';
				if($provider) $warning .= "<br>However, the sitter {$provider['fname']} {$provider['lname']} DOES have this login id";
				labelRow('Error:', $name, $warning, null, 'warning', null, null, 1);
			}
			else {
				labelRow('Client:', $name, "{$person['fname']} {$person['lname']} ({$person['nickname']})", $inputClass='name');
				labelRow('Client ID:', $name, $person['clientid']);
			}
		}
	}
	echo "</table>";
	exit;
}

$sql = "SELECT * FROM tbllogin WHERE Success = 1 AND browser LIKE 'LeashTime%Sitter%' ORDER BY LastUpdateDate";
$result = doQuery($sql);
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$days[substr($row['LastUpdateDate'], 0, 10)][$row['LoginID']] += 1;
	$userTotals[$row['LoginID']] += 1;
}


$uniqueUsers = fetchAssociations("SELECT * FROM tbluser WHERE loginid IN ('"
	.join("','", array_map('mysql_real_escape_string', array_keys($userTotals)))."')");
	
$usersByBusiness = array();
foreach($uniqueUsers as $user) {
	$userBiz[strtoupper($user['loginid'])] = $user['bizptr'];
	$useridsByLoginid[strtoupper($user['loginid'])] = $user['userid'];
}
$bizzes = fetchAssociationsKeyedBy(
	"SELECT * FROM tblpetbiz WHERE bizid IN (".join(',', array_unique($userBiz))	.")", 'bizid');

require_once "native-sitter-api.php";
echo "<h2>Successful Native Sitter Logins (= page accesses)</h2><a href=#TOTALS>Jump to Totals</a> - <a href=#BIZZES>Jump to Bizzes</a> - <a href=reports-native-sitter-logins.php>Refresh</a><p>";
echo "<style>.link {font: bold;color:blue;text-decoration:underline}</style>";
foreach($days as $day=>$users) {
	$total = array_sum($users);
	$numusers = count($users);
	echo "<p><u>$day ($total logins by $numusers loginids)</u><br>";
	foreach($users as $loginid=>$count) {
//echo "[$loginid] ";		
		$upperLoginID = strtoupper($loginid);
		if(!$loginid) $numVisits = '?';
		else if(!$useridsByLoginid[$upperLoginID]) $numVisits = 'loginid?';
		else if(!($biz = $bizzes[$userBiz[$upperLoginID]])) $numVisits = 'bizid?';
		else {
			reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass'], 1);
			$numVisits = count(getNativeVisitsForUserOnDay($useridsByLoginid[$upperLoginID], $day));
			require "common/init_db_common.php";
		}
		echo "[{$biz['db']}] <a class='link' onclick='showUser(\"".urlencode($loginid)."\")'>$loginid</a>: $count Visits: $numVisits";
		echo "<br>";
	}
}
$userCount = count($userTotals);
$accessCount = array_sum($userTotals);
$userbizzes = fetchKeyValuePairs(
	"SELECT loginid, bizptr
		FROM tbluser
		WHERE loginid IN ('".join("','", array_map('mysql_real_escape_string', array_keys($userTotals)))."')
		ORDER BY loginid");
foreach($userbizzes as $loginid=>$bizptr)
	$bizusers[$bizptr][] = $loginid;
$bizzes = fetchKeyValuePairs(
							"SELECT bizid, CONCAT(bizname, ' (',db,') bizid:', bizid) FROM tblpetbiz WHERE bizid IN ("
								.join(',', array_unique($userbizzes)).")");
asort($bizzes);
//print_r($bizusers);
echo "<hr><a name=BIZZES></a>USERS BY BUSINESS";
foreach($bizzes as $bizptr=>$bizname)
	echo "<p><u><b>$bizname</b> (".count($bizusers[$bizptr])." user".(count($bizusers[$bizptr]) == 1 ? '' : 's').")</u>"
				."<br>".join(', ', $bizusers[$bizptr]);


echo "<hr><a name=TOTALS></a>TOTALS BY USER ($userCount users, $accessCount accesses)<p>";
asort($userTotals);
$userTotals = array_reverse($userTotals);
foreach($userTotals as $user=>$count) {
	echo "<a class='link' onclick='showUser(\"".urlencode($user)."\")'>$user</a>: ".number_format($count,0)."<br>";
}
?>
<script language='javascript' src="common.js"></script>
<script language='javascript'>
function showUser(user) {
	openConsoleWindow('userdetail', 'reports-native-sitter-logins.php?loginid='+user,400,400);
}
</script>
