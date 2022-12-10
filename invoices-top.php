<? // invoices-top.php -- show current invoices and invoices past due
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
pageTimeOn();  // sets $page_start_time


require_once "js-gui-fns.php";
require_once "invoice-gui-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-');

extract($_REQUEST);


$pastDueDays = $_SESSION['preferences']['pastDueDays'];
$pastDueDays = strlen(''.$pastDueDays) == '0' ? 30 : $pastDueDays;

$billDays = array();
if(is_numeric($_SESSION['preferences']['bimonthlyBillOn1'])) $billDays[] = $_SESSION['preferences']['bimonthlyBillOn1'];
if(is_numeric($_SESSION['preferences']['bimonthlyBillOn2'])) $billDays[] = $_SESSION['preferences']['bimonthlyBillOn2'];
sort($billDays);
$billDays = array_reverse($billDays);
$billDay = 31;  // in case no bill days specified
foreach($billDays as $billDay)
	if(date('j') >= $billDay) break; // date('j') = day of month
	
$originalBillDay = $billDay;

$billDay = sprintf('%02d', min($billDay, date('t')));

$lastBillDate = date("Y-m-$billDay");
if($lastBillDate > date('Y-m-d')) {
	$lastDayLastMonth = date('t', strtotime('-1 month'));
	$billDay = min($originalBillDay, date('t', strtotime('-1 month')));
	$lastBillDate = date('Y-m-', strtotime("-1 month")).sprintf('%02d', $lastDayLastMonth);
}

if($asOfDate) $throughDateInt = strtotime($asOfDate);
if(!$throughDateInt) {
	$throughDateInt = strtotime($lastBillDate);
	$asOfDate = date('Y-m-d', $throughDateInt);
}

$pageTitle = "Client Invoices";

include "frame.html";
// ***************************************************************************
/*
Sections:
Current Invoices
	Invoice Previews 
		- if there are uninvoiced billables
		- lists NOT INVOICED for notification
		- links to invoice-create.php
	Invoice
		- last invoice if no uninvoiced billables
		- lists as of date (= date of last billable)
		- links to invoice viewer
		- has email and print links

Invoices Past Due 15 Days
Invoices Past Due 30 Days
Invoices Past Due 45 Days
	- all invoices

*/
?>
<style>
.bluebar {width:99.85%;border:solid black 1px;font-weight:bold;background:lightblue;height:20px;
					text-align:center;font-size:1.1em;padding-top:5px;margin-bottom:2px;}
.tabrow {width:98.5%;}					
.tabrow td {padding:0px;}					
.tabplain {margin:2px;padding: 10px 4px 10px 4px;text-align:center;font: bold 1.1em arial,sans-serif;background:palegreen;}
.tabselected {margin:2px;padding: 10px 4px 10px 4px;text-align:center;font: bold 1.3em arial,sans-serif;background:#CCFFCC;}
</style>
<?
if(isset($listInvoices) && $listInvoices) {
		echo "<span style='font-size:1.1em'>";
		echo "Information through ";
		calendarSet('', 'asOfDate', $asOfDate);
		echo " ";
		echoButton('', 'Show', "changeAsOfDate({$invoice['clientptr']})");
		echo " ";
		labeledCheckbox('Show all clients', 'showAllClients', $showAllClients, null, null, "changeAsOfDate({$invoice['clientptr']})");
		echo "<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>";
}

echo "<span style='background:palegreen'>Last Billing Day was ".date("F j, Y", strtotime($lastBillDate))."</span>";

if($_SESSION['staffuser']) screenLog("Script: {$_SERVER['SCRIPT_NAME']}");
$uTime = microtime(1);
$clientDetails = getClientDetails(fetchCol0("SELECT clientid FROM tblclient"), array('invoiceby', 'email'), 'sorted');
if($_SESSION['staffuser']) screenLog("Time to getClientDetails: ".round((microtime(1)-$uTime)*1000)." ms");
$uTime = microtime(1);
$uninvoicedCharges = getUninvoicedCharges(null, date('Y-m-d', $throughDateInt));

 //echo "found 14681:".in_array(14681, $uninvoicedCharges)." and 14682: ".in_array(14682, $uninvoicedCharges)."<p>";
if($_SESSION['staffuser']) screenLog("Time to getUninvoicedCharges: ".round((microtime(1)-$uTime)*1000)." ms");
$totalUninvoiced = dollarAmount(array_sum($uninvoicedCharges));
$totalAcctBal = dollarAmount(getInvoicedAccountBalanceTotal());
$uTime = microtime(1);
$incompleteJobCounts = countAllIncompleteJobsByClient(date('Y-m-d', $throughDateInt));
if($_SESSION['staffuser']) screenLog("Time to countAllIncompleteJobsByClient: ".round((microtime(1)-$uTime)*1000)." ms");
echo "<p><div style='padding: 3px;border: solid black 1px;'><b>Total Accounts Due: <span id='totalacctbal'>$totalAcctBal</span>
			<img src='art/spacer.gif' WIDTH=20 HEIGHT=1>Total Uninvoiced Services: $totalUninvoiced (through ".date('F j', strtotime($asOfDate)).")</div>";
echo "</span><p>";
$showAllClients = isset($showAllClients) ? $showAllClients : false;;


$tabWidths = 200;
$labelAndIds = array("all"=>'All Invoices');
if($_SESSION['ccenabled'] && adequateRights('*cc')) 
	$labelAndIds["autopay"] = "AutoPay Invoices";
$labelAndIds = array_merge($labelAndIds, array("email"=>"Email Invoices", "mail"=>'Mail Invoices', "overdue"=>'Overdue Invoices', "incomplete"=>"Incomplete Visits"));



echo "<table class='tabrow'><tr>";
foreach($labelAndIds as $key => $label) {
	$w = (100 / count($labelAndIds));
	echo "<td style='width:$w%'>";
	if($tab == $key) echo "<div class='tabselected'>$label</div>";
	else echo "<div class='tabplain'><a href='invoices-$key.php?asOfDate=$asOfDate'>$label</a></div>";
	echo "</td>";
}
echo "</tr></table>";

if($_SESSION['staffuser']) screenLog("Time to run invoices-top.php: ".round((microtime(1)-$page_start_time)*1000)." ms");

