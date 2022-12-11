<? // client-unpaid-billables.php
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

$billables = fetchAssociations(
	"SELECT * 
		FROM tblbillable 
		WHERE clientptr = $id
			AND superseded = 0
			AND paid < charge
		ORDER BY itemdate");

$map = explodePairsLine('tblappointment|Visit||tblsurcharge|Surcharge||tblothercharge|Misc Charge');
$servicetypes = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
$surchargetypes = fetchKeyValuePairs("SELECT surchargetypeid, label FROM tblsurchargetype");

foreach($billables as $i => $b) {
	$totalOwed += $b['charge'] - $b['paid'];
	$billables[$i]['date'] = shortDate(strtotime($b['itemdate']));
	$billables[$i]['type'] = $map[$b['itemtable']];
	$billables[$i]['owed'] = dollarAmount($b['charge'] - $b['paid']);
	$billables[$i]['paid'] = dollarAmount($b['paid']);
	$billables[$i]['charge'] = dollarAmount($b['charge']);
	if($b['itemtable'] == 'tblappointment') {
		$appt = getAppointment($b['itemptr']);
		$billables[$i]['date'] .= " {$appt['timeofday']}";
		$billables[$i]['label'] = $servicetypes[$appt['servicecode']]." ({$appt['provider']})";
		$billables[$i]['label'] = 
			fauxLink($billables[$i]['label'], 
				"openConsoleWindow(\"visitdetail\", \"appointment-edit.php?id={$appt['appointmentid']}\", 800, 300);", 1, 'edit visit')." [b: {$b['billableid']}]";
		$collectedBillableIDs[] = $b['billableid'];
	}
	else if($b['itemtable'] == 'tblsurcharge') {
		$surch = getSurcharge($b['itemptr']);
		$billables[$i]['label'] = $surchargetypes[$surch['surchargecode']];
	}
	else if($b['itemtable'] == 'tblothercharge') {
		$billables[$i]['label'] = 
			fetchRow0Col0("SELECT reason FROM tblothercharge WHERE chargeid = {$b['itemptr']} LIMIT 1");
		$billables[$i]['label'] .=  " [b: {$b['billableid']}]";
	}
	else if($b['itemtable'] == 'tblrecurringpackage') {
		$billables[$i]['type'] = "Monthly";
		$billables[$i]['label'] = date('M Y', strtotime($billables[$i]['monthyear']));
	}
}
if($billables) $billables[] = array('paid'=>"<b>Total:</b>", 'owed'=>dollarAmount($totalOwed));

	
$columns = explodePairsLine('date|Date||type|Type||charge|Charge||paid|Paid||owed|Still Owed||label|Detail');

$extraHeadContent = "<script language='javascript' src='common.js'></script>";
include "frame-bannerless.php";

if(!$billables) echo "No unpaid billables found for {$client['fname']} {$client['lname']}";
else {
	echo "<h2>{$client['fname']} {$client['lname']}'s Unpaid Billables</h2>";
	tableFrom($columns, $billables);
	
}

$unappliedCredits = fetchAssociations("SELECT * FROM tblcredit WHERE clientptr = $id AND amountused < amount");
if($unappliedCredits) {
	echo "<h3>Unapplied Credits</h3>";
	echo "<table border=1 bordercolor=gray>";
	echo "<tr><th>Date<th>Amount<th>Amount Used<th>Type<th>Note</tr>";
	foreach($unappliedCredits as $cred) {
		$date = date('m/d/Y', strtotime($cred['issuedate']));
		$type = $cred['payment'] ? 'payment' : 'credit';
		echo "<tr><td>$date<td>{$cred['amount']}<td>{$cred['amountused']}<td>$type<td>{$cred['reason']}</tr>";
	}
	echo "</table>";
}
if(!$_GET['detail']) echo "<p class='fontSize1_2em'><a href='?id=$id&detail=1#charges'>Show All Charges and Payments</a><p>";
else if(!$_GET['csv']) echo "<p class='fontSize1_2em'><a href='?id=$id&detail=1&csv=1#csv'>Show CSV for All Charges and Payments</a><p>";
require "client-account-analysis.php";