<? // payment-history.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";



// Determine access privs
$locked = locked('o-');

extract(extractVars('firstDay,lastDay,id,csv,go', $_REQUEST));


//$pastDueDays = $_SESSION['preferences']['pastDueDays'];
$nullChoice = 'email';

$windowTitle = 
  $id ? "Payment History for ".fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblclient WHERE clientid = $id LIMIT 1")
  		: "Payment History";

$date = $firstDay ? shortNaturalDate(strtotime($firstDay)) : ""; 
//$lastdate = isset($lastDay) && $lastDay ? $lastDay :  date('Y-m-d');
$lastdate = isset($lastDay) && $lastDay ? shortNaturalDate(strtotime($lastDay)) :  shortNaturalDate(strtotime(date('Y-m-d')));
if($csv) {
	function dumpCSVRow($row) {
		if(!$row) echo "\n";
		if(is_array($row)) echo join(',', array_map('csv',$row))."\n";
		else echo csv($row)."\n";
	}

	function csv($val) {
		$val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
		$val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
		$val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
		$val = (strpos($val, "$&nbsp;") !== FALSE) ? str_replace("$&nbsp;", '', $val) : $val;
		return "\"$val\"";
	}
	$from = $date ? "from $date" : '';
	$to = $date ? "to $lastdate" : "up to $lastdate" ;
	echo "$windowTitle\nPayments and credits $from $to\n";
	echo "Report Generated ".shortDateAndTime('now')."\n\n";
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=PaymentHistory.csv ");
}
else {
	include "frame-bannerless.php";
	// ***************************************************************************
	echo "<h2>$windowTitle</h2>";
	echo "<form name='showform'>";
	echo "<span style='font-size:1.1em'>Payments and credits between ";
	calendarSet('Start Date', 'firstDay', $date);

	hiddenElement('origFirstDay', date('Y-m-d', strtotime($date)));
	echo ' and ';


	calendarSet('End Date', 'lastDay', $lastdate);
	echo ' ';
	echoButton('showpayments', 'Show', "changeRange(0)"); 
	echo ' ';
	echo '<img style="cursor: pointer;" onclick="changeRange(1)" src="art/spreadsheet-32x32.png" title="Export to Spreadsheet">';

	echo "</form><p>";
}

