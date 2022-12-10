<?
//pay-history.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "pay-fns.php";

// Determine access privs
$locked = locked('vh');

extract($_REQUEST);

if($dailySheet) { // dump a spreadsheet of daily pay for completed visits and gratuities
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

	$paystarting = date('Y-m-d', strtotime($paystarting));
	$payending = date('Y-m-d', strtotime($payending));
	// first sum rate+bonus, grouped by day
	$payByDay = fetchAssociationsKeyedBy(
		"SELECT date, SUM(rate+IFNULL(bonus, 0)) as pay, COUNT(*) as visits
			FROM tblappointment 
			WHERE completed IS NOT NULL
				AND providerptr = $prov
				AND date >= '$paystarting' AND date <= '$payending'
			GROUP BY date", 'date', 1);
	// then sum gratuities, grouped by day
	$grats = fetchAssociationsKeyedBy(
		"SELECT SUBSTRING(issuedate, 1, 10) as date, sum(amount) as pay, COUNT(*) as tips
			FROM tblgratuity
			WHERE providerptr = $prov
				AND issuedate >= '$paystarting' AND issuedate <= '$payending'
			GROUP BY date", 'date', 1);
	foreach($grats as $date => $grat) {
		$payByDay[$date]['date'] = $date;
		$payByDay[$date]['pay'] += $grat['pay'];
		$payByDay[$date]['tips'] = $grat['tips'];
	}
//print_r($grats);	
	$surcharges = fetchAssociationsKeyedBy(
		"SELECT date, sum(rate) as pay, COUNT(*) as surcharges
			FROM tblsurcharge
			WHERE providerptr = $prov
				AND date >= '$paystarting' AND date <= '$payending'
			GROUP BY date", 'date', 1);
	foreach($surcharges as $date => $surch) {
		$payByDay[$date]['date'] = $date;
		$payByDay[$date]['pay'] += $surch['pay'];
		$payByDay[$date]['surcharges'] = $surch['surcharges'];
	}
//print_r($surcharges);exit;	
	$othercomps = fetchAssociationsKeyedBy(
		"SELECT date, sum(amount) as pay, COUNT(*) as othercomp
			FROM tblothercomp
			WHERE providerptr = $prov
				AND date >= '$paystarting' AND date <= '$payending'
			GROUP BY date", 'date', 1);
	foreach($othercomps as $date => $comp) {
		$payByDay[$date]['date'] = $date;
		$payByDay[$date]['pay'] += $comp['pay'];
		$payByDay[$date]['othercomp'] = $comp['othercomp'];
	}
	ksort($payByDay);
	header("Content-Type: text/csv");
	header("Content-Disposition: inline; filename=Daily-Pay-$prov.csv ");
	dumpCSVRow('Daily Pay for Completed Visits and Gratuities');
	dumpCSVRow('Sitter: '.fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $prov", 1));
	dumpCSVRow("Period: ".shortDate(strtotime($paystarting)).' - '.shortDate(strtotime($payending)));
	dumpCSVRow("Report generated: ".shortDateAndTime('now', 'mil'));
	dumpCSVRow("");
	$cols = array('date'=>'Date', 'visits'=>'# visits', 'tips'=>'# gratuities', 'surcharges'=>'# surcharges', 'othercomp'=>'# other comps', 'pay'=>'Pay');
	if(!$surcharges) unset($cols['surcharges']);
	if(!$grats) unset($cols['tips']);
	if(!$othercomps) unset($cols['othercomp']);
	if(!$payByDay)
		echo "There is no sitter pay to report in this period.";
	else {
		dumpCSVRow($cols);
		foreach($payByDay as $date => $arr) {
		//echo "DATE: $date ".print_r($arr,1)."\n";
			$arr['date'] = shortDate(strtotime($arr['date']));
			dumpCSVRow($arr, array_keys($cols));
		}
	}
	exit;
		
}

$lastDay = $payending ? date('Y-m-d', strtotime($payending)) : date('Y-m-d');  // TBD: figure out last day of current period
$lastDayPretty  = shortDate(strtotime($lastDay));
$currentPeriodDue = 0;
foreach(getUnpaidPayables($lastDay, $prov) as $payable) {
	$currentPeriodDue += $payable['amount'] - $payable['paid'];
}

foreach(getNegativePayments($lastDay, $prov, $firstDay=null) as $payable) {
	if(mattOnlyTEST()) $currentPeriodDue -= ($payable['amount'] - $payable['paid']);
}

$where = $paystarting ? "paymentdate >= '".date('Y-m-d', strtotime($paystarting))."'" : '';
if($payending)
	$where .= ($where ? ' AND ' : '')."paymentdate <= '".date('Y-m-d', strtotime($payending))."'";

$columns = explodePairsLine('paymentdate|Payment Date||enddate|Period Ending||paymenttype|Payment Type||amount|Amount||transactionid|Check / Transaction ID');
$colClasses = array('amount'=>'dollaramountcell');
$link = paymentDetailLink($prov, 'regular', 'current', $currentPeriodDue);
$currentPeriodDue = dollarAmount($currentPeriodDue);
$rows[] = array('#CUSTOM_ROW#'=>"<tr style='background: lightgrey'><td colspan=2 style='font-weight:bold;text-align:right;padding-right:20px;'>Pay due through $lastDayPretty</td><td class='sortableListCell'>$link</th><td class='dollaramountcell'>$currentPeriodDue</td>".
	                  "<td>&nbsp;</td></tr>");
$payments = getProviderPayments($prov, $where, 'enddate DESC');
foreach($payments as $payment) {
	$row = array();
	$row['enddate'] = $payment['enddate'] ? shortDate(strtotime($payment['enddate'])) : '';
	$row['paymentdate'] = shortDate(strtotime($payment['paymentdate']));
	$row['sortdate'] = date('Y-m-d', strtotime($payment['paymentdate']));
	$row['paymenttype'] = paymentDetailLink($prov, $payment['paymenttype'], $payment['paymentid'], $payment['amount']);
	$row['amount'] = dollarAmount($payment['amount']);
	$row['transactionid'] = $payment['transactionid'];
	$rows[] = $row;
}
$bookeepingNegComps = fetchAssociations(
	"SELECT tblnegativecomp.* FROM tblnegativecomp 
		LEFT JOIN relproviderpayablepayment ON payableptr = negcompid AND negative = 1 
		WHERE tblnegativecomp.providerptr = $prov AND
			providerpaymentptr IS NULL AND date  >= '".date('Y-m-d', strtotime($paystarting))
			."' AND date <= '".date('Y-m-d', strtotime($payending))."'");
foreach($bookeepingNegComps as $neg) {
	$row = array();
	$row['enddate'] = '';
	$row['paymentdate'] = shortDate(strtotime($neg['date']));
	$row['sortdate'] = date('Y-m-d', strtotime($neg['date']));
	$row['paymenttype'] = negCompLink($prov, $neg['negcompid']);
	$row['amount'] = dollarAmount(0 - $neg['amount']);
	$row['transactionid'] = $neg['reason'];
	$rows[] = $row;
}

function inDateOrder($a, $b) {
	return strcmp($a['sortdate'], $b['sortdate']);
}

usort($rows, 'inDateOrder');

$numFound = count($payments);
$searchResults = ($numFound ? $numFound : 'No')." payment".($numFound == 1 ? '' : 's')." found.  ";

echo "$searchResults";
?>

<br>
<?
tableFrom($columns, $rows, 'WIDTH=100%', null, null, null, 'sortableListCell', null, null, $colClasses);
echo "<p>";

function paymentDetailLink($prov, $label, $paymentId, $amount) {
	global $payTypes, $lastDay;
	$label = isset($payTypes[$label]) ? $payTypes[$label] : $label;
	if($amount == 0) return "<span title='There are no payables to show.'>$label</span>";
	else if($paymentId == 'current')
		return fauxLink($label, "openConsoleWindow(\"paydetail\", \"provider-payables.php?id=$prov&through=$lastDay\",700,600)",1, 'Show details');
	if($_SESSION['preferences']['sittersPaidHourly'] and userRole() == 'p') $extra = "&colsup=amount";
	return fauxLink($label, "openConsoleWindow(\"paydetail\", \"payment-detail.php?id=$paymentId$extra\",700,600)",1, 'Show details');
}

function negCompLink($prov, $negcompid) {
	return fauxLink("Negative Compensation", "openConsoleWindow(\"paydetail\", \"neg-compensation-edit.php?id=$negcompid\",700,600)",1, 'Show details');
}
?>