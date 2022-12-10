<? // reports-count-providers.php 
require_once "common/init_session.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";
require_once "client-flag-fns.php";

$locked = locked('o-');
if(!$_SESSION['staffuser']) $errors[] = "You must be logged in as Staff to view this report.";
if($_SESSION['db'] != 'leashtimecustomers') $errors[] = "This report is available only in the the context of the LT Customers db.";
extract(extractVars('action,newbizdb,bizdb,pattern,date,showtest,detail,forward,fetchlastcharge,getlogins,byvisitcount', $_REQUEST));
if($getlogins) {
	require_once "common/init_db_common.php";
	$month = date('Y-m', strtotime($date));
	$sql =  "SELECT L.LoginID, COUNT( * ) as logins , U.rights
		FROM `tbllogin` L
		LEFT JOIN tbluser U ON U.LoginID = L.LoginID
		WHERE L.success =1
		and U.ltstaffuserid != 1
		AND `LastUpdateDate` LIKE '$month%'
		AND U.bizptr = $getlogins
		GROUP BY L.LoginID";
	$users = fetchAssociations($sql);
//if(mattOnlyTEST()) {echo "$sql<p>".print_r($users,1);exit;}
	$month = date('F Y', strtotime($date));
	if(!$users) echo "No logins during $month";
	else {
		echo "<table bordercolor=black border=1><tr><td colspan=2><b>Logins during $month</b><tr><td>Users<td>Logins";
		foreach($users as $u) {
			if($u['rights'][0] == 'o') echo "<tr><td>Manager {$u['LoginID']}<td>{$u['logins']}</td></tr>";
			else if($u['rights'][0] == 'c') $logins['clients'] += $u['logins'];
			else if($u['rights'][0] == 'd') $logins['dispatchers'] += $u['logins'];
			else if($u['rights'][0] == 'p') $logins['sitters'] += $u['logins'];
		}
		foreach((array)$logins as $type => $count)
			echo "<tr><td>$type<td>$count</td></tr>";
		echo "</table>";
	}
	exit;
}
$businessFlags = getBizFlagList();
$greyStar = $businessFlags[21]['src'];
$cautionSign = $businessFlags[1]['src'];

if($date) $date = date('Y-m-d', strtotime($date));

//$bizdb = $newbizdb;
require_once "common/init_db_petbiz.php";

// check for duplicate garagegatecodes
$duplicates = fetchKeyValuePairs("SELECT garagegatecode, count(*) FROM tblclient WHERE garagegatecode IS NOT NULL  group by garagegatecode");
foreach($duplicates as $ggc => $dup)
	if($dup == 1) unset($duplicates[$ggc]);
foreach($duplicates as $ggc => $dup)
	if($dup == 1) unset($duplicates[$ggc]);
if($duplicates) {
	foreach(array_unique(array_keys($duplicates)) as $ggc) {
		$dups = fetchAssociations("SELECT fname, lname, active FROM tblclient WHERE garagegatecode = $ggc ORDER BY fname, lname");
		foreach($dups as $i => $dup) {
			$active = $dup['active'] ? 'active' : "<font color=red>inactive</font>";
			$dups[$i] = "<li>($ggc) {$dup['fname']} {$dup['lname']} $active";
		}
	}
}


if($fetchlastcharge) {
	$date = fetchRow0Col0("SELECT issuedate FROM tblothercharge WHERE clientptr = '$fetchlastcharge' ORDER BY issuedate DESC LIMIT 1");
	echo "<span style='color:blue;font-weight:bold'>".date('n/j/Y', strtotime($date))."</span>";
	exit;
}

