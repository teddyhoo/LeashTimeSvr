<?
// keys-needed.php
/* Two modes: standalone and included
if included, do not echo head section and body start
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "key-fns.php";
require_once "gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('+ka');
$right = keyManagementRight();

extract($_GET);

$standalone = strpos($_SERVER["SCRIPT_FILENAME"], 'keys-needed.php') > 0;
if(strpos($_SERVER["REQUEST_URI"], 'key-check-out') !== FALSE) $linkOp = 'key-check-out.php?keyid=';
$daysAhead = 14;

$needed = allClientKeysMissingForDaysAhead($daysAhead);  //clientptr, providerptr, date

foreach($needed as $need) $clients[] = $need['clientptr'];
$clientKeys = getClientGroupKeys($clients);
//print_r($clientKeys);

function dateSort($a, $b) { //$a[''] < $b['']
	return $a['date'] < $b['date'] ? -1 : (
					$a['date'] > $b['date'] ? 1 : (
					$a['provider'] < $b['provider'] ? -1 : (
					$a['provider'] > $b['provider'] ? 1 : (
					$a['clientsortname'] < $b['clientsortname'] ? -1 : (
					$a['clientsortname'] > $b['clientsortname'] ? 1 : 0)))));
}			
function rDateSort($a, $b) { return 0 - dateSort($a, $b); }

function providerSort($a, $b) { //$a[''] < $b['']
	return 
					$a['provider'] < $b['provider'] ? -1 : (
					$a['provider'] > $b['provider'] ? 1 : 0);
}					
function rProviderSort($a, $b) { return 0 - providerSort($a, $b); }

function clientSort($a, $b) { //$a[''] < $b['']
	return 
					$a['clientsortname'] < $b['clientsortname'] ? -1 : (
					$a['clientsortname'] > $b['clientsortname'] ? 1 : 0);
}					
function rClientSort($a, $b) { return 0 - clientSort($a, $b); }
//print_r($needed);

if($right == 'ka') {
	$columns = explodePairsLine('key|Key||bin|Key Hook||dateneeded|Date Needed||provider|Sitter||client|Client');
	$columnSorts = array('dateneeded'=>'asc','client'=>null, 'provider'=>null);
}
else {
	$columns = explodePairsLine('key|Key||bin|Key Hook||dateneeded|Date Needed||client|Client');
	$columnSorts = array('dateneeded'=>'asc','client'=>null);
}
$sortFns = explodePairsLine('dateneeded_asc|dateSort||dateneeded_|dateSort||dateneeded_desc|rDateSort||client_|clientSort||client_asc|clientSort||client_desc|rClientSort||'.
														'provider_|providerSort||provider_asc|providerSort||provider_desc|rProviderSort');

$rows = array();
foreach($needed as $need) {
	//if(!($clientKeys[$need['clientptr']]['keyid'])) continue;
	if($right == 'ki' && $need['providerptr'] != $_SESSION['providerid']) continue;
	$row['client'] = $need['client'];
	$row['clientsortname'] = $need['clientsortname'];
	$row['provider'] = $need['providerdescription'];
	if(!$row['provider']) $row['provider'] = 'Unassigned';
	$row['date'] = $need['date'];
	$row['dateneeded'] = shortDate(strtotime($need['date']));
	$keyid = $clientKeys[$need['clientptr']]['keyid'];
	if($keyid) {
		if($linkOp) $keyid = fauxLink(sprintf("%04d", $keyid), "document.location.href=\"$linkOp".sprintf("%04d", $keyid)."-00\"", 1, 'Check out this key');
		else $keyid = fauxLink(sprintf("%04d", $keyid), "if(window.opener) window.opener.location.href=\"key-edit.php?id=$keyid\"", 1, 'Edit this key');
	}
	$row['key'] = $keyid 
		? $keyid 
		: "<span style='cursor:pointer' title='This client (ID: @{$need['clientptr']}) has no key on record.'>????</span>";
	$row['description'] = $keyid ? $clientKeys[$need['clientptr']]['description'] : null;
	$row['bin'] = $clientKeys[$need['clientptr']]['bin'];
	$rows[] = $row;
}

														
if($rows) usort($rows, $sortFns[$sort] ? $sortFns[$sort] : 'dateSort');

$newRows = array();
foreach($rows as $row) {
	$newRows[] = $row;
	if($row['description'])
		$newRows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=5>Key #{$row['key']} Description: {$row['description']}</td></tr>");
}
$rows = $newRows;
$pageTitle = "Keys Needed in the Next $daysAhead Days";


if($standalone) {?>
<head>
<title><?= $pageTitle ?></title>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" />
<style>
.sortableListHeader {
  font-size: 1.05em;
  padding-bottom: 5px; 
  border-collapse: collapse;
  text-align: left;
}

</style>
</head>
<body style='background:white;padding: 10px;'>
<?
}
?>
<h2><?= $pageTitle ?><div style='float:right;display:inline;'><img src='art/small-key.gif'></div></h2><?

if($rows) tableFrom($columns, $rows, 'WIDTH=95%', null, null, null, null, $columnSorts);
else echo "No additional keys are needed.";