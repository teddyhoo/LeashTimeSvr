<? // maint-silent-businesses.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

//$ltstaff = fetchCol0("SELECT userid FROM tbluser WHERE ltstaffuserid > 0");

$limit = date('Y-m-d 00:00:00', strtotime("-21 days"));

$recentLogins = fetchKeyValuePairs($sql = 
	"SELECT bizptr, LastUpdateDate
		FROM tbllogin log
		LEFT JOIN tbluser u ON log.loginid = u.loginid
		WHERE u.ltstaffuserid = 0
			AND LastUpdateDate > '$limit'
			AND rights like 'o-%'");
			//AND userid NOT IN (".join(',', $ltstaff).")");
			
$bizzes = fetchAssociations("SELECT * FROM tblpetbiz WHERE test = 0 AND activebiz = 1 ORDER BY bizname");

foreach($bizzes as $biz) if(!$recentLogins[$biz['bizid']]) $mias[$biz['bizid']] = $biz;


$lastLogins = fetchKeyValuePairs(
	"SELECT bizptr, LastUpdateDate
		FROM tbllogin log
		LEFT JOIN tbluser u ON log.loginid = u.loginid
		WHERE 
			u.ltstaffuserid = 0
			AND rights like 'o-%'
			AND u.bizptr IN (".join(',', array_keys($mias)).")");
			
foreach($mias as $bizptr => $biz) {
	$rows[] = array('biz'=>$biz['bizname'], 'lastlogin'=>($lastLogins[$bizptr] ? $lastLogins[$bizptr] : '<font color=red>NEVER</font>'));
	$sortable[] = array('biz'=>$biz['bizname'], 'lastlogin'=>$lastLogins[$bizptr]);
}
	
echo "There are ".count($rows)." active businesses that have not logged in since ".substr($limit, 0, 10)."<p>"	;
	
echo "<table><tr><td style='border:solid black 1px;'><b>By Name<p>";
quickTable($rows);	
echo "<td style='padding-left:10px;border:solid black 1px;'><b>By Most recent Login<p>";
function bydate($a, $b) {return strcmp($b['lastlogin'], $a['lastlogin']); }
usort($sortable, 'bydate');
foreach($sortable as $i=>$v) {
	if(!$v['lastlogin']) {
		$v['lastlogin'] = '<font color=red>NEVER</font>';
		$undated[] = $v;
		unset($sortable[$i]);
	}
}
$sortable = array_merge($undated, $sortable);
quickTable($sortable);	

