<? 
// reports-actual-tax-liability.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "projections.php";
require_once "service-fns.php";
require_once "tax-fns.php";

$failure = false;
if($_REQUEST['print']) $auxiliaryWindow = true; // prevent login from appearing here if session times out
// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('start,end,print,reportType,csv', $_REQUEST));

		
$pageTitle = "Invoices with Taxes by Client";
include "frame.html";

$invoices = fetchAssociations("SELECT m.*, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(' ', lname, fname) as sortname
FROM tblmessage m
LEFT JOIN tblclient on clientid = correspid
WHERE subject LIKE '%invoice%'
AND body LIKE '%GST%'
ORDER BY sortname, datetime");

foreach($invoices as $i => $inv) {
	$taxstart = strpos($inv['body'], '>GST');
	if($taxstart == FALSE) $taxstart = strpos($inv['body'], '>Tax');
	$taxstart = strpos($inv['body'], '&#36; ', $taxstart);
	$taxend = strpos($inv['body'], '<', $taxstart);
	$invoices[$i]['tax'] = substr($inv['body'], $taxstart, ($taxend - $taxstart));	
//echo "[{$inv['clientptr']}] ".substr($inv['body'], $taxstart, 100); exit; 
	$amtstart = strpos($inv['body'], '&#36; ', strpos($inv['body'], 'Amount Due'));
	$amtend = strpos($inv['body'], '<', $amtstart);
	$invoices[$i]['amount'] = substr($inv['body'], $amtstart, ($amtend - $amtstart));	
	$invoices[$i]['datetime'] = shortDateAndTime(strtotime($inv['datetime']));	
	
	//$lastClientptr = $inv['clientptr'];
}

$columns = explodePairsLine('name|Client||datetime|Sent||amount|Total||tax|GST');

tableFrom($columns, $invoices, "border=1 bordercolor=black", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');

?><h2>Billables with GST</h2><?

$billables = fetchAssociations(
	"SELECT b.*, CONCAT_WS(' ', fname, lname) as name, CONCAT_WS(' ', lname, fname) as sortname
	FROM tblbillable b
	LEFT JOIN tblclient on clientid = clientptr
	WHERE superseded = 0 AND tax > 0 AND tax IS NOT NULL
	ORDER BY sortname, itemdate");
	
$total = 0;
$grandTotal = 0;
foreach($billables as $i => $b) {
	$billables[$i]['itemdate'] = shortDate(strtotime($b['itemdate']));
	if($lastClientptr && $lastClientptr != $b['clientptr']) {
		$billables[$i-1]['total'] = dollarAmount($total);
		$billables[$i-1]['name'] = "<b>{$billables[$i-1]['name']}</b>";
		$total = 0;
	}
	$total += $b['tax'];
	$grandTotal += $b['tax'];
	$lastClientptr = $b['clientptr'];
	$billables[$i]['chargeDollar'] = dollarAmount($b['charge']);
	$billables[$i]['taxDollar'] = dollarAmount($b['tax']);
}
$billables[$i]['total'] = dollarAmount($total);
$billables[$i]['name'] = "<b>{$billables[$i-1]['name']}</b>";

$billables[] = array('taxDollar'=>'<b>TOTAL</b>', 'total'=>dollarAmount($grandTotal));
$columns = explodePairsLine('name|Client||itemdate|Item Date||chargeDollar|Charge||taxDollar|GST||total|Total GST');

tableFrom($columns, $billables, "border=1 bordercolor=black", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses=null, $colClasses, 'sortClick');

include "frame-end.html";