if($id) generateReport($id, $date, $lastdate, $csv);
else if($go) {
	$clients = fetchKeyValuePairs(
		"SELECT clientid, CONCAT_WS(' ', fname, lname), lname, fname FROM tblclient
				ORDER BY lname, fname");
	foreach($clients as $clientid => $clientName)
		generateReport($clientid, $date, $lastdate, $csv, $clientName);
	$id = null;
}

function generateReport($id, $date, $lastdate, $csv, $clientName=null) {
	global $providers, $serviceNames, $surchargeNames;
	$providers = getProviderNames();
	$serviceNames = getAllServiceNamesById(1, $noInactiveLabel=1, $setGlobalVar=true);
	$surchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	$startClause = $date ? "AND issuedate >= '".dbDate($date)."'" : '';
	$endClause = $lastdate ? "AND issuedate <= '".dbDate($lastdate)." 23:59:59'" : '';
	$credits = fetchAssociationsKeyedBy($sql = 
			"SELECT * 
				FROM tblcredit 
				WHERE clientptr = $id $startClause $endClause
				ORDER BY issuedate", 'creditid');
	if($credits) {
		$rawBillableCredits = fetchAssociations(
			"SELECT * 
				FROM relbillablepayment 
				WHERE amount > 0 AND paymentptr IN (".join(',', array_keys($credits)).")"); // oh the shame of $0 payments...
		foreach($rawBillableCredits as $row) {
			$billableCredits[$row['billableptr']][$row['paymentptr']] = $row['amount']; //array(billableid=>array(creditid=>amount,...))
		}

		if($billableCredits) {
			$billables = fetchAssociations("SELECT * 
																			FROM tblbillable
																			WHERE billableid IN 
																				(".join(',', array_keys($billableCredits)).")
																			ORDER BY itemdate");
			foreach($billables as $i => $billable) 
				$billables[$i]['covered'] = $billableCredits[$billable['billableid']]; // array(creditptr => amount)
		}
		foreach((array)$billables as $b) {
			$b['handle'] = "{$b['itemtable']}{$b['itemptr']}";
			$queries[$b['itemtable']][] = $b['itemptr'];
			foreach($b['covered'] as $credid => $amount)
				$sortedBillables[$credid][] = $b;
		}
		foreach((array)$queries as $tbl => $ids) {
			$idfield = $tbl == 'tblappointment' ? 'appointmentid' : 
									($tbl == 'tblrecurringpackage' ? 'packageid' :
									($tbl == 'tblothercharge' ? 'chargeid' :
									($tbl == 'tblsurcharge' ? 'surchargeid' : '')));
			foreach(fetchAssociations("SELECT * FROM $tbl WHERE $idfield IN (".join(',', $ids).")") as $item)
				$items["$tbl{$item[$idfield]}"] = $item;
		}
		$providers = getProviderNames();
		$serviceNames = getAllServiceNamesById(1, $noInactiveLabel=1, $setGlobalVar=true);
		$surchargeNames = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");

		$references = array();
		foreach((array)$credits as $credit) {
			$debitsForThisCredit = 0;
			$rowCluster = array();
	//print_r($sortedBillables[$credit['creditid']]);echo  '<p>';		
			foreach((array)$sortedBillables[$credit['creditid']] as $b) {
				$item = $items[$b['handle']];
				$tbl = $b['itemtable'];
				$idfield = $tbl == 'tblappointment' ? 'appointmentid' : 
										($tbl == 'tblrecurringpackage' ? 'packageid' :
										($tbl == 'tblothercharge' ? 'chargeid' :
										($tbl == 'tblsurcharge' ? 'surchargeid' : '?')));
				$editor = $tbl == 'tblappointment' ? 'appointment-edit.php?id=' : 
										($tbl == 'tblrecurringpackage' ? '*service-monthly.php?id=' :
										($tbl == 'tblothercharge' ? 'charge-edit.php?id=' :
										($tbl == 'tblsurcharge' ? 'surcharge-edit.php?id=' : '?')));
				$editor = $editor.$item[$idfield];
				$creditCoverage = $b['covered'][$credit['creditid']];
				if($b['charge'] != $creditCoverage) {
					if(!$references[$b['billableid']]) $references[$b['billableid']] = chr(count($references)+65);
					$ref = "<b>({$references[$b['billableid']]})</b> ";
				}
				else $ref = '';
				$debit = $b['charge'] == $creditCoverage
									? dollarAmount($b['charge']) :
									$ref."[".dollarAmount($b['charge']).'] '.dollarAmount($b['covered'][$credit['creditid']]);

				$row = array('date'=>shortDate(strtotime($b['itemdate'])), 'time'=>$item['timeofday'], 'sitter'=>$providers[$item['providerptr']],
								'debit'=>$debit, 'sortdate'=>date('Y-m-d', strtotime($b['itemdate']))." {$item['starttime']}",
								'status'=>($creditCoverage < $b['charge'] ? "partial: ".dollarAmount($creditCoverage) : 'PAID'));
				$description = $tbl == 'tblappointment' ? $serviceNames[$item['servicecode']] : 
									($tbl == 'tblrecurringpackage' ? "Monthly ".date('F Y', strtotime($b['monthyear'])) :
									($tbl == 'tblothercharge' ? 'Miscellaneous Charge' :
									($tbl == 'tblsurcharge' ? $surchargeNames[$item['surchargecode']] : '?')));
				if($editor[0] == '*') {
					$editor = substr($editor, 1);
					$description = fauxLink($description." ({$credit['creditid']})", "document.location.href=\"$editor\"", true, 'edit this item');
				}
				else $description = fauxLink($description." ({$credit['creditid']})", "openConsoleWindow(\"itemeditor\", \"$editor\", 700, 600)", 1, 'edit this item');
				$row['description'] = $description;
				$rowCluster[] = $row;
	//print_r($b['covered']);
				$totalDebits += $b['covered'][$credit['creditid']];
				$debitsForThisCredit += $b['covered'][$credit['creditid']];
			}
			$incDebits = findIncompletes($rowCluster, $id, $credit['amount'] - $credit['amountused'], $credit['creditid']);
			$debitsForThisCredit += $incDebits;
			$totalDebits += $incDebits;
			addCanceledVisits($rowCluster, $id);
			foreach($rowCluster as $row) $rows[] = $row;
			$issuedate = shortDate(strtotime($credit['issuedate']));
			if(!$csv) $issuedate = "<b>$issuedate</b>"; 
			$row = array('date'=>$issuedate, 'time'=>'', 'sitter'=>'',
							'debit'=>'<b>'.dollarAmount($debitsForThisCredit).'</b>', 'credit'=>dollarAmount($credit['amount']));
			$description = $credit['payment'] ? "<b>Payment #{$credit['creditid']}</b>" : "<b>Credit #{$credit['creditid']}</b>";
			if($credit['externalreference']) $description .= " [{$credit['externalreference']}]";
			$editor = ($credit['payment'] ? 'payment-edit.php?id=' : 'credit-edit.php?id=').$credit['creditid'];
			$description = fauxLink($description, "openConsoleWindow(\"itemeditor\", \"$editor\", 700, 600)", 1, 'edit this item');
	//echo 		$description;exit;
			$row['description'] = $description;
			$rows[] = $row;
			$totalCredits += $credit['amount'];
		}
		$rows[] = array('description'=>"<b>Total</b>", 'credit'=>dollarAmount($totalCredits), 'debit'=>dollarAmount($totalDebits));

		$columns = explodePairsLine("date|Date||time|Time||description|Description||sitter|Sitter||debit|Debit||credit|Credit||status|Status");
		// 'dollaramountcell warning'
	//function tableFrom($columns, $data=null, $attributes=null, 
								//$class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', 
										//$columnSorts=null, $rowClasses=null, $colClasses=null, $sortClickAction=null) {
		$dataCellClass = array('debit'=>'dollaramountcell warning', 'credit'=>'dollaramountcell darkgreen');
		$clientName = $id ? fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname), lname, fname FROM tblclient WHERE clientid = $id") : '';
		if(!$csv) {
			$from = $date ? "from $date " : '';
			$to = $date ? "to $lastdate" : "up to $lastdate" ;
			
			echo "<h3>Payment History for $clientName $from$to</h3>";
			tableFrom($columns, $rows, 'bgcolor=white width=100% bordercolor=black border=1', 
								'futuretedtask', null, 'topRow', null, $sortableCols, $rowClasses, $dataCellClass); //'noncompletedtask'
		}
		else {
			if($clientName) echo "Client,";
			echo "Date,Time,Description,Sitter,Debit,Credit,Status\n";
			foreach($rows as $row) {
				$arr = $clientName ? array($clientName) : array();
				$arr = array_merge($arr, array($row['date'],$row['time'],$row['description'],$row['sitter'],$row['debit'],$row['credit'],$row['status']));
				foreach($arr as $i => $val) $arr[$i] = str_replace('&#36;&nbsp;', '$', $val);
				dumpCSVRow(array_map('strip_tags', $arr));
			}
		}
	}
}

function findIncompletes(&$rows, $clientid, $credit, $creditid) {
	// add rows for any incomplete visits until $credit is used up
	// return amount to add to total debits
	global $providers, $serviceNames, $surchargeNames;
	static $allIncompletes;
	if($credit <= 0) return 0;
	if(!$allIncompletes) {
		$allIncompletes = fetchAssociations(
				"SELECT *, charge+ifnull(adjustment,0) as toupee, CONCAT_WS(' ', date, starttime) as sortdate 
					FROM tblappointment 
						WHERE canceled IS NULL 
							AND completed IS NULL
							AND clientptr = $clientid
						ORDER BY date, starttime");
		$surcharges = fetchAssociations(
				"SELECT *, charge as toupee, CONCAT_WS(' ', date, starttime) as sortdate  
					FROM tblsurcharge 
						WHERE canceled IS NULL 
							AND completed IS NULL
							AND clientptr = $clientid
						ORDER BY date, starttime");
		$allIncompletes = array_merge($allIncompletes, $surcharges);
		usort($allIncompletes, 'sortBySortdate');
	}
	foreach($allIncompletes as $i => $appt) {
		if($appt['toupee'] == 0) continue;
		$toPay = min($appt['toupee'], $credit);
		$debit += $toPay;
		$credit = $credit - $toPay;
		$allIncompletes[$i]['toupee'] = $appt['toupee'] - $toPay;
		$description = $appt['servicecode'] ? $serviceNames[$appt['servicecode']] : $surchargeNames[$appt['surchargecode']];
		$description = fauxLink($description." ($creditid)", 
											"openConsoleWindow(\"itemeditor\", \"appointment-edit.php?id={$appt['appointmentid']}\", 700, 600)", 
											1, 'edit this item');
		$lineDebit = $appt['toupee'] == $toPay ? dollarAmount($appt['toupee']) : dollarAmount($appt['toupee']).' ('.dollarAmount($toPay).')';
		$status = $appt['toupee'] == $toPay ? 'CREDIT' : 'PARTIAL CREDIT';
		$rows[] = 
			array('date'=>shortDate(strtotime($appt['date'])), 'time'=>$appt['timeofday'], 'sitter'=>$providers[$appt['providerptr']],
							'sortdate'=>date('Y-m-d', strtotime($appt['date']))." {$appt['starttime']}",
							'status'=>"<span style='color:grey'>$status</span>", 'debit'=>"<span style='color:grey'>".$lineDebit."</span>",
						 'description'=>$description);
		if($credit <= 0) break;
	}
	usort($rows, 'sortBySortdate');
	return $debit;
}

function addCanceledVisits(&$rows, $clientid) {
	global $providers, $serviceNames;
	if(!$rows) return;
	$start = date('Y-m-d', strtotime($rows[0]['date']));
	$end = date('Y-m-d', strtotime($rows[count($rows)-1]['date']));
	$canned = fetchAssociations(
			"SELECT * 
				FROM tblappointment 
					WHERE canceled IS NOT NULL AND clientptr = $clientid AND date >= '$start' AND date <= '$end'
					ORDER BY date, starttime");
				
	if(!$canned) return;
	
	foreach($canned as $appt) {
		$description = fauxLink($serviceNames[$appt['servicecode']], 
											"openConsoleWindow(\"itemeditor\", \"appointment-edit.php?id={$appt['appointmentid']}\", 700, 600)", 
											1, 'edit this item');
		$rows[] = 
			array('date'=>shortDate(strtotime($appt['date'])), 'time'=>$appt['timeofday'], 'sitter'=>$providers[$appt['providerptr']],
										'debit'=>'', 'sortdate'=>date('Y-m-d', strtotime($appt['date']))." {$appt['starttime']}",
							'status'=>'<font color=red>CANCELED</font>', 'debit'=>"[".dollarAmount($appt['charge']+$appt['adjustment'])."]",
						 'description'=>$description);
	}
	usort($rows, 'sortBySortdate');
}

function sortBySortdate($a, $b) { return strcmp($a['sortdate'], $b['sortdate']); }

if(!$csv) {
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='common.js'></script>
<script language='javascript'>
<? dumpPopCalendarJS(); ?>
setPrettynames('firstDay','Start Date', 'lastDay','End Date', 'lookahead','Lookahead');
function dayArg(argName) {
	argName = argName ? argName : 'firstDay';
	var val = document.getElementById(argName).value;
	if(!val) return '';
	return dbDate(val);
	//var mdy = val.split('/');
	//return mdy[2]+'-'+mdy[0]+'-'+mdy[1];
}


function changeRange(csv) {
	var includeall = document.getElementById('includeall') != null  && document.getElementById('includeall').checked ? '1' : '';
	if(MM_validateForm(
		'firstDay','','isDate', 
		'lastDay', '', 'isDate', 
		'firstDay', 'lastDay', 'datesInOrder')) {
			document.location.href='payment-history.php?go=1&id=<?= $id ?>&firstDay='+dayArg('firstDay')+'&lastDay='+dayArg('lastDay')+'&csv='+csv;
	}
}
</script>
<?
// ***************************************************************************
//include "frame-end.html";
}
?>


