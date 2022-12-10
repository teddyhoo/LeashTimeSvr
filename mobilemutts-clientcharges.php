<? // mobilemutts-clientcharges.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-flag-fns.php";
require_once "client-fns.php";

locked('o-');

echo "Hamstrung";
exit;



$eightFlag = $db == 'mobilemutts' ? 21 : 21;
$ballFlag = $db == 'mobilemutts' ? 23 : 23;
$dollarFlag = $db == 'mobilemutts' ? 20 : 20;

if($_GET['go']) {
	$candidates = $_GET['test'] ? array(getClient($_GET['test'])) : getDoomedFlagCandidates();
	foreach($candidates as $client) {
		dropClientFlag($client['clientid'], $eightFlag);
		dropClientFlag($client['clientid'], $ballFlag);
		dropClientFlag($client['clientid'], $dollarFlag);
	}
}



$bizname = $db == 'mobilemutts' ? 'Mobile Mutts <font color=green>South</font>' : 'Mobile Mutts <font color=red>North</font>';
?>
<h2><?= $bizname ?> "8" Flag Clients with ball, 8, or dollar flags</h2>
<?

$candidates = getDoomedFlagCandidates();
if(!$candidates) echo "None found.";
else echo count($candidates)." found.<p>";
foreach($candidates as $client) 
	echo "<a href='client-edit.php?tab=services&id={$client['clientid']}' target='groupwalk'>{$client['fname']} {$client['lname']}</a>"
				.clientFlagPanel($client['clientid'], $officeOnly=false, $noEdit=true, $contentsOnly=true, $onClick=null, $includeBillingFlags=false)
				."<br>";


function getDoomedFlagCandidates() {
	global $db, $eightFlag, $dollarFlag, $ballFlag;
	
	$eightballs = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property LIKE 'flag_%' AND value LIKE '$eightFlag|%'");
	$balls = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property LIKE 'flag_%' AND value LIKE '$ballFlag|%'");
	$dollars = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property LIKE 'flag_%' AND value LIKE '$dollarFlag|%'");
	
	
	if($eightballs) $flagFilter[] = "clientid IN (".join(',', $eightballs).")";
	if($balls) $flagFilter[] = "clientid IN (".join(',', $balls).")";
	if($dollars) $flagFilter[] = "clientid IN (".join(',', $dollars).")";

	if(!$flagFilter) return array();
	$clients = fetchAssociationsKeyedBy($sql = 
		"SELECT * FROM tblclient 
			WHERE ".join(' OR ', $flagFilter)."
				ORDER BY lname, fname", 'clientid');
//echo $sql;
	return $clients;
}



if($db != 'mobilemutts' && $db != 'mobilemuttsnorth') {
	echo "WRONG DATABASE: ($db)";
	exit;
}
$eightFlag = $db == 'mobilemutts' ? 21 : 21;
$starFlag = $db == 'mobilemutts' ? 22 : 22;

if($_GET['go']) {
	$candidates = $_GET['test'] ? array(getClient($_GET['test'])) : getDrop8Candidates();
	foreach($candidates as $client) {
		deleteTable('relclientcharge', "clientptr = {$client['clientid']}", 1);
		dropClientFlag($client['clientid'], $eightFlag);
		addClientFlag($client['clientid'], $starFlag, $note=' this flag added by LT '.date('m/d/Y'));
	}
}



$bizname = $db == 'mobilemutts' ? 'Mobile Mutts <font color=green>South</font>' : 'Mobile Mutts <font color=red>North</font>';
?>
<h2><?= $bizname ?> "8" Flag Clients with custom rates and with no visits after 10/31</h2>
<?

$candidates = getDrop8Candidates();
if(!$candidates) echo "None found.";
else echo count($candidates)." found.<p>";
foreach($candidates as $client) 
	echo "<a href='client-edit.php?tab=services&id={$client['clientid']}' target='groupwalk'>{$client['fname']} {$client['lname']}</a>"
				.clientFlagPanel($client['clientid'], $officeOnly=false, $noEdit=true, $contentsOnly=true, $onClick=null, $includeBillingFlags=false)
				."<br>";

function getDrop8Candidates() {
	global $db, $eightFlag;
	
	$recentClientIds = fetchCol0("SELECT clientptr FROM tblappointment WHERE date > '2015-11-01'");
	$eightballs = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property LIKE 'flag_%' AND value LIKE '$eightFlag|%'");
	$customChargeClientIds = fetchCol0("SELECT clientptr FROM relclientcharge");
	
	
	if($customChargeClientIds) $flagFilter[] = "clientid IN (".join(',', $customChargeClientIds).")";
	if($eightballs) $flagFilter[] = "clientid IN (".join(',', $eightballs).")";
	if($recentClientIds) $flagFilter[] = "clientid NOT IN (".join(',', $recentClientIds).")";

	$clients = fetchAssociationsKeyedBy($sql = 
		"SELECT * FROM tblclient 
			WHERE ".join(' AND ', $flagFilter)."
				ORDER BY lname, fname", 'clientid');
	//echo $sql;
	return $clients;
}




exit;
$bizname = $db == 'mobilemutts' ? 'Mobile Mutts <font color=green>South</font>' : 'Mobile Mutts <font color=red>North</font>';

$starFlag = $db == 'mobilemutts' ? 22 : 22;
$eightFlag = $db == 'mobilemutts' ? 21 : 21;
$groupWalk = $db == 'mobilemutts' ? 48 : 48;

if($_GET['go']) {
	$candidates = $_GET['test'] ? array(getClient($_GET['test'])) : getCustomChargeCandidates();
	foreach($candidates as $client) {
		addClientFlag($client['clientid'], $eightFlag, $note='added by LT');
		insertTable('relclientcharge', 
							array('clientptr'=>$client['clientid'], 
										'servicetypeptr'=>$groupWalk,
										'charge'=>17,
										'taxrate'=>-1.0), 1);
	}
}

function getCustomChargeCandidates() {
	global $db, $starFlag, $groupWalk;
	$starred = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property LIKE 'flag_%' AND value LIKE '$starFlag|%'");
	if($starred) $flagFilter[] = "clientid NOT IN (".join(',', $starred).")";

	$grouped = fetchCol0("SELECT clientptr FROM relclientcharge WHERE servicetypeptr = $groupWalk");
	if($grouped) $flagFilter[] = "clientid NOT IN (".join(',', $grouped).")";

	$dogFlag = $db == 'mobilemutts' ? 1 : 1;
	$dogged = fetchCol0("SELECT clientptr FROM tblclientpref WHERE property LIKE 'flag_%' AND value LIKE '$dogFlag|%'");
	if($dogged) $flagFilter[] = "clientid IN (".join(',', $dogged).")";

	$clients = fetchAssociationsKeyedBy($sql = 
		"SELECT * FROM tblclient 
			WHERE active = 1
				AND ".join(' AND ', $flagFilter)."
				ORDER BY lname, fname", 'clientid');
	//echo $sql;
	return $clients;
}
?>
<h2><?= $bizname ?> Dog Clients with no Star Flag and no Custom Group Walk Rate</h2>
<?
$candidates = getCandidates();
if(!$candidates) echo "None found.";
else echo count($candidates)." found.";
foreach($candidates as $client) 
	echo "<a href='client-edit.php?tab=billing&id={$client['clientid']}' target='groupwalk'>{$client['fname']} {$client['lname']}</a>"
				.clientFlagPanel($client['clientid'], $officeOnly=false, $noEdit=true, $contentsOnly=true, $onClick=null, $includeBillingFlags=false)
				."<br>";