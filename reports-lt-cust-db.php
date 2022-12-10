<? // reports-lt-cust-db.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "field-utils.php";
require_once "provider-fns.php";
require_once "preference-fns.php";
require_once "client-fns.php";
require_once "client-flag-fns.php";

// Determine access privs
$locked = locked('o-');
if(dbTEST('leashtimecustomers')) {
	$customers = fetchAssociations("SELECT fname, lname, garagegatecode, active, clientid FROM tblclient ORDER BY fname", 1);
	$counts =  fetchKeyValuePairs("SELECT garagegatecode, count(*) FROM tblclient WHERE garagegatecode IS NOT NULL GROUP BY garagegatecode");
	foreach($counts as $gatecode => $count) {
		if($count == 1) continue;
		$dups[$gatecode] = fetchCol0("SELECT fname FROM tblclient WHERE garagegatecode = $gatecode");
		$dups[$gatecode] = join(', ', $dups[$gatecode]);
	}
		
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require_once "common/init_db_common.php";
	$bizzes = fetchAssociationsKeyedBy("SELECT * FROM tblpetbiz", 'bizid');
	$orphans = fetchAssociations("SELECT bizid, bizname, db FROM tblpetbiz WHERE test = 0 AND activebiz=1 AND bizid != 68 AND bizid NOT IN ("
																.join(',', array_keys($counts)).")", 1);
																
	foreach($customers as $i => $cust) {
		$gatecode = $cust['garagegatecode'];
		$title = "Client ID: {$cust['clientid']}";
		if(!$cust['active']) $title .= " is inactive.";
		$customers[$i]['fname'] = "<span title='$title'>{$cust['fname']}</span>";
		
		if($bizzes[$gatecode]['lockout']) {
			$lockout = date('m/d/Y', strtotime($bizzes[$gatecode]['lockout']));
			if(strcmp($today, $bizzes[$gatecode]['lockout']) == -1) {$lockoutColor = 'yellow'; $lockoutTitle = "Lock out set for: $lockout";}
			else {$lockoutColor = 'red'; $lockoutTitle = "Locked out since: $lockout";}
			$customers[$i]['fname'] =  "<img src='art/lockout-$lockoutColor.gif' title='$lockoutTitle'> ".$customers[$i]['fname'];
		}
		
		
		
		
		
		
		$customers[$i]['bizname'] = $bizzes[$gatecode]['bizname'];
		if($bizzes[$gatecode]['test']) $customers[$i]['bizname'] = "[T] {$customers[$i]['bizname']}";
		if(!$gatecode) $customers[$i]['bizname'] = "";
		else if(!$bizzes[$gatecode]) $customers[$i]['bizname'] = "<span  style='color:red'>BIZ $gatecode not found</span>";
		else if(!$bizzes[$gatecode]['activebiz']) {
			$title = safeValue($customers[$i]['bizname'])." database {$bizzes[$gatecode]['db']} [{$bizzes[$gatecode]['bizid']}] is inactive.";
			$customers[$i]['bizname'] = "<span style='color:red' title='$title'>{$customers[$i]['bizname']}</span>";
		}
		else  {
			$title = safeValue("database: {$bizzes[$gatecode]['db']}.");
			$customers[$i]['bizname'] = "<span title='$title'>{$customers[$i]['bizname']}</span>";
		}
		$owners = !$gatecode ? array() : fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM tbluser WHERE bizptr = $gatecode AND rights LIKE 'o-%' AND isowner=1");
		$customers[$i]['owners'] = join(', ', $owners);
		$class = $customers[$i]['active'] ? 'futuretask' : 'canceledtask';
		if($i % 2 == 0) $class .= 'EVEN';
		$rowClasses[] = $class;
	}
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	foreach($customers as $i => $cust) {
		$customers[$i]['fname'] .= clientFlagPanel($cust['clientid'], $officeOnly=false, $noEdit=true, $contentsOnly=false, $onClick=null, $includeBillingFlags=false);
	}

}


$pageTitle = "Customer Databases";
$breadcrumbs = "<a href='reports.php'>Reports</a>";	

include "frame.html";
if(!dbTEST('leashtimecustomers')) {
	echo "Insufficient access rights.  Code: DB";
	exit;
}
if($dups) {
	echo "<span style='color:red'>Duplicate garagegatecode:<ul>";
	foreach($dups as $gatecode => $bizzes) echo "<li>[$gatecode] $bizzes";
	echo "</ul></span><hr>";
}
if($orphans) {
	echo "<span style='color:red'>Orphan DBs:<ul>";
	foreach($orphans as $orphan) echo "<li>[{$orphan['bizid']}] {$orphan['bizname']} ({$orphan['db']})";
	echo "</ul></span><hr>";
}
?><b>Legend:</b> <span class='canceledtask'>Inactive Client</span>  <span style='color:red'>Inactive or missing business db</span><?

//print_r($customers);
$columns = explodePairsLine('fname|Cust Biz Name||lname|Cust Name||garagegatecode|Biz ID||bizname|DB Biz Name||owners|Owners');
tableFrom($columns, $customers, $attributes=null, $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses=null, $sortClickAction=null);
include "frame-end.html";