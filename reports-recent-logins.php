<? // reports-recent-logins.php 
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


if($show) {
	if($action == 'detail') {
		require_once "common/init_db_petbiz.php";
		$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE garagegatecode = $bizid");
		require_once "common/init_db_common.php";
		$mgrs = fetchAssociations(
			"SELECT * 
			FROM tbluser 
			WHERE bizptr = $bizid 
				AND active = 1 
				AND ltstaffuserid = 0
				AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
			ORDER BY loginid");
		echo "<h2>{$client['fname']} {$client['lname']} (bizid: $bizid)</h2>Email: {$client['email']}<p><u>Managers:</u><p>";
		foreach($mgrs as $mgr) {
			$style = $mgr['isowner'] ? "style='font-weight:bold'" : '';
			$role = $mgr['rights'][0] == 'o' ? '' : '[D] ';
			$proprietor = $mgr['isowner'] ? ' (proprietor)' : '';
			echo "<span $style$>$role({$mgr['loginid']}) {$mgr['fname']} {$mgr['lname']} $proprietor</span> {$mgr['email']}<br>";
		}
		exit;	
	}


	//$bizdb = $newbizdb;
	$bizdb = array_key_exists('bizdb', $_REQUEST) ? $bizdb : 'prospects';

	$withOutandingBalanceOnly = $bizdb == 'paying' && (!$show || $withOutandingBalanceOnly);
	require_once "common/init_db_petbiz.php";
	$clientsByPetBizId = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient");
	$starred = array();
	foreach($clientsByPetBizId as $bizId => $clientid) {
		$flagsByPetBizId[$bizId] = 	clientFlagPanel($clientid, $officeOnly=false, $noEdit=tue, $contentsOnly=false, $onClick=null);
		$flagIds = (array)getClientFlagIDs($clientid);
		// goldstar 2, greystar 21
		if(in_array(2, $flagIds) || in_array(21, $flagIds)) $starred[$bizId] = 1;
	}

	$liveBizIds = fetchCol0("SELECT garagegatecode 
															FROM tblclient 
															WHERE garagegatecode AND
																		clientid IN (SELECT clientptr 
																									FROM tblclientpref 
																									WHERE property LIKE 'flag_%' AND value like '2|%')");

	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);		

	require_once "common/init_db_common.php";
	$allDBs = fetchCol0("SHOW DATABASES");
	$bizzes =  fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz", 'bizid');
	$lastMgrLogins = array();
	$loginCounts = array();
	if($date) $filter[] = "LastUpdateDate >= '$date'";

	if($bizdb == 'prospects') $filter[] = "bizptr NOT IN (".join(',', $liveBizIds).")";
	else if($bizdb == 'paying') $filter[] = "bizptr IN (".join(',', $liveBizIds).")";
	if($filter) $filter = 'AND '.join(' AND ', $filter);

	function hottestComp($a, $b) { return $a > $b ? -1 : ($a < $b ? 1 : 0); }
	function bizComp($a, $b) { return strcmp($a['biz'], $b['biz']); }
	function lastloginComp($a, $b) { return $a['lastlogintime'] > $b['lastlogintime'] ? -1 : ($a['lastlogintime'] < $b['lastlogintime'] ? 1 : 0); }
	function dumpCSVRow($row, $cols=null) {
		if(!$row) echo "\n";
		if(is_array($row)) {
			if($cols) {
				$nrow = array();
				if(is_string($cols)) $cols = explode(',', $cols);
				foreach($cols as $k) $nrow[] = strip_tags($row[$k]);
				$row = $nrow;
			}
			echo join(',', array_map('csv',$row))."\n";
		}
		else echo csv($row)."\n";
	}


	function csv($val) {
		$val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
		$val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
		$val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
		return "\"$val\"";
	}

	function countSitters($biz, $starred=false) {
		global $dbhost, $db, $dbuser, $dbpass, $date;
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if(!$starred) $sitters = fetchRow0Col0("SELECT count(*) FROM tblprovider WHERE active");
		else {
			$start = date('Y-m-01', strtotime($date));
			$end = date('Y-m-t', strtotime($date));
			$sitters = fetchKeyValuePairs(
				"SELECT providerptr, 1 
					FROM tblappointment 
					WHERE date >= '$start' AND date <= '$end' AND providerptr > 0 AND canceled IS NULL");
			$sitters = count($sitters);
		}
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		return $sitters;
	}

	function figureRate($biz, $numsitters) {
		if(!$numsitters) return 0;
		if(!$biz['rates']) return '<span title="No rates set.">??</span>';
		$rates = explode(',',$biz['rates']);
		foreach($rates as $rate) {
			$rate = array_map('trim',explode('=',$rate));
			if($numsitters <= $rate[0]) return $rate[1];
		}
		return $rate[1];
	}


	$firstLogins = array();
	if(isset($bizdb)) {
		$result = doQuery("SELECT bizptr, LastUpdateDate, FailureCause
												FROM tbllogin l
												LEFT JOIN tbluser u ON l.loginid = u.loginid
												WHERE rights LIKE 'o-%' AND ltstaffuserid = 0 $filter
												ORDER BY LastUpdateDate");
		while($row = mysql_fetch_assoc($result)) {
			if($row['FailureCause']) $failureCounts[$row['bizptr']] += 1;
			else $lastMgrLogins[$row['bizptr']] = $row['LastUpdateDate'];
			$loginCounts[$row['bizptr']] += 1;
			if(!$date && !$firstDate[$row['bizptr']]) $firstDate[$row['bizptr']] = $row['LastUpdateDate'];
			else $firstDate[$row['bizptr']] = $date;
		}

		$result = doQuery("SELECT bizptr, LastUpdateDate
												FROM tbllogin l
												LEFT JOIN tbluser u ON l.loginid = u.loginid
												WHERE rights LIKE 'o-%' AND ltstaffuserid = 0
												ORDER BY LastUpdateDate");
		while($row = mysql_fetch_assoc($result)) {
			if(!isset($firstLogins[$row['bizptr']])) $firstLogins[$row['bizptr']] = $row['LastUpdateDate'];
		}

	//foreach($lastMgrLogins as $d)echo "$d<br>";
		uasort($loginCounts, 'hottestComp');
	}



	//foreach(array_keys($loginCounts) as $i) screenLog("$i");	
	$total = '0';
	$rows = array();
	$bizTotals = array();
	$rowClasses[] = null;
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	$activeSittersRatherThanActual = "<span title='This is the total number of active sitters, not the number of sitters with visits'> #</span>";
	foreach($loginCounts as $bizid => $count) {
		//if(!isset($bizzes[$bizid]))  continue;
		$biz = $bizzes[$bizid];

		if(!in_array($biz['db'], $allDBs)) continue;
		if($biz['test']) continue;
		/*if(in_array($bizid, $liveBizIds)) {
			continue; // to exclude gold stars
		}*/
		if($failureCounts[$bizid])
			$count = "<span title='includes {$failureCounts[$bizid]} failed login attempt(s).'>*$count</span>";

		if($withOutandingBalanceOnly) {
			$balance = getAccountBalance($clientsByPetBizId[$bizid], true);
	//echo "{$bizzes[$bizid]['bizname']} ($bizid): $balance<br>";		
			if($balance < .01)	continue;
		}

		$bizLabel = "{$bizzes[$bizid]['bizname']} ($bizid)";
		if(!$csv) $bizLabel = "<span style='cursor:pointer;color:darkgreen;' onclick='showDetails($bizid)'>$bizLabel</span>";
		$rows[] = array(
			'bizid'=>$bizid, 
			'biz'=>$bizLabel.(!$csv ? ' '.$flagsByPetBizId[$bizid] : ''), 
			'logins'=>$count, 
			'lastlogintime'=>strtotime($lastMgrLogins[$bizid]),
			'lastlogin'=>date('m/d/Y', strtotime($lastMgrLogins[$bizid])),
			'firstlogin'=>($firstlogin = date('m/d/Y', strtotime($firstLogins[$bizid]))),
			'activesitters'=>($numsitters = countSitters($bizzes[$bizid], $starred[$bizid])).($starred[$bizid] ? '' : $activeSittersRatherThanActual),
			'charge'=>figureRate($bizzes[$bizid], $numsitters),
			'golive'=>(!$firstlogin ? '' : date('m/d/Y', strtotime('+30 days', strtotime($firstLogins[$bizid])))),
			'balance'=>$balance
			// TBD check for GO LIVE date in CAUTION client tag and replace the default

			);
		//$rowClasses[] = ($bizzes[$bizid]['freeuntil'] == '1970-01-01') ? 'freeclass' : null;

	}
	if($sort) {
		$sortParts = explode('_', $sort);
		if($sortParts[0] == 'biz') usort($rows, 'bizComp');
		else if($sortParts[0] == 'lastlogin') usort($rows, 'lastloginComp');
		else ; // defaults to # manager logins
		if($sortParts[1] == 'desc') $rows = array_reverse($rows);
	}
	//screenLog(join(',', $liveBizIds));
	//screenLog(join(',', $bizzes[3]));
	//echo "<hr>$db $date - $enddate<p>";print_r($rows);echo "<hr>";
	$rows = array_merge(array(array('biz'=>'<b>Total ('.count($rows).')</b>', 'logins'=>array_sum($loginCounts))), $rows);
	$columns = explodePairsLine('biz|Business||logins|Mgr logins||lastlogin|Last Login||firstlogin|First Login||golive|Est Go Live||activesitters|Sitters||charge|Charge');
	if($withOutandingBalanceOnly) $columns['balance'] = 'Balance';


	if($csv) {
		$dbn = !$bizdb ? 'All' : $bizdb;
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=RecentLogins-$dbn.csv");
		dumpCSVRow("Report generated: ".date('m/d/Y H:i'));
		dumpCSVRow($columns);
		foreach($rows as $row) {
			dumpCSVRow($row, array_keys($columns));
		}
		exit;
	}
}  // end $_POST

$pageTitle = 'Manager Login Activity';
$breadcrumbs = "<a href='reports.php'>Reports</a>";	
if($date) $pageTitle =  "<h2>Manager Login Activity since ".date('m/d/Y', strtotime($date))."</h2>";
// ******************************************************
include 'frame.html';
?>
<?
if($errors) {
	echo "<ul><li>".join('<li>', $errors)."</ul>";
	require_once "frame-end.html";
	exit;
}
if($bizName) echo "<h2>$bizName</h2>";

?>
<style>
.biztable td {padding-left:10px;}
.freeclass {color: gray; }
.overdue {color: red; }
</style>

<?
$mdyDate = date('m/d/Y', strtotime($date));
	//isset($bizdb) ? ($date ? date('m/d/Y', strtotime($date)) : '') 
	//: date('m/d/Y', strtotime($date));
if($msg) echo "<p style='color:darkgreen'>$msg</p>";
if($error) echo "<p style='color:red'>$error</p>";
selectElement('Business:', 'bizdb', $bizdb, $dbs);
hiddenElement('bizdb', $bizdb);
//echo " Date: <input id='date' name='date' value='$mdyDate'>";
calendarSet('Starting', 'date', $mdyDate);

echo " ";
labeledCheckbox('with outstanding balances', 'withOutandingBalanceOnly', $withOutandingBalanceOnly);
echoButton('', 'Show', 'show(0)');
echo " ";
echoButton('', 'CSV', 'show(1)');

if($rows) {
	$finalRows = array();  // to exclude gold stars
	echo "<p style='font-size:1.2em;padding-left:5px;'>Manager logins (excluding LT Staff, Test databases).  SItter count reflects sitters with visits the month shown above.
				</p>";
	//$x = array('goldstar'=>' ');
	$x = array('notinltcustomers'=>'');
	foreach($columns as $k=>$v) $x[$k] = $v;
	$columns = $x;
	foreach($rows as $i => $row) {
		if(!$clientsByPetBizId[$row['bizid']]) $rows[$i]['notinltcustomers'] = "<span title='Not in LT Customers'><b>?</b></span>";
		if(in_array($row['bizid'], $liveBizIds)) {
			$rows[$i]['goldstar'] = "<img src='art/flag-yellow-star.jpg'>"; 
			//continue; // to exclude gold stars
		}
		if(!$csv) $rows[$i]['firstlogin'] = 
			"<span title='".date('m/d/Y H:i', strtotime($firstLogins[$row['bizid']]))."'>{$row['firstlogin']}</span>";
		if($row['golive']) {
			if(!$csv && strtotime($row['golive']) < strtotime(date('m/d/Y')))
				$rows[$i]['golive'] = "<span class='overdue'>{$rows[$i]['golive']}</span>";
		}
		if($row['bizid']) 
			$rows[$i]['logins'] = "<a href='javascript:chart({$row['bizid']}, \"{$firstDate[$row['bizid']]}\")'>{$row['logins']}</a>";
		$finalRows[] = $rows[$i];
	}
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
	$columnSorts = array('biz'=>'1','logins'=>'1','lastlogin'=>'1');
	tableFrom($columns, $finalRows, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, $columnSorts, $rowClasses);
}
?>
<p><img src='art/spacer.gif' height=250 width=1>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

function chart(bizid, start) {
	var url= "reports-manager-logins.php?end=<?= date('Y-m-d') ?>&bizid="+bizid+"&start="+start;
	$.fn.colorbox({href:url, width:"750", height:"600", scrolling: true, iframe: true, opacity: "0.3"});
}

function show(csv) {
	if(!MM_validateForm('date', '', 'R', 'date', '', 'isDate')) return;
	var bizdb = document.getElementById("bizdb").value;
	var date = escape(date = document.getElementById("date").value);
	var withOutandingBalanceOnly = document.getElementById("withOutandingBalanceOnly").checked ? 1 : 0;
	document.location.href="reports-recent-logins.php?date="+date+"&bizdb="+bizdb+"&csv="+csv+'&show=1'+"&withOutandingBalanceOnly="+withOutandingBalanceOnly;
}

function showDetails(bizid) {
	$.fn.colorbox({href:"reports-recent-logins.php?action=detail&bizid="+bizid, width:"750", height:"470", scrolling: true, opacity: "0.3"});
}

<? dumpPopCalendarJS(); ?>
</script>

<?
require_once "frame-end.html";
