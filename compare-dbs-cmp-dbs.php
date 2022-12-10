<? //compare-dbs-cmp-dbs.php
// http://iwmr.info/petbizdev/compare-dbs-cmp-dbs.php?a=dogslife&b=woofies
require_once "common/db_fns.php";	
ensureInstallationSettings();
$dbhost = $installationSettings['dbhost'];
$dbuser = $installationSettings['dbuser'];
$dbpass = $installationSettings['dbpass'];


function getSchema($name) {
	global $db;
	$db = $name;
	include 'common/init_db_petbiz.php';
//echo fetchRow0Col0("SELECT COUNT(*) FROM relclientcharge")." ($name)<P>";	
	$tables = array();
	$tnames = fetchCol0("SHOW TABLES");
	if(!in_array('tblbillable', $tnames)) return null;
	foreach($tnames as $table)
		$tables[$table] = fetchAssociations("SHOW FULL COLUMNS FROM $table");
	return $tables;
}

function getAllSchemasExcept($a) {
	include 'common/init_db_common.php';
	$schemas = array();
	foreach(fetchCol0("SHOW DATABASES") as $name) 
		if($name != $a) {
			if($schema = getSchema($name))
				$schemas[$name] = $schema;
		}
	return $schemas;
}

function cmpTables($a, $b, $labelA, $labelB) {
	$diffs = array();
	$diff = array_diff(array_map('getField', $a), array_map('getField', $b));
	if($diff) $diffs[] = "Fields missing from $labelB: ".join(', ', $diff);
	$diff = array_diff(array_map('getField', $b), array_map('getField', $a));
	if($diff) $diffs[] = "Fields missing from $labelA: ".join(', ', $diff);
	
	foreach($b as $field) $bmap[$field['Field']] = descrField($field);
	foreach($a as $field) {
		$descr = descrField($field);
		if($descr != $bmap[$field['Field']]) 
			$diffs[] = "-- [$labelA] $descr<br>-- [$labelB] {$bmap[$field['Field']]}<br>";
		}
	return $diffs;
}

function getField($row) {return $row['Field'];}

function descrField($row) {
	return $row['Field'].' '.$row['Type'].' '.($row['Null'] == 'YES' ? 'NULL' : 'Not NULL').' '.$row['Key'].' '.$row['Extra']
		.($row['Comment'] ? " COMMENT '{$row['Comment']}'" : ' ');
}

$aName = $_REQUEST['all'] ? $_REQUEST['all'] : $_REQUEST['a'];
$a = getSchema($aName);
if($_REQUEST['all']) {
	$bs = getAllSchemasExcept($aName);
}
else {
	$bs = array($_REQUEST['b'] => getSchema($_REQUEST['b']));
}
echo "<b>Remember to logout of LT First!</b><p>";
foreach($bs as $bName => $b) {
	echo "<hr><b>Table A:</b> $aName ".count($a)." tables <p><b>Table B:</b> <font color=blue>$bName</font> ".count($b)." tables<p>";
	$diff = array_diff(array_keys($a), array_keys($b));
	if($diff) echo "Tables missing from $bName: ".join(', ', $diff).'<p>';

	$diff = array_diff(array_keys($b), array_keys($a));
	if($diff) echo "Tables missing from $aName: ".join(', ', $diff).'<p>';

	foreach($a as $name => $table) {
		if(!$b[$name]) continue;
		$diffs = cmpTables($table, $b[$name], "$aName.$name", "$bName.$name");
		if($diffs) {
			echo "$name:<br>";
			foreach($diffs as $diff) echo "$diff<br>";
		}
		echo "<p>";
	}
}