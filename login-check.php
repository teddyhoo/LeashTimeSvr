<? // login-check.php // check logins for today's sitters
// data dump for manager standalone app
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "field-utils.php";
require_once "request-fns.php";

$locked = locked('o-');

// find sitters
if($showAllSitters = $_GET['showAllSitters'])
	$provs = fetchAssociationsKeyedBy(
		"SELECT IFNULL(userid, 0-providerid) as userid, providerid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as pname
			FROM tblprovider 
			WHERE providerid != 0 AND active
			ORDER BY pname", 'userid');
else $provs = fetchAssociationsKeyedBy(
	"SELECT IFNULL(userid, 0-providerptr) as userid, providerptr, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as pname
		FROM tblappointment 
		LEFT JOIN tblprovider ON providerid = providerptr
		WHERE providerptr != 0 AND date = '".date('Y-m-d')."' ORDER BY pname", 'userid');
if($provs) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	include "common/init_db_common.php";
	$loginids = fetchKeyValuePairs("SELECT userid, UCASE(loginid) FROM tbluser WHERE userid IN (".join(',', array_keys($provs)).")");
	
	$logins = fetchAssociationsKeyedBy(
		"SELECT TRIM(UCASE(loginid)) as loginid, LastUpdateDate, UNIX_TIMESTAMP(LastUpdateDate)  as utime
			FROM tbllogin 
			WHERE success = 1 AND TRIM(loginid) IN ('".join("','", $loginids)."')
			ORDER BY LastUpdateDate ASC", 'loginid');
			
	$lastAppCallByUserId = fetchKeyValuePairs(
		"SELECT userptr, value
			FROM tbluserpref 
			WHERE userptr IN (".join(',', array_keys($loginids)).") AND property = 'lastAppCall'");
			
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	$firstCallTodayByUserId = fetchKeyValuePairs(
		"SELECT userptr, value
			FROM tbluserpref 
			WHERE userptr IN (".join(',', array_keys($loginids)).") AND property = 'firstCallToday'");
			
	// for each login id, if there is no login today use the firstCallToday for that login ID 
	// if it is later than the last login
	$today = date('Y-m-d');
	foreach($loginids as $userid => $loginid) {
		//if($userid == 118515) "$loginid: ".print_r($lastLogin, 1);
		$lastLogin = $logins[$loginid]['LastUpdateDate'];
		$lastLoginDay = date('Y-m-d', strtotime($lastLogin));
		if($lastLoginDay != $today) {
//if(mattOnlyTEST() && $loginid == 'CHRIS.CALLEJAS') echo "lastLogin: [$lastLogin] lastLoginDay: [$lastLoginDay] today: [$today]<p>";//$print_r($logins);
//if(mattOnlyTEST() && $loginid == 'CHRIS.CALLEJAS') echo print_r($firstCallTodayByUserId);
			if($firstCall = $firstCallTodayByUserId[$userid])
				$logins[$loginid] = array('loginid'=>$loginid, 'LastUpdateDate'=>$firstCall, 'utime'=>strtotime($firstCall));
		}
	}
}
include "frame-bannerless.php";

$includeAllProvidersLink = true;
if($includeAllProvidersLink) {
	require_once "gui-fns.php";
	$includeAllProvidersLink = $showAllSitters ? 
		fauxLink('Show Just Today&apos;s Sitters', 'document.location.href="login-check.php"', 1)
		: fauxLink('Show All Sitters', 'document.location.href="login-check.php?showAllSitters=1"', 1);
}
if(!$provs) {
	echo "<h1>No Sitters have visits today</h1>$includeAllProvidersLink";
	exit;
}
$todayOnly = $showAllSitters ? '' : ' with Visits Today';
echo "<h1>Last Logins for Sitters$todayOnly</h1>";
if($includeAllProvidersLink) echo $includeAllProvidersLink;
echo "<style>.problem {color:red;}</style>";
echo "<table>";
foreach($provs as $prov) {
	$lastLog = $logins[$loginids[$prov['userid']]]['LastUpdateDate'];
	$logtime = $logins[$loginids[$prov['userid']]]['utime'];
	$problem = !$lastLog || date('Y-m-d', $logtime) != date('Y-m-d', time()) 
						? "class='problem'"
						: "";
	if(!$problem) $time = date('g:i a', $logtime);
	else if(date('Y-m-d', $logtime) == date('Y-m-d', strtotime('yesterday')))
		$time = 'yesterday '.date('g:i a', $logtime);
	else if(!$lastLog) $time = 'No login info available.';
	else $time = shortDate($logtime).' '.date('g:i a', $logtime);
	echo "<tr><td>{$prov['pname']}</td><td $problem>$time</td></tr>";
}
echo "</table>";
	


