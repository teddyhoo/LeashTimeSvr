<? // reports-account-running.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "client-fns.php";

$failure = false;
// Determine access privs
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
$locked = locked('o-#vr');
extract(extractVars('id', $_REQUEST));

$client = getOneClientsDetails($id, array('fullname'));

$month = $month ? $month : date('F');
		
$pageTitle = "Running Tally for Client {$client['clientname']} <font color=red> BETA</font>";


$paymentsAndCredits = fetchAssociations(
	"SELECT *
		FROM tblcredit
		WHERE clientptr = $id AND 
			(payment = 1 
			 OR (reason NOT LIKE '%(v:%'
			 			OR reason NOT LIKE 'Billable [%'))");
foreach($paymentsAndCredits as $i => $each) {
	$each['time'] = strtotime($each['issuedate']);
	$each['ddate'] = shortDateAndTime($each['time']);
	$grats = fetchRow0Col0("SELECT sum(amount) FROM tblgratuity WHERE paymentptr = {$each['creditid']}");
	$xtra = !$grats ? '' : " + ".dollarAmount($grats)." = total: ".dollarAmount($each['amount']+$grats);
	if($each['voidedamount']) $xtra .= "<span class='warning'> VOIDED: {$each['voidedamount']}</span>";
	$dam = '<b>'.dollarAmount($each['amount']).'</b>'.$xtra;
	$each['label'] = $each['payment'] ? "Payment: $dam" : "Credit: $dam {$credit['reason']}";
	$rows[] = $each;
	$total += $each['amount'];
}
			 			
$serviceTypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
$visitResults = fetchAssociations(
	"SELECT *
		FROM tblappointment
		WHERE clientptr = $id AND canceled IS NULL");
foreach($visitResults as $i => $each) {
	$each['time'] = strtotime("{$each['date']} {$each['starttime']}");
	$each['ddate'] = shortDateAndTime($each['time']);
	$each['label'] = $serviceTypes[$each['servicecode']];
	$each['deduction'] = $each['charge'] + ($each['adjustment'] ? $each['adjustment'] : 0);
	$rows[] = $each;
}
		
$surchTypes = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
$surchargeResults = fetchAssociations(
	"SELECT *
		FROM tblsurcharge
		WHERE clientptr = $id AND canceled IS NULL");
foreach($surchargeResults as $i => $each) {
	$each['time'] = strtotime("{$each['date']} ".($each['starttime'] ? $each['starttime'] : '00:00:00'));
	$each['ddate'] = shortDateAndTime($each['time']);
	$each['label'] = $surchTypes[$each['surchargecode']];
	$each['deduction'] = $each['charge'];
	$rows[] = $each;
}
		
$miscChargeResults = fetchAssociations(
	"SELECT *
		FROM tblothercharge
		WHERE clientptr = $id");
foreach($miscChargeResults as $i => $each) {
	$each['time'] = strtotime("{$each['issuedate']} 00:00:00");
	$each['ddate'] = shortDateAndTime($each['time']);
	$each['label'] = "Misc Charge: {$each['reason']}";
	$each['deduction'] = $each['amount'];
	$rows[] = $each;
}

function cmpRows($a, $b) {
	$a = $a['time'];
	$b = $b['time'];
	return $a == $b ? 0 : ($a < $b ? -1 : 1);
}

usort($rows, 'cmpRows');

foreach($rows as $i => $each)
	$totalCharges += $each['deduction'];
	
	
if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a> - <a href='client-edit.php?id={$client['clientid']}'>{$client['clientname']}</a>";
		
	include "frame.html";
	
	// ***************************************************************************

	echo "This report <ul>
		<li>Includes all uncanceled visits and surcharges, regardless of completion.
		<li>Includes miscellaneous charges.
		<li>ignores refunds, monthly charges, discounts, and taxes.</ul><p>";
	echo "Total payments ".dollarAmount($total)."<p>";
	echo "Total charges ".dollarAmount($totalCharges)."<p>";
	foreach($rows as $i => $each) {
		if($each['creditid']) {
			$rtotal += $each['amount'];
			$rows[$i]['balance'] = dollarAmount($rtotal);
			$rowClasses[$i] = 'paymenttask';
		}
		else {
			$total -= $each['deduction'];
			$rtotal -= $each['deduction'];
			$rows[$i]['balance'] = dollarAmount($rtotal);
			$rows[$i]['deduction'] = dollarAmount($each['deduction']);
			$rowClasses[$i] = $each['time'] < time() ? null : 'futuretaskEVEN';
		}
	}
	$columns = explodePairsLine('ddate|Date||label|Description||deduction|Deduction||balance|Balance');
	tableFrom($columns, $rows, 'width=100% border=1', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses=null, $sortClickAction);
	include "frame-end.html";
}