$clientsByPetBizId = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient");
$liveBizIds = fetchCol0("SELECT garagegatecode 
														FROM tblclient 
														WHERE clientid IN (SELECT clientptr 
																								FROM tblclientpref 
																								WHERE property LIKE 'flag_%' AND value like '2|%')");
$deadLeadIds = fetchCol0("SELECT garagegatecode 
														FROM tblclient 
														WHERE clientid IN (SELECT clientptr 
																								FROM tblclientpref 
																								WHERE property LIKE 'flag_%' AND value like '8|%')");
$formerCustomerIds = fetchCol0("SELECT garagegatecode 
														FROM tblclient 
														WHERE clientid IN (SELECT clientptr 
																								FROM tblclientpref 
																								WHERE property LIKE 'flag_%' AND value like '21|%')");
$trialCustomerIds = fetchCol0("SELECT garagegatecode 
														FROM tblclient 
														WHERE clientid IN (SELECT clientptr 
																								FROM tblclientpref 
																								WHERE property LIKE 'flag_%' AND value like '1|%')");
$lastInvoiceDates = fetchKeyValuePairs("SELECT garagegatecode, date
																				FROM tblinvoice
																				LEFT JOIN tblclient ON clientid = clientptr
																				ORDER BY date");
$lastCharges = fetchKeyValuePairs("SELECT garagegatecode, issuedate
																				FROM tblothercharge
																				LEFT JOIN tblclient ON clientid = clientptr
																				ORDER BY issuedate");
require_once "common/init_db_common.php";
$dbs = fetchKeyValuePairs("SELECT db, db FROM tblpetbiz ORDER BY db", 1);
$dbs = array_merge(array('All Active Businesses'=>''), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
if($detail && $date) {  // AJAX call
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$detail' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$rows = getProviderVisitCountForMonth($date);
//print_r($rows);	
	foreach($rows as $row) $total += $row['visits'];
	$rows = array_merge(array(array('name'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
	if($_SESSION["LTCustInvoiceSitterCountItemNote_$detail"]) unset($_SESSION["LTCustInvoiceSitterCount_$detail"]);
	if($rows) {
		$columns = explodePairsLine('name|Sitter||visits|Visits');
		$monthYear = date('F Y', strtotime($date));
		ob_start();
		ob_implicit_flush(0);
		echo "<p style='font-size:1.2em;padding-left:5px;'>Here are the sitters who made visits in $monthYear. Visit counts <u>exclude</u> canceled visits.</p>";
		tableFrom($columns, $rows, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses);
		$_SESSION["LTCustInvoiceSitterCountItemNote_$detail"] = ob_get_contents();
		// HAS NO EFFECT: session_write_close();
		ob_end_clean();
		echo "LTCustInvoiceSitterCountItemNote_$detail";  // return the name of variable to use as an itemnote
		//echo "<hr>{$_SESSION["LTCustInvoiceSitterCountItemNote_$detail"]}";
	}
	exit;
}
else if($bizdb && $date && $forward) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$bizdb' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$month = date('Y-m-01', strtotime($date));
	$thismonth = date('Y-m-01');
	$columns = array('name'=>'Sitter');
	for($month; strcmp($month, $thismonth) < 1; $month = date('Y-m-01', strtotime('+1 month', strtotime($month)))) {
		$columns[$month] = date('M Y', strtotime($month));
		$total = 0;
		$pvCount = getProviderVisitCountForMonth($month);
		// providerptr, visits, name, sortname, rate,  charge
		//if(!$pvCount) $pvCount = array('-1'=>explodePairsLine('providerptr|-1||visits|0||name|No Sitters||sortname|No Sitters||rate|0||charge|0'));
		$raw[$month] = $pvCount;
		foreach($raw[$month] as $row) {
			$total += $row['visits'];
			$allSitters[] = $row['name'];
		}
		$raw[$month]['total'] = $total.' ('.count($raw[$month]).')';
	}
	
	foreach($raw as $month => $col)
		$rows[0][$month] = $raw[$month]['total'];
		
	foreach(array_unique($allSitters) as $i => $sittername) {
		$thisRow = array('name' => $sittername);
		foreach($raw as $month => $col) {
			$numVisits = 0;
			foreach($raw[$month] as $sitter) {
//echo "{$sitter['name']} ($sittername)<br>";exit;
				
				if($sitter['name'] == $sittername) {
//screenLog(print_r($sitter,1)."<br>");
					$thisRow['sortname'] = $sitter['sortname'];
					$thisRow[$month] = $sitter['visits'] ? $sitter['visits'] : '--';
				}
			}
		}
		$rows[] = $thisRow;
//screenLog(print_r($thisRow,1)."<br>");
}
	
}
else if($bizdb && $date) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$bizdb' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$pvCount = getProviderVisitCountForMonth($date, $byvisitcount);
	// providerptr, visits, name, sortname, rate,  charge
	//if(!$pvCount) $pvCount = array('-1'=>explodePairsLine('providerptr|-1||visits|0||name|No Sitters||sortname|No Sitters||rate|0||charge|0'));
	$rows = $pvCount;
	$index = 0;
	foreach($rows as $i => $row) { 
		$index += 1;
		$rows[$i]['index'] = $index;
		$total += $row['visits'];
	}
	$rows = array_merge(array(array('name'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
}
else if($date) {
	$patternClause = $pattern ? "AND db LIKE '%$pattern%'" : "";
	$hideTest = $showtest ? '' : 'AND test = 0'; 
	$bizzes = fetchAssociations($sql = "SELECT * FROM tblpetbiz WHERE activebiz = 1 $hideTest $patternClause");
//echo "BANG! $sql";exit;	
	foreach($bizzes as $biz) $activeBizIds[] = $biz['bizid'];
	if(!($result = doQuery(
		"SELECT bizptr, loginid 
		FROM tbluser 
		WHERE active = 1 AND ltstaffuserid = 0 AND 
			(rights LIKE 'o-%' OR rights LIKE 'd-%')
			AND bizptr IN (".join(',', $activeBizIds).")"))) return null;
	$bizMgrs = array();
	while($row = mysql_fetch_array($result, MYSQL_ASSOC))
		$bizMgrs[$row['bizptr']][] = $row['loginid'];

	$total = '0';
	$active = 0;
	foreach($bizzes as $biz) {
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if(!in_array('tblappointment', fetchCol0("SHOW TABLES"))) continue;
		$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
		$sortName = $bizName ? $bizName : $biz['db'];
		$bizName = $bizName ? $bizName : "[{$biz['db']}]";
		$providers = getProvidersForMonth($date);
		$uncanceledVisits = getVisitCountForMonth($date);
		$allVisits = getTotalVisitCountForMonth($date);
		$unassignedVisits = getUnassignedVisitCountForMonth($date);
		$title = "Visits: $allVisits Unassigned: $unassignedVisits Canceled: ".($allVisits - $uncanceledVisits)." Not Canceled: $uncanceledVisits";
		$rate = figureRate($biz, count($providers));
		$zeroRate = zeroRate($biz);
		$providerCount = count($providers);
		$providers = "<a title= '$title' target='bizdetail' href='reports-count-providers.php?bizdb={$biz['db']}&date=$date'>$providerCount</a>";
		if($providerCount == 0) {
			$url = "reports-count-providers.php?date={$_REQUEST['date']}&getlogins={$biz['bizid']}";
			$providers .= " <img src='art/help.jpg' width=15 height=15 onclick='".
											"$.fn.colorbox({href:\"$url\", width:\"300\", height:\"400\", scrolling: true, iframe:true, opacity: \"0.3\"});'>";
		}
		
		
		
		$trialSuffix = $biz['freeuntil'] && strcmp($biz['freeuntil'], date('Y-m-d')) <= 0 ? 'ed' : 's';
		$paystatus = $biz['freeuntil'] == '1970-01-01' ? 'Free' : (
								 $biz['freeuntil'] ? "Trial end$trialSuffix ".date('n/j/Y', strtotime($biz['freeuntil'])) : (
								 $biz['rates'] ? 'paying'
								 : 'No rates set'));
		$dontCharge = $biz['freeuntil'] == '1970-01-01' 
									|| strcmp($biz['freeuntil'], date('Y-m-d')) >= 0
									|| !$biz['rates'] 
									|| in_array($biz['bizid'], $deadLeadIds)
									|| in_array($biz['bizid'], $formerCustomerIds);
		if(!nothingOwed($biz)) {
			$total += $rate;
			$active++;
		}
		$unconditionalTotal += $rate;
		$bizName = "<a title= '$title' target='bizdetail' href='reports-count-providers.php?bizdb={$biz['db']}&date=$date'>$bizName</a>";
		
		$chargeLink = $clientsByPetBizId[$biz['bizid']];
//if($biz['bizid']==50)	{echo "{$biz['bizid']}: [[$chargeLink]]";	print_r($clientsByPetBizId);}
		if($chargeLink && $rate && !$dontCharge) {
			$chargeLink = fauxLink('Charge', "fetchItemNoteForBiz(\"{$biz['db']}\",$rate, \"$chargeLink\")  ", 1);
		}
		else $chargeLink = '';
		
		$lid = $lastInvoiceDates[$biz['bizid']];
		$lid = $lid ? date('n/j/Y', strtotime($lid)) : '';
		
		$chargeDate = $lastCharges[$biz['bizid']];
		$chargeDate = $chargeDate ? date('n/j/Y', strtotime($chargeDate)) : '';
		$chargeDate = "<span id='lastcharge_{$clientsByPetBizId[$biz['bizid']]}'>$chargeDate</span>";
		
		$liveMark = in_array($biz['bizid'], $liveBizIds) ? "<img src='art/flag-yellow-star.jpg' height=16>" : '';
		$deadMark = in_array($biz['bizid'], $deadLeadIds) ? "<img src='art/flag-parking-no.jpg' height=16>" : (
								in_array($biz['bizid'], $formerCustomerIds) ? "<img src='$greyStar' height=16>" : (
								in_array($biz['bizid'], $trialCustomerIds) ? "<img src='$cautionSign' height=16>" :
									''));
									
		$flagOrderLegend = explodePairsLine('1|goldstar||2|noflag||3|trial||4|deadlead||5|formercustomer');
		$flagOrder = 
			$liveMark ? 1 : (
			in_array($biz['bizid'], $deadLeadIds) ? 4 : (
			in_array($biz['bizid'], $formerCustomerIds) ? 5 : (
			in_array($biz['bizid'], $trialCustomerIds) ? 3 :
				2)));
			
		$goldStars += $liveMark ? 1 : 0;
		if($liveMark) $goldStarTotal +=  $rate;
		$rows[] = array('name'=>$sortName, 'sitters'=>$providers, 'label'=>"<span title='{$biz['db']}'>({$biz['bizid']}) $bizName</span>",
										'paystatus'=>$paystatus,'rate'=>$rate, 'freeuntil'=>$biz['freeuntil'], 'chargelink'=>$chargeLink,
										'goldstar'=>$liveMark,
										'flagOrder'=>$flagOrder,
										'livemark'=>($liveMark ? $liveMark : $deadMark), 
										'lastinvoicedate'=>$lid, 
										'lastchargedate'=>$chargeDate, 
										'bizid'=>$biz['bizid'],
										'zeroRate'=>$zeroRate);
	}
	$bizName = null;
	usort($rows, 'sortByFlagOrder');
	foreach($rows as $i => $row) {
		$rows[$i]['name'] = $row['label'];
		$rowClasses[] = nothingOwed($row) ? 'futuretaskEVEN' : 'futuretask';
	}
	$stats = "$active active customers.  $goldStars gold stars.  ".count($rows)." total.";
	$rateSummary = "<u title='Column Total: \$$unconditionalTotal - blue lines = \$$total'>$goldStarTotal</u>";
	$rows = array_merge(array(array('name'=>$stats, 'paystatus'=>'Total', 'rate'=>$rateSummary)), $rows);
	$rowClasses = array_merge(array('futuretask'), $rowClasses);
}

function sortByNameAndStar($a, $b) {
	if($a['goldstar'] && !$b['goldstar']) return -1;
	else if(!$a['goldstar'] && $b['goldstar'])  return 1;
	else return strcmp(strtoupper($a['name']), strtoupper($b['name']));
}

function sortByFlagOrder($a, $b) {
	if($a['flagOrder']< $b['flagOrder']) return -1;
	else if($a['flagOrder'] > $b['flagOrder'])  return 1;
	else return strcmp(strtoupper($a['name']), strtoupper($b['name']));
}

function nothingOwed($biz) {
	if($biz['freeuntil'] == '1970-01-01') return true;
	else if($biz['freeuntil']) {
		if(strcmp(date('Y-m-d'), $biz['freeuntil']) < 0)
			return true;
	}
}

function zeroRate($biz) {
	$rates = explode(',',$biz['rates']);
	$ratesMap = array();
	foreach($rates as $rate) {
		$rate = array_map('trim',explode('=',$rate));
		$ratesMap[$rate[0]] = $rate[1];
	}
	return $ratesMap[0];

}
function figureRate($biz, $numsitters) {
	$rates = explode(',',$biz['rates']);
	$ratesMap = array();
	foreach($rates as $rate) {
		$rate = array_map('trim',explode('=',$rate));
		$ratesMap[$rate[0]] = $rate[1];
	}
	if(!$numsitters && !$ratesMap[0]) return 0;
	
	foreach($rates as $rate) {
		$rate = array_map('trim',explode('=',$rate));
		if($numsitters <= $rate[0]) return $rate[1];
	}
	return $rate[1];
}

function creditsTable($start, $bizdb) {
	include "common/init_db_common.php";
	$leashtime = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$bizdb' LIMIT 1");
	reconnectPetBizDB($leashtime['db'], $leashtime['dbhost'], $leashtime['dbuser'], $leashtime['dbpass']);
	$clientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE garagegatecode = {$biz['bizid']} LIMIT 1");
	$rows = fetchAssociations(
		"SELECT issuedate, reason, amount, creditid FROM tblcredit 
			WHERE clientptr = $clientid 
				AND issuedate >= '$start' 
			ORDER BY issuedate, creditid");
	foreach($rows as $i => $row) {
		$rows[$i]['issuedate'] = date('m/d/Y', strtotime($rows[$i]['issuedate']));
		//$rows[$i]['creditid'] = "[{$rows[$i]['creditid']}]";
	}
	echo "<h2>Credits and Payments</h2>";
	$columns = explodePairsLine('issuedate|Date||creditid|ID||amount|Amount||reason|Note');
	tableFrom($columns, $rows, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses);
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

function dumpCSVContent($date, $columns, $rows, $bizdb, $bizMgrs) {
	global $flagOrderLegend;
	dumpCSVRow("Sitter Counts: ".date('F Y', strtotime($date)));
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow($columns);
	foreach($rows as $row) {
//echo print_r($row, 1)."\n";
		$row['livemark'] = $flagOrderLegend[$row['flagOrder']];
		if($bizdb) $row['net'] = $row['charge'] - $row['rate'] ;
		$row['numMgrs'] = count($bizMgrs[$row['bizid']]) ? count($bizMgrs[$row['bizid']]) : '';
		if($bizMgrs[$row['bizid']]) $row['managers'] = join(', ', $bizMgrs[$row['bizid']]);
		dumpCSVRow(array_map('strip_tags', $row), array_keys($columns));
	}
}

if($_REQUEST['csv']) {
//if(mattOnlyTEST()) {print_r($rows); exit;	}
	if($bizdb) $columns = explodePairsLine("name|Sitter||visits|Visits||charge|Total Rev||rate|Pay||net|Net to Business");
	else $columns = explodePairsLine("name|Business||sitters|Sitters||paystatus|Paying||rate|Fee||chargelink|Charge||livemark| ||lastinvoicedate| Last Inv.||lastchargedate|Last Charge||zeroRate|Zero Rate||numMgrs|Managers/Dispatchers||managers| ");
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Sitter-count.csv ");
	/*dumpCSVRow("Sitter Counts: ".date('F Y', strtotime($date)));
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow($columns);
	foreach($rows as $row) {
//echo print_r($row, 1)."\n";
		if($bizdb) $row['net'] = $row['charge'] - $row['rate'] ;
		$row['numMgrs'] = count($bizMgrs[$row['bizid']]) ? count($bizMgrs[$row['bizid']]) : '';
		if($bizMgrs[$row['bizid']]) $row['managers'] = join(', ', $bizMgrs[$row['bizid']]);
		dumpCSVRow(array_map('strip_tags', $row), array_keys($columns));
	}*/
	dumpCSVContent($date, $columns, $rows, $bizdb, $bizMgrs);
	exit;
}

$pageTitle = 'Business Tiers Report - Sitter Count';
$breadcrumbs = "<a href='reports.php'>Reports</a>";	
if($bizdb) {
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$bizName = $bizName ? $bizName : "[$db}]";
	$pageTitle = "<h2>Business Tiers Report: Sitters with Visits in ".date('F Y', strtotime($date))."</h2>";
	$breadcrumbs .=  " - <a href='reports-count-providers.php?&date=$date'>Back to All Businesses</a><p>";
}
else if($date) $pageTitle =  "<h2>Business Tiers: Sitters with Visits in ".date('F Y', strtotime($date))."</h2>";
// ******************************************************
include 'frame.html';

if($errors) {
	echo "<ul><li>".join('<li>', $errors)."</ul>";
	require_once "frame-end.html";
	exit;
}
?>
<table width=97%>
<tr>
<td class='fontSize1_2em bold'>
<? fauxLink("Review Missing and Duplicate Misc Charges",
							"openConsoleWindow(\"viewmisccharges\", \"reports-missing-miscs.php\", 700, 500);", 0, 
							"Identify clients who SHOULD have been charged but were not, and who was charged more than once");
?>
</td>
<?
if($dups) {
	echo "<td style='color:red'>The following businesses refer to the same garagegate codes<br><ul>";
	echo join("\n", $dups);
	echo "</ul></td>";
}
?>
</tr>
</table>
<?


if($bizName) echo "<h2>$bizName</h2>";

?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
$mdyDate = $date ? date('m/d/Y', strtotime($date)) : date('m/d/Y');
selectElement('Business:', 'bizdb', $bizdb, $dbs);
hiddenElement('bizdb', $bizdb);
hiddenElement('pattern', $pattern);
//echo " Date: <input id='date' name='date' value='$mdyDate'>";
calendarSet('Date', 'date', $mdyDate);

if($msg) echo "<p style='color:darkgreen'>$msg</p>";
if($error) echo "<p style='color:red'>$error</p>";
echoButton('', 'Show', 'show()');
echo "&nbsp;";
echoButton('', 'Download Spreadsheet', "genCSV()");
if($bizdb) {
	hiddenElement('showtest', $showtest);
	echo ' ';
	echoButton('', 'Show From Date Forward', 'show("forward")');

}
else labeledCheckbox(' show test databases', 'showtest', $showtest);
if($rows) {
	if($bizdb) {
		$columns = $columns ? $columns : explodePairsLine('name|Sitter||visits|Visits');
		echo "<p style='font-size:1.2em;padding-left:5px;'>Visit counts <u>exclude</u> canceled visits.</p>";
	}
	else {
		$columns = explodePairsLine("name|Business||sitters|Sitters||paystatus|Paying||rate|Fee||chargelink|Charge||livemark| ||lastinvoicedate| Last Inv.||lastchargedate|Last Charge");
	}
	
	// dump a "temporary" file
	ob_start();
	ob_implicit_flush(0);
	if($bizdb) $csvcolumns = explodePairsLine("name|Sitter||visits|Visits||charge|Total Rev||rate|Pay||net|Net to Business");
	else $csvcolumns = explodePairsLine("name|Business||sitters|Sitters||paystatus|Paying||rate|Fee||chargelink|Charge||livemark| ||lastinvoicedate| Last Inv.||lastchargedate|Last Charge||numMgrs|Managers/Dispatchers||managers| ");
	dumpCSVContent($date, $csvcolumns, $rows, $bizdb, $bizMgrs);
	$csvoutput = ob_get_contents();
	ob_end_clean();
	file_put_contents ($spreadsheetURL="output/sittercounts.csv", $csvoutput);
	
	echo "<br><a href='$spreadsheetURL'>Open Spreadsheet for data shown &#x25BC;</a>";
	
	if($bizdb && !$forwared) {
		$newCols = array('index'=>'');
		foreach($columns as $k => $v) $newCols[$k] = $v;
		$columns = $newCols;
		if($byvisitcount) echoButton('', 'By Sitter Name', 'show()');
		else echoButton('', 'By Visit Count', 'showByVisitCount()');
	}
	
	
	tableFrom($columns, $rows, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses);
	
	if($bizdb && $forward) creditsTable($start, $bizdb);

}
?>
<p><img src='art/spacer.gif' height=250 width=1>
<script language='javascript' src='common.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>
function fetchItemNoteForBiz(bizdb,amount,client) {
	var date = '<?= date('Y-m-t', strtotime($date)) ?>';
	var args = new Array(bizdb,amount,client);
	ajaxGetAndCallWith('reports-count-providers.php?detail='+bizdb+'&date='+date, createAMiscCharge, args);
}
function createAMiscCharge(args, response) {
	var issuedate = '<?= date('Y-m-t', strtotime('-1 month', strtotime(date('Y-m-1', strtotime($date))))) ?>';
	var reason = escape('LeashTime Service for <?= date('F Y', strtotime($date)) ?>');
	openConsoleWindow('editcharge', 'charge-edit.php?client='+args[2]+'&amount='+args[1]
											+'&reason='+reason+'&issuedate='+issuedate+'&itemnotesessionvar='+escape(response), 700, 500);
}

function show(forward) {
	if(!MM_validateForm('date', '', 'R', 'date', '', 'isDate')) return;
	var bizdb = document.getElementById("bizdb").value;
	var showtest = document.getElementById("showtest").checked ? 1 : 0;
	var pattern = document.getElementById("pattern").value;
	var date = escape(date = document.getElementById("date").value);
	var forward = forward ? '&forward=1' : '';
	document.location.href="reports-count-providers.php?date="+date+"&bizdb="+bizdb+"&showtest="+showtest+forward+"&pattern="+pattern;
}

function showByVisitCount() {
	if(!MM_validateForm('date', '', 'R', 'date', '', 'isDate')) return;
	var bizdb = document.getElementById("bizdb").value;
	var showtest = document.getElementById("showtest").checked ? 1 : 0;
	var date = escape(date = document.getElementById("date").value);
	document.location.href="reports-count-providers.php?date="+date+"&bizdb="+bizdb+"&showtest="+showtest+"&byvisitcount=1";
}

function genCSV(forward) {
	if(!MM_validateForm('date', '', 'R', 'date', '', 'isDate')) return;
	var bizdb = document.getElementById("bizdb").value;
	var showtest = document.getElementById("showtest").checked ? 1 : 0;
	var date = escape(date = document.getElementById("date").value);
	var forward = forward ? '&forward=1' : '';
	document.location.href="reports-count-providers.php?csv=1&date="+date+"&bizdb="+bizdb+"&showtest="+showtest+forward;
}

function update(unused, unused2, clientid) {
//alert("reports-count-providers.php?fetchlastcharge="+clientid, 'lastcharge_'+clientid);	
	ajaxGet("reports-count-providers.php?fetchlastcharge="+clientid, 'lastcharge_'+clientid);
}
<? dumpPopCalendarJS(); ?>
</script>

<?
require_once "frame-end.html";
