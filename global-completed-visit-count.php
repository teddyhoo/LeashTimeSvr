<? // global-completed-visit-count.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";

$sunset = 300;  // max seconds since last refresh

if($_GET['last2days']) {
	$t0 = microtime(1);
	$counts = getLastNDaysVisitCounts(1);
	if(!is_array($counts)) $counts = (array('error'=>$counts));
	else {
		$dates = array_keys($counts);
		$raw =  fetchRow0Col0($sql = 
			"SELECT value 
			 FROM tbluserpref 
			 WHERE  property = 'visits_{$dates[0]}' AND userptr = '-999' LIMIT 1");
//echo $raw;			 
		$asOf = substr($raw, 0, strpos($raw, '|'));
		$counts['asOf'] = $asOf;
		$counts['totalyesterday'] = getYesterdayTOTALVisitCounts(1200);
	 }
	echo json_encode($counts);
	if($_GET['test']) echo "<br>Runtime: ".(microtime(1) - $t0)." seconds";
}
else if($_GET['lastndays']) {
	$t0 = microtime(1);
	$counts = getLastNDaysVisitCounts($_GET['lastndays']);
	if(!is_array($counts)) echo json_encode(array('error'=>$counts));
	echo json_encode($counts);
	if($_GET['test']) echo "<br>Runtime: ".(microtime(1) - $t0)." seconds";
}
else if($_GET['latest']) {
	$count = getLastCount(0);
	if(is_array($count)) echo json_encode(array('error'=>$count[0]));
	echo json_encode(array('todaysvisits'=>$count));
}
else if($_GET['all']) {
	$t0 = microtime(1);
	$ycount = getYearCount(false);
	if(is_array($ycount)) $all = array('error'=>"year: {$ycount[0]}");
	else {
		$today = getLastCount(0);
		if(is_array($today)) $all = array('error'=>"today: {$today[0]}");
		else $all = array('thisyear'=>$ycount, 'today'=>$today);
	}
	if($all) echo json_encode($all);
	if($_GET['test']) echo "<br>Runtime: ".(microtime(1) - $t0)." seconds";
}
	
else if($_GET['after']) {
	$t0 = microtime(1);
	$after = $_GET['after'];
	$result = recalculate(1, $after);
	if(is_array($result[1])) echo "ERROR: {$result[1][0]}";
	else echo "Visits after $after: ".$result[1];
	echo "<br>Runtime: ".(microtime(1) - $t0)." seconds";
}
	
else if($_GET['thisyear']) {
	$t0 = microtime(1);
	$count = getYearCount('test');
	if(is_array($count)) echo "ERROR: {$count[0]}";
	else echo "Visits this year: ".$count;
	echo "<br>Runtime: ".(microtime(1) - $t0)." seconds";
}
	
else if($_GET['test']) {
	$t0 = microtime(1);
	$count = getLastCount(1);
	if(is_array($count)) echo "ERROR: {$count[0]}";
	else echo "Visits today: ".$count;
	echo "<br>Runtime: ".(microtime(1) - $t0)." seconds";
}
	
function recalculate($test, $after=null, $property=null) {
	//echo "<p>OI!<p>";
	// TBD: restrict access to the leashtime.com domain
	// for each active, non-test business, count today's visits
	$bizzes = fetchAssociationsKeyedBy(
		"SELECT * 
			FROM tblpetbiz 
			WHERE activebiz=1 AND test=0", 'db');
	$today = date("Y-m-d");
	foreach($bizzes as $biz) {
		$dbhost = $biz['dbhost'];
		$dbuser = $biz['dbuser'];
		$dbpass = $biz['dbpass'];
		$db = $biz['db'];
		$bizptr = $biz['bizid'];
		$lnk = mysql_connect($dbhost, $dbuser, $dbpass);
		if ($lnk < 1) {
			if($test) echo "Not able to connect: invalid database username and/or password.\n";
		}
		$lnk1 = mysql_select_db($db);
		if(mysql_error()) {
			if($test) echo mysql_error().'<br>';
			continue;
		}
		$tables = fetchCol0("SHOW TABLES");
		if(!in_array('tblappointment', $tables)) continue;
		$today = date("Y-m-d");
		$dateTest = $after ? "date > '$after'" : "date = '$today'";
		$count += fetchRow0Col0(
			"SELECT COUNT(*) 
				FROM tblappointment
				WHERE $dateTest AND completed IS NOT NULL");
	}
	// $error = 'some message';
	if($error)$parts = array(date('Y-m-d H:i:s'), array($error));
	else {
		$parts = array(date('Y-m-d H:i:s'), $count);
		require "common/init_db_common.php";
		$property = $property ? $property : 'lastvisitcount';
		replaceTable('tbluserpref', 
									array('userptr'=>'-999', 'property'=>$property, 'value'=>join('|', $parts)),
									1);
	}
	return $parts;
}

function recalculateCompletedCountsStarting($onOrAfter) {
	//echo "<p>OI!<p>";
	// TBD: restrict access to the leashtime.com domain
	// for each active, non-test business, count today's visits
	$bizzes = fetchAssociationsKeyedBy(
		"SELECT * 
			FROM tblpetbiz 
			WHERE activebiz=1 AND test=0", 'db');
	$today = date("Y-m-d");
	foreach($bizzes as $biz) {
		$dbhost = $biz['dbhost'];
		$dbuser = $biz['dbuser'];
		$dbpass = $biz['dbpass'];
		$db = $biz['db'];
		$bizptr = $biz['bizid'];
		$lnk = mysql_connect($dbhost, $dbuser, $dbpass);
		if ($lnk < 1) {
			if($test) echo "Not able to connect: invalid database username and/or password.\n";
		}
		$lnk1 = mysql_select_db($db);
		if(mysql_error()) {
			if($test) echo mysql_error().'<br>';
			continue;
		}
		$tables = fetchCol0("SHOW TABLES");
		if(!in_array('tblappointment', $tables)) continue;
		$dateTest = "date >= '$onOrAfter' AND date <= '$today'";
		//$count += fetchRow0Col0(
		//	"SELECT COUNT(*) 
		//		FROM tblappointment
		//		WHERE $dateTest AND completed IS NOT NULL");
		$local = fetchKeyValuePairs(
			"SELECT date, COUNT(*) 
				FROM tblappointment
				WHERE $dateTest AND completed IS NOT NULL
				GROUP BY date
				ORDER BY date");
		foreach($local as $date => $count) $counts[$date] += $count;
	}
	// $error = 'some message';
	if($error) $error = $error;
	else {
		require "common/init_db_common.php";
		$now = date('Y-m-d H:i:s');
		foreach($counts as $date=>$count) {
			replaceTable('tbluserpref', 
										array('userptr'=>'-999', 'property'=>"visits_$date", 'value'=>"$now|$count"),
										1);
		}
	}
	return $error ? $error : $counts;
}


function getLastCount($test) {
	// return an array on error
	// TBD: restrict access to the leashtime.com domain
	global $sunset;
	$raw =  fetchRow0Col0(
		"SELECT value 
		 FROM tbluserpref 
		 WHERE property = 'lastvisitcount' AND userptr = '-999' LIMIT 1");
	// format: lastRefreshdatetime|count
	$parts = explode('|', "$raw");
	if(!$parts[0]) {
		$parts = recalculate($test);
		$refreshed = true;
	}
	else {
		$datetime = strtotime($parts[0]);
		//echo time()." - $datetime > $sunset<p>";
		if(time() - $datetime  > $sunset && lockRecalculator()) {
			$parts = recalculate($test);
			unlockRecalculator();
		}
	}
	return $parts[1];
}

function getLastNDaysVisitCounts($n) {
	global $sunset;
	$today = date("Y-m-d");
	$onOrAfter = date('Y-m-d', strtotime("-$n days"));
	$raw =  fetchRow0Col0(
		"SELECT value 
		 FROM tbluserpref 
		 WHERE property = 'visits_$onOrAfter' AND userptr = '-999' LIMIT 1");
	// format: visits_2016-12-31 = > lastRefreshdatetime|count
	$parts = explode('|', "$raw");
	if(!$parts[0]) {
		$counts = recalculateCompletedCountsStarting($onOrAfter);
		$refreshed = true;
	}
	else {
		$datetime = strtotime($parts[0]);
		//echo time()." - $datetime > $sunset<p>";
		if(time() - $datetime  > $sunset && lockRecalculator()) {
			$counts = recalculateCompletedCountsStarting($onOrAfter);
			unlockRecalculator();
		}
		else {
			for($n; $n>=0; $n-=1) {
				$properties[] = "visits_".date('Y-m-d', strtotime((0-$n)." days"));
			}
			$raw = 
				fetchKeyValuePairs("SELECT property, value
								 FROM tbluserpref 
								 WHERE property IN ('".join("','", $properties)."')
								 ORDER BY property");
			foreach($raw as $key=>$value) {
				if(!$value) continue;
				$count = explode('|', $value);
				$counts[substr($key, strlen('visits_'))] = $count[1];
			}
		}
	}
	return $counts;
}

function getYesterdayTOTALVisitCounts($sunset=1200, $day=null) {
	$day = $day ? $day : date("Y-m-d", strtotime("- 1 day"));
	$raw =  fetchRow0Col0(
		"SELECT value 
		 FROM tbluserpref 
		 WHERE property = 'totalvisits_$day' AND userptr = '-999' LIMIT 1");
	// format: visits_2016-12-31 = > lastRefreshdatetime|count
	$parts = explode('|', "$raw");
	if(!$parts[0]) {
		$count = recalculateTOTALVisitCount($day);
		$refreshed = true;
	}
	else {
		$datetime = strtotime($parts[0]);
		//echo time()." - $datetime > $sunset<p>";
		if(time() - $datetime  > $sunset && lockRecalculator()) {
			$count = recalculateTOTALVisitCount($day);
			unlockRecalculator();
		}
		else {
			$raw = 
				fetchRow0Col0("SELECT value
								 FROM tbluserpref 
								 WHERE property = 'totalvisits_$day')
								 ORDER BY property");
			$count = explode('|', $value);
			$count = $count[1];
		}
	}
	return $count;
}

function recalculateTOTALVisitCount($day) {
	//echo "<p>OI!<p>";
	// TBD: restrict access to the leashtime.com domain
	// for each active, non-test business, count today's visits
	$bizzes = fetchAssociationsKeyedBy(
		"SELECT * 
			FROM tblpetbiz 
			WHERE activebiz=1 AND test=0", 'db');
	foreach($bizzes as $biz) {
		$dbhost = $biz['dbhost'];
		$dbuser = $biz['dbuser'];
		$dbpass = $biz['dbpass'];
		$db = $biz['db'];
		$bizptr = $biz['bizid'];
		$lnk = mysql_connect($dbhost, $dbuser, $dbpass);
		if ($lnk < 1) {
			if($test) echo "Not able to connect: invalid database username and/or password.\n";
		}
		$lnk1 = mysql_select_db($db);
		if(mysql_error()) {
			if($test) echo mysql_error().'<br>';
			continue;
		}
		$tables = fetchCol0("SHOW TABLES");
		if(!in_array('tblappointment', $tables)) continue;
		$local = fetchRow0Col0(
			"SELECT COUNT(*) 
				FROM tblappointment
				WHERE date = '$day'");
		$count += $local;
	}
	// $error = 'some message';
	if($error) $error = $error;
	else {
		replaceTable('tbluserpref', 
									array('userptr'=>'-999', 'property'=>"totalvisits_$date", 'value'=>"$now|$count"),
									1);
	}
	return $error ? $error : $count;
}

function getYearCount($test) {
	// return an array on error
	// TBD: restrict access to the leashtime.com domain
	global $sunset;
	$raw =  fetchRow0Col0(
		"SELECT value 
		 FROM tbluserpref 
		 WHERE property = 'lastvisitcountthisyear' AND userptr = '-999' LIMIT 1");
	// format: lastRefreshdatetime|count
	$parts = explode('|', "$raw");
	$after = (date('Y')-1).'-12-31';

	if(!$parts[0]) {
		$parts = recalculate($test, $after, 'lastvisitcountthisyear');
	}
	else {
		$datetime = strtotime($parts[0]);
		//echo time()." - $datetime > $sunset<p>";
		if(time() - $datetime  > $sunset && lockRecalculator()) {
			$parts = recalculate($test, $after, 'lastvisitcountthisyear');
			unlockRecalculator();
		}
	}
	return $parts[1];
}
	
function unlockRecalculator() {
	deleteTable('tbluserpref', "userptr = '-999' && property = 'visitcountrecalcstart'", 1);
}

function lockRecalculator() {
	// return false if already locked.  break lock if lock older than 2 minutes.
	// return true is lock has just been set
	$datetime =  fetchRow0Col0(
		"SELECT value 
		 FROM tbluserpref 
		 WHERE property = 'visitcountrecalcstart' AND userptr = '-999' LIMIT 1");
	if(!$datetime) $lockHerUp = 1;
	$datetime = strtotime($datetime);
	if(time() - $datetime  > 120) { // must have gotten hung up
		$lockHerUp = 1;
	}
	if($lockHerUp)
		replaceTable('tbluserpref', 
									array('userptr'=>'-999', 'property'=>'visitcountrecalcstart', 'value'=>date('Y-m-d H:i:s')),
									1);
	return $lockHerUp;
}

