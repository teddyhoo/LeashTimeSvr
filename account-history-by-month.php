<? // account-history-by-month.php

// return a spreadsheet of visits, payments, and credits (non-system) in a given date range

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";

$locked = locked('o-#vr');

if(FALSE && !staffOnlyTEST()) {
	echo "Must be staff.";
	exit;
}

$OLDWAY = FALSE; //!mattOnlyTEST();


extract(extractVars('client,start,end,csv', $_REQUEST));

$clientName = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $client LIMIT 1");
$start = date('Y-m-d', strtotime($start));
$end = date('Y-m-d', strtotime($end));
$afterEnd = date('Y-m-d 00:00:00', strtotime("+ 1 day", strtotime(date('Y-m-d', strtotime($end)))));
$startMonthYear = date('Y-m', strtotime($start));


$OLDWAY = $OLDWAY ? "AND a.date >= '$start'" : '';
$billables = fetchAssociationsKeyedBy("SELECT appointmentid, a.date, timeofday, starttime, label as service, CONCAT_WS(' ', fname, lname) as provider, a.charge+IFNULL(adjustment,0) as charge,
															tax, b.charge as bcharge
														  FROM tblappointment a
														  LEFT JOIN tblbillable b ON itemptr = appointmentid AND itemtable = 'tblappointment' AND superseded = 0
														  LEFT JOIN tblprovider ON providerid = providerptr
														  LEFT JOIN tblservicetype ON servicetypeid = servicecode
														  WHERE completed IS NOT NULL AND a.clientptr = $client
														  	$OLDWAY	AND a.date <= '$end'
														  ORDER BY a.date, starttime", 'appointmentid');
$discounts = array();
if($billables) $discounts = fetchKeyValuePairs("SELECT appointmentptr, amount FROM relapptdiscount WHERE appointmentptr IN ("
																.join(',', array_keys($billables)).")");
$OLDWAY = $OLDWAY ? "AND s.date >= '$start'" : '';
$surcharges = fetchAssociations("SELECT s.date, timeofday, starttime, CONCAT('Surcharge: ', label) as service, CONCAT_WS(' ', fname, lname) as provider, s.charge as charge,
															tax, b.charge as bcharge
														  FROM tblsurcharge s
														  LEFT JOIN tblbillable b ON itemptr = surchargeid AND itemtable = 'tblsurcharge' AND superseded = 0
														  LEFT JOIN tblprovider ON providerid = providerptr
														  LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
														  WHERE completed IS NOT NULL AND s.clientptr = $client
														  	$OLDWAY AND s.date <= '$end'
														  ORDER BY s.date, starttime");
foreach($surcharges as $surcharge) $billables[] = $surcharge;

$OLDWAY = $OLDWAY ? "AND issuedate >= '$start'" : '';
$othercharges = fetchAssociations("SELECT issuedate as date, CONCAT('Charge: ', reason) as service, amount as charge,
															tax, b.charge as bcharge
														  FROM tblothercharge o
														  LEFT JOIN tblbillable b ON itemptr = chargeid AND itemtable = 'tblothercharge' AND superseded = 0
														  WHERE o.clientptr = $client
														  	$OLDWAY AND issuedate <= '$afterEnd'
														  ORDER BY date");
foreach($othercharges as $charge) $billables[] = $charge;

$OLDWAY = $OLDWAY ? "AND itemdate >= '$start'" : '';
$monthlies = fetchAssociations("SELECT itemdate as date, monthYear as service, b.charge as bcharge,
															tax, b.charge as bcharge
														  FROM  tblbillable b
														  WHERE b.clientptr = $client
														  	$OLDWAY AND itemdate <= '$end' 
														  	AND monthYear IS NOT NULL AND superseded = 0
														  ORDER BY date");
foreach($monthlies as $billable) {
	$billable['service'] = "Monthly Contract: ".date('F Y', strtotime($billable['service']));
	$billables[] = $billable;
}
$OLDWAY = $OLDWAY ? "AND issuedate >= '$start'" : '';
$payments = fetchAssociations("SELECT issuedate as date, if(payment, 'PAYMENT', 'CREDIT') as service, 
																	CONCAT('(', amount, ')') as bcharge
														  FROM  tblcredit
														  WHERE clientptr = $client
														  	$OLDWAY AND issuedate <= '$afterEnd' 
														  	AND voided IS NULL
														  ORDER BY date");
foreach($payments as $billable) {
	$billables[] = $billable;
}
														  	  

usort($billables, 'billableSort');

function billableSort($a, $b) {
	$x = strcmp($a['date'], $b['date']);
	if($x) return $x;
	return strcmp($a['starttime'], $b['starttime']);
}

$month = array();
$months = array();
foreach($billables as $bill) {
	$monthIndex = date('Y-m', strtotime($bill['date']));
	$months[$monthIndex]['billables'][] = $bill;
	$months[$monthIndex]['due'] += $bill['bcharge'];
	
}


$OLDWAY = $OLDWAY ? "AND issuedate >= '$start'" : '';
$credits = fetchAssociations("SELECT issuedate as date, if(payment=1, 'Payment', 'Credit') as type, reason, amount, payment
															FROM tblcredit
															WHERE clientptr = $client
																AND bookkeeping = 0
														  	$OLDWAY AND issuedate <= '$afterEnd'
														  	AND bookkeeping = 0
														  ORDER BY date");
														  // 
foreach($credits as $credit) {
	$monthIndex = date('Y-m', strtotime($credit['date']));
	if($credit['payment']) $months[$monthIndex]['payment'] += $credit['amount'];
	else $months[$monthIndex]['credit'] += $credit['amount'];
}

ksort($months);

foreach($months as $monthIndex => $month) {
	$months[$monthIndex]['prevbal'] = $prevMonth['acctbal'];
	$months[$monthIndex]['acctbal'] = $month['due'] + $prevMonth['acctbal'] - $month['payment'] - $month['credit'];
	$prevMonth = $months[$monthIndex];
}

$start = shortDate(strtotime($start));
$end = shortDate(strtotime($end));
if($csv) {
	dumpSpreadsheet($clientName, $client, $start, $end, $months, $startMonthYear);
	exit;
}
$pageTitle = "Raw Account Report for $clientName";
include "frame.html";
// ***************************************************************************
echo "<p style='text-align:center'>Report generated: ".shortDateAndTime('now', 'mil')
			."<br>Period: $start - $end</p>";
$columns = explodePairsLine('date|Date||timeofday|Time||service|Service||provider|Sitter||charge|Visit Charge||discount|Discount||tax|Tax||bcharge|Billable Charge');

foreach($months as $monthYear => $month) {
	if(strcmp($monthYear, $startMonthYear) < 0) continue;
	
	$monthLabel = date('F Y', strtotime("$monthYear-01"));
	
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td colspan=8><hr></td></tr>");
	$rows[] = array('#CUSTOM_ROW#'=>"<tr><td style='font-size:1.2em;font-weight:bold;' colspan=8>$monthLabel</td></tr>");
	foreach((array)$month['billables'] as $row) {
		$row['discount'] = $discounts[$row['appointmentid']];
		$row['tax'] = $row['tax'] > 0 ? $row['tax']  : '';
		$row['date'] = shortDate(strtotime($row['date']));
		$rows[] = $row;
	}
	$rows[] = array('tax'=>'Amt Due', 'bcharge'=>dollarAmount($month['due'], 1, '0'));
	if(($month['credit'])) $rows[] = array('tax'=>'Credit', 'bcharge'=>dollarAmount($month['credit'], 1, '0'));
	$rows[] = array('tax'=>'Payment', 'bcharge'=>dollarAmount($month['payment'], 1, '0'));
	if($month['prevbal']) $rows[] = array('tax'=>'Prev Bal', 'bcharge'=>dollarAmount($month['prevbal'], 1, '0'));
	$rows[] = array('tax'=>'Acct Bal', 'bcharge'=>dollarAmount($month['acctbal'], 1, '0'));
}

echoButton('', 'Download Spreadsheeet', "document.location.href=\"{$_SERVER['REQUEST_URI']}&csv=1\"");
$colClasses = array('charge'=>'dollaramountcell', 'discount'=>'dollaramountcell', 'tax'=>'dollaramountcell', 'bcharge'=>'dollaramountcell');
tableFrom($columns, $rows, '', null, null, null, null, null, $rowClasses, $colClasses);
include "frame-end.html";



function dumpSpreadsheet($clientName, $client, $start, $end, $months, $startMonthYear) {
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Client-Account-Report-$client.csv ");
	dumpCSVRow("Raw Account Report for $clientName");
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("Period: $start - $end");
	dumpCSVRow("");

	dumpCSVRow(explode(',',"Date,Time,Service,Sitter,Visit Charge,Discount,Tax,Billable Charge"));														  
	foreach($months as $monthYear => $month) {
		if(strcmp($monthYear, $startMonthYear) < 0) continue;
		foreach((array)$month['billables'] as $row) {
			$row['discount'] = $discounts[$row['appointmentid']];
			$row['tax'] = $row['tax'] > 0 ? $row['tax']  : '';
			$row['date'] = strpos($row['date'], ' ') ? substr($row['date'], 0, strpos($row['date'], ' ')) : $row['date'];
			dumpCSVRow($row, 'date,timeofday,service,provider,charge,discount,tax,bcharge');
		}
		dumpCSVRow(array('', '', '', '', '', '', 'Amt Due', numericAmount($month['due'])));
		if(($month['credit'])) dumpCSVRow(array('', '', '', '', '', '', 'Credit', numericAmount($month['credit'])));
		dumpCSVRow(array('', '', '', '', '', '', 'Payment', numericAmount($month['payment'])));
		$prevBal = numericAmount($month['prevbal']);
		//dumpCSVRow(array('', '', '', '', '', '', 'Prev Bal', ($month['prevbal'] > 0 ? "($prevBal)" : $prevBal)));
		dumpCSVRow(array('', '', '', '', '', '', 'Prev Bal', $prevBal));
		$acctBal = numericAmount($month['acctbal']);
		//dumpCSVRow(array('', '', '', '', '', '', 'Acct Bal', ($month['acctbal'] > 0 ? "($acctBal)" : $acctBal)));
		dumpCSVRow(array('', '', '', '', '', '', 'Acct Bal', $acctBal));
	}
}

function numericAmount($amt) {
	return $amt ? $amt : '0';
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

