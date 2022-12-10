<? // client-account-analysis.php
/*
Show details for all unpaid billables
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "provider-fns.php";
require_once "appointment-fns.php";
require_once "surcharge-fns.php";
require_once "gui-fns.php";

$locked = locked('o-');

$id = $_REQUEST['id'];

$client = getClient($id);

if(!$client) {
	echo "Client not found [$id]";
	exit;
}

$numberCompletedVisits = fetchRow0Col0("SELECT count(*) FROM tblappointment WHERE clientptr = $id AND completed IS NOT NULL");
$completedVisitCharges = fetchRow0Col0("SELECT sum(charge+ifnull(adjustment,0)) FROM tblappointment WHERE clientptr = $id AND completed IS NOT NULL");
$numberCompletedSurcharges = fetchRow0Col0("SELECT count(*) FROM tblsurcharge WHERE clientptr = $id AND completed IS NOT NULL");
$completedSurchargeCharges = fetchRow0Col0("SELECT sum(charge) FROM tblsurcharge WHERE clientptr = $id AND completed IS NOT NULL");
$numberMiscCharges = fetchRow0Col0("SELECT count(*) FROM tblothercharge WHERE clientptr = $id");
$miscCharges = fetchRow0Col0("SELECT sum(amount) FROM tblothercharge WHERE clientptr = $id");
$numberMonthlies = fetchRow0Col0(
	"SELECT count(*)
		FROM tblbillable 
		WHERE clientptr = $id
			AND monthyear IS NOT NULL
			AND superseded = 0
		ORDER BY monthyear");
$monthlies = fetchRow0Col0(
	"SELECT sum(charge-IFNULL(tax,0))
		FROM tblbillable 
		WHERE clientptr = $id
			AND monthyear IS NOT NULL
			AND superseded = 0
		ORDER BY monthyear");
			



$numberBillables = fetchRow0Col0(
	"SELECT count(*)
		FROM tblbillable 
		WHERE clientptr = $id
			AND superseded = 0");
			
$billableCharges = fetchRow0Col0(
	"SELECT sum(charge)
		FROM tblbillable 
		WHERE clientptr = $id
			AND superseded = 0");
			
$billableTaxes = fetchRow0Col0(
	"SELECT sum(ifnull(tax,0))
		FROM tblbillable 
		WHERE clientptr = $id
			AND superseded = 0");
			
$numberPayments = fetchRow0Col0(
	"SELECT count(*)
		FROM tblcredit 
		WHERE clientptr = $id
			AND payment = 1");
			
$payments = fetchRow0Col0(
	"SELECT sum(amount)
		FROM tblcredit 
		WHERE clientptr = $id
			AND payment = 1");
			
			
require_once "frame-bannerless.php";
echo "<style>.dolla {text-align:right;}</style>";
echo "<h2>Account Analysis</h2>";
echo "<table border=1 bordercolor=gray><tr><th><th>Count<th>Total<th>Tax";
echo "<tr><td>Completed Visits<td>$numberCompletedVisits<td class='dolla'>".dollarAmount($completedVisitCharges);
echo "<tr><td>Completed Surcharges<td>$numberCompletedSurcharges<td class='dolla'>".dollarAmount($completedSurchargeCharges);
echo "<tr><td>Miscellaneous Charges<td>$numberMiscCharges<td class='dolla'>".dollarAmount($miscCharges);
echo "<tr><td>Monthly Schedules<td>$numberMonthlies<td class='dolla'>".dollarAmount($monthlies);
echo "<tr><td>Total (untaxed) charges<td>&nbsp;<td class='dolla'>".dollarAmount($completedVisitCharges+$completedSurchargeCharges+$miscCharges);
echo "<tr><td colspan=4>===============";
echo "<tr><td>Billables<td>$numberBillables<td class='dolla'>".dollarAmount($billableCharges)
			."<td class='dolla'>".dollarAmount($billableTaxes);
echo "<tr><td>Payments<td>$numberPayments<td class='dolla'>".dollarAmount($payments);
echo "<tr><td>Difference<td>&nbsp;<td class='dolla'>".dollarAmount($billableCharges-$payments);
echo "</table>";

if($_GET['detail']) {
	$visits = fetchAssociations(
		"SELECT a.*, CONCAT_WS(' ', date, starttime) as sort, tax, paid, a.charge+ifnull(adjustment,0) as pretax, billableid,
				tblbillable.charge as billableamount
			FROM tblappointment a
			LEFT JOIN tblbillable ON itemtable = 'tblappointment' AND itemptr = appointmentid AND superseded = 0
			WHERE a.clientptr = $id AND completed IS NOT NULL");
	$surcharges = fetchAssociations(
		"SELECT s.*, CONCAT_WS(' ', date, starttime) as sort, tax, paid, s.charge as pretax, billableid,
				tblbillable.charge as billableamount
			FROM tblsurcharge s
			LEFT JOIN tblbillable ON itemtable = 'tblsurcharge' AND itemptr = surchargeid AND superseded = 0
			WHERE s.clientptr = $id AND completed IS NOT NULL");
	$miscccharges = fetchAssociations(
		"SELECT o.*, CONCAT_WS(' ', issuedate) as sort, tax, paid, o.amount as pretax, issuedate as date, billableid
			FROM tblothercharge o
			LEFT JOIN tblbillable ON itemtable = 'tblothercharge' AND itemptr = chargeid
			WHERE o.clientptr = $id");
			
	$payments = fetchAssociations(
		"SELECT *
			FROM tblcredit 
			WHERE clientptr = $id
				AND payment = 1
				AND amount > 0
			ORDER BY issuedate");
			
	$monthlies = fetchAssociations(
		"SELECT *, charge-IFNULL(tax,0) as pretax, itemdate as sort, itemdate as date
			FROM tblbillable 
			WHERE clientptr = $id
				AND monthyear IS NOT NULL
				AND superseded = 0
			ORDER BY monthyear");
			
			
	$servicetypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	$surchargetypes = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");
	
	
	foreach($visits as $item) $items[] = $item;
	foreach($surcharges as $item) $items[] = $item;
	foreach($miscccharges as $item) $items[] = $item;
	foreach($monthlies as $item) $items[] = $item;
	function cmpitem($a, $b) { return strcmp($a['sort'], $b['sort']); }
	usort($items, 'cmpitem');
	echo "<a name='charges'></a>";
	echo "<h2>Charges</h2><p><table border=1 bordercolor=gray><tr><th>Date<th>Time<th>Description<th>Charge<th>Tax<th>Paid<th>Credit/Payment #";
	$csvout = "Charges\nDate,Time,Description,Charge,Tax,Paid,Credit/Payment #s\n";

	foreach($items as $i) {
		$pretax += $i['pretax'];
		$tax += $i['tax'];
		$paid += $i['paid'];
	}
	echo "<tr><td><td><td class='dolla'><b>Total</b><td>".dollarAmount($pretax, 'cents', '&nbsp;')
				."<td>".dollarAmount($tax, 'cents', '&nbsp;')
				."<td>".dollarAmount($paid, 'cents', '&nbsp;');
	foreach($items as $i) {
		$paymentIds = $i['billableid'] && $i['paid'] 
			? join(', ', fetchCol0("SELECT paymentptr FROM relbillablepayment WHERE billableptr = {$i['billableid']}", 1))
			: '';
		$date = shortDate(strtotime($i['date']));
		$tod = $i['timeofday'];
		echo "<tr><td>$date";
		echo "<td>$tod";
		$canceled = $i['canceled'] ? "style='color:red'" : '';
		$item = $i['servicecode'] ? $servicetypes[$i['servicecode']] : (
									$i['surchargecode'] ? $surchargetypes[$i['surchargecode']] : (
									$i['monthyear'] ? 'Monthly Schedule '.date('F Y', strtotime($i['monthyear'])) : 
									$i['reason']));
		echo "<td $canceled>$item";
		
		$warning = $i['billableamount'] != $i['pretax'] + $i['tax'] ? 'warning' : '';
		$warningNote = !$warning ? ''
			: "Current item charge+tax ({$i['pretax']}+{$i['tax']}) does not equal billable charge ({$i['billableamount']})";
		$warningNote = $warningNote ? "title='$warningNote'" : '';
		$pretax = $i['pretax'] > 0 ? $i['pretax'] : null;
		echo "<td class='dolla $warning' $warningNote>".dollarAmount($pretax, 'cents', '&nbsp;');
		$tax = $i['tax'] > 0 ? $i['tax'] : null;
		echo "<td class='dolla'>".dollarAmount($tax, 'cents', '&nbsp;');
		
		
		$paid = $i['paid'] > 0 ? $i['paid'] : null;
		$warning = $i['paid'] < $i['pretax'] + $i['tax'] ? 'warning' : '';
		$warningNote = !$warning ? '' : "Paid amount does not equal total charge.";
		$warningNote = $warningNote ? "title='$warningNote'" : '';
		echo "<td class='dolla $warning' $warningNote>".dollarAmount($paid, 'cents', '&nbsp;');
		echo "<td>$paymentIds</td>";
		$pretax = numnum(($pretax ? $pretax : 0));
		$tax = numnum(($tax ? $tax : 0));
		$paid = numnum(($paid ? $paid : 0));
		$csvout .= "$date,$tod,$item,$pretax,$tax,$paid,\"$paymentIds\"\n";
	}
	echo "</table>";
	
	foreach($payments as $p) $totalPay += $p['amount'];
	echo "<h2>Payments</h2><p><table border=1 bordercolor=gray><tr><th>Date<th>Amount<th>Credit/Payment #";
	$csvout .= "\n\nPayments\nDate,Amount,Credit/Payment #\n";
	echo"<tr><td class='dolla'><b>Total</b><td>".dollarAmount($totalPay, 'cents', '&nbsp;');
	foreach($payments as $p) {
		$date = shortDate(strtotime($p['issuedate']));
		$amount = $p['amount'];
		$creditid = $p['creditid'];
		echo "<tr><td>$date"
					."<td class='dolla'>".dollarAmount($amount)
					."<td>$creditid";
		$csvout .= "$date,".numnum($amount).",$creditid\n";
	}
	echo "</table>";
	if($_REQUEST['csv']) echo "<a name='csv'></a><hr><pre>$csvout</pre>";
}

function numnum($num) {
	return number_format($num, 2, '.', '');
}