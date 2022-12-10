<? // reports-clients-inactive.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

locked('o-');

$inactiveClients = fetchAssociationsKeyedBy(
	"SELECT clientid, lname, fname 
		FROM tblclient 
		WHERE active = 0 
		ORDER by lname, fname", 'clientid', 1);

$deactivations = fetchKeyValuePairs(
	"SELECT itemptr, time 
		FROM tblchangelog
		WHERE itemptr IN (".join(',', array_keys($inactiveClients)).")
			AND itemtable = 'tblclient'
				AND note = 'Deactivated'
		ORDER BY time", 1);
		
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=inactive-clients.csv");
		
$started = 0;
foreach($inactiveClients as $id => $client) {
	$client['deactivated'] = $deactivations[$id];
	if(!$started) dumpCSVRow(array_keys($client));
	$started = 1;
	dumpCSVRow($client);
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


	