<? // maint-count-providers.php 
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "gui-fns.php";

$locked = locked('z-');
extract(extractVars('action,newbizdb,bizdb,date', $_REQUEST));

if($date) $date = date('Y-m-d', strtotime($date));

//$bizdb = $newbizdb;
$ltBiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1");
reconnectPetBizDB($ltBiz['db'], $ltBiz['dbhost'], $ltBiz['dbuser'], $ltBiz['dbpass'], 1);
// garagecodes from LT customers
$leashTimeCustomerIds = fetchKeyValuePairs("SELECT garagegatecode, clientid FROM tblclient"); 
// garagecodes of apse members
//$apseMembers = fetchAssociations("SELECT garagegatecode FROM tblclient");
$liveCustomers = fetchKeyValuePairs("SELECT garagegatecode, clientid 
																			FROM tblclientpref 
																			LEFT JOIN tblclient ON clientid = clientptr
																			WHERE property = 'flag_2'");
$invoicedCustomers = fetchKeyValuePairs("SELECT garagegatecode, clientid 
																			FROM tblinvoice 
																			LEFT JOIN tblclient ON clientid = clientptr");
require "common/init_db_common.php";


$dbs = fetchKeyValuePairs("SELECT db, db FROM tblpetbiz ORDER BY db");
$dbs = array_merge(array('All Active Businesses'=>''), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
if($bizdb && $date) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = '$bizdb' LIMIT 1");
	reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
	$rows = getProviderVisitCountForMonth($date);
	foreach($rows as $i => $row) {
		$rows[$i]['charge'] = round($row['charge']);
		$rows[$i]['rate'] = round($row['rate']);
		$rows[$i]['net'] = round($row['charge']) - round($row['rate']);
		$total += $row['visits'];
	}
	$rows = array_merge(array(array('name'=>'<b>Total ('.count($rows).')</b>', 'visits'=>$total)), $rows);
}
else if($date) {
	$bizzes = fetchAssociations("SELECT * FROM tblpetbiz WHERE activebiz = 1");
	$total = '0';
	foreach($bizzes as $biz) {
		reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);
		if(!in_array('tblappointment', fetchCol0("SHOW TABLES"))) continue;
		$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
		$bizName = $bizName ? $bizName : "[{$biz['db']}]";
		
		
		$providers = getProvidersForMonth($date);
		$uncanceledVisits = getVisitCountForMonth($date);
		$allVisits = getTotalVisitCountForMonth($date);
		$unassignedVisits = getUnassignedVisitCountForMonth($date);
		$title = "Visits: $allVisits Unassigned: $unassignedVisits Canceled: ".($allVisits - $uncanceledVisits)." Not Canceled: $uncanceledVisits";
		$rate = figureRate($biz, count($providers));
		$providers = "<a title= '$title' href='maint-count-providers.php?bizdb={$biz['db']}&date=$date'>".count($providers)."</a>";
		$trialSuffix = $biz['freeuntil'] && strcmp($biz['freeuntil'], date('Y-m-d')) <= 0 ? 'ed' : 's';
		$paystatus = $biz['freeuntil'] == '1970-01-01' ? 'Free' : (
								$biz['freeuntil'] ? "Trial end$trialSuffix ".date('n/j/Y', strtotime($biz['freeuntil'])) : (
								$liveCustomers[$biz['bizid']] ? 'paying' : (
								$invoicedCustomers[$biz['bizid']] ? 'paying' : 	'trial')));
		$total += $rate;
		$unconditionalTotal += $rate;
		$warningTitle = !$leashTimeCustomerIds[$biz['bizid']] ? " - NO LeashTime Customers Entry" : '';
		$warningStyle = !$leashTimeCustomerIds[$biz['bizid']] ? "style='color:red'" : '';
		$testFlag = $biz['test'] ? ' [test]' : '';
		$rows[] = array('name'=>$bizName, 'sitters'=>$providers, 
											'label'=>"<span $warningStyle title='{$biz['db']}$warningTitle'>({$biz['bizid']}) $bizName$testFlag</span>",
										'paystatus'=>$paystatus,'rate'=>$rate);
	}
	usort($rows, 'sortByName');
	foreach($rows as $i => $row) {
		$rows[$i]['name'] = $row['label'];
		$rowClasses[] = 
			$row['paystatus'] == 'Free' ? 'futuretaskEVEN' : (
			$row['paystatus'] == 'paying' ? 'futuretask' : (
			strpos($row['paystatus'], 'ended') ? 'futuretask' : 'futuretaskEVEN'));
	}
	$rows = array_merge(array(array('paystatus'=>'Total', 'rate'=>"<u title='Unconditioal total: \$$unconditionalTotal'>$total</u>")), $rows);
	$rowClasses = array_merge(array('futuretask'), $rowClasses);
}

function sortByName($a, $b) {
	return strcmp($a['name'], $b['name']);
}

function 	figureRate($biz, $numsitters) {
	if(!$numsitters || $biz['freeuntil'] == '1970-01-01') return 0;
	else if($biz['freeuntil']) {
		if(strcmp(date('Y-m-d'), $biz['freeuntil']) < 0)
			return 0;
	}
	$rates = explode(',',$biz['rates']);
	foreach($rates as $rate) {
		$rate = array_map('trim',explode('=',$rate));
		if($numsitters <= $rate[0]) return $rate[1];
	}
	return $rate[1];
}


$windowTitle = 'Business Tiers - Sitter Count';
include 'frame-maintenance.php';


if($bizdb) {
	$bizName = fetchRow0Col0("SELECT value FROM tblpreference WHERE property = 'bizName' LIMIT 1");
	$bizName = $bizName ? $bizName : "[$db}]";
	echo "<h2>$bizName</h2>";
	echo "<h2>Business Tier: Sitters with Visits in ".date('F Y', strtotime($date))."</h2>";
	echo "<a href='maint-count-providers.php?&date=$date'>Back to All Businesses</a><p>";
}
else if($date) echo "<h2>Business Tiers: Sitters with Visits in ".date('F Y', strtotime($date))."</h2>";
else echo "<h2>Sitters with Visits</h2>";

?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
$mdyDate = $date ? date('m/d/Y', strtotime($date)) : date('m/d/Y');
selectElement('Business:', 'bizdb', $bizdb, $dbs);
hiddenElement('bizdb', $bizdb);
echo " Date: <input id='date' name='date' value='$mdyDate'>";

if($msg) echo "<p style='color:darkgreen'>$msg</p>";
if($error) echo "<p style='color:red'>$error</p>";
echoButton('', 'Show', 'show()');

if($rows) {
	if($bizdb) {
		$columns = explodePairsLine('name|Sitter||visits|Visits||charge|Revenue||rate|Pay||net|Net');
		echo "<p style='font-size:1.2em;padding-left:5px;'>Visit counts <u>exclude</u> canceled visits.</p>";
	}
	else $columns = explodePairsLine('name|Business||sitters|Sitters||paystatus|Paying||rate|Fee');
	tableFrom($columns, $rows, 'border=1 bordercolor=darkgrey style="margin-left:5px; "', null, null, null, null, null, $rowClasses);
}
?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function show() {
	if(!MM_validateForm('date', '', 'R', 'date', '', 'isDate')) return;
	var bizdb = document.getElementById("bizdb").value;
	var date = escape(date = document.getElementById("date").value);
	document.location.href="maint-count-providers.php?date="+date+"&bizdb="+bizdb;
}
</script>