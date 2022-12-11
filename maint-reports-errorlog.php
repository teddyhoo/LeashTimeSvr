<? // maint-reports-errorlog.php
// use this script by hand to modify all LT biz databases
set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";


// exit;



$locked = locked('z-');
$scriptStart = microtime(1);
$databases = fetchCol0("SHOW DATABASES");
$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz ", 'db'); // WHERE activebiz=1
foreach($bizzes as $biz) {
	if($biz['db'] == 'leashtimecustomers') $ltBiz = $biz;
	else $allBizzesLeashTimeFirst[] = $biz;
}

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
// END GOLD STARS



function cmpDb($a, $b) {return strcmp($a['db'], $b['db']);}

usort($allBizzesLeashTimeFirst, 'cmpDb');

$allBizzesLeashTimeFirst = array_merge(array('leashtimecustomers'=>$ltBiz), $allBizzesLeashTimeFirst);

if($_GET['inactiveclearerror'] || !$_GET['list']) {
	echo "There are ".count($allBizzesLeashTimeFirst)." businesses.<p>";
	echo "<a href='maint-reports-errorlog.php?list=1'>List their error and message counts</a><p>";
	echo "<a href='maint-reports-errorlog.php?list=1&inactiveclearerror=1'>Clear errors in all inactive databases</a><p>";
	if(!$_GET['inactiveclearerror']) exit;
}

//header("Content-Type: text/csv");
if(!$_GET['inactiveclearerror']) {
	header("Content-Type: application/msexcel");
	header("Content-Disposition: inline;filename=errorlogs.csv;");
}


foreach($allBizzesLeashTimeFirst as $bizCount => $biz) {
	//echo "<font color=gray>$bizCount / ".(count($allBizzesLeashTimeFirst)-2)."</font><br>";
	if($bizCount == count($allBizzesLeashTimeFirst)-2) $lastBiz = true;  // why "2"?
	if(!in_array($biz['db'], $databases)) {
		//echo "<br><font color=gray>DB: {$biz['db']} not found.<br></font>";
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
	if(TRUE) {
		
		$sql = 
		"SELECT SUM((data_length+index_length)/power(1024,1)) as dbsize
			FROM information_schema.tables
			WHERE table_schema='$db';";
		$allsizeKB = fetchRow0Col0($sql);
		$dbSizeInMB = round($allsizeKB/1024);

		
		
		
		//if(!$biz['activebiz']) continue;
		$sql = 
		"SELECT (data_length+index_length)/power(1024,1) tablesize
			FROM information_schema.tables
			WHERE table_schema='$db' and table_name='#TAB#';";
		$errorcount = fetchRow0Col0("SELECT count(*) FROM tblerrorlog");
		$errorsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblerrorlog', $sql));
		/*$msgcount = fetchRow0Col0("SELECT count(*) FROM tblmessage");
		$msgsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblmessage', $sql));
		
		$msgsbefore = date('Y-m-d', strtotime("-540 days"));
		$msgcountbefore = fetchRow0Col0("SELECT count(*) FROM tblmessage WHERE datetime < '$msgsbefore 00:00:00'");
				
		*/
		$newErrorsStart = '2017-01-01 00:00:00';
		$newErrors = fetchRow0Col0("SELECT count(*) FROM tblerrorlog WHERE time < '$newErrorsStart'");
		
		if($_GET['inactiveclearerror'] && !$biz['activebiz']) {
			doQuery("TRUNCATE tblerrorlog");
			$bizName = str_replace(',', '', $bizName);
			echo "Dropped errors from \"$bizName\": $errorsizeKB,$errorcount<br>";
		}

		
		if(0 && /*$errorsizeKB > 5000*/!$biz['activebiz']) {
			//doQuery("DELETE FROM tblerrorlog WHERE time < '$newMessagesStart'");
			doQuery("DELETE FROM tblerrorlog");			
			$dropped = mysqli_affected_rows();
			$dropped = ",(dropped $dropped of $errorcount rows)";
			doQuery(" OPTIMIZE TABLE `tblerrorlog`");
			$errorsizeKB = fetchRow0Col0(str_replace('#TAB#', 'tblerrorlog', $sql));
		}
		else $dropped = '';
		$status = $biz['activebiz'] ? 'active' : '[inactive]';
		$bizName = str_replace(',', '', $bizName);
		$arch = fetchKeyValuePairs("SELECT property, value FROM tblpreference WHERE property IN ('enableMessageArchiveFeature', 'enableMessageArchiveCron')");
		$arch = ($arch['enableMessageArchiveFeature'] ? 'y/' : 'n/').($arch['enableMessageArchiveCron'] ? 'y' : 'n');
		if(!$_GET['inactiveclearerror']) {
			if(!$started) {echo "bizName,status,arch,db,dbSizeInMB,msgSizeKB,msgcount,msgs before $msgsbefore,errorsizeKB,errorcount,errrors since $newErrorsStart\n";$started=1;}
			echo "\"$bizName\",$status,$arch,$db,$dbSizeInMB,$msgsizeKB,$msgcount,$msgcountbefore,$errorsizeKB,$errorcount,$newErrors$dropped\n";
		}
	}
}