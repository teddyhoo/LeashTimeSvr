<? // account-history-raw.php

// return a spreadsheet of visits, payments, and credits (non-system) in a given date range

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$locked = locked('o-#vr');

if(!staffOnlyTEST()) {
	echo "Must be staff.";
	exit;
}

extract(extractVars('client,start,end', $_REQUEST));

header("Content-Type: text/csv");
header("Content-Disposition: inline; filename=Client-Account-Report-$client.csv ");
$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $client LIMIT 1");
dumpCSVRow("Raw Account Report for $clientName");
dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
dumpCSVRow("Period: $start - $end");
dumpCSVRow("");
$start = date('Y-m-d', strtotime($start));
$end = date('Y-m-d', strtotime($end));
$rows = fetchAssociationsKeyedBy("SELECT appointmentid, a.date, timeofday, starttime, label as service, CONCAT_WS(' ', fname, lname) as provider, a.charge+IFNULL(adjustment,0) as charge,
															tax, b.charge as bcharge
														  FROM tblappointment a
														  LEFT JOIN tblbillable b ON itemptr = appointmentid AND itemtable = 'tblappointment' AND superseded = 0
														  LEFT JOIN tblprovider ON providerid = providerptr
														  LEFT JOIN tblservicetype ON servicetypeid = servicecode
														  WHERE completed IS NOT NULL AND a.clientptr = $client
														  	AND a.date >= '$start' AND a.date <= '$end'
														  ORDER BY a.date, starttime", 'appointmentid');
$discounts = array();
if($rows) $discounts = fetchKeyValuePairs("SELECT appointmentptr, amount FROM relapptdiscount WHERE appointmentptr IN ("
																.join(',', array_keys($rows)).")");
$surcharges = fetchAssociations("SELECT s.date, timeofday, starttime, CONCAT('Surcharge: ', label) as service, CONCAT_WS(' ', fname, lname) as provider, s.charge as charge,
															tax, b.charge as bcharge
														  FROM tblsurcharge s
														  LEFT JOIN tblbillable b ON itemptr = surchargeid AND itemtable = 'tblsurcharge' AND superseded = 0
														  LEFT JOIN tblprovider ON providerid = providerptr
														  LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
														  WHERE completed IS NOT NULL AND s.clientptr = $client
														  	AND s.date >= '$start' AND s.date <= '$end'
														  ORDER BY s.date, starttime");
foreach($surcharges as $surcharge) $rows[] = $surcharge;

$othercharges = fetchAssociations("SELECT issuedate as date, CONCAT('Charge: ', reason) as service, amount as charge,
															tax, b.charge as bcharge
														  FROM tblothercharge o
														  LEFT JOIN tblbillable b ON itemptr = chargeid AND itemtable = 'tblothercharge' AND superseded = 0
														  WHERE o.clientptr = $client
														  	AND issuedate >= '$start' AND issuedate <= '$end'
														  ORDER BY date");
foreach($othercharges as $surcharge) $rows[] = $surcharge;
														  	  

usort($rows, 'rowSort');

function rowSort($a, $b) {
	$x = strcmp($a['date'], $b['date']);
	if($x) return $x;
	return strcmp($a['starttime'], $b['starttime']);
}

dumpCSVRow(explode(',',"Date,Time,Service,Sitter,Visit Charge,Discount,Tax,Billable Charge"));														  
foreach($rows as $row) {
	$row['discount'] = $discounts[$row['appointmentid']];
	$row['tax'] = $row['tax'] > 0 ? $row['tax']  : '';
	dumpCSVRow($row, 'date,timeofday,service,provider,charge,discount,tax,bcharge');
}
dumpCSVRow("");
dumpCSVRow("");
$credits = fetchAssociations("SELECT issuedate as date, if(payment=1, 'Payment', 'Credit') as type, reason, amount
															FROM tblcredit
															WHERE clientptr = $client
																AND bookkeeping = 0
														  	AND issuedate >= '$start' AND issuedate <= '$end'
														  ORDER BY date");
dumpCSVRow(explode(',',"Date,Time,Type,Reason,Amount"));														  
foreach($credits as $row) {
	$parts = explode(' ', $row['date']);
	$row = array($parts[0], $parts[1], $row['type'], $row['reason'], $row['amount']); 
	dumpCSVRow($row);
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

