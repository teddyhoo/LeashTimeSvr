<? // maint-report-mobile-logins.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

locked('z-');
$since = $_GET['since'];
$allRoles = explodePairsLine('o|Manager||d|Dispatcher||p|Sitter||c|Client');
if($roles = $_GET['roles']) $roles = explode(',', $roles);
else $roles = explode(',', 'o,d,p,c');
foreach($roles as $role) $rolesSQL[] = "rights like '$role-%'";
$rolesSQL = "AND (".join(' OR ', $rolesSQL).")";
//	return 'Alcatel,iPhone,iPod,SIE-,BlackBerry,Android,IEMobile,Obigo,Windows CE,LG/,LG-,CLDC,Nokia,SymbianOS,PalmSource'
//						.',Pre/,Palm webOS,SEC-SGH,SAMSUNG-SGH';

$mobileTokenString = getMobileUserAgentTokensString().",iPad";
$mobileTokens = "(browser LIKE '%".join("%' OR browser LIKE '%", explode(',', $mobileTokenString))."%')";

$sql = "SELECT UserUpdatePtr, LastUpdateDate, note, browser, rights
				FROM tbllogin
				LEFT JOIN tbluser ON userid = UserUpdatePtr
				WHERE UserUpdatePtr != 0
					$rolesSQL
					AND $mobileTokens";
if($since) $sql .= "AND LastUpdateDate >= '$since'";
//echo $sql;
	
$result = doQuery($sql);
while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	if(substr($row['LastUpdateDate'], 0, 10) != $date) {
		//if($logins[$date]) $logins[$date] = count($logins[$date]);
		$date = substr($row['LastUpdateDate'], 0, 10);
	}
	$logins['counts'][$date] += 1;
	$logins['uniqueUsers'][$date][$row['UserUpdatePtr']] = 1;
	if($row['note'] == 'mobile') $logins['msa'][$date] += 1;
	$logins['device'][$date][mobileDevice($row['browser'])] += 1;
	$logins['roles'][$date][$row['rights'][0]] += 1;
}

foreach($logins['counts'] as $date => $count) {
	$datum['date'] = $date;
	$datum['logins'] = $count;
	$datum['unique users'] = count($logins['uniqueUsers'][$date]);
	if(strpos($roles,'p') !== FALSE) $datum['MSA logins'] = $logins['msa'][$date];
	$datum['iPhone'] = $logins['device'][$date]['iPhone'];
	$datum['Android'] = $logins['device'][$date]['Android'];
	$datum['iPad'] = $logins['device'][$date]['iPad'];
	$datum['iPod'] = $logins['device'][$date]['iPod'];
	$datum['Windows CE'] = $logins['device'][$date]['Windows CE'];
	foreach($roles as $role)
		$datum[$allRoles[$role]] = $logins['roles'][$date][$role];

	$data[] = $datum;
}

echo "Mobile Device Usage";
quickTable($data, 'border=1', $style=null, $repeatHeaders=20);

function mobileDevice($agent) {
	global $mobileTokenString;
	// See: http://en.wikipedia.org/wiki/List_of_user_agents_for_mobile_phones
	static $tokens;
	if(!$tokens) {
		$tokens =  explode(',',$mobileTokenString); // ini_session.php
		$maybe = 'iPad';
	}
	foreach($tokens as $token) 
		if(strpos($agent, $token) !== FALSE) {
			//echo "$token<br>";
			return $token;
		}
}
