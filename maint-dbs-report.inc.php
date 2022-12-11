<? // maint-dbs-report.inc.php
// use this script by hand to report/modify all LT biz databases

// see maint-dbs-report-visitphotos.php

set_time_limit(10 * 60);

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";


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
	if(mattOnlyTEST() && $val && is_array($val)) $val = print_r($val, 1);
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}




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
	
	// #########################################################################################################
	
	processBusiness();

}
if(function_exists('postProcess')) { postProcess(); }
