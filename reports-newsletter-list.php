<? // reports-newsletter-list.php 
require_once "common/init_session.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "invoice-fns.php"; // for getAccountBalance
require_once "client-flag-fns.php";

$locked = locked('o-');
if(!$_SESSION['staffuser']) $errors[] = "You must be logged in as Staff to view this report.";
if($_SESSION['db'] != 'leashtimecustomers') $errors[] = "This report is available only in the the context of the LT Customers db.";
extract(extractVars('action,newbizdb,bizdb,bizid,date,enddate,showtest,detail,csv,sort,show,withOutandingBalanceOnly', $_REQUEST));

if($date) $date = date('Y-m-d', strtotime($date));
else if(!$show) $date = date('Y-m-d', strtotime("-3 days"));


$dbs = array_merge(array('All Active Businesses'=>'', 'Gold Stars Only'=>'paying', 'Active Prospects Only'=>'prospects'));

//$bizdb = $newbizdb;
$bizdb = array_key_exists('bizdb', $_REQUEST) ? $bizdb : 'prospects';

$withOutandingBalanceOnly = $bizdb == 'paying' && (!$show || $withOutandingBalanceOnly);
require_once "common/init_db_petbiz.php";
$clientsByPetBizId = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient");
$starred = array();
foreach($clientsByPetBizId as $bizId => $clientid) {
	$flagsByPetBizId[$bizId] = 	clientFlagPanel($clientid, $officeOnly=false, $noEdit=tue, $contentsOnly=false, $onClick=null);
	$flagIds = (array)getClientFlagIDs($clientid);
	// goldstar 2, greystar 21, trial 1
	if(in_array(2, $flagIds) || in_array(21, $flagIds)) $starred[$bizId] = 1;
	if(in_array(2, $flagIds)) $goldstarbizzess[] = $bizId;
	if(in_array(1, $flagIds)) $trialbizzes[$bizId] = fetchRow0Col0("SELECT setupdate FROM tblclient WHERE clientid = $clientid LIMIT 1", 1);
}
asort($trialbizzes);
$trialbizzes = array_reverse($trialbizzes, $keepKeys=1);

$liveBizIds = fetchKeyValuePairs("SELECT garagegatecode 
														FROM tblclient 
														WHERE garagegatecode AND
																	clientid IN (SELECT clientptr 
																								FROM tblclientpref 
																								WHERE property LIKE 'flag_%' AND value like '2|%')");




list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);		

require_once "common/init_db_common.php";
$mgrSQL = "SELECT LOWER(loginid) as loginid, email, lname, fname
			FROM tbluser 
			WHERE bizptr = BIZID AND active = 1 AND ltstaffuserid = 0 AND email IS NOT NULL
				AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
				AND email NOT LIKE 'teddyhoo%' AND email NOT LIKE '%@leashtime%'";
$loginIDSQL = "SELECT LOWER(loginid)
			FROM tbluser 
			WHERE bizptr = BIZID AND active = 1 AND ltstaffuserid = 0 AND email IS NOT NULL
				AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
				AND email NOT LIKE 'teddyhoo%' AND email NOT LIKE '%@leashtime%'";
foreach($goldstarbizzess as $bizid) {
	$sql = str_replace('BIZID', $bizid, $mgrSQL);
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1");
	$mgrs = fetchAssociations($sql, 1);
	foreach($mgrs as $mgr) {
		$loginid = $mgr['loginid'];
		$allLoginIds[] = $loginid;
		//unset($mgr['loginid']);
		$mgr = array_merge($mgr);
		$mgr[] = $biz['bizname'];
		$rows[$loginid] = array_reverse($mgr);
		$rows[$loginid][] = ''; // for setupdate

	}
	//$sql = str_replace('BIZID', $bizid, $loginIDSQL);
	//foreach(fetchCol0($sql, 1) as $loginId)
	//	$allLoginIds[] = $loginId;
}
$rows[] = null;

foreach($trialbizzes as $bizid => $setupdate) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1");
	$sql = str_replace('BIZID', $bizid, $mgrSQL);
	$mgrs = fetchAssociations($sql, 1);
	foreach($mgrs as $mgr) {
		$loginid = $mgr['loginid'];
		$allLoginIds[] = $loginid;
		//unset($mgr['loginid']);
		$mgr = array_merge($mgr);
		$mgr[] = $biz['bizname'];
		$mgr = array_reverse($mgr);
		$mgr['setup'] = $setupdate;
		$rows[$loginid] = $mgr;
	}
	//$sql = str_replace('BIZID', $bizid, $loginIDSQL);
	//foreach(fetchCol0($sql, 1) as $loginId)
	//	$allLoginIds[] = $loginId;
}
//print_r($rows); exit;
//print_r($allLoginIds); exit;
$allLoginIdsLIST = join("','", $allLoginIds);
$threshold = date('Y-m-d H:i:s', strtotime("-30 days"));
$recent = fetchKeyValuePairs(
	"SELECT DISTINCT LOWER(TRIM(loginid)) as loginid, lastupdatedate
		FROM tbllogin 
		WHERE loginid IN ('$allLoginIdsLIST')
			AND lastupdatedate >= '$threshold'
			AND success = 1
			ORDER BY lastupdatedate ASC", 1);
foreach($recent as $loginid => $date)
	$rows[trim($loginid)]['lastlogin'] = "[$date]";
	
$missing = array_diff($allLoginIds, array_keys($recent));

echo "Last Login date shown only if it occurred on or after $threshold<p>";
//print_r($rows); exit;

echo "<table border=1><tr><td colspan=4><h1>Gold Stars</h1></td></tr>";
foreach($rows as $i => $row) {
	if(!$row) echo "<tr><td colspan=4><h1>Trials</h1></td></tr>";
	else echo "<tr><td>".join("</td><td>", $row)."</td></tr>"; //<td>[$i]</td>
}
echo "</table>";
exit;

$allLoginIdsLIST = join("','", $allLoginIds);
$threshold = date('Y-m-d H:i:s', strtotime("-30 days"));
$recent = fetchCol0(
	"SELECT DISTINCT LOWER(loginid)
		FROM tbllogin 
		WHERE loginid IN ('$allLoginIdsLIST')
			AND lastupdatedate >= '$threshold'", 1);
$missing = array_diff($allLoginIds, $recent);
$missingLIST = join("','", $missing);
$rows = fetchCol0("SELECT CONCAT_WS(', ', lname, fname,email, CONCAT('[',loginid,']')) FROM tbluser WHERE loginid IN ('$missingLIST')", 1);

echo "<h2>No logins since $threshold</h2>";
foreach($rows as $row) echo "$row<br>";

/////// **********************************************************

//require_once "frame-end.html";
