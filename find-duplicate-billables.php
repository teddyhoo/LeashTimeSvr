<? // find-duplicate-billables.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

$apptids = fetchCol0("SELECT itemptr FROM
	(SELECT itemptr, count(*) as dups
	FROM tblbillable
	WHERE superseded = 0 AND itemtable = 'tblappointment'
	GROUP BY itemptr) x
	WHERE x.dups > 1");
	

$surchargeids = fetchCol0("SELECT itemptr FROM
	(SELECT itemptr, count(*) as dups
	FROM tblbillable
	WHERE superseded = 0 AND itemtable = 'tblsurcharge'
	GROUP BY itemptr) x
	WHERE x.dups > 1");
	

if(!$apptids) {
	echo "No dups.";
	exit;
}

$clients = fetchKeyValuePairs("SELECT clientid, CONCAT_WS(' ', fname, lname) FROM tblclient");

//print_r($billables);
if($apptids) {
	$lastInvoice = -1;
	$billables = fetchAssociations("SELECT * FROM tblbillable WHERE superseded = 0 AND itemptr IN (".join(',', $apptids).") 
																		ORDER BY itemptr, invoiceptr, billableid");
	$appts = fetchAssociationsKeyedBy("SELECT * FROM tblappointment 
																			WHERE appointmentid IN (".join(',', $apptids).")", 'appointmentid');
	foreach($billables as $b) {
		if($b['itemptr'] != $lastItem) {
			$lastInvoice = -1;
			$appt = $appts[$b['itemptr']];
			echo "<hr>VISIT [{$b['itemptr']}]: Client: {$clients[$b['clientptr']]} {$appt['date']} {$appt['timeofday']} $".($appt['charge']+$appt['adjustment']).'<br>';
			$lastItem = $b['itemptr'];
		}
		if($b['invoiceptr'] != $lastInvoice) echo ($b['invoiceptr'] ? " - INVOICE: {$b['invoiceptr']}" : " - UNINVOICED").'<br>';
		$lastInvoice = $b['invoiceptr'];
		echo "- - BILLABLE: {$b['billableid']} BILLDATE: {$b['billabledate']} SUPERSEDED: ".($b['superseded'] ? 'yes' : 'no')
					." Charge \${$b['charge']}  Paid: \${$b['paid']}<br>";
	}
}

if($surchargeids) {
	$lastInvoice = -1;
	$billables = fetchAssociations("SELECT * FROM tblbillable WHERE superseded = 0 AND itemptr IN (".join(',', $apptids).")
																		ORDER BY itemptr, invoiceptr, billableid");
	$surcharges = fetchAssociationsKeyedBy("SELECT * FROM tblsurcharge 
																			WHERE surchargeid IN (".join(',', $surchargeids).")", 'appointmentid');
	foreach($billables as $b) {
		if($b['itemptr'] != $lastItem) {
			$lastInvoice = -1;
			$appt = $surcharges[$b['itemptr']];
			echo "<hr>SURCHARGE [{$b['itemptr']}]: Client: {$clients[$b['clientptr']]} {$appt['date']} {$appt['timeofday']} $".($appt['charge']).'<br>';
			$lastItem = $b['itemptr'];
		}
		if($b['invoiceptr'] != $lastInvoice) echo ($b['invoiceptr'] ? " - INVOICE: {$b['invoiceptr']}" : " - UNINVOICED").'<br>';
		$lastInvoice = $b['invoiceptr'];
		echo "- - BILLABLE: {$b['billableid']} BILLDATE: {$b['billabledate']} SUPERSEDED: ".($b['superseded'] ? 'yes' : 'no').
					" Charge \${$b['charge']}  Paid: \${$b['paid']}<br>";
	}
}