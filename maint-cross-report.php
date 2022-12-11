<? // maint-cross-report.php
// use this script by hand to modify all LT biz databases
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";

set_time_limit(1 * 60);
/*
SELECT * FROM `tbluser` WHERE 
rights NOT like 'c-%' AND
rights NOT like 'p-%' AND
rights NOT like 'd-%' AND
rights NOT like 'o-%'AND
rights NOT like 'z-%'
*/


$locked = locked('z-');

extract($_REQUEST);
//print_r($_REQUEST);exit;
$databases = fetchCol0("SHOW DATABASES");
$windowTitle = $action == 'activity' ? 'Activity Report' : 'Reports';
if(!$csv) {
	include 'frame-maintenance.php';
?>
<h2>Reports</h2>
<form name=searchform method=post>
<span style='font-size:1.5em;font-weight:bold'>Activity from</span>
<? 
//labeledInput('Date:', 'activitydate', $activitydate);
calendarSet('Date:', 'activitydate', $activitydate);
echo ' ';
calendarSet('until:', 'activityend', $activityend);
$months = array('--'=>0, date('F')=>($lastMonth = date('m/01/Y')).'-'.date('m/t/Y'));
for($i=0;$i<4;$i++) {
	$d = strtotime("-1 month", strtotime($lastMonth));
	$months[date('F', $d)] =  ($lastMonth = date('m/01/Y', $d)).'-'.date('m/t/Y', $d);
}

selectElement('', '', '--', $months, 'setDateRange(this)', $labelClass=null, $inputClass=null, $noEcho=false);
echo ' (leave blank for today) ';
echoButton('', 'Find', 'report("activity")'); 
echo ' ';
labeledCheckBox('include inactive businesses', 'includeInactive', $includeInactive);
labeledCheckBox('include test businesses', 'includeTest', $includeTest);
labeledCheckBox('spreadsheet', 'csv', $csv);
hiddenElement('action', '');
hiddenElement('days', '');
//print_r($activitydbs);
hiddenElement('activitydbs', $activitydbs);
?> 
<hr>
<span style='font-size:1.5em;font-weight:bold'>User Agent</span>
<?  
labeledInput('pattern:', 'useragent', $useragent); 
echoButton('', 'Search', 'report("useragent")');
echo ' '.join(', ', array(uAP('ipod'), uAP('iphone'), uAP('droid'),  uAP('Windows Phone')));
?>  -- (reference: <a href=http://www.useragentstring.com/pages/useragentstring.php>User Agents strings</a>)

<hr>
<? echoButton('', 'Find Premature Monthlies', 'report("findpremies")'); ?>
<hr>
<? echoButton('', 'Find Duplicate Monthlies', 'report("finddoublemonthlies")'); ?>
<hr>
<? echoButton('', 'Find Wrong-looking users', 'report("findWrongUsers")'); ?>
<hr>
<? echoButton('', 'Find Bad Email Addresses', 'report("findBadEmailAddresses")'); ?>
<hr>
<? echoButton('', 'Find Dup Payables', 'report("findDupPayables")'); ?>
<hr>
<? echoButton('', 'Find Dup Billabes', 'report("findDuplicateBillabes")'); ?>
<hr>
<? echoButton('', 'Show Latest Rollover', 'report("showRollover")'); ?>
<hr>
<? echoButton('', 'Find Conflicting Recurring Schedules', 'report("showConflictingRecurring")'); ?>
<hr>
<? echoButton('', 'Login Stats by Role', 'report("showStats")'); 
   echo "  ";
   echoButton('', 'Visit Stats', 'report("showVisitStats")'); 
 ?>

<hr>
</form>

<?
} // if !$csv
if($error) {
	echo "<p>$error";
	exit;
}

function compareBizname($a, $b) {return strcmp($a['bizname'], $b['bizname']);}
function compareRole($a, $b) {return strcmp($a['role'], $b['role']);}
function compareLogins($a, $b) {return $a['logins'] < $b['logins'] ? -1 : ($a['logins'] > $b['logins'] ? 1 : 0);}

function uAP($pat) {
	return fauxLink($pat, "document.getElementById(\"useragent\").value=\"$pat\"", 1);
}


$dbs = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz", 'db');
uksort($dbs, 'cistrcmp');
if($action) {	
	if($csv) {
		header("Content-Type: text/csv");
		header("Content-Disposition: inline; filename=Client-Account-Report-$client.csv ");
	}
	if($action == 'showStats') {
		if(!$globalStatsRun) reportGlobalStats();
		$globalStatsRun = 1;
	}
	
	else if($action == 'showVisitStats') {
		if(!$globalStatsRun) reportVisitStats();
		$globalStatsRun = 1;
	}
	
	else if($action == 'useragent' && $useragent) {
		$pattern = isset($useragent) ? $useragent : 'iphone';
		$loginids = fetchKeyValuePairs("
		SELECT UPPER(TRIM(loginid)), count(*)  
		FROM `tbllogin` 
		WHERE `browser` LIKE '%$pattern%'
		GROUP BY loginid
		ORDER BY loginid");
//foreach($loginids as $id=>$cnt) echo "[$id]: $cnt<br>"; 
		$users = fetchAssociations("
		SELECT trim(loginid) as loginid, bizptr, bizname, rights 
		FROM tbluser
		LEFT JOIN tblpetbiz ON bizid = bizptr
		WHERE loginid IN ('".join("','", array_map('mysqli_real_escape_string', array_keys($loginids)))."')
		ORDER BY bizname");
//foreach($users as $user) echo "[{$user['loginid']}]<br>"; 
		if(!$csv) {
			echo "<h2>User agent logins for $useragent</h2>";
			echo "<b>$pattern users: (".count($users).")</b> ";
			labeledCheckBox('Hide Sitters', 'a', '', '', '', 'toggleRole("Sitter")', 1);
			echo "  ";
			labeledCheckBox('Hide Client', 'b', '', '', '', 'toggleRole("Client")', 1);
			echo "  ";
			labeledCheckBox('Hide Manager', 'c', '', '', '', 'toggleRole("Manager")', 1);
			echo "  ";
			labeledCheckBox('Hide Dispatcher', 'd', '', '', '', 'toggleRole("Dispatcher")', 1);
		}
		//action=useragent&useragent=ipad&sort=logins_desc
		$href = "maint-cross-report.php?action=useragent&useragent=$useragent&sort=$sort&csv=1";
		if(!$csv) fauxLink('Spreadsheet', "document.location.href=\"$href\"");
//function labeledCheckbox($label, $name, $value=null, $labelClass=null, $inputClass=null, $onClick=null, $boxFirst
		$roles = explodePairsLine('p|Sitter||o|Manager||d|Dispatcher||c|Client||z|LT Staff');
		foreach($users as $i=>$u) {
			$users[$i]['role'] = $roles[$u['rights'][0]];
			$users[$i]['logins'] = $loginids[strtoupper($u['loginid'])];
		}
		/*tableFrom(
			$columns, 
			$data=null, 
			$attributes=null, 
			$class=null, 
			$headerClass='sortableListHeader', 
			$headerRowClass=null, 
			$dataCellClass='sortableListCell', 
			$columnSorts=null, 
			$rowClasses=null, 
			$colClasses=null, 
			$sortClickAction=null)
		*/
		$sortParts = $sort ? explode('_', $sort) : array('bizname', 'ASC');
		usort($users, $sortParts[0] == 'role' ? 'compareRole' : ($sortParts[0] == 'logins' ? 'compareLogins' : 'compareBizname'));
		if($sortParts[1] == 'desc') $users =  array_reverse($users);
		$totalLogins = array_sum($loginids);
		if(!$csv) tableFrom(array('loginid'=>'ID', 'bizname'=>'Biz', 'role'=>'Role', 'logins'=>"$pattern<br>Logins ($totalLogins)"), 
							$users,
							'border=1 bgcolor=white',
							'','','','',
							explodePairsLine('bizname|1||role|1||logins|1'),
							'',
							'',
							$sortClickAction='sortUserAgent');
		// $sortClickAction - JS function name which takes 'sortKey' and 'direction' (asc/desc) as args
		else {
			echo "User agent logins for $useragent\n\n$pattern users: (".count($users).")\n\n";
			echo "ID,Business,Role,$useragent logins ($totalLogins)\n";
			foreach($users as $user) dumpCSVRow($user, explode(',', "loginid,bizname,role,logins"));
		}


	}
	else if($action == 'activity') {
		$since = date('Y-m-d', strtotime($activitydate ? $activitydate : '1/1/2005'));
		$sinceTime = "$since 00:00:00";
		$end = date('Y-m-d', strtotime($activityend ? $activityend : date('Y-m-d')));
		$endTime = "$end 11:59:59";
		echo "<h2>Activity from ".date('m/d/Y', strtotime($since))." to ".date('m/d/Y', strtotime($end))."</h2>";
		$selectAllBox = "<input id='selectall' type='checkbox' onclick='selectAll(this)' CHECKED>";
		echo "<table border=1 bgcolor=white bordercolor=black>
		<tr><th>$selectAllBox<th>Company<th>Total<br>Visits<th>Completed<br>Visits<th>Canceled<br>Visits<th>Active<br>Sitters<th>Invoices<th>Payments<th>All<br>Messages<th>Outbound<br>Messages<th colspan=2>Logged Messages<th colspan=5>Logins</tr>
		<tr><th colspan=10>&nbsp;<th>Phone<th>Email<th>Man<th>Disp<th>Sit<th>Client<th>All</tr>";
		$logins = fetchAssociations("SELECT userid, log.loginid, bizptr, rights
												FROM tbllogin log 
												LEFT JOIN tbluser u ON u.loginid = log.loginid
												WHERE success = 1 
															AND ltstaffuserid = 0
															AND LastUpdateDate >= '$since' AND 	LastUpdateDate <= '$end'");
	}
	else if(in_array($action, array('showRollover', 'findpremies', 'finddoublemonthlies', 'findWrongUsers', 
												'findBadEmailAddresses', 'findDupPayables', 'findDuplicateBillabes',
												'showConflictingRecurring'))) {
		echo "<table border=1 bgcolor=white bordercolor=black>";
	}
	
	if($action != 'useragent') foreach($dbs as $biz) {
		if(!in_array($biz['db'], $databases)) {
			//echo "DB: {$biz['db']} not found.\n";
			continue;
		}
		$dbhost = $biz['dbhost'];
		$dbuser = $biz['dbuser'];
		$dbpass = $biz['dbpass'];
		$db = $biz['db'];
		$bizptr = $biz['bizid'];
		$lnk = mysqli_connect($dbhost, $dbuser, $dbpass);
		if ($lnk < 1) {
			echo "Not able to connect: invalid database username and/or password.\n";
		}
		$lnk1 = mysqli_select_db($db);
		if(mysqli_error()) echo mysqli_error();
		$tables = fetchCol0("SHOW TABLES");
		$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
		$bizName = $bizName ? $bizName : "($db)";

		if($action == 'activity') {
			if(!$includeInactive && !$biz['activebiz']) continue;
			if(!$includeTest && $biz['test']) continue;
			$checkedDBs = explode(',', $_POST["activitydbs"]);
			list($totLogins, $totMan, $totSit, $totClient, $totDisp) = array(0,0,0,0,0);
			foreach($logins as $login) {
				if($biz['bizid'] != $login['bizptr']) continue;
				$totLogins++;
				$role = $login['rights'][0];
				if($role == 'p') $totSit++;
				else if($role == 'c') $totClient++;
				else if($role == 'o') $totMan++;
				else if($role == 'd') $totDisp++;
			}
			$visits = fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE date >= '$since' AND date <= '$end'");
			$sitters = fetchCol0("SELECT distinct providerptr FROM tblappointment WHERE date >= '$since' AND date <= '$end' AND canceled IS NULL");
			$sitters = count($sitters) - (in_array(0, $sitters) ? 1 : 0);
			$canceledVisits = fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE date >= '$since' AND date <= '$end' AND canceled IS NOT NULL");
			$completedVisits = fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE date >= '$since' AND date <= '$end' AND completed IS NOT NULL");
			$messages = fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE datetime >= '$sinceTime' AND datetime <= '$endTime'");
			$outbound = fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE inbound = 0 AND datetime >= '$sinceTime' AND datetime <= '$endTime'");
			$phone = fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE transcribed = 'phone' AND datetime >= '$sinceTime' AND datetime <= '$endTime'");
			$email = fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE transcribed = 'email' AND datetime >= '$sinceTime' AND datetime <= '$endTime'");
			$invoices = fetchRow0Col0("SELECT count(*) FROM tblinvoice WHERE date >= '$sinceTime' AND date <= '$endTime'");
			$payments = fetchRow0Col0("SELECT count(*) FROM tblcredit WHERE payment = 1 AND issuedate >= '$sinceTime' AND issuedate <= '$endTime'");
			$tot['sitters'] += $sitters;
			$tot['visits'] += $visits;
			$tot['canceledVisits'] += $canceledVisits;
			$tot['completedVisits'] += $completedVisits;
			$tot['messages'] += $messages;
			$tot['outbound'] += $outbound;
			$tot['phone'] += $phone;
			$tot['email'] += $email;
			$tot['invoices'] += $invoices;
			$tot['payments'] += $payments;
			
			$tot['mlogin'] += $totMan;
			$tot['dlogin'] += $totDisp;
			$tot['plogin'] += $totSit;
			$tot['clogin'] += $totClient;
			$tot['tlogin'] += $totLogins;

			
			$checked = in_array("check_$db", $checkedDBs) ? 'CHECKED' : '';
			$cb = "<input type='checkbox' id='check_$db' onclick='recalculateAll(this)' $checked>";
			$label = $bizName ? $bizName : "($db)";
			$label = "<label for='check_$db'>$label</label>";
			echo "<tr id='$db'><td>$cb </td><td>$label</td>
						<td name='visits'>$visits</td>
						<td name='completedVisits'>$completedVisits</td>
						<td name='canceledVisits'>$canceledVisits</td>
						<td name='sitters'>$sitters</td>
						<td name='invoices'>$invoices</td>
						<td name='payments'>$payments</td>
						<td name='messages'>$messages</td>
						<td name='outbound'>$outbound</td><td name='phone'>$phone</td><td name='email'>$email</td>
						<td name='mlogin'>$totMan</td><td name='dlogin'>$totDisp</td><td name='plogin'>$totSit</td>
						<td name='clogin'>$totClient</td><td name='tlogin'>$totLogins</td>
						</tr>";
			
		}
		else if($action == 'findpremies') findPrematureMonthlies($bizName);
		else if($action == 'finddoublemonthlies') findDoubleMonthlies($bizName);
		else if($action == 'findWrongUsers') findWrongUsers($bizName, $biz);
		else if($action == 'findBadEmailAddresses') findBadEmailAddresses($bizName, $biz);
		else if($action == 'findDupPayables') findDupPayables($bizName, $biz);
		else if($action == 'findDuplicateBillabes') findDuplicateBillabes($bizName);
		else if($action == 'showRollover') showRollover($bizName);
		else if($action == 'showConflictingRecurring') showConflictingRecurring($bizName);

	}
	if($action == 'activity') {
		echo "<tr id='total'><td id='selections'>&nbsp;</td><td style='text-align:right;font-weight:bold'>Total</td>
		<td name='visits'>{$tot['visits']}</td>
		<td name='completedVisits'>{$tot['completedVisits']}</td>
		<td name='canceledVisits'>{$tot['canceledVisits']}</td>
		<td name='sitters'>{$tot['sitters']}</td>
		<td name='invoices'>{$tot['invoices']}</td>
		<td name='payments'>{$tot['payments']}</td>
		<td name='messages'>{$tot['messages']}</td>
		<td name='outbound'>{$tot['outbound']}</td><td name='phone'>{$tot['phone']}</td><td name='email'>{$tot['email']}</td>
		<td name='mlogin'>{$tot['mlogin']}</td><td name='dlogin'>{$tot['dlogin']}</td><td name='plogin'>{$tot['plogin']}</td>
		<td name='clogin'>{$tot['clogin']}</td><td name='tlogin'>{$tot['tlogin']}</td>
		
		</tr>";
		echo "</table>";
	}
	else if(in_array($action, array('findpremies', 'finddoublemonthlies','findWrongUsers', 'findBadEmailAddresses'))) {
		echo "</table>";
	}
	
}

function dumpCSVRow($row, $cols=null) {
	if(!$row) echo "\n";
	if(is_array($row)) {
		if($cols) {
			$nrow = array();
			if(is_string($cols)) $cols = explode(',', $cols);
			foreach($cols as $k) $nrow[] = $row[$k];
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


function cistrcmp($a, $b) {return strcmp(strtolower($a),strtolower($b)); }
function nameLine($arr) { 
	$loginid = $arr['loginid'] ? "[username: {$arr['loginid']}]" : '';
	$line = "{$arr['label']} $loginid [{$arr['id']}] [userid: {$arr['userid']}] {$arr['email']}";
	return $arr['active'] ? $line : "<font color=red>$line</font>";
}
function userId($arr) { return $arr['userid']; }

function reportGlobalStats() {
	$days = $_REQUEST['days'] ? $_REQUEST['days'] : 20;
	$start =  date('Y-m-d', strtotime("-$days days"));
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$roles = explodePairsLine('o|Manager||p|Sitter||c|Client||d|Dispatcher||z|Staff');
	$roles[null] = 'Unknown';
	/* ====== */
	/*$ignoreBizzes = fetchCol0("SELECT bizid FROM tblpetbiz WHERE test = 1");
	$ignoreBizzes[] = 68;
	$loginIdsToIgnore = fetchCol0("SELECT loginid FROM tbluser WHERE bizptr IN (".join(',', $ignoreBizzes).")");
	$distinctLogins =  fetchCol0(
			"SELECT loginid
			FROM tbllogin 
			WHERE LastUpdateDate > '$start 00:00:00' 
				AND loginid NOT IN ('".join("','", array_map('mysqli_real_escape_string', $loginIdsToIgnore))."')");
	$distinctLogins = fetchAssociationsKeyedBy(
		"SELECT loginid, bizptr, substring(rights, 1, 1) as role 
			FROM tbluser 
			WHERE loginid IN ('".join("','", array_map('mysqli_real_escape_string', $distinctLogins))."')", 'loginid');
	for($date = $start; $date <= date('Y-m-d'); $date = date('Y-m-d', strtotime("+ 24 hours", strtotime($date)))) {
		$sql = "SELECT loginid FROM tbllogin WHERE LastUpdateDate LIKE '$date%' AND success";
		$result = doQuery($sql);
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
//if(!$row['role']) echo print_r($row, 1).'<br>';			
			$logins[$date]['bizzes'][$distinctLogins[$row['loginid']]['bizptr']] = null;
			$logins[$date]['loginids'][$row['loginid']] = $roles[$distinctLogins[$row['loginid']]['role']];
		}*/
	/* ====== */
	
	
	for($date = $start; $date <= date('Y-m-d'); $date = date('Y-m-d', strtotime("+ 24 hours", strtotime($date)))) {
		$sql = "SELECT substring(rights, 1, 1) as role, bizptr, tbllogin.loginid as loginid
						FROM tbllogin
						LEFT JOIN tbluser u ON u.loginid = tbllogin.loginid
						LEFT JOIN tblpetbiz ON bizid = bizptr
						WHERE LastUpdateDate LIKE '$date%' AND success AND test = 0 AND bizid != 68";
		$result = doQuery($sql);
//echo "[{$logins[$date]['count']}] ".print_r($sql, 1).'<br>';			
		while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
//if(!$row['role']) echo print_r($row, 1).'<br>';			
			$logins[$date]['bizzes'][$row['bizptr']] = null;
			$logins[$date]['loginids'][$row['loginid']] = $roles[$row['role']];
		}
		$logins[$date]['count'] = count($logins[$date]['loginids']);
		$logins[$date]['bizzes'] = count($logins[$date]['bizzes']);
		foreach($logins[$date]['loginids'] as $role)
			$logins[$date]['roles'][$role]++;
	}
//echo print_r($logins, 1).'<br>';			
	ksort($logins);
	$columns = explodePairsLine('date|Date||bizzes|Businesses||total|Total Users');
	foreach($logins as $date => $stats) {
		$row = array('date'=>date('m/d/Y D', strtotime($date)), 'total'=>$stats['count'], 'bizzes'=>$stats['bizzes']);
		foreach((array)$stats['roles'] as $r => $rcount) {
			$allRoles[$r] = null;
			$row[$r] = $rcount;
		}
		$rows[] = $row;
	}
	ksort($allRoles);
	foreach($allRoles as $k => $v) $columns[$k] = $k;
	
	/*$logins = fetchKeyValuePairs($sql);
	ksort($logins);
	$sql = "SELECT distinct loginid 
					FROM tbllogin 
					WHERE LastUpdateDate >= '$start' AND success 
					GROUP BY logdate";
	foreach($logins as $date => $count) echo "<tr><td>".date('m/d D', strtotime($date))."<td>$count";
	*/
	
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	echo "<b>Distinct Users Logging In Successfully By Day</b> (excludes LeashTime Customers db and Test dbs)<p>";
	tableFrom($columns, $rows, $attributes='border=1 bordercolor=black', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}

function reportVisitStats() {
	global $dbs, $visitStats, $databases;
	$days = $_REQUEST['days'];
	$start =  date('Y-m-d', strtotime("-$days days"));

	foreach($dbs as $biz) {
		if(!in_array($biz['db'], $databases)) {
			//echo "DB: {$biz['db']} not found.\n";
			continue;
		}
		if($biz['test']) continue;
		if(!$biz['activebiz']) continue;
		if(!$biz['db'] == 'leashtimecustomers') continue;
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if(mysqli_error()) echo mysqli_error();
		for($date = $start; $date <= date('Y-m-d'); $date = date('Y-m-d', strtotime("+ 24 hours", strtotime($date)))) {
			$sql = "SELECT completed, canceled
							FROM tblappointment
							WHERE date = '$date'";
			$result = doQuery($sql);
			if($result) while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
				$visitStats[$date]['date'] = $date;
				$visitStats[$date]['total'] += 1;
				$visitStats[$date]['canceled'] += ($row['canceled'] ? 1 : 0);
				$visitStats[$date]['complete'] += ($row['completed'] ? 1 : 0);
				$visitStats[$date]['active'] += (!$row['canceled'] ? 1 : 0);
				$visitStats[$date]['incomplete'] += (!$row['completed'] && !$row['canceled'] ? 1 : 0);
			}
		}
	}
	ksort($visitStats);
	foreach($visitStats as $day) {
		$finalDay['date'] = 'All Days';
		$finalDay['total'] += $day['total'];
		$finalDay['canceled'] += $day['canceled'];
		$finalDay['complete'] += $day['complete'];
		$finalDay['active'] += $day['active'];
		$finalDay['incomplete'] += $day['incomplete'];
	}
	$visitStats[] = $finalDay;
	$columns = explodePairsLine('date|Date||total|Total||canceled|Canceled||active|Active||complete|Complete||incomplete|Incomplete');
	
	echo "<b>Total Visits By Day</b> (excludes LeashTime Customers db and Test dbs)<p>";
	tableFrom($columns, $visitStats, $attributes='border=1 bordercolor=black', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null);
}

function findDupPayables($bizName, $biz) {
	$sql = "SELECT concat(itemtable, '_', itemptr) AS compoundkey, itemptr, itemtable, count( * ) AS x FROM tblpayable GROUP BY compoundkey";
	foreach(fetchAssociations($sql) as $row) if($row['x'] > 1) $dups[$row['itemtable']][] = $row['itemptr'];
	
	$queries = array(
		'tblappointment' =>
			"SELECT tblpayable.date, itemtable, itemptr, payableid,
				CONCAT_WS(' ', p.fname, p.lname) as providername, 
				CONCAT_WS(' ', c.fname, c.lname) as clientname,
				tblpayable.amount,
				paid,
				s.label as service,
				completed
				FROM `tblpayable`
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblappointment a ON appointmentid = itemptr
				LEFT JOIN tblclient c ON clientid = clientptr
				LEFT JOIN tblservicetype s ON servicetypeid = servicecode
				 WHERE itemptr IN (##SET##)
				ORDER BY providername, date, clientname",
		'tblsurcharge'=>
			"SELECT tblpayable.date, itemtable, itemptr, payableid,
				CONCAT_WS(' ', p.fname, p.lname) as providername, 
				CONCAT_WS(' ', c.fname, c.lname) as clientname,
				tblpayable.amount,
				paid,
				s.label as service,
				completed
				FROM `tblpayable`
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblsurcharge a ON surchargeid = itemptr
				LEFT JOIN tblclient c ON clientid = clientptr
				LEFT JOIN tblsurchargetype s ON surchargetypeid = surchargecode
				 WHERE itemptr IN (##SET##)
				ORDER BY providername, date, clientname",
		'tblrecurringpackage'=>
			"SELECT tblpayable.date, itemtable, itemptr, payableid,
				CONCAT_WS(' ', p.fname, p.lname) as providername, 
				CONCAT_WS(' ', c.fname, c.lname) as clientname,
				tblpayable.amount,
				paid,
				'Monthly Service' as service
				FROM `tblpayable`
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblsurcharge a ON packageid = itemptr
				LEFT JOIN tblclient c ON clientid = clientptr
				 WHERE itemptr IN (##SET##)
				ORDER BY providername, date, clientname",
		'tblothercomp'=>
			"SELECT tblpayable.date, itemtable, itemptr, payableid,
				CONCAT_WS(' ', p.fname, p.lname) as providername, 
				tblpayable.amount,
				paid,
				descr as service
				FROM `tblpayable`
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblothercomp a ON compid = itemptr
				 WHERE itemptr IN (##SET##)
				ORDER BY providername, date",
		'tblgratuity'=>
			"SELECT tblpayable.date, itemtable, itemptr, payableid,
				CONCAT_WS(' ', p.fname, p.lname) as providername, 
				CONCAT_WS(' ', c.fname, c.lname) as clientname,
				tblpayable.amount,
				paid,
				tipnote as service
				FROM `tblpayable`
				LEFT JOIN tblprovider p ON providerid = providerptr
				LEFT JOIN tblgratuity a ON gratuityid = itemptr
				LEFT JOIN tblclient c ON clientid = clientptr
				 WHERE itemptr IN (##SET##)
				ORDER BY providername, date"
	);


	//print_r($dups);
	foreach((array)$dups as $table => $set) {
		if(!($sql = $queries[$table])) echo "$table???";
		$sql = str_replace('##TABLE##', $table, $queries[$table]);
		$sql = str_replace('##SET##', join(',', $set), $sql);
		$completed = in_array($table, array('tblappointment', 'tblsurcharge'))
				? 'a.completed as completed'
				: '1';
		$sql = str_replace('##COMPLETED##', join(',', $set), $sql);
		foreach(fetchAssociations($sql) as $row) $rows[] = $row;
	}
	if($rows) {
		echo "<tr><td colspan=10><b>$bizName [$db]";
		$columns = explodePairsLine('date|Date||providername|Sitter||payableid|Payable||itemtable|Type||itemptr|Item||clientname|Client||amount|Amount||paid|Paid||service|Service||completed|completed');
		echo "<tr><th>".join('<th>', $columns);
		foreach($rows as $row) {
			$disp = array();
			foreach(array_keys($columns) as $col) $disp[] = $row[$col];
			echo "<tr><td>".join('<td>', $disp);
		}
	}
	else echo "<tr><td colspan=10 style='color:lightgrey'><b>$bizName [$db] - none";
	
	//tableFrom($columns, $rows, "WIDTH=100%",null,null,null,null,$colSorts,$rowClasses, $colClasses, 'sortInvoices');
	
}
function findBadEmailAddresses($bizName, $biz) {
	global $dbhost, $db, $dbuser, $dbpass;
	static $users;
	
	if(!$users) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$users = fetchAssociationsKeyedBy("SELECT *, CONCAT_WS(' ', fname, lname) as name FROM tbluser WHERE rights LIKE 'o-%' OR  rights LIKE 'o-%'", 'userid');
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	}
	foreach($users as $user) {
		if($user['bizptr'] != $biz['bizid']) continue;
		if($user['email'] && !isEmailValid($user['email'])) { // see field-utils.php
			$user['name'] .= $rights[0] = 'o' ? " (manager)" : " (dispatcher)";
			$badMans[] = $user;
		}
	}
	foreach(fetchAssociations("SELECT CONCAT_WS(' ', fname, lname) as name, email, email2, clientid, userid as id FROM tblclient") as $client) {
		$msg1 = !$client['email'] || isEmailValid($client['email']) ? '' : "Bad email: [{$client['email']}].  ";
		$msg2 = !$client['email2'] || isEmailValid($client['email2']) ? '' : "Bad email2: [{$client['email2']}]";
		if($msg1 || $msg2) {
			//echo "<br>--- Client <a href=client-edit.php?id={$client['id']}>[{$client['name']}]</a>: $msg1$msg2";
			$badClients[] = $client;
		}
	}
	foreach(fetchAssociations("SELECT CONCAT_WS(' ', fname, lname) as name, email, providerid, userid as id FROM tblprovider") as $prov) {
		$msg1 = !$prov['email'] || isEmailValid($prov['email']) ? '' : "Bad email: [{$prov['email']}].  ";
		if($msg1) {
			//echo "<br>--- Sitter <a href=provider-edit.php?id={$provider['id']}>[{$prov['name']}]</a>: $msg1";
			$badProviders[] = $prov;
		}
	}
	if(!$biz['activebiz']) $bizName .= '<font color=red>INACTIVE</font>';
	$color = !$badMans && !$badClients && !$badProviders ? 'lightgrey' : 'black';
	echo "<tr><td colspan=5 style='color:$color'><b>$bizName [$db]";
	if(!$badMans && !$badClients && !$badProviders) echo ": none";
	else echo "<tr><td>User ID<td>User Name<td>Email";
	foreach(array('Managers and Dispatchers'=>$badMans, 'Clients (requires login to change)'=>$badClients, 'Providers (requires login to change)'=>$badProviders) as $label => $set) {
		if(!$set) continue;
		echo "<tr><td style='font-weight:bold;text-align:center;' colspan=3>$label";
		foreach($set as $person) {
			if($label[0] == 'M') $editor = "openConsoleWindow(\"edituser\", \"maint-edit-user.php?userid={$person['userid']}\", 400, 450)";
			else if($label[0] == 'P') $editor = "document.location.href=\"provider-edit.php?id={$provider['id']}";
			else $editor = "document.location.href=\"client-edit.php?id={$person['clientid']}\"";
			echo "<tr><td>{$person['userid']}<td>".fauxLink($person['name'], $editor, 1)."<td>{$person['email']}";
		}
	}
}

function findWrongUsers($bizName, $biz) {
	global $dbhost, $db, $dbuser, $dbpass;
	static $users;
	if(!$users) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$users = fetchAssociationsKeyedBy("SELECT userid, loginid, rights, bizptr FROM tbluser", 'userid');
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, 1);
	}
	$rows = array();
	$missingIds = array();
	foreach($users as $userid => $user) {
		if($user['bizptr'] != $biz['bizid']) continue;
		$errors = array();
		$rights = $user['rights'];
		if(!rights) $errors[] = 'No rights found';
		else {
			$role = $rights[0];
			$provider = fetchFirstAssoc("SELECT * FROM tblprovider WHERE userid = {$user['userid']} LIMIT 1");
			$client = fetchFirstAssoc("SELECT * FROM tblclient WHERE userid = {$user['userid']} LIMIT 1");
			if($role == 'p') {
				$role = 'sitter';
				$person = $provider;
				if(!$person) {
					$errors[] = 'No sitter with this userid was found';
					$missingIds[] = $userid;
					if($client) $errors[] = "However, the client {$client['fname']} {$client['lname']} DOES have this login id";
				}
			}
			if($role == 'c') {
				$role = 'client';
				$person = $client;
				if(!$person) {
					$errors[] = 'No client with this userid was found';
					$missingIds[] = $userid;
					if($provider) $errors[] = "<br>However, the sitter {$provider['fname']} {$provider['lname']} DOES have this login id";
				}
			}
			else $person = $client ? $client : $provider;
			if(strlen($rights) < 3 && !in_array($rights, array('o-', 'd-'))) {
				$role = $client ? 'client' : 'provider';
				require_once "system-login-fns.php";
				$suggestion = basicRightsForRole($role);
				$role = $role == 'provider' ? 'sitter' : $role;
				$errors[] = "[$rights] does not look right, but userid belongs to $role {$person['fname']} {$person['lname']} [$suggestion]";
			}
		}
		if(!$errors) continue;
		$rows[] = "<tr><td>$userid<td>{$user['loginid']}<td>$role<td>{$person['fname']} {$person['lname']}<td>".join('<br>', $errors);
	}
	if($rows) {
		echo "<tr><td colspan=5><b>$bizName [$db]";
		echo "<tr><td>User ID<td>Login ID<td>Role<td>Name<td>Errors";
		foreach($rows as $row) echo $row;
		if($missingIds) echo "<tr><td colspan=5 style='color:blue'>DELETE FROM tbluser WHERE userid IN (".join(', ', $missingIds).");";
	}
}

function findPrematureMonthlies($bizName) {
	$nextMonth = date("Y-m-01", strtotime("+ 1 month", strtotime(date("Y-m-01"))));
	
	$billOn = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'monthlyBillOn' LIMIT 1");
	$latestMonthYear = date('d') >= $billOn ? $nextMonth : date("Y-m-01");

	$billables = fetchAssociations($sql = 
		"SELECT tblbillable.*, CONCAT_WS(' ', fname, lname) as client, lname, fname
			FROM tblbillable 
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE monthyear IS NOT NULL AND monthyear > '$latestMonthYear' AND superseded = 0
			ORDER BY lname, fname, monthyear");
	if(!$billables) return;
	$clientptr = null;
	echo "<tr><td colspan=4><b>$bizName";
	echo "<tr><td colspan=4><b>Bill on: [$billOn]: ".count($billables)." premature.";
	echo "<tr><td>ID<td>monthyear<td>charge<td>paid";
	foreach($billables as $b) {
		if($clientptr != $b['clientptr']) echo "<tr><td colspan=4>{$b['client']}</td></tr>";
		echo "<tr><td>{$b['billableid']}<td>{$b['monthyear']}<td>{$b['charge']}<td>{$b['paid']}";
	}
}

function findDoubleMonthlies($bizName) {
	static $started;
	$nextMonth = date("Y-m-01", strtotime("+ 1 month", strtotime(date("Y-m-01"))));
	
	$billOn = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'monthlyBillOn' LIMIT 1");

	$billables = fetchAssociations($sql = 
		"SELECT tblbillable.*, CONCAT_WS(' ', fname, lname) as client, lname, fname, count(*) as copies, CONCAT_WS(' ', clientptr, ',', monthyear) as X
			FROM tblbillable 
			LEFT JOIN tblclient ON clientid = clientptr
			WHERE monthyear IS NOT NULL AND superseded = 0
			GROUP BY X
			ORDER BY lname, fname, monthyear, billabledate");
	foreach($billables as $i => $b) {
		if($b['copies'] < 2) unset($billables[$i]);
	}
			
	if(!$billables) return;
	$clientptr = null;
	echo "<tr><td colspan=6><b>$bizName";
	echo "<tr><td colspan=6><b>Bill on: [$billOn]: ".count($billables)." duplicate monthlies.";
	echo "<tr><td>ID<td>monthyear<td>copies<td>charge<td>created<td>query";
	foreach($billables as $b) {
		$sql = "SELECT * FROM tblbillable WHERE clientptr = {$b['clientptr']} AND monthyear = '{$b['monthyear']}' AND superseded = 0";
		if($clientptr != $b['clientptr']) 
			echo "<tr><td colspan=6>{$b['client']} ({$b['clientptr']})</td></tr>";
		$clientptr = $b['clientptr'];
		echo "<tr><td>{$b['billableid']}<td>{$b['monthyear']}<td>{$b['copies']}<td>{$b['charge']}<td>{$b['billabledate']}<td>$sql";
	}
}

function showConflictingRecurring($bizName) {
	$counts = fetchKeyValuePairs("SELECT clientptr, count(*) as nn FROM tblrecurringpackage WHERE current = 1 group by clientptr");
	foreach($counts as $clientptr => $count) if($count > 1) $clients[] = $clientptr;
	if($clients) $packs = fetchAssociations(
										"SELECT clientptr, packageid, created, modified, monthly, CONCAT_WS(' ', fname, lname) as name 
												FROM tblrecurringpackage
												LEFT JOIN tblclient ON clientid = clientptr
												WHERE current = 1 AND clientptr IN (".join(',', $clients).")
												ORDER BY clientptr, packageid");
	if(!$packs) {
		echo "<tr style='border:solid black 1px;border-top:solid black 2px;color:lightgrey'><td colspan=1><b>$bizName</b><td>No conflicts.";
		return;
	}
	echo "<tr style='border: solid black 1px;border-top:solid black 2px;font-weight:bold;font-size:1.2em;'><td colspan=4><b>$bizName</b>";
	echo "<tr><th>ID<th>Created<th>Modified<th>Monthly";
	foreach($packs as $pack) {
		if($pack['name'] != $clientname) echo  "<tr><td colspan=4><b>{$pack['name']} ({$pack['clientptr']})</b>";
		$clientname = $pack['name'];
		$monthly = $pack['monthly'] ? 'monthly' : '';
		echo "<tr><td>{$pack['packageid']}<td>{$pack['created']}<td>{$pack['modified']}<td>$monthly";
	}
}

function showRollover($bizName) {
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job
	$recurringLookaheadDays = $prefs['recurringScheduleWindow'] ? $prefs['recurringScheduleWindow'] : 30;
	$lastDay = date('l, Y-m-d', strtotime("+ $recurringLookaheadDays days"));
	
	echo "<tr><td colspan=1 style='border: solid black 1px;'><b>$bizName</b><td>Last Day: $lastDay";
	$rollos = fetchAssociations("SELECT * 
														FROM  `tblchangelog` 
														WHERE note LIKE  'ROLL%'
														ORDER BY  `tblchangelog`.`time` DESC 
														LIMIT 5");
	foreach($rollos as $rollo)
		echo "<tr><td style='border: solid black 1px;'>{$rollo['time']}<td style='border: solid black 1px;'>{$rollo['note']}";
}

function findDuplicateBillabes($bizName) {
	$earliestDate = '2013-01-01';
	$billableDateTest = "AND billabledate >= '$earliestDate'";
	$apptids = fetchCol0("SELECT itemptr FROM
		(SELECT itemptr, count(*) as dups
		FROM tblbillable
		WHERE superseded = 0 AND itemtable = 'tblappointment' $billableDateTest
		GROUP BY itemptr) x
		WHERE x.dups > 1");


	$surchargeids = fetchCol0("SELECT itemptr FROM
		(SELECT itemptr, count(*) as dups
		FROM tblbillable
		WHERE superseded = 0 AND itemtable = 'tblsurcharge' $billableDateTest
		GROUP BY itemptr) x
		WHERE x.dups > 1");


	echo "<tr><td style='border: solid black 1px;'><b>$bizName</b><p>";
	if(!$apptids && !$surchargeids) {
		echo "No dups.</td>";
		return;
	}

	$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");

	//print_r($billables);
	if($apptids) {
		$lastInvoice = -1;
		$billables = fetchAssociations("SELECT * FROM tblbillable WHERE superseded = 0 $billableDateTest AND itemptr IN (".join(',', $apptids).") 
																			ORDER BY itemptr, invoiceptr, billableid");
		$appts = fetchAssociationsKeyedBy("SELECT * FROM tblappointment 
																				WHERE appointmentid IN (".join(',', $apptids).")", 'appointmentid');
		foreach($billables as $b) {
			if($b['itemptr'] != $lastItem) {
				$lastInvoice = -1;
				$appt = $appts[$b['itemptr']];
				echo "<hr>VISIT [{$b['itemptr']}]: Client: {$clients[$b['clientptr']]} {$appt['date']} {$appt['timeofday']} $".($appt['charge']+$appt['adjustment']).'<br>';
				$lastItem = $b['itemptr'];
			}
			if($b['invoiceptr'] != $lastInvoice) echo ($b['invoiceptr'] ? " - INVOICE: {$b['invoiceptr']}" : " - UNINVOICED").'<br>';
			$lastInvoice = $b['invoiceptr'];
			echo "- - BILLABLE: {$b['billableid']} BILLDATE: {$b['billabledate']} SUPERSEDED: ".($b['superseded'] ? 'yes' : 'no')
						." Charge \${$b['charge']}  Paid: \${$b['paid']}<br>";
		}
	}

	if($surchargeids) {
		$lastInvoice = -1;
		$billables = fetchAssociations("SELECT * FROM tblbillable WHERE superseded = 0 $billableDateTest AND itemptr IN (".join(',', $surchargeids).")
																			ORDER BY itemptr, invoiceptr, billableid");
		$surcharges = fetchAssociationsKeyedBy("SELECT * FROM tblsurcharge 
																				WHERE surchargeid IN (".join(',', $surchargeids).")", 'appointmentid');
		foreach($billables as $b) {
			if($b['itemptr'] != $lastItem) {
				$lastInvoice = -1;
				$appt = $surcharges[$b['itemptr']];
				echo "<hr>SURCHARGE [{$b['itemptr']}]: Client: {$clients[$b['clientptr']]} {$appt['date']} {$appt['timeofday']} $".($appt['charge']).'<br>';
				$lastItem = $b['itemptr'];
			}
			if($b['invoiceptr'] != $lastInvoice) echo ($b['invoiceptr'] ? " - INVOICE: {$b['invoiceptr']}" : " - UNINVOICED").'<br>';
			$lastInvoice = $b['invoiceptr'];
			echo "- - BILLABLE: {$b['billableid']} BILLDATE: {$b['billabledate']} SUPERSEDED: ".($b['superseded'] ? 'yes' : 'no').
						" Charge \${$b['charge']}  Paid: \${$b['paid']}<br>";
		}
	}
	echo "</td>";
}

if($csv) exit;

?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='common.js'></script>

<script language='javascript'>
function report(action) {
	var days=0;
	if(action == 'showVisitStats' || action == 'showStats') {
		if(days = prompt('How many days back?', '28'))
			document.getElementById('days').value=days;
		else return;
	}
	activityReportPreSubmit();
	document.getElementById('action').value=action;
	document.searchform.submit();
}

function activityReportPreSubmit() {
	var inps = document.getElementsByTagName('input');
	var sels = new Array();
	for(var i=0;i<inps.length;i++) 
		if(inps[i].type=='checkbox' 
				&& inps[i].id 
				&& inps[i].id.indexOf('check_') == 0
				&& inps[i].checked) sels[sels.length] = inps[i].id;
	document.getElementById('activitydbs').value = sels.join(',');
	//alert(sels.join(','));
}

function getTD(row, col) {
	var tds = document.getElementsByTagName('td');
	for(var i=0;i<tds.length;i++) {
		if(tds[i].getAttribute('name')==col && tds[i].parentNode.id==row)
			return tds[i];
	}
}

function toggleRole(role) {
	$("td.sortableListCell").each(
		function(ind, element) {
			if($(this).text() == role)
				$(this).parent().toggle();
		}
	);
}

function selectAll(el) {
	var inps = document.getElementsByTagName('input');
	for(var i=0;i<inps.length;i++) if(inps[i].type=='checkbox' && inps[i].id && inps[i].id.indexOf('check_') == 0) inps[i].checked = el.checked;
	recalculateAll();
}

function recalculateAll() {
	var cols = 'visits,completedVisits,canceledVisits,invoices,payments,messages,outbound,phone,email'.split(',');
	for(var i = 0; i < cols.length; i++) {
		var sum = sumColumn(cols[i]);
		getTD('total', cols[i]).innerHTML = sum;
	}
	document.getElementById('selections').innerHTML = updateLooks();
}

function updateLooks() {
	var inps = document.getElementsByTagName('input');
	var n = 0;
	for(var i=0;i<inps.length;i++) 
		if(inps[i].type=='checkbox' && inps[i].id && inps[i].id.indexOf('check_') == 0) {
			inps[i].parentNode.parentNode.style.background = inps[i].checked ? 'white' : 'lightgrey';
			if(inps[i].checked) n++;
		}
	return n;
}

function sortUserAgent(sortKey, direction) {
	document.location.href='maint-cross-report.php?action=useragent&useragent='
		+escape(document.searchform.useragent.value)
		+'&sort='+sortKey+'_'+direction;
}

function sumColumn(col) {
	var total = 0;
	var tds = document.getElementsByTagName('td');
	for(var i=0;i<tds.length;i++) {
		if(tds[i].getAttribute('name')==col 
				&& tds[i].parentNode.id!='total'
				&& document.getElementById('check_'+tds[i].parentNode.id).checked )
			total += parseFloat(tds[i].innerHTML);
	}
	return total;
}

function setDateRange(el) {
	var sel = el.options[el.selectedIndex].value;
	if(!sel) return;
	sel = sel.split('-');
	document.getElementById('activitydate').value = sel[0];
	document.getElementById('activityend').value = sel[1];
}

updateLooks();
//selectAll(document.getElementById('selectall'));

<? dumpPopCalendarJS(); ?>
</script	